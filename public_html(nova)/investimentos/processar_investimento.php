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

$produto_id = (int)($_POST['produto_id'] ?? 0);
$valor_investimento = (float)($_POST['valor_investimento'] ?? 0);

if ($produto_id <= 0 || $valor_investimento <= 0) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
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
            u.referenciado_por,
            u.codigo_referencia,
            c.saldo_principal,
            c.total_investido
        FROM usuarios u 
        JOIN carteiras c ON u.id = c.usuario_id 
        WHERE u.id = ? AND u.status = 'ativo'
    ");
    $stmt->execute([$user_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        throw new Exception('Usuário não encontrado ou inativo');
    }
    
    // Verificar saldo suficiente
    if ($usuario['saldo_principal'] < $valor_investimento) {
        echo json_encode([
            'success' => false, 
            'message' => 'Saldo insuficiente',
            'saldo_atual' => $usuario['saldo_principal']
        ]);
        exit;
    }
    
    // Buscar dados do produto
    $stmt = $pdo->prepare("
        SELECT 
            titulo,
            valor_minimo,
            valor_maximo,
            rendimento_diario,
            tipo_rendimento,
            duracao_dias,
            vendidos,
            limite_vendas,
            status,
            comissao_nivel1,
            comissao_nivel2,
            comissao_nivel3
        FROM produtos 
        WHERE id = ? AND status = 'ativo'
    ");
    $stmt->execute([$produto_id]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$produto) {
        throw new Exception('Produto não encontrado ou inativo');
    }
    
    // Verificar valor mínimo e máximo
    if ($valor_investimento < $produto['valor_minimo']) {
        echo json_encode([
            'success' => false, 
            'message' => "Valor mínimo para investimento: R$ " . number_format($produto['valor_minimo'], 2, ',', '.')
        ]);
        exit;
    }
    
    if ($produto['valor_maximo'] && $valor_investimento > $produto['valor_maximo']) {
        echo json_encode([
            'success' => false, 
            'message' => "Valor máximo para investimento: R$ " . number_format($produto['valor_maximo'], 2, ',', '.')
        ]);
        exit;
    }
    
    // Verificar limite de vendas
    if ($produto['limite_vendas'] && $produto['vendidos'] >= $produto['limite_vendas']) {
        echo json_encode([
            'success' => false, 
            'message' => 'Produto esgotado'
        ]);
        exit;
    }
    
    // Debitar saldo da carteira
    $stmt = $pdo->prepare("
        UPDATE carteiras 
        SET saldo_principal = saldo_principal - ?,
            total_investido = total_investido + ?
        WHERE usuario_id = ?
    ");
    $stmt->execute([$valor_investimento, $valor_investimento, $user_id]);
    
    // Calcular data de vencimento
    $data_vencimento = date('Y-m-d', strtotime('+' . $produto['duracao_dias'] . ' days'));
    
    // Criar o investimento
    $stmt = $pdo->prepare("
        INSERT INTO investimentos 
        (usuario_id, produto_id, valor_investido, dias_restantes, data_vencimento, status) 
        VALUES (?, ?, ?, ?, ?, 'ativo')
    ");
    $stmt->execute([$user_id, $produto_id, $valor_investimento, $produto['duracao_dias'], $data_vencimento]);
    $investimento_id = $pdo->lastInsertId();
    
    // Atualizar contador de vendas do produto
    $stmt = $pdo->prepare("UPDATE produtos SET vendidos = vendidos + 1 WHERE id = ?");
    $stmt->execute([$produto_id]);
    
    // Processar comissões da rede de afiliação
    $comissoes_processadas = [];
    
    if ($usuario['referenciado_por']) {
        $comissoes = [
            1 => $produto['comissao_nivel1'],
            2 => $produto['comissao_nivel2'],
            3 => $produto['comissao_nivel3']
        ];
        
        $atual_referenciado_por = $usuario['referenciado_por'];
        
        for ($nivel = 1; $nivel <= 3 && $atual_referenciado_por; $nivel++) {
            $percentual = $comissoes[$nivel];
            if ($percentual <= 0) continue;
            
            $valor_comissao = ($valor_investimento * $percentual) / 100;
            
            // Buscar dados do usuário que vai receber a comissão
            $stmt = $pdo->prepare("
                SELECT id, nome, referenciado_por 
                FROM usuarios 
                WHERE id = ? AND status = 'ativo'
            ");
            $stmt->execute([$atual_referenciado_por]);
            $usuario_comissao = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario_comissao) {
                // Adicionar comissão ao saldo
                $stmt = $pdo->prepare("
                    UPDATE carteiras 
                    SET saldo_comissao = saldo_comissao + ? 
                    WHERE usuario_id = ?
                ");
                $stmt->execute([$valor_comissao, $usuario_comissao['id']]);
                
                // Registrar a comissão
                $stmt = $pdo->prepare("
                    INSERT INTO comissoes 
                    (usuario_id, origem_usuario_id, investimento_id, nivel_comissao, percentual_aplicado, valor_base, valor_comissao, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pago')
                ");
                $stmt->execute([
                    $usuario_comissao['id'],
                    $user_id,
                    $investimento_id,
                    $nivel,
                    $percentual,
                    $valor_investimento,
                    $valor_comissao
                ]);
                
                $comissoes_processadas[] = [
                    'nivel' => $nivel,
                    'usuario' => $usuario_comissao['nome'],
                    'valor' => $valor_comissao
                ];
                
                // Próximo nível na rede
                $atual_referenciado_por = $usuario_comissao['referenciado_por'];
            } else {
                break;
            }
        }
    }
    
    // Log da operação
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema 
        (usuario_id, acao, tabela_afetada, registro_id, dados_novos, ip_address) 
        VALUES (?, 'investimento_criado', 'investimentos', ?, ?, ?)
    ");
    $dados_log = json_encode([
        'investimento_id' => $investimento_id,
        'produto' => $produto['titulo'],
        'valor_investido' => $valor_investimento,
        'comissoes' => $comissoes_processadas,
        'data_vencimento' => $data_vencimento
    ]);
    $stmt->execute([
        $user_id,
        $dados_log,
        $_SERVER['REMOTE_ADDR'] ?? 'cliente'
    ]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Investimento realizado com sucesso!',
        'dados' => [
            'investimento_id' => $investimento_id,
            'produto' => $produto['titulo'],
            'valor_investido' => $valor_investimento,
            'data_vencimento' => date('d/m/Y', strtotime($data_vencimento)),
            'dias_duracao' => $produto['duracao_dias'],
            'comissoes_processadas' => count($comissoes_processadas),
            'saldo_restante' => $usuario['saldo_principal'] - $valor_investimento
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Erro no processamento de investimento: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>