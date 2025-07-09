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
    
    // Criar tabelas se necessário ANTES de processar
    criarTabelasSeNecessario($conn);
    
    // Decodificar dados JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['produto_id'])) {
        echo json_encode(['success' => false, 'message' => 'Produto não especificado']);
        exit();
    }
    
    $userId = $_SESSION['user_id'];
    $produtoId = intval($input['produto_id']);
    
    // Iniciar transação
    $conn->beginTransaction();
    
    // Buscar dados do produto COM CÁLCULOS CORRETOS
    $stmtProduto = $conn->prepare("
        SELECT 
            *,
            COALESCE(limite_compras, 100) as limite_compras_safe,
            COALESCE(vendidos, 0) as vendidos_safe,
            (COALESCE(limite_compras, 100) - COALESCE(vendidos, 0)) as restantes,
            COALESCE(duracao_dias, validade, 30) as duracao_dias_safe,
            COALESCE(tipo_rendimento, 'diario') as tipo_rendimento_safe,
            COALESCE(data_criacao, created_at) as data_criacao_safe,
            CASE 
                WHEN limite_dias_venda IS NOT NULL THEN 
                    GREATEST(0, DATEDIFF(DATE_ADD(COALESCE(data_criacao, created_at), INTERVAL limite_dias_venda DAY), NOW()))
                ELSE NULL 
            END as dias_restantes_venda
        FROM produtos 
        WHERE id = ? AND status = 'ativo'
    ");
    $stmtProduto->execute([$produtoId]);
    $produto = $stmtProduto->fetch(PDO::FETCH_ASSOC);
    
    if (!$produto) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Produto não encontrado ou inativo']);
        exit();
    }
    
    // VERIFICAR PRAZO DE VENDA (CORREÇÃO IMPORTANTE)
    if ($produto['dias_restantes_venda'] !== null && $produto['dias_restantes_venda'] <= 0) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Prazo de venda do produto expirado']);
        exit();
    }
    
    // ==========================================
    // VERIFICAR LIMITE POR PESSOA
    // ==========================================
    $stmt = $conn->prepare("SELECT COUNT(*) as compras_usuario FROM investimentos 
                           WHERE usuario_id = ? AND produto_id = ?");
    $stmt->execute([$userId, $produtoId]);
    $compras_usuario = $stmt->fetchColumn();

    if ($compras_usuario >= $produto['limite_compras']) {
        $conn->rollBack();
        echo json_encode([
            'success' => false, 
            'message' => "Você já atingiu o limite de {$produto['limite_compras']} compra(s) deste produto"
        ]);
        exit();
    }
    
    // Buscar saldo do usuário
    $stmtSaldo = $conn->prepare("SELECT saldo, nome FROM usuarios WHERE id = ?");
    $stmtSaldo->execute([$userId]);
    $usuario = $stmtSaldo->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
        exit();
    }
    
    $saldoAtual = $usuario['saldo'];
    
    if ($saldoAtual < $produto['valor_investimento']) {
        $conn->rollBack();
        $saldo_formatado = number_format($saldoAtual, 2, ',', '.');
        $valor_formatado = number_format($produto['valor_investimento'], 2, ',', '.');
        echo json_encode([
            'success' => false, 
            'message' => "Saldo insuficiente. Seu saldo: R$ $saldo_formatado. Necessário: R$ $valor_formatado"
        ]);
        exit();
    }
    
    // Debitar valor da conta do usuário
    $novoSaldo = $saldoAtual - $produto['valor_investimento'];
    $stmtDebitar = $conn->prepare("UPDATE usuarios SET saldo = ? WHERE id = ?");
    $stmtDebitar->execute([$novoSaldo, $userId]);
    
    // Calcular data de vencimento (USANDO CAMPO CORRETO)
    $dataVencimento = date('Y-m-d', strtotime('+' . $produto['duracao_dias_safe'] . ' days'));
    
    // Registrar investimento (TABELA E CAMPOS CORRIGIDOS)
    $stmtInvestimento = $conn->prepare("
        INSERT INTO investimentos (
            usuario_id, produto_id, valor_investido, renda_diaria, 
            dias_restantes, data_vencimento, status, tipo_rendimento
        ) VALUES (?, ?, ?, ?, ?, ?, 'ativo', ?)
    ");
    
    $stmtInvestimento->execute([
        $userId,
        $produtoId,
        $produto['valor_investimento'],
        $produto['renda_diaria'],
        $produto['duracao_dias_safe'],
        $dataVencimento,
        $produto['tipo_rendimento_safe']
    ]);
    
    $investimento_id = $conn->lastInsertId();
    
    // Atualizar contador de vendidos do produto
    $stmtVendidos = $conn->prepare("UPDATE produtos SET vendidos = vendidos + 1 WHERE id = ?");
    $stmtVendidos->execute([$produtoId]);
    
    // Buscar dados do usuário para comissões (CAMPO CORRETO)
    $stmtUsuario = $conn->prepare("SELECT referenciado_por FROM usuarios WHERE id = ?");
    $stmtUsuario->execute([$userId]);
    $dadosUsuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);
    
    $indicador_id = null;
    
    // Processar comissões se o usuário foi referenciado
    if ($dadosUsuario && $dadosUsuario['referenciado_por']) {
        $indicador_id = $dadosUsuario['referenciado_por'];
        processarComissoes($conn, $dadosUsuario['referenciado_por'], $userId, $produtoId, $produto['valor_investimento']);
    }
    
    // Registrar transação no histórico
    $stmtHistorico = $conn->prepare("
        INSERT INTO historico_transacoes (
            user_id, tipo, valor, descricao, status, data_transacao
        ) VALUES (?, 'investimento', ?, ?, 'concluido', NOW())
    ");
    
    $descricaoTransacao = "Investimento em " . $produto['titulo'];
    $stmtHistorico->execute([$userId, $produto['valor_investimento'], $descricaoTransacao]);
    
    // Confirmar transação
    $conn->commit();
    
    // ==========================================
    // SISTEMA DE ROLETA PARA INVESTIMENTOS R$ 200+
    // ==========================================
    $ganhou_roleta = false;
    $indicador_ganhou_roleta = false;
    
    if ($produto['valor_investimento'] >= 200) {
        try {
            // 1. DAR RODADA PARA O COMPRADOR
            $stmt = $conn->prepare("
                SELECT giros_disponiveis FROM roleta_giros_usuario WHERE usuario_id = ?
            ");
            $stmt->execute([$userId]);
            $giros_atuais = $stmt->fetchColumn();
            
            if ($giros_atuais !== false) {
                // Usuário já existe, só incrementar
                $stmt = $conn->prepare("
                    UPDATE roleta_giros_usuario 
                    SET giros_disponiveis = giros_disponiveis + 1,
                        total_giros_historico = total_giros_historico + 1
                    WHERE usuario_id = ?
                ");
                $stmt->execute([$userId]);
            } else {
                // Criar registro novo
                $stmt = $conn->prepare("
                    INSERT INTO roleta_giros_usuario 
                    (usuario_id, giros_disponiveis, giros_hoje, data_reset_diario, total_giros_historico) 
                    VALUES (?, 1, 0, CURDATE(), 1)
                ");
                $stmt->execute([$userId]);
            }
            
            $ganhou_roleta = true;
            
            // 2. DAR RODADA PARA O INDICADOR (se existir)
            if ($indicador_id) {
                $stmt = $conn->prepare("
                    SELECT giros_disponiveis FROM roleta_giros_usuario WHERE usuario_id = ?
                ");
                $stmt->execute([$indicador_id]);
                $giros_indicador = $stmt->fetchColumn();
                
                if ($giros_indicador !== false) {
                    // Indicador já existe, incrementar
                    $stmt = $conn->prepare("
                        UPDATE roleta_giros_usuario 
                        SET giros_disponiveis = giros_disponiveis + 1,
                            total_giros_historico = total_giros_historico + 1
                        WHERE usuario_id = ?
                    ");
                    $stmt->execute([$indicador_id]);
                } else {
                    // Criar registro para indicador
                    $stmt = $conn->prepare("
                        INSERT INTO roleta_giros_usuario 
                        (usuario_id, giros_disponiveis, giros_hoje, data_reset_diario, total_giros_historico) 
                        VALUES (?, 1, 0, CURDATE(), 1)
                    ");
                    $stmt->execute([$indicador_id]);
                }
                
                $indicador_ganhou_roleta = true;
            }
            
        } catch (Exception $e) {
            // Log erro mas não falha o investimento
            error_log("Erro ao conceder rodadas da roleta: " . $e->getMessage());
        }
    }
    
    // Preparar resposta
    $dados_resposta = [
        'investimento_id' => $investimento_id,
        'produto' => $produto['titulo'],
        'valor_investido' => $produto['valor_investimento'],
        'renda_diaria' => $produto['renda_diaria'],
        'tipo_rendimento' => $produto['tipo_rendimento_safe'],
        'duracao' => $produto['duracao_dias_safe'],
        'novo_saldo' => $novoSaldo,
        'data_vencimento' => $dataVencimento
    ];

    // ADICIONAR INFO DA ROLETA:
    if ($ganhou_roleta) {
        $dados_resposta['ganhou_roleta'] = true;
        $dados_resposta['rodadas_ganhas'] = 1;
        if ($indicador_ganhou_roleta) {
            $dados_resposta['indicador_ganhou'] = true;
        }
    }
    
    // Resposta de sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Investimento realizado com sucesso!',
        'dados' => $dados_resposta
    ]);
    
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Erro no processamento de investimento: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor: ' . $e->getMessage()]);
}

/**
 * Função para processar comissões em múltiplos níveis - VERSÃO ATUALIZADA
 */
function processarComissoes($conn, $referenciadorId, $referidoId, $produtoId, $valorInvestimento) {
    // BUSCAR COMISSÕES DA TABELA configuracao_comissoes
    try {
        $stmt = $conn->prepare("
            SELECT nivel, percentual 
            FROM configuracao_comissoes 
            WHERE ativo = 1 
            ORDER BY nivel ASC
        ");
        $stmt->execute();
        $comissoes_config = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($comissoes_config)) {
            // Se não há comissões configuradas, usar padrão
            $percentuaisComissao = [
                1 => 0.05, // 5%
                2 => 0.03, // 3%
                3 => 0.02  // 2%
            ];
        } else {
            // Converter para o formato esperado
            $percentuaisComissao = [];
            foreach ($comissoes_config as $comissao) {
                $percentuaisComissao[$comissao['nivel']] = $comissao['percentual'] / 100;
            }
        }
    } catch (Exception $e) {
        // Em caso de erro, usar configuração padrão
        error_log("Erro ao buscar configurações de comissão: " . $e->getMessage());
        $percentuaisComissao = [
            1 => 0.05, // 5%
            2 => 0.03, // 3%
            3 => 0.02  // 2%
        ];
    }
    
    $usuarioAtual = $referenciadorId;
    $nivel = 1;
    
    // Processar níveis configurados
    while ($usuarioAtual && isset($percentuaisComissao[$nivel])) {
        $valorComissao = $valorInvestimento * $percentuaisComissao[$nivel];
        
        // Registrar comissão
        $stmtComissao = $conn->prepare("
            INSERT INTO comissoes (
                user_id, referido_id, produto_id, valor_investimento, 
                valor_comissao, nivel, status
            ) VALUES (?, ?, ?, ?, ?, ?, 'pendente')
        ");
        
        $stmtComissao->execute([
            $usuarioAtual, $referidoId, $produtoId, 
            $valorInvestimento, $valorComissao, $nivel
        ]);
        
        // Creditar comissão ao saldo do referenciador
        $stmtCreditarComissao = $conn->prepare("UPDATE usuarios SET saldo = saldo + ? WHERE id = ?");
        $stmtCreditarComissao->execute([$valorComissao, $usuarioAtual]);
        
        // Atualizar status da comissão para processado
        $stmtAtualizarComissao = $conn->prepare("
            UPDATE comissoes SET status = 'processado' 
            WHERE user_id = ? AND referido_id = ? AND produto_id = ? AND nivel = ?
        ");
        $stmtAtualizarComissao->execute([$usuarioAtual, $referidoId, $produtoId, $nivel]);
        
        // Registrar no histórico
        $stmtHistoricoComissao = $conn->prepare("
            INSERT INTO historico_transacoes (
                user_id, tipo, valor, descricao, status
            ) VALUES (?, 'comissao', ?, ?, 'concluido')
        ");
        
        $descricaoComissao = "Comissão nível {$nivel} - Referido ID: {$referidoId}";
        $stmtHistoricoComissao->execute([$usuarioAtual, $valorComissao, $descricaoComissao]);
        
        // Atualizar tabela niveis_convite (se existir)
        try {
            // Verificar se o registro já existe
            $stmtCheck = $conn->prepare("SELECT id FROM niveis_convite WHERE user_id = ?");
            $stmtCheck->execute([$usuarioAtual]);
            
            if ($stmtCheck->fetch()) {
                // Atualizar registro existente
                $campoNivel = "total_nivel{$nivel}";
                $stmtUpdate = $conn->prepare("
                    UPDATE niveis_convite 
                    SET {$campoNivel} = {$campoNivel} + ? 
                    WHERE user_id = ?
                ");
                $stmtUpdate->execute([$valorComissao, $usuarioAtual]);
            } else {
                // Criar novo registro
                $stmtInsert = $conn->prepare("
                    INSERT INTO niveis_convite (user_id, nivel_1, nivel_2, nivel_3, total_nivel1, total_nivel2, total_nivel3) 
                    VALUES (?, 0, 0, 0, 0, 0, 0)
                ");
                $stmtInsert->execute([$usuarioAtual]);
                
                // Atualizar com o valor da comissão
                $campoNivel = "total_nivel{$nivel}";
                $stmtUpdate = $conn->prepare("
                    UPDATE niveis_convite 
                    SET {$campoNivel} = ? 
                    WHERE user_id = ?
                ");
                $stmtUpdate->execute([$valorComissao, $usuarioAtual]);
            }
        } catch (Exception $e) {
            // Se tabela niveis_convite não existir, continuar sem erro
            error_log("Aviso: Não foi possível atualizar niveis_convite: " . $e->getMessage());
        }
        
        // Buscar o próximo referenciador na hierarquia (CAMPO CORRETO)
        $stmtProximo = $conn->prepare("SELECT referenciado_por FROM usuarios WHERE id = ?");
        $stmtProximo->execute([$usuarioAtual]);
        $proximoReferenciador = $stmtProximo->fetchColumn();
        
        $usuarioAtual = $proximoReferenciador;
        $nivel++;
    }
}

/**
 * Função para criar tabelas se não existirem
 */
function criarTabelasSeNecessario($conn) {
    $tabelas = [
        'configuracao_comissoes' => "CREATE TABLE IF NOT EXISTS `configuracao_comissoes` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nivel` int(11) NOT NULL COMMENT 'Nível da comissão (1, 2, 3, etc)',
            `percentual` decimal(5,2) NOT NULL COMMENT 'Percentual da comissão (ex: 5.00 = 5%)',
            `descricao` varchar(255) DEFAULT NULL COMMENT 'Descrição do nível',
            `ativo` boolean DEFAULT TRUE COMMENT 'Se este nível está ativo',
            `data_criacao` timestamp DEFAULT CURRENT_TIMESTAMP,
            `data_atualizacao` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `nivel_unico` (`nivel`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        'historico_transacoes' => "CREATE TABLE IF NOT EXISTS `historico_transacoes` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `tipo` enum('deposito','saque','investimento','rendimento','comissao') NOT NULL,
            `valor` decimal(10,2) NOT NULL,
            `descricao` text,
            `status` enum('pendente','concluido','cancelado') DEFAULT 'pendente',
            `data_transacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        'investimentos' => "CREATE TABLE IF NOT EXISTS `investimentos` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `usuario_id` int(11) NOT NULL,
            `produto_id` int(11) NOT NULL,
            `valor_investido` decimal(10,2) NOT NULL,
            `renda_diaria` decimal(10,2) NOT NULL,
            `renda_total` decimal(10,2) DEFAULT 0.00,
            `dias_restantes` int(11) NOT NULL,
            `data_investimento` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `data_vencimento` date NOT NULL,
            `status` enum('ativo','concluido','cancelado') DEFAULT 'ativo',
            `ultimo_rendimento` date DEFAULT NULL,
            `tipo_rendimento` enum('diario','final') DEFAULT 'diario',
            PRIMARY KEY (`id`),
            KEY `usuario_id` (`usuario_id`),
            KEY `produto_id` (`produto_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        'comissoes' => "CREATE TABLE IF NOT EXISTS `comissoes` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `referido_id` int(11) NOT NULL,
            `produto_id` int(11) NOT NULL,
            `valor_investimento` decimal(10,2) NOT NULL,
            `valor_comissao` decimal(10,2) NOT NULL,
            `nivel` int(11) NOT NULL,
            `status` enum('pendente','processado') DEFAULT 'pendente',
            `data_comissao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `referido_id` (`referido_id`),
            KEY `produto_id` (`produto_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
    
    foreach ($tabelas as $nome => $sql) {
        try {
            $conn->exec($sql);
        } catch (Exception $e) {
            // Tabela já existe ou erro na criação - não é crítico
            error_log("Aviso ao criar tabela {$nome}: " . $e->getMessage());
        }
    }
    
    // Inserir configurações padrão se não existirem
    try {
        $stmt = $conn->query("SELECT COUNT(*) FROM configuracao_comissoes");
        $count = $stmt->fetchColumn();
        
        if ($count == 0) {
            $conn->exec("
                INSERT INTO configuracao_comissoes (nivel, percentual, descricao, ativo) VALUES
                (1, 5.00, 'Comissão Nível 1 - Indicação Direta', TRUE),
                (2, 3.00, 'Comissão Nível 2 - Segundo Nível', TRUE),
                (3, 2.00, 'Comissão Nível 3 - Terceiro Nível', TRUE)
            ");
        }
    } catch (Exception $e) {
        error_log("Aviso ao inserir configurações padrão: " . $e->getMessage());
    }
    
    // Adicionar colunas que podem estar faltando
    $colunas_adicionar = [
        "ALTER TABLE investimentos ADD COLUMN tipo_rendimento ENUM('diario','final') DEFAULT 'diario'",
        "ALTER TABLE produtos ADD COLUMN tipo_rendimento ENUM('diario','final') DEFAULT 'diario'",
        "ALTER TABLE produtos ADD COLUMN duracao_dias INT DEFAULT 30"
    ];
    
    foreach ($colunas_adicionar as $sql) {
        try {
            $conn->exec($sql);
        } catch (Exception $e) {
            // Coluna já existe - não é crítico
        }
    }
}
?>