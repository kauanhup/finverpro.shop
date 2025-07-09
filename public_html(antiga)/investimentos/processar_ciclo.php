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
    
    // Buscar investimentos ativos do usuário
    $stmt = $conn->prepare("
        SELECT 
            i.*,
            p.titulo,
            p.tipo_rendimento,
            DATEDIFF(NOW(), i.data_investimento) as dias_decorridos,
            DATEDIFF(i.data_vencimento, NOW()) as dias_restantes_real
        FROM investimentos i 
        LEFT JOIN produtos p ON i.produto_id = p.id 
        WHERE i.usuario_id = ? AND i.status = 'ativo'
        ORDER BY i.data_investimento ASC
    ");
    $stmt->execute([$user_id]);
    $investimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($investimentos)) {
        echo json_encode([
            'success' => true,
            'message' => 'Você não possui investimentos ativos no momento.',
            'rendimentos_processados' => 0,
            'valor_total_creditado' => 0
        ]);
        exit();
    }
    
    $conn->beginTransaction();
    
    $rendimentos_processados = 0;
    $valor_total_creditado = 0;
    $investimentos_finalizados = 0;
    $detalhes_processamento = [];
    
    foreach ($investimentos as $investimento) {
        $tipo_rendimento = $investimento['tipo_rendimento'] ?? 'diario';
        $dias_decorridos = $investimento['dias_decorridos'];
        $dias_restantes = max(0, $investimento['dias_restantes_real']);
        
        if ($tipo_rendimento === 'diario') {
            // TIPO DIÁRIO - Processar rendimentos pendentes COM ATRASO DE 1 DIA
            $rendimentos_pendentes = processarRendimentosDiariosComAtraso($conn, $investimento, $dias_decorridos);
            
            if ($rendimentos_pendentes['quantidade'] > 0) {
                $rendimentos_processados += $rendimentos_pendentes['quantidade'];
                $valor_total_creditado += $rendimentos_pendentes['valor_total'];
                
                $detalhes_processamento[] = [
                    'produto' => $investimento['titulo'],
                    'tipo' => 'diario',
                    'dias_processados' => $rendimentos_pendentes['quantidade'],
                    'valor_creditado' => $rendimentos_pendentes['valor_total'],
                    'status' => 'processado'
                ];
            }
            
            // Verificar se o investimento chegou ao fim
            if ($dias_restantes <= 0) {
                finalizarInvestimento($conn, $investimento);
                $investimentos_finalizados++;
            }
            
        } else {
            // TIPO FINAL - Verificar se chegou na data de vencimento COM ATRASO DE 1 DIA
            if ($dias_restantes <= -1) { // -1 = já passou 1 dia do vencimento
                $valor_final = processarRendimentoFinal($conn, $investimento);
                
                if ($valor_final > 0) {
                    $rendimentos_processados++;
                    $valor_total_creditado += $valor_final;
                    $investimentos_finalizados++;
                    
                    $detalhes_processamento[] = [
                        'produto' => $investimento['titulo'],
                        'tipo' => 'final',
                        'dias_processados' => 1,
                        'valor_creditado' => $valor_final,
                        'status' => 'finalizado'
                    ];
                }
            }
        }
    }
    
    $conn->commit();
    
    // Preparar resposta
    $message = '';
    if ($rendimentos_processados > 0) {
        $valor_formatado = number_format($valor_total_creditado, 2, ',', '.');
        $message = "✅ Processamento concluído!\n";
        $message .= "💰 R$ {$valor_formatado} creditados ao seu saldo\n";
        $message .= "⏰ Rendimentos creditados 1 dia após gerados\n";
        
        if ($investimentos_finalizados > 0) {
            $message .= "🎯 {$investimentos_finalizados} investimento(s) finalizados";
        }
    } else {
        $message = "ℹ️ Nenhum rendimento pendente encontrado.\n";
        $message .= "💡 Lembre-se: rendimentos são creditados 1 dia após serem gerados.\n";
        $message .= "Todos os seus investimentos estão em dia!";
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'rendimentos_processados' => $rendimentos_processados,
        'valor_total_creditado' => $valor_total_creditado,
        'investimentos_finalizados' => $investimentos_finalizados,
        'detalhes' => $detalhes_processamento
    ]);
    
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollback();
    }
    
    error_log("Erro no processamento de ciclo: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao processar rendimentos. Tente novamente.'
    ]);
}

/**
 * Processar rendimentos diários pendentes COM ATRASO DE 1 DIA
 */
function processarRendimentosDiariosComAtraso($conn, $investimento, $dias_decorridos) {
    $ultimo_rendimento = $investimento['ultimo_rendimento'];
    $data_investimento = $investimento['data_investimento'];
    $renda_diaria = $investimento['renda_diaria'];
    $investimento_id = $investimento['id'];
    $usuario_id = $investimento['usuario_id'];
    
    // APLICAR ATRASO DE 1 DIA
    $dias_decorridos_com_atraso = max(0, $dias_decorridos - 1);
    
    // Determinar quantos dias processar
    if ($ultimo_rendimento) {
        // Calcular dias desde o último rendimento
        $data_ultimo = new DateTime($ultimo_rendimento);
        $data_hoje = new DateTime();
        $data_hoje->modify('-1 day'); // APLICAR ATRASO DE 1 DIA
        
        $dias_para_processar = $data_ultimo->diff($data_hoje)->days;
        
        // Se a diferença for negativa ou zero, não processar
        if ($dias_para_processar <= 0) {
            return ['quantidade' => 0, 'valor_total' => 0];
        }
    } else {
        // Primeira vez processando - processar desde o investimento COM ATRASO
        $dias_para_processar = $dias_decorridos_com_atraso;
    }
    
    // Não processar mais que os dias totais do investimento
    $dias_para_processar = min($dias_para_processar, $investimento['dias_restantes']);
    
    if ($dias_para_processar <= 0) {
        return ['quantidade' => 0, 'valor_total' => 0];
    }
    
    $valor_total = $renda_diaria * $dias_para_processar;
    
    // Creditar no saldo do usuário
    $stmt = $conn->prepare("UPDATE usuarios SET saldo = saldo + ? WHERE id = ?");
    $stmt->execute([$valor_total, $usuario_id]);
    
    // Atualizar renda total do investimento (USAR DATA DE ONTEM)
    $data_ultimo_rendimento = date('Y-m-d', strtotime('-1 day'));
    
    $stmt = $conn->prepare("
        UPDATE investimentos 
        SET renda_total = renda_total + ?, 
            ultimo_rendimento = ?,
            dias_restantes = dias_restantes - ?
        WHERE id = ?
    ");
    $stmt->execute([$valor_total, $data_ultimo_rendimento, $dias_para_processar, $investimento_id]);
    
    // Registrar no histórico
    $stmt = $conn->prepare("
        INSERT INTO historico_transacoes (
            user_id, tipo, valor, descricao, status
        ) VALUES (?, 'rendimento', ?, ?, 'concluido')
    ");
    
    $descricao = "Rendimento de {$dias_para_processar} dias (creditado 1 dia após) - {$investimento['titulo']}";
    $stmt->execute([$usuario_id, $valor_total, $descricao]);
    
    return [
        'quantidade' => $dias_para_processar,
        'valor_total' => $valor_total
    ];
}

/**
 * Processar rendimento final (tipo final) COM ATRASO DE 1 DIA
 */
function processarRendimentoFinal($conn, $investimento) {
    $valor_final = $investimento['renda_diaria']; // Para tipo final, renda_diaria contém o valor total
    $investimento_id = $investimento['id'];
    $usuario_id = $investimento['usuario_id'];
    
    // Creditar valor final + devolver investimento
    $valor_total = $valor_final + $investimento['valor_investido'];
    
    $stmt = $conn->prepare("UPDATE usuarios SET saldo = saldo + ? WHERE id = ?");
    $stmt->execute([$valor_total, $usuario_id]);
    
    // Atualizar investimento
    $stmt = $conn->prepare("
        UPDATE investimentos 
        SET renda_total = ?, 
            status = 'concluido',
            dias_restantes = 0
        WHERE id = ?
    ");
    $stmt->execute([$valor_final, $investimento_id]);
    
    // Registrar no histórico
    $stmt = $conn->prepare("
        INSERT INTO historico_transacoes (
            user_id, tipo, valor, descricao, status
        ) VALUES (?, 'rendimento', ?, ?, 'concluido')
    ");
    
    $descricao = "Rendimento final + devolução (creditado 1 dia após vencimento) - {$investimento['titulo']}";
    $stmt->execute([$usuario_id, $valor_total, $descricao]);
    
    return $valor_total;
}

/**
 * Finalizar investimento que chegou ao fim
 */
function finalizarInvestimento($conn, $investimento) {
    $investimento_id = $investimento['id'];
    $usuario_id = $investimento['usuario_id'];
    $valor_investido = $investimento['valor_investido'];
    
    // Devolver o valor investido
    $stmt = $conn->prepare("UPDATE usuarios SET saldo = saldo + ? WHERE id = ?");
    $stmt->execute([$valor_investido, $usuario_id]);
    
    // Marcar como concluído
    $stmt = $conn->prepare("UPDATE investimentos SET status = 'concluido' WHERE id = ?");
    $stmt->execute([$investimento_id]);
    
    // Registrar devolução
    $stmt = $conn->prepare("
        INSERT INTO historico_transacoes (
            user_id, tipo, valor, descricao, status
        ) VALUES (?, 'rendimento', ?, ?, 'concluido')
    ");
    
    $descricao = "Devolução do investimento (creditado 1 dia após) - {$investimento['titulo']}";
    $stmt->execute([$usuario_id, $valor_investido, $descricao]);
}
?>