<?php
session_start();
require_once '../../bank/db.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar autenticação
if (!checkAuth()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$investimento_id = (int)($_POST['investimento_id'] ?? 0);

if ($investimento_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID do investimento inválido']);
    exit;
}

try {
    $pdo = getPDO();
    $pdo->beginTransaction();
    
    // Buscar dados do investimento
    $stmt = $pdo->prepare("
        SELECT 
            i.id,
            i.usuario_id,
            i.produto_id,
            i.valor_investido,
            i.rendimento_acumulado,
            i.dias_restantes,
            i.status,
            i.created_at,
            p.titulo as produto_titulo,
            p.duracao_dias,
            u.nome as usuario_nome
        FROM investimentos i
        JOIN produtos p ON i.produto_id = p.id
        JOIN usuarios u ON i.usuario_id = u.id
        WHERE i.id = ? AND i.usuario_id = ?
    ");
    $stmt->execute([$investimento_id, $user_id]);
    $investimento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$investimento) {
        echo json_encode(['success' => false, 'message' => 'Investimento não encontrado']);
        exit;
    }
    
    // Verificar se o investimento pode ser concluído
    if ($investimento['status'] !== 'ativo') {
        echo json_encode(['success' => false, 'message' => 'Este investimento não está ativo']);
        exit;
    }
    
    if ($investimento['dias_restantes'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Este investimento ainda não pode ser concluído']);
        exit;
    }
    
    // Buscar saldo atual da carteira
    $stmt = $pdo->prepare("
        SELECT 
            saldo_principal, 
            total_investido 
        FROM carteiras 
        WHERE usuario_id = ?
    ");
    $stmt->execute([$user_id]);
    $carteira = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$carteira) {
        echo json_encode(['success' => false, 'message' => 'Carteira não encontrada']);
        exit;
    }
    
    // Calcular valores a serem creditados
    $valor_principal = $investimento['valor_investido'];
    $valor_rendimentos = $investimento['rendimento_acumulado'];
    $valor_total = $valor_principal + $valor_rendimentos;
    
    // Atualizar saldo na carteira (devolver principal + rendimentos)
    $stmt = $pdo->prepare("
        UPDATE carteiras 
        SET saldo_principal = saldo_principal + ?,
            total_investido = total_investido - ?
        WHERE usuario_id = ?
    ");
    $stmt->execute([$valor_total, $valor_principal, $user_id]);
    
    // Marcar investimento como concluído
    $stmt = $pdo->prepare("
        UPDATE investimentos 
        SET status = 'concluido', 
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$investimento_id]);
    
    // Log da operação
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema 
        (usuario_id, acao, tabela_afetada, registro_id, dados_novos, ip_address) 
        VALUES (?, 'investimento_concluido_manual', 'investimentos', ?, ?, ?)
    ");
    $dados_log = json_encode([
        'investimento_id' => $investimento_id,
        'produto' => $investimento['produto_titulo'],
        'valor_principal' => $valor_principal,
        'valor_rendimentos' => $valor_rendimentos,
        'valor_total_creditado' => $valor_total,
        'conclusao_manual' => true
    ]);
    $stmt->execute([
        $user_id,
        $dados_log,
        $_SERVER['REMOTE_ADDR'] ?? 'cliente'
    ]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Investimento concluído com sucesso!',
        'dados' => [
            'investimento_id' => $investimento_id,
            'produto' => $investimento['produto_titulo'],
            'valor_principal' => $valor_principal,
            'valor_rendimentos' => $valor_rendimentos,
            'valor_total' => $valor_total,
            'novo_saldo' => $carteira['saldo_principal'] + $valor_total,
            'data_conclusao' => date('d/m/Y H:i')
        ]
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Erro ao concluir investimento: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno: ' . $e->getMessage()
    ]);
}
?>