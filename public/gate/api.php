<?php
session_start();
require_once '../bank/db.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar método e autenticação
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

if (!checkAuth()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    $pdo = getPDO();
    
    switch ($action) {
        case 'criar_pagamento':
            criarPagamento($pdo, $user_id);
            break;
            
        case 'consultar_saldo':
            consultarSaldo($pdo, $user_id);
            break;
            
        case 'atualizar_saldo':
            if (!checkAdmin($user_id)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Acesso negado']);
                exit;
            }
            atualizarSaldo($pdo);
            break;
            
        case 'verificar_status':
            verificarStatusPagamento($pdo, $user_id);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }
    
} catch (Exception $e) {
    error_log("Erro na API do gateway: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}

function criarPagamento($pdo, $user_id) {
    $valor = (float)($_POST['valor'] ?? 0);
    $metodo = $_POST['metodo'] ?? 'pix';
    $chave_pix = $_POST['chave_pix'] ?? '';
    $nome_titular = $_POST['nome_titular'] ?? '';
    
    if ($valor <= 0) {
        echo json_encode(['success' => false, 'message' => 'Valor inválido']);
        return;
    }
    
    // Buscar configurações
    $stmt = $pdo->prepare("
        SELECT valor FROM configuracoes 
        WHERE categoria = 'financeiro' AND chave = 'deposito_valor_minimo'
    ");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    $valor_minimo = $config ? (float)$config['valor'] : 25.00;
    
    if ($valor < $valor_minimo) {
        echo json_encode([
            'success' => false, 
            'message' => "Valor mínimo para depósito: R$ " . number_format($valor_minimo, 2, ',', '.')
        ]);
        return;
    }
    
    // Buscar dados do usuário
    $stmt = $pdo->prepare("
        SELECT u.telefone, u.nome, u.email 
        FROM usuarios u 
        WHERE u.id = ? AND u.status = 'ativo'
    ");
    $stmt->execute([$user_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
        return;
    }
    
    // Gerar código de referência único
    $codigo_referencia = 'DEP_' . time() . '_' . $user_id . '_' . mt_rand(1000, 9999);
    
    // Calcular taxa (se houver)
    $valor_taxa = 0;
    $valor_liquido = $valor - $valor_taxa;
    
    // Inserir na tabela de operações financeiras
    $stmt = $pdo->prepare("
        INSERT INTO operacoes_financeiras 
        (usuario_id, tipo, metodo, valor_solicitado, valor_taxa, valor_liquido, 
         chave_pix, nome_titular, codigo_referencia, gateway, status) 
        VALUES (?, 'deposito', ?, ?, ?, ?, ?, ?, ?, 'pixup', 'pendente')
    ");
    $stmt->execute([
        $user_id, $metodo, $valor, $valor_taxa, $valor_liquido,
        $chave_pix, $nome_titular, $codigo_referencia, 'pixup'
    ]);
    
    $operacao_id = $pdo->lastInsertId();
    
    // Simular integração com gateway de pagamento
    $qr_code = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==";
    $chave_pix_pagamento = "exemplo@pixup.com.br";
    
    // Log da operação
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema 
        (usuario_id, acao, tabela_afetada, registro_id, dados_novos, ip_address) 
        VALUES (?, 'pagamento_criado', 'operacoes_financeiras', ?, ?, ?)
    ");
    $dados_log = json_encode([
        'operacao_id' => $operacao_id,
        'valor' => $valor,
        'metodo' => $metodo,
        'codigo_referencia' => $codigo_referencia
    ]);
    $stmt->execute([
        $user_id,
        $dados_log,
        $_SERVER['REMOTE_ADDR'] ?? 'cliente'
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Pagamento criado com sucesso',
        'dados' => [
            'operacao_id' => $operacao_id,
            'codigo_referencia' => $codigo_referencia,
            'valor' => $valor,
            'valor_liquido' => $valor_liquido,
            'qr_code' => $qr_code,
            'chave_pix' => $chave_pix_pagamento,
            'tempo_expiracao' => 900 // 15 minutos
        ]
    ]);
}

function consultarSaldo($pdo, $user_id) {
    // Buscar saldo na carteira
    $stmt = $pdo->prepare("
        SELECT 
            c.saldo_principal,
            c.saldo_bonus,
            c.saldo_comissao,
            c.total_depositado,
            c.total_sacado,
            c.total_investido,
            u.telefone,
            u.nome
        FROM carteiras c
        JOIN usuarios u ON c.usuario_id = u.id
        WHERE c.usuario_id = ?
    ");
    $stmt->execute([$user_id]);
    $carteira = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$carteira) {
        // Criar carteira se não existir
        $stmt = $pdo->prepare("
            INSERT INTO carteiras (usuario_id) VALUES (?)
        ");
        $stmt->execute([$user_id]);
        
        $carteira = [
            'saldo_principal' => 0.00,
            'saldo_bonus' => 0.00,
            'saldo_comissao' => 0.00,
            'total_depositado' => 0.00,
            'total_sacado' => 0.00,
            'total_investido' => 0.00
        ];
    }
    
    echo json_encode([
        'success' => true,
        'dados' => [
            'saldo_principal' => (float)$carteira['saldo_principal'],
            'saldo_bonus' => (float)$carteira['saldo_bonus'],
            'saldo_comissao' => (float)$carteira['saldo_comissao'],
            'saldo_total' => (float)($carteira['saldo_principal'] + $carteira['saldo_bonus'] + $carteira['saldo_comissao']),
            'total_depositado' => (float)$carteira['total_depositado'],
            'total_sacado' => (float)$carteira['total_sacado'],
            'total_investido' => (float)$carteira['total_investido']
        ]
    ]);
}

function atualizarSaldo($pdo) {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $novo_saldo = (float)($_POST['novo_saldo'] ?? 0);
    $tipo_saldo = $_POST['tipo_saldo'] ?? 'principal';
    $motivo = $_POST['motivo'] ?? 'Ajuste administrativo';
    
    if ($user_id <= 0 || $novo_saldo < 0) {
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        return;
    }
    
    // Buscar saldo atual
    $stmt = $pdo->prepare("
        SELECT saldo_principal, saldo_bonus, saldo_comissao 
        FROM carteiras 
        WHERE usuario_id = ?
    ");
    $stmt->execute([$user_id]);
    $saldo_atual = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$saldo_atual) {
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
        return;
    }
    
    // Determinar campo a atualizar
    $campo_saldo = '';
    switch ($tipo_saldo) {
        case 'principal':
            $campo_saldo = 'saldo_principal';
            break;
        case 'bonus':
            $campo_saldo = 'saldo_bonus';
            break;
        case 'comissao':
            $campo_saldo = 'saldo_comissao';
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Tipo de saldo inválido']);
            return;
    }
    
    // Atualizar saldo
    $stmt = $pdo->prepare("
        UPDATE carteiras 
        SET {$campo_saldo} = ? 
        WHERE usuario_id = ?
    ");
    $stmt->execute([$novo_saldo, $user_id]);
    
    // Log da operação
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema 
        (usuario_id, acao, tabela_afetada, registro_id, dados_anteriores, dados_novos, ip_address) 
        VALUES (?, 'saldo_atualizado', 'carteiras', ?, ?, ?, ?)
    ");
    $dados_anteriores = json_encode(['saldo_anterior' => $saldo_atual[$campo_saldo]]);
    $dados_novos = json_encode([
        'saldo_novo' => $novo_saldo,
        'tipo_saldo' => $tipo_saldo,
        'motivo' => $motivo
    ]);
    $stmt->execute([
        $_SESSION['user_id'],
        $dados_anteriores,
        $dados_novos,
        $_SERVER['REMOTE_ADDR'] ?? 'admin'
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Saldo atualizado com sucesso',
        'dados' => [
            'saldo_anterior' => (float)$saldo_atual[$campo_saldo],
            'saldo_novo' => $novo_saldo,
            'tipo_saldo' => $tipo_saldo
        ]
    ]);
}

function verificarStatusPagamento($pdo, $user_id) {
    $codigo_referencia = $_POST['codigo_referencia'] ?? '';
    
    if (empty($codigo_referencia)) {
        echo json_encode(['success' => false, 'message' => 'Código de referência obrigatório']);
        return;
    }
    
    // Buscar status do pagamento
    $stmt = $pdo->prepare("
        SELECT 
            id,
            valor_liquido,
            status,
            data_processamento,
            created_at
        FROM operacoes_financeiras 
        WHERE codigo_referencia = ? AND usuario_id = ? AND tipo = 'deposito'
    ");
    $stmt->execute([$codigo_referencia, $user_id]);
    $operacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$operacao) {
        echo json_encode(['success' => false, 'message' => 'Pagamento não encontrado']);
        return;
    }
    
    $status_map = [
        'pendente' => 'Aguardando Pagamento',
        'processando' => 'Processando',
        'aprovado' => 'Aprovado',
        'rejeitado' => 'Rejeitado',
        'cancelado' => 'Cancelado'
    ];
    
    echo json_encode([
        'success' => true,
        'dados' => [
            'id' => $operacao['id'],
            'status' => $operacao['status'],
            'status_texto' => $status_map[$operacao['status']] ?? 'Desconhecido',
            'valor' => (float)$operacao['valor_liquido'],
            'data_criacao' => $operacao['created_at'],
            'data_processamento' => $operacao['data_processamento']
        ]
    ]);
}
?>