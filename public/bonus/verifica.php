<?php
session_start();
require_once '../bank/db.php';

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

$codigo_bonus = strtoupper(trim($_POST['codigo_bonus'] ?? ''));

if (empty($codigo_bonus)) {
    echo json_encode(['success' => false, 'message' => 'Código de bônus obrigatório']);
    exit;
}

try {
    $pdo = getPDO();
    $pdo->beginTransaction();
    
    // Buscar dados do usuário e saldo atual
    $stmt = $pdo->prepare("
        SELECT 
            u.nome, 
            u.telefone,
            c.saldo_bonus,
            c.total_depositado
        FROM usuarios u 
        JOIN carteiras c ON u.id = c.usuario_id 
        WHERE u.id = ? AND u.status = 'ativo'
    ");
    $stmt->execute([$user_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
        exit;
    }
    
    // Buscar código de bônus
    $stmt = $pdo->prepare("
        SELECT 
            id,
            codigo,
            tipo,
            valor,
            descricao,
            uso_maximo,
            uso_atual,
            uso_por_usuario,
            valor_minimo_deposito,
            apenas_primeiro_uso,
            ativo,
            data_inicio,
            data_expiracao
        FROM bonus_codigos 
        WHERE codigo = ? AND ativo = 1
    ");
    $stmt->execute([$codigo_bonus]);
    $bonus = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$bonus) {
        echo json_encode(['success' => false, 'message' => 'Código de bônus inválido ou inativo']);
        exit;
    }
    
    // Verificar se o bônus ainda está dentro da data de validade
    if ($bonus['data_expiracao'] && strtotime($bonus['data_expiracao']) < time()) {
        echo json_encode(['success' => false, 'message' => 'Código de bônus expirado']);
        exit;
    }
    
    if ($bonus['data_inicio'] && strtotime($bonus['data_inicio']) > time()) {
        echo json_encode(['success' => false, 'message' => 'Código de bônus ainda não disponível']);
        exit;
    }
    
    // Verificar limite de uso global
    if ($bonus['uso_maximo'] && $bonus['uso_atual'] >= $bonus['uso_maximo']) {
        echo json_encode(['success' => false, 'message' => 'Código de bônus esgotado']);
        exit;
    }
    
    // Verificar quantas vezes o usuário já usou este código
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as usos 
        FROM bonus_utilizados 
        WHERE usuario_id = ? AND bonus_codigo_id = ?
    ");
    $stmt->execute([$user_id, $bonus['id']]);
    $uso_usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($uso_usuario['usos'] >= $bonus['uso_por_usuario']) {
        echo json_encode(['success' => false, 'message' => 'Você já utilizou este código o máximo de vezes permitido']);
        exit;
    }
    
    // Verificar se é apenas para primeiro uso e se o usuário já teve depósitos
    if ($bonus['apenas_primeiro_uso']) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_usos 
            FROM bonus_utilizados 
            WHERE usuario_id = ?
        ");
        $stmt->execute([$user_id]);
        $total_usos = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($total_usos['total_usos'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Este bônus é apenas para novos usuários']);
            exit;
        }
    }
    
    // Verificar valor mínimo de depósito se aplicável
    if ($bonus['valor_minimo_deposito'] > 0 && $usuario['total_depositado'] < $bonus['valor_minimo_deposito']) {
        echo json_encode([
            'success' => false, 
            'message' => "É necessário ter depositado pelo menos R$ " . number_format($bonus['valor_minimo_deposito'], 2, ',', '.') . " para usar este bônus"
        ]);
        exit;
    }
    
    // Calcular valor do bônus
    $valor_bonus = 0;
    
    switch ($bonus['tipo']) {
        case 'valor_fixo':
            $valor_bonus = $bonus['valor'];
            break;
            
        case 'percentual':
            // Para bônus percentual, aplicar sobre o total depositado
            $valor_bonus = ($usuario['total_depositado'] * $bonus['valor']) / 100;
            break;
            
        case 'produto_gratis':
            // Para produtos grátis, seria implementado de forma diferente
            echo json_encode(['success' => false, 'message' => 'Tipo de bônus não implementado ainda']);
            exit;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Tipo de bônus inválido']);
            exit;
    }
    
    if ($valor_bonus <= 0) {
        echo json_encode(['success' => false, 'message' => 'Valor de bônus inválido']);
        exit;
    }
    
    // Aplicar o bônus
    $stmt = $pdo->prepare("
        UPDATE carteiras 
        SET saldo_bonus = saldo_bonus + ? 
        WHERE usuario_id = ?
    ");
    $stmt->execute([$valor_bonus, $user_id]);
    
    // Registrar uso do bônus
    $stmt = $pdo->prepare("
        INSERT INTO bonus_utilizados 
        (usuario_id, bonus_codigo_id, codigo_usado, valor_concedido) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $bonus['id'], $codigo_bonus, $valor_bonus]);
    
    // Atualizar contador de uso do código
    $stmt = $pdo->prepare("
        UPDATE bonus_codigos 
        SET uso_atual = uso_atual + 1 
        WHERE id = ?
    ");
    $stmt->execute([$bonus['id']]);
    
    // Log da operação
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema 
        (usuario_id, acao, tabela_afetada, registro_id, dados_novos, ip_address) 
        VALUES (?, 'bonus_utilizado', 'bonus_utilizados', ?, ?, ?)
    ");
    $dados_log = json_encode([
        'codigo_bonus' => $codigo_bonus,
        'valor_concedido' => $valor_bonus,
        'tipo_bonus' => $bonus['tipo'],
        'descricao' => $bonus['descricao']
    ]);
    $stmt->execute([
        $user_id,
        $dados_log,
        $_SERVER['REMOTE_ADDR'] ?? 'cliente'
    ]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Bônus aplicado com sucesso!',
        'dados' => [
            'codigo' => $codigo_bonus,
            'valor_bonus' => $valor_bonus,
            'descricao' => $bonus['descricao'],
            'novo_saldo_bonus' => $usuario['saldo_bonus'] + $valor_bonus,
            'tipo' => $bonus['tipo']
        ]
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Erro ao verificar bônus: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno: ' . $e->getMessage()
    ]);
}
?>