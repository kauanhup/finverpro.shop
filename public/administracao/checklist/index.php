<?php
/**
 * ========================================
 * FINVER PRO - ADMINISTRAÇÃO DO CHECKLIST
 * Módulo Completo de Gerenciamento do Checklist Diário
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
    case 'update_config':
        if ($_POST) {
            try {
                // Atualizar configurações do checklist
                $db->query("UPDATE checklist SET 
                    valor_dia1 = ?, valor_dia2 = ?, valor_dia3 = ?, valor_dia4 = ?, 
                    valor_dia5 = ?, valor_dia6 = ?, valor_dia7 = ?
                    WHERE user_id = 0 AND tarefa = 'CONFIG_VALORES'", [
                    floatval($_POST['valor_dia1']),
                    floatval($_POST['valor_dia2']),
                    floatval($_POST['valor_dia3']),
                    floatval($_POST['valor_dia4']),
                    floatval($_POST['valor_dia5']),
                    floatval($_POST['valor_dia6']),
                    floatval($_POST['valor_dia7'])
                ]);
                
                logAdminAction('checklist.config', 'Configurações do checklist atualizadas');
                $success = "Configurações atualizadas com sucesso!";
            } catch (Exception $e) {
                $error = "Erro ao atualizar configurações: " . $e->getMessage();
            }
        }
        break;
        
    case 'reset_user':
        $userId = intval($_GET['user_id']);
        try {
            $db->query("UPDATE usuarios SET checklist = 0, data_checklist = NULL WHERE id = ?", [$userId]);
            logAdminAction('checklist.reset', 'Checklist resetado para usuário: ' . $userId);
            $success = "Checklist resetado com sucesso!";
        } catch (Exception $e) {
            $error = "Erro ao resetar checklist: " . $e->getMessage();
        }
        break;
        
    case 'complete_day':
        $userId = intval($_GET['user_id']);
        $dia = intval($_GET['dia']);
        try {
            // Buscar configuração atual
            $config = $db->fetchOne("SELECT valor_dia1, valor_dia2, valor_dia3, valor_dia4, valor_dia5, valor_dia6, valor_dia7 FROM checklist WHERE user_id = 0 AND tarefa = 'CONFIG_VALORES' LIMIT 1");
            $valorDia = $config["valor_dia{$dia}"] ?? 0;
            
            // Atualizar usuário
            $db->query("UPDATE usuarios SET 
                checklist = ?, 
                data_checklist = CURDATE(),
                saldo = saldo + ?
                WHERE id = ?", [$dia, $valorDia, $userId]);
                
            logAdminAction('checklist.complete', "Dia {$dia} do checklist completado para usuário: {$userId}");
            $success = "Dia {$dia} completado com sucesso! Valor adicionado: R$ " . number_format($valorDia, 2, ',', '.');
        } catch (Exception $e) {
            $error = "Erro ao completar dia: " . $e->getMessage();
        }
        break;
}

// Buscar configurações atuais
$config = $db->fetchOne("SELECT valor_dia1, valor_dia2, valor_dia3, valor_dia4, valor_dia5, valor_dia6, valor_dia7 FROM checklist WHERE user_id = 0 AND tarefa = 'CONFIG_VALORES' LIMIT 1");

// Se não existir configuração, criar uma padrão
if (!$config) {
    $db->query("INSERT INTO checklist (user_id, tarefa, concluida, recompensa, valor_dia1, valor_dia2, valor_dia3, valor_dia4, valor_dia5, valor_dia6, valor_dia7) VALUES (0, 'CONFIG_VALORES', 0, 0.00, 1.00, 2.00, 3.00, 5.00, 8.00, 15.00, 25.00)");
    $config = $db->fetchOne("SELECT valor_dia1, valor_dia2, valor_dia3, valor_dia4, valor_dia5, valor_dia6, valor_dia7 FROM checklist WHERE user_id = 0 AND tarefa = 'CONFIG_VALORES' LIMIT 1");
}

// Estatísticas
$stats = [
    'usuarios_ativos_hoje' => $db->fetchOne("SELECT COUNT(*) as total FROM usuarios WHERE data_checklist = CURDATE()")['total'],
    'total_valor_distribuido_hoje' => $db->fetchOne("SELECT SUM(CASE 
        WHEN checklist = 1 THEN ? 
        WHEN checklist = 2 THEN ? 
        WHEN checklist = 3 THEN ? 
        WHEN checklist = 4 THEN ? 
        WHEN checklist = 5 THEN ? 
        WHEN checklist = 6 THEN ? 
        WHEN checklist = 7 THEN ? 
        ELSE 0 END) as total 
        FROM usuarios WHERE data_checklist = CURDATE()", [
        $config['valor_dia1'], $config['valor_dia2'], $config['valor_dia3'], 
        $config['valor_dia4'], $config['valor_dia5'], $config['valor_dia6'], $config['valor_dia7']
    ])['total'] ?? 0,
    'usuarios_ciclo_completo' => $db->fetchOne("SELECT COUNT(*) as total FROM usuarios WHERE checklist >= 6")['total'],
    'media_dias_completados' => $db->fetchOne("SELECT AVG(checklist) as media FROM usuarios WHERE checklist > 0")['media'] ?? 0
];

// Relatório de usuários
$page = intval($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

$usuarios = $db->fetchAll("SELECT u.id, u.nome, u.telefone, u.checklist, u.data_checklist, c.saldo_principal 
    FROM usuarios u 
    LEFT JOIN carteiras c ON u.id = c.usuario_id 
    WHERE u.checklist > 0 OR u.data_checklist IS NOT NULL
    ORDER BY u.checklist DESC, u.data_checklist DESC 
    LIMIT ? OFFSET ?", [$limit, $offset]);

$totalUsuarios = $db->fetchOne("SELECT COUNT(*) as total FROM usuarios WHERE u.checklist > 0 OR u.data_checklist IS NOT NULL")['total'];
$totalPages = ceil($totalUsuarios / $limit);

// Relatório por dia da semana
$relatorioDias = [];
for ($i = 1; $i <= 7; $i++) {
    $relatorioDias[$i] = $db->fetchOne("SELECT COUNT(*) as total FROM usuarios WHERE checklist >= ?", [$i])['total'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administração do Checklist - Finver Pro</title>
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
            grid-template-columns: 1fr 1fr;
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
        
        .checklist-preview {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.5rem;
            margin: 1rem 0;
        }
        
        .checklist-day {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius);
            padding: 1rem;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .checklist-day:hover {
            background: rgba(255, 255, 255, 0.15);
        }
        
        .day-number {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .day-value {
            font-size: 0.875rem;
            color: var(--success-color);
            font-weight: 600;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            overflow: hidden;
            margin: 0.5rem 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--success-color), var(--info-color));
            transition: width 0.3s ease;
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
            .checklist-preview { grid-template-columns: repeat(3, 1fr); }
        }
        
        @media (max-width: 768px) {
            .checklist-preview { grid-template-columns: 1fr; }
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
                        <a href="../checklist/" class="nav-link active">
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
                <h1 class="page-title">📋 Administração do Checklist</h1>
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
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['usuarios_ativos_hoje']) ?></div>
                    <div class="stat-label">Usuários Ativos Hoje</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #3B82F6, #1D4ED8);">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="stat-value">R$ <?= number_format($stats['total_valor_distribuido_hoje'], 2, ',', '.') ?></div>
                    <div class="stat-label">Valor Distribuído Hoje</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #F59E0B, #D97706);">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['usuarios_ciclo_completo']) ?></div>
                    <div class="stat-label">Ciclos Completos</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #EF4444, #DC2626);">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['media_dias_completados'], 1) ?></div>
                    <div class="stat-label">Média de Dias</div>
                </div>
            </div>

            <!-- Conteúdo Principal -->
            <div class="content-grid">
                <!-- Configurações -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Configurações do Checklist</h2>
                    </div>
                    
                    <form method="POST" action="?action=update_config">
                        <p style="margin-bottom: 1.5rem; color: rgba(255, 255, 255, 0.7);">
                            Configure os valores que serão distribuídos para cada dia do checklist:
                        </p>
                        
                        <div class="checklist-preview">
                            <?php for ($i = 1; $i <= 7; $i++): ?>
                            <div class="checklist-day">
                                <div class="day-number">Dia <?= $i ?></div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <input type="number" 
                                           name="valor_dia<?= $i ?>" 
                                           class="form-input" 
                                           value="<?= $config["valor_dia{$i}"] ?>" 
                                           step="0.01" 
                                           min="0" 
                                           style="text-align: center; font-size: 0.875rem;">
                                </div>
                            </div>
                            <?php endfor; ?>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                Salvar Configurações
                            </button>
                        </div>
                    </form>
                    
                    <!-- Relatório por Dia -->
                    <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid rgba(255, 255, 255, 0.1);">
                        <h3 style="margin-bottom: 1rem;">Usuários por Dia</h3>
                        <?php for ($i = 1; $i <= 7; $i++): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <span>Dia <?= $i ?>:</span>
                            <div style="flex: 1; margin: 0 1rem;">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= $stats['usuarios_ciclo_completo'] > 0 ? ($relatorioDias[$i] / $stats['usuarios_ciclo_completo']) * 100 : 0 ?>%"></div>
                                </div>
                            </div>
                            <span style="font-weight: 600;"><?= $relatorioDias[$i] ?> usuários</span>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Lista de Usuários -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Usuários com Checklist</h2>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Usuário</th>
                                    <th>Dia Atual</th>
                                    <th>Última Atividade</th>
                                    <th>Saldo</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $usuario): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($usuario['nome'] ?: 'Usuário #' . $usuario['id']) ?></strong>
                                            <br>
                                            <small style="color: rgba(255, 255, 255, 0.7);"><?= $usuario['telefone'] ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($usuario['checklist'] == 0): ?>
                                            <span class="badge badge-warning">Não iniciado</span>
                                        <?php elseif ($usuario['checklist'] >= 7): ?>
                                            <span class="badge badge-success">Completo</span>
                                        <?php else: ?>
                                            <span class="badge badge-info">Dia <?= $usuario['checklist'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $usuario['data_checklist'] ? date('d/m/Y', strtotime($usuario['data_checklist'])) : 'Nunca' ?>
                                    </td>
                                    <td>R$ <?= number_format($usuario['saldo_principal'] ?? 0, 2, ',', '.') ?></td>
                                    <td>
                                        <?php if ($usuario['checklist'] < 7): ?>
                                            <a href="?action=complete_day&user_id=<?= $usuario['id'] ?>&dia=<?= $usuario['checklist'] + 1 ?>" 
                                               class="btn btn-success btn-sm" 
                                               onclick="return confirm('Completar dia <?= $usuario['checklist'] + 1 ?> para este usuário?')">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="?action=reset_user&user_id=<?= $usuario['id'] ?>" 
                                           class="btn btn-warning btn-sm" 
                                           onclick="return confirm('Resetar checklist para este usuário?')">
                                            <i class="fas fa-redo"></i>
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
            </div>
        </main>
    </div>
</body>
</html>