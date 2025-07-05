<?php
session_start();

header('Content-Type: application/json');

// Tentar obter user_id da sessão ou do POST
$user_id = null;

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} else {
    // Se não tem sessão, tentar pegar do POST
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (isset($data['user_id'])) {
        $user_id = $data['user_id'];
    }
}

if (!$user_id) {
    echo json_encode([
        'error' => 'Usuário não autenticado',
        'debug' => [
            'session_id' => session_id(),
            'session_data' => $_SESSION,
            'post_data' => $data ?? null
        ]
    ]);
    exit;
}

require '../../bank/db.php';

try {
    $pdo = getDBConnection();
    
    // Buscar PIX pendente do usuário (últimos 15 minutos) - TABELA CORRETA
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            cod_referencia as external_reference, 
            valor, 
            data as created_at,
            TIMESTAMPDIFF(MINUTE, data, NOW()) as minutes_elapsed
        FROM pagamentos 
        WHERE user_id = :user_id 
        AND status IN ('Pendente', 'pendente', 'waiting')
        AND data >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ORDER BY data DESC 
        LIMIT 1
    ");
    
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    $pending_payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($pending_payment) {
        $minutes_left = 10 - $pending_payment['minutes_elapsed'];
        
        if ($minutes_left > 0) {
            // Ainda tem tempo - tem PIX pendente
            echo json_encode([
                'has_pending' => true,
                'external_reference' => $pending_payment['external_reference'],
                'valor' => number_format($pending_payment['valor'], 2, ',', '.'),
                'minutes_left' => $minutes_left,
                'created_at' => $pending_payment['created_at'],
                'debug' => [
                    'user_id' => $user_id,
                    'minutes_elapsed' => $pending_payment['minutes_elapsed']
                ]
            ]);
        } else {
            // Expirou - pode remover e criar novo
            $deleteStmt = $pdo->prepare("DELETE FROM pagamentos WHERE id = :id");
            $deleteStmt->bindParam(':id', $pending_payment['id']);
            $deleteStmt->execute();
            
            echo json_encode([
                'has_pending' => false,
                'debug' => 'PIX expirado removido'
            ]);
        }
    } else {
        // Não tem PIX pendente
        echo json_encode([
            'has_pending' => false,
            'debug' => 'Nenhum PIX pendente encontrado'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Erro interno: ' . $e->getMessage()
    ]);
}
?>