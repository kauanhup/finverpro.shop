<?php
session_start();
require_once '../bank/db.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Buscar dados do usuário
    $stmt = $pdo->prepare("SELECT u.*, c.saldo_principal, c.saldo_bonus, c.saldo_comissao FROM usuarios u LEFT JOIN carteiras c ON u.id = c.usuario_id WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        header('Location: ../index.php');
        exit();
    }

    // Calcular saldo total
    $saldo_total = ($user['saldo_principal'] ?? 0) + ($user['saldo_bonus'] ?? 0) + ($user['saldo_comissao'] ?? 0);

    // Buscar investimentos ativos
    $stmt = $pdo->prepare("SELECT i.*, p.titulo, p.rendimento_diario FROM investimentos i JOIN produtos p ON i.produto_id = p.id WHERE i.usuario_id = ? AND i.status = 'ativo' ORDER BY i.created_at DESC LIMIT 3");
    $stmt->execute([$user_id]);
    $investimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Atualizar último login
    $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?");
    $stmt->execute([$user_id]);

} catch (PDOException $e) {
    error_log("Erro no dashboard: " . $e->getMessage());
    $user = ['nome' => 'Usuário', 'codigo_referencia' => 'N/A'];
    $saldo_total = 0;
    $investimentos = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FinverPro - Dashboard</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/reu-dashboard.css">
</head>

<body>
    <!-- Fixed Header -->
    <header class="fixed-header">
        <div class="header-left">
            <button class="hamburger" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div class="header-title">FinverPro</div>
        </div>
        
        <div class="language-selector">
            <button class="language-btn" onclick="toggleLanguageDropdown()">
                <i class="fas fa-globe"></i>
                <span id="current-language">Português</span>
                <i class="fas fa-chevron-down"></i>
            </button>
            
            <div class="language-dropdown" id="language-dropdown">
                <div class="language-option active" onclick="changeLanguage('pt', 'Português')">
                    <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjQiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjI0IiBoZWlnaHQ9IjI0IiByeD0iMiIgZmlsbD0iIzAwOTczOSIvPgo8cmVjdCB5PSI4IiB3aWR0aD0iMjQiIGhlaWdodD0iOCIgZmlsbD0iI0ZGRkYwMCIvPgo8Y2lyY2xlIGN4PSI5IiBjeT0iMTIiIHI9IjMuNSIgZmlsbD0iIzAwMjc2OCIvPgo8L3N2Zz4=" alt="PT" width="20" height="14">
                    Português
                </div>
                <div class="language-option" onclick="changeLanguage('en', 'English')">
                    <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjQiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjI0IiBoZWlnaHQ9IjI0IiByeD0iMiIgZmlsbD0iIzAwNTJCNCIvPgo8cmVjdCB5PSI5IiB3aWR0aD0iMjQiIGhlaWdodD0iMS41IiBmaWxsPSIjRkZGRkZGIi8+CjxyZWN0IHk9IjEzLjUiIHdpZHRoPSIyNCIgaGVpZ2h0PSIxLjUiIGZpbGw9IiNGRkZGRkYiLz4KPC9zdmc+" alt="EN" width="20" height="14">
                    English
                </div>
                <div class="language-option" onclick="changeLanguage('es', 'Español')">
                    <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjQiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjI0IiBoZWlnaHQ9IjI0IiByeD0iMiIgZmlsbD0iI0FBMTUyQSIvPgo8cmVjdCB5PSI2IiB3aWR0aD0iMjQiIGhlaWdodD0iMTIiIGZpbGw9IiNGRkM0MDAiLz4KPC9zdmc+" alt="ES" width="20" height="14">
                    Español
                </div>
            </div>
        </div>
    </header>

    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <!-- Logo Section -->
        <div class="sidebar-logo">
            <div class="logo-container">
                <div class="logo-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="logo-text">
                    <div class="logo-title">FinverPro</div>
                    <div class="logo-subtitle">Investment Platform</div>
                </div>
            </div>
        </div>
        
        <div class="sidebar-content">
            <div class="nav-section">
                <div class="nav-section-title" data-translate="basic">Básico</div>
                <a href="#" class="nav-item active">
                    <i class="fas fa-home"></i>
                    <span data-translate="home">Início</span>
                </a>
                <a href="../equipe/" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span data-translate="team">Equipe</span>
                </a>
                <a href="../salarios/" class="nav-item">
                    <i class="fas fa-dollar-sign"></i>
                    <span data-translate="salaries">Salários</span>
                </a>
                <a href="../perfil/" class="nav-item">
                    <i class="fas fa-user-circle"></i>
                    <span data-translate="profile">Perfil</span>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title" data-translate="investments">Investimentos</div>
                <a href="../investimentos/" class="nav-item">
                    <i class="fas fa-coins"></i>
                    <span data-translate="products">Produtos</span>
                </a>
                <a href="../investimentos/" class="nav-item">
                    <i class="fas fa-chart-line"></i>
                    <span data-translate="my_investments">Meus Investimentos</span>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title" data-translate="bonus">Bônus</div>
                <a href="../roleta/" class="nav-item">
                    <i class="fas fa-magic"></i>
                    <span data-translate="fortune_wheel">Roleta da Sorte</span>
                </a>
                <a href="../checklist/" class="nav-item">
                    <i class="fas fa-check-square"></i>
                    <span data-translate="checklist">Checklist</span>
                </a>
                <a href="../tarefas/" class="nav-item">
                    <i class="fas fa-tasks"></i>
                    <span data-translate="tasks">Tarefas</span>
                </a>
                <a href="../codigo-bonus/" class="nav-item">
                    <i class="fas fa-gift"></i>
                    <span data-translate="bonus_code">Código Bônus</span>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title" data-translate="statistics">Estatísticas</div>
                <a href="#" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span data-translate="dashboard">Dashboard</span>
                </a>
                <a href="../relatorios/" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span data-translate="reports">Relatórios</span>
                </a>
            </div>
        </div>

        <div class="sidebar-footer">
            <div class="theme-toggle">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-moon"></i>
                    <span data-translate="dark_mode">Modo Escuro</span>
                </div>
                <div class="theme-switch" id="theme-switch" onclick="toggleTheme()"></div>
            </div>
            
            <a href="#" class="nav-item" onclick="showToast('Abrindo configurações...', 'info')">
                <i class="fas fa-cog"></i>
                <span data-translate="settings">Configurações</span>
            </a>
            <a href="../sair/logout.php" class="nav-item" id="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span data-translate="logout">Sair</span>
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <div class="profile-section">
                    <div class="profile-avatar">
                        <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjQiIGhlaWdodD0iNjQiIHZpZXdCb3g9IjAgMCA2NCA2NCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMzIiIGN5PSIzMiIgcj0iMzIiIGZpbGw9IiNGM0Y0RjYiLz4KPGNpcmNsZSBjeD0iMzIiIGN5PSIyNCIgcj0iMTAiIGZpbGw9IiM2MzdBOUIiLz4KPHBhdGggZD0iTTEwIDU2QzEwIDQ0IDIwIDM2IDMyIDM2UzU0IDQ0IDU0IDU2SDEwWiIgZmlsbD0iIzYzN0E5QiIvPgo8L3N2Zz4=" alt="Profile" class="profile-img">
                        <div class="profile-status"></div>
                        <div class="profile-id">ID: #<?php echo htmlspecialchars($user['codigo_referencia']); ?></div>
                    </div>
                    <div class="profile-info">
                        <h1><span data-translate="welcome_back">Bem-vindo de volta</span>, <?php echo htmlspecialchars($user['nome'] ?? 'Usuário'); ?>!</h1>
                        <div class="profile-badges">
                            <span class="badge-premium" data-translate="premium">Premium</span>
                            <span class="badge-verified" data-translate="verified">Verificado</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Balance Section -->
        <section class="balance-section">
            <div class="balance-card">
                <div class="balance-label" data-translate="total_balance">Patrimônio Total</div>
                <div class="balance-amount">R$ <?php echo number_format($saldo_total, 2, ',', '.'); ?></div>
            </div>
            
            <div class="actions-card">
                <h3 class="actions-title" data-translate="quick_actions">Ações Rápidas</h3>
                <a href="../deposito/" class="action-btn">
                    <i class="fas fa-plus"></i>
                    <span data-translate="deposit">Depósito</span>
                </a>
                <a href="../saque/" class="action-btn secondary">
                    <i class="fas fa-minus"></i>
                    <span data-translate="withdrawal">Saque</span>
                </a>
            </div>
        </section>

        <!-- Investments Section -->
        <section class="investments-section">
            <div class="section-header">
                <h2 class="section-title" data-translate="featured_investments">Investimentos em Destaque</h2>
                <a href="../investimentos/" class="view-all" data-translate="view_all">Ver todos</a>
            </div>
            
            <div class="investments-grid">
                <?php if (!empty($investimentos)): ?>
                    <?php foreach ($investimentos as $investimento): ?>
                        <a href="../investimentos/" class="investment-card">
                            <div class="investment-header">
                                <div class="investment-icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="investment-return"><?php echo number_format($investimento['rendimento_diario'], 2); ?>% a.d.</div>
                            </div>
                            <div class="investment-name"><?php echo htmlspecialchars($investimento['titulo']); ?></div>
                            <div class="investment-details"><span data-translate="invested">Investido</span>: R$ <?php echo number_format($investimento['valor_investido'], 2, ',', '.'); ?></div>
                            <div class="investment-amount"><span data-translate="yield">Rendimento</span>: R$ <?php echo number_format($investimento['rendimento_acumulado'], 2, ',', '.'); ?></div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <a href="../investimentos/" class="investment-card">
                        <div class="investment-header">
                            <div class="investment-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="investment-return">15.2% a.a.</div>
                        </div>
                        <div class="investment-name" data-translate="fixed_income_premium">Renda Fixa Premium</div>
                        <div class="investment-details"><span data-translate="minimum_investment">Investimento mínimo</span>: R$ 1.000</div>
                        <div class="investment-amount" data-translate="start_investing">Comece a investir hoje!</div>
                    </a>
                <?php endif; ?>
            </div>
        </section>

        <!-- Services Section -->
        <section class="services-section">
            <div class="section-header">
                <h2 class="section-title" data-translate="services">Serviços</h2>
            </div>
            
            <div class="services-grid">
                <a href="../roleta/" class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-magic"></i>
                    </div>
                    <div class="service-title" data-translate="fortune_wheel">Roleta da Sorte</div>
                    <div class="service-description" data-translate="fortune_wheel_desc">Gire e ganhe prêmios incríveis todos os dias</div>
                </a>

                <a href="../equipe/" class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="service-title" data-translate="referral_program">Programa de Indicação</div>
                    <div class="service-description" data-translate="referral_program_desc">Indique amigos e ganhe comissões por vida</div>
                </a>

                <a href="../relatorios/" class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div class="service-title" data-translate="detailed_reports">Relatórios Detalhados</div>
                    <div class="service-description" data-translate="detailed_reports_desc">Acompanhe seu desempenho em tempo real</div>
                </a>

                <a href="../perfil/" class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-user-cog"></i>
                    </div>
                    <div class="service-title" data-translate="settings">Configurações</div>
                    <div class="service-description" data-translate="settings_desc">Personalize sua experiência na plataforma</div>
                </a>
            </div>
        </section>
    </main>

    <script src="../assets/js/reu-dashboard.js"></script>
    <script src="../assets/js/reu-translations.js"></script>
</body>
</html>