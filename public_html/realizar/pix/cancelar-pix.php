<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

require '../../bank/db.php';

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!isset($data['external_reference'])) {
        echo json_encode(['success' => false, 'message' => 'External reference não fornecido']);
        exit;
    }
    
    $pdo = getDBConnection();
    $user_id = $_SESSION['user_id'];
    $external_reference = $data['external_reference'];
    
    // Verificar se o PIX pertence ao usuário e está pendente - TABELA CORRETA
    $stmt = $pdo->prepare("
        SELECT id, status 
        FROM pagamentos 
        WHERE user_id = :user_id 
        AND cod_referencia = :external_reference
        AND status IN ('Pendente', 'pendente', 'waiting')
    ");
    
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':external_reference', $external_reference);
    $stmt->execute();
    
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        echo json_encode(['success' => false, 'message' => 'PIX não encontrado ou já processado']);
        exit;
    }
    
    // Remover o PIX pendente - TABELA CORRETA
    $deleteStmt = $pdo->prepare("
        DELETE FROM pagamentos 
        WHERE id = :id 
        AND user_id = :user_id
    ");
    
    $deleteStmt->bindParam(':id', $payment['id']);
    $deleteStmt->bindParam(':user_id', $user_id);
    
    if ($deleteStmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'PIX cancelado com sucesso'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Erro ao cancelar PIX'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Erro interno: ' . $e->getMessage()
    ]);
}
?>