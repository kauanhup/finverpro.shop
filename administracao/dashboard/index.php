<?php
// Iniciar buffer de saída para evitar problemas com header
ob_start();

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Variável para armazenar logs de debug
$debug_log = [];

// Função para adicionar log de debug
function debug_log($message) {
    global $debug_log;
    $debug_log[] = $message;
}

debug_log("Iniciando verificações...");

// Incluir conexão com banco de dados
try {
    require_once '../../bank/db.php';
    debug_log("Conexão com banco incluída com sucesso");
} catch (Exception $e) {
    debug_log("ERRO: Não foi possível conectar ao banco de dados: " . $e->getMessage());
    // Limpar buffer e mostrar erro
    ob_end_clean();
    die("ERRO: Não foi possível conectar ao banco de dados: " . $e->getMessage());
}

// DEBUG: Verificar se as variáveis de sessão existem
debug_log("admin_logado = " . (isset($_SESSION['admin_logado']) ? ($_SESSION['admin_logado'] ? 'true' : 'false') : 'não definido'));
debug_log("admin_id = " . (isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 'não definido'));

// Verificar se o usuário está logado e é admin
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    debug_log("Usuário não está logado ou não é admin");
    ob_end_clean();
    header('Location: ../index.php');
    exit;
}

// Verificar se admin_id existe na sessão
if (!isset($_SESSION['admin_id'])) {
    debug_log("admin_id não está definido na sessão");
    ob_end_clean();
    session_destroy();
    header('Location: ../index.php?erro=sessao_invalida');
    exit;
}

// Verificar se é realmente um admin no banco
try {
    $stmt = $pdo->prepare("SELECT id, nome, email, tipo_usuario FROM usuarios WHERE id = ? AND tipo_usuario = 'admin' AND status = 'ativo'");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    debug_log("Query executada, admin encontrado: " . ($admin ? 'Sim' : 'Não'));
    if ($admin) {
        debug_log("Admin: " . $admin['nome'] . " (" . $admin['email'] . ")");
    }
    
    if (!$admin) {
        debug_log("Admin não encontrado no banco ou não está ativo");
        ob_end_clean();
        session_destroy();
        header('Location: ../index.php?erro=acesso_negado');
        exit;
    }
} catch (PDOException $e) {
    debug_log("Erro ao verificar admin: " . $e->getMessage());
    error_log("Erro ao verificar admin: " . $e->getMessage());
    ob_end_clean();
    header('Location: ../index.php?erro=sistema');
    exit;
}

// Se chegou até aqui, o usuário está autenticado
debug_log("Usuário autenticado com sucesso: " . $admin['nome']);

// Função para verificar se tabela existe
function tableExists($pdo, $tableName) {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$tableName]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Erro ao verificar tabela {$tableName}: " . $e->getMessage());
        return false;
    }
}

// Função para obter estatísticas do dashboard
function getDashboardStats($pdo, $startDate = null, $endDate = null) {
    global $debug_log;
    try {
        $stats = [];
        
        // Verificar se tabelas existem
        $operacoesExists = tableExists($pdo, 'operacoes_financeiras');
        $usuariosExists = tableExists($pdo, 'usuarios');
        $comissoesExists = tableExists($pdo, 'comissoes');
        $investimentosExists = tableExists($pdo, 'investimentos');
        $bonusExists = tableExists($pdo, 'bonus_utilizados');
        
        debug_log("Tabelas - operacoes: " . ($operacoesExists ? 'OK' : 'NÃO EXISTE'));
        debug_log("Tabelas - usuarios: " . ($usuariosExists ? 'OK' : 'NÃO EXISTE'));
        debug_log("Tabelas - comissoes: " . ($comissoesExists ? 'OK' : 'NÃO EXISTE'));
        debug_log("Tabelas - investimentos: " . ($investimentosExists ? 'OK' : 'NÃO EXISTE'));
        debug_log("Tabelas - bonus: " . ($bonusExists ? 'OK' : 'NÃO EXISTE'));
        
        // Configurar filtros de data
        $dateCondition = "";
        $params = [];
        
        if ($startDate && $endDate) {
            $dateCondition = " AND DATE(created_at) BETWEEN ? AND ?";
            $params = [$startDate, $endDate];
        } elseif ($startDate) {
            $dateCondition = " AND DATE(created_at) >= ?";
            $params = [$startDate];
        } elseif ($endDate) {
            $dateCondition = " AND DATE(created_at) <= ?";
            $params = [$endDate];
        }
        
        // Depósitos hoje
        if ($operacoesExists) {
            try {
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(valor_liquido), 0) as total FROM operacoes_financeiras WHERE tipo = 'deposito' AND status = 'aprovado' AND DATE(created_at) = CURDATE()");
                $stmt->execute();
                $stats['depositos_hoje'] = $stmt->fetchColumn();
                
                // Total depósitos
                $sql = "SELECT COALESCE(SUM(valor_liquido), 0) as total FROM operacoes_financeiras WHERE tipo = 'deposito' AND status = 'aprovado'" . ($dateCondition ? " AND DATE(created_at) BETWEEN ? AND ?" : "");
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $stats['total_depositos'] = $stmt->fetchColumn();
                
                // Total sacado
                $sql = "SELECT COALESCE(SUM(valor_liquido), 0) as total FROM operacoes_financeiras WHERE tipo = 'saque' AND status = 'aprovado'" . ($dateCondition ? " AND DATE(created_at) BETWEEN ? AND ?" : "");
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $stats['total_sacado'] = $stmt->fetchColumn();
            } catch (PDOException $e) {
                debug_log("Erro ao consultar operações financeiras: " . $e->getMessage());
                $stats['depositos_hoje'] = 0;
                $stats['total_depositos'] = 0;
                $stats['total_sacado'] = 0;
            }
        } else {
            $stats['depositos_hoje'] = 0;
            $stats['total_depositos'] = 0;
            $stats['total_sacado'] = 0;
        }
        
        // Cadastros
        if ($usuariosExists) {
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM usuarios WHERE DATE(created_at) = CURDATE()");
                $stmt->execute();
                $stats['cadastros_hoje'] = $stmt->fetchColumn();
                
                $sql = "SELECT COUNT(*) as total FROM usuarios" . ($dateCondition ? " WHERE DATE(created_at) BETWEEN ? AND ?" : "");
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $stats['total_cadastros'] = $stmt->fetchColumn();
            } catch (PDOException $e) {
                debug_log("Erro ao consultar usuários: " . $e->getMessage());
                $stats['cadastros_hoje'] = 0;
                $stats['total_cadastros'] = 0;
            }
        } else {
            $stats['cadastros_hoje'] = 0;
            $stats['total_cadastros'] = 0;
        }
        
        // Comissões hoje
        if ($comissoesExists) {
            try {
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(valor_comissao), 0) as total FROM comissoes WHERE status = 'pago' AND DATE(created_at) = CURDATE()");
                $stmt->execute();
                $stats['comissoes_hoje'] = $stmt->fetchColumn();
            } catch (PDOException $e) {
                debug_log("Erro ao consultar comissões: " . $e->getMessage());
                $stats['comissoes_hoje'] = 0;
            }
        } else {
            $stats['comissoes_hoje'] = 0;
        }
        
        // Salários hoje (simulado)
        $stats['salarios_hoje'] = 8200.00;
        
        // Saldo da plataforma
        $stats['saldo_plataforma'] = ($stats['total_depositos'] ?? 0) - ($stats['total_sacado'] ?? 0);
        
        // Investidores ativos
        if ($investimentosExists) {
            try {
                $stmt = $pdo->prepare("SELECT COUNT(DISTINCT usuario_id) as total FROM investimentos WHERE status = 'ativo'");
                $stmt->execute();
                $stats['investidores_ativos'] = $stmt->fetchColumn();
            } catch (PDOException $e) {
                debug_log("Erro ao consultar investimentos: " . $e->getMessage());
                $stats['investidores_ativos'] = 0;
            }
        } else {
            $stats['investidores_ativos'] = 0;
        }
        
        // Códigos usados
        if ($bonusExists) {
            try {
                $sql = "SELECT COUNT(*) as total FROM bonus_utilizados" . ($dateCondition ? " WHERE DATE(created_at) BETWEEN ? AND ?" : "");
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $stats['codigos_usados'] = $stmt->fetchColumn();
            } catch (PDOException $e) {
                debug_log("Erro ao consultar bonus: " . $e->getMessage());
                $stats['codigos_usados'] = 0;
            }
        } else {
            $stats['codigos_usados'] = 0;
        }
        
        return $stats;
        
    } catch (PDOException $e) {
        debug_log("Erro geral ao obter estatísticas: " . $e->getMessage());
        error_log("Erro ao obter estatísticas: " . $e->getMessage());
        return [
            'depositos_hoje' => 0,
            'total_depositos' => 0,
            'cadastros_hoje' => 0,
            'total_cadastros' => 0,
            'total_sacado' => 0,
            'comissoes_hoje' => 0,
            'salarios_hoje' => 0,
            'saldo_plataforma' => 0,
            'investidores_ativos' => 0,
            'codigos_usados' => 0
        ];
    }
}

// Função para obter transações recentes
function getRecentTransactions($pdo, $limit = 10) {
    global $debug_log;
    try {
        // Verificar se tabela existe
        if (!tableExists($pdo, 'operacoes_financeiras')) {
            debug_log("Tabela operacoes_financeiras não existe");
            return [];
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                tipo,
                valor_liquido as valor,
                metodo,
                nome_titular,
                created_at,
                CASE 
                    WHEN tipo = 'deposito' THEN CONCAT('Depósito ', UPPER(IFNULL(metodo, 'PIX')))
                    WHEN tipo = 'saque' THEN CONCAT('Saque ', UPPER(IFNULL(metodo, 'PIX')))
                    ELSE 'Transação'
                END as descricao
            FROM operacoes_financeiras 
            WHERE status = 'aprovado'
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        debug_log("Transações encontradas: " . count($transactions));
        return $transactions;
        
    } catch (PDOException $e) {
        debug_log("Erro ao obter transações: " . $e->getMessage());
        error_log("Erro ao obter transações: " . $e->getMessage());
        return [];
    }
}

// Obter dados para o dashboard
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;

debug_log("Obtendo estatísticas...");
$stats = getDashboardStats($pdo, $startDate, $endDate);

debug_log("Obtendo transações...");
$recentTransactions = getRecentTransactions($pdo, 4);

// Função para formatar moeda
function formatCurrency($value) {
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

// Função para formatar número
function formatNumber($value) {
    return number_format((int)$value, 0, ',', '.');
}

// Função para formatar data
function formatDate($date) {
    if (!$date) return 'N/A';
    return date('d/m/Y - H:i', strtotime($date));
}

// Inicializar valores padrão se não existirem
$stats = array_merge([
    'depositos_hoje' => 0,
    'total_depositos' => 0,
    'cadastros_hoje' => 0,
    'total_cadastros' => 0,
    'total_sacado' => 0,
    'comissoes_hoje' => 0,
    'salarios_hoje' => 0,
    'saldo_plataforma' => 0,
    'investidores_ativos' => 0,
    'codigos_usados' => 0
], $stats);

debug_log("Dashboard carregado com sucesso!");

// Agora que todas as verificações passaram, podemos limpar o buffer e mostrar o conteúdo
ob_end_clean();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finver Pro - Dashboard Administrativo</title>
    <meta name="author" content="Finver Pro" />

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Core CSS -->
    <link rel="stylesheet" href="../../assets/css/reu-admin-dashboard.css">
</head>
<body>
    <!-- Loader -->
    <div class="loader-container" id="loader">
        <div class="loader"></div>
        <div class="loading-text">Carregando Dashboard...</div>
    </div>

    <!-- Overlay -->
    <div class="overlay" id="overlay"></div>

    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="header-left">
                <button class="menu-button" id="menuButton">
                    <span class="icon">≡</span>
                </button>
                <a href="#" class="logo">
                    <div class="logo-icon">FP</div>
                    Finver Pro
                </a>
            </div>
            <div class="header-right">
                <div class="user-info">
                    <?php echo htmlspecialchars($admin['nome']); ?>
                </div>
                <div class="user-avatar">
                    <?php
                        $initials = '';
                        if (!empty($admin['nome'])) {
                            $parts = explode(' ', $admin['nome']);
                            foreach ($parts as $p) {
                                $initials .= mb_substr($p, 0, 1);
                                if (mb_strlen($initials) >= 2) break;
                            }
                        } else {
                            $initials = 'AD';
                        }
                        echo strtoupper($initials);
                    ?>
                </div>
                <a href="../sair/" class="logout-btn"><span class="icon">↦</span> Sair</a>
            </div>
        </div>
    </header>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-content">
            <!-- Principal -->
            <div class="nav-section">
                <div class="nav-title">Principal</div>
                <a href="#" class="nav-item active"><span class="nav-icon icon">⊞</span> Dashboard</a>
                <a href="#" class="nav-item"><span class="nav-icon icon">◉</span> Usuários</a>
                <a href="#" class="nav-item"><span class="nav-icon icon">✎</span> Cadastro</a>
            </div>
            <!-- Financeiro -->
            <div class="nav-section">
                <div class="nav-title">Financeiro</div>
                <a href="#" class="nav-item"><span class="nav-icon icon">↗</span> Entradas Geral</a>
                <a href="#" class="nav-item"><span class="nav-icon icon">↙</span> Saídas</a>
                <a href="#" class="nav-item"><span class="nav-icon icon">$</span> Comissões</a>
                <a href="#" class="nav-item"><span class="nav-icon icon">₹</span> Salário</a>
            </div>
            <!-- Plataforma -->
            <div class="nav-section">
                <div class="nav-title">Plataforma</div>
                <a href="#" class="nav-item"><span class="nav-icon icon">📈</span> Investidores</a>
                <a href="#" class="nav-item"><span class="nav-icon icon">⧉</span> Produtos</a>
                <a href="#" class="nav-item"><span class="nav-icon icon">#</span> Códigos</a>
                <a href="#" class="nav-item"><span class="nav-icon icon">◯</span> Roleta</a>
                <a href="#" class="nav-item"><span class="nav-icon icon">✓</span> Tarefas</a>
                <a href="#" class="nav-item"><span class="nav-icon icon">☑</span> Checklist</a>
            </div>
            <!-- Configurações -->
            <div class="nav-section">
                <div class="nav-title">Configurações</div>
                <a href="#" class="nav-item"><span class="nav-icon icon">⚙</span> Config de Pagamento</a>
            </div>
            <!-- Personalização -->
            <div class="nav-section">
                <div class="nav-title">Personalização</div>
                <a href="#" class="nav-item"><span class="nav-icon icon">⬟</span> Personalização de Cores</a>
                <a href="#" class="nav-item"><span class="nav-icon icon">Tt</span> Personalização de Textos</a>
                <a href="#" class="nav-item"><span class="nav-icon icon">▦</span> Personalizar Imagens</a>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <div class="container">
            <!-- Welcome -->
            <section class="welcome-section">
                <div class="welcome-content">
                    <div class="welcome-text">
                        <h1>Bem-vindo, <?php echo htmlspecialchars($admin['nome']); ?>!</h1>
                        <p>Gerencie sua plataforma Finver Pro com facilidade e eficiência.</p>
                        <a href="#" class="welcome-button"><span class="icon">→</span> Acessar Plataforma</a>
                    </div>
                    <div class="welcome-icon">◉</div>
                </div>
            </section>

            <!-- Date Selector -->
            <section class="date-selector">
                <h2 class="date-selector-title"><span class="icon">◷</span> Filtrar por Período</h2>
                <div class="date-inputs">
                    <input type="date" class="date-input" id="startDate" value="<?php echo htmlspecialchars($startDate ?? ''); ?>">
                    <span style="color: var(--text-secondary); font-weight: 600;">até</span>
                    <input type="date" class="date-input" id="endDate" value="<?php echo htmlspecialchars($endDate ?? ''); ?>">
                    <button class="filter-button" onclick="filterByDate()"><span class="icon">⊙</span> Filtrar Dados</button>
                </div>
            </section>

            <!-- Stats Grid -->
            <section class="stats-grid">
                <!-- Depósitos Hoje -->
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-info">
                            <h3 id="depositos-hoje"><?php echo formatCurrency($stats['depositos_hoje']); ?></h3>
                            <p>Depósitos Hoje</p>
                        </div>
                        <div class="stat-icon success"><span class="icon">↗</span></div>
                    </div>
                </div>
                <!-- Total Depósitos -->
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-info">
                            <h3 id="total-depositos"><?php echo formatCurrency($stats['total_depositos']); ?></h3>
                            <p>Total Depósitos</p>
                        </div>
                        <div class="stat-icon success"><span class="icon">$</span></div>
                    </div>
                </div>
                <!-- Cadastros Hoje -->
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-info">
                            <h3 id="cadastros-hoje"><?php echo formatNumber($stats['cadastros_hoje']); ?></h3>
                            <p>Cadastros Hoje</p>
                        </div>
                        <div class="stat-icon info"><span class="icon">◉</span></div>
                    </div>
                </div>
                <!-- Total Cadastros -->
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-info">
                            <h3 id="total-cadastros"><?php echo formatNumber($stats['total_cadastros']); ?></h3>
                            <p>Total Cadastros</p>
                        </div>
                        <div class="stat-icon info"><span class="icon">⊞</span></div>
                    </div>
                </div>
                <!-- Total Sacado -->
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-info">
                            <h3 id="total-sacado"><?php echo formatCurrency($stats['total_sacado']); ?></h3>
                            <p>Total Sacado</p>
                        </div>
                        <div class="stat-icon danger"><span class="icon">↙</span></div>
                    </div>
                </div>
                <!-- Comissões Hoje -->
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-info">
                            <h3 id="comissoes-hoje"><?php echo formatCurrency($stats['comissoes_hoje']); ?></h3>
                            <p>Comissões Hoje</p>
                        </div>
                        <div class="stat-icon warning"><span class="icon">%</span></div>
                    </div>
                </div>
                <!-- Salários Hoje -->
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-info">
                            <h3 id="salarios-hoje"><?php echo formatCurrency($stats['salarios_hoje']); ?></h3>
                            <p>Salários Hoje</p>
                        </div>
                        <div class="stat-icon purple"><span class="icon">₹</span></div>
                    </div>
                </div>
                <!-- Saldo Plataforma -->
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-info">
                            <h3 id="saldo-plataforma"><?php echo formatCurrency($stats['saldo_plataforma']); ?></h3>
                            <p>Saldo Plataforma</p>
                        </div>
                        <div class="stat-icon success"><span class="icon">⊞</span></div>
                    </div>
                </div>
                <!-- Investidores Ativos -->
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-info">
                            <h3 id="investidores-ativos"><?php echo formatNumber($stats['investidores_ativos']); ?></h3>
                            <p>Investidores Ativos</p>
                        </div>
                        <div class="stat-icon pink"><span class="icon">△</span></div>
                    </div>
                </div>
                <!-- Códigos Usados -->
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-info">
                            <h3 id="codigos-usados"><?php echo formatNumber($stats['codigos_usados']); ?></h3>
                            <p>Códigos Usados</p>
                        </div>
                        <div class="stat-icon info"><span class="icon">#</span></div>
                    </div>
                </div>
            </section>

            <!-- Transações -->
            <section class="transactions-section">
                <h2 class="section-title"><span class="icon">⟦</span> Transações Recentes</h2>
                <?php if (empty($recentTransactions)): ?>
                    <div class="no-data"><p>📊 Nenhuma transação encontrada</p></div>
                <?php else: ?>
                    <?php foreach ($recentTransactions as $transaction): ?>
                        <?php
                            $tIcon = $transaction['tipo'] === 'deposito' ? '↗' : ($transaction['tipo'] === 'saque' ? '↙' : '%');
                            $tGradient = $transaction['tipo'] === 'deposito' ? 'var(--gradient-success)' : ($transaction['tipo'] === 'saque' ? 'var(--gradient-danger)' : 'var(--gradient-warning)');
                            $tColor = $transaction['tipo'] === 'deposito' ? 'success' : 'danger';
                        ?>
                        <div class="transaction-item">
                            <div class="transaction-left">
                                <div class="transaction-icon" style="background: <?php echo $tGradient; ?>;">
                                    <span class="icon"><?php echo $tIcon; ?></span>
                                </div>
                                <div class="transaction-info">
                                    <h4><?php echo htmlspecialchars($transaction['descricao']); ?></h4>
                                    <span><?php echo formatDate($transaction['created_at']); ?></span>
                                </div>
                            </div>
                            <div class="transaction-amount" style="color: var(--<?php echo $tColor; ?>);">
                                <?php echo $transaction['tipo'] === 'deposito' ? '+' : '-'; ?><?php echo formatCurrency($transaction['valor']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <!-- Core JS -->
    <script src="../../assets/js/reu-admin-dashboard.js"></script>
</body>
</html>