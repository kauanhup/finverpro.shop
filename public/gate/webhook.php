<?php
require_once '../bank/db.php';

header('Content-Type: application/json; charset=utf-8');

// Log do webhook para debug
$input = file_get_contents('php://input');
$headers = getallheaders();
$data = json_decode($input, true);

error_log("Webhook recebido: " . $input);

// Verificar se é um webhook válido
if (!$data || !isset($data['event']) || !isset($data['data'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados inválidos']);
    exit;
}

$event = $data['event'];
$transaction_data = $data['data'];

try {
    $pdo = getPDO();
    
    switch ($event) {
        case 'payment.approved':
            processarPagamentoAprovado($pdo, $transaction_data);
            break;
            
        case 'payment.cancelled':
        case 'payment.failed':
            processarPagamentoCancelado($pdo, $transaction_data);
            break;
            
        case 'payment.pending':
            processarPagamentoPendente($pdo, $transaction_data);
            break;
            
        default:
            error_log("Evento webhook não tratado: " . $event);
            echo json_encode(['message' => 'Evento não tratado']);
            exit;
    }
    
    echo json_encode(['status' => 'success']);
    
} catch (Exception $e) {
    error_log("Erro no webhook: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno']);
}

function processarPagamentoAprovado($pdo, $transaction_data) {
    $codigo_referencia = $transaction_data['external_reference'] ?? $transaction_data['id'] ?? '';
    $valor_pago = (float)($transaction_data['amount'] ?? 0);
    
    if (empty($codigo_referencia) || $valor_pago <= 0) {
        throw new Exception('Dados do pagamento inválidos');
    }
    
    $pdo->beginTransaction();
    
    try {
        // Buscar a operação financeira pelo código de referência
        $stmt = $pdo->prepare("
            SELECT 
                id, 
                usuario_id, 
                valor_liquido, 
                status 
            FROM operacoes_financeiras 
            WHERE codigo_referencia = ? AND tipo = 'deposito'
        ");
        $stmt->execute([$codigo_referencia]);
        $operacao = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$operacao) {
            throw new Exception('Operação não encontrada: ' . $codigo_referencia);
        }
        
        if ($operacao['status'] === 'aprovado') {
            // Já foi processado
            $pdo->rollBack();
            return;
        }
        
        // Atualizar status da operação
        $stmt = $pdo->prepare("
            UPDATE operacoes_financeiras 
            SET status = 'aprovado', 
                data_processamento = NOW(),
                metadados = JSON_SET(COALESCE(metadados, '{}'), '$.webhook_data', ?)
            WHERE codigo_referencia = ? AND tipo = 'deposito'
        ");
        $stmt->execute([json_encode($transaction_data), $codigo_referencia]);
        
        // Atualizar saldo na carteira
        $stmt = $pdo->prepare("
            UPDATE carteiras 
            SET saldo_principal = saldo_principal + ?,
                total_depositado = total_depositado + ?
            WHERE usuario_id = ?
        ");
        $stmt->execute([$operacao['valor_liquido'], $operacao['valor_liquido'], $operacao['usuario_id']]);
        
        // Verificar se aplicar bônus de primeiro depósito
        aplicarBonusPrimeiroDeposito($pdo, $operacao['usuario_id'], $operacao['valor_liquido']);
        
        // Processar comissões de afiliação se aplicável
        processarComissoesDeposito($pdo, $operacao['usuario_id'], $operacao['valor_liquido']);
        
        // Log da operação
        $stmt = $pdo->prepare("
            INSERT INTO logs_sistema 
            (usuario_id, acao, tabela_afetada, registro_id, dados_novos, ip_address) 
            VALUES (?, 'pagamento_aprovado', 'operacoes_financeiras', ?, ?, ?)
        ");
        $dados_log = json_encode([
            'operacao_id' => $operacao['id'],
            'valor_creditado' => $operacao['valor_liquido'],
            'codigo_referencia' => $codigo_referencia,
            'origem' => 'webhook'
        ]);
        $stmt->execute([
            $operacao['usuario_id'],
            $dados_log,
            $_SERVER['REMOTE_ADDR'] ?? 'gateway'
        ]);
        
        $pdo->commit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function processarPagamentoCancelado($pdo, $transaction_data) {
    $codigo_referencia = $transaction_data['external_reference'] ?? $transaction_data['id'] ?? '';
    
    if (empty($codigo_referencia)) {
        throw new Exception('Código de referência inválido');
    }
    
    // Atualizar status da operação
    $stmt = $pdo->prepare("
        UPDATE operacoes_financeiras 
        SET status = 'cancelado', 
            data_processamento = NOW(),
            metadados = JSON_SET(COALESCE(metadados, '{}'), '$.webhook_data', ?)
        WHERE codigo_referencia = ? AND tipo = 'deposito' AND status = 'pendente'
    ");
    $stmt->execute([json_encode($transaction_data), $codigo_referencia]);
    
    // Log da operação
    if ($pdo->rowCount() > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO logs_sistema 
            (acao, tabela_afetada, dados_novos, ip_address) 
            VALUES ('pagamento_cancelado', 'operacoes_financeiras', ?, ?)
        ");
        $dados_log = json_encode([
            'codigo_referencia' => $codigo_referencia,
            'motivo' => 'cancelamento_via_webhook'
        ]);
        $stmt->execute([
            $dados_log,
            $_SERVER['REMOTE_ADDR'] ?? 'gateway'
        ]);
    }
}

function processarPagamentoPendente($pdo, $transaction_data) {
    $codigo_referencia = $transaction_data['external_reference'] ?? $transaction_data['id'] ?? '';
    
    if (empty($codigo_referencia)) {
        throw new Exception('Código de referência inválido');
    }
    
    // Atualizar metadados com informações do webhook
    $stmt = $pdo->prepare("
        UPDATE operacoes_financeiras 
        SET metadados = JSON_SET(COALESCE(metadados, '{}'), '$.webhook_data', ?)
        WHERE codigo_referencia = ? AND tipo = 'deposito' AND status = 'pendente'
    ");
    $stmt->execute([json_encode($transaction_data), $codigo_referencia]);
}

function aplicarBonusPrimeiroDeposito($pdo, $user_id, $valor_deposito) {
    // Verificar se é o primeiro depósito aprovado
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_depositos 
        FROM operacoes_financeiras 
        WHERE usuario_id = ? AND tipo = 'deposito' AND status = 'aprovado'
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['total_depositos'] > 1) {
        return; // Não é o primeiro depósito
    }
    
    // Buscar configuração do bônus
    $stmt = $pdo->prepare("
        SELECT valor FROM configuracoes 
        WHERE categoria = 'cadastro' AND chave = 'bonus_primeiro_deposito'
    ");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config) {
        return; // Bônus não configurado
    }
    
    $bonus_valor = (float)$config['valor'];
    if ($bonus_valor <= 0) {
        return;
    }
    
    // Aplicar bônus (pode ser percentual ou valor fixo)
    $valor_bonus = $bonus_valor;
    if ($bonus_valor < 1) {
        // Se menor que 1, tratar como percentual
        $valor_bonus = $valor_deposito * $bonus_valor;
    }
    
    // Creditar bônus na carteira
    $stmt = $pdo->prepare("
        UPDATE carteiras 
        SET saldo_bonus = saldo_bonus + ? 
        WHERE usuario_id = ?
    ");
    $stmt->execute([$valor_bonus, $user_id]);
    
    // Log do bônus
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema 
        (usuario_id, acao, tabela_afetada, dados_novos, ip_address) 
        VALUES (?, 'bonus_primeiro_deposito', 'carteiras', ?, ?)
    ");
    $dados_log = json_encode([
        'valor_bonus' => $valor_bonus,
        'valor_deposito' => $valor_deposito,
        'tipo' => 'primeiro_deposito'
    ]);
    $stmt->execute([
        $user_id,
        $dados_log,
        $_SERVER['REMOTE_ADDR'] ?? 'sistema'
    ]);
}

function processarComissoesDeposito($pdo, $user_id, $valor_deposito) {
    // Buscar o referenciador do usuário
    $stmt = $pdo->prepare("
        SELECT referenciado_por FROM usuarios WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario || !$usuario['referenciado_por']) {
        return; // Usuário não tem referenciador
    }
    
    // Buscar configurações de comissão
    $comissoes = [];
    $stmt = $pdo->prepare("
        SELECT chave, valor FROM configuracoes 
        WHERE categoria = 'afiliacao' AND chave LIKE 'comissao_deposito_%'
    ");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $nivel = str_replace('comissao_deposito_nivel', '', $row['chave']);
        $comissoes[$nivel] = (float)$row['valor'];
    }
    
    if (empty($comissoes)) {
        return; // Nenhuma comissão configurada
    }
    
    $atual_referenciado_por = $usuario['referenciado_por'];
    
    foreach ($comissoes as $nivel => $percentual) {
        if (!$atual_referenciado_por || $percentual <= 0) {
            break;
        }
        
        $valor_comissao = ($valor_deposito * $percentual) / 100;
        
        // Creditar comissão
        $stmt = $pdo->prepare("
            UPDATE carteiras 
            SET saldo_comissao = saldo_comissao + ? 
            WHERE usuario_id = ?
        ");
        $stmt->execute([$valor_comissao, $atual_referenciado_por]);
        
        // Registrar comissão
        $stmt = $pdo->prepare("
            INSERT INTO comissoes 
            (usuario_id, origem_usuario_id, nivel_comissao, percentual_aplicado, valor_base, valor_comissao, tipo, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'deposito', 'pago')
        ");
        $stmt->execute([
            $atual_referenciado_por,
            $user_id,
            $nivel,
            $percentual,
            $valor_deposito,
            $valor_comissao
        ]);
        
        // Buscar próximo nível
        $stmt = $pdo->prepare("
            SELECT referenciado_por FROM usuarios WHERE id = ?
        ");
        $stmt->execute([$atual_referenciado_por]);
        $next_user = $stmt->fetch(PDO::FETCH_ASSOC);
        $atual_referenciado_por = $next_user ? $next_user['referenciado_por'] : null;
    }
}
?>