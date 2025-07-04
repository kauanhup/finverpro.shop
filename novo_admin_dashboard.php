<?php
session_start();

// Verificação de segurança
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../');
    exit();
}

// Conectar ao banco
require '../bank/db.php';

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    die("Erro de conexão: " . $e->getMessage());
}

$user_id = $_SESSION['user_id']; 

// Verificar se é admin e obter permissões
$sql = "SELECT u.*, ar.nome as role_name, ar.cor as role_color 
        FROM usuarios u 
        LEFT JOIN admin_roles ar ON u.admin_role_id = ar.id 
        WHERE u.id = :user_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin || !in_array($admin['cargo'], ['admin', 'super_admin'])) {
    header('Location: ../');
    exit();
}

// Configurar variáveis de auditoria
$conn->exec("SET @current_admin_id = $user_id");
$conn->exec("SET @current_admin_ip = '" . $_SERVER['REMOTE_ADDR'] . "'");

// Função para verificar permissão
function hasPermission($conn, $user_id, $permission) {
    $sql = "SELECT COUNT(*) FROM admin_role_permissions arp 
            JOIN admin_permissions ap ON arp.permission_id = ap.id 
            JOIN usuarios u ON u.admin_role_id = arp.role_id 
            WHERE u.id = ? AND (ap.nome = ? OR ap.nome = 'admin.full')";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id, $permission]);
    return $stmt->fetchColumn() > 0;
}

// Buscar estatísticas otimizadas
try {
    // Usar as estatísticas em cache
    $stats_sql = "SELECT * FROM vw_admin_stats LIMIT 1";
    $stats = $conn->query($stats_sql)->fetch(PDO::FETCH_ASSOC);
    
    // Buscar widgets do usuário
    $widgets_sql = "SELECT * FROM dashboard_widgets WHERE admin_id = ? AND ativo = 1 ORDER BY posicao_y, posicao_x";
    $widgets_stmt = $conn->prepare($widgets_sql);
    $widgets_stmt->execute([$user_id]);
    $widgets = $widgets_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar notificações não lidas
    $notif_sql = "SELECT * FROM admin_notifications 
                  WHERE (admin_id = ? OR admin_id IS NULL) 
                  AND lida = 0 AND (expires_at IS NULL OR expires_at > NOW()) 
                  ORDER BY importante DESC, created_at DESC LIMIT 5";
    $notif_stmt = $conn->prepare($notif_sql);
    $notif_stmt->execute([$user_id]);
    $notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Dados para gráficos (últimos 7 dias)
    $chart_sql = "SELECT 
                    DATE(created_at) as data,
                    SUM(CASE WHEN tipo = 'deposito' THEN valor ELSE 0 END) as depositos,
                    SUM(CASE WHEN tipo = 'saque' THEN valor ELSE 0 END) as saques
                  FROM transacoes 
                  WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                  AND status = 'concluido'
                  GROUP BY DATE(created_at)
                  ORDER BY data";
    $chart_data = $conn->query($chart_sql)->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Erro ao carregar dados: " . $e->getMessage();
}

// Processar ações AJAX
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'mark_notification_read':
            $notif_id = $_POST['notification_id'];
            $sql = "UPDATE admin_notifications SET lida = 1 WHERE id = ? AND admin_id = ?";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([$notif_id, $user_id]);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'get_widget_data':
            $widget_id = $_POST['widget_id'];
            $sql = "SELECT configuracao FROM dashboard_widgets WHERE id = ? AND admin_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$widget_id, $user_id]);
            $widget = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($widget) {
                $config = json_decode($widget['configuracao'], true);
                if (isset($config['query'])) {
                    try {
                        $data = $conn->query($config['query'])->fetchAll(PDO::FETCH_ASSOC);
                        echo json_encode(['success' => true, 'data' => $data]);
                    } catch (Exception $e) {
                        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                    }
                }
            }
            exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FinverPro - Dashboard Admin</title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/date-fns@2.29.3/index.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@2.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    
    <!-- CSS Moderno -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-50: #eff6ff;
            --primary-500: #3b82f6;
            --primary-600: #2563eb;
            --primary-900: #1e3a8a;
            
            --success-50: #f0fdf4;
            --success-500: #22c55e;
            --success-600: #16a34a;
            
            --warning-50: #fffbeb;
            --warning-500: #f59e0b;
            --warning-600: #d97706;
            
            --error-50: #fef2f2;
            --error-500: #ef4444;
            --error-600: #dc2626;
            
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            
            --sidebar-width: 280px;
            --header-height: 70px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
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
            background: linear-gradient(180deg, var(--gray-900) 0%, var(--gray-800) 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-700);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-500), var(--primary-600));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: bold;
        }

        .logo-text {
            font-size: 20px;
            font-weight: 700;
            color: white;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-section {
            margin-bottom: 2rem;
        }

        .nav-section-title {
            padding: 0 1.5rem 0.5rem;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray-400);
        }

        .nav-item {
            display: block;
            padding: 0.75rem 1.5rem;
            color: var(--gray-300);
            text-decoration: none;
            transition: all 0.2s ease;
            position: relative;
            border-left: 3px solid transparent;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.05);
            color: white;
            border-left-color: var(--primary-500);
        }

        .nav-item.active {
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary-400);
            border-left-color: var(--primary-500);
        }

        .nav-item i {
            width: 20px;
            margin-right: 12px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .header {
            height: var(--header-height);
            background: white;
            border-bottom: 1px solid var(--gray-200);
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: between;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--gray-900);
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-left: auto;
        }

        .notification-btn {
            position: relative;
            background: none;
            border: none;
            padding: 0.5rem;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .notification-btn:hover {
            background: var(--gray-100);
        }

        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background: var(--error-500);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .admin-profile {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem;
            border-radius: 12px;
            transition: background 0.2s;
            cursor: pointer;
        }

        .admin-profile:hover {
            background: var(--gray-100);
        }

        .admin-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-500), var(--primary-600));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .admin-info h4 {
            font-size: 14px;
            font-weight: 600;
            color: var(--gray-900);
        }

        .admin-role {
            font-size: 12px;
            color: var(--gray-500);
        }

        /* Content Area */
        .content {
            padding: 2rem;
            flex: 1;
            max-width: 100%;
            overflow-x: hidden;
        }

        /* Grid System */
        .grid {
            display: grid;
            gap: 1.5rem;
        }

        .grid-cols-1 { grid-template-columns: repeat(1, 1fr); }
        .grid-cols-2 { grid-template-columns: repeat(2, 1fr); }
        .grid-cols-3 { grid-template-columns: repeat(3, 1fr); }
        .grid-cols-4 { grid-template-columns: repeat(4, 1fr); }

        /* Cards */
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .card-header {
            padding: 1.5rem 1.5rem 0;
            display: flex;
            align-items: center;
            justify-content: between;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--gray-900);
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Stat Cards */
        .stat-card {
            background: linear-gradient(135deg, var(--primary-500), var(--primary-600));
            color: white;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(30px, -30px);
        }

        .stat-card.success {
            background: linear-gradient(135deg, var(--success-500), var(--success-600));
        }

        .stat-card.warning {
            background: linear-gradient(135deg, var(--warning-500), var(--warning-600));
        }

        .stat-card.error {
            background: linear-gradient(135deg, var(--error-500), var(--error-600));
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-bottom: 1rem;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.9;
            font-weight: 500;
        }

        /* Chart Container */
        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 1rem;
        }

        /* Notifications */
        .notifications-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2);
            width: 350px;
            max-height: 400px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .notification-item {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
            cursor: pointer;
            transition: background 0.2s;
        }

        .notification-item:hover {
            background: var(--gray-50);
        }

        .notification-item.unread {
            background: var(--primary-50);
            border-left: 3px solid var(--primary-500);
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .grid-cols-4 {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .grid-cols-2 {
                grid-template-columns: 1fr;
            }
        }

        /* Loading States */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Animations */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">FP</div>
                <span class="logo-text">FinverPro</span>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Principal</div>
                    <a href="./" class="nav-item active">
                        <i class="fas fa-chart-line"></i>
                        Dashboard
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Usuários</div>
                    <?php if (hasPermission($conn, $user_id, 'users.view')): ?>
                    <a href="../usuarios/" class="nav-item">
                        <i class="fas fa-users"></i>
                        Gerenciar Usuários
                    </a>
                    <?php endif; ?>
                    
                    <a href="../afiliados/" class="nav-item">
                        <i class="fas fa-network-wired"></i>
                        Afiliados
                    </a>
                </div>
                
                <?php if (hasPermission($conn, $user_id, 'finance.view')): ?>
                <div class="nav-section">
                    <div class="nav-section-title">Financeiro</div>
                    <a href="../entradas-geral/" class="nav-item">
                        <i class="fas fa-arrow-up"></i>
                        Entradas
                    </a>
                    <a href="../saidas-usuarios/" class="nav-item">
                        <i class="fas fa-arrow-down"></i>
                        Saques
                    </a>
                    <a href="../comissoes/" class="nav-item">
                        <i class="fas fa-percentage"></i>
                        Comissões
                    </a>
                </div>
                <?php endif; ?>
                
                <div class="nav-section">
                    <div class="nav-section-title">Plataforma</div>
                    <a href="../investimentos/" class="nav-item">
                        <i class="fas fa-chart-bar"></i>
                        Investimentos
                    </a>
                    <a href="../produtos/" class="nav-item">
                        <i class="fas fa-robot"></i>
                        Produtos
                    </a>
                    <a href="../bonus/" class="nav-item">
                        <i class="fas fa-gift"></i>
                        Bônus
                    </a>
                </div>
                
                <?php if (hasPermission($conn, $user_id, 'config.view')): ?>
                <div class="nav-section">
                    <div class="nav-section-title">Configurações</div>
                    <a href="../configuracoes/" class="nav-item">
                        <i class="fas fa-cog"></i>
                        Sistema
                    </a>
                    <a href="../gateways/" class="nav-item">
                        <i class="fas fa-credit-card"></i>
                        Pagamentos
                    </a>
                    <a href="../design/" class="nav-item">
                        <i class="fas fa-palette"></i>
                        Design
                    </a>
                </div>
                <?php endif; ?>
                
                <?php if (hasPermission($conn, $user_id, 'admin.logs')): ?>
                <div class="nav-section">
                    <div class="nav-section-title">Admin</div>
                    <a href="../logs/" class="nav-item">
                        <i class="fas fa-file-alt"></i>
                        Logs
                    </a>
                    <a href="../backups/" class="nav-item">
                        <i class="fas fa-database"></i>
                        Backups
                    </a>
                    <a href="../tasks/" class="nav-item">
                        <i class="fas fa-tasks"></i>
                        Tarefas
                    </a>
                </div>
                <?php endif; ?>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <h1 class="header-title">Dashboard</h1>
                </div>
                
                <div class="header-right">
                    <!-- Notifications -->
                    <div style="position: relative;">
                        <button class="notification-btn" onclick="toggleNotifications()">
                            <i class="fas fa-bell"></i>
                            <?php if (count($notifications) > 0): ?>
                            <span class="notification-badge"><?= count($notifications) ?></span>
                            <?php endif; ?>
                        </button>
                        
                        <div class="notifications-dropdown" id="notificationsDropdown">
                            <div style="padding: 1rem; border-bottom: 1px solid var(--gray-200);">
                                <h4>Notificações</h4>
                            </div>
                            <?php foreach ($notifications as $notif): ?>
                            <div class="notification-item <?= $notif['lida'] ? '' : 'unread' ?>" 
                                 onclick="markAsRead(<?= $notif['id'] ?>)">
                                <h5><?= htmlspecialchars($notif['titulo']) ?></h5>
                                <p style="font-size: 14px; color: var(--gray-600); margin: 0.25rem 0;">
                                    <?= htmlspecialchars($notif['mensagem']) ?>
                                </p>
                                <small style="color: var(--gray-500);">
                                    <?= date('d/m/Y H:i', strtotime($notif['created_at'])) ?>
                                </small>
                            </div>
                            <?php endforeach; ?>
                            
                            <?php if (count($notifications) == 0): ?>
                            <div style="padding: 2rem; text-align: center; color: var(--gray-500);">
                                <i class="fas fa-check-circle" style="font-size: 24px; margin-bottom: 0.5rem;"></i>
                                <p>Nenhuma notificação</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Admin Profile -->
                    <div class="admin-profile">
                        <div class="admin-avatar">
                            <?= strtoupper(substr($admin['nome'] ?: 'A', 0, 1)) ?>
                        </div>
                        <div class="admin-info">
                            <h4><?= htmlspecialchars($admin['nome'] ?: 'Administrador') ?></h4>
                            <div class="admin-role" style="color: <?= $admin['role_color'] ?: '#6b7280' ?>">
                                <?= htmlspecialchars($admin['role_name'] ?: 'Admin') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Content -->
            <div class="content">
                <!-- Stats Cards -->
                <div class="grid grid-cols-4 fade-in">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-value"><?= number_format($stats['total_usuarios']) ?></div>
                            <div class="stat-label">Total de Usuários</div>
                            <div style="margin-top: 0.5rem; font-size: 12px; opacity: 0.8;">
                                +<?= $stats['usuarios_hoje'] ?> hoje
                            </div>
                        </div>
                    </div>
                    
                    <div class="card stat-card success">
                        <div class="card-body">
                            <div class="stat-icon">
                                <i class="fas fa-wallet"></i>
                            </div>
                            <div class="stat-value">R$ <?= number_format($stats['saldo_total_plataforma'], 2, ',', '.') ?></div>
                            <div class="stat-label">Saldo Total</div>
                            <div style="margin-top: 0.5rem; font-size: 12px; opacity: 0.8;">
                                R$ <?= number_format($stats['depositos_hoje'], 2, ',', '.') ?> hoje
                            </div>
                        </div>
                    </div>
                    
                    <div class="card stat-card warning">
                        <div class="card-body">
                            <div class="stat-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="stat-value"><?= number_format($stats['investimentos_ativos']) ?></div>
                            <div class="stat-label">Investimentos Ativos</div>
                            <div style="margin-top: 0.5rem; font-size: 12px; opacity: 0.8;">
                                Gerando rendimentos
                            </div>
                        </div>
                    </div>
                    
                    <div class="card stat-card error">
                        <div class="card-body">
                            <div class="stat-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="stat-value"><?= $stats['saques_pendentes'] + $stats['depositos_pendentes'] ?></div>
                            <div class="stat-label">Pendências</div>
                            <div style="margin-top: 0.5rem; font-size: 12px; opacity: 0.8;">
                                Necessita atenção
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts and Tables -->
                <div class="grid grid-cols-2" style="margin-top: 2rem;">
                    <!-- Financial Chart -->
                    <div class="card fade-in">
                        <div class="card-header">
                            <h3 class="card-title">Movimentação Financeira</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="financialChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Activity -->
                    <div class="card fade-in">
                        <div class="card-header">
                            <h3 class="card-title">Atividade Recente</h3>
                        </div>
                        <div class="card-body">
                            <div id="recentActivity">
                                <div class="loading"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Configuração dos gráficos
        const chartData = <?= json_encode($chart_data) ?>;
        
        // Gráfico financeiro
        const ctx = document.getElementById('financialChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.map(d => d.data),
                datasets: [
                    {
                        label: 'Depósitos',
                        data: chartData.map(d => d.depositos),
                        borderColor: 'rgb(34, 197, 94)',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Saques',
                        data: chartData.map(d => d.saques),
                        borderColor: 'rgb(239, 68, 68)',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'R$ ' + value.toLocaleString('pt-BR');
                            }
                        }
                    }
                }
            }
        });
        
        // Toggle notifications
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationsDropdown');
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        }
        
        // Mark notification as read
        function markAsRead(notificationId) {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=mark_notification_read&notification_id=${notificationId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
        
        // Close notifications when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('notificationsDropdown');
            const button = document.querySelector('.notification-btn');
            
            if (!dropdown.contains(event.target) && !button.contains(event.target)) {
                dropdown.style.display = 'none';
            }
        });
        
        // Load recent activity
        function loadRecentActivity() {
            // Simular carregamento de atividades recentes
            setTimeout(() => {
                document.getElementById('recentActivity').innerHTML = `
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--gray-50); border-radius: 8px;">
                            <div style="width: 8px; height: 8px; background: var(--success-500); border-radius: 50%;"></div>
                            <div>
                                <div style="font-weight: 600; font-size: 14px;">Novo usuário cadastrado</div>
                                <div style="font-size: 12px; color: var(--gray-500);">há 5 minutos</div>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--gray-50); border-radius: 8px;">
                            <div style="width: 8px; height: 8px; background: var(--primary-500); border-radius: 50%;"></div>
                            <div>
                                <div style="font-weight: 600; font-size: 14px;">Investimento realizado</div>
                                <div style="font-size: 12px; color: var(--gray-500);">há 12 minutos</div>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--gray-50); border-radius: 8px;">
                            <div style="width: 8px; height: 8px; background: var(--warning-500); border-radius: 50%;"></div>
                            <div>
                                <div style="font-weight: 600; font-size: 14px;">Saque solicitado</div>
                                <div style="font-size: 12px; color: var(--gray-500);">há 25 minutos</div>
                            </div>
                        </div>
                    </div>
                `;
            }, 1000);
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadRecentActivity();
            
            // Auto-refresh stats every 5 minutes
            setInterval(() => {
                // location.reload();
            }, 300000);
        });
    </script>
</body>
</html>