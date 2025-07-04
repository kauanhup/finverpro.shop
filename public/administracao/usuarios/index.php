<?php
/**
 * ========================================
 * FINVER PRO - GESTÃO DE USUÁRIOS
 * Módulo de Administração de Usuários
 * ========================================
 */

require_once '../includes/auth.php';
require_once '../../config/database.php';

// Verificar autenticação
requireAdmin();

$admin = getAdminData();
$db = Database::getInstance();

// Configuração de paginação
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filtros
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$orderBy = $_GET['order'] ?? 'created_at';
$orderDir = $_GET['dir'] ?? 'DESC';

// Construir query com filtros
$whereClause = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $whereClause .= " AND (u.nome LIKE ? OR u.telefone LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status)) {
    $whereClause .= " AND u.status = ?";
    $params[] = $status;
}

// Consultar usuários
try {
    // Total de registros
    $totalQuery = "SELECT COUNT(*) as total FROM usuarios u $whereClause";
    $totalResult = $db->fetchOne($totalQuery, $params);
    $totalRecords = $totalResult['total'];
    $totalPages = ceil($totalRecords / $limit);
    
    // Usuários com paginação
    $query = "
        SELECT 
            u.id,
            u.nome,
            u.telefone,
            u.email,
            u.status,
            u.created_at,
            u.ultimo_login,
            c.saldo_principal,
            c.saldo_bonus,
            c.saldo_comissao,
            c.total_depositado,
            c.total_investido,
            c.total_sacado,
            (SELECT COUNT(*) FROM usuarios WHERE indicado_por = u.id) as total_indicados,
            (SELECT COUNT(*) FROM investimentos WHERE usuario_id = u.id AND status = 'ativo') as investimentos_ativos
        FROM usuarios u
        LEFT JOIN carteiras c ON u.id = c.usuario_id
        $whereClause
        ORDER BY u.$orderBy $orderDir
        LIMIT $limit OFFSET $offset
    ";
    
    $usuarios = $db->fetchAll($query, $params);
    
} catch (Exception $e) {
    error_log("Erro ao buscar usuários: " . $e->getMessage());
    $usuarios = [];
    $totalRecords = 0;
    $totalPages = 0;
}

// Registrar acesso
logAdminAction('usuarios.list', 'Listagem de usuários acessada');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Usuários - Finver Pro</title>
    
    <!-- Fonts & Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #152731;
            --secondary-color: #335D67;
            --background-color: #121A1E;
            --text-color: #FFFFFF;
            --accent-color: #152731;
            --success-color: #10B981;
            --error-color: #EF4444;
            --warning-color: #F59E0B;
            --info-color: #3B82F6;
            --border-radius: 12px;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --sidebar-width: 280px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--background-color);
            color: var(--text-color);
            line-height: 1.6;
        }
        
        .admin-layout {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            padding: 1.5rem;
            box-shadow: var(--shadow-lg);
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
        
        .sidebar-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .sidebar-subtitle {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .sidebar-nav ul {
            list-style: none;
        }
        
        .nav-item {
            margin-bottom: 0.5rem;
        }
        
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
        
        .nav-link i {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
        }
        
        .nav-badge {
            margin-left: auto;
            background: var(--error-color);
            color: white;
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 10px;
            font-weight: 600;
        }
        
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
            background-clip: text;
        }
        
        .page-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .admin-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: rgba(255, 255, 255, 0.1);
            padding: 0.75rem 1rem;
            border-radius: var(--border-radius);
        }
        
        .admin-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--info-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        .logout-btn {
            background: var(--error-color);
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .logout-btn:hover {
            background: #DC2626;
            transform: translateY(-2px);
        }
        
        .filters-section {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .filters-form {
            display: flex;
            gap: 1rem;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .filter-label {
            font-weight: 500;
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.9);
        }
        
        .filter-input {
            padding: 0.75rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius);
            color: var(--text-color);
            font-size: 0.875rem;
            min-width: 200px;
        }
        
        .filter-input:focus {
            outline: none;
            border-color: var(--secondary-color);
            background: rgba(255, 255, 255, 0.15);
        }
        
        .filter-btn {
            padding: 0.75rem 1.5rem;
            background: var(--secondary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .filter-btn:hover {
            background: var(--primary-color);
            transform: translateY(-2px);
        }
        
        .users-table {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .table-title {
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .table-info {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.875rem;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
            white-space: nowrap;
        }
        
        .table th a {
            color: inherit;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .table th a:hover {
            color: var(--secondary-color);
        }
        
        .table td {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            vertical-align: middle;
        }
        
        .table tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-badge.ativo {
            background: rgba(16, 185, 129, 0.2);
            color: #10B981;
        }
        
        .status-badge.inativo {
            background: rgba(239, 68, 68, 0.2);
            color: #EF4444;
        }
        
        .status-badge.suspenso {
            background: rgba(245, 158, 11, 0.2);
            color: #F59E0B;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .action-btn {
            padding: 0.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .action-btn.edit {
            background: var(--info-color);
            color: white;
        }
        
        .action-btn.delete {
            background: var(--error-color);
            color: white;
        }
        
        .action-btn.view {
            background: var(--secondary-color);
            color: white;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .pagination-info {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.875rem;
        }
        
        .pagination-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .pagination-btn {
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius);
            color: var(--text-color);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .pagination-btn:hover {
            background: var(--secondary-color);
            color: white;
        }
        
        .pagination-btn.active {
            background: var(--secondary-color);
            color: white;
        }
        
        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: rgba(255, 255, 255, 0.6);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: rgba(255, 255, 255, 0.3);
        }
        
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            .page-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .page-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .filters-form {
                flex-direction: column;
            }
            
            .filter-input {
                min-width: 100%;
            }
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
                        <a href="./" class="nav-link active">
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
                        <a href="../configuracoes/" class="nav-link">
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
                <h1 class="page-title">Gestão de Usuários</h1>
                <div class="page-actions">
                    <div class="admin-info">
                        <div class="admin-avatar">
                            <?= strtoupper(substr($admin['nome'], 0, 1)) ?>
                        </div>
                        <div>
                            <strong><?= htmlspecialchars($admin['nome']) ?></strong>
                            <small><?= htmlspecialchars($admin['email']) ?></small>
                        </div>
                    </div>
                    <a href="../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        Sair
                    </a>
                </div>
            </div>
            
            <!-- Filtros -->
            <div class="filters-section">
                <form class="filters-form" method="GET">
                    <div class="filter-group">
                        <label class="filter-label">Buscar</label>
                        <input 
                            type="text" 
                            name="search" 
                            class="filter-input" 
                            placeholder="Nome, telefone ou email..."
                            value="<?= htmlspecialchars($search) ?>"
                        >
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select name="status" class="filter-input">
                            <option value="">Todos</option>
                            <option value="ativo" <?= $status === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                            <option value="inativo" <?= $status === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                            <option value="suspenso" <?= $status === 'suspenso' ? 'selected' : '' ?>>Suspenso</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Ordenar por</label>
                        <select name="order" class="filter-input">
                            <option value="created_at" <?= $orderBy === 'created_at' ? 'selected' : '' ?>>Data de Cadastro</option>
                            <option value="nome" <?= $orderBy === 'nome' ? 'selected' : '' ?>>Nome</option>
                            <option value="ultimo_login" <?= $orderBy === 'ultimo_login' ? 'selected' : '' ?>>Último Login</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Direção</label>
                        <select name="dir" class="filter-input">
                            <option value="DESC" <?= $orderDir === 'DESC' ? 'selected' : '' ?>>Decrescente</option>
                            <option value="ASC" <?= $orderDir === 'ASC' ? 'selected' : '' ?>>Crescente</option>
                        </select>
                    </div>
                    <button type="submit" class="filter-btn">
                        <i class="fas fa-search"></i>
                        Filtrar
                    </button>
                </form>
            </div>
            
            <!-- Tabela de Usuários -->
            <div class="users-table">
                <div class="table-header">
                    <h3 class="table-title">Usuários Cadastrados</h3>
                    <div class="table-info">
                        <?= number_format($totalRecords) ?> usuários encontrados
                    </div>
                </div>
                
                <?php if (!empty($usuarios)): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome</th>
                                    <th>Telefone</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Saldo Total</th>
                                    <th>Investido</th>
                                    <th>Indicados</th>
                                    <th>Cadastro</th>
                                    <th>Último Login</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <tr>
                                        <td><?= $usuario['id'] ?></td>
                                        <td><?= htmlspecialchars($usuario['nome'] ?: 'N/A') ?></td>
                                        <td><?= htmlspecialchars($usuario['telefone']) ?></td>
                                        <td><?= htmlspecialchars($usuario['email'] ?: 'N/A') ?></td>
                                        <td>
                                            <span class="status-badge <?= $usuario['status'] ?>">
                                                <?= ucfirst($usuario['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            R$ <?= number_format(
                                                ($usuario['saldo_principal'] + $usuario['saldo_bonus'] + $usuario['saldo_comissao']), 
                                                2, ',', '.'
                                            ) ?>
                                        </td>
                                        <td>R$ <?= number_format($usuario['total_investido'], 2, ',', '.') ?></td>
                                        <td><?= $usuario['total_indicados'] ?></td>
                                        <td><?= date('d/m/Y', strtotime($usuario['created_at'])) ?></td>
                                        <td>
                                            <?= $usuario['ultimo_login'] ? date('d/m/Y H:i', strtotime($usuario['ultimo_login'])) : 'Nunca' ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="view.php?id=<?= $usuario['id'] ?>" class="action-btn view" title="Visualizar">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit.php?id=<?= $usuario['id'] ?>" class="action-btn edit" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button class="action-btn delete" title="Excluir" onclick="confirmDelete(<?= $usuario['id'] ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Paginação -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <div class="pagination-info">
                                Página <?= $page ?> de <?= $totalPages ?> (<?= number_format($totalRecords) ?> registros)
                            </div>
                            <div class="pagination-buttons">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&order=<?= urlencode($orderBy) ?>&dir=<?= urlencode($orderDir) ?>" class="pagination-btn">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php 
                                $start = max(1, $page - 2);
                                $end = min($totalPages, $page + 2);
                                
                                for ($i = $start; $i <= $end; $i++): 
                                ?>
                                    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&order=<?= urlencode($orderBy) ?>&dir=<?= urlencode($orderDir) ?>" 
                                       class="pagination-btn <?= $i === $page ? 'active' : '' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&order=<?= urlencode($orderBy) ?>&dir=<?= urlencode($orderDir) ?>" class="pagination-btn">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h3>Nenhum usuário encontrado</h3>
                        <p>Não há usuários que correspondam aos filtros aplicados.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script>
        function confirmDelete(userId) {
            if (confirm('Tem certeza que deseja excluir este usuário? Esta ação não pode ser desfeita.')) {
                window.location.href = 'delete.php?id=' + userId;
            }
        }
        
        // Toggle sidebar em mobile
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('open');
        }
    </script>
</body>
</html>