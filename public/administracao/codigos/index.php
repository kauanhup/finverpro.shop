<?php
/**
 * ========================================
 * FINVER PRO - ADMINISTRAÇÃO DE CÓDIGOS
 * Módulo Completo de Gerenciamento de Códigos de Bônus
 * ========================================
 */

require_once '../includes/auth.php';
require_once '../../config/database.php';

// Verificar autenticação e permissões
requireAdmin('admin');

$admin = getAdminData();
$db = Database::getInstance();

// Processar ações
$action = $_GET['action'] ?? '';
$success = '';
$error = '';

switch ($action) {
    case 'add':
        if ($_POST) {
            try {
                // Gerar código automático se não fornecido
                $codigo = $_POST['codigo'] ?: strtoupper(substr(md5(time() . rand()), 0, 8));
                
                $db->query("INSERT INTO bonus_codigos (codigo, valor, descricao, max_usos, ativo, data_expiracao) VALUES (?, ?, ?, ?, ?, ?)", [
                    $codigo,
                    floatval($_POST['valor']),
                    $_POST['descricao'],
                    intval($_POST['max_usos']),
                    isset($_POST['ativo']) ? 1 : 0,
                    $_POST['data_expiracao'] ?: null
                ]);
                
                logAdminAction('codigo.add', 'Novo código criado: ' . $codigo);
                $success = "Código criado com sucesso: {$codigo}";
            } catch (Exception $e) {
                $error = "Erro ao criar código: " . $e->getMessage();
            }
        }
        break;
        
    case 'edit':
        $id = intval($_GET['id']);
        if ($_POST) {
            try {
                $db->query("UPDATE bonus_codigos SET valor = ?, descricao = ?, max_usos = ?, ativo = ?, data_expiracao = ? WHERE id = ?", [
                    floatval($_POST['valor']),
                    $_POST['descricao'],
                    intval($_POST['max_usos']),
                    isset($_POST['ativo']) ? 1 : 0,
                    $_POST['data_expiracao'] ?: null,
                    $id
                ]);
                
                logAdminAction('codigo.edit', 'Código editado: ID ' . $id);
                $success = "Código atualizado com sucesso!";
            } catch (Exception $e) {
                $error = "Erro ao atualizar código: " . $e->getMessage();
            }
        }
        break;
        
    case 'delete':
        $id = intval($_GET['id']);
        try {
            $codigo = $db->fetchOne("SELECT codigo FROM bonus_codigos WHERE id = ?", [$id])['codigo'] ?? '';
            $db->query("DELETE FROM bonus_codigos WHERE id = ?", [$id]);
            logAdminAction('codigo.delete', 'Código excluído: ' . $codigo);
            $success = "Código excluído com sucesso!";
        } catch (Exception $e) {
            $error = "Erro ao excluir código: " . $e->getMessage();
        }
        break;
        
    case 'toggle':
        $id = intval($_GET['id']);
        try {
            $db->query("UPDATE bonus_codigos SET ativo = !ativo WHERE id = ?", [$id]);
            logAdminAction('codigo.toggle', 'Status do código alterado: ID ' . $id);
            $success = "Status alterado com sucesso!";
        } catch (Exception $e) {
            $error = "Erro ao alterar status: " . $e->getMessage();
        }
        break;
        
    case 'generate_bulk':
        if ($_POST) {
            try {
                $quantidade = intval($_POST['quantidade']);
                $valor = floatval($_POST['valor']);
                $max_usos = intval($_POST['max_usos']);
                $prefixo = $_POST['prefixo'] ?: 'BONUS';
                
                $codigosCriados = [];
                for ($i = 0; $i < $quantidade; $i++) {
                    $codigo = $prefixo . strtoupper(substr(md5(time() . rand() . $i), 0, 6));
                    $db->query("INSERT INTO bonus_codigos (codigo, valor, descricao, max_usos, ativo) VALUES (?, ?, ?, ?, 1)", [
                        $codigo,
                        $valor,
                        "Código gerado em lote",
                        $max_usos
                    ]);
                    $codigosCriados[] = $codigo;
                }
                
                logAdminAction('codigo.bulk', "Criados {$quantidade} códigos em lote");
                $success = "Criados {$quantidade} códigos com sucesso!";
            } catch (Exception $e) {
                $error = "Erro ao criar códigos em lote: " . $e->getMessage();
            }
        }
        break;
}

// Buscar dados
$page = intval($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

$codigos = $db->fetchAll("SELECT * FROM bonus_codigos ORDER BY data_criacao DESC LIMIT ? OFFSET ?", [$limit, $offset]);
$totalCodigos = $db->fetchOne("SELECT COUNT(*) as total FROM bonus_codigos")['total'];
$totalPages = ceil($totalCodigos / $limit);

// Estatísticas
$stats = [
    'total_codigos' => $totalCodigos,
    'codigos_ativos' => $db->fetchOne("SELECT COUNT(*) as total FROM bonus_codigos WHERE ativo = 1 AND (data_expiracao IS NULL OR data_expiracao > NOW())")['total'],
    'codigos_usados' => $db->fetchOne("SELECT COUNT(*) as total FROM bonus_codigos WHERE usos_atuais > 0")['total'],
    'valor_total_distribuido' => $db->fetchOne("SELECT SUM(bc.valor * bc.usos_atuais) as total FROM bonus_codigos bc")['total'] ?? 0
];

// Histórico de resgates recentes
$resgates = $db->fetchAll("SELECT br.*, u.nome as usuario_nome FROM bonus_resgatados br LEFT JOIN usuarios u ON br.user_id = u.id ORDER BY br.data_resgate DESC LIMIT 10");

$editItem = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $editItem = $db->fetchOne("SELECT * FROM bonus_codigos WHERE id = ?", [intval($_GET['id'])]);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administração de Códigos - Finver Pro</title>
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
        
        .header-actions {
            display: flex;
            gap: 1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            text-align: center;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
        }
        
        .card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--border-radius);
            padding: 1.5rem;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
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
            font-size: 0.875rem;
        }
        
        .btn-primary {
            background: var(--secondary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-color);
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: var(--success-color);
            color: white;
        }
        
        .btn-danger {
            background: var(--error-color);
            color: white;
        }
        
        .btn-warning {
            background: var(--warning-color);
            color: white;
        }
        
        .btn-info {
            background: var(--info-color);
            color: white;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
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
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
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
        
        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--secondary-color);
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .table th {
            background: rgba(255, 255, 255, 0.05);
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
        }
        
        .table tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-success {
            background: rgba(16, 185, 129, 0.2);
            color: #10B981;
        }
        
        .badge-danger {
            background: rgba(239, 68, 68, 0.2);
            color: #EF4444;
        }
        
        .badge-warning {
            background: rgba(245, 158, 11, 0.2);
            color: #F59E0B;
        }
        
        .badge-info {
            background: rgba(59, 130, 246, 0.2);
            color: #3B82F6;
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
        
        .codigo-display {
            font-family: 'Courier New', monospace;
            background: rgba(255, 255, 255, 0.1);
            padding: 0.5rem;
            border-radius: 6px;
            font-weight: 600;
            letter-spacing: 1px;
        }
        
        .progress-bar {
            width: 100%;
            height: 6px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
            overflow: hidden;
            margin: 0.5rem 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--success-color), var(--info-color));
            transition: width 0.3s ease;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
        }
        
        .modal-content {
            background: var(--background-color);
            margin: 5% auto;
            padding: 2rem;
            border-radius: var(--border-radius);
            width: 90%;
            max-width: 500px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
        }
        
        .close:hover {
            color: white;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }
        
        .pagination a {
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius);
            color: var(--text-color);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .pagination a:hover,
        .pagination a.active {
            background: var(--secondary-color);
        }
        
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
            .content-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
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
                        <a href="../roleta/" class="nav-link">
                            <i class="fas fa-dice"></i>
                            <span>Roleta</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../checklist/" class="nav-link">
                            <i class="fas fa-tasks"></i>
                            <span>Checklist</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../codigos/" class="nav-link active">
                            <i class="fas fa-gift"></i>
                            <span>Códigos</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../configuracoes/" class="nav-link">
                            <i class="fas fa-cog"></i>
                            <span>Configurações</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../relatorios/" class="nav-link">
                            <i class="fas fa-chart-line"></i>
                            <span>Relatórios</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../logout.php" class="nav-link">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Sair</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">🎁 Administração de Códigos</h1>
                <div class="header-actions">
                    <button class="btn btn-info" onclick="openModal('bulkModal')">
                        <i class="fas fa-layer-group"></i>
                        Gerar em Lote
                    </button>
                    <button class="btn btn-primary" onclick="openModal('addModal')">
                        <i class="fas fa-plus"></i>
                        Novo Código
                    </button>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Estatísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #10B981, #059669);">
                        <i class="fas fa-gift"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['total_codigos']) ?></div>
                    <div class="stat-label">Total de Códigos</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #3B82F6, #1D4ED8);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['codigos_ativos']) ?></div>
                    <div class="stat-label">Códigos Ativos</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #F59E0B, #D97706);">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['codigos_usados']) ?></div>
                    <div class="stat-label">Códigos Usados</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #EF4444, #DC2626);">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="stat-value">R$ <?= number_format($stats['valor_total_distribuido'], 2, ',', '.') ?></div>
                    <div class="stat-label">Valor Distribuído</div>
                </div>
            </div>

            <!-- Conteúdo Principal -->
            <div class="content-grid">
                <!-- Lista de Códigos -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Códigos de Bônus</h2>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Valor</th>
                                    <th>Usos</th>
                                    <th>Status</th>
                                    <th>Criado</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($codigos as $codigo): ?>
                                <tr>
                                    <td>
                                        <div class="codigo-display"><?= htmlspecialchars($codigo['codigo']) ?></div>
                                        <?php if ($codigo['descricao']): ?>
                                            <small style="color: rgba(255, 255, 255, 0.7);"><?= htmlspecialchars($codigo['descricao']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>R$ <?= number_format($codigo['valor'], 2, ',', '.') ?></td>
                                    <td>
                                        <div><?= $codigo['usos_atuais'] ?> / <?= $codigo['max_usos'] ?: '∞' ?></div>
                                        <?php if ($codigo['max_usos'] > 0): ?>
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?= ($codigo['usos_atuais'] / $codigo['max_usos']) * 100 ?>%"></div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $expired = $codigo['data_expiracao'] && strtotime($codigo['data_expiracao']) < time();
                                        $maxed = $codigo['max_usos'] > 0 && $codigo['usos_atuais'] >= $codigo['max_usos'];
                                        
                                        if (!$codigo['ativo']):
                                        ?>
                                            <span class="badge badge-danger">Inativo</span>
                                        <?php elseif ($expired): ?>
                                            <span class="badge badge-warning">Expirado</span>
                                        <?php elseif ($maxed): ?>
                                            <span class="badge badge-warning">Esgotado</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">Ativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($codigo['data_criacao'])) ?></td>
                                    <td>
                                        <a href="?action=edit&id=<?= $codigo['id'] ?>" class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?action=toggle&id=<?= $codigo['id'] ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-toggle-on"></i>
                                        </a>
                                        <a href="?action=delete&id=<?= $codigo['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Tem certeza?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Paginação -->
                    <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?page=<?= $i ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Histórico Recente -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Resgates Recentes</h2>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Usuário</th>
                                    <th>Código</th>
                                    <th>Valor</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resgates as $resgate): ?>
                                <tr>
                                    <td><?= htmlspecialchars($resgate['usuario_nome'] ?? 'Usuário #' . $resgate['user_id']) ?></td>
                                    <td>
                                        <div class="codigo-display" style="font-size: 0.75rem;"><?= htmlspecialchars($resgate['codigo']) ?></div>
                                    </td>
                                    <td>R$ <?= number_format($resgate['valor'], 2, ',', '.') ?></td>
                                    <td><?= date('d/m H:i', strtotime($resgate['data_resgate'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Adicionar -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Novo Código de Bônus</h3>
                <button class="close" onclick="closeModal('addModal')">&times;</button>
            </div>
            <form method="POST" action="?action=add">
                <div class="form-group">
                    <label class="form-label">Código (deixe vazio para gerar automaticamente)</label>
                    <input type="text" name="codigo" class="form-input" placeholder="Ex: BONUS2024">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Valor (R$)</label>
                    <input type="number" name="valor" class="form-input" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Descrição</label>
                    <input type="text" name="descricao" class="form-input" placeholder="Descrição do código">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Máximo de Usos (0 = ilimitado)</label>
                    <input type="number" name="max_usos" class="form-input" value="1" min="0">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Data de Expiração (opcional)</label>
                    <input type="datetime-local" name="data_expiracao" class="form-input">
                </div>
                
                <div class="form-group">
                    <label class="form-checkbox">
                        <input type="checkbox" name="ativo" checked>
                        Ativo
                    </label>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Criar Código
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Gerar em Lote -->
    <div id="bulkModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Gerar Códigos em Lote</h3>
                <button class="close" onclick="closeModal('bulkModal')">&times;</button>
            </div>
            <form method="POST" action="?action=generate_bulk">
                <div class="form-group">
                    <label class="form-label">Quantidade de Códigos</label>
                    <input type="number" name="quantidade" class="form-input" value="10" min="1" max="100" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Valor de Cada Código (R$)</label>
                    <input type="number" name="valor" class="form-input" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Máximo de Usos por Código</label>
                    <input type="number" name="max_usos" class="form-input" value="1" min="1">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Prefixo dos Códigos</label>
                    <input type="text" name="prefixo" class="form-input" value="BONUS" placeholder="BONUS">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-magic"></i>
                        Gerar Códigos
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar -->
    <?php if ($editItem): ?>
    <div id="editModal" class="modal" style="display: block;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Editar Código</h3>
                <button class="close" onclick="window.location.href='?'">&times;</button>
            </div>
            <form method="POST" action="?action=edit&id=<?= $editItem['id'] ?>">
                <div class="form-group">
                    <label class="form-label">Código</label>
                    <input type="text" class="form-input" value="<?= htmlspecialchars($editItem['codigo']) ?>" readonly style="background: rgba(255, 255, 255, 0.05);">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Valor (R$)</label>
                    <input type="number" name="valor" class="form-input" step="0.01" min="0" value="<?= $editItem['valor'] ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Descrição</label>
                    <input type="text" name="descricao" class="form-input" value="<?= htmlspecialchars($editItem['descricao']) ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Máximo de Usos (0 = ilimitado)</label>
                    <input type="number" name="max_usos" class="form-input" value="<?= $editItem['max_usos'] ?>" min="0">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Data de Expiração (opcional)</label>
                    <input type="datetime-local" name="data_expiracao" class="form-input" value="<?= $editItem['data_expiracao'] ? date('Y-m-d\TH:i', strtotime($editItem['data_expiracao'])) : '' ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-checkbox">
                        <input type="checkbox" name="ativo" <?= $editItem['ativo'] ? 'checked' : '' ?>>
                        Ativo
                    </label>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Atualizar Código
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Fechar modal clicando fora
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let modal of modals) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            }
        }
    </script>
</body>
</html>