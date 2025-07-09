<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../');
    exit();
}

require '../bank/db.php';

try {
    $conn = getDBConnection();
    $user_id = $_SESSION['user_id'];
    
    // Buscar dados do usuário
    $stmt = $conn->prepare("SELECT nome, telefone, codigo_referencia FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        throw new Exception("Usuário não encontrado");
    }
    
    // Buscar dados financeiros da carteira
    $stmt = $conn->prepare("
        SELECT saldo_principal, saldo_bonus, saldo_comissao, 
               total_depositado, total_sacado, total_investido 
        FROM carteiras 
        WHERE usuario_id = ?
    ");
    $stmt->execute([$user_id]);
    $carteira = $stmt->fetch();
    
    if (!$carteira) {
        // Criar carteira se não existir
        $stmt = $conn->prepare("
            INSERT INTO carteiras (usuario_id, saldo_principal, saldo_bonus, saldo_comissao, 
                                 total_depositado, total_sacado, total_investido, created_at) 
            VALUES (?, 0, 0, 0, 0, 0, 0, NOW())
        ");
        $stmt->execute([$user_id]);
        
        $carteira = [
            'saldo_principal' => 0,
            'saldo_bonus' => 0,
            'saldo_comissao' => 0,
            'total_depositado' => 0,
            'total_sacado' => 0,
            'total_investido' => 0
        ];
    }
    
    // CORREÇÃO: Buscar dados da equipe usando 'referenciado_por'
    $stmt = $conn->prepare("
        SELECT u.telefone, u.data_cadastro, c.total_depositado
        FROM usuarios u 
        LEFT JOIN carteiras c ON u.id = c.usuario_id
        WHERE u.referenciado_por = ?
        ORDER BY u.data_cadastro DESC
    ");
    $stmt->execute([$user_id]);
    $indicados = $stmt->fetchAll();
    
    // Estatísticas da equipe
    $total_indicados = count($indicados);
    $total_volume_equipe = array_sum(array_column($indicados, 'total_depositado'));
    
    // CORREÇÃO: Buscar histórico de depósitos da nova tabela 'operacoes_financeiras'
    $stmt = $conn->prepare("
        SELECT status, data_processamento, valor_liquido, metodo, gateway
        FROM operacoes_financeiras 
        WHERE usuario_id = ? AND tipo = 'deposito'
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $historico_depositos = $stmt->fetchAll();
    
    // Buscar histórico de saques
    $stmt = $conn->prepare("
        SELECT status, data_processamento, valor_liquido, valor_taxa, chave_pix
        FROM operacoes_financeiras 
        WHERE usuario_id = ? AND tipo = 'saque'
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $historico_saques = $stmt->fetchAll();
    
    // Buscar investimentos ativos
    $stmt = $conn->prepare("
        SELECT i.valor_investido, i.rendimento_acumulado, i.dias_restantes, 
               i.data_vencimento, i.status, p.titulo
        FROM investimentos i
        JOIN produtos p ON i.produto_id = p.id
        WHERE i.usuario_id = ?
        ORDER BY i.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $investimentos = $stmt->fetchAll();
    
    // Buscar comissões recebidas
    $stmt = $conn->prepare("
        SELECT valor_comissao, nivel_comissao, tipo, status, created_at
        FROM comissoes 
        WHERE usuario_id = ?
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $historico_comissoes = $stmt->fetchAll();
    
    // Calcular totais
    $total_investimentos = array_sum(array_column($investimentos, 'valor_investido'));
    $total_rendimentos = array_sum(array_column($investimentos, 'rendimento_acumulado'));
    $total_comissoes = array_sum(array_map(function($item) {
        return $item['status'] === 'pago' ? $item['valor_comissao'] : 0;
    }, $historico_comissoes));
    
} catch (Exception $e) {
    error_log("Erro nos relatórios: " . $e->getMessage());
    die("Erro ao carregar relatórios. Tente novamente.");
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - Finver Pro</title>
    
    <!-- Fonts & Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 20px;
            text-align: center;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--secondary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: white;
            font-size: 18px;
        }

        .stat-value {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
        }

        .section-card {
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 12px 8px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 13px;
        }

        .table th {
            font-weight: 600;
            color: rgba(255, 255, 255, 0.8);
            background: rgba(255, 255, 255, 0.05);
        }

        .table td {
            color: var(--text-color);
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-aprovado {
            background: rgba(16, 185, 129, 0.2);
            color: var(--secondary-color);
        }

        .status-pendente {
            background: rgba(245, 158, 11, 0.2);
            color: var(--warning-color);
        }

        .status-rejeitado {
            background: rgba(239, 68, 68, 0.2);
            color: var(--error-color);
        }

        .status-ativo {
            background: rgba(16, 185, 129, 0.2);
            color: var(--secondary-color);
        }

        .status-concluido {
            background: rgba(59, 130, 246, 0.2);
            color: var(--primary-color);
        }

        .amount-positive {
            color: var(--secondary-color);
            font-weight: 600;
        }

        .amount-negative {
            color: var(--error-color);
            font-weight: 600;
        }

        .no-data {
            text-align: center;
            color: rgba(255, 255, 255, 0.6);
            padding: 40px 20px;
        }

        .no-data i {
            font-size: 32px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .tabs {
            display: flex;
            background: rgba(255, 255, 255, 0.05);
            border-radius: var(--border-radius-sm);
            padding: 5px;
            margin-bottom: 20px;
        }

        .tab {
            flex: 1;
            padding: 10px;
            text-align: center;
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 12px;
            font-weight: 600;
        }

        .tab.active {
            background: var(--secondary-color);
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .summary-item {
            background: rgba(255, 255, 255, 0.05);
            border-radius: var(--border-radius-sm);
            padding: 15px;
            text-align: center;
        }

        .summary-value {
            font-size: 16px;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 5px;
        }

        .summary-label {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.7);
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <a href="../inicio/" class="back-btn">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="header-title">Relatórios</h1>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="stat-value">R$ <?= number_format($carteira['saldo_principal'], 2, ',', '.') ?></div>
                <div class="stat-label">Saldo Principal</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value">R$ <?= number_format($total_investimentos, 2, ',', '.') ?></div>
                <div class="stat-label">Total Investido</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="stat-value">R$ <?= number_format($total_rendimentos, 2, ',', '.') ?></div>
                <div class="stat-label">Rendimentos</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?= $total_indicados ?></div>
                <div class="stat-label">Indicados</div>
            </div>
        </div>

        <!-- Resumo Financeiro -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="fas fa-chart-pie"></i>
                Resumo Financeiro
            </h2>
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-value">R$ <?= number_format($carteira['total_depositado'], 2, ',', '.') ?></div>
                    <div class="summary-label">Total Depositado</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value">R$ <?= number_format($carteira['total_sacado'], 2, ',', '.') ?></div>
                    <div class="summary-label">Total Sacado</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value">R$ <?= number_format($total_comissoes, 2, ',', '.') ?></div>
                    <div class="summary-label">Comissões</div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="section-card">
            <div class="tabs">
                <div class="tab active" onclick="showTab('depositos')">Depósitos</div>
                <div class="tab" onclick="showTab('saques')">Saques</div>
                <div class="tab" onclick="showTab('investimentos')">Investimentos</div>
            </div>

            <!-- Depósitos -->
            <div class="tab-content active" id="depositos">
                <?php if (empty($historico_depositos)): ?>
                <div class="no-data">
                    <i class="fas fa-inbox"></i>
                    <div>Nenhum depósito encontrado</div>
                </div>
                <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Valor</th>
                            <th>Status</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historico_depositos as $deposito): ?>
                        <tr>
                            <td class="amount-positive">R$ <?= number_format($deposito['valor_liquido'], 2, ',', '.') ?></td>
                            <td>
                                <span class="status-badge status-<?= $deposito['status'] ?>">
                                    <?= ucfirst($deposito['status']) ?>
                                </span>
                            </td>
                            <td><?= $deposito['data_processamento'] ? date('d/m/Y', strtotime($deposito['data_processamento'])) : 'Pendente' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <!-- Saques -->
            <div class="tab-content" id="saques">
                <?php if (empty($historico_saques)): ?>
                <div class="no-data">
                    <i class="fas fa-inbox"></i>
                    <div>Nenhum saque encontrado</div>
                </div>
                <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Valor</th>
                            <th>Status</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historico_saques as $saque): ?>
                        <tr>
                            <td class="amount-negative">R$ <?= number_format($saque['valor_liquido'], 2, ',', '.') ?></td>
                            <td>
                                <span class="status-badge status-<?= $saque['status'] ?>">
                                    <?= ucfirst($saque['status']) ?>
                                </span>
                            </td>
                            <td><?= $saque['data_processamento'] ? date('d/m/Y', strtotime($saque['data_processamento'])) : 'Pendente' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <!-- Investimentos -->
            <div class="tab-content" id="investimentos">
                <?php if (empty($investimentos)): ?>
                <div class="no-data">
                    <i class="fas fa-chart-line"></i>
                    <div>Nenhum investimento encontrado</div>
                </div>
                <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Valor</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($investimentos as $investimento): ?>
                        <tr>
                            <td><?= htmlspecialchars($investimento['titulo']) ?></td>
                            <td class="amount-positive">R$ <?= number_format($investimento['valor_investido'], 2, ',', '.') ?></td>
                            <td>
                                <span class="status-badge status-<?= $investimento['status'] ?>">
                                    <?= ucfirst($investimento['status']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Equipe -->
        <?php if (!empty($indicados)): ?>
        <div class="section-card">
            <h2 class="section-title">
                <i class="fas fa-users"></i>
                Minha Equipe (<?= $total_indicados ?>)
            </h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Telefone</th>
                        <th>Depósitos</th>
                        <th>Cadastro</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($indicados, 0, 10) as $indicado): ?>
                    <tr>
                        <td><?= htmlspecialchars(substr($indicado['telefone'], 0, -4)) ?>****</td>
                        <td class="amount-positive">R$ <?= number_format($indicado['total_depositado'] ?? 0, 2, ',', '.') ?></td>
                        <td><?= date('d/m/Y', strtotime($indicado['data_cadastro'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }
    </script>
</body>
</html>