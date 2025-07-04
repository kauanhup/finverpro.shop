<?php
require_once '../includes/auth.php';
require_once '../../config/database.php';

requireAdmin();
$admin = getAdminData();
$db = Database::getInstance();

// Processar atualizações
if ($_POST) {
    try {
        $db->beginTransaction();
        
        // Atualizar configurações gerais
        if (isset($_POST['site_titulo'])) {
            $db->query("INSERT INTO configuracoes (categoria, chave, valor) VALUES ('site', 'titulo', ?) 
                       ON DUPLICATE KEY UPDATE valor = ?", [$_POST['site_titulo'], $_POST['site_titulo']]);
        }
        
        if (isset($_POST['site_descricao'])) {
            $db->query("INSERT INTO configuracoes (categoria, chave, valor) VALUES ('site', 'descricao', ?) 
                       ON DUPLICATE KEY UPDATE valor = ?", [$_POST['site_descricao'], $_POST['site_descricao']]);
        }
        
        // Atualizar configurações de saque
        if (isset($_POST['saque_valor_minimo'])) {
            $db->query("UPDATE config_saques SET 
                valor_minimo = ?, 
                taxa_percentual = ?, 
                limite_diario = ?,
                requer_investimento_ativo = ?
                WHERE id = 1", [
                $_POST['saque_valor_minimo'],
                $_POST['saque_taxa'],
                $_POST['saque_limite_diario'],
                isset($_POST['saque_requer_investimento']) ? 1 : 0
            ]);
        }
        
        $db->commit();
        logAdminAction('config.update', 'Configurações atualizadas');
        $message = "Configurações atualizadas com sucesso!";
        
    } catch (Exception $e) {
        $db->rollback();
        $error = "Erro ao atualizar configurações: " . $e->getMessage();
    }
}

// Buscar configurações atuais
try {
    $configs = [];
    $result = $db->fetchAll("SELECT categoria, chave, valor FROM configuracoes");
    foreach ($result as $config) {
        $configs[$config['categoria']][$config['chave']] = $config['valor'];
    }
    
    $configSaques = $db->fetchOne("SELECT * FROM config_saques WHERE id = 1") ?: [
        'valor_minimo' => 30.00,
        'taxa_percentual' => 8.00,
        'limite_diario' => 1,
        'requer_investimento_ativo' => 1
    ];
    
} catch (Exception $e) {
    $configs = [];
    $configSaques = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - Finver Pro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #152731;
            --secondary-color: #335D67;
            --background-color: #121A1E;
            --text-color: #FFFFFF;
            --success-color: #10B981;
            --error-color: #EF4444;
            --border-radius: 12px;
            --sidebar-width: 280px;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--background-color);
            color: var(--text-color);
            line-height: 1.6;
        }
        
        .admin-layout { display: flex; min-height: 100vh; }
        
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-color) 100%);
            padding: 1.5rem;
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
            background: linear-gradient(135deg, var(--success-color), #3B82F6);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: white;
        }
        
        .sidebar-title { font-size: 1.25rem; font-weight: 700; margin-bottom: 0.25rem; }
        .sidebar-subtitle { font-size: 0.875rem; color: rgba(255, 255, 255, 0.7); }
        
        .sidebar-nav ul { list-style: none; }
        .nav-item { margin-bottom: 0.5rem; }
        
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
        
        .nav-link i { margin-right: 0.75rem; width: 20px; text-align: center; }
        
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 2rem;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
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
        }
        
        .config-section {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.9);
        }
        
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius);
            color: var(--text-color);
            font-size: 0.875rem;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--secondary-color);
            background: rgba(255, 255, 255, 0.15);
        }
        
        .form-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: var(--secondary-color);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
            font-weight: 500;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10B981;
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #EF4444;
        }
        
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h2 class="sidebar-title">Admin Panel</h2>
                <p class="sidebar-subtitle">Finver Pro</p>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li class="nav-item">
                        <a href="../dashboard/" class="nav-link">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../usuarios/" class="nav-link">
                            <i class="fas fa-users"></i>
                            <span>Usuários</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../afiliados/" class="nav-link">
                            <i class="fas fa-user-friends"></i>
                            <span>Afiliados</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../produtos/" class="nav-link">
                            <i class="fas fa-robot"></i>
                            <span>Produtos</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../saques/" class="nav-link">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Saques</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../pagamentos/" class="nav-link">
                            <i class="fas fa-credit-card"></i>
                            <span>Pagamentos</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../relatorios/" class="nav-link">
                            <i class="fas fa-chart-bar"></i>
                            <span>Relatórios</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="./" class="nav-link active">
                            <i class="fas fa-cogs"></i>
                            <span>Configurações</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">Configurações do Sistema</h1>
            </div>
            
            <?php if (isset($message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <!-- Configurações Gerais -->
                <div class="config-section">
                    <h3 class="section-title">
                        <i class="fas fa-globe"></i>
                        Configurações Gerais
                    </h3>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Título do Site</label>
                            <input type="text" name="site_titulo" class="form-input" 
                                   value="<?= htmlspecialchars($configs['site']['titulo'] ?? 'Finver Pro') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Descrição do Site</label>
                            <textarea name="site_descricao" class="form-input" rows="3"><?= htmlspecialchars($configs['site']['descricao'] ?? 'Plataforma de investimentos automatizados') ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Configurações de Saques -->
                <div class="config-section">
                    <h3 class="section-title">
                        <i class="fas fa-money-bill-wave"></i>
                        Configurações de Saques
                    </h3>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Valor Mínimo (R$)</label>
                            <input type="number" step="0.01" name="saque_valor_minimo" class="form-input" 
                                   value="<?= $configSaques['valor_minimo'] ?? 30.00 ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Taxa (%)</label>
                            <input type="number" step="0.01" name="saque_taxa" class="form-input" 
                                   value="<?= $configSaques['taxa_percentual'] ?? 8.00 ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Limite Diário</label>
                            <input type="number" name="saque_limite_diario" class="form-input" 
                                   value="<?= $configSaques['limite_diario'] ?? 1 ?>">
                        </div>
                        
                        <div class="form-group">
                            <div class="form-check">
                                <input type="checkbox" name="saque_requer_investimento" id="saque_requer_investimento" 
                                       <?= ($configSaques['requer_investimento_ativo'] ?? 1) ? 'checked' : '' ?>>
                                <label for="saque_requer_investimento" class="form-label">Requer investimento ativo</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Botão de Salvar -->
                <div style="text-align: center; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Salvar Configurações
                    </button>
                </div>
            </form>
        </main>
    </div>
</body>
</html>