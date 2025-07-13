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
    
    <!-- CSS -->
    <link rel="stylesheet" href="../../assets/css/reu-admin-dashboard.css">
    
    <style>
        /* Estilos básicos caso o CSS não carregue */
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            background: #f5f5f5;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0;
            font-size: 2.5em;
            font-weight: 700;
        }
        
        .header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        
        .debug-panel {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .debug-panel h3 {
            color: #333;
            margin-top: 0;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        
        .debug-panel h4 {
            color: #667eea;
            margin-top: 20px;
        }
        
        .debug-panel pre {
            background: #f8f8f8;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 12px;
            border-left: 4px solid #667eea;
        }
        
        .debug-logs {
            background: #f0f7ff;
            border: 1px solid #b3d9ff;
            border-radius: 4px;
            padding: 15px;
            margin: 10px 0;
        }
        
        .debug-logs ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .debug-logs li {
            padding: 2px 0;
            font-family: monospace;
            font-size: 12px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .stat-card {
            background: #fff;
            padding: 25px;
            border-radius: 8px;
            border: 1px solid #ddd;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .stat-card h4 {
            color: #667eea;
            margin: 0 0 10px 0;
            font-size: 16px;
            font-weight: 600;
        }
        
        .stat-card .value {
            font-size: 28px;
            font-weight: bold;
            margin: 0;
        }
        
        .stat-card .value.success { color: #059669; }
        .stat-card .value.danger { color: #dc2626; }
        .stat-card .value.info { color: #2563eb; }
        .stat-card .value.warning { color: #d97706; }
        
        .transactions-section {
            margin-top: 40px;
        }
        
        .transaction-item {
            background: #f8f9fa;
            padding: 20px;
            margin: 15px 0;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .transaction-item.deposito {
            border-left-color: #059669;
        }
        
        .transaction-item.saque {
            border-left-color: #dc2626;
        }
        
        .transaction-info strong {
            display: block;
            margin-bottom: 5px;
            color: #333;
        }
        
        .transaction-info small {
            color: #6b7280;
        }
        
        .transaction-amount {
            font-size: 18px;
            font-weight: bold;
        }
        
        .transaction-amount.success { color: #059669; }
        .transaction-amount.danger { color: #dc2626; }
        
        .success-badge {
            background: #10b981;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #6b7280;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Finver Pro Dashboard</h1>
            <p>Bem-vindo, <strong><?php echo htmlspecialchars($admin['nome'] ?? 'Administrador'); ?></strong>!</p>
            <span class="success-badge">Sistema Funcionando</span>
        </div>
        
        <div class="debug-panel">
            <h3>🔍 Logs de Debug</h3>
            <div class="debug-logs">
                <ul>
                    <?php foreach ($debug_log as $log): ?>
                        <li><?php echo htmlspecialchars($log); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        
        <div class="debug-panel">
            <h3>👤 Informações do Admin</h3>
            <p><strong>Nome:</strong> <?php echo htmlspecialchars($admin['nome'] ?? 'N/A'); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($admin['email'] ?? 'N/A'); ?></p>
            <p><strong>ID:</strong> <?php echo htmlspecialchars($admin['id'] ?? 'N/A'); ?></p>
            <p><strong>Tipo:</strong> <?php echo htmlspecialchars($admin['tipo_usuario'] ?? 'N/A'); ?></p>
        </div>
        
        <div class="debug-panel">
            <h3>📊 Estatísticas do Dashboard</h3>
            <div class="stats-grid">
                <div class="stat-card">
                    <h4>💰 Depósitos Hoje</h4>
                    <p class="value success"><?php echo formatCurrency($stats['depositos_hoje']); ?></p>
                </div>
                
                <div class="stat-card">
                    <h4>💎 Total Depósitos</h4>
                    <p class="value success"><?php echo formatCurrency($stats['total_depositos']); ?></p>
                </div>
                
                <div class="stat-card">
                    <h4>👥 Cadastros Hoje</h4>
                    <p class="value info"><?php echo formatNumber($stats['cadastros_hoje']); ?></p>
                </div>
                
                <div class="stat-card">
                    <h4>🎯 Total Cadastros</h4>
                    <p class="value info"><?php echo formatNumber($stats['total_cadastros']); ?></p>
                </div>
                
                <div class="stat-card">
                    <h4>💸 Total Sacado</h4>
                    <p class="value danger"><?php echo formatCurrency($stats['total_sacado']); ?></p>
                </div>
                
                <div class="stat-card">
                    <h4>📈 Comissões Hoje</h4>
                    <p class="value warning"><?php echo formatCurrency($stats['comissoes_hoje']); ?></p>
                </div>
                
                <div class="stat-card">
                    <h4>🏆 Saldo Plataforma</h4>
                    <p class="value success"><?php echo formatCurrency($stats['saldo_plataforma']); ?></p>
                </div>
                
                <div class="stat-card">
                    <h4>🔥 Investidores Ativos</h4>
                    <p class="value info"><?php echo formatNumber($stats['investidores_ativos']); ?></p>
                </div>
            </div>
        </div>
        
        <div class="debug-panel">
            <h3>💳 Transações Recentes</h3>
            <?php if (empty($recentTransactions)): ?>
                <div class="no-data">
                    <p>📊 Nenhuma transação encontrada</p>
                </div>
            <?php else: ?>
                <?php foreach ($recentTransactions as $transaction): ?>
                    <div class="transaction-item <?php echo $transaction['tipo']; ?>">
                        <div class="transaction-info">
                            <strong><?php echo htmlspecialchars($transaction['descricao']); ?></strong>
                            <small><?php echo formatDate($transaction['created_at']); ?></small>
                            <?php if ($transaction['nome_titular']): ?>
                                <small> - <?php echo htmlspecialchars($transaction['nome_titular']); ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="transaction-amount <?php echo $transaction['tipo'] === 'deposito' ? 'success' : 'danger'; ?>">
                            <?php echo $transaction['tipo'] === 'deposito' ? '+' : '-'; ?><?php echo formatCurrency($transaction['valor']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="debug-panel">
            <h3>🔧 Dados Técnicos</h3>
            <h4>Estatísticas Brutas:</h4>
            <pre><?php print_r($stats); ?></pre>
            
            <h4>Transações Brutas:</h4>
            <pre><?php print_r($recentTransactions); ?></pre>
        </div>
    </div>
    
    <script>
        console.log('✅ Dashboard carregado com sucesso!');
        console.log('📊 Stats:', <?php echo json_encode($stats); ?>);
        console.log('💳 Transações:', <?php echo json_encode($recentTransactions); ?>);
        console.log('🔍 Debug logs:', <?php echo json_encode($debug_log); ?>);
        
        // Auto-refresh a cada 5 minutos
        setTimeout(() => {
            console.log('🔄 Auto-refresh...');
            window.location.reload();
        }, 300000);
    </script>
</body>
</html>