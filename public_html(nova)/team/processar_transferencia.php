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
    
    // CORREÇÃO: Buscar saldo de comissões na nova tabela 'carteiras'
    $stmt = $conn->prepare("
        SELECT saldo_comissao
        FROM carteiras 
        WHERE usuario_id = ?
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $saldo_comissoes = $result['saldo_comissao'] ?? 0;
    
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
    
    // CORREÇÃO: 1. Creditar no saldo principal da tabela 'carteiras'
    $stmt = $conn->prepare("UPDATE carteiras SET saldo_principal = saldo_principal + ? WHERE usuario_id = ?");
    $stmt->execute([$saldo_comissoes, $user_id]);
    
    // CORREÇÃO: 2. Zerar saldo de comissões na tabela 'carteiras'
    $stmt = $conn->prepare("UPDATE carteiras SET saldo_comissao = 0 WHERE usuario_id = ?");
    $stmt->execute([$user_id]);
    
    // 3. Registrar a transferência no histórico (se tabela existir)
    try {
        $stmt = $conn->prepare("
            INSERT INTO logs_sistema (
                usuario_id, acao, tabela_afetada, dados_novos, ip_address, created_at
            ) VALUES (?, 'transferencia_comissao', 'carteiras', ?, ?, NOW())
        ");
        $dados_log = json_encode([
            'valor_transferido' => $saldo_comissoes,
            'tipo' => 'comissao_para_principal'
        ]);
        $stmt->execute([$user_id, $dados_log, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {
        // Se tabela de logs não existir, continuar sem erro
        error_log("Erro ao registrar log: " . $e->getMessage());
    }
    
    // CORREÇÃO: 4. Buscar novo saldo do usuário na tabela 'carteiras'
    $stmt = $conn->prepare("SELECT saldo_principal, saldo_comissao FROM carteiras WHERE usuario_id = ?");
    $stmt->execute([$user_id]);
    $novos_saldos = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Confirmar transação
    $conn->commit();
    
    // Resposta de sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Transferência realizada com sucesso!',
        'dados' => [
            'valor_transferido' => $saldo_comissoes,
            'novo_saldo_principal' => $novos_saldos['saldo_principal'],
            'novo_saldo_comissoes' => $novos_saldos['saldo_comissao']
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