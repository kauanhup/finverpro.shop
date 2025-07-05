<?php
/**
 * ========================================
 * FINVER PRO - ADMINISTRAÇÃO DE AFILIADOS
 * Módulo Completo de Gestão de Afiliados e Comissões
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
    case 'pay_commission':
        $comissaoId = intval($_GET['id']);
        try {
            $comissao = $db->fetchOne("SELECT c.*, u.nome FROM comissoes c LEFT JOIN usuarios u ON c.usuario_id = u.id WHERE c.id = ?", [$comissaoId]);
            
            if ($comissao && $comissao['status'] === 'pendente') {
                // Atualizar status da comissão
                $db->query("UPDATE comissoes SET status = 'processado', data_pagamento = NOW() WHERE id = ?", [$comissaoId]);
                
                // Adicionar valor à carteira do usuário
                $db->query("UPDATE carteiras SET saldo_comissao = saldo_comissao + ? WHERE usuario_id = ?", 
                    [$comissao['valor_comissao'], $comissao['usuario_id']]);
                
                logAdminAction('commission.pay', 'Comissão paga: R$ ' . $comissao['valor_comissao'] . ' para ' . $comissao['nome']);
                $success = "Comissão paga com sucesso!";
            }
        } catch (Exception $e) {
            $error = "Erro ao pagar comissão: " . $e->getMessage();
        }
        break;
        
    case 'reject_commission':
        $comissaoId = intval($_GET['id']);
        try {
            $db->query("UPDATE comissoes SET status = 'rejeitado' WHERE id = ?", [$comissaoId]);
            logAdminAction('commission.reject', 'Comissão rejeitada: ID ' . $comissaoId);
            $success = "Comissão rejeitada!";
        } catch (Exception $e) {
            $error = "Erro ao rejeitar comissão: " . $e->getMessage();
        }
        break;
}

// Filtros
$statusFilter = $_GET['status'] ?? '';
$whereClause = '';
$params = [];

if ($statusFilter) {
    $whereClause = 'WHERE c.status = ?';
    $params[] = $statusFilter;
}

// Buscar dados
$page = intval($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// Estatísticas
$stats = [
    'total_afiliados' => $db->fetchOne("SELECT COUNT(DISTINCT u.id) as total FROM usuarios u INNER JOIN indicacoes i ON u.id = i.indicador_id")['total'],
    'comissoes_pendentes' => $db->fetchOne("SELECT COUNT(*) as total FROM comissoes WHERE status = 'pendente'")['total'],
    'valor_comissoes_mes' => $db->fetchOne("SELECT COALESCE(SUM(valor_comissao), 0) as total FROM comissoes WHERE status = 'processado' AND MONTH(created_at) = MONTH(CURRENT_DATE) AND YEAR(created_at) = YEAR(CURRENT_DATE)")['total'],
    'total_indicacoes' => $db->fetchOne("SELECT COUNT(*) as total FROM indicacoes")['total']
];

// Top afiliados
$topAfiliados = $db->fetchAll("SELECT u.id, u.nome, u.telefone, COUNT(i.id) as total_indicacoes, COALESCE(SUM(c.valor_comissao), 0) as total_comissoes FROM usuarios u LEFT JOIN indicacoes i ON u.id = i.indicador_id LEFT JOIN comissoes c ON u.id = c.usuario_id AND c.status = 'processado' GROUP BY u.id HAVING total_indicacoes > 0 ORDER BY total_comissoes DESC LIMIT 10");

// Comissões pendentes
$comissoesPendentes = $db->fetchAll("SELECT c.*, u.nome as usuario_nome, u.telefone FROM comissoes c LEFT JOIN usuarios u ON c.usuario_id = u.id $whereClause ORDER BY c.created_at DESC LIMIT ? OFFSET ?", array_merge($params, [$limit, $offset]));

$totalComissoes = $db->fetchOne("SELECT COUNT(*) as total FROM comissoes c $whereClause", $params)['total'];
$totalPages = ceil($totalComissoes / $limit);

// Indicações recentes
$indicacoesRecentes = $db->fetchAll("SELECT i.*, u1.nome as indicador_nome, u2.nome as indicado_nome FROM indicacoes i LEFT JOIN usuarios u1 ON i.indicador_id = u1.id LEFT JOIN usuarios u2 ON i.indicado_id = u2.id ORDER BY i.data_indicacao DESC LIMIT 10");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administração de Afiliados - Finver Pro</title>
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
            grid-template-columns: 2fr 1fr;
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
        
        .filters {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .filter-select {
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius);
            color: var(--text-color);
            font-size: 0.875rem;
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
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
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
        
        .badge-danger {
            background: rgba(239, 68, 68, 0.2);
            color: #EF4444;
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
                        <a href="../codigos/" class="nav-link">
                            <i class="fas fa-gift"></i>
                            <span>Códigos</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../afiliados/" class="nav-link active">
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
                <h1 class="page-title">👥 Administração de Afiliados</h1>
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
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['total_afiliados']) ?></div>
                    <div class="stat-label">Total de Afiliados</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #F59E0B, #D97706);">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['comissoes_pendentes']) ?></div>
                    <div class="stat-label">Comissões Pendentes</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #3B82F6, #1D4ED8);">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="stat-value">R$ <?= number_format($stats['valor_comissoes_mes'], 2, ',', '.') ?></div>
                    <div class="stat-label">Comissões deste Mês</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #EF4444, #DC2626);">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['total_indicacoes']) ?></div>
                    <div class="stat-label">Total de Indicações</div>
                </div>
            </div>

            <!-- Conteúdo Principal -->
            <div class="content-grid">
                <!-- Comissões -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Comissões</h2>
                    </div>
                    
                    <!-- Filtros -->
                    <form method="GET" class="filters">
                        <select name="status" class="filter-select">
                            <option value="">Todos os Status</option>
                            <option value="pendente" <?= $statusFilter === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                            <option value="processado" <?= $statusFilter === 'processado' ? 'selected' : '' ?>>Processado</option>
                            <option value="rejeitado" <?= $statusFilter === 'rejeitado' ? 'selected' : '' ?>>Rejeitado</option>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
                    </form>
                    
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Afiliado</th>
                                    <th>Valor</th>
                                    <th>Nível</th>
                                    <th>Status</th>
                                    <th>Data</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($comissoesPendentes as $comissao): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($comissao['usuario_nome'] ?: 'Usuário') ?></strong>
                                            <br>
                                            <small style="color: rgba(255, 255, 255, 0.7);"><?= $comissao['telefone'] ?></small>
                                        </div>
                                    </td>
                                    <td>R$ <?= number_format($comissao['valor_comissao'], 2, ',', '.') ?></td>
                                    <td>Nível <?= $comissao['nivel_comissao'] ?></td>
                                    <td>
                                        <?php
                                        $badgeClass = match($comissao['status']) {
                                            'processado' => 'badge-success',
                                            'pendente' => 'badge-warning',
                                            'rejeitado' => 'badge-danger',
                                            default => 'badge-warning'
                                        };
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= ucfirst($comissao['status']) ?></span>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($comissao['created_at'])) ?></td>
                                    <td>
                                        <?php if ($comissao['status'] === 'pendente'): ?>
                                            <a href="?action=pay_commission&id=<?= $comissao['id'] ?>" class="btn btn-success btn-sm" onclick="return confirm('Pagar esta comissão?')">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <a href="?action=reject_commission&id=<?= $comissao['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Rejeitar esta comissão?')">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        <?php endif; ?>
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
                            <a href="?page=<?= $i ?>&status=<?= $statusFilter ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Top Afiliados -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Top Afiliados</h2>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Afiliado</th>
                                    <th>Indicações</th>
                                    <th>Comissões</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topAfiliados as $afiliado): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($afiliado['nome'] ?: 'Usuário') ?></strong>
                                            <br>
                                            <small style="color: rgba(255, 255, 255, 0.7);"><?= $afiliado['telefone'] ?></small>
                                        </div>
                                    </td>
                                    <td><?= number_format($afiliado['total_indicacoes']) ?></td>
                                    <td>R$ <?= number_format($afiliado['total_comissoes'], 2, ',', '.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Indicações Recentes -->
            <div class="card" style="margin-top: 2rem;">
                <div class="card-header">
                    <h2 class="card-title">Indicações Recentes</h2>
                </div>
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Indicador</th>
                                <th>Indicado</th>
                                <th>Data</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($indicacoesRecentes as $indicacao): ?>
                            <tr>
                                <td><?= htmlspecialchars($indicacao['indicador_nome'] ?: 'Usuário') ?></td>
                                <td><?= htmlspecialchars($indicacao['indicado_nome'] ?: 'Usuário') ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($indicacao['data_indicacao'])) ?></td>
                                <td>
                                    <span class="badge badge-success">Ativa</span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>