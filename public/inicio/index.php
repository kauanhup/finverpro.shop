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
    
    // CORREÇÃO: Buscar dados do usuário incluindo novos campos
    $stmt = $conn->prepare("SELECT *, tipo_usuario, status FROM usuarios WHERE id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_data) {
        session_destroy();
        header('Location: ../');
        exit();
    }
    
    // Verificar se usuário está ativo
    if ($user_data['status'] !== 'ativo') {
        session_destroy();
        header('Location: ../?message=Conta inativa. Entre em contato com o suporte.&toastType=error');
        exit();
    }
    
    // Buscar dados da carteira
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
    
    // Buscar investimentos ativos
    $stmt = $conn->prepare("
        SELECT i.*, p.titulo, p.rendimento_diario, p.tipo_rendimento
        FROM investimentos i
        JOIN produtos p ON i.produto_id = p.id
        WHERE i.usuario_id = ? AND i.status = 'ativo'
        ORDER BY i.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $investimentos_ativos = $stmt->fetchAll();
    
    // Calcular rendimento diário total
    $rendimento_diario = 0;
    foreach ($investimentos_ativos as $inv) {
        if ($inv['tipo_rendimento'] === 'percentual_diario') {
            $rendimento_diario += ($inv['valor_investido'] * $inv['rendimento_diario'] / 100);
        } else {
            $rendimento_diario += $inv['rendimento_diario'];
        }
    }
    
    // Buscar produtos disponíveis
    $stmt = $conn->prepare("
        SELECT * FROM produtos 
        WHERE status = 'ativo' 
        ORDER BY valor_minimo ASC 
        LIMIT 6
    ");
    $stmt->execute();
    $produtos = $stmt->fetchAll();
    
    // Buscar últimas transações
    $stmt = $conn->prepare("
        SELECT tipo, valor_liquido, status, created_at, metodo
        FROM operacoes_financeiras 
        WHERE usuario_id = ?
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $ultimas_transacoes = $stmt->fetchAll();
    
    // Buscar estatísticas da equipe
    $stmt = $conn->prepare("SELECT COUNT(*) FROM usuarios WHERE referenciado_por = ?");
    $stmt->execute([$user_id]);
    $total_indicados = $stmt->fetchColumn();
    
    // Buscar configurações do site
    $stmt = $conn->query("SELECT chave, valor FROM configuracoes WHERE categoria IN ('sistema', 'design')");
    $configs = [];
    while ($row = $stmt->fetch()) {
        $configs[$row['chave']] = $row['valor'];
    }
    
    $nome_site = $configs['nome_site'] ?? 'Finver Pro';
    $link_suporte = $configs['telefone_suporte'] ?? 'https://t.me/finverpro';
    
} catch (Exception $e) {
    error_log("Erro no início: " . $e->getMessage());
    die("Erro interno. Tente novamente.");
}

// Preparar dados para exibição
$nome_usuario = !empty($user_data['nome']) ? $user_data['nome'] : 'Investidor';
$saldo_total = $carteira['saldo_principal'] + $carteira['saldo_bonus'];
$total_investido = count($investimentos_ativos);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($nome_site) ?> - Dashboard</title>
    
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
            padding: 0 0 80px 0;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 20%, rgba(51, 93, 103, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(139, 92, 246, 0.1) 0%, transparent 50%);
            pointer-events: none;
            z-index: -1;
        }

        .container {
            max-width: 400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header */
        .header {
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            text-align: center;
        }

        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--secondary-color), #059669);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: white;
            font-size: 32px;
            overflow: hidden;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .welcome-text {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .user-name {
            font-size: 24px;
            font-weight: 800;
            color: var(--secondary-color);
        }

        /* Balance Section */
        .balance-section {
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .balance-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--secondary-color), #059669);
        }

        .balance-label {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 10px;
        }

        .balance-amount {
            font-size: 36px;
            font-weight: 800;
            color: var(--text-color);
            margin-bottom: 15px;
        }

        .balance-breakdown {
            display: flex;
            justify-content: space-between;
            gap: 15px;
        }

        .balance-item {
            flex: 1;
            background: rgba(255, 255, 255, 0.05);
            border-radius: var(--border-radius-sm);
            padding: 12px;
        }

        .balance-item-value {
            font-size: 14px;
            font-weight: 700;
            color: var(--secondary-color);
        }

        .balance-item-label {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.6);
            margin-top: 3px;
        }

        /* Quick Stats */
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
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
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow);
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--secondary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            color: white;
            font-size: 16px;
        }

        .stat-value {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.7);
        }

        /* Action Buttons */
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .action-btn {
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 20px;
            text-decoration: none;
            color: var(--text-color);
            text-align: center;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow);
            background: var(--secondary-color);
            color: white;
        }

        .action-btn i {
            font-size: 24px;
            color: var(--secondary-color);
            transition: all 0.3s ease;
        }

        .action-btn:hover i {
            color: white;
        }

        .action-btn-text {
            font-size: 14px;
            font-weight: 600;
        }

        /* Products Section */
        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .product-card {
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 20px;
            text-decoration: none;
            color: var(--text-color);
            transition: all 0.3s ease;
        }

        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow);
            border-color: var(--secondary-color);
        }

        .product-title {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .product-price {
            font-size: 16px;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 5px;
        }

        .product-return {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
        }

        /* Recent Activity */
        .activity-list {
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--secondary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 3px;
        }

        .activity-date {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
        }

        .activity-amount {
            font-size: 14px;
            font-weight: 700;
            color: var(--secondary-color);
        }

        /* Footer Navigation */
        .footer-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border-top: 1px solid var(--border-color);
            padding: 15px 20px;
            z-index: 100;
        }

        .nav-items {
            display: flex;
            justify-content: space-around;
            align-items: center;
            max-width: 400px;
            margin: 0 auto;
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            text-decoration: none;
            color: rgba(255, 255, 255, 0.6);
            transition: all 0.3s ease;
            padding: 8px;
            border-radius: 12px;
            min-width: 60px;
        }

        .nav-item.active,
        .nav-item:hover {
            color: var(--secondary-color);
            background: rgba(16, 185, 129, 0.1);
        }

        .nav-item i {
            font-size: 18px;
        }

        .nav-item span {
            font-size: 10px;
            font-weight: 500;
        }

        .no-data {
            text-align: center;
            color: rgba(255, 255, 255, 0.6);
            padding: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="user-avatar">
                <?php if ($user_data['foto_perfil'] && file_exists("../uploads/fotos/" . $user_data['foto_perfil'])): ?>
                    <img src="../uploads/fotos/<?= htmlspecialchars($user_data['foto_perfil']) ?>" alt="Foto do usuário">
                <?php else: ?>
                    <i class="fas fa-user"></i>
                <?php endif; ?>
            </div>
            <div class="welcome-text">Bem-vindo de volta,</div>
            <div class="user-name"><?= htmlspecialchars($nome_usuario) ?></div>
        </div>

        <!-- Balance Section -->
        <div class="balance-section">
            <div class="balance-label">Saldo Total Disponível</div>
            <div class="balance-amount">R$ <?= number_format($saldo_total, 2, ',', '.') ?></div>
            <div class="balance-breakdown">
                <div class="balance-item">
                    <div class="balance-item-value">R$ <?= number_format($carteira['saldo_principal'], 2, ',', '.') ?></div>
                    <div class="balance-item-label">Principal</div>
                </div>
                <div class="balance-item">
                    <div class="balance-item-value">R$ <?= number_format($carteira['saldo_bonus'], 2, ',', '.') ?></div>
                    <div class="balance-item-label">Bônus</div>
                </div>
                <div class="balance-item">
                    <div class="balance-item-value">R$ <?= number_format($carteira['saldo_comissao'], 2, ',', '.') ?></div>
                    <div class="balance-item-label">Comissão</div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="quick-stats">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value"><?= $total_investido ?></div>
                <div class="stat-label">Investimentos</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="stat-value">R$ <?= number_format($rendimento_diario, 2, ',', '.') ?></div>
                <div class="stat-label">Renda/Dia</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?= $total_indicados ?></div>
                <div class="stat-label">Indicados</div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="../investimentos/" class="action-btn">
                <i class="fas fa-chart-line"></i>
                <span class="action-btn-text">Investir</span>
            </a>
            <a href="../retirar/dinheiro/" class="action-btn">
                <i class="fas fa-money-bill-wave"></i>
                <span class="action-btn-text">Sacar</span>
            </a>
            <a href="../gate/" class="action-btn">
                <i class="fas fa-credit-card"></i>
                <span class="action-btn-text">Depositar</span>
            </a>
            <a href="../team/" class="action-btn">
                <i class="fas fa-users"></i>
                <span class="action-btn-text">Equipe</span>
            </a>
        </div>

        <!-- Products -->
        <?php if (!empty($produtos)): ?>
        <h2 class="section-title">
            <i class="fas fa-rocket"></i>
            Produtos em Destaque
        </h2>
        <div class="products-grid">
            <?php foreach (array_slice($produtos, 0, 4) as $produto): ?>
            <a href="../detalhes/investimento/?id=<?= $produto['id'] ?>" class="product-card">
                <div class="product-title"><?= htmlspecialchars($produto['titulo']) ?></div>
                <div class="product-price">R$ <?= number_format($produto['valor_minimo'], 2, ',', '.') ?></div>
                <div class="product-return">
                    <?= number_format($produto['rendimento_diario'], 2) ?><?= $produto['tipo_rendimento'] === 'percentual_diario' ? '%' : '' ?> por dia
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Recent Activity -->
        <div class="activity-list">
            <h2 class="section-title">
                <i class="fas fa-history"></i>
                Atividade Recente
            </h2>
            <?php if (empty($ultimas_transacoes)): ?>
            <div class="no-data">
                <i class="fas fa-inbox" style="font-size: 24px; margin-bottom: 10px; opacity: 0.5;"></i>
                <div>Nenhuma transação encontrada</div>
            </div>
            <?php else: ?>
            <?php foreach ($ultimas_transacoes as $transacao): ?>
            <div class="activity-item">
                <div class="activity-icon">
                    <?php if ($transacao['tipo'] === 'deposito'): ?>
                        <i class="fas fa-arrow-down"></i>
                    <?php else: ?>
                        <i class="fas fa-arrow-up"></i>
                    <?php endif; ?>
                </div>
                <div class="activity-content">
                    <div class="activity-title">
                        <?= $transacao['tipo'] === 'deposito' ? 'Depósito' : 'Saque' ?>
                        <?= $transacao['metodo'] ? ' via ' . strtoupper($transacao['metodo']) : '' ?>
                    </div>
                    <div class="activity-date"><?= date('d/m/Y H:i', strtotime($transacao['created_at'])) ?></div>
                </div>
                <div class="activity-amount">
                    <?= $transacao['tipo'] === 'deposito' ? '+' : '-' ?>R$ <?= number_format($transacao['valor_liquido'], 2, ',', '.') ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer Navigation -->
    <div class="footer-nav">
        <div class="nav-items">
            <a href="./" class="nav-item active">
                <i class="fas fa-home"></i>
                <span>Início</span>
            </a>
            <a href="../investimentos/" class="nav-item">
                <i class="fas fa-chart-line"></i>
                <span>Investir</span>
            </a>
            <a href="../team/" class="nav-item">
                <i class="fas fa-users"></i>
                <span>Equipe</span>
            </a>
            <a href="../perfil/" class="nav-item">
                <i class="fas fa-user"></i>
                <span>Perfil</span>
            </a>
        </div>
    </div>
</body>
</html>