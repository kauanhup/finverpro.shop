<?php
/**
 * ========================================
 * FINVER PRO - CONFIGURAÇÕES DO SISTEMA
 * Módulo Completo de Configurações
 * ========================================
 */

require_once '../includes/auth.php';
require_once '../../config/database.php';

// Verificar autenticação e permissões
requireAdmin('admin');

$admin = getAdminData();
$db = Database::getInstance();

// Processar formulário
if ($_POST) {
    try {
        $db->beginTransaction();
        
        // Configurações do site
        if (isset($_POST['site_nome'])) {
            $db->setConfig('site', 'nome', $_POST['site_nome'], 'string', 'Nome do site');
            $db->setConfig('site', 'titulo', $_POST['site_titulo'], 'string', 'Título do site');
            $db->setConfig('site', 'descricao', $_POST['site_descricao'], 'string', 'Descrição do site');
            $db->setConfig('site', 'url', $_POST['site_url'], 'url', 'URL do site');
        }
        
        // Configurações de saque
        if (isset($_POST['saque_valor_minimo'])) {
            $db->query("UPDATE config_saques SET 
                valor_minimo = ?, 
                taxa_percentual = ?, 
                limite_diario = ?,
                horario_inicio = ?,
                horario_fim = ?,
                segunda_feira = ?,
                terca_feira = ?,
                quarta_feira = ?,
                quinta_feira = ?,
                sexta_feira = ?,
                sabado = ?,
                domingo = ?,
                requer_investimento_ativo = ?,
                atualizado_em = NOW()
                WHERE id = 1", [
                floatval($_POST['saque_valor_minimo']),
                floatval($_POST['saque_taxa_percentual']),
                intval($_POST['saque_limite_diario']),
                $_POST['saque_horario_inicio'],
                $_POST['saque_horario_fim'],
                isset($_POST['saque_segunda']) ? 1 : 0,
                isset($_POST['saque_terca']) ? 1 : 0,
                isset($_POST['saque_quarta']) ? 1 : 0,
                isset($_POST['saque_quinta']) ? 1 : 0,
                isset($_POST['saque_sexta']) ? 1 : 0,
                isset($_POST['saque_sabado']) ? 1 : 0,
                isset($_POST['saque_domingo']) ? 1 : 0,
                isset($_POST['saque_requer_investimento']) ? 1 : 0
            ]);
        }
        
        // Configurações de comissão
        if (isset($_POST['comissao_nivel_1'])) {
            $db->query("UPDATE configuracao_comissoes SET percentual = ? WHERE nivel = 1", [floatval($_POST['comissao_nivel_1'])]);
            $db->query("UPDATE configuracao_comissoes SET percentual = ? WHERE nivel = 2", [floatval($_POST['comissao_nivel_2'])]);
            $db->query("UPDATE configuracao_comissoes SET percentual = ? WHERE nivel = 3", [floatval($_POST['comissao_nivel_3'])]);
        }
        
        // Configurações de cadastro
        if (isset($_POST['cadastro_bonus'])) {
            $db->query("UPDATE configurar_cadastro SET 
                bonus_cadastro = ?,
                min_password_length = ?,
                allow_registration = ?,
                require_invite_code = ?,
                updated_at = NOW()
                WHERE id = 1", [
                floatval($_POST['cadastro_bonus']),
                intval($_POST['cadastro_min_senha']),
                isset($_POST['cadastro_permitir']) ? 1 : 0,
                isset($_POST['cadastro_requer_convite']) ? 1 : 0
            ]);
        }
        
        // Configurações de texto
        if (isset($_POST['texto_titulo'])) {
            $db->query("UPDATE configurar_textos SET 
                titulo_site = ?,
                descricao_site = ?,
                keywords_site = ?,
                link_site = ?,
                link_suporte = ?,
                popup_titulo = ?,
                popup_ativo = ?,
                popup_delay = ?
                WHERE id = 1", [
                $_POST['texto_titulo'],
                $_POST['texto_descricao'],
                $_POST['texto_keywords'],
                $_POST['texto_link'],
                $_POST['texto_suporte'],
                $_POST['texto_popup_titulo'],
                isset($_POST['texto_popup_ativo']) ? 1 : 0,
                intval($_POST['texto_popup_delay'])
            ]);
        }
        
        // Configurações de cores
        if (isset($_POST['cor_1'])) {
            $db->query("UPDATE personalizar_cores SET 
                cor_1 = ?, cor_2 = ?, cor_3 = ?, cor_4 = ?, cor_5 = ?, updated_at = NOW()
                WHERE id = 1", [
                $_POST['cor_1'], $_POST['cor_2'], $_POST['cor_3'], $_POST['cor_4'], $_POST['cor_5']
            ]);
        }
        
        $db->commit();
        logAdminAction('config.update', 'Configurações do sistema atualizadas');
        $success = "Configurações salvas com sucesso!";
        
    } catch (Exception $e) {
        $db->rollback();
        logAdminAction('config.update.error', 'Erro ao atualizar configurações: ' . $e->getMessage());
        $error = "Erro ao salvar configurações: " . $e->getMessage();
    }
}

// Carregar configurações atuais
try {
    // Configurações de saque
    $configSaque = $db->fetchOne("SELECT * FROM config_saques WHERE id = 1") ?: [];
    
    // Configurações de comissão
    $configComissoes = [];
    $comissoes = $db->fetchAll("SELECT nivel, percentual FROM configuracao_comissoes ORDER BY nivel");
    foreach ($comissoes as $comissao) {
        $configComissoes[$comissao['nivel']] = $comissao['percentual'];
    }
    
    // Configurações de cadastro
    $configCadastro = $db->fetchOne("SELECT * FROM configurar_cadastro WHERE id = 1") ?: [];
    
    // Configurações de texto
    $configTexto = $db->fetchOne("SELECT * FROM configurar_textos WHERE id = 1") ?: [];
    
    // Configurações de cores
    $configCores = $db->fetchOne("SELECT * FROM personalizar_cores WHERE id = 1") ?: [];
    
    // Gateways
    $gateways = $db->fetchAll("SELECT * FROM gateways ORDER BY nome");
    
} catch (Exception $e) {
    error_log("Erro ao carregar configurações: " . $e->getMessage());
    $configSaque = $configComissoes = $configCadastro = $configTexto = $configCores = [];
    $gateways = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações do Sistema - Finver Pro</title>
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
            --warning-color: #F59E0B;
            --info-color: #3B82F6;
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
            background: linear-gradient(135deg, var(--success-color), var(--info-color));
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
        
        .config-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .config-tab {
            padding: 1rem 1.5rem;
            background: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .config-tab.active {
            color: var(--info-color);
            border-bottom-color: var(--info-color);
        }
        
        .config-section {
            display: none;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--border-radius);
            padding: 2rem;
        }
        
        .config-section.active { display: block; }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--text-color);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .form-label {
            font-weight: 500;
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.875rem;
        }
        
        .form-input, .form-select, .form-textarea {
            padding: 0.75rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius);
            color: var(--text-color);
            font-size: 0.875rem;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--secondary-color);
            background: rgba(255, 255, 255, 0.15);
        }
        
        .form-textarea { resize: vertical; min-height: 100px; }
        
        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .form-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--secondary-color);
        }
        
        .color-input {
            width: 60px;
            height: 40px;
            padding: 0;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            cursor: pointer;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: var(--secondary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-color);
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
        
        .gateway-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .gateway-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .gateway-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .gateway-status.ativo {
            background: rgba(16, 185, 129, 0.2);
            color: #10B981;
        }
        
        .gateway-status.inativo {
            background: rgba(239, 68, 68, 0.2);
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
                        <a href="../saques/" class="nav-link">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Saques</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../produtos/" class="nav-link">
                            <i class="fas fa-robot"></i>
                            <span>Produtos</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../configuracoes/" class="nav-link active">
                            <i class="fas fa-cog"></i>
                            <span>Configurações</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../afiliados/" class="nav-link">
                            <i class="fas fa-user-friends"></i>
                            <span>Afiliados</span>
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
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">Configurações do Sistema</h1>
                <div class="page-actions">
                    <div class="admin-info">
                        <div class="admin-avatar"><?= strtoupper(substr($admin['nome'] ?: $admin['email'], 0, 2)) ?></div>
                        <div>
                            <div style="font-weight: 500;"><?= htmlspecialchars($admin['nome'] ?: 'Admin') ?></div>
                            <div style="font-size: 0.75rem; opacity: 0.7;"><?= htmlspecialchars($admin['email']) ?></div>
                        </div>
                    </div>
                    <a href="../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        Sair
                    </a>
                </div>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Tabs de Configuração -->
            <div class="config-tabs">
                <button class="config-tab active" onclick="showTab('saques')">
                    <i class="fas fa-money-bill-wave"></i>
                    Saques
                </button>
                <button class="config-tab" onclick="showTab('comissoes')">
                    <i class="fas fa-percentage"></i>
                    Comissões
                </button>
                <button class="config-tab" onclick="showTab('cadastro')">
                    <i class="fas fa-user-plus"></i>
                    Cadastro
                </button>
                <button class="config-tab" onclick="showTab('site')">
                    <i class="fas fa-globe"></i>
                    Site
                </button>
                <button class="config-tab" onclick="showTab('cores')">
                    <i class="fas fa-palette"></i>
                    Cores
                </button>
                <button class="config-tab" onclick="showTab('gateways')">
                    <i class="fas fa-credit-card"></i>
                    Gateways
                </button>
            </div>

            <form method="POST">
                <!-- Configurações de Saques -->
                <div id="saques" class="config-section active">
                    <h2 class="section-title">
                        <i class="fas fa-money-bill-wave"></i>
                        Configurações de Saques
                    </h2>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Valor Mínimo (R$)</label>
                            <input type="number" name="saque_valor_minimo" class="form-input" 
                                   value="<?= $configSaque['valor_minimo'] ?? 30 ?>" 
                                   step="0.01" min="1" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Taxa Percentual (%)</label>
                            <input type="number" name="saque_taxa_percentual" class="form-input" 
                                   value="<?= $configSaque['taxa_percentual'] ?? 8 ?>" 
                                   step="0.01" min="0" max="100" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Limite Diário</label>
                            <input type="number" name="saque_limite_diario" class="form-input" 
                                   value="<?= $configSaque['limite_diario'] ?? 1 ?>" 
                                   min="1" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Horário de Início</label>
                            <input type="time" name="saque_horario_inicio" class="form-input" 
                                   value="<?= $configSaque['horario_inicio'] ?? '09:00' ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Horário de Fim</label>
                            <input type="time" name="saque_horario_fim" class="form-input" 
                                   value="<?= $configSaque['horario_fim'] ?? '18:00' ?>" required>
                        </div>
                    </div>
                    
                    <h3 style="margin: 2rem 0 1rem 0; font-size: 1.125rem;">Dias Permitidos</h3>
                    <div class="form-grid">
                        <div class="form-checkbox">
                            <input type="checkbox" name="saque_segunda" <?= ($configSaque['segunda_feira'] ?? 1) ? 'checked' : '' ?>>
                            <label>Segunda-feira</label>
                        </div>
                        <div class="form-checkbox">
                            <input type="checkbox" name="saque_terca" <?= ($configSaque['terca_feira'] ?? 1) ? 'checked' : '' ?>>
                            <label>Terça-feira</label>
                        </div>
                        <div class="form-checkbox">
                            <input type="checkbox" name="saque_quarta" <?= ($configSaque['quarta_feira'] ?? 1) ? 'checked' : '' ?>>
                            <label>Quarta-feira</label>
                        </div>
                        <div class="form-checkbox">
                            <input type="checkbox" name="saque_quinta" <?= ($configSaque['quinta_feira'] ?? 1) ? 'checked' : '' ?>>
                            <label>Quinta-feira</label>
                        </div>
                        <div class="form-checkbox">
                            <input type="checkbox" name="saque_sexta" <?= ($configSaque['sexta_feira'] ?? 1) ? 'checked' : '' ?>>
                            <label>Sexta-feira</label>
                        </div>
                        <div class="form-checkbox">
                            <input type="checkbox" name="saque_sabado" <?= ($configSaque['sabado'] ?? 0) ? 'checked' : '' ?>>
                            <label>Sábado</label>
                        </div>
                        <div class="form-checkbox">
                            <input type="checkbox" name="saque_domingo" <?= ($configSaque['domingo'] ?? 0) ? 'checked' : '' ?>>
                            <label>Domingo</label>
                        </div>
                    </div>
                    
                    <div style="margin-top: 1.5rem;">
                        <div class="form-checkbox">
                            <input type="checkbox" name="saque_requer_investimento" <?= ($configSaque['requer_investimento_ativo'] ?? 1) ? 'checked' : '' ?>>
                            <label>Exigir investimento ativo para saque</label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="margin-top: 2rem;">
                        <i class="fas fa-save"></i>
                        Salvar Configurações de Saque
                    </button>
                </div>

                <!-- Configurações de Comissões -->
                <div id="comissoes" class="config-section">
                    <h2 class="section-title">
                        <i class="fas fa-percentage"></i>
                        Configurações de Comissões
                    </h2>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Comissão Nível 1 (%)</label>
                            <input type="number" name="comissao_nivel_1" class="form-input" 
                                   value="<?= $configComissoes[1] ?? 10 ?>" 
                                   step="0.01" min="0" max="100" required>
                            <small style="color: rgba(255,255,255,0.6);">Indicação direta</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Comissão Nível 2 (%)</label>
                            <input type="number" name="comissao_nivel_2" class="form-input" 
                                   value="<?= $configComissoes[2] ?? 6 ?>" 
                                   step="0.01" min="0" max="100" required>
                            <small style="color: rgba(255,255,255,0.6);">Segundo nível</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Comissão Nível 3 (%)</label>
                            <input type="number" name="comissao_nivel_3" class="form-input" 
                                   value="<?= $configComissoes[3] ?? 1 ?>" 
                                   step="0.01" min="0" max="100" required>
                            <small style="color: rgba(255,255,255,0.6);">Terceiro nível</small>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="margin-top: 2rem;">
                        <i class="fas fa-save"></i>
                        Salvar Configurações de Comissão
                    </button>
                </div>

                <!-- Configurações de Cadastro -->
                <div id="cadastro" class="config-section">
                    <h2 class="section-title">
                        <i class="fas fa-user-plus"></i>
                        Configurações de Cadastro
                    </h2>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Bônus de Cadastro (R$)</label>
                            <input type="number" name="cadastro_bonus" class="form-input" 
                                   value="<?= $configCadastro['bonus_cadastro'] ?? 6 ?>" 
                                   step="0.01" min="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Tamanho Mínimo da Senha</label>
                            <input type="number" name="cadastro_min_senha" class="form-input" 
                                   value="<?= $configCadastro['min_password_length'] ?? 6 ?>" 
                                   min="4" max="20" required>
                        </div>
                    </div>
                    
                    <div style="margin-top: 1.5rem;">
                        <div class="form-checkbox">
                            <input type="checkbox" name="cadastro_permitir" <?= ($configCadastro['allow_registration'] ?? 1) ? 'checked' : '' ?>>
                            <label>Permitir novos cadastros</label>
                        </div>
                        
                        <div class="form-checkbox">
                            <input type="checkbox" name="cadastro_requer_convite" <?= ($configCadastro['require_invite_code'] ?? 0) ? 'checked' : '' ?>>
                            <label>Exigir código de convite</label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="margin-top: 2rem;">
                        <i class="fas fa-save"></i>
                        Salvar Configurações de Cadastro
                    </button>
                </div>

                <!-- Configurações do Site -->
                <div id="site" class="config-section">
                    <h2 class="section-title">
                        <i class="fas fa-globe"></i>
                        Configurações do Site
                    </h2>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Título do Site</label>
                            <input type="text" name="texto_titulo" class="form-input" 
                                   value="<?= htmlspecialchars($configTexto['titulo_site'] ?? 'FinverPro') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Link do Site</label>
                            <input type="url" name="texto_link" class="form-input" 
                                   value="<?= htmlspecialchars($configTexto['link_site'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Link do Suporte</label>
                            <input type="url" name="texto_suporte" class="form-input" 
                                   value="<?= htmlspecialchars($configTexto['link_suporte'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Título do Popup</label>
                            <input type="text" name="texto_popup_titulo" class="form-input" 
                                   value="<?= htmlspecialchars($configTexto['popup_titulo'] ?? 'Notificação') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Delay do Popup (ms)</label>
                            <input type="number" name="texto_popup_delay" class="form-input" 
                                   value="<?= $configTexto['popup_delay'] ?? 3000 ?>" min="1000">
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-top: 1.5rem;">
                        <label class="form-label">Descrição do Site</label>
                        <textarea name="texto_descricao" class="form-textarea"><?= htmlspecialchars($configTexto['descricao_site'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Keywords SEO</label>
                        <textarea name="texto_keywords" class="form-textarea"><?= htmlspecialchars($configTexto['keywords_site'] ?? '') ?></textarea>
                    </div>
                    
                    <div style="margin-top: 1.5rem;">
                        <div class="form-checkbox">
                            <input type="checkbox" name="texto_popup_ativo" <?= ($configTexto['popup_ativo'] ?? 1) ? 'checked' : '' ?>>
                            <label>Popup ativo</label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="margin-top: 2rem;">
                        <i class="fas fa-save"></i>
                        Salvar Configurações do Site
                    </button>
                </div>

                <!-- Configurações de Cores -->
                <div id="cores" class="config-section">
                    <h2 class="section-title">
                        <i class="fas fa-palette"></i>
                        Personalização de Cores
                    </h2>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Cor Primária</label>
                            <input type="color" name="cor_1" class="color-input" 
                                   value="<?= $configCores['cor_1'] ?? '#121A1E' ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Cor Secundária</label>
                            <input type="color" name="cor_2" class="color-input" 
                                   value="<?= $configCores['cor_2'] ?? '#FFFFFF' ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Cor Terciária</label>
                            <input type="color" name="cor_3" class="color-input" 
                                   value="<?= $configCores['cor_3'] ?? '#152731' ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Cor Quaternária</label>
                            <input type="color" name="cor_4" class="color-input" 
                                   value="<?= $configCores['cor_4'] ?? '#335D67' ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Cor Quinária</label>
                            <input type="color" name="cor_5" class="color-input" 
                                   value="<?= $configCores['cor_5'] ?? '#152731' ?>">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="margin-top: 2rem;">
                        <i class="fas fa-save"></i>
                        Salvar Configurações de Cores
                    </button>
                </div>

                <!-- Configurações de Gateways -->
                <div id="gateways" class="config-section">
                    <h2 class="section-title">
                        <i class="fas fa-credit-card"></i>
                        Gateways de Pagamento
                    </h2>
                    
                    <?php foreach ($gateways as $gateway): ?>
                        <div class="gateway-card">
                            <div class="gateway-header">
                                <h3><?= htmlspecialchars($gateway['nome']) ?></h3>
                                <span class="gateway-status <?= $gateway['ativo'] ? 'ativo' : 'inativo' ?>">
                                    <?= $gateway['ativo'] ? 'Ativo' : 'Inativo' ?>
                                </span>
                            </div>
                            <p><strong>Código:</strong> <?= htmlspecialchars($gateway['codigo']) ?></p>
                            <p><strong>Ambiente:</strong> <?= ucfirst($gateway['ambiente']) ?></p>
                            <?php if ($gateway['taxa_percentual'] > 0): ?>
                                <p><strong>Taxa:</strong> <?= number_format($gateway['taxa_percentual'], 2) ?>%</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($gateways)): ?>
                        <p style="text-align: center; color: rgba(255,255,255,0.6); padding: 2rem;">
                            Nenhum gateway configurado
                        </p>
                    <?php endif; ?>
                </div>
            </form>
        </main>
    </div>

    <script>
        function showTab(tabName) {
            // Esconder todas as seções
            document.querySelectorAll('.config-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Remover classe active de todas as tabs
            document.querySelectorAll('.config-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Mostrar seção selecionada
            document.getElementById(tabName).classList.add('active');
            
            // Adicionar classe active na tab clicada
            event.target.classList.add('active');
        }
    </script>
</body>
</html>