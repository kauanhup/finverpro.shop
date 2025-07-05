<?php
/**
 * ========================================
 * FINVER PRO - LAYOUT MASTER ADMINISTRATIVO
 * Template Base para Todas as Páginas Admin
 * ========================================
 */

// Garantir autenticação
if (!function_exists('requireAdmin')) {
    require_once __DIR__ . '/../includes/auth.php';
    requireAdmin();
}

$admin = getAdminData();
$db = Database::getInstance();

// Obter estatísticas para badges (cache por 5 minutos)
$cache_key = 'admin_nav_stats';
$nav_stats = null;

try {
    // Verificar cache simples (arquivo)
    $cache_file = __DIR__ . '/../cache/nav_stats.json';
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 300) {
        $nav_stats = json_decode(file_get_contents($cache_file), true);
    } else {
        // Buscar estatísticas
        $nav_stats = [
            'saques_pendentes' => $db->fetchOne("SELECT COUNT(*) as count FROM saques WHERE status = 'pendente'")['count'] ?? 0,
            'usuarios_hoje' => $db->fetchOne("SELECT COUNT(*) as count FROM usuarios WHERE DATE(created_at) = CURDATE()")['count'] ?? 0,
            'comissoes_pendentes' => $db->fetchOne("SELECT COUNT(*) as count FROM comissoes WHERE status = 'pendente'")['count'] ?? 0
        ];
        
        // Salvar cache
        @mkdir(dirname($cache_file), 0755, true);
        file_put_contents($cache_file, json_encode($nav_stats));
    }
} catch (Exception $e) {
    error_log("Erro ao obter estatísticas de navegação: " . $e->getMessage());
    $nav_stats = ['saques_pendentes' => 0, 'usuarios_hoje' => 0, 'comissoes_pendentes' => 0];
}

// Configurar meta dados da página
$page_title = $page_title ?? 'Dashboard';
$page_description = $page_description ?? 'Painel Administrativo Finver Pro';
$page_icon = $page_icon ?? 'fas fa-tachometer-alt';

// Menu de navegação
$nav_items = [
    [
        'section' => 'Principal',
        'items' => [
            [
                'label' => 'Dashboard',
                'url' => '../dashboard/',
                'icon' => 'fas fa-tachometer-alt',
                'active' => strpos($_SERVER['REQUEST_URI'], '/dashboard/') !== false
            ],
            [
                'label' => 'Usuários',
                'url' => '../usuarios/',
                'icon' => 'fas fa-users',
                'badge' => $nav_stats['usuarios_hoje'] > 0 ? $nav_stats['usuarios_hoje'] : null,
                'active' => strpos($_SERVER['REQUEST_URI'], '/usuarios/') !== false
            ],
            [
                'label' => 'Afiliados',
                'url' => '../afiliados/',
                'icon' => 'fas fa-user-friends',
                'active' => strpos($_SERVER['REQUEST_URI'], '/afiliados/') !== false
            ]
        ]
    ],
    [
        'section' => 'Produtos & Investimentos',
        'items' => [
            [
                'label' => 'Produtos',
                'url' => '../produtos/',
                'icon' => 'fas fa-robot',
                'active' => strpos($_SERVER['REQUEST_URI'], '/produtos/') !== false
            ],
            [
                'label' => 'Checklist',
                'url' => '../checklist/',
                'icon' => 'fas fa-tasks',
                'active' => strpos($_SERVER['REQUEST_URI'], '/checklist/') !== false
            ],
            [
                'label' => 'Roleta',
                'url' => '../roleta/',
                'icon' => 'fas fa-dice',
                'active' => strpos($_SERVER['REQUEST_URI'], '/roleta/') !== false
            ]
        ]
    ],
    [
        'section' => 'Financeiro',
        'items' => [
            [
                'label' => 'Saques',
                'url' => '../saques/',
                'icon' => 'fas fa-money-bill-wave',
                'badge' => $nav_stats['saques_pendentes'] > 0 ? $nav_stats['saques_pendentes'] : null,
                'badge_type' => 'danger',
                'active' => strpos($_SERVER['REQUEST_URI'], '/saques/') !== false
            ],
            [
                'label' => 'Pagamentos',
                'url' => '../pagamentos/',
                'icon' => 'fas fa-credit-card',
                'active' => strpos($_SERVER['REQUEST_URI'], '/pagamentos/') !== false
            ],
            [
                'label' => 'Gateways',
                'url' => '../gateways/',
                'icon' => 'fas fa-plug',
                'active' => strpos($_SERVER['REQUEST_URI'], '/gateways/') !== false
            ]
        ]
    ],
    [
        'section' => 'Promoções & Bônus',
        'items' => [
            [
                'label' => 'Códigos',
                'url' => '../codigos/',
                'icon' => 'fas fa-gift',
                'active' => strpos($_SERVER['REQUEST_URI'], '/codigos/') !== false
            ]
        ]
    ],
    [
        'section' => 'Sistema',
        'items' => [
            [
                'label' => 'Configurações',
                'url' => '../configuracoes/',
                'icon' => 'fas fa-cog',
                'active' => strpos($_SERVER['REQUEST_URI'], '/configuracoes/') !== false
            ],
            [
                'label' => 'Relatórios',
                'url' => '../relatorios/',
                'icon' => 'fas fa-chart-line',
                'active' => strpos($_SERVER['REQUEST_URI'], '/relatorios/') !== false
            ]
        ]
    ]
];

// Determinar caminho dos assets baseado na localização atual
$current_path = $_SERVER['REQUEST_URI'];
$admin_root = '/administracao/';
$assets_path = $admin_root . 'assets/';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - Finver Pro Admin</title>
    <meta name="description" content="<?= htmlspecialchars($page_description) ?>">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Favicon -->
    <link rel="icon" href="<?= $assets_path ?>images/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="<?= $assets_path ?>images/favicon.ico" type="image/x-icon">
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    
    <!-- CSS Principal -->
    <link rel="stylesheet" href="<?= $assets_path ?>css/admin.css?v=<?= time() ?>">
    
    <!-- CSS adicional da página -->
    <?php if (isset($additional_css)): ?>
        <?php foreach ($additional_css as $css): ?>
            <link rel="stylesheet" href="<?= $css ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Meta Tags para Performance -->
    <meta name="theme-color" content="#1a365d">
    <meta name="format-detection" content="telephone=no">
    
    <!-- Preload de recursos críticos -->
    <link rel="preload" href="<?= $assets_path ?>js/admin.js" as="script">
    
    <style>
        /* CSS crítico inline para evitar FOUC */
        body { 
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #0f1419 0%, #1a202c 100%);
            margin: 0;
            overflow-x: hidden;
        }
        
        .loading-initial {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, #0f1419 0%, #1a202c 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.3s ease;
        }
        
        .loading-initial.hidden {
            opacity: 0;
            pointer-events: none;
        }
        
        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(255,255,255,0.1);
            border-top: 3px solid #3182ce;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Loading inicial -->
    <div class="loading-initial" id="initialLoading">
        <div class="loading-spinner"></div>
    </div>
    
    <!-- Layout Principal -->
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <!-- Header do Sidebar -->
            <div class="sidebar-header">
                <a href="../dashboard/" class="sidebar-logo">
                    <div class="sidebar-logo-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <span class="sidebar-logo-text">Finver Pro</span>
                </a>
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-chevron-left"></i>
                </button>
            </div>
            
            <!-- Navegação -->
            <nav class="sidebar-nav">
                <?php foreach ($nav_items as $section): ?>
                    <div class="nav-section">
                        <div class="nav-section-title"><?= htmlspecialchars($section['section']) ?></div>
                        <?php foreach ($section['items'] as $item): ?>
                            <div class="nav-item">
                                <a href="<?= htmlspecialchars($item['url']) ?>" 
                                   class="nav-link <?= $item['active'] ? 'active' : '' ?>">
                                    <div class="nav-icon">
                                        <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
                                    </div>
                                    <span class="nav-text"><?= htmlspecialchars($item['label']) ?></span>
                                    <?php if (isset($item['badge']) && $item['badge'] > 0): ?>
                                        <span class="nav-badge <?= $item['badge_type'] ?? '' ?>">
                                            <?= $item['badge'] ?>
                                        </span>
                                    <?php endif; ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </nav>
        </aside>
        
        <!-- Conteúdo Principal -->
        <main class="main-content" id="mainContent">
            <!-- Header da Página -->
            <header class="page-header">
                <div class="page-header-left">
                    <button class="mobile-menu-toggle" id="mobileMenuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="page-title-section">
                        <h1 class="page-title">
                            <i class="<?= htmlspecialchars($page_icon) ?>"></i>
                            <?= htmlspecialchars($page_title) ?>
                        </h1>
                        <?php if (isset($page_subtitle)): ?>
                            <p class="page-subtitle"><?= htmlspecialchars($page_subtitle) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="page-actions">
                    <!-- Notificações -->
                    <div class="notification-center">
                        <button class="btn-notification" onclick="checkNotifications()">
                            <i class="fas fa-bell"></i>
                            <span class="notification-badge" id="notificationBadge" style="display: none;"></span>
                        </button>
                    </div>
                    
                    <!-- Info do Admin -->
                    <div class="admin-info">
                        <div class="admin-avatar">
                            <?= strtoupper(substr($admin['nome'] ?? $admin['email'], 0, 1)) ?>
                        </div>
                        <div class="admin-details">
                            <div class="admin-name"><?= htmlspecialchars($admin['nome'] ?? 'Admin') ?></div>
                            <div class="admin-role"><?= ucfirst(htmlspecialchars($admin['nivel'] ?? 'admin')) ?></div>
                        </div>
                    </div>
                    
                    <!-- Logout -->
                    <a href="../logout.php" class="logout-btn" onclick="return confirm('Tem certeza que deseja sair?')">
                        <i class="fas fa-sign-out-alt"></i>
                        <span class="logout-text">Sair</span>
                    </a>
                </div>
            </header>
            
            <!-- Conteúdo da Página -->
            <div class="page-content">
                <?php
                // Incluir o conteúdo específico da página
                if (isset($page_content)) {
                    echo $page_content;
                } elseif (isset($content_file) && file_exists($content_file)) {
                    include $content_file;
                } else {
                    echo '<div class="alert alert-warning">Conteúdo não encontrado.</div>';
                }
                ?>
            </div>
            
            <!-- Footer -->
            <footer class="page-footer">
                <div class="footer-content">
                    <div class="footer-left">
                        <span>&copy; <?= date('Y') ?> Finver Pro. Todos os direitos reservados.</span>
                    </div>
                    <div class="footer-right">
                        <span>Versão 2.0</span>
                        <span class="separator">•</span>
                        <span id="currentTime"><?= date('d/m/Y H:i') ?></span>
                    </div>
                </div>
            </footer>
        </main>
    </div>
    
    <!-- Scripts -->
    <script>
        // Configurações globais
        window.ADMIN_CONFIG = {
            baseUrl: '<?= $admin_root ?>',
            assetsUrl: '<?= $assets_path ?>',
            currentPage: '<?= basename($_SERVER['PHP_SELF'], '.php') ?>',
            user: {
                id: <?= $admin['id'] ?>,
                name: '<?= htmlspecialchars($admin['nome'] ?? 'Admin') ?>',
                level: '<?= htmlspecialchars($admin['nivel'] ?? 'admin') ?>'
            },
            stats: <?= json_encode($nav_stats) ?>
        };
    </script>
    
    <!-- JavaScript Principal -->
    <script src="<?= $assets_path ?>js/admin.js?v=<?= time() ?>"></script>
    
    <!-- JavaScript adicional da página -->
    <?php if (isset($additional_js)): ?>
        <?php foreach ($additional_js as $js): ?>
            <script src="<?= $js ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- JavaScript inline da página -->
    <?php if (isset($inline_js)): ?>
        <script><?= $inline_js ?></script>
    <?php endif; ?>
    
    <script>
        // Remover loading inicial quando página carregar
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                const loading = document.getElementById('initialLoading');
                if (loading) {
                    loading.classList.add('hidden');
                    setTimeout(() => loading.remove(), 300);
                }
            }, 500);
        });
        
        // Atualizar relógio
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleString('pt-BR', {
                day: '2-digit',
                month: '2-digit', 
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            const timeElement = document.getElementById('currentTime');
            if (timeElement) {
                timeElement.textContent = timeString;
            }
        }
        
        // Atualizar a cada minuto
        setInterval(updateClock, 60000);
        
        // Função para verificar notificações
        async function checkNotifications() {
            try {
                const response = await fetch('<?= $admin_root ?>api/notifications.php');
                const data = await response.json();
                
                if (data.success && data.notifications.length > 0) {
                    finverAdmin.showToast(
                        'Notificações',
                        `Você tem ${data.notifications.length} notificação(ões)`,
                        'info'
                    );
                }
            } catch (error) {
                console.warn('Erro ao verificar notificações:', error);
            }
        }
    </script>
</body>
</html>