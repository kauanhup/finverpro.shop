<?php
session_start();
require_once '../../bank/db.php';

// Verificar autenticação
if (!checkAuth()) {
    header('Location: ../../');
    exit;
}

$user_id = $_SESSION['user_id'];
$produto_id = (int)($_GET['id'] ?? 0);

if ($produto_id <= 0) {
    header('Location: ../../investimentos/');
    exit;
}

try {
    $pdo = getPDO();
    
    // Buscar dados do produto
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
            comissao_nivel1,
            comissao_nivel2,
            comissao_nivel3,
            status
        FROM produtos 
        WHERE id = ? AND status = 'ativo'
    ");
    $stmt->execute([$produto_id]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$produto) {
        header('Location: ../../investimentos/');
        exit;
    }
    
    // Buscar dados do usuário e saldo
    $stmt = $pdo->prepare("
        SELECT 
            u.nome, 
            u.telefone,
            c.saldo_principal,
            c.saldo_bonus
        FROM usuarios u 
        JOIN carteiras c ON u.id = c.usuario_id 
        WHERE u.id = ? AND u.status = 'ativo'
    ");
    $stmt->execute([$user_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        header('Location: ../../');
        exit;
    }
    
    // Buscar investimentos do usuário neste produto
    $stmt = $pdo->prepare("
        SELECT 
            id,
            valor_investido,
            rendimento_acumulado,
            dias_restantes,
            data_vencimento,
            status,
            created_at
        FROM investimentos 
        WHERE usuario_id = ? AND produto_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id, $produto_id]);
    $meus_investimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Erro na página de detalhes: " . $e->getMessage());
    header('Location: ../../investimentos/');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($produto['titulo']) ?> - Finver Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .produto-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        .rendimento-card {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            border-radius: 15px;
        }
        .info-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .investimento-item {
            border-left: 4px solid #007bff;
            background: #f8f9fa;
        }
        .progress-custom {
            height: 25px;
            border-radius: 15px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <!-- Cabeçalho -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex align-items-center mb-3">
                    <a href="../../investimentos/" class="btn btn-outline-secondary me-3">
                        <i class="bi bi-arrow-left"></i> Voltar
                    </a>
                    <h1 class="h3 mb-0">Detalhes do Produto</h1>
                </div>
            </div>
        </div>

        <!-- Informações do Produto -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card produto-header">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h2 class="card-title mb-3"><?= htmlspecialchars($produto['titulo']) ?></h2>
                                <p class="card-text"><?= htmlspecialchars($produto['descricao']) ?></p>
                                <div class="row">
                                    <div class="col-sm-6">
                                        <small>Categoria</small>
                                        <div class="fw-bold"><?= ucfirst($produto['categoria']) ?></div>
                                    </div>
                                    <div class="col-sm-6">
                                        <small>Duração</small>
                                        <div class="fw-bold"><?= $produto['duracao_dias'] ?> dias</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="rendimento-card p-4">
                                    <h5>Rendimento</h5>
                                    <h3>
                                        <?php if ($produto['tipo_rendimento'] == 'percentual_diario'): ?>
                                            <?= number_format($produto['rendimento_diario'], 2, ',', '.') ?>% /dia
                                        <?php else: ?>
                                            R$ <?= number_format($produto['rendimento_diario'], 2, ',', '.') ?> /dia
                                        <?php endif; ?>
                                    </h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informações de Investimento -->
        <div class="row mb-4">
            <div class="col-lg-6 mb-3">
                <div class="card info-card h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-cash-coin"></i> Valores de Investimento</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted">Valor Mínimo</small>
                                <div class="h4 text-primary">R$ <?= number_format($produto['valor_minimo'], 2, ',', '.') ?></div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Valor Máximo</small>
                                <div class="h4 text-secondary">
                                    <?= $produto['valor_maximo'] ? 'R$ ' . number_format($produto['valor_maximo'], 2, ',', '.') : 'Sem limite' ?>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="text-center">
                            <small class="text-muted">Seu Saldo Disponível</small>
                            <div class="h5 text-success">R$ <?= number_format($usuario['saldo_principal'], 2, ',', '.') ?></div>
                            <?php if ($usuario['saldo_bonus'] > 0): ?>
                            <small class="text-muted">+ R$ <?= number_format($usuario['saldo_bonus'], 2, ',', '.') ?> em bônus</small>
                            <?php endif; ?>
                        </div>
                        <button class="btn btn-success btn-lg w-100 mt-3" onclick="investir()">
                            <i class="bi bi-plus-circle"></i> Investir Agora
                        </button>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-3">
                <div class="card info-card h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-graph-up"></i> Informações Adicionais</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($produto['limite_vendas']): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small>Disponibilidade</small>
                                <small><?= $produto['vendidos'] ?>/<?= $produto['limite_vendas'] ?></small>
                            </div>
                            <div class="progress progress-custom">
                                <?php $percentual = ($produto['vendidos'] / $produto['limite_vendas']) * 100; ?>
                                <div class="progress-bar bg-warning" style="width: <?= $percentual ?>%"></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-12 mb-2">
                                <small class="text-muted">Comissões de Afiliação</small>
                                <ul class="list-unstyled">
                                    <li><span class="badge bg-primary me-2">Nível 1</span> <?= number_format($produto['comissao_nivel1'], 2, ',', '.') ?>%</li>
                                    <li><span class="badge bg-info me-2">Nível 2</span> <?= number_format($produto['comissao_nivel2'], 2, ',', '.') ?>%</li>
                                    <li><span class="badge bg-secondary me-2">Nível 3</span> <?= number_format($produto['comissao_nivel3'], 2, ',', '.') ?>%</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Simulação de Rendimento -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card info-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-calculator"></i> Simulação de Rendimento</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label">Valor a Investir</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="valorSimulacao" 
                                           value="<?= $produto['valor_minimo'] ?>" 
                                           min="<?= $produto['valor_minimo'] ?>"
                                           <?= $produto['valor_maximo'] ? 'max="' . $produto['valor_maximo'] . '"' : '' ?>
                                           step="0.01" onchange="calcularSimulacao()">
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="row" id="resultadoSimulacao">
                                    <div class="col-sm-4 text-center">
                                        <small class="text-muted">Rendimento Diário</small>
                                        <div class="h5 text-success" id="rendimentoDiario">-</div>
                                    </div>
                                    <div class="col-sm-4 text-center">
                                        <small class="text-muted">Rendimento Total</small>
                                        <div class="h5 text-primary" id="rendimentoTotal">-</div>
                                    </div>
                                    <div class="col-sm-4 text-center">
                                        <small class="text-muted">Valor Final</small>
                                        <div class="h5 text-warning" id="valorFinal">-</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Meus Investimentos -->
        <?php if (!empty($meus_investimentos)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card info-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Meus Investimentos neste Produto</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($meus_investimentos as $inv): ?>
                        <div class="investimento-item p-3 mb-3 rounded">
                            <div class="row align-items-center">
                                <div class="col-md-2">
                                    <small class="text-muted">ID</small>
                                    <div class="fw-bold">#<?= $inv['id'] ?></div>
                                </div>
                                <div class="col-md-2">
                                    <small class="text-muted">Investido</small>
                                    <div class="fw-bold">R$ <?= number_format($inv['valor_investido'], 2, ',', '.') ?></div>
                                </div>
                                <div class="col-md-2">
                                    <small class="text-muted">Rendimento</small>
                                    <div class="fw-bold text-success">R$ <?= number_format($inv['rendimento_acumulado'], 2, ',', '.') ?></div>
                                </div>
                                <div class="col-md-2">
                                    <small class="text-muted">Dias Restantes</small>
                                    <div class="fw-bold"><?= $inv['dias_restantes'] ?></div>
                                </div>
                                <div class="col-md-2">
                                    <small class="text-muted">Status</small>
                                    <div>
                                        <span class="badge bg-<?= $inv['status'] == 'ativo' ? 'success' : ($inv['status'] == 'concluido' ? 'primary' : 'secondary') ?>">
                                            <?= ucfirst($inv['status']) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <small class="text-muted">Data</small>
                                    <div><?= date('d/m/Y', strtotime($inv['created_at'])) ?></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal de Investimento -->
    <div class="modal fade" id="modalInvestir" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Investir em <?= htmlspecialchars($produto['titulo']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formInvestir">
                        <input type="hidden" name="produto_id" value="<?= $produto['id'] ?>">
                        <div class="mb-3">
                            <label class="form-label">Valor do Investimento</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="number" class="form-control" name="valor_investimento" 
                                       value="<?= $produto['valor_minimo'] ?>" 
                                       min="<?= $produto['valor_minimo'] ?>"
                                       <?= $produto['valor_maximo'] ? 'max="' . $produto['valor_maximo'] . '"' : '' ?>
                                       step="0.01" required>
                            </div>
                            <div class="form-text">
                                Saldo disponível: R$ <?= number_format($usuario['saldo_principal'], 2, ',', '.') ?>
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <small>
                                <strong>Resumo:</strong><br>
                                Duração: <?= $produto['duracao_dias'] ?> dias<br>
                                Rendimento: <?php if ($produto['tipo_rendimento'] == 'percentual_diario'): ?>
                                    <?= number_format($produto['rendimento_diario'], 2, ',', '.') ?>% ao dia
                                <?php else: ?>
                                    R$ <?= number_format($produto['rendimento_diario'], 2, ',', '.') ?> ao dia
                                <?php endif; ?>
                            </small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" onclick="confirmarInvestimento()">
                        <i class="bi bi-check-circle"></i> Confirmar Investimento
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const produto = <?= json_encode($produto) ?>;
        const modalInvestir = new bootstrap.Modal(document.getElementById('modalInvestir'));

        function investir() {
            modalInvestir.show();
        }

        function confirmarInvestimento() {
            const form = document.getElementById('formInvestir');
            const formData = new FormData(form);

            fetch('../../investimentos/processar_investimento.php', {
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

        function calcularSimulacao() {
            const valor = parseFloat(document.getElementById('valorSimulacao').value) || 0;
            
            let rendimentoDiario = 0;
            if (produto.tipo_rendimento === 'percentual_diario') {
                rendimentoDiario = valor * (produto.rendimento_diario / 100);
            } else {
                rendimentoDiario = produto.rendimento_diario;
            }
            
            const rendimentoTotal = rendimentoDiario * produto.duracao_dias;
            const valorFinal = valor + rendimentoTotal;
            
            document.getElementById('rendimentoDiario').textContent = 
                'R$ ' + rendimentoDiario.toFixed(2).replace('.', ',');
            document.getElementById('rendimentoTotal').textContent = 
                'R$ ' + rendimentoTotal.toFixed(2).replace('.', ',');
            document.getElementById('valorFinal').textContent = 
                'R$ ' + valorFinal.toFixed(2).replace('.', ',');
        }

        // Calcular simulação inicial
        document.addEventListener('DOMContentLoaded', calcularSimulacao);
    </script>
</body>
</html>