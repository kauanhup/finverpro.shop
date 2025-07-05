<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../');
    exit();
}

require '../../bank/db.php';

try {
    $conn = getDBConnection();
    $user_id = $_SESSION['user_id'];
    
    // Buscar configurações de saque
    $stmt = $conn->query("SELECT chave, valor FROM configuracoes WHERE categoria = 'financeiro' AND chave LIKE 'saque_%'");
    $config_saque = [];
    while ($row = $stmt->fetch()) {
        $config_saque[$row['chave']] = $row['valor'];
    }
    
    // Configurações padrão se não existirem
    $valor_minimo = $config_saque['saque_valor_minimo'] ?? 37.00;
    $taxa_percentual = $config_saque['saque_taxa_percentual'] ?? 9.00;
    $limite_diario = $config_saque['saque_limite_diario'] ?? 1;
    
    // CORREÇÃO: Buscar dados do usuário e saldo na nova estrutura
    $stmt = $conn->prepare("
        SELECT c.saldo_principal, u.nome, u.telefone, u.status 
        FROM usuarios u 
        JOIN carteiras c ON u.id = c.usuario_id 
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $usuario_data = $stmt->fetch();
    
    if (!$usuario_data) {
        throw new Exception("Usuário não encontrado");
    }
    
    $saldo_principal = $usuario_data['saldo_principal'];
    $nome_usuario = $usuario_data['nome'];
    $telefone_usuario = $usuario_data['telefone'];
    $status_usuario = $usuario_data['status'];
    
    // Verificar se usuário está ativo
    if ($status_usuario !== 'ativo') {
        $erro = "Sua conta está inativa. Entre em contato com o suporte.";
    }
    
    // Verificar limite diário de saques
    $stmt = $conn->prepare("
        SELECT COUNT(*) as saques_hoje 
        FROM operacoes_financeiras 
        WHERE usuario_id = ? 
        AND tipo = 'saque' 
        AND DATE(created_at) = CURRENT_DATE()
    ");
    $stmt->execute([$user_id]);
    $saques_hoje = $stmt->fetchColumn();
    
    if ($saques_hoje >= $limite_diario) {
        $erro = "Você já atingiu o limite de {$limite_diario} saque(s) por dia.";
    }
    
    // Buscar chaves PIX do usuário
    $stmt = $conn->prepare("SELECT * FROM chaves_pix WHERE usuario_id = ? AND ativa = 1");
    $stmt->execute([$user_id]);
    $chave_pix_ativa = $stmt->fetch();
    
    // Verificar se tem chave PIX ativa
    if (!$chave_pix_ativa) {
        $erro = "Você precisa cadastrar e ativar uma chave PIX antes de solicitar um saque.";
    }
    
    // Processar formulário
    $success = '';
    $erro = $erro ?? '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($erro)) {
        $valor_solicitado = floatval($_POST['valor'] ?? 0);
        
        // Validações
        if ($valor_solicitado < $valor_minimo) {
            $erro = "Valor mínimo para saque é R$ " . number_format($valor_minimo, 2, ',', '.');
        } elseif ($valor_solicitado > $saldo_principal) {
            $erro = "Saldo insuficiente para este saque.";
        } else {
            // Calcular taxa
            $valor_taxa = ($valor_solicitado * $taxa_percentual) / 100;
            $valor_liquido = $valor_solicitado - $valor_taxa;
            
            try {
                $conn->beginTransaction();
                
                // CORREÇÃO: Atualizar saldo na tabela carteiras
                $stmt = $conn->prepare("UPDATE carteiras SET saldo_principal = saldo_principal - ? WHERE usuario_id = ?");
                $stmt->execute([$valor_solicitado, $user_id]);
                
                // CORREÇÃO: Inserir na nova tabela operacoes_financeiras
                $stmt = $conn->prepare("
                    INSERT INTO operacoes_financeiras (
                        usuario_id, tipo, metodo, valor_solicitado, valor_taxa, valor_liquido,
                        chave_pix, nome_titular, status, created_at
                    ) VALUES (?, 'saque', 'pix', ?, ?, ?, ?, ?, 'pendente', NOW())
                ");
                $stmt->execute([
                    $user_id,
                    $valor_solicitado,
                    $valor_taxa,
                    $valor_liquido,
                    $chave_pix_ativa['chave'],
                    $chave_pix_ativa['nome_titular']
                ]);
                
                // Registrar log se tabela existir
                try {
                    $stmt = $conn->prepare("
                        INSERT INTO logs_sistema (
                            usuario_id, acao, tabela_afetada, dados_novos, ip_address, created_at
                        ) VALUES (?, 'solicitacao_saque', 'operacoes_financeiras', ?, ?, NOW())
                    ");
                    $dados_log = json_encode([
                        'valor_solicitado' => $valor_solicitado,
                        'valor_taxa' => $valor_taxa,
                        'valor_liquido' => $valor_liquido,
                        'chave_pix' => $chave_pix_ativa['chave']
                    ]);
                    $stmt->execute([$user_id, $dados_log, $_SERVER['REMOTE_ADDR'] ?? '']);
                } catch (Exception $e) {
                    // Continuar se tabela de logs não existir
                    error_log("Erro ao registrar log: " . $e->getMessage());
                }
                
                $conn->commit();
                
                $success = "Saque solicitado com sucesso! Valor líquido: R$ " . number_format($valor_liquido, 2, ',', '.') . 
                          ". Processamento em até 72 horas.";
                
                // Atualizar saldo para exibição
                $saldo_principal -= $valor_solicitado;
                
            } catch (Exception $e) {
                $conn->rollBack();
                $erro = "Erro ao processar saque. Tente novamente.";
                error_log("Erro no saque: " . $e->getMessage());
            }
        }
    }
    
    // Buscar histórico de saques
    $stmt = $conn->prepare("
        SELECT * FROM operacoes_financeiras 
        WHERE usuario_id = ? AND tipo = 'saque'
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $historico_saques = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Erro geral no saque: " . $e->getMessage());
    $erro = "Erro interno do sistema. Tente novamente.";
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sacar Dinheiro - Finver Pro</title>
    
    <!-- Fonts & Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --background-color: #0F172A;
            --text-color: #FFFFFF;
            --primary-color: #3B82F6;
            --secondary-color: #10B981;
            --dark-background: #1E293B;
            --success-color: #10B981;
            --error-color: #EF4444;
            --warning-color: #F59E0B;
            --border-radius: 16px;
            --border-radius-sm: 8px;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            --blur-bg: rgba(255, 255, 255, 0.08);
            --border-color: rgba(255, 255, 255, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--background-color) 0%, var(--dark-background) 100%);
            min-height: 100vh;
            color: var(--text-color);
            padding: 20px;
        }

        .container {
            max-width: 400px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
        }

        .back-btn {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: var(--blur-bg);
            border: 1px solid var(--border-color);
            color: var(--text-color);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: var(--secondary-color);
            color: white;
        }

        .header-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-color);
        }

        .balance-card {
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            text-align: center;
        }

        .balance-label {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 8px;
        }

        .balance-amount {
            font-size: 32px;
            font-weight: 800;
            color: var(--secondary-color);
            margin-bottom: 15px;
        }

        .balance-info {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
        }

        .withdraw-form {
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
        }

        .form-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--text-color);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 8px;
        }

        .form-input {
            width: 100%;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            padding: 15px;
            color: var(--text-color);
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: rgba(255, 255, 255, 0.12);
        }

        .form-info {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
            margin-top: 5px;
        }

        .fee-info {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: var(--border-radius-sm);
            padding: 15px;
            margin-bottom: 20px;
        }

        .fee-info h4 {
            color: var(--warning-color);
            font-size: 14px;
            margin-bottom: 8px;
        }

        .fee-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
            font-size: 13px;
        }

        .fee-row:last-child {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid rgba(245, 158, 11, 0.2);
            font-weight: 600;
            color: var(--secondary-color);
        }

        .submit-btn {
            width: 100%;
            background: linear-gradient(135deg, var(--secondary-color), #059669);
            border: none;
            border-radius: var(--border-radius-sm);
            padding: 18px;
            color: white;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .history-section {
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 25px;
        }

        .history-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--text-color);
        }

        .history-item {
            background: rgba(255, 255, 255, 0.05);
            border-radius: var(--border-radius-sm);
            padding: 15px;
            margin-bottom: 15px;
        }

        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .history-amount {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-color);
        }

        .history-status {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pendente {
            background: rgba(245, 158, 11, 0.2);
            color: var(--warning-color);
        }

        .status-aprovado {
            background: rgba(16, 185, 129, 0.2);
            color: var(--secondary-color);
        }

        .status-rejeitado {
            background: rgba(239, 68, 68, 0.2);
            color: var(--error-color);
        }

        .history-details {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
        }

        .alert {
            padding: 15px;
            border-radius: var(--border-radius-sm);
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: var(--secondary-color);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: var(--error-color);
        }

        .pix-info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: var(--border-radius-sm);
            padding: 15px;
            margin-bottom: 20px;
        }

        .pix-info h4 {
            color: var(--primary-color);
            font-size: 14px;
            margin-bottom: 8px;
        }

        .pix-details {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.8);
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <a href="../../inicio/" class="back-btn">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="header-title">Sacar Dinheiro</h1>
        </div>

        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($success) ?>
        </div>
        <?php endif; ?>

        <?php if ($erro): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <?= htmlspecialchars($erro) ?>
        </div>
        <?php endif; ?>

        <!-- Saldo Disponível -->
        <div class="balance-card">
            <div class="balance-label">Saldo Disponível</div>
            <div class="balance-amount">R$ <?= number_format($saldo_principal, 2, ',', '.') ?></div>
            <div class="balance-info">Valor mínimo para saque: R$ <?= number_format($valor_minimo, 2, ',', '.') ?></div>
        </div>

        <?php if ($chave_pix_ativa): ?>
        <!-- Informações PIX -->
        <div class="pix-info">
            <h4><i class="fas fa-credit-card"></i> Chave PIX Ativa</h4>
            <div class="pix-details">
                <strong><?= htmlspecialchars($chave_pix_ativa['nome_titular']) ?></strong><br>
                <?= strtoupper($chave_pix_ativa['tipo']) ?>: <?= htmlspecialchars($chave_pix_ativa['chave']) ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Formulário de Saque -->
        <?php if (empty($erro) || strpos($erro, 'limite') === false): ?>
        <div class="withdraw-form">
            <h2 class="form-title">Solicitar Saque</h2>
            
            <form method="POST" action="" id="withdrawForm">
                <div class="form-group">
                    <label for="valor" class="form-label">Valor do Saque</label>
                    <input type="number" 
                           id="valor" 
                           name="valor" 
                           class="form-input" 
                           min="<?= $valor_minimo ?>" 
                           max="<?= $saldo_principal ?>"
                           step="0.01" 
                           placeholder="Digite o valor"
                           required
                           oninput="calcularTaxa()">
                    <div class="form-info">
                        Valor mínimo: R$ <?= number_format($valor_minimo, 2, ',', '.') ?> | 
                        Máximo: R$ <?= number_format($saldo_principal, 2, ',', '.') ?>
                    </div>
                </div>

                <div class="fee-info" id="feeInfo" style="display: none;">
                    <h4><i class="fas fa-calculator"></i> Cálculo do Saque</h4>
                    <div class="fee-row">
                        <span>Valor solicitado:</span>
                        <span id="valorSolicitado">R$ 0,00</span>
                    </div>
                    <div class="fee-row">
                        <span>Taxa (<?= $taxa_percentual ?>%):</span>
                        <span id="valorTaxa">R$ 0,00</span>
                    </div>
                    <div class="fee-row">
                        <span>Valor líquido:</span>
                        <span id="valorLiquido">R$ 0,00</span>
                    </div>
                </div>

                <button type="submit" class="submit-btn" <?= empty($chave_pix_ativa) ? 'disabled' : '' ?>>
                    <i class="fas fa-money-bill-wave"></i>
                    Solicitar Saque
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Histórico de Saques -->
        <div class="history-section">
            <h3 class="history-title">Histórico de Saques</h3>
            
            <?php if (empty($historico_saques)): ?>
                <div style="text-align: center; color: rgba(255, 255, 255, 0.6); padding: 20px;">
                    <i class="fas fa-inbox" style="font-size: 24px; margin-bottom: 10px;"></i>
                    <div>Nenhum saque realizado ainda</div>
                </div>
            <?php else: ?>
                <?php foreach ($historico_saques as $saque): ?>
                <div class="history-item">
                    <div class="history-header">
                        <div class="history-amount">R$ <?= number_format($saque['valor_liquido'], 2, ',', '.') ?></div>
                        <div class="history-status status-<?= $saque['status'] ?>">
                            <?= ucfirst($saque['status']) ?>
                        </div>
                    </div>
                    <div class="history-details">
                        Solicitado em: <?= date('d/m/Y H:i', strtotime($saque['created_at'])) ?><br>
                        Taxa: R$ <?= number_format($saque['valor_taxa'], 2, ',', '.') ?> | 
                        PIX: <?= htmlspecialchars($saque['chave_pix']) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function calcularTaxa() {
            const valorInput = document.getElementById('valor');
            const valor = parseFloat(valorInput.value) || 0;
            const taxaPercentual = <?= $taxa_percentual ?>;
            
            if (valor > 0) {
                const taxa = (valor * taxaPercentual) / 100;
                const liquido = valor - taxa;
                
                document.getElementById('valorSolicitado').textContent = 'R$ ' + valor.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                document.getElementById('valorTaxa').textContent = 'R$ ' + taxa.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                document.getElementById('valorLiquido').textContent = 'R$ ' + liquido.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                document.getElementById('feeInfo').style.display = 'block';
            } else {
                document.getElementById('feeInfo').style.display = 'none';
            }
        }

        // Formatação de valores
        document.getElementById('valor').addEventListener('input', function(e) {
            let value = e.target.value;
            if (value < 0) e.target.value = 0;
            if (value > <?= $saldo_principal ?>) e.target.value = <?= $saldo_principal ?>;
        });
    </script>
</body>
</html>