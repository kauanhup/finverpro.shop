<?php
session_start();
require_once '../bank/db.php';

// Verificar autenticação
if (!checkAuth()) {
    header('Location: ../');
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $pdo = getPDO();
    
    // Buscar dados do usuário e saldo
    $stmt = $pdo->prepare("
        SELECT 
            u.nome, 
            u.telefone, 
            u.tipo_usuario,
            c.saldo_principal,
            c.saldo_bonus,
            c.total_investido
        FROM usuarios u 
        JOIN carteiras c ON u.id = c.usuario_id 
        WHERE u.id = ? AND u.status = 'ativo'
    ");
    $stmt->execute([$user_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        header('Location: ../');
        exit;
    }
    
    // Buscar produtos ativos
    $stmt = $pdo->prepare("
        SELECT 
            id,
            titulo,
            descricao,
            categoria,
            imagem,
            valor_minimo,
            valor_maximo,
            rendimento_diario,
            tipo_rendimento,
            duracao_dias,
            vendidos,
            limite_vendas,
            status
        FROM produtos 
        WHERE status = 'ativo'
        ORDER BY valor_minimo ASC
    ");
    $stmt->execute();
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar investimentos ativos do usuário
    $stmt = $pdo->prepare("
        SELECT 
            i.id,
            i.valor_investido,
            i.rendimento_acumulado,
            i.dias_restantes,
            i.data_vencimento,
            i.status,
            i.created_at,
            p.titulo as produto_titulo,
            p.rendimento_diario,
            p.tipo_rendimento
        FROM investimentos i
        JOIN produtos p ON i.produto_id = p.id
        WHERE i.usuario_id = ?
        ORDER BY i.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $meus_investimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estatísticas dos investimentos
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_investimentos,
            COALESCE(SUM(valor_investido), 0) as total_investido,
            COALESCE(SUM(rendimento_acumulado), 0) as total_rendimentos,
            COUNT(CASE WHEN status = 'ativo' THEN 1 END) as ativos,
            COUNT(CASE WHEN status = 'concluido' THEN 1 END) as concluidos
        FROM investimentos 
        WHERE usuario_id = ?
    ");
    $stmt->execute([$user_id]);
    $estatisticas = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Erro na página de investimentos: " . $e->getMessage());
    $produtos = [];
    $meus_investimentos = [];
    $estatisticas = [
        'total_investimentos' => 0,
        'total_investido' => 0,
        'total_rendimentos' => 0,
        'ativos' => 0,
        'concluidos' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Investimentos - Finver Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .saldo-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        .produto-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .produto-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .rendimento-badge {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            font-weight: bold;
        }
        .investimento-item {
            border-left: 4px solid #007bff;
            background: #f8f9fa;
        }
        .stats-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-dark sidebar min-vh-100">
                <div class="position-sticky pt-3">
                    <h5 class="text-white text-center mb-4">Finver Pro</h5>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../inicio/">
                                <i class="bi bi-house"></i> Início
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white active" href="./">
                                <i class="bi bi-graph-up"></i> Investimentos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../team/">
                                <i class="bi bi-people"></i> Minha Equipe
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../retirar/dinheiro/">
                                <i class="bi bi-cash"></i> Sacar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../relatorios/">
                                <i class="bi bi-bar-chart"></i> Relatórios
                            </a>
                        </li>
                        <li class="nav-item mt-auto">
                            <a class="nav-link text-white" href="../bank/logout.php">
                                <i class="bi bi-box-arrow-right"></i> Sair
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Conteúdo Principal -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Investimentos</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-outline-secondary" onclick="location.reload()">
                                <i class="bi bi-arrow-clockwise"></i> Atualizar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Saldo e Estatísticas -->
                <div class="row mb-4">
                    <div class="col-lg-4 mb-3">
                        <div class="card saldo-card">
                            <div class="card-body text-center">
                                <h5 class="card-title">Saldo Disponível</h5>
                                <h2 class="display-6">R$ <?= number_format($usuario['saldo_principal'], 2, ',', '.') ?></h2>
                                <small>+ R$ <?= number_format($usuario['saldo_bonus'], 2, ',', '.') ?> em bônus</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-8">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <div class="card stats-card text-center">
                                    <div class="card-body">
                                        <h3 class="text-primary"><?= $estatisticas['total_investimentos'] ?></h3>
                                        <small class="text-muted">Total de Investimentos</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card stats-card text-center">
                                    <div class="card-body">
                                        <h3 class="text-info">R$ <?= number_format($estatisticas['total_investido'], 2, ',', '.') ?></h3>
                                        <small class="text-muted">Valor Investido</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card stats-card text-center">
                                    <div class="card-body">
                                        <h3 class="text-success">R$ <?= number_format($estatisticas['total_rendimentos'], 2, ',', '.') ?></h3>
                                        <small class="text-muted">Rendimentos</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card stats-card text-center">
                                    <div class="card-body">
                                        <h3 class="text-warning"><?= $estatisticas['ativos'] ?></h3>
                                        <small class="text-muted">Ativos</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Produtos Disponíveis -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h3 class="mb-3">Produtos de Investimento</h3>
                        <div class="row">
                            <?php foreach ($produtos as $produto): ?>
                            <div class="col-lg-4 col-md-6 mb-4">
                                <div class="card produto-card h-100">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="card-title mb-0"><?= htmlspecialchars($produto['titulo']) ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="card-text"><?= htmlspecialchars($produto['descricao']) ?></p>
                                        <div class="row mb-3">
                                            <div class="col-6">
                                                <small class="text-muted">Valor Mínimo</small>
                                                <div class="fw-bold">R$ <?= number_format($produto['valor_minimo'], 2, ',', '.') ?></div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Duração</small>
                                                <div class="fw-bold"><?= $produto['duracao_dias'] ?> dias</div>
                                            </div>
                                        </div>
                                        <div class="text-center mb-3">
                                            <span class="badge rendimento-badge fs-6">
                                                <?php if ($produto['tipo_rendimento'] == 'percentual_diario'): ?>
                                                    <?= number_format($produto['rendimento_diario'], 2, ',', '.') ?>% ao dia
                                                <?php else: ?>
                                                    R$ <?= number_format($produto['rendimento_diario'], 2, ',', '.') ?> ao dia
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <?php if ($produto['limite_vendas']): ?>
                                        <div class="progress mb-3">
                                            <?php $percentual = ($produto['vendidos'] / $produto['limite_vendas']) * 100; ?>
                                            <div class="progress-bar" style="width: <?= $percentual ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?= $produto['vendidos'] ?>/<?= $produto['limite_vendas'] ?> vendidos</small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-footer">
                                        <button class="btn btn-primary w-100" onclick="investir(<?= $produto['id'] ?>)">
                                            <i class="bi bi-plus-circle"></i> Investir
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Meus Investimentos -->
                <?php if (!empty($meus_investimentos)): ?>
                <div class="row">
                    <div class="col-12">
                        <h3 class="mb-3">Meus Investimentos Recentes</h3>
                        <div class="card">
                            <div class="card-body">
                                <?php foreach ($meus_investimentos as $investimento): ?>
                                <div class="investimento-item p-3 mb-3 rounded">
                                    <div class="row align-items-center">
                                        <div class="col-md-3">
                                            <strong><?= htmlspecialchars($investimento['produto_titulo']) ?></strong>
                                            <br><small class="text-muted">ID: #<?= $investimento['id'] ?></small>
                                        </div>
                                        <div class="col-md-2">
                                            <small class="text-muted">Investido</small>
                                            <div class="fw-bold">R$ <?= number_format($investimento['valor_investido'], 2, ',', '.') ?></div>
                                        </div>
                                        <div class="col-md-2">
                                            <small class="text-muted">Rendimento</small>
                                            <div class="fw-bold text-success">R$ <?= number_format($investimento['rendimento_acumulado'], 2, ',', '.') ?></div>
                                        </div>
                                        <div class="col-md-2">
                                            <small class="text-muted">Dias Restantes</small>
                                            <div class="fw-bold"><?= $investimento['dias_restantes'] ?></div>
                                        </div>
                                        <div class="col-md-2">
                                            <small class="text-muted">Status</small>
                                            <div>
                                                <span class="badge bg-<?= $investimento['status'] == 'ativo' ? 'success' : ($investimento['status'] == 'concluido' ? 'primary' : 'secondary') ?>">
                                                    <?= ucfirst($investimento['status']) ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="col-md-1">
                                            <button class="btn btn-sm btn-outline-primary" onclick="verDetalhes(<?= $investimento['id'] ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Modal de Investimento -->
    <div class="modal fade" id="modalInvestir" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Realizar Investimento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formInvestir">
                        <input type="hidden" id="produto_id" name="produto_id">
                        <div class="mb-3">
                            <label class="form-label">Produto</label>
                            <input type="text" class="form-control" id="produto_nome" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Valor do Investimento</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="number" class="form-control" id="valor_investimento" name="valor_investimento" 
                                       step="0.01" min="0" required>
                            </div>
                            <div class="form-text">
                                Saldo disponível: R$ <?= number_format($usuario['saldo_principal'], 2, ',', '.') ?>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="confirmarInvestimento()">
                        <i class="bi bi-check-circle"></i> Confirmar Investimento
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const produtos = <?= json_encode($produtos) ?>;
        const modalInvestir = new bootstrap.Modal(document.getElementById('modalInvestir'));

        function investir(produtoId) {
            const produto = produtos.find(p => p.id == produtoId);
            if (!produto) return;

            document.getElementById('produto_id').value = produto.id;
            document.getElementById('produto_nome').value = produto.titulo;
            document.getElementById('valor_investimento').value = produto.valor_minimo;
            document.getElementById('valor_investimento').min = produto.valor_minimo;
            
            if (produto.valor_maximo) {
                document.getElementById('valor_investimento').max = produto.valor_maximo;
            }

            modalInvestir.show();
        }

        function confirmarInvestimento() {
            const form = document.getElementById('formInvestir');
            const formData = new FormData(form);

            fetch('processar_investimento.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Investimento realizado com sucesso!');
                    modalInvestir.hide();
                    location.reload();
                } else {
                    alert('Erro: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro interno. Tente novamente.');
            });
        }

        function verDetalhes(investimentoId) {
            window.open('../detalhes/investimento/?id=' + investimentoId, '_blank');
        }
    </script>
</body>
</html>