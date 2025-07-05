<?php
session_start();
header('Content-Type: application/json');

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit();
}

require '../bank/db.php';

try {
    $conn = getDBConnection();
    $user_id = $_SESSION['user_id'];
    
    // Buscar saldo de comissões do usuário
    $stmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(total_nivel1 + total_nivel2 + total_nivel3), 0) as saldo_comissoes
        FROM niveis_convite 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $saldo_comissoes = $result['saldo_comissoes'] ?? 0;
    
    // Verificar se tem saldo para transferir
    if ($saldo_comissoes <= 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Você não possui saldo de comissões para transferir'
        ]);
        exit();
    }
    
    // Iniciar transação
    $conn->beginTransaction();
    
    // 1. Creditar no saldo do usuário
    $stmt = $conn->prepare("UPDATE usuarios SET saldo = saldo + ? WHERE id = ?");
    $stmt->execute([$saldo_comissoes, $user_id]);
    
    // 2. Zerar saldo de comissões
    $stmt = $conn->prepare("
        UPDATE niveis_convite 
        SET total_nivel1 = 0, total_nivel2 = 0, total_nivel3 = 0 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    
    // 3. Registrar a transferência no histórico
    $stmt = $conn->prepare("
        INSERT INTO historico_transacoes (
            user_id, tipo, valor, descricao, status, data_transacao
        ) VALUES (?, 'comissao', ?, ?, 'concluido', NOW())
    ");
    $descricao = "Transferência de comissões para carteira principal";
    $stmt->execute([$user_id, $saldo_comissoes, $descricao]);
    
    // 4. Buscar novo saldo do usuário
    $stmt = $conn->prepare("SELECT saldo FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id]);
    $novo_saldo = $stmt->fetchColumn();
    
    // Confirmar transação
    $conn->commit();
    
    // Resposta de sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Transferência realizada com sucesso!',
        'dados' => [
            'valor_transferido' => $saldo_comissoes,
            'novo_saldo_principal' => $novo_saldo,
            'novo_saldo_comissoes' => 0
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Erro na transferência de comissões: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro interno do servidor. Tente novamente em alguns instantes.'
    ]);
}
?>