<?php
/**
 * ========================================
 * FINVER PRO - RELATÓRIOS ADMINISTRATIVOS
 * Módulo Completo de Relatórios e Analytics
 * ========================================
 */

require_once '../includes/auth.php';
require_once '../../config/database.php';

// Verificar autenticação e permissões
requireAdmin('admin');

$admin = getAdminData();
$db = Database::getInstance();

// Parâmetros de filtro
$periodo = $_GET['periodo'] ?? '30';
$dataInicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$dataFim = $_GET['data_fim'] ?? date('Y-m-d');

// Função para formatar data para SQL
function formatDateForSQL($date) {
    return date('Y-m-d', strtotime($date));
}

// Relatório Financeiro
$relatorioFinanceiro = [
    'total_depositado' => $db->fetchOne("SELECT COALESCE(SUM(valor), 0) as total FROM transacoes WHERE tipo = 'deposito' AND status = 'concluido' AND DATE(created_at) BETWEEN ? AND ?", [$dataInicio, $dataFim])['total'],
    'total_sacado' => $db->fetchOne("SELECT COALESCE(SUM(valor_liquido), 0) as total FROM saques WHERE status = 'aprovado' AND DATE(created_at) BETWEEN ? AND ?", [$dataInicio, $dataFim])['total'],
    'total_investido' => $db->fetchOne("SELECT COALESCE(SUM(valor_investido), 0) as total FROM investimentos WHERE DATE(data_investimento) BETWEEN ? AND ?", [$dataInicio, $dataFim])['total'],
    'total_rendimentos' => $db->fetchOne("SELECT COALESCE(SUM(valor), 0) as total FROM transacoes WHERE tipo = 'rendimento' AND DATE(created_at) BETWEEN ? AND ?", [$dataInicio, $dataFim])['total'],
    'total_comissoes' => $db->fetchOne("SELECT COALESCE(SUM(valor_comissao), 0) as total FROM comissoes WHERE status = 'processado' AND DATE(created_at) BETWEEN ? AND ?", [$dataInicio, $dataFim])['total']
];

// Relatório de Usuários
$relatorioUsuarios = [
    'novos_usuarios' => $db->fetchOne("SELECT COUNT(*) as total FROM usuarios WHERE DATE(created_at) BETWEEN ? AND ?", [$dataInicio, $dataFim])['total'],
    'usuarios_ativos' => $db->fetchOne("SELECT COUNT(DISTINCT usuario_id) as total FROM investimentos WHERE status = 'ativo' AND DATE(data_investimento) BETWEEN ? AND ?", [$dataInicio, $dataFim])['total'],
    'usuarios_com_saque' => $db->fetchOne("SELECT COUNT(DISTINCT usuario_id) as total FROM saques WHERE DATE(created_at) BETWEEN ? AND ?", [$dataInicio, $dataFim])['total'],
    'total_usuarios' => $db->fetchOne("SELECT COUNT(*) as total FROM usuarios")['total']
];

// Relatório de Produtos
$relatorioProdutos = $db->fetchAll("SELECT p.titulo, p.codigo_robo, COUNT(i.id) as total_vendas, SUM(i.valor_investido) as valor_total FROM produtos p LEFT JOIN investimentos i ON p.id = i.produto_id AND DATE(i.data_investimento) BETWEEN ? AND ? GROUP BY p.id ORDER BY total_vendas DESC LIMIT 10", [$dataInicio, $dataFim]);

// Dados para gráficos - Evolução diária
$evolucaoFinanceira = $db->fetchAll("SELECT DATE(created_at) as data, SUM(CASE WHEN tipo = 'deposito' AND status = 'concluido' THEN valor ELSE 0 END) as depositos, SUM(CASE WHEN tipo = 'rendimento' THEN valor ELSE 0 END) as rendimentos FROM transacoes WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY DATE(created_at) ORDER BY data", [$dataInicio, $dataFim]);

// Top 10 usuários por investimento
$topUsuarios = $db->fetchAll("SELECT u.nome, u.telefone, SUM(i.valor_investido) as total_investido, COUNT(i.id) as total_investimentos FROM usuarios u INNER JOIN investimentos i ON u.id = i.usuario_id WHERE DATE(i.data_investimento) BETWEEN ? AND ? GROUP BY u.id ORDER BY total_investido DESC LIMIT 10", [$dataInicio, $dataFim]);

// Estatísticas de saques
$estatisticasSaques = $db->fetchAll("SELECT status, COUNT(*) as quantidade, SUM(valor_bruto) as valor_total FROM saques WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY status", [$dataInicio, $dataFim]);

// Relatório de afiliados
$relatorioAfiliados = $db->fetchAll("SELECT u.nome, u.telefone, COUNT(i.id) as total_indicacoes, SUM(c.valor_comissao) as total_comissoes FROM usuarios u LEFT JOIN indicacoes i ON u.id = i.indicador_id LEFT JOIN comissoes c ON u.id = c.usuario_id AND c.status = 'processado' WHERE (DATE(i.data_indicacao) BETWEEN ? AND ? OR DATE(c.created_at) BETWEEN ? AND ?) GROUP BY u.id HAVING total_indicacoes > 0 OR total_comissoes > 0 ORDER BY total_comissoes DESC LIMIT 10", [$dataInicio, $dataFim, $dataInicio, $dataFim]);

// Preparar dados para gráficos JavaScript
$dadosGrafico = [
    'labels' => array_column($evolucaoFinanceira, 'data'),
    'depositos' => array_column($evolucaoFinanceira, 'depositos'),
    'rendimentos' => array_column($evolucaoFinanceira, 'rendimentos')
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - Finver Pro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .filters {
            display: flex;
            gap: 1rem;
            align-items: center;
            background: rgba(255, 255, 255, 0.05);
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .filter-label {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .filter-input {
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--border-radius);
            padding: 1.5rem;
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .stat-title {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 500;
        }
        
        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stat-change {
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .stat-change.positive { color: var(--success-color); }
        .stat-change.negative { color: var(--error-color); }
        
        .reports-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
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
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 1rem;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
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
            font-size: 0.875rem;
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
        
        .badge-info {
            background: rgba(59, 130, 246, 0.2);
            color: #3B82F6;
        }
        
        .export-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }
        
        .full-width {
            grid-column: 1 / -1;
        }
        
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
            .reports-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); }
            .filters { flex-direction: column; align-items: stretch; }
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
                        <a href="../configuracoes/" class="nav-link">
                            <i class="fas fa-cog"></i>
                            <span>Configurações</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../relatorios/" class="nav-link active">
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
                <h1 class="page-title">📊 Relatórios e Analytics</h1>
                <div class="export-actions">
                    <button class="btn btn-primary btn-sm" onclick="exportToCSV()">
                        <i class="fas fa-download"></i>
                        Exportar CSV
                    </button>
                </div>
            </div>

            <!-- Filtros -->
            <form method="GET" class="filters">
                <div class="filter-group">
                    <label class="filter-label">Período</label>
                    <select name="periodo" class="filter-input" onchange="this.form.submit()">
                        <option value="7" <?= $periodo == '7' ? 'selected' : '' ?>>Últimos 7 dias</option>
                        <option value="30" <?= $periodo == '30' ? 'selected' : '' ?>>Últimos 30 dias</option>
                        <option value="90" <?= $periodo == '90' ? 'selected' : '' ?>>Últimos 90 dias</option>
                        <option value="custom" <?= $periodo == 'custom' ? 'selected' : '' ?>>Personalizado</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Data Início</label>
                    <input type="date" name="data_inicio" class="filter-input" value="<?= $dataInicio ?>">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Data Fim</label>
                    <input type="date" name="data_fim" class="filter-input" value="<?= $dataFim ?>">
                </div>
                
                <div class="filter-group" style="justify-content: end;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        Filtrar
                    </button>
                </div>
            </form>

            <!-- Estatísticas Financeiras -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Total Depositado</div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #10B981, #059669);">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                    </div>
                    <div class="stat-value">R$ <?= number_format($relatorioFinanceiro['total_depositado'], 2, ',', '.') ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Total Sacado</div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #EF4444, #DC2626);">
                            <i class="fas fa-arrow-down"></i>
                        </div>
                    </div>
                    <div class="stat-value">R$ <?= number_format($relatorioFinanceiro['total_sacado'], 2, ',', '.') ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Total Investido</div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #3B82F6, #1D4ED8);">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                    <div class="stat-value">R$ <?= number_format($relatorioFinanceiro['total_investido'], 2, ',', '.') ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Rendimentos Pagos</div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #F59E0B, #D97706);">
                            <i class="fas fa-coins"></i>
                        </div>
                    </div>
                    <div class="stat-value">R$ <?= number_format($relatorioFinanceiro['total_rendimentos'], 2, ',', '.') ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Comissões Pagas</div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #8B5CF6, #7C3AED);">
                            <i class="fas fa-percentage"></i>
                        </div>
                    </div>
                    <div class="stat-value">R$ <?= number_format($relatorioFinanceiro['total_comissoes'], 2, ',', '.') ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Novos Usuários</div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #06B6D4, #0891B2);">
                            <i class="fas fa-user-plus"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($relatorioUsuarios['novos_usuarios']) ?></div>
                </div>
            </div>

            <!-- Gráficos e Relatórios -->
            <div class="reports-grid">
                <!-- Gráfico de Evolução -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Evolução Financeira</h2>
                    </div>
                    <div class="chart-container">
                        <canvas id="evolucaoChart"></canvas>
                    </div>
                </div>

                <!-- Top Usuários -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Top Investidores</h2>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Usuário</th>
                                    <th>Investido</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topUsuarios as $usuario): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($usuario['nome'] ?: 'Usuário') ?></strong>
                                            <br>
                                            <small style="color: rgba(255, 255, 255, 0.7);"><?= $usuario['telefone'] ?></small>
                                        </div>
                                    </td>
                                    <td>R$ <?= number_format($usuario['total_investido'], 2, ',', '.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Produtos Mais Vendidos -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Produtos Mais Vendidos</h2>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th>Vendas</th>
                                    <th>Valor Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($relatorioProdutos as $produto): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($produto['titulo']) ?></strong>
                                            <br>
                                            <small style="color: rgba(255, 255, 255, 0.7);"><?= $produto['codigo_robo'] ?></small>
                                        </div>
                                    </td>
                                    <td><?= number_format($produto['total_vendas']) ?></td>
                                    <td>R$ <?= number_format($produto['valor_total'] ?? 0, 2, ',', '.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Estatísticas de Saques -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Status dos Saques</h2>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Quantidade</th>
                                    <th>Valor Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($estatisticasSaques as $saque): ?>
                                <tr>
                                    <td>
                                        <?php
                                        $badgeClass = match($saque['status']) {
                                            'aprovado' => 'badge-success',
                                            'pendente' => 'badge-warning',
                                            'rejeitado' => 'badge-danger',
                                            default => 'badge-info'
                                        };
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= ucfirst($saque['status']) ?></span>
                                    </td>
                                    <td><?= number_format($saque['quantidade']) ?></td>
                                    <td>R$ <?= number_format($saque['valor_total'], 2, ',', '.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Top Afiliados -->
                <div class="card full-width">
                    <div class="card-header">
                        <h2 class="card-title">Top Afiliados</h2>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Afiliado</th>
                                    <th>Telefone</th>
                                    <th>Total Indicações</th>
                                    <th>Total Comissões</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($relatorioAfiliados as $afiliado): ?>
                                <tr>
                                    <td><?= htmlspecialchars($afiliado['nome'] ?: 'Usuário') ?></td>
                                    <td><?= $afiliado['telefone'] ?></td>
                                    <td><?= number_format($afiliado['total_indicacoes']) ?></td>
                                    <td>R$ <?= number_format($afiliado['total_comissoes'] ?? 0, 2, ',', '.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Dados para o gráfico
        const dadosGrafico = <?= json_encode($dadosGrafico) ?>;
        
        // Configurar gráfico de evolução
        const ctx = document.getElementById('evolucaoChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: dadosGrafico.labels.map(date => {
                    const d = new Date(date + 'T00:00:00');
                    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
                }),
                datasets: [{
                    label: 'Depósitos',
                    data: dadosGrafico.depositos,
                    borderColor: '#10B981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Rendimentos',
                    data: dadosGrafico.rendimentos,
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: '#FFFFFF'
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            color: '#FFFFFF'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    },
                    y: {
                        ticks: {
                            color: '#FFFFFF',
                            callback: function(value) {
                                return 'R$ ' + value.toLocaleString('pt-BR');
                            }
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    }
                },
                elements: {
                    point: {
                        radius: 4,
                        hoverRadius: 6
                    }
                }
            }
        });
        
        // Função para exportar para CSV
        function exportToCSV() {
            const data = [
                ['Período', '<?= date('d/m/Y', strtotime($dataInicio)) ?>', 'até', '<?= date('d/m/Y', strtotime($dataFim)) ?>'],
                [],
                ['RESUMO FINANCEIRO'],
                ['Total Depositado', 'R$ <?= number_format($relatorioFinanceiro['total_depositado'], 2, ',', '.') ?>'],
                ['Total Sacado', 'R$ <?= number_format($relatorioFinanceiro['total_sacado'], 2, ',', '.') ?>'],
                ['Total Investido', 'R$ <?= number_format($relatorioFinanceiro['total_investido'], 2, ',', '.') ?>'],
                ['Rendimentos Pagos', 'R$ <?= number_format($relatorioFinanceiro['total_rendimentos'], 2, ',', '.') ?>'],
                ['Comissões Pagas', 'R$ <?= number_format($relatorioFinanceiro['total_comissoes'], 2, ',', '.') ?>'],
                [],
                ['USUÁRIOS'],
                ['Novos Usuários', '<?= $relatorioUsuarios['novos_usuarios'] ?>'],
                ['Usuários Ativos', '<?= $relatorioUsuarios['usuarios_ativos'] ?>'],
                ['Total Usuários', '<?= $relatorioUsuarios['total_usuarios'] ?>']
            ];
            
            const csvContent = data.map(row => row.join(',')).join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `relatorio_finver_${new Date().toISOString().split('T')[0]}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Auto-submit do formulário quando mudar o período
        document.querySelector('select[name="periodo"]').addEventListener('change', function() {
            if (this.value !== 'custom') {
                const hoje = new Date();
                const dataFim = hoje.toISOString().split('T')[0];
                
                let dataInicio;
                switch(this.value) {
                    case '7':
                        dataInicio = new Date(hoje.getTime() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                        break;
                    case '30':
                        dataInicio = new Date(hoje.getTime() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                        break;
                    case '90':
                        dataInicio = new Date(hoje.getTime() - 90 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                        break;
                }
                
                document.querySelector('input[name="data_inicio"]').value = dataInicio;
                document.querySelector('input[name="data_fim"]').value = dataFim;
                this.form.submit();
            }
        });
    </script>
</body>
</html>