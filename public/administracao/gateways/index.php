<?php
/**
 * ========================================
 * FINVER PRO - CONFIGURAÇÃO DE GATEWAYS
 * Módulo Completo de Configuração de Gateways de Pagamento
 * ========================================
 */

require_once '../includes/auth.php';
require_once '../../config/database.php';

// Verificar autenticação e permissões
requireAdmin('super');

$admin = getAdminData();
$db = Database::getInstance();

// Processar ações
$action = $_GET['action'] ?? '';
$success = '';
$error = '';

switch ($action) {
    case 'toggle_gateway':
        $gatewayId = intval($_GET['id']);
        try {
            $gateway = $db->fetchOne("SELECT * FROM gateways WHERE id = ?", [$gatewayId]);
            if ($gateway) {
                $newStatus = $gateway['status'] === 'ativo' ? 'inativo' : 'ativo';
                $db->query("UPDATE gateways SET status = ? WHERE id = ?", [$newStatus, $gatewayId]);
                logAdminAction('gateway.toggle', "Gateway {$gateway['nome']} alterado para {$newStatus}");
                $success = "Status do gateway atualizado!";
            }
        } catch (Exception $e) {
            $error = "Erro ao alterar status: " . $e->getMessage();
        }
        break;
        
    case 'update_gateway':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $gatewayId = intval($_POST['gateway_id']);
            $nome = trim($_POST['nome']);
            $tipo = trim($_POST['tipo']);
            $taxa = floatval($_POST['taxa']);
            $valorMinimo = floatval($_POST['valor_minimo']);
            $valorMaximo = floatval($_POST['valor_maximo']);
            $configuracoes = json_encode($_POST['configuracoes'] ?? []);
            
            try {
                $db->query("UPDATE gateways SET nome = ?, tipo = ?, taxa = ?, valor_minimo = ?, valor_maximo = ?, configuracoes = ? WHERE id = ?", 
                    [$nome, $tipo, $taxa, $valorMinimo, $valorMaximo, $configuracoes, $gatewayId]);
                
                logAdminAction('gateway.update', "Gateway {$nome} atualizado");
                $success = "Gateway atualizado com sucesso!";
            } catch (Exception $e) {
                $error = "Erro ao atualizar gateway: " . $e->getMessage();
            }
        }
        break;
        
    case 'add_gateway':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nome = trim($_POST['nome']);
            $tipo = trim($_POST['tipo']);
            $taxa = floatval($_POST['taxa']);
            $valorMinimo = floatval($_POST['valor_minimo']);
            $valorMaximo = floatval($_POST['valor_maximo']);
            $configuracoes = json_encode($_POST['configuracoes'] ?? []);
            
            try {
                $db->query("INSERT INTO gateways (nome, tipo, taxa, valor_minimo, valor_maximo, configuracoes, status) VALUES (?, ?, ?, ?, ?, ?, 'inativo')", 
                    [$nome, $tipo, $taxa, $valorMinimo, $valorMaximo, $configuracoes]);
                
                logAdminAction('gateway.add', "Novo gateway {$nome} adicionado");
                $success = "Gateway adicionado com sucesso!";
            } catch (Exception $e) {
                $error = "Erro ao adicionar gateway: " . $e->getMessage();
            }
        }
        break;
}

// Buscar gateways
$gateways = $db->fetchAll("SELECT * FROM gateways ORDER BY nome");

// Estatísticas
$stats = [
    'total_gateways' => count($gateways),
    'gateways_ativos' => count(array_filter($gateways, fn($g) => $g['status'] === 'ativo')),
    'total_transacoes' => $db->fetchOne("SELECT COUNT(*) as total FROM pagamentos")['total'],
    'valor_processado' => $db->fetchOne("SELECT COALESCE(SUM(valor), 0) as total FROM pagamentos WHERE status = 'aprovado'")['total']
];

// Gateway sendo editado
$gatewayEdit = null;
if (isset($_GET['edit'])) {
    $gatewayEdit = $db->fetchOne("SELECT * FROM gateways WHERE id = ?", [intval($_GET['edit'])]);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuração de Gateways - Finver Pro</title>
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
        
        .card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
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
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.9);
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius);
            color: var(--text-color);
            font-size: 0.875rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--secondary-color);
            background: rgba(255, 255, 255, 0.15);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
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
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background: var(--background-color);
            margin: 5% auto;
            padding: 2rem;
            border-radius: var(--border-radius);
            width: 90%;
            max-width: 600px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .close {
            color: rgba(255, 255, 255, 0.7);
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: var(--text-color);
        }
        
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
            .stats-grid { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
            .form-row { grid-template-columns: 1fr; }
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
                        <a href="../gateways/" class="nav-link active">
                            <i class="fas fa-plug"></i>
                            <span>Gateways</span>
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
                <h1 class="page-title">🔌 Configuração de Gateways</h1>
                <button class="btn btn-primary" onclick="showAddModal()">
                    <i class="fas fa-plus"></i>
                    Adicionar Gateway
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
                        <i class="fas fa-plug"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['total_gateways']) ?></div>
                    <div class="stat-label">Total de Gateways</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #3B82F6, #1D4ED8);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['gateways_ativos']) ?></div>
                    <div class="stat-label">Gateways Ativos</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #F59E0B, #D97706);">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['total_transacoes']) ?></div>
                    <div class="stat-label">Total de Transações</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #EF4444, #DC2626);">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-value">R$ <?= number_format($stats['valor_processado'], 2, ',', '.') ?></div>
                    <div class="stat-label">Valor Processado</div>
                </div>
            </div>

            <!-- Lista de Gateways -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Gateways de Pagamento</h2>
                </div>
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Gateway</th>
                                <th>Tipo</th>
                                <th>Taxa</th>
                                <th>Limites</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gateways as $gateway): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars($gateway['nome']) ?></strong>
                                        <br>
                                        <small style="color: rgba(255, 255, 255, 0.7);"><?= ucfirst($gateway['tipo']) ?></small>
                                    </div>
                                </td>
                                <td><?= ucfirst($gateway['tipo']) ?></td>
                                <td><?= number_format($gateway['taxa'], 2, ',', '.') ?>%</td>
                                <td>
                                    R$ <?= number_format($gateway['valor_minimo'], 2, ',', '.') ?> - 
                                    R$ <?= number_format($gateway['valor_maximo'], 2, ',', '.') ?>
                                </td>
                                <td>
                                    <span class="badge <?= $gateway['status'] === 'ativo' ? 'badge-success' : 'badge-danger' ?>">
                                        <?= ucfirst($gateway['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?action=toggle_gateway&id=<?= $gateway['id'] ?>" 
                                       class="btn <?= $gateway['status'] === 'ativo' ? 'btn-warning' : 'btn-success' ?> btn-sm"
                                       onclick="return confirm('Alterar status deste gateway?')">
                                        <i class="fas fa-<?= $gateway['status'] === 'ativo' ? 'pause' : 'play' ?>"></i>
                                    </a>
                                    <button class="btn btn-primary btn-sm" onclick="editGateway(<?= $gateway['id'] ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal para Adicionar/Editar Gateway -->
    <div id="gatewayModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">Adicionar Gateway</h2>
            
            <form id="gatewayForm" method="POST">
                <input type="hidden" id="gatewayId" name="gateway_id">
                
                <div class="form-group">
                    <label class="form-label">Nome do Gateway</label>
                    <input type="text" class="form-control" id="nome" name="nome" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Tipo</label>
                        <select class="form-control" id="tipo" name="tipo" required>
                            <option value="">Selecione...</option>
                            <option value="pix">PIX</option>
                            <option value="cartao">Cartão de Crédito</option>
                            <option value="boleto">Boleto</option>
                            <option value="transferencia">Transferência</option>
                            <option value="manual">Manual</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Taxa (%)</label>
                        <input type="number" class="form-control" id="taxa" name="taxa" step="0.01" min="0" max="100" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Valor Mínimo</label>
                        <input type="number" class="form-control" id="valor_minimo" name="valor_minimo" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Valor Máximo</label>
                        <input type="number" class="form-control" id="valor_maximo" name="valor_maximo" step="0.01" min="0" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">URL da API</label>
                    <input type="url" class="form-control" name="configuracoes[api_url]" id="api_url">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Client ID</label>
                        <input type="text" class="form-control" name="configuracoes[client_id]" id="client_id">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Client Secret</label>
                        <input type="password" class="form-control" name="configuracoes[client_secret]" id="client_secret">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Webhook URL</label>
                    <input type="url" class="form-control" name="configuracoes[webhook_url]" id="webhook_url">
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showAddModal() {
            document.getElementById('modalTitle').textContent = 'Adicionar Gateway';
            document.getElementById('gatewayForm').action = '?action=add_gateway';
            document.getElementById('gatewayForm').reset();
            document.getElementById('gatewayId').value = '';
            document.getElementById('gatewayModal').style.display = 'block';
        }

        function editGateway(id) {
            // Buscar dados do gateway via AJAX ou usar dados já carregados
            const gateways = <?= json_encode($gateways) ?>;
            const gateway = gateways.find(g => g.id == id);
            
            if (gateway) {
                document.getElementById('modalTitle').textContent = 'Editar Gateway';
                document.getElementById('gatewayForm').action = '?action=update_gateway';
                document.getElementById('gatewayId').value = gateway.id;
                document.getElementById('nome').value = gateway.nome;
                document.getElementById('tipo').value = gateway.tipo;
                document.getElementById('taxa').value = gateway.taxa;
                document.getElementById('valor_minimo').value = gateway.valor_minimo;
                document.getElementById('valor_maximo').value = gateway.valor_maximo;
                
                // Configurações (se existirem)
                try {
                    const config = JSON.parse(gateway.configuracoes || '{}');
                    document.getElementById('api_url').value = config.api_url || '';
                    document.getElementById('client_id').value = config.client_id || '';
                    document.getElementById('client_secret').value = config.client_secret || '';
                    document.getElementById('webhook_url').value = config.webhook_url || '';
                } catch (e) {
                    console.error('Erro ao carregar configurações:', e);
                }
                
                document.getElementById('gatewayModal').style.display = 'block';
            }
        }

        function closeModal() {
            document.getElementById('gatewayModal').style.display = 'none';
        }

        // Fechar modal clicando fora
        window.onclick = function(event) {
            const modal = document.getElementById('gatewayModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>