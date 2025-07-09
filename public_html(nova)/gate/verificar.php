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

$codigo_referencia = $_POST['codigo_referencia'] ?? '';

if (empty($codigo_referencia)) {
    echo json_encode(['success' => false, 'message' => 'Código de referência obrigatório']);
    exit;
}

try {
    $pdo = getPDO();
    
    // Buscar a operação financeira
    $stmt = $pdo->prepare("
        SELECT 
            id,
            usuario_id,
            valor_solicitado,
            valor_liquido,
            status,
            gateway,
            metadados,
            created_at,
            data_processamento
        FROM operacoes_financeiras 
        WHERE codigo_referencia = ? AND usuario_id = ? AND tipo = 'deposito'
    ");
    $stmt->execute([$codigo_referencia, $user_id]);
    $operacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$operacao) {
        echo json_encode([
            'success' => false, 
            'message' => 'Pagamento não encontrado'
        ]);
        exit;
    }
    
    $status_info = getStatusInfo($operacao['status']);
    
    // Se ainda está pendente, tentar verificar no gateway
    if ($operacao['status'] === 'pendente') {
        $status_gateway = verificarStatusGateway($operacao, $codigo_referencia);
        
        if ($status_gateway && $status_gateway !== 'pendente') {
            // Atualizar status na base de dados
            atualizarStatusOperacao($pdo, $operacao['id'], $status_gateway, $operacao['usuario_id']);
            $operacao['status'] = $status_gateway;
            $status_info = getStatusInfo($status_gateway);
        }
    }
    
    // Calcular tempo decorrido
    $tempo_criacao = new DateTime($operacao['created_at']);
    $tempo_atual = new DateTime();
    $tempo_decorrido = $tempo_atual->diff($tempo_criacao);
    
    $response = [
        'success' => true,
        'dados' => [
            'id' => $operacao['id'],
            'codigo_referencia' => $codigo_referencia,
            'valor_solicitado' => (float)$operacao['valor_solicitado'],
            'valor_liquido' => (float)$operacao['valor_liquido'],
            'status' => $operacao['status'],
            'status_texto' => $status_info['texto'],
            'status_cor' => $status_info['cor'],
            'status_icone' => $status_info['icone'],
            'gateway' => $operacao['gateway'],
            'data_criacao' => $operacao['created_at'],
            'data_processamento' => $operacao['data_processamento'],
            'tempo_decorrido' => [
                'horas' => $tempo_decorrido->h,
                'minutos' => $tempo_decorrido->i,
                'segundos' => $tempo_decorrido->s
            ]
        ]
    ];
    
    // Adicionar informações específicas baseadas no status
    if ($operacao['status'] === 'aprovado') {
        $response['dados']['mensagem'] = 'Pagamento aprovado! O valor foi creditado em sua conta.';
    } elseif ($operacao['status'] === 'rejeitado') {
        $response['dados']['mensagem'] = 'Pagamento rejeitado. Entre em contato com o suporte.';
    } elseif ($operacao['status'] === 'pendente') {
        $response['dados']['mensagem'] = 'Aguardando confirmação do pagamento.';
        $response['dados']['tempo_limite'] = 900; // 15 minutos em segundos
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Erro ao verificar pagamento: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor'
    ]);
}

function getStatusInfo($status) {
    $status_map = [
        'pendente' => [
            'texto' => 'Aguardando Pagamento',
            'cor' => 'warning',
            'icone' => 'clock'
        ],
        'processando' => [
            'texto' => 'Processando',
            'cor' => 'info',
            'icone' => 'arrow-repeat'
        ],
        'aprovado' => [
            'texto' => 'Aprovado',
            'cor' => 'success',
            'icone' => 'check-circle'
        ],
        'rejeitado' => [
            'texto' => 'Rejeitado',
            'cor' => 'danger',
            'icone' => 'x-circle'
        ],
        'cancelado' => [
            'texto' => 'Cancelado',
            'cor' => 'secondary',
            'icone' => 'slash-circle'
        ]
    ];
    
    return $status_map[$status] ?? [
        'texto' => 'Desconhecido',
        'cor' => 'secondary',
        'icone' => 'question-circle'
    ];
}

function verificarStatusGateway($operacao, $codigo_referencia) {
    // Simular verificação no gateway
    // Em um ambiente real, aqui seria feita uma consulta à API do gateway
    
    $gateway = $operacao['gateway'];
    
    switch ($gateway) {
        case 'pixup':
            return verificarPixUp($codigo_referencia);
            
        case 'mercadopago':
            return verificarMercadoPago($codigo_referencia);
            
        default:
            return null;
    }
}

function verificarPixUp($codigo_referencia) {
    // Simular verificação no PixUp
    // Retornar status aleatório para demonstração
    $statuses = ['pendente', 'aprovado', 'rejeitado'];
    return $statuses[array_rand($statuses)];
}

function verificarMercadoPago($codigo_referencia) {
    // Simular verificação no Mercado Pago
    return 'pendente';
}

function atualizarStatusOperacao($pdo, $operacao_id, $novo_status, $user_id) {
    $pdo->beginTransaction();
    
    try {
        // Atualizar status da operação
        $stmt = $pdo->prepare("
            UPDATE operacoes_financeiras 
            SET status = ?, data_processamento = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$novo_status, $operacao_id]);
        
        // Se foi aprovado, creditar valor na carteira
        if ($novo_status === 'aprovado') {
            $stmt = $pdo->prepare("
                SELECT valor_liquido FROM operacoes_financeiras WHERE id = ?
            ");
            $stmt->execute([$operacao_id]);
            $operacao = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($operacao) {
                $stmt = $pdo->prepare("
                    UPDATE carteiras 
                    SET saldo_principal = saldo_principal + ?,
                        total_depositado = total_depositado + ?
                    WHERE usuario_id = ?
                ");
                $stmt->execute([
                    $operacao['valor_liquido'], 
                    $operacao['valor_liquido'], 
                    $user_id
                ]);
            }
        }
        
        // Log da atualização
        $stmt = $pdo->prepare("
            INSERT INTO logs_sistema 
            (usuario_id, acao, tabela_afetada, registro_id, dados_novos, ip_address) 
            VALUES (?, 'status_pagamento_atualizado', 'operacoes_financeiras', ?, ?, ?)
        ");
        $dados_log = json_encode([
            'operacao_id' => $operacao_id,
            'novo_status' => $novo_status,
            'origem' => 'verificacao_manual'
        ]);
        $stmt->execute([
            $user_id,
            $dados_log,
            $_SERVER['REMOTE_ADDR'] ?? 'cliente'
        ]);
        
        $pdo->commit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
?>