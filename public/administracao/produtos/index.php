<?php
require_once '../includes/auth.php';
require_once '../../config/database.php';

requireAdmin();
$admin = getAdminData();
$db = Database::getInstance();

// Processar ações
if ($_POST['action'] ?? false) {
    $action = $_POST['action'];
    
    if ($action === 'create') {
        try {
            $db->query("
                INSERT INTO produtos (titulo, descricao, codigo_robo, valor_minimo, valor_maximo, 
                                    tipo_rendimento, rendimento_diario, rendimento_total, duracao_dias, 
                                    limite_vendas, limite_por_usuario, status, destaque) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $_POST['titulo'],
                $_POST['descricao'],
                $_POST['codigo_robo'],
                $_POST['valor_minimo'],
                $_POST['valor_maximo'] ?: null,
                $_POST['tipo_rendimento'],
                $_POST['rendimento_diario'],
                $_POST['rendimento_total'],
                $_POST['duracao_dias'],
                $_POST['limite_vendas'] ?: null,
                $_POST['limite_por_usuario'],
                $_POST['status'],
                isset($_POST['destaque']) ? 1 : 0
            ]);
            
            logAdminAction('produto.create', "Produto criado: {$_POST['titulo']}", 'produtos');
            $message = "Produto criado com sucesso!";
            
        } catch (Exception $e) {
            $error = "Erro ao criar produto: " . $e->getMessage();
        }
    }
    
    if ($action === 'update') {
        $id = intval($_POST['id']);
        try {
            $db->query("
                UPDATE produtos SET 
                    titulo = ?, descricao = ?, valor_minimo = ?, valor_maximo = ?,
                    tipo_rendimento = ?, rendimento_diario = ?, rendimento_total = ?,
                    duracao_dias = ?, limite_vendas = ?, limite_por_usuario = ?,
                    status = ?, destaque = ?
                WHERE id = ?
            ", [
                $_POST['titulo'],
                $_POST['descricao'],
                $_POST['valor_minimo'],
                $_POST['valor_maximo'] ?: null,
                $_POST['tipo_rendimento'],
                $_POST['rendimento_diario'],
                $_POST['rendimento_total'],
                $_POST['duracao_dias'],
                $_POST['limite_vendas'] ?: null,
                $_POST['limite_por_usuario'],
                $_POST['status'],
                isset($_POST['destaque']) ? 1 : 0,
                $id
            ]);
            
            logAdminAction('produto.update', "Produto atualizado: {$_POST['titulo']}", 'produtos', $id);
            $message = "Produto atualizado com sucesso!";
            
        } catch (Exception $e) {
            $error = "Erro ao atualizar produto: " . $e->getMessage();
        }
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        try {
            // Verificar se há investimentos ativos
            $investimentos = $db->fetchOne("SELECT COUNT(*) as total FROM investimentos WHERE produto_id = ? AND status = 'ativo'", [$id]);
            
            if ($investimentos['total'] > 0) {
                $error = "Não é possível excluir produto com investimentos ativos.";
            } else {
                $produto = $db->fetchOne("SELECT titulo FROM produtos WHERE id = ?", [$id]);
                $db->query("DELETE FROM produtos WHERE id = ?", [$id]);
                
                logAdminAction('produto.delete', "Produto excluído: {$produto['titulo']}", 'produtos', $id);
                $message = "Produto excluído com sucesso!";
            }
            
        } catch (Exception $e) {
            $error = "Erro ao excluir produto: " . $e->getMessage();
        }
    }
}

// Buscar produtos
try {
    $produtos = $db->fetchAll("
        SELECT 
            p.*,
            COUNT(i.id) as total_investimentos,
            SUM(i.valor_investido) as valor_total_investido
        FROM produtos p
        LEFT JOIN investimentos i ON p.id = i.produto_id AND i.status = 'ativo'
        GROUP BY p.id
        ORDER BY p.ordem_exibicao ASC, p.created_at DESC
    ");
    
} catch (Exception $e) {
    $produtos = [];
    $error = "Erro ao carregar produtos: " . $e->getMessage();
}

// Produto para edição
$editProduct = null;
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $editProduct = $db->fetchOne("SELECT * FROM produtos WHERE id = ?", [$editId]);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Produtos - Finver Pro</title>
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
        
        .btn-primary { background: var(--secondary-color); color: white; }
        .btn-success { background: var(--success-color); color: white; }
        .btn-danger { background: var(--error-color); color: white; }
        .btn-secondary { background: rgba(255, 255, 255, 0.1); color: var(--text-color); }
        
        .btn:hover { transform: translateY(-2px); }
        
        .form-section {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .form-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
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
        
        .form-check input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--secondary-color);
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .product-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            position: relative;
        }
        
        .product-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        
        .product-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .product-code {
            background: var(--secondary-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .product-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin: 1rem 0;
        }
        
        .stat-item {
            text-align: center;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: var(--border-radius);
        }
        
        .stat-value {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--success-color);
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.7);
            margin-top: 0.25rem;
        }
        
        .product-info {
            margin: 1rem 0;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }
        
        .info-label {
            color: rgba(255, 255, 255, 0.7);
        }
        
        .info-value {
            font-weight: 500;
        }
        
        .product-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-badge.ativo { background: rgba(16, 185, 129, 0.2); color: #10B981; }
        .status-badge.inativo { background: rgba(239, 68, 68, 0.2); color: #EF4444; }
        
        .destaque-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--warning-color);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
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
            .products-grid { grid-template-columns: 1fr; }
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
                        <a href="./" class="nav-link active">
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
                <h1 class="page-title">Gestão de Produtos</h1>
                <a href="#form-section" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Novo Produto
                </a>
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
            
            <!-- Formulário -->
            <div class="form-section" id="form-section">
                <h3 class="form-title">
                    <?= $editProduct ? 'Editar Produto' : 'Novo Produto' ?>
                </h3>
                
                <form method="POST">
                    <input type="hidden" name="action" value="<?= $editProduct ? 'update' : 'create' ?>">
                    <?php if ($editProduct): ?>
                        <input type="hidden" name="id" value="<?= $editProduct['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="form-grid">
                        <div>
                            <div class="form-group">
                                <label class="form-label">Título do Produto</label>
                                <input type="text" name="titulo" class="form-input" 
                                       value="<?= htmlspecialchars($editProduct['titulo'] ?? '') ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Código do Robô</label>
                                <input type="text" name="codigo_robo" class="form-input" 
                                       value="<?= htmlspecialchars($editProduct['codigo_robo'] ?? '') ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Descrição</label>
                                <textarea name="descricao" class="form-input" rows="4"><?= htmlspecialchars($editProduct['descricao'] ?? '') ?></textarea>
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label class="form-label">Valor Mínimo (R$)</label>
                                <input type="number" step="0.01" name="valor_minimo" class="form-input" 
                                       value="<?= $editProduct['valor_minimo'] ?? '' ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Valor Máximo (R$) - Opcional</label>
                                <input type="number" step="0.01" name="valor_maximo" class="form-input" 
                                       value="<?= $editProduct['valor_maximo'] ?? '' ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Tipo de Rendimento</label>
                                <select name="tipo_rendimento" class="form-input" required>
                                    <option value="diario" <?= ($editProduct['tipo_rendimento'] ?? '') === 'diario' ? 'selected' : '' ?>>Diário</option>
                                    <option value="unico" <?= ($editProduct['tipo_rendimento'] ?? '') === 'unico' ? 'selected' : '' ?>>Único</option>
                                    <option value="progressivo" <?= ($editProduct['tipo_rendimento'] ?? '') === 'progressivo' ? 'selected' : '' ?>>Progressivo</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Rendimento Diário (%)</label>
                                <input type="number" step="0.01" name="rendimento_diario" class="form-input" 
                                       value="<?= $editProduct['rendimento_diario'] ?? '' ?>" required>
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label class="form-label">Rendimento Total (%)</label>
                                <input type="number" step="0.01" name="rendimento_total" class="form-input" 
                                       value="<?= $editProduct['rendimento_total'] ?? '' ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Duração (dias)</label>
                                <input type="number" name="duracao_dias" class="form-input" 
                                       value="<?= $editProduct['duracao_dias'] ?? '' ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Limite de Vendas - Opcional</label>
                                <input type="number" name="limite_vendas" class="form-input" 
                                       value="<?= $editProduct['limite_vendas'] ?? '' ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Limite por Usuário</label>
                                <input type="number" name="limite_por_usuario" class="form-input" 
                                       value="<?= $editProduct['limite_por_usuario'] ?? 1 ?>" required>
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-input" required>
                                    <option value="ativo" <?= ($editProduct['status'] ?? '') === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                                    <option value="inativo" <?= ($editProduct['status'] ?? '') === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                                    <option value="esgotado" <?= ($editProduct['status'] ?? '') === 'esgotado' ? 'selected' : '' ?>>Esgotado</option>
                                </select>
                            </div>
                            
                            <div class="form-check">
                                <input type="checkbox" name="destaque" id="destaque" 
                                       <?= ($editProduct['destaque'] ?? 0) ? 'checked' : '' ?>>
                                <label for="destaque" class="form-label">Produto em Destaque</label>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            <?= $editProduct ? 'Atualizar' : 'Criar' ?> Produto
                        </button>
                        
                        <?php if ($editProduct): ?>
                            <a href="./" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                                Cancelar
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Lista de Produtos -->
            <div class="products-grid">
                <?php foreach ($produtos as $produto): ?>
                    <div class="product-card">
                        <?php if ($produto['destaque']): ?>
                            <div class="destaque-badge">
                                <i class="fas fa-star"></i> Destaque
                            </div>
                        <?php endif; ?>
                        
                        <div class="product-header">
                            <div>
                                <h3 class="product-title"><?= htmlspecialchars($produto['titulo']) ?></h3>
                                <span class="product-code"><?= htmlspecialchars($produto['codigo_robo']) ?></span>
                            </div>
                            <span class="status-badge <?= $produto['status'] ?>">
                                <?= ucfirst($produto['status']) ?>
                            </span>
                        </div>
                        
                        <div class="product-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?= $produto['total_investimentos'] ?: 0 ?></div>
                                <div class="stat-label">Investimentos</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">R$ <?= number_format($produto['valor_total_investido'] ?: 0, 0, ',', '.') ?></div>
                                <div class="stat-label">Volume Total</div>
                            </div>
                        </div>
                        
                        <div class="product-info">
                            <div class="info-row">
                                <span class="info-label">Valor Mínimo:</span>
                                <span class="info-value">R$ <?= number_format($produto['valor_minimo'], 2, ',', '.') ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Rendimento Diário:</span>
                                <span class="info-value"><?= $produto['rendimento_diario'] ?>%</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Duração:</span>
                                <span class="info-value"><?= $produto['duracao_dias'] ?> dias</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Tipo:</span>
                                <span class="info-value"><?= ucfirst($produto['tipo_rendimento']) ?></span>
                            </div>
                        </div>
                        
                        <div class="product-actions">
                            <a href="?edit=<?= $produto['id'] ?>#form-section" class="btn btn-secondary">
                                <i class="fas fa-edit"></i> Editar
                            </a>
                            <button class="btn btn-danger" onclick="confirmDelete(<?= $produto['id'] ?>, '<?= htmlspecialchars($produto['titulo']) ?>')">
                                <i class="fas fa-trash"></i> Excluir
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($produtos)): ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: rgba(255,255,255,0.6);">
                        <i class="fas fa-robot" style="font-size: 3rem; margin-bottom: 1rem; color: rgba(255,255,255,0.3);"></i>
                        <h3>Nenhum produto encontrado</h3>
                        <p>Crie seu primeiro produto para começar.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script>
        function confirmDelete(id, title) {
            if (confirm(`Tem certeza que deseja excluir o produto "${title}"?\n\nEsta ação não pode ser desfeita.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>