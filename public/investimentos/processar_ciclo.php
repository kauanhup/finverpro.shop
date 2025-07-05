<?php
session_start();
require_once '../bank/db.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar autenticação admin
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Verificar se é admin
if (!checkAdmin($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

try {
    $pdo = getPDO();
    $pdo->beginTransaction();
    
    $hoje = date('Y-m-d');
    $processados = 0;
    $erros = [];
    
    // Buscar investimentos ativos que precisam ser processados
    $stmt = $pdo->prepare("
        SELECT 
            i.id,
            i.usuario_id,
            i.produto_id,
            i.valor_investido,
            i.rendimento_acumulado,
            i.dias_restantes,
            i.data_vencimento,
            i.ultimo_rendimento,
            p.rendimento_diario,
            p.tipo_rendimento,
            p.titulo as produto_titulo,
            u.nome as usuario_nome,
            u.telefone as usuario_telefone
        FROM investimentos i
        JOIN produtos p ON i.produto_id = p.id
        JOIN usuarios u ON i.usuario_id = u.id
        WHERE i.status = 'ativo'
        AND i.dias_restantes > 0
        AND (i.ultimo_rendimento IS NULL OR i.ultimo_rendimento < :hoje)
        AND i.data_vencimento >= :hoje
        ORDER BY i.id ASC
    ");
    $stmt->bindParam(':hoje', $hoje);
    $stmt->execute();
    $investimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($investimentos as $inv) {
        try {
            $valor_rendimento = 0;
            
            // Calcular rendimento baseado no tipo
            if ($inv['tipo_rendimento'] == 'percentual_diario') {
                $valor_rendimento = ($inv['valor_investido'] * $inv['rendimento_diario']) / 100;
            } elseif ($inv['tipo_rendimento'] == 'valor_fixo_diario') {
                $valor_rendimento = $inv['rendimento_diario'];
            }
            
            if ($valor_rendimento > 0) {
                // Atualizar saldo na carteira
                $stmt_saldo = $pdo->prepare("
                    UPDATE carteiras 
                    SET saldo_principal = saldo_principal + ? 
                    WHERE usuario_id = ?
                ");
                $stmt_saldo->execute([$valor_rendimento, $inv['usuario_id']]);
                
                // Atualizar o investimento
                $novo_rendimento_acumulado = $inv['rendimento_acumulado'] + $valor_rendimento;
                $novos_dias_restantes = $inv['dias_restantes'] - 1;
                
                $stmt_inv = $pdo->prepare("
                    UPDATE investimentos 
                    SET rendimento_acumulado = ?, 
                        dias_restantes = ?, 
                        ultimo_rendimento = ?,
                        status = CASE WHEN ? <= 0 THEN 'concluido' ELSE 'ativo' END
                    WHERE id = ?
                ");
                $stmt_inv->execute([
                    $novo_rendimento_acumulado,
                    $novos_dias_restantes,
                    $hoje,
                    $novos_dias_restantes,
                    $inv['id']
                ]);
                
                // Log da operação
                $stmt_log = $pdo->prepare("
                    INSERT INTO logs_sistema 
                    (usuario_id, acao, tabela_afetada, registro_id, dados_novos, ip_address) 
                    VALUES (?, 'rendimento_processado', 'investimentos', ?, ?, ?)
                ");
                $dados_log = json_encode([
                    'investimento_id' => $inv['id'],
                    'valor_rendimento' => $valor_rendimento,
                    'produto' => $inv['produto_titulo'],
                    'usuario' => $inv['usuario_nome'],
                    'dias_restantes' => $novos_dias_restantes
                ]);
                $stmt_log->execute([
                    $_SESSION['user_id'],
                    $dados_log,
                    $_SERVER['REMOTE_ADDR'] ?? 'sistema'
                ]);
                
                $processados++;
                
                // Se o investimento foi concluído, processar devolução do valor principal
                if ($novos_dias_restantes <= 0) {
                    $stmt_devolucao = $pdo->prepare("
                        UPDATE carteiras 
                        SET saldo_principal = saldo_principal + ? 
                        WHERE usuario_id = ?
                    ");
                    $stmt_devolucao->execute([$inv['valor_investido'], $inv['usuario_id']]);
                    
                    // Log da devolução
                    $stmt_log_dev = $pdo->prepare("
                        INSERT INTO logs_sistema 
                        (usuario_id, acao, tabela_afetada, registro_id, dados_novos, ip_address) 
                        VALUES (?, 'investimento_concluido', 'investimentos', ?, ?, ?)
                    ");
                    $dados_dev = json_encode([
                        'investimento_id' => $inv['id'],
                        'valor_devolvido' => $inv['valor_investido'],
                        'rendimento_total' => $novo_rendimento_acumulado,
                        'usuario' => $inv['usuario_nome']
                    ]);
                    $stmt_log_dev->execute([
                        $_SESSION['user_id'],
                        $dados_dev,
                        $_SERVER['REMOTE_ADDR'] ?? 'sistema'
                    ]);
                }
            }
            
        } catch (Exception $e) {
            $erros[] = "Erro no investimento ID {$inv['id']}: " . $e->getMessage();
            continue;
        }
    }
    
    $pdo->commit();
    
    $response = [
        'success' => true,
        'message' => "Processamento concluído! {$processados} investimentos processados.",
        'dados' => [
            'total_processados' => $processados,
            'total_investimentos' => count($investimentos),
            'data_processamento' => $hoje,
            'erros' => $erros
        ]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Erro no processamento de ciclos: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno no processamento: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>