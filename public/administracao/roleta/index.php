<?php
/**
 * ========================================
 * FINVER PRO - ADMINISTRAÇÃO DA ROLETA
 * Módulo Completo de Gerenciamento da Roleta
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
                $db->query("INSERT INTO roleta (nome, tipo_premio, valor_premio, percentual_desconto, produto_id, probabilidade, cor, icone, ativo, ordem) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", [
                    $_POST['nome'],
                    $_POST['tipo_premio'],
                    floatval($_POST['valor_premio'] ?? 0),
                    floatval($_POST['percentual_desconto'] ?? 0),
                    $_POST['produto_id'] ?: null,
                    floatval($_POST['probabilidade']),
                    $_POST['cor'],
                    $_POST['icone'],
                    isset($_POST['ativo']) ? 1 : 0,
                    intval($_POST['ordem'])
                ]);
                
                logAdminAction('roleta.add', 'Novo prêmio da roleta adicionado: ' . $_POST['nome']);
                $success = "Prêmio adicionado com sucesso!";
            } catch (Exception $e) {
                $error = "Erro ao adicionar prêmio: " . $e->getMessage();
            }
        }
        break;
        
    case 'edit':
        $id = intval($_GET['id']);
        if ($_POST) {
            try {
                $db->query("UPDATE roleta SET nome = ?, tipo_premio = ?, valor_premio = ?, percentual_desconto = ?, produto_id = ?, probabilidade = ?, cor = ?, icone = ?, ativo = ?, ordem = ? WHERE id = ?", [
                    $_POST['nome'],
                    $_POST['tipo_premio'],
                    floatval($_POST['valor_premio'] ?? 0),
                    floatval($_POST['percentual_desconto'] ?? 0),
                    $_POST['produto_id'] ?: null,
                    floatval($_POST['probabilidade']),
                    $_POST['cor'],
                    $_POST['icone'],
                    isset($_POST['ativo']) ? 1 : 0,
                    intval($_POST['ordem']),
                    $id
                ]);
                
                logAdminAction('roleta.edit', 'Prêmio da roleta editado: ID ' . $id);
                $success = "Prêmio atualizado com sucesso!";
            } catch (Exception $e) {
                $error = "Erro ao atualizar prêmio: " . $e->getMessage();
            }
        }
        break;
        
    case 'delete':
        $id = intval($_GET['id']);
        try {
            $db->query("DELETE FROM roleta WHERE id = ?", [$id]);
            logAdminAction('roleta.delete', 'Prêmio da roleta excluído: ID ' . $id);
            $success = "Prêmio excluído com sucesso!";
        } catch (Exception $e) {
            $error = "Erro ao excluir prêmio: " . $e->getMessage();
        }
        break;
        
    case 'toggle':
        $id = intval($_GET['id']);
        try {
            $db->query("UPDATE roleta SET ativo = !ativo WHERE id = ?", [$id]);
            logAdminAction('roleta.toggle', 'Status do prêmio alterado: ID ' . $id);
            $success = "Status alterado com sucesso!";
        } catch (Exception $e) {
            $error = "Erro ao alterar status: " . $e->getMessage();
        }
        break;
}

// Buscar dados
$premios = $db->fetchAll("SELECT r.*, p.titulo as produto_nome FROM roleta r LEFT JOIN produtos p ON r.produto_id = p.id ORDER BY r.ordem ASC, r.id DESC");
$produtos = $db->fetchAll("SELECT id, titulo FROM produtos WHERE status = 'ativo' ORDER BY titulo");

// Estatísticas
$stats = [
    'total_premios' => $db->fetchOne("SELECT COUNT(*) as total FROM roleta")['total'],
    'premios_ativos' => $db->fetchOne("SELECT COUNT(*) as total FROM roleta WHERE ativo = 1")['total'],
    'total_giros_hoje' => $db->fetchOne("SELECT COUNT(*) as total FROM roleta_historico WHERE DATE(data_giro) = CURDATE()")['total'],
    'valor_total_premios' => $db->fetchOne("SELECT SUM(valor_ganho) as total FROM roleta_historico WHERE DATE(data_giro) = CURDATE()")['total'] ?? 0
];

// Histórico recente
$historico = $db->fetchAll("SELECT rh.*, u.nome as usuario_nome FROM roleta_historico rh LEFT JOIN usuarios u ON rh.user_id = u.id ORDER BY rh.data_giro DESC LIMIT 10");

$editItem = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $editItem = $db->fetchOne("SELECT * FROM roleta WHERE id = ?", [intval($_GET['id'])]);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administração da Roleta - Finver Pro</title>
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
            justify-content: between;
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
        
        .color-preview {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-block;
            border: 2px solid rgba(255, 255, 255, 0.3);
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
                        <a href="../roleta/" class="nav-link active">
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
                        <a href="../codigos/" class="nav-link">
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
                <h1 class="page-title">🎰 Administração da Roleta</h1>
                <button class="btn btn-primary" onclick="openModal('addModal')">
                    <i class="fas fa-plus"></i>
                    Adicionar Prêmio
                </button>
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
                    <div class="stat-value"><?= number_format($stats['total_premios']) ?></div>
                    <div class="stat-label">Total de Prêmios</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #3B82F6, #1D4ED8);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['premios_ativos']) ?></div>
                    <div class="stat-label">Prêmios Ativos</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #F59E0B, #D97706);">
                        <i class="fas fa-dice"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['total_giros_hoje']) ?></div>
                    <div class="stat-label">Giros Hoje</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #EF4444, #DC2626);">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="stat-value">R$ <?= number_format($stats['valor_total_premios'], 2, ',', '.') ?></div>
                    <div class="stat-label">Valor Premiado Hoje</div>
                </div>
            </div>

            <!-- Conteúdo Principal -->
            <div class="content-grid">
                <!-- Lista de Prêmios -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Prêmios da Roleta</h2>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Ordem</th>
                                    <th>Nome</th>
                                    <th>Tipo</th>
                                    <th>Valor</th>
                                    <th>Probabilidade</th>
                                    <th>Cor</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($premios as $premio): ?>
                                <tr>
                                    <td><?= $premio['ordem'] ?></td>
                                    <td>
                                        <i class="<?= $premio['icone'] ?>"></i>
                                        <?= htmlspecialchars($premio['nome']) ?>
                                    </td>
                                    <td>
                                        <?php
                                        $tipos = [
                                            'dinheiro' => 'Dinheiro',
                                            'bonus' => 'Bônus',
                                            'produto' => 'Produto',
                                            'desconto' => 'Desconto'
                                        ];
                                        echo $tipos[$premio['tipo_premio']] ?? $premio['tipo_premio'];
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($premio['tipo_premio'] === 'dinheiro' || $premio['tipo_premio'] === 'bonus'): ?>
                                            R$ <?= number_format($premio['valor_premio'], 2, ',', '.') ?>
                                        <?php elseif ($premio['tipo_premio'] === 'desconto'): ?>
                                            <?= $premio['percentual_desconto'] ?>%
                                        <?php elseif ($premio['tipo_premio'] === 'produto'): ?>
                                            <?= $premio['produto_nome'] ?? 'Produto não encontrado' ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $premio['probabilidade'] ?>%</td>
                                    <td>
                                        <span class="color-preview" style="background-color: <?= $premio['cor'] ?>"></span>
                                    </td>
                                    <td>
                                        <?php if ($premio['ativo']): ?>
                                            <span class="badge badge-success">Ativo</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="?action=edit&id=<?= $premio['id'] ?>" class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?action=toggle&id=<?= $premio['id'] ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-toggle-on"></i>
                                        </a>
                                        <a href="?action=delete&id=<?= $premio['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Tem certeza?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Histórico Recente -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Histórico Recente</h2>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Usuário</th>
                                    <th>Prêmio</th>
                                    <th>Valor</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historico as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['usuario_nome'] ?? 'Usuário #' . $item['user_id']) ?></td>
                                    <td><?= htmlspecialchars($item['premio_nome']) ?></td>
                                    <td>R$ <?= number_format($item['valor_ganho'], 2, ',', '.') ?></td>
                                    <td><?= date('d/m H:i', strtotime($item['data_giro'])) ?></td>
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
                <h3 class="modal-title">Adicionar Prêmio</h3>
                <button class="close" onclick="closeModal('addModal')">&times;</button>
            </div>
            <form method="POST" action="?action=add">
                <div class="form-group">
                    <label class="form-label">Nome do Prêmio</label>
                    <input type="text" name="nome" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Tipo de Prêmio</label>
                    <select name="tipo_premio" class="form-select" required onchange="togglePremioFields(this)">
                        <option value="">Selecione</option>
                        <option value="dinheiro">Dinheiro</option>
                        <option value="bonus">Bônus</option>
                        <option value="produto">Produto</option>
                        <option value="desconto">Desconto</option>
                    </select>
                </div>
                
                <div class="form-group" id="valor_premio_group">
                    <label class="form-label">Valor do Prêmio (R$)</label>
                    <input type="number" name="valor_premio" class="form-input" step="0.01" min="0">
                </div>
                
                <div class="form-group" id="percentual_desconto_group" style="display: none;">
                    <label class="form-label">Percentual de Desconto (%)</label>
                    <input type="number" name="percentual_desconto" class="form-input" step="0.01" min="0" max="100">
                </div>
                
                <div class="form-group" id="produto_id_group" style="display: none;">
                    <label class="form-label">Produto</label>
                    <select name="produto_id" class="form-select">
                        <option value="">Selecione um produto</option>
                        <?php foreach ($produtos as $produto): ?>
                            <option value="<?= $produto['id'] ?>"><?= htmlspecialchars($produto['titulo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Probabilidade (%)</label>
                    <input type="number" name="probabilidade" class="form-input" step="0.01" min="0" max="100" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Cor</label>
                    <input type="color" name="cor" class="form-input" value="#FF0000">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Ícone (FontAwesome)</label>
                    <input type="text" name="icone" class="form-input" value="fas fa-gift" placeholder="fas fa-gift">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Ordem de Exibição</label>
                    <input type="number" name="ordem" class="form-input" value="0" min="0">
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
                        Salvar Prêmio
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
                <h3 class="modal-title">Editar Prêmio</h3>
                <button class="close" onclick="window.location.href='?'">&times;</button>
            </div>
            <form method="POST" action="?action=edit&id=<?= $editItem['id'] ?>">
                <div class="form-group">
                    <label class="form-label">Nome do Prêmio</label>
                    <input type="text" name="nome" class="form-input" value="<?= htmlspecialchars($editItem['nome']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Tipo de Prêmio</label>
                    <select name="tipo_premio" class="form-select" required onchange="togglePremioFields(this)">
                        <option value="">Selecione</option>
                        <option value="dinheiro" <?= $editItem['tipo_premio'] === 'dinheiro' ? 'selected' : '' ?>>Dinheiro</option>
                        <option value="bonus" <?= $editItem['tipo_premio'] === 'bonus' ? 'selected' : '' ?>>Bônus</option>
                        <option value="produto" <?= $editItem['tipo_premio'] === 'produto' ? 'selected' : '' ?>>Produto</option>
                        <option value="desconto" <?= $editItem['tipo_premio'] === 'desconto' ? 'selected' : '' ?>>Desconto</option>
                    </select>
                </div>
                
                <div class="form-group" id="valor_premio_group">
                    <label class="form-label">Valor do Prêmio (R$)</label>
                    <input type="number" name="valor_premio" class="form-input" step="0.01" min="0" value="<?= $editItem['valor_premio'] ?>">
                </div>
                
                <div class="form-group" id="percentual_desconto_group" style="display: <?= $editItem['tipo_premio'] === 'desconto' ? 'block' : 'none' ?>;">
                    <label class="form-label">Percentual de Desconto (%)</label>
                    <input type="number" name="percentual_desconto" class="form-input" step="0.01" min="0" max="100" value="<?= $editItem['percentual_desconto'] ?>">
                </div>
                
                <div class="form-group" id="produto_id_group" style="display: <?= $editItem['tipo_premio'] === 'produto' ? 'block' : 'none' ?>;">
                    <label class="form-label">Produto</label>
                    <select name="produto_id" class="form-select">
                        <option value="">Selecione um produto</option>
                        <?php foreach ($produtos as $produto): ?>
                            <option value="<?= $produto['id'] ?>" <?= $editItem['produto_id'] == $produto['id'] ? 'selected' : '' ?>><?= htmlspecialchars($produto['titulo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Probabilidade (%)</label>
                    <input type="number" name="probabilidade" class="form-input" step="0.01" min="0" max="100" value="<?= $editItem['probabilidade'] ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Cor</label>
                    <input type="color" name="cor" class="form-input" value="<?= $editItem['cor'] ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Ícone (FontAwesome)</label>
                    <input type="text" name="icone" class="form-input" value="<?= htmlspecialchars($editItem['icone']) ?>" placeholder="fas fa-gift">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Ordem de Exibição</label>
                    <input type="number" name="ordem" class="form-input" value="<?= $editItem['ordem'] ?>" min="0">
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
                        Atualizar Prêmio
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
        
        function togglePremioFields(select) {
            const valorGroup = document.getElementById('valor_premio_group');
            const percentualGroup = document.getElementById('percentual_desconto_group');
            const produtoGroup = document.getElementById('produto_id_group');
            
            // Ocultar todos
            valorGroup.style.display = 'none';
            percentualGroup.style.display = 'none';
            produtoGroup.style.display = 'none';
            
            // Mostrar conforme o tipo
            switch(select.value) {
                case 'dinheiro':
                case 'bonus':
                    valorGroup.style.display = 'block';
                    break;
                case 'desconto':
                    percentualGroup.style.display = 'block';
                    break;
                case 'produto':
                    produtoGroup.style.display = 'block';
                    break;
            }
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