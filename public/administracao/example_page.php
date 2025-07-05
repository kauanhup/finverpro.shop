<?php
/**
 * ========================================
 * EXEMPLO DE USO DO NOVO LAYOUT
 * Como implementar páginas administrativas
 * ========================================
 */

require_once 'includes/auth.php';
require_once '../config/database.php';

// Verificar autenticação
requireAdmin();

$admin = getAdminData();
$db = Database::getInstance();

// Configurar meta dados da página
$page_title = 'Título da Página';
$page_subtitle = 'Descrição opcional da página';
$page_icon = 'fas fa-exemplo'; // Ícone FontAwesome

// CSS e JS adicionais (opcional)
$additional_css = [
    // 'assets/css/custom.css'
];

$additional_js = [
    // 'assets/js/custom.js'
];

// JavaScript inline (opcional)
$inline_js = '
    console.log("JavaScript inline executado");
    
    // Exemplo de uso do finverAdmin
    document.addEventListener("DOMContentLoaded", function() {
        finverAdmin.showToast("Sucesso", "Página carregada!", "success", 3000);
    });
';

// Dados da página (buscar do banco, etc.)
try {
    // Exemplo de busca de dados
    $dados = $db->fetchAll("SELECT * FROM usuarios LIMIT 10");
    
} catch (Exception $e) {
    error_log("Erro ao carregar dados: " . $e->getMessage());
    $dados = [];
}

// Conteúdo da página
ob_start();
?>

<!-- Breadcrumbs (opcional) -->
<nav class="breadcrumb mb-4">
    <a href="../dashboard/" class="breadcrumb-link">Dashboard</a>
    <span class="breadcrumb-separator">/</span>
    <span class="breadcrumb-current">Título da Página</span>
</nav>

<!-- Ações do topo (opcional) -->
<div class="page-actions-top">
    <button class="btn btn-primary" onclick="novaAcao()">
        <i class="fas fa-plus"></i>
        Nova Ação
    </button>
    <button class="btn btn-secondary" onclick="exportData()">
        <i class="fas fa-download"></i>
        Exportar
    </button>
</div>

<!-- Cards de estatísticas (se necessário) -->
<div class="stats-grid">
    <div class="stat-card success">
        <div class="stat-header">
            <div class="stat-icon success">
                <i class="fas fa-users"></i>
            </div>
        </div>
        <div class="stat-value">123</div>
        <div class="stat-label">Total de Itens</div>
        <div class="stat-change positive">
            <i class="fas fa-arrow-up"></i>
            +5 hoje
        </div>
    </div>
</div>

<!-- Filtros e busca (se necessário) -->
<div class="table-container">
    <div class="table-header">
        <h3 class="table-title">
            <i class="fas fa-filter"></i>
            Filtros
        </h3>
    </div>
    <div style="padding: 1.5rem;">
        <form method="GET" class="filter-form">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Buscar:</label>
                    <input type="text" name="search" class="form-input" placeholder="Digite para buscar..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Status:</label>
                    <select name="status" class="form-select">
                        <option value="">Todos</option>
                        <option value="ativo" <?= ($_GET['status'] ?? '') === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                        <option value="inativo" <?= ($_GET['status'] ?? '') === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        Filtrar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Tabela principal -->
<div class="table-container">
    <div class="table-header">
        <h3 class="table-title">
            <i class="fas fa-list"></i>
            Lista de Dados
        </h3>
        <div class="table-actions">
            <button class="btn btn-sm btn-secondary" onclick="refreshTable()">
                <i class="fas fa-sync-alt"></i>
                Atualizar
            </button>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th data-sort="id">
                        <input type="checkbox" class="table-select-all">
                    </th>
                    <th data-sort="nome">Nome</th>
                    <th data-sort="email">Email</th>
                    <th data-sort="status">Status</th>
                    <th data-sort="created_at">Data</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($dados)): ?>
                    <tr>
                        <td colspan="6" class="text-center">Nenhum dado encontrado</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($dados as $item): ?>
                        <tr>
                            <td>
                                <input type="checkbox" value="<?= $item['id'] ?>">
                            </td>
                            <td data-label="Nome">
                                <div class="d-flex items-center gap-3">
                                    <div class="admin-avatar" style="width: 32px; height: 32px; font-size: 0.75rem;">
                                        <?= strtoupper(substr($item['nome'] ?? 'U', 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="font-weight-600"><?= htmlspecialchars($item['nome'] ?? 'N/A') ?></div>
                                        <div class="text-muted">ID: <?= $item['id'] ?></div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Email"><?= htmlspecialchars($item['email'] ?? 'N/A') ?></td>
                            <td data-label="Status">
                                <span class="badge badge-<?= ($item['status'] ?? 'inativo') === 'ativo' ? 'success' : 'danger' ?>">
                                    <?= ucfirst($item['status'] ?? 'inativo') ?>
                                </span>
                            </td>
                            <td data-label="Data"><?= date('d/m/Y H:i', strtotime($item['created_at'] ?? 'now')) ?></td>
                            <td data-label="Ações">
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-secondary" onclick="editarItem(<?= $item['id'] ?>)" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteWithConfirm('?delete=<?= $item['id'] ?>', 'Tem certeza que deseja excluir este item?')" title="Excluir">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Paginação (se necessário) -->
<div class="pagination">
    <button class="pagination-btn" disabled>
        <i class="fas fa-chevron-left"></i>
    </button>
    <button class="pagination-btn active">1</button>
    <button class="pagination-btn">2</button>
    <button class="pagination-btn">3</button>
    <button class="pagination-btn">
        <i class="fas fa-chevron-right"></i>
    </button>
</div>

<!-- Modal de exemplo -->
<div class="modal-overlay" id="exampleModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Modal de Exemplo</h3>
            <button class="modal-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p>Conteúdo do modal aqui...</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
            <button class="btn btn-primary">Confirmar</button>
        </div>
    </div>
</div>

<script>
// Funções específicas da página
function novaAcao() {
    finverAdmin.openModal('exampleModal');
}

function editarItem(id) {
    finverAdmin.showToast('Info', `Editando item ${id}`, 'info');
}

function refreshTable() {
    finverAdmin.showLoading();
    setTimeout(() => {
        finverAdmin.hideLoading();
        finverAdmin.showToast('Sucesso', 'Tabela atualizada!', 'success');
    }, 1000);
}

// Configurar formulários
document.addEventListener('DOMContentLoaded', function() {
    // Exemplo de validação em tempo real
    document.querySelectorAll('.form-input').forEach(input => {
        input.addEventListener('blur', function() {
            if (this.hasAttribute('required') && !this.value.trim()) {
                this.classList.add('invalid');
            } else {
                this.classList.remove('invalid');
                this.classList.add('valid');
            }
        });
    });
});
</script>

<?php
$page_content = ob_get_clean();

// Incluir o layout
require_once 'layouts/admin_layout.php';
?>