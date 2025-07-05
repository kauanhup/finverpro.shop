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
    
    // Buscar dados do usuário e saldo de bônus
    $stmt = $pdo->prepare("
        SELECT 
            u.nome, 
            u.telefone,
            c.saldo_bonus,
            c.saldo_principal,
            c.total_depositado
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
    
    // Buscar histórico de bônus utilizados
    $stmt = $pdo->prepare("
        SELECT 
            bu.codigo_usado,
            bu.valor_concedido,
            bu.created_at,
            bc.descricao,
            bc.tipo
        FROM bonus_utilizados bu
        JOIN bonus_codigos bc ON bu.bonus_codigo_id = bc.id
        WHERE bu.usuario_id = ?
        ORDER BY bu.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $historico_bonus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar códigos de bônus disponíveis (ativos e dentro da validade)
    $stmt = $pdo->prepare("
        SELECT 
            codigo,
            tipo,
            valor,
            descricao,
            uso_maximo,
            uso_atual,
            valor_minimo_deposito,
            apenas_primeiro_uso,
            data_expiracao
        FROM bonus_codigos 
        WHERE ativo = 1 
        AND (data_inicio IS NULL OR data_inicio <= NOW())
        AND (data_expiracao IS NULL OR data_expiracao >= NOW())
        AND (uso_maximo IS NULL OR uso_atual < uso_maximo)
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $bonus_disponiveis = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Erro na página de bônus: " . $e->getMessage());
    $historico_bonus = [];
    $bonus_disponiveis = [];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bônus - Finver Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .bonus-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        .codigo-input {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .bonus-item {
            border-left: 4px solid #28a745;
            background: #f8f9fa;
        }
        .bonus-disponivel {
            border: 2px dashed #6f42c1;
            background: linear-gradient(45deg, #f8f9fa, #e9ecef);
        }
        .valor-bonus {
            font-size: 1.5rem;
            font-weight: bold;
            color: #28a745;
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
                            <a class="nav-link text-white" href="../investimentos/">
                                <i class="bi bi-graph-up"></i> Investimentos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../team/">
                                <i class="bi bi-people"></i> Minha Equipe
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white active" href="./">
                                <i class="bi bi-gift"></i> Bônus
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../checklist/">
                                <i class="bi bi-check-square"></i> Check-in
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
                    <h1 class="h2"><i class="bi bi-gift text-primary"></i> Sistema de Bônus</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-outline-secondary" onclick="location.reload()">
                                <i class="bi bi-arrow-clockwise"></i> Atualizar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Saldo de Bônus -->
                <div class="row mb-4">
                    <div class="col-lg-6 mb-3">
                        <div class="card bonus-card">
                            <div class="card-body text-center">
                                <h5 class="card-title">
                                    <i class="bi bi-gift-fill"></i> Saldo de Bônus
                                </h5>
                                <h2 class="display-6">R$ <?= number_format($usuario['saldo_bonus'], 2, ',', '.') ?></h2>
                                <small>Disponível para investimentos</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="bi bi-code-square"></i> Resgatar Código de Bônus
                                </h5>
                                <form id="formBonus">
                                    <div class="mb-3">
                                        <label class="form-label">Código de Bônus</label>
                                        <input type="text" class="form-control codigo-input" 
                                               id="codigo_bonus" name="codigo_bonus" 
                                               placeholder="Digite o código" maxlength="20" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-check-circle"></i> Resgatar Bônus
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bônus Disponíveis -->
                <?php if (!empty($bonus_disponiveis)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <h3 class="mb-3">
                            <i class="bi bi-star"></i> Bônus Disponíveis
                        </h3>
                        <div class="row">
                            <?php foreach ($bonus_disponiveis as $bonus): ?>
                            <div class="col-lg-4 col-md-6 mb-3">
                                <div class="card bonus-disponivel h-100">
                                    <div class="card-body">
                                        <h5 class="card-title text-purple">
                                            <?= htmlspecialchars($bonus['descricao']) ?>
                                        </h5>
                                        <div class="valor-bonus mb-3">
                                            <?php if ($bonus['tipo'] == 'valor_fixo'): ?>
                                                R$ <?= number_format($bonus['valor'], 2, ',', '.') ?>
                                            <?php else: ?>
                                                <?= number_format($bonus['valor'], 2, ',', '.') ?>%
                                            <?php endif; ?>
                                        </div>
                                        <div class="small text-muted">
                                            <div><strong>Código:</strong> <?= $bonus['codigo'] ?></div>
                                            <?php if ($bonus['valor_minimo_deposito'] > 0): ?>
                                            <div><strong>Depósito mín:</strong> R$ <?= number_format($bonus['valor_minimo_deposito'], 2, ',', '.') ?></div>
                                            <?php endif; ?>
                                            <?php if ($bonus['uso_maximo']): ?>
                                            <div><strong>Disponível:</strong> <?= $bonus['uso_maximo'] - $bonus['uso_atual'] ?> usos</div>
                                            <?php endif; ?>
                                            <?php if ($bonus['data_expiracao']): ?>
                                            <div><strong>Expira em:</strong> <?= date('d/m/Y', strtotime($bonus['data_expiracao'])) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <button class="btn btn-outline-primary btn-sm mt-2" 
                                                onclick="usarCodigo('<?= $bonus['codigo'] ?>')">
                                            <i class="bi bi-clipboard"></i> Usar Código
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Histórico de Bônus -->
                <?php if (!empty($historico_bonus)): ?>
                <div class="row">
                    <div class="col-12">
                        <h3 class="mb-3">
                            <i class="bi bi-clock-history"></i> Histórico de Bônus
                        </h3>
                        <div class="card">
                            <div class="card-body">
                                <?php foreach ($historico_bonus as $bonus): ?>
                                <div class="bonus-item p-3 mb-3 rounded">
                                    <div class="row align-items-center">
                                        <div class="col-md-3">
                                            <strong><?= htmlspecialchars($bonus['codigo_usado']) ?></strong>
                                            <br><small class="text-muted"><?= ucfirst($bonus['tipo']) ?></small>
                                        </div>
                                        <div class="col-md-4">
                                            <small class="text-muted">Descrição</small>
                                            <div><?= htmlspecialchars($bonus['descricao']) ?></div>
                                        </div>
                                        <div class="col-md-2">
                                            <small class="text-muted">Valor</small>
                                            <div class="fw-bold text-success">
                                                R$ <?= number_format($bonus['valor_concedido'], 2, ',', '.') ?>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted">Data</small>
                                            <div><?= date('d/m/Y H:i', strtotime($bonus['created_at'])) ?></div>
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

    <!-- Modal de Resultado -->
    <div class="modal fade" id="modalResultado" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Resultado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const modalResultado = new bootstrap.Modal(document.getElementById('modalResultado'));

        document.getElementById('formBonus').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('codigo_bonus', document.getElementById('codigo_bonus').value);
            
            fetch('verifica.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('modalBody').innerHTML = `
                        <div class="alert alert-success">
                            <h5><i class="bi bi-check-circle"></i> Bônus Resgatado!</h5>
                            <p><strong>Código:</strong> ${data.dados.codigo}</p>
                            <p><strong>Valor:</strong> R$ ${data.dados.valor_bonus.toFixed(2).replace('.', ',')}</p>
                            <p><strong>Descrição:</strong> ${data.dados.descricao}</p>
                            <p><strong>Novo saldo de bônus:</strong> R$ ${data.dados.novo_saldo_bonus.toFixed(2).replace('.', ',')}</p>
                        </div>
                    `;
                    document.getElementById('formBonus').reset();
                    modalResultado.show();
                    
                    // Atualizar página após 3 segundos
                    setTimeout(() => {
                        location.reload();
                    }, 3000);
                } else {
                    document.getElementById('modalBody').innerHTML = `
                        <div class="alert alert-danger">
                            <h5><i class="bi bi-x-circle"></i> Erro</h5>
                            <p>${data.message}</p>
                        </div>
                    `;
                    modalResultado.show();
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                document.getElementById('modalBody').innerHTML = `
                    <div class="alert alert-danger">
                        <h5><i class="bi bi-x-circle"></i> Erro</h5>
                        <p>Erro interno. Tente novamente.</p>
                    </div>
                `;
                modalResultado.show();
            });
        });

        function usarCodigo(codigo) {
            document.getElementById('codigo_bonus').value = codigo;
            document.getElementById('codigo_bonus').focus();
        }

        // Converter código para maiúsculo
        document.getElementById('codigo_bonus').addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase();
        });
    </script>
</body>
</html>