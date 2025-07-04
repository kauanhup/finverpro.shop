<?php
/**
 * ========================================
 * FINVER PRO - DASHBOARD ADMINISTRATIVO
 * Painel Principal de Controle
 * ========================================
 */

require_once '../includes/auth.php';
require_once '../../config/database.php';

// Verificar autenticação
requireAdmin();

$admin = getAdminData();
$db = Database::getInstance();

// Obter estatísticas gerais
try {
    // Usuários
    $totalUsuarios = $db->fetchOne("SELECT COUNT(*) as total FROM usuarios")['total'] ?? 0;
    $usuariosHoje = $db->fetchOne("SELECT COUNT(*) as total FROM usuarios WHERE DATE(created_at) = CURDATE()")['total'] ?? 0;
    $usuariosAtivos = $db->fetchOne("SELECT COUNT(*) as total FROM usuarios WHERE status = 'ativo'")['total'] ?? 0;
    
    // Investimentos
    $totalInvestimentos = $db->fetchOne("SELECT COUNT(*) as total FROM investimentos WHERE status = 'ativo'")['total'] ?? 0;
    $valorTotalInvestido = $db->fetchOne("SELECT SUM(valor_investido) as total FROM investimentos WHERE status = 'ativo'")['total'] ?? 0;
    $investimentosHoje = $db->fetchOne("SELECT COUNT(*) as total FROM investimentos WHERE DATE(created_at) = CURDATE()")['total'] ?? 0;
    
    // Saques
    $saquesPendentes = $db->fetchOne("SELECT COUNT(*) as total FROM saques WHERE status = 'pendente'")['total'] ?? 0;
    $valorSaquesPendentes = $db->fetchOne("SELECT SUM(valor_bruto) as total FROM saques WHERE status = 'pendente'")['total'] ?? 0;
    $saquesHoje = $db->fetchOne("SELECT COUNT(*) as total FROM saques WHERE DATE(created_at) = CURDATE()")['total'] ?? 0;
    
    // Transações
    $transacoesHoje = $db->fetchOne("SELECT COUNT(*) as total FROM transacoes WHERE DATE(created_at) = CURDATE()")['total'] ?? 0;
    $depositosHoje = $db->fetchOne("SELECT SUM(valor) as total FROM transacoes WHERE tipo = 'deposito' AND DATE(created_at) = CURDATE() AND status = 'concluido'")['total'] ?? 0;
    
    // Comissões
    $comissoesPendentes = $db->fetchOne("SELECT COUNT(*) as total FROM comissoes WHERE status = 'pendente'")['total'] ?? 0;
    $valorComissoesPendentes = $db->fetchOne("SELECT SUM(valor_comissao) as total FROM comissoes WHERE status = 'pendente'")['total'] ?? 0;
    
    // Últimos usuários registrados
    $ultimosUsuarios = $db->fetchAll("
        SELECT id, nome, telefone, created_at, status 
        FROM usuarios 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    
    // Últimos saques pendentes
    $ultimosSaques = $db->fetchAll("
        SELECT s.id, s.valor_bruto, s.created_at, u.nome, u.telefone, cp.chave_pix
        FROM saques s
        JOIN usuarios u ON s.usuario_id = u.id
        JOIN chaves_pix cp ON s.chave_pix_id = cp.id
        WHERE s.status = 'pendente'
        ORDER BY s.created_at DESC
        LIMIT 5
    ");
    
    // Produtos mais populares
    $produtosPopulares = $db->fetchAll("
        SELECT p.titulo, COUNT(i.id) as total_investimentos, SUM(i.valor_investido) as valor_total
        FROM produtos p
        LEFT JOIN investimentos i ON p.id = i.produto_id AND i.status = 'ativo'
        GROUP BY p.id, p.titulo
        ORDER BY total_investimentos DESC
        LIMIT 5
    ");
    
} catch (Exception $e) {
    error_log("Erro ao carregar dashboard: " . $e->getMessage());
    $totalUsuarios = $usuariosHoje = $usuariosAtivos = 0;
    $totalInvestimentos = $valorTotalInvestido = $investimentosHoje = 0;
    $saquesPendentes = $valorSaquesPendentes = $saquesHoje = 0;
    $transacoesHoje = $depositosHoje = 0;
    $comissoesPendentes = $valorComissoesPendentes = 0;
    $ultimosUsuarios = $ultimosSaques = $produtosPopulares = [];
}

// Registrar acesso ao dashboard
logAdminAction('dashboard.access', 'Acesso ao dashboard administrativo');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administrativo - Finver Pro</title>
    
    <!-- Fonts & Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #152731;
            --secondary-color: #335D67;
            --background-color: #121A1E;
            --text-color: #FFFFFF;
            --accent-color: #152731;
            --success-color: #10B981;
            --error-color: #EF4444;
            --warning-color: #F59E0B;
            --info-color: #3B82F6;
            --border-radius: 12px;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --sidebar-width: 280px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--background-color);
            color: var(--text-color);
            line-height: 1.6;
        }
        
        /* Layout Principal */
        .admin-layout {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            padding: 1.5rem;
            box-shadow: var(--shadow-lg);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .sidebar-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--success-color), var(--info-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: white;
        }
        
        .sidebar-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .sidebar-subtitle {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .sidebar-nav {
            list-style: none;
        }
        
        .nav-item {
            margin-bottom: 0.5rem;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.875rem 1rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: var(--border-radius);
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .nav-link:hover, .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }
        
        .nav-link i {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
        }
        
        .nav-badge {
            margin-left: auto;
            background: var(--error-color);
            color: white;
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 10px;
            font-weight: 600;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 2rem;
        }
        
        .page-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--text-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .page-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .admin-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: rgba(255, 255, 255, 0.1);
            padding: 0.75rem 1rem;
            border-radius: var(--border-radius);
        }
        
        .admin-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--info-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        .logout-btn {
            background: var(--error-color);
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .logout-btn:hover {
            background: #DC2626;
            transform: translateY(-2px);
        }
        
        /* Cards de Estatísticas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--accent-color);
        }
        
        .stat-card.success::before { background: var(--success-color); }
        .stat-card.warning::before { background: var(--warning-color); }
        .stat-card.error::before { background: var(--error-color); }
        .stat-card.info::before { background: var(--info-color); }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .stat-icon.success { background: var(--success-color); }
        .stat-icon.warning { background: var(--warning-color); }
        .stat-icon.error { background: var(--error-color); }
        .stat-icon.info { background: var(--info-color); }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }
        
        .stat-change {
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .stat-change.positive { color: var(--success-color); }
        .stat-change.negative { color: var(--error-color); }
        
        /* Seções de Dados */
        .data-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .data-section {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--border-radius);
            padding: 1.5rem;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
        }
        
        .section-link {
            color: var(--info-color);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .section-link:hover {
            text-decoration: underline;
        }
        
        .data-list {
            list-style: none;
        }
        
        .data-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .data-item:last-child {
            border-bottom: none;
        }
        
        .item-info h4 {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .item-info p {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .item-value {
            text-align: right;
        }
        
        .item-value .value {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .item-value .label {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.6);
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-badge.ativo { background: rgba(16, 185, 129, 0.2); color: #10B981; }
        .status-badge.pendente { background: rgba(245, 158, 11, 0.2); color: #F59E0B; }
        .status-badge.inativo { background: rgba(239, 68, 68, 0.2); color: #EF4444; }
        
        /* Responsividade */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            }
            
            .data-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            .page-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .page-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h2 class="sidebar-title">Admin Panel</h2>
                <p class="sidebar-subtitle">Finver Pro</p>
            </div>
            
            <ul class="sidebar-nav">
                <li class="nav-item">
                    <a href="#" class="nav-link active">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../usuarios/" class="nav-link">
                        <i class="fas fa-users"></i>
                        Usuários
                        <span class="nav-badge"><?= $usuariosHoje ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../produtos/" class="nav-link">
                        <i class="fas fa-robot"></i>
                        Produtos/Robôs
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../saques/" class="nav-link">
                        <i class="fas fa-money-bill-wave"></i>
                        Saques
                        <?php if ($saquesPendentes > 0): ?>
                            <span class="nav-badge"><?= $saquesPendentes ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../pagamentos/" class="nav-link">
                        <i class="fas fa-credit-card"></i>
                        Pagamentos
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../relatorios/" class="nav-link">
                        <i class="fas fa-chart-bar"></i>
                        Relatórios
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../configuracoes/" class="nav-link">
                        <i class="fas fa-cog"></i>
                        Configurações
                    </a>
                </li>
            </ul>
        </nav>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">Dashboard</h1>
                <div class="page-actions">
                    <div class="admin-info">
                        <div class="admin-avatar">
                            <?= strtoupper(substr($admin['nome'] ?? 'A', 0, 1)) ?>
                        </div>
                        <div>
                            <div style="font-weight: 500;"><?= htmlspecialchars($admin['nome'] ?? 'Administrador') ?></div>
                            <div style="font-size: 0.875rem; color: rgba(255,255,255,0.7);"><?= htmlspecialchars($admin['nivel'] ?? 'admin') ?></div>
                        </div>
                    </div>
                    <a href="../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        Sair
                    </a>
                </div>
            </div>
            
            <!-- Cards de Estatísticas -->
            <div class="stats-grid">
                <div class="stat-card success">
                    <div class="stat-header">
                        <div class="stat-icon success">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($totalUsuarios) ?></div>
                    <div class="stat-label">Total de Usuários</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        +<?= $usuariosHoje ?> hoje
                    </div>
                </div>
                
                <div class="stat-card info">
                    <div class="stat-header">
                        <div class="stat-icon info">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($totalInvestimentos) ?></div>
                    <div class="stat-label">Investimentos Ativos</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        +<?= $investimentosHoje ?> hoje
                    </div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-header">
                        <div class="stat-icon warning">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= $saquesPendentes ?></div>
                    <div class="stat-label">Saques Pendentes</div>
                    <div class="stat-change">
                        R$ <?= number_format($valorSaquesPendentes, 2, ',', '.') ?>
                    </div>
                </div>
                
                <div class="stat-card error">
                    <div class="stat-header">
                        <div class="stat-icon error">
                            <i class="fas fa-coins"></i>
                        </div>
                    </div>
                    <div class="stat-value">R$ <?= number_format($valorTotalInvestido, 0, ',', '.') ?></div>
                    <div class="stat-label">Valor Total Investido</div>
                    <div class="stat-change positive">
                        R$ <?= number_format($depositosHoje, 2, ',', '.') ?> hoje
                    </div>
                </div>
            </div>
            
            <!-- Seções de Dados -->
            <div class="data-grid">
                <!-- Últimos Usuários -->
                <div class="data-section">
                    <div class="section-header">
                        <h3 class="section-title">Últimos Usuários</h3>
                        <a href="../usuarios/" class="section-link">Ver todos</a>
                    </div>
                    
                    <ul class="data-list">
                        <?php foreach ($ultimosUsuarios as $usuario): ?>
                            <li class="data-item">
                                <div class="item-info">
                                    <h4><?= htmlspecialchars($usuario['nome'] ?: 'Usuário') ?></h4>
                                    <p><?= htmlspecialchars($usuario['telefone']) ?></p>
                                </div>
                                <div class="item-value">
                                    <div class="value">
                                        <span class="status-badge <?= $usuario['status'] ?>">
                                            <?= ucfirst($usuario['status']) ?>
                                        </span>
                                    </div>
                                    <div class="label"><?= date('d/m/Y H:i', strtotime($usuario['created_at'])) ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                        
                        <?php if (empty($ultimosUsuarios)): ?>
                            <li class="data-item">
                                <div class="item-info">
                                    <p style="color: rgba(255,255,255,0.6);">Nenhum usuário encontrado</p>
                                </div>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <!-- Saques Pendentes -->
                <div class="data-section">
                    <div class="section-header">
                        <h3 class="section-title">Saques Pendentes</h3>
                        <a href="../saques/" class="section-link">Ver todos</a>
                    </div>
                    
                    <ul class="data-list">
                        <?php foreach ($ultimosSaques as $saque): ?>
                            <li class="data-item">
                                <div class="item-info">
                                    <h4><?= htmlspecialchars($saque['nome'] ?: 'Usuário') ?></h4>
                                    <p><?= htmlspecialchars(substr($saque['chave_pix'], 0, 20)) ?>...</p>
                                </div>
                                <div class="item-value">
                                    <div class="value">R$ <?= number_format($saque['valor_bruto'], 2, ',', '.') ?></div>
                                    <div class="label"><?= date('d/m/Y H:i', strtotime($saque['created_at'])) ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                        
                        <?php if (empty($ultimosSaques)): ?>
                            <li class="data-item">
                                <div class="item-info">
                                    <p style="color: rgba(255,255,255,0.6);">Nenhum saque pendente</p>
                                </div>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <!-- Produtos Populares -->
                <div class="data-section">
                    <div class="section-header">
                        <h3 class="section-title">Produtos Populares</h3>
                        <a href="../produtos/" class="section-link">Ver todos</a>
                    </div>
                    
                    <ul class="data-list">
                        <?php foreach ($produtosPopulares as $produto): ?>
                            <li class="data-item">
                                <div class="item-info">
                                    <h4><?= htmlspecialchars($produto['titulo']) ?></h4>
                                    <p><?= $produto['total_investimentos'] ?> investimentos</p>
                                </div>
                                <div class="item-value">
                                    <div class="value">R$ <?= number_format($produto['valor_total'] ?? 0, 0, ',', '.') ?></div>
                                    <div class="label">Total investido</div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                        
                        <?php if (empty($produtosPopulares)): ?>
                            <li class="data-item">
                                <div class="item-info">
                                    <p style="color: rgba(255,255,255,0.6);">Nenhum produto encontrado</p>
                                </div>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Auto-refresh do dashboard a cada 30 segundos
        setInterval(() => {
            // Recarregar apenas os dados dinâmicos via AJAX
            // Por enquanto, deixamos assim para simplicidade
        }, 30000);
        
        // Toggle sidebar em mobile
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('open');
        }
        
        // Adicionar botão de menu para mobile
        if (window.innerWidth <= 1024) {
            const pageHeader = document.querySelector('.page-header');
            const menuBtn = document.createElement('button');
            menuBtn.innerHTML = '<i class="fas fa-bars"></i>';
            menuBtn.style.cssText = 'background: var(--primary-color); border: none; color: white; padding: 0.5rem; border-radius: 6px; cursor: pointer;';
            menuBtn.onclick = toggleSidebar;
            pageHeader.insertBefore(menuBtn, pageHeader.firstChild);
        }
    </script>
</body>
</html>