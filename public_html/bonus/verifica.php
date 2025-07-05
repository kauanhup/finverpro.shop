<?php
session_start();
header('Content-Type: application/json');

// Verificar se usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit();
}

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

// Obter dados JSON
$input = json_decode(file_get_contents('php://input'), true);
$codigo = isset($input['codigo']) ? trim(strtoupper($input['codigo'])) : '';

if (empty($codigo)) {
    echo json_encode(['success' => false, 'message' => 'Código não informado']);
    exit();
}

try {
    require '../bank/db.php';
    $pdo = getDBConnection();
    
    // Iniciar transação
    $pdo->beginTransaction();
    
    // Verificar se o código existe e está ativo
    $stmt = $pdo->prepare("SELECT * FROM bonus_codigos WHERE codigo = ? AND ativo = 1");
    $stmt->execute([$codigo]);
    $bonus = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$bonus) {
        echo json_encode(['success' => false, 'message' => 'Código inválido ou inativo']);
        $pdo->rollBack();
        exit();
    }
    
    // Verificar se o código ainda tem usos disponíveis
    if ($bonus['usos_atuais'] >= $bonus['max_usos']) {
        echo json_encode(['success' => false, 'message' => 'Código esgotado']);
        $pdo->rollBack();
        exit();
    }
    
    // Verificar se o código não expirou
    if ($bonus['data_expiracao'] && strtotime($bonus['data_expiracao']) < time()) {
        echo json_encode(['success' => false, 'message' => 'Código expirado']);
        $pdo->rollBack();
        exit();
    }
    
    // Verificar se o usuário já resgatou este código
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bonus_resgatados WHERE user_id = ? AND codigo = ?");
    $stmt->execute([$_SESSION['user_id'], $codigo]);
    $jaResgatado = $stmt->fetchColumn();
    
    if ($jaResgatado > 0) {
        echo json_encode(['success' => false, 'message' => 'Você já resgatou este código']);
        $pdo->rollBack();
        exit();
    }
    
    // Obter saldo atual do usuário
    $stmt = $pdo->prepare("SELECT saldo FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $saldoAtual = $stmt->fetchColumn();
    
    if ($saldoAtual === false) {
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
        $pdo->rollBack();
        exit();
    }
    
    // Calcular novo saldo
    $novoSaldo = $saldoAtual + $bonus['valor'];
    
    // Atualizar saldo do usuário
    $stmt = $pdo->prepare("UPDATE usuarios SET saldo = ? WHERE id = ?");
    $stmt->execute([$novoSaldo, $_SESSION['user_id']]);
    
    // Registrar o resgate
    $stmt = $pdo->prepare("INSERT INTO bonus_resgatados (user_id, codigo, valor) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $codigo, $bonus['valor']]);
    
    // Atualizar contador de usos do código
    $stmt = $pdo->prepare("UPDATE bonus_codigos SET usos_atuais = usos_atuais + 1 WHERE codigo = ?");
    $stmt->execute([$codigo]);
    
    // Confirmar transação
    $pdo->commit();
    
    // Retornar sucesso
    echo json_encode([
        'success' => true, 
        'message' => 'Código resgatado com sucesso!',
        'valor' => $bonus['valor'],
        'novo_saldo' => $novoSaldo
    ]);
    
} catch (Exception $e) {
    // Reverter transação em caso de erro
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    // Log do erro (opcional)
    error_log("Erro ao resgatar código: " . $e->getMessage());
    
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>