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
    
    // Buscar dados do usuário
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
        header('Location: ../');
        exit;
    }
    
    // Buscar dados do checklist diário do usuário
    $stmt = $pdo->prepare("
        SELECT 
            dia_consecutivo, 
            ultimo_checkin, 
            total_dias, 
            valor_acumulado 
        FROM checklist_diario 
        WHERE usuario_id = ?
    ");
    $stmt->execute([$user_id]);
    $checklist = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$checklist) {
        // Criar registro de checklist se não existir
        $stmt = $pdo->prepare("
            INSERT INTO checklist_diario (usuario_id) VALUES (?)
        ");
        $stmt->execute([$user_id]);
        
        $checklist = [
            'dia_consecutivo' => 0,
            'ultimo_checkin' => null,
            'total_dias' => 0,
            'valor_acumulado' => 0.00
        ];
    }
    
    // Verificar se pode fazer checkin hoje
    $hoje = date('Y-m-d');
    $pode_checkin = true;
    $proximo_checkin = null;
    
    if ($checklist['ultimo_checkin']) {
        $ultimo_checkin = date('Y-m-d', strtotime($checklist['ultimo_checkin']));
        
        if ($ultimo_checkin === $hoje) {
            $pode_checkin = false;
            $proximo_checkin = date('d/m/Y', strtotime('+1 day'));
        } elseif ($ultimo_checkin < date('Y-m-d', strtotime('-1 day'))) {
            // Quebrou a sequência
            $checklist['dia_consecutivo'] = 0;
        }
    }
    
    // Calcular recompensas por dia
    $recompensas = [
        1 => 2.00,
        2 => 2.50,
        3 => 3.00,
        4 => 3.50,
        5 => 4.00,
        6 => 4.50,
        7 => 10.00, // Bônus especial no 7º dia
    ];
    
    // Calcular próxima recompensa
    $proximo_dia = $checklist['dia_consecutivo'] + 1;
    if ($proximo_dia > 7) {
        $proximo_dia = 1; // Reset após 7 dias
    }
    $proxima_recompensa = $recompensas[$proximo_dia] ?? 2.00;
    
} catch (Exception $e) {
    error_log("Erro na página de checklist: " . $e->getMessage());
    $checklist = [
        'dia_consecutivo' => 0,
        'ultimo_checkin' => null,
        'total_dias' => 0,
        'valor_acumulado' => 0.00
    ];
    $pode_checkin = false;
    $proxima_recompensa = 2.00;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check-in Diário - Finver Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .checklist-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        .dia-checklist {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .dia-ativo {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            transform: scale(1.1);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
        }
        .dia-concluido {
            background: #6c757d;
            color: white;
        }
        .dia-disponivel {
            background: #f8f9fa;
            border: 3px dashed #007bff;
            color: #007bff;
            animation: pulse 2s infinite;
        }
        .dia-bloqueado {
            background: #e9ecef;
            color: #6c757d;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        .streak-badge {
            background: linear-gradient(45deg, #ff6b6b, #feca57);
            color: white;
            border-radius: 20px;
            padding: 10px 20px;
            font-weight: bold;
            display: inline-block;
        }
        .reward-card {
            border: 2px solid #28a745;
            border-radius: 15px;
            background: linear-gradient(45deg, #f8fff8, #e8f5e8);
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
                            <a class="nav-link text-white" href="../bonus/">
                                <i class="bi bi-gift"></i> Bônus
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white active" href="./">
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
                    <h1 class="h2"><i class="bi bi-check-square text-primary"></i> Check-in Diário</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-outline-secondary" onclick="location.reload()">
                                <i class="bi bi-arrow-clockwise"></i> Atualizar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Status do Check-in -->
                <div class="row mb-4">
                    <div class="col-lg-8 mb-3">
                        <div class="card checklist-card">
                            <div class="card-body text-center">
                                <h5 class="card-title">
                                    <i class="bi bi-calendar-check"></i> Status do Check-in
                                </h5>
                                <div class="row">
                                    <div class="col-md-4">
                                        <h3 class="display-6"><?= $checklist['dia_consecutivo'] ?></h3>
                                        <small>Dias Consecutivos</small>
                                    </div>
                                    <div class="col-md-4">
                                        <h3 class="display-6"><?= $checklist['total_dias'] ?></h3>
                                        <small>Total de Dias</small>
                                    </div>
                                    <div class="col-md-4">
                                        <h3 class="display-6">R$ <?= number_format($checklist['valor_acumulado'], 2, ',', '.') ?></h3>
                                        <small>Valor Acumulado</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 mb-3">
                        <div class="card reward-card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">
                                    <i class="bi bi-gift"></i> Próxima Recompensa
                                </h5>
                                <h2 class="text-success">R$ <?= number_format($proxima_recompensa, 2, ',', '.') ?></h2>
                                <?php if ($pode_checkin): ?>
                                    <button class="btn btn-success btn-lg mt-3" onclick="fazerCheckin()">
                                        <i class="bi bi-check-circle"></i> Fazer Check-in
                                    </button>
                                <?php else: ?>
                                    <p class="text-muted mt-3">
                                        Próximo check-in:<br>
                                        <strong><?= $proximo_checkin ?? 'Amanhã' ?></strong>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sequência de 7 Dias -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h3 class="mb-3">
                            <i class="bi bi-trophy"></i> Sequência de Check-in
                        </h3>
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-center flex-wrap">
                                    <?php for ($dia = 1; $dia <= 7; $dia++): ?>
                                        <?php
                                        $classe = 'dia-bloqueado';
                                        $icone = 'circle';
                                        $titulo = "Dia {$dia}";
                                        
                                        if ($dia <= $checklist['dia_consecutivo']) {
                                            $classe = 'dia-concluido';
                                            $icone = 'check-circle-fill';
                                            $titulo = "Dia {$dia} - Concluído";
                                        } elseif ($dia == $checklist['dia_consecutivo'] + 1 && $pode_checkin) {
                                            $classe = 'dia-disponivel';
                                            $icone = 'clock';
                                            $titulo = "Dia {$dia} - Disponível";
                                        }
                                        ?>
                                        <div class="dia-checklist <?= $classe ?>" title="<?= $titulo ?>">
                                            <div class="text-center">
                                                <i class="bi bi-<?= $icone ?> fs-4"></i>
                                                <div class="small"><?= $dia ?></div>
                                                <div class="smaller">R$ <?= number_format($recompensas[$dia], 2, ',', '.') ?></div>
                                            </div>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                                
                                <div class="text-center mt-4">
                                    <?php if ($checklist['dia_consecutivo'] > 0): ?>
                                        <span class="streak-badge">
                                            <i class="bi bi-fire"></i> <?= $checklist['dia_consecutivo'] ?> dias em sequência!
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Informações e Regras -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-info-circle"></i> Como Funciona o Check-in Diário
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6><i class="bi bi-calendar2-check text-primary"></i> Recompensas Diárias:</h6>
                                        <ul class="list-unstyled">
                                            <li><span class="badge bg-primary me-2">Dia 1</span> R$ 2,00</li>
                                            <li><span class="badge bg-primary me-2">Dia 2</span> R$ 2,50</li>
                                            <li><span class="badge bg-primary me-2">Dia 3</span> R$ 3,00</li>
                                            <li><span class="badge bg-primary me-2">Dia 4</span> R$ 3,50</li>
                                            <li><span class="badge bg-primary me-2">Dia 5</span> R$ 4,00</li>
                                            <li><span class="badge bg-primary me-2">Dia 6</span> R$ 4,50</li>
                                            <li><span class="badge bg-success me-2">Dia 7</span> R$ 10,00 <span class="badge bg-warning">Bônus!</span></li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6><i class="bi bi-exclamation-triangle text-warning"></i> Regras Importantes:</h6>
                                        <ul>
                                            <li>Você pode fazer check-in uma vez por dia</li>
                                            <li>A sequência reseta se você perder um dia</li>
                                            <li>No 7º dia consecutivo, você ganha um bônus especial</li>
                                            <li>Após o 7º dia, a sequência recomeça</li>
                                            <li>Os valores são creditados como bônus</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal de Resultado -->
    <div class="modal fade" id="modalResultado" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Check-in Realizado!</h5>
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

        function fazerCheckin() {
            fetch('checklist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=checkin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('modalBody').innerHTML = `
                        <div class="alert alert-success text-center">
                            <i class="bi bi-check-circle-fill fs-1 text-success"></i>
                            <h4 class="mt-3">Check-in Realizado!</h4>
                            <p><strong>Recompensa:</strong> R$ ${data.dados.valor_recompensa.toFixed(2).replace('.', ',')}</p>
                            <p><strong>Dia consecutivo:</strong> ${data.dados.dia_consecutivo}</p>
                            <p><strong>Novo saldo de bônus:</strong> R$ ${data.dados.novo_saldo_bonus.toFixed(2).replace('.', ',')}</p>
                            ${data.dados.bonus_especial ? '<div class="badge bg-warning fs-6 mt-2">🎉 Bônus do 7º dia!</div>' : ''}
                        </div>
                    `;
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
        }
    </script>
</body>
</html>