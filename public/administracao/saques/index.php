<?php
require_once '../includes/auth.php';
require_once '../../config/database.php';

requireAdmin();
$admin = getAdminData();
$db = Database::getInstance();

// Processar ações (aprovar/rejeitar)
if ($_POST['action'] ?? false) {
    $action = $_POST['action'];
    $saqueId = intval($_POST['saque_id']);
    
    if ($action === 'aprovar') {
        try {
            $db->beginTransaction();
            
            // Atualizar status do saque
            $db->query("UPDATE saques SET status = 'aprovado', processado_por = ?, data_processamento = NOW() WHERE id = ?", 
                      [$admin['id'], $saqueId]);
            
            // Registrar transação
            $saque = $db->fetchOne("SELECT * FROM saques WHERE id = ?", [$saqueId]);
            $db->query("INSERT INTO transacoes (usuario_id, tipo, valor, descricao, status) VALUES (?, 'saque', ?, ?, 'concluido')",
                      [$saque['usuario_id'], $saque['valor_liquido'], "Saque aprovado - #{$saqueId}"]);
            
            $db->commit();
            logAdminAction('saque.approve', "Saque #{$saqueId} aprovado", 'saques', $saqueId);
            $message = "Saque aprovado com sucesso!";
            
        } catch (Exception $e) {
            $db->rollback();
            $error = "Erro ao aprovar saque: " . $e->getMessage();
        }
    }
    
    if ($action === 'rejeitar') {
        $motivo = $_POST['motivo'] ?? 'Não informado';
        try {
            $db->beginTransaction();
            
            // Atualizar status do saque
            $db->query("UPDATE saques SET status = 'rejeitado', motivo_rejeicao = ?, processado_por = ?, data_processamento = NOW() WHERE id = ?", 
                      [$motivo, $admin['id'], $saqueId]);
            
            // Devolver valor para saldo do usuário
            $saque = $db->fetchOne("SELECT * FROM saques WHERE id = ?", [$saqueId]);
            
            // Verificar se carteira existe, se não, criar
            $carteira = $db->fetchOne("SELECT id FROM carteiras WHERE usuario_id = ?", [$saque['usuario_id']]);
            if (!$carteira) {
                $db->query("INSERT INTO carteiras (usuario_id) VALUES (?)", [$saque['usuario_id']]);
            }
            
            // Devolver valor
            $db->query("UPDATE carteiras SET saldo_principal = saldo_principal + ? WHERE usuario_id = ?",
                      [$saque['valor_bruto'], $saque['usuario_id']]);
            
            $db->commit();
            logAdminAction('saque.reject', "Saque #{$saqueId} rejeitado: {$motivo}", 'saques', $saqueId);
            $message = "Saque rejeitado com sucesso!";
            
        } catch (Exception $e) {
            $db->rollback();
            $error = "Erro ao rejeitar saque: " . $e->getMessage();
        }
    }
}

// Filtros e paginação
$status = $_GET['status'] ?? 'pendente';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Buscar saques
try {
    $whereClause = $status ? "WHERE s.status = ?" : "";
    $params = $status ? [$status] : [];
    
    $query = "
        SELECT 
            s.*,
            u.nome as usuario_nome,
            u.telefone as usuario_telefone,
            cp.chave_pix,
            cp.tipo,
            cp.nome_titular,
            cp.banco,
            cp.apelido
        FROM saques s
        JOIN usuarios u ON s.usuario_id = u.id
        JOIN chaves_pix cp ON s.chave_pix_id = cp.id
        $whereClause
        ORDER BY s.created_at DESC
        LIMIT $limit OFFSET $offset
    ";
    
    $saques = $db->fetchAll($query, $params);
    
    // Total
    $totalQuery = "SELECT COUNT(*) as total FROM saques s $whereClause";
    $totalResult = $db->fetchOne($totalQuery, $params);
    $totalRecords = $totalResult['total'];
    $totalPages = ceil($totalRecords / $limit);
    
} catch (Exception $e) {
    $saques = [];
    $totalRecords = 0;
    $totalPages = 0;
}

// Estatísticas
try {
    $stats = [
        'pendentes' => $db->fetchOne("SELECT COUNT(*) as total FROM saques WHERE status = 'pendente'")['total'],
        'aprovados_hoje' => $db->fetchOne("SELECT COUNT(*) as total FROM saques WHERE status = 'aprovado' AND DATE(data_processamento) = CURDATE()")['total'],
        'valor_pendente' => $db->fetchOne("SELECT SUM(valor_bruto) as total FROM saques WHERE status = 'pendente'")['total'] ?? 0,
        'valor_aprovado_hoje' => $db->fetchOne("SELECT SUM(valor_liquido) as total FROM saques WHERE status = 'aprovado' AND DATE(data_processamento) = CURDATE()")['total'] ?? 0
    ];
} catch (Exception $e) {
    $stats = ['pendentes' => 0, 'aprovados_hoje' => 0, 'valor_pendente' => 0, 'valor_aprovado_hoje' => 0];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Saques - Finver Pro</title>
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
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .stat-icon.warning { background: var(--warning-color); }
        .stat-icon.success { background: var(--success-color); }
        .stat-icon.info { background: var(--info-color); }
        
        .stat-value { font-size: 2rem; font-weight: 700; margin-bottom: 0.25rem; }
        .stat-label { color: rgba(255, 255, 255, 0.7); font-size: 0.875rem; }
        
        .filters-section {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .filter-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .filter-tab {
            padding: 0.75rem 1.5rem;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            border-radius: var(--border-radius);
            color: var(--text-color);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .filter-tab.active {
            background: var(--secondary-color);
            color: white;
        }
        
        .saques-table {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--border-radius);
            padding: 1.5rem;
        }
        
        .table-responsive { overflow-x: auto; }
        
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
        }
        
        .table td {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            vertical-align: middle;
        }
        
        .table tr:hover { background: rgba(255, 255, 255, 0.05); }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-badge.pendente { background: rgba(245, 158, 11, 0.2); color: #F59E0B; }
        .status-badge.aprovado { background: rgba(16, 185, 129, 0.2); color: #10B981; }
        .status-badge.rejeitado { background: rgba(239, 68, 68, 0.2); color: #EF4444; }
        
        .action-buttons { display: flex; gap: 0.5rem; }
        
        .action-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .action-btn.approve {
            background: var(--success-color);
            color: white;
        }
        
        .action-btn.reject {
            background: var(--error-color);
            color: white;
        }
        
        .action-btn:hover { transform: translateY(-2px); }
        
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
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius);
            padding: 2rem;
            max-width: 500px;
            margin: 10% auto;
        }
        
        .modal-title { font-size: 1.5rem; font-weight: 600; margin-bottom: 1rem; }
        
        .form-group { margin-bottom: 1rem; }
        
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
        
        .modal-buttons {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary { background: var(--secondary-color); color: white; }
        .btn-danger { background: var(--error-color); color: white; }
        .btn-secondary { background: rgba(255, 255, 255, 0.1); color: var(--text-color); }
        
        .btn:hover { transform: translateY(-2px); }
        
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
                        <a href="./" class="nav-link active">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Saques</span>
                            <?php if($stats['pendentes'] > 0): ?>
                                <span class="nav-badge"><?= $stats['pendentes'] ?></span>
                            <?php endif; ?>
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
                <h1 class="page-title">Gestão de Saques</h1>
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
            
            <!-- Estatísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= $stats['pendentes'] ?></div>
                    <div class="stat-label">Saques Pendentes</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon success">
                            <i class="fas fa-check"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= $stats['aprovados_hoje'] ?></div>
                    <div class="stat-label">Aprovados Hoje</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon warning">
                            <i class="fas fa-money-bill"></i>
                        </div>
                    </div>
                    <div class="stat-value">R$ <?= number_format($stats['valor_pendente'], 2, ',', '.') ?></div>
                    <div class="stat-label">Valor Pendente</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon info">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                    <div class="stat-value">R$ <?= number_format($stats['valor_aprovado_hoje'], 2, ',', '.') ?></div>
                    <div class="stat-label">Aprovado Hoje</div>
                </div>
            </div>
            
            <!-- Filtros -->
            <div class="filters-section">
                <div class="filter-tabs">
                    <a href="?status=pendente" class="filter-tab <?= $status === 'pendente' ? 'active' : '' ?>">
                        Pendentes (<?= $stats['pendentes'] ?>)
                    </a>
                    <a href="?status=aprovado" class="filter-tab <?= $status === 'aprovado' ? 'active' : '' ?>">
                        Aprovados
                    </a>
                    <a href="?status=rejeitado" class="filter-tab <?= $status === 'rejeitado' ? 'active' : '' ?>">
                        Rejeitados
                    </a>
                    <a href="?status=" class="filter-tab <?= $status === '' ? 'active' : '' ?>">
                        Todos
                    </a>
                </div>
            </div>
            
            <!-- Tabela de Saques -->
            <div class="saques-table">
                <?php if (!empty($saques)): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Usuário</th>
                                    <th>Chave PIX</th>
                                    <th>Valor Bruto</th>
                                    <th>Taxa</th>
                                    <th>Valor Líquido</th>
                                    <th>Status</th>
                                    <th>Data</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($saques as $saque): ?>
                                    <tr>
                                        <td>#<?= $saque['id'] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($saque['usuario_nome'] ?: 'N/A') ?></strong><br>
                                            <small><?= htmlspecialchars($saque['usuario_telefone']) ?></small>
                                        </td>
                                        <td>
                                            <strong><?= strtoupper($saque['tipo_pix']) ?></strong><br>
                                            <small><?= htmlspecialchars($saque['chave_pix']) ?></small><br>
                                            <small><?= htmlspecialchars($saque['nome_titular']) ?></small>
                                        </td>
                                        <td>R$ <?= number_format($saque['valor_bruto'], 2, ',', '.') ?></td>
                                        <td>R$ <?= number_format($saque['taxa'], 2, ',', '.') ?></td>
                                        <td>R$ <?= number_format($saque['valor_liquido'], 2, ',', '.') ?></td>
                                        <td>
                                            <span class="status-badge <?= $saque['status'] ?>">
                                                <?= ucfirst($saque['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($saque['created_at'])) ?></td>
                                        <td>
                                            <?php if ($saque['status'] === 'pendente'): ?>
                                                <div class="action-buttons">
                                                    <button class="action-btn approve" onclick="aprovarSaque(<?= $saque['id'] ?>)">
                                                        <i class="fas fa-check"></i> Aprovar
                                                    </button>
                                                    <button class="action-btn reject" onclick="rejeitarSaque(<?= $saque['id'] ?>)">
                                                        <i class="fas fa-times"></i> Rejeitar
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <small class="text-muted">Processado</small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 3rem; color: rgba(255,255,255,0.6);">
                        <i class="fas fa-money-bill-wave" style="font-size: 3rem; margin-bottom: 1rem; color: rgba(255,255,255,0.3);"></i>
                        <h3>Nenhum saque encontrado</h3>
                        <p>Não há saques com o status selecionado.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- Modal de Rejeição -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">Rejeitar Saque</h3>
            <form method="POST">
                <input type="hidden" name="action" value="rejeitar">
                <input type="hidden" name="saque_id" id="rejectSaqueId">
                
                <div class="form-group">
                    <label class="form-label">Motivo da Rejeição</label>
                    <textarea name="motivo" class="form-input" rows="4" placeholder="Informe o motivo da rejeição..." required></textarea>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Rejeitar Saque</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function aprovarSaque(saqueId) {
            if (confirm('Confirma a aprovação deste saque?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="aprovar">
                    <input type="hidden" name="saque_id" value="${saqueId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function rejeitarSaque(saqueId) {
            document.getElementById('rejectSaqueId').value = saqueId;
            document.getElementById('rejectModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('rejectModal').style.display = 'none';
        }
        
        // Fechar modal clicando fora
        window.onclick = function(event) {
            const modal = document.getElementById('rejectModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>