<?php
/**
 * ========================================
 * FINVER PRO - DASHBOARD ADMINISTRATIVO
 * Painel Principal Moderno e Responsivo
 * ========================================
 */

require_once '../includes/auth.php';
require_once '../../config/database.php';

// Verificar autenticação
requireAdmin();

$admin = getAdminData();
$db = Database::getInstance();

// Configurar meta dados da página
$page_title = 'Dashboard';
$page_subtitle = 'Visão geral do sistema';
$page_icon = 'fas fa-tachometer-alt';

try {
    // Obter estatísticas principais
    $stats = $db->getDashboardStats();
    
    // Últimos usuários
    $ultimosUsuarios = $db->getLatestUsers(5);
    
    // Últimos saques pendentes
    $ultimosSaques = $db->getLatestPendingWithdrawals(5);
    
    // Produtos populares
    $produtosPopulares = $db->getPopularProducts(5);
    
    // Transações recentes (últimas 10)
    $transacoesRecentes = $db->fetchAll("
        SELECT t.*, u.nome as usuario_nome, u.telefone as usuario_telefone
        FROM transacoes t 
        LEFT JOIN usuarios u ON t.usuario_id = u.id
        ORDER BY t.created_at DESC 
        LIMIT 10
    ");
    
    // Gráfico de crescimento (últimos 7 dias)
    $dadosGrafico = [];
    for ($i = 6; $i >= 0; $i--) {
        $data = date('Y-m-d', strtotime("-$i days"));
        $dataFormatada = date('d/m', strtotime("-$i days"));
        
        $usuarios = $db->fetchOne("SELECT COUNT(*) as count FROM usuarios WHERE DATE(created_at) = ?", [$data])['count'] ?? 0;
        $investimentos = $db->fetchOne("SELECT COUNT(*) as count FROM investimentos WHERE DATE(created_at) = ?", [$data])['count'] ?? 0;
        $saques = $db->fetchOne("SELECT COUNT(*) as count FROM saques WHERE DATE(created_at) = ?", [$data])['count'] ?? 0;
        
        $dadosGrafico[] = [
            'data' => $dataFormatada,
            'usuarios' => $usuarios,
            'investimentos' => $investimentos,
            'saques' => $saques
        ];
    }
    
    // Estatísticas para cards
    $totalUsuarios = $stats['usuarios']['total'] ?? 0;
    $usuariosHoje = $stats['usuarios']['hoje'] ?? 0;
    $usuariosAtivos = $stats['usuarios']['ativos'] ?? 0;
    
    $totalInvestimentos = $stats['investimentos']['total'] ?? 0;
    $valorTotalInvestido = $stats['investimentos']['valor_total'] ?? 0;
    $investimentosHoje = $stats['investimentos']['hoje'] ?? 0;
    
    $saquesPendentes = $stats['saques']['pendentes'] ?? 0;
    $valorSaquesPendentes = $stats['saques']['valor_pendente'] ?? 0;
    $saquesHoje = $stats['saques']['hoje'] ?? 0;
    
    $transacoesHoje = $stats['transacoes']['hoje'] ?? 0;
    $depositosHoje = $stats['transacoes']['depositos_hoje'] ?? 0;
    
    $comissoesPendentes = $stats['comissoes']['pendentes'] ?? 0;
    $valorComissoesPendentes = $stats['comissoes']['valor_pendente'] ?? 0;
    
} catch (Exception $e) {
    error_log("Erro ao carregar dashboard: " . $e->getMessage());
    // Definir valores padrão em caso de erro
    $totalUsuarios = $usuariosHoje = $usuariosAtivos = 0;
    $totalInvestimentos = $valorTotalInvestido = $investimentosHoje = 0;
    $saquesPendentes = $valorSaquesPendentes = $saquesHoje = 0;
    $transacoesHoje = $depositosHoje = 0;
    $comissoesPendentes = $valorComissoesPendentes = 0;
    $ultimosUsuarios = $ultimosSaques = $produtosPopulares = $transacoesRecentes = [];
    $dadosGrafico = [];
}

// Registrar acesso ao dashboard
logAdminAction('dashboard.access', 'Acesso ao dashboard administrativo');

// Conteúdo da página
ob_start();
?>

<!-- Cards de Estatísticas -->
<div class="stats-grid">
    <!-- Total de Usuários -->
    <div class="stat-card success animate-fadeIn">
        <div class="stat-header">
            <div class="stat-icon success">
                <i class="fas fa-users"></i>
            </div>
        </div>
        <div class="stat-value" data-stat="totalUsuarios"><?= number_format($totalUsuarios) ?></div>
        <div class="stat-label">Total de Usuários</div>
        <div class="stat-change positive">
            <i class="fas fa-arrow-up"></i>
            +<?= $usuariosHoje ?> hoje
        </div>
    </div>
    
    <!-- Investimentos Ativos -->
    <div class="stat-card info animate-fadeIn" style="animation-delay: 0.1s;">
        <div class="stat-header">
            <div class="stat-icon info">
                <i class="fas fa-chart-line"></i>
            </div>
        </div>
        <div class="stat-value" data-stat="totalInvestimentos"><?= number_format($totalInvestimentos) ?></div>
        <div class="stat-label">Investimentos Ativos</div>
        <div class="stat-change positive">
            <i class="fas fa-arrow-up"></i>
            +<?= $investimentosHoje ?> hoje
        </div>
    </div>
    
    <!-- Saques Pendentes -->
    <div class="stat-card warning animate-fadeIn" style="animation-delay: 0.2s;">
        <div class="stat-header">
            <div class="stat-icon warning">
                <i class="fas fa-clock"></i>
            </div>
        </div>
        <div class="stat-value" data-stat="saquesPendentes"><?= number_format($saquesPendentes) ?></div>
        <div class="stat-label">Saques Pendentes</div>
        <div class="stat-change">
            <i class="fas fa-money-bill-wave"></i>
            R$ <?= number_format($valorSaquesPendentes, 2, ',', '.') ?>
        </div>
    </div>
    
    <!-- Volume Total -->
    <div class="stat-card danger animate-fadeIn" style="animation-delay: 0.3s;">
        <div class="stat-header">
            <div class="stat-icon danger">
                <i class="fas fa-dollar-sign"></i>
            </div>
        </div>
        <div class="stat-value" data-stat="valorTotalInvestido">R$ <?= number_format($valorTotalInvestido, 0, ',', '.') ?></div>
        <div class="stat-label">Volume Total Investido</div>
        <div class="stat-change">
            <i class="fas fa-chart-bar"></i>
            Volume geral
        </div>
    </div>
</div>

<!-- Gráfico de Atividade -->
<div class="table-container animate-fadeIn" style="animation-delay: 0.4s;">
    <div class="table-header">
        <h3 class="table-title">
            <i class="fas fa-chart-area"></i>
            Atividade dos Últimos 7 Dias
        </h3>
        <div class="table-actions">
            <select class="form-select" id="chartPeriod" onchange="updateChart()">
                <option value="7">7 dias</option>
                <option value="30">30 dias</option>
                <option value="90">90 dias</option>
            </select>
        </div>
    </div>
    
    <div style="padding: 2rem;">
        <canvas id="activityChart" width="400" height="100"></canvas>
    </div>
</div>

<!-- Grid de Informações -->
<div class="data-grid">
    <!-- Últimos Usuários -->
    <div class="table-container animate-fadeIn" style="animation-delay: 0.5s;">
        <div class="table-header">
            <h3 class="table-title">
                <i class="fas fa-user-plus"></i>
                Últimos Usuários
            </h3>
            <a href="../usuarios/" class="btn btn-sm btn-primary">
                Ver Todos <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>Status</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ultimosUsuarios)): ?>
                        <tr>
                            <td colspan="4" class="text-center">Nenhum usuário encontrado</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ultimosUsuarios as $usuario): ?>
                            <tr>
                                <td>
                                    <div class="d-flex items-center gap-3">
                                        <div class="admin-avatar" style="width: 32px; height: 32px; font-size: 0.75rem;">
                                            <?= strtoupper(substr($usuario['nome'] ?: $usuario['telefone'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="font-weight-600"><?= htmlspecialchars($usuario['nome'] ?: 'Usuário #' . $usuario['id']) ?></div>
                                            <div class="text-muted"><?= htmlspecialchars($usuario['telefone']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $usuario['status'] === 'ativo' ? 'success' : ($usuario['status'] === 'inativo' ? 'danger' : 'warning') ?>">
                                        <?= ucfirst($usuario['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($usuario['created_at'])) ?></td>
                                <td>
                                    <a href="../usuarios/?id=<?= $usuario['id'] ?>" class="btn btn-sm btn-secondary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Saques Pendentes -->
    <div class="table-container animate-fadeIn" style="animation-delay: 0.6s;">
        <div class="table-header">
            <h3 class="table-title">
                <i class="fas fa-exclamation-triangle"></i>
                Saques Pendentes
            </h3>
            <a href="../saques/" class="btn btn-sm btn-warning">
                Gerenciar <i class="fas fa-cog"></i>
            </a>
        </div>
        
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>Valor</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ultimosSaques)): ?>
                        <tr>
                            <td colspan="4" class="text-center">Nenhum saque pendente</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ultimosSaques as $saque): ?>
                            <tr>
                                <td>
                                    <div>
                                        <div class="font-weight-600"><?= htmlspecialchars($saque['nome'] ?: 'Usuário') ?></div>
                                        <div class="text-muted" style="font-size: 0.8125rem;"><?= htmlspecialchars($saque['chave_pix'] ?? 'N/A') ?></div>
                                    </div>
                                </td>
                                <td>
                                    <span class="font-weight-600">R$ <?= number_format($saque['valor_bruto'], 2, ',', '.') ?></span>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($saque['created_at'])) ?></td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <button class="btn btn-sm btn-success" onclick="processWithdrawal(<?= $saque['id'] ?>, 'approve')" title="Aprovar">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="processWithdrawal(<?= $saque['id'] ?>, 'reject')" title="Rejeitar">
                                            <i class="fas fa-times"></i>
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
</div>

<!-- Produtos Populares e Transações -->
<div class="data-grid">
    <!-- Produtos Populares -->
    <div class="table-container animate-fadeIn" style="animation-delay: 0.7s;">
        <div class="table-header">
            <h3 class="table-title">
                <i class="fas fa-star"></i>
                Produtos Populares
            </h3>
            <a href="../produtos/" class="btn btn-sm btn-secondary">
                Ver Todos <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Investimentos</th>
                        <th>Volume</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($produtosPopulares)): ?>
                        <tr>
                            <td colspan="3" class="text-center">Nenhum produto encontrado</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($produtosPopulares as $produto): ?>
                            <tr>
                                <td>
                                    <div class="font-weight-600"><?= htmlspecialchars($produto['titulo']) ?></div>
                                    <div class="text-muted"><?= htmlspecialchars($produto['codigo_robo'] ?? '') ?></div>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?= $produto['total_investimentos'] ?? 0 ?></span>
                                </td>
                                <td>
                                    <span class="font-weight-600">R$ <?= number_format($produto['valor_total'] ?? 0, 2, ',', '.') ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Transações Recentes -->
    <div class="table-container animate-fadeIn" style="animation-delay: 0.8s;">
        <div class="table-header">
            <h3 class="table-title">
                <i class="fas fa-exchange-alt"></i>
                Transações Recentes
            </h3>
            <a href="../relatorios/" class="btn btn-sm btn-secondary">
                Relatório <i class="fas fa-chart-bar"></i>
            </a>
        </div>
        
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Usuário</th>
                        <th>Valor</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transacoesRecentes)): ?>
                        <tr>
                            <td colspan="4" class="text-center">Nenhuma transação encontrada</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach (array_slice($transacoesRecentes, 0, 5) as $transacao): ?>
                            <tr>
                                <td>
                                    <span class="badge badge-<?= $transacao['tipo'] === 'deposito' ? 'success' : ($transacao['tipo'] === 'saque' ? 'warning' : 'info') ?>">
                                        <?= ucfirst($transacao['tipo']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div>
                                        <div class="font-weight-500"><?= htmlspecialchars($transacao['usuario_nome'] ?: 'Sistema') ?></div>
                                        <div class="text-muted" style="font-size: 0.8125rem;"><?= htmlspecialchars($transacao['usuario_telefone'] ?? '') ?></div>
                                    </div>
                                </td>
                                <td>
                                    <span class="font-weight-600">R$ <?= number_format($transacao['valor'], 2, ',', '.') ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $transacao['status'] === 'concluido' ? 'success' : ($transacao['status'] === 'pendente' ? 'warning' : 'danger') ?>">
                                        <?= ucfirst($transacao['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Scripts para gráficos -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Dados do gráfico
    const chartData = <?= json_encode($dadosGrafico) ?>;
    
    // Configurar gráfico
    const ctx = document.getElementById('activityChart').getContext('2d');
    const activityChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartData.map(d => d.data),
            datasets: [
                {
                    label: 'Usuários',
                    data: chartData.map(d => d.usuarios),
                    borderColor: '#38a169',
                    backgroundColor: 'rgba(56, 161, 105, 0.1)',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Investimentos',
                    data: chartData.map(d => d.investimentos),
                    borderColor: '#3182ce',
                    backgroundColor: 'rgba(49, 130, 206, 0.1)',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Saques',
                    data: chartData.map(d => d.saques),
                    borderColor: '#d69e2e',
                    backgroundColor: 'rgba(214, 158, 46, 0.1)',
                    tension: 0.4,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        color: '#ffffff',
                        usePointStyle: true
                    }
                }
            },
            scales: {
                x: {
                    ticks: { color: '#ffffff' },
                    grid: { color: 'rgba(255, 255, 255, 0.1)' }
                },
                y: {
                    ticks: { color: '#ffffff' },
                    grid: { color: 'rgba(255, 255, 255, 0.1)' }
                }
            }
        }
    });
    
    // Função para atualizar período do gráfico
    function updateChart() {
        const period = document.getElementById('chartPeriod').value;
        finverAdmin.showToast('Info', `Carregando dados de ${period} dias...`, 'info', 2000);
        
        // Aqui você pode implementar uma requisição AJAX para buscar novos dados
        // Por enquanto, só mostra a notificação
    }
    
    // Atualizar estatísticas a cada 30 segundos
    setInterval(() => {
        fetch('../api/stats.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Atualizar valores com animação
                    document.querySelectorAll('[data-stat]').forEach(element => {
                        const statType = element.dataset.stat;
                        if (data[statType] !== undefined) {
                            finverAdmin.animateValue(
                                element, 
                                parseFloat(element.textContent.replace(/[^\d.-]/g, '')), 
                                data[statType]
                            );
                        }
                    });
                }
            })
            .catch(error => console.warn('Erro ao atualizar estatísticas:', error));
    }, 30000);
</script>

<?php
$page_content = ob_get_clean();

// Incluir o layout
require_once '../layouts/admin_layout.php';
?>