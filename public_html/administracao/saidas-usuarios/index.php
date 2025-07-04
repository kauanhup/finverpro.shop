<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../');
    exit();
}

// Incluir apenas o arquivo de saque (que já inclui db.php)
require '../bank/saque.php';

// Verificar se é admin
$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// ... resto do código permanece igual

$sql = "SELECT cargo FROM usuarios WHERE id = :user_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['cargo'] !== 'admin') {
   header('Location: ../');
   exit();
}

// ============================================================================
// PROCESSAR AÇÕES
// ============================================================================
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    if ($_GET['action'] === 'approve') {
        // Verificar se gateway está ativo antes de aprovar
        $stmtGateway = $conn->prepare("SELECT COUNT(*) FROM gateway WHERE status = 'true'");
        $stmtGateway->execute();
        $gatewayAtivo = $stmtGateway->fetchColumn();
        
        if ($gatewayAtivo == 0) {
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Erro de Configuração',
                        text: 'Nenhum gateway de pagamento está ativo. Configure um gateway antes de aprovar saques.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = './';
                        }
                    });
                });
            </script>";
        } else {
            // Aprovar o saque usando o novo sistema
            $status_saque = efetuarSaque($id);
            
            // ✅ CORREÇÃO: Verificar se retornou array ou string
            $sucesso = false;
            $mensagem = '';
            $detalhes = '';
            
            if (is_array($status_saque)) {
                // Novo formato - retorna array
                $sucesso = $status_saque['success'] ?? false;
                $mensagem = $status_saque['message'] ?? 'Resposta sem mensagem';
                
                if ($sucesso) {
                    $detalhes = "Transaction ID: " . ($status_saque['transaction_id'] ?? 'N/A') . 
                               "<br>IP usado: " . ($status_saque['ip_usado'] ?? 'N/A') . 
                               "<br>Protocolo: " . ($status_saque['protocolo'] ?? 'N/A');
                } else {
                    $detalhes = "Código erro: " . ($status_saque['error_code'] ?? 'N/A') . 
                               "<br>IP usado: " . ($status_saque['ip_usado'] ?? 'N/A');
                }
                
                // Log para debug
                error_log("Resultado saque array: " . json_encode($status_saque));
                
            } else {
                // Formato antigo - retorna string
                $sucesso = ($status_saque == 'OK' || 
                           strpos($status_saque, 'sucesso') !== false || 
                           strpos($status_saque, 'Aprovado') !== false);
                $mensagem = $status_saque;
                
                // Log para debug
                error_log("Resultado saque string: " . $status_saque);
            }
            
            if ($sucesso) {
                echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            title: '✅ Saque Aprovado!',
                            html: '<div style=\"text-align: left;\"><strong>PIX processado com sucesso!</strong><br><br>$mensagem<br><br><small style=\"color: #28a745;\">$detalhes</small></div>',
                            icon: 'success',
                            confirmButtonText: 'OK'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = './';
                            }
                        });
                    });
                </script>";
            } else {
                echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            title: '❌ Erro no Processamento',
                            html: '<div style=\"text-align: left;\"><strong>Falha ao processar saque:</strong><br><br>$mensagem<br><br><small style=\"color: #dc3545;\">$detalhes</small><br><br><small style=\"color: #666;\">Verifique os logs para mais detalhes.</small></div>',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = './';
                            }
                        });
                    });
                </script>";
            }
        }
        
    } elseif ($_GET['action'] === 'reject') {
        // Rejeitar o saque e devolver o valor ao saldo do usuário
        $stmt = $conn->prepare("SELECT user_id, valor FROM saques WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $saque = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($saque) {
            $user_id_saque = $saque['user_id'];
            $valor = $saque['valor'];

            // Devolver o saldo ao usuário
            $stmt = $conn->prepare("UPDATE usuarios SET saldo = saldo + :valor WHERE id = :user_id");
            $stmt->execute(['valor' => $valor, 'user_id' => $user_id_saque]);

            // Atualizar o status do saque para Rejeitado
            $stmt = $conn->prepare("UPDATE saques SET status = 'Rejeitado' WHERE id = :id");
            $stmt->execute(['id' => $id]);

            // Log da ação
            $timestamp = date('Y-m-d H:i:s');
            file_put_contents('admin_actions.log', "[$timestamp] REJECT: Admin rejeitou saque ID $id, valor R$ $valor devolvido ao usuário $user_id_saque" . PHP_EOL, FILE_APPEND);

            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: '🔄 Rejeitado!',
                        text: 'O saque foi cancelado e R$ " . number_format($valor, 2, ',', '.') . " foi devolvido ao saldo do usuário.',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = './';
                        }
                    });
                });
            </script>";
        }
    } elseif ($_GET['action'] === 'delete') {
        // Apagar o registro de saque
        $stmt = $conn->prepare("DELETE FROM saques WHERE id = :id");
        $stmt->execute(['id' => $id]);

        // Log da ação
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents('admin_actions.log', "[$timestamp] DELETE: Admin deletou saque ID $id" . PHP_EOL, FILE_APPEND);

        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: '🗑️ Deletado!',
                    text: 'O registro de saque foi excluído permanentemente.',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = './';
                    }
                });
            });
        </script>";
    }
}

// ============================================================================
// CONSULTAR SAQUES COM FILTROS
// ============================================================================

// Filtros
$filtroStatus = $_GET['status'] ?? 'todos';
$filtroData = $_GET['data'] ?? 'todos';

// Construir query baseada nos filtros
$whereConditions = [];
$params = [];

if ($filtroStatus !== 'todos') {
    $whereConditions[] = "status = :status";
    $params['status'] = $filtroStatus;
}

if ($filtroData !== 'todos') {
    switch ($filtroData) {
        case 'hoje':
            $whereConditions[] = "DATE(data) = CURDATE()";
            break;
        case 'ontem':
            $whereConditions[] = "DATE(data) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'semana':
            $whereConditions[] = "data >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'mes':
            $whereConditions[] = "data >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
    }
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$sql = "SELECT id, user_id, tipo_pix, chave_pix, numero_telefone, nome_titular, valor, status, data 
        FROM saques $whereClause 
        ORDER BY data DESC";

$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue(":$key", $value);
}
$stmt->execute();
$saques = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estatísticas com proteção contra NULL
$statsQuery = "SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN status = 'Pendente' THEN 1 END) as pendentes,
    COUNT(CASE WHEN status = 'Aprovado' THEN 1 END) as aprovados,
    COUNT(CASE WHEN status = 'Rejeitado' THEN 1 END) as rejeitados,
    COALESCE(SUM(CASE WHEN status = 'Aprovado' THEN valor ELSE 0 END), 0) as valor_aprovado,
    COALESCE(SUM(CASE WHEN status = 'Pendente' THEN valor ELSE 0 END), 0) as valor_pendente
    FROM saques $whereClause";

$stmtStats = $conn->prepare($statsQuery);
foreach ($params as $key => $value) {
    $stmtStats->bindValue(":$key", $value);
}
$stmtStats->execute();
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

// Garantir que os valores nunca sejam NULL
$stats['valor_aprovado'] = floatval($stats['valor_aprovado'] ?? 0);
$stats['valor_pendente'] = floatval($stats['valor_pendente'] ?? 0);

// Verificar status dos gateways
$stmtGateways = $conn->query("SELECT banco, status FROM gateway");
$gateways = $stmtGateways->fetchAll(PDO::FETCH_ASSOC);
$gatewayAtivo = array_filter($gateways, function($g) { return $g['status'] === 'true'; });
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
   <title>Saques de Usuários - Dashboard</title>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <link rel="icon" href="../assets/images/favicon.svg" type="image/x-icon" />
  <link rel="stylesheet" href="../assets/css/plugins/style.css">
  <link rel="stylesheet" href="../assets/fonts/inter/inter.css" id="main-font-link" />
  <link rel="stylesheet" href="../assets/fonts/tabler-icons.min.css" />
  <link rel="stylesheet" href="../assets/fonts/feather.css" />
  <link rel="stylesheet" href="../assets/fonts/fontawesome.css" />
  <link rel="stylesheet" href="../assets/fonts/material.css" />
  <link rel="stylesheet" href="../assets/css/style.css" id="main-style-link" />
  <link rel="stylesheet" href="../assets/css/style-preset.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  
  <style>
    .loader-container {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: #131920; z-index: 9999; display: flex;
        justify-content: center; align-items: center; flex-direction: column;
    }
    .loader {
        width: 60px; height: 60px; border-radius: 50%; background-color: transparent;
        border: 8px solid #ffffff; border-top-color: #4680ff;
        animation: spin 1s linear infinite; margin-bottom: 20px;
    }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    .loading-text { color: #ffffff; font-size: 16px; }
  </style>
</head>
<body data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-layout="vertical" data-pc-direction="ltr" data-pc-theme_contrast="" data-pc-theme="dark">

<!-- Loader -->
<div class="loader-container">
  <div class="loader"></div>
  <div class="loading-text">Carregando recursos, aguarde...</div>
</div>

<!-- Sidebar -->
<nav class="pc-sidebar">
    <div class="navbar-wrapper">
        <div class="m-header">
            <a href="../dashboard/" class="b-brand text-primary">
            <img src="../assets/images/logo-dark.svg" class="img-fluid logo-lg" alt="logo" />
            </a>
        </div>
        <div class="navbar-content">
            <div class="card pc-user-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <img src="../assets/images/user/avatar-2.jpg" alt="user-image" class="user-avtar wid-45 rounded-circle" />
                        </div>
                        <div class="flex-grow-1 ms-3 me-2">
                            <h6 class="mb-0">Administrador</h6>
                            <small>Controle Geral</small>
                        </div>
                        <a class="btn btn-icon btn-link-secondary avtar" data-bs-toggle="collapse" href="#pc_sidebar_userlink">
                            <svg class="pc-icon">
                                <use xlink:href="#custom-sort-outline"></use>
                            </svg>
                        </a>
                    </div>
                    <div class="collapse pc-user-links" id="pc_sidebar_userlink">
                        <div class="pt-3">
                            <a href="#!">
                            <i class="ti ti-power"></i>
                            <span>Sair</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <ul class="pc-navbar">
                <li class="pc-item pc-caption">
                    <label>Navegação</label>
                </li>
                <li class="pc-item">
                    <a href="../dashboard/" class="pc-link">
                        <span class="pc-micon">
                            <svg class="pc-icon">
                                <use xlink:href="#custom-status-up"></use>
                            </svg>
                        </span>
                        <span class="pc-mtext">Dashboard</span>
                    </a>
                </li>
                <li class="pc-item ">
                    <a href="../usuarios/" class="pc-link">
                    <span class="pc-micon">
                    <i class="ti ti-users"></i>
                    </span>
                    <span class="pc-mtext">Usuarios</span>
                    </a>
                </li>
                <li class="pc-item">
                    <a href="../afiliados/" class="pc-link">
                    <span class="pc-micon">
                    <i class="ti ti-users"></i>
                    </span>
                    <span class="pc-mtext">Afiliados</span>
                    </a>
                </li>
                <li class="pc-item pc-caption">
                    <label>Financeiro</label>
                    <svg class="pc-icon">
                        <use xlink:href="#custom-presentation-chart"></use>
                    </svg>
                </li>
                <li class="pc-item">
                    <a href="../entradas-geral/" class="pc-link">
                    <span class="pc-micon">
                    <i class="ti ti-arrow-bar-up"></i>
                    </span>
                    <span class="pc-mtext">Entradas Geral</span>
                    </a>
                </li>
                <li class="pc-item">
                    <a href="./" class="pc-link">
                    <span class="pc-micon">
                    <i class="ti ti-arrow-bar-to-down"></i>
                    </span>
                    <span class="pc-mtext">Saidas de Usuarios</span>
                    </a>
                </li>
                <li class="pc-item">
                    <a href="../saidas-afiliados/" class="pc-link">
                    <span class="pc-micon">
                    <i class="ti ti-arrow-bar-to-down"></i>
                    </span>
                    <span class="pc-mtext">Saidas de Afiliados</span>
                    </a>
                </li>
                <li class="pc-item pc-caption">
                    <label>Plataforma</label>
                    <svg class="pc-icon">
                        <use xlink:href="#custom-presentation-chart"></use>
                    </svg>
                </li>
                <li class="pc-item">
                    <a href="../transacao-investidores/" class="pc-link">
                    <span class="pc-micon">
                   <i class="ti ti-chart-bar"></i>
                    </span>
                    <span class="pc-mtext">Investidores</span>
                    </a>
                </li>
                <li class="pc-item">
                    <a href="../configuracao-produtos/" class="pc-link">
                    <span class="pc-micon">
                    <i class="ti ti-shopping-cart"></i>
                    </span>
                    <span class="pc-mtext">Produtos</span>
                    </a>
                </li>
                <li class="pc-item">
                    <a href="../codigos/" class="pc-link">
                    <span class="pc-micon">
                    <i class="ti ti-gift"></i>
                    </span>
                    <span class="pc-mtext">Códigos</span>
                    </a>
                </li>
                <li class="pc-item pc-caption">
                    <label>Configurações</label>
                    <svg class="pc-icon">
                        <use xlink:href="#custom-presentation-chart"></use>
                    </svg>
                </li>
                <li class="pc-item">
                    <a href="../dashboard/" class="pc-link">
                    <span class="pc-micon">
                    <i class="ti ti-settings"></i>
                    </span>
                    <span class="pc-mtext">Configurações Geral</span>
                    </a>
                </li>
                <li class="pc-item">
                    <a href="../configuracao-pagamentos/" class="pc-link">
                    <span class="pc-micon">
                    <i class="ti ti-building-bank"></i>
                    </span>
                    <span class="pc-mtext">Config de Pagamento</span>
                    </a>
                </li>
                <li class="pc-item">
                    <a href="../dashboard/" class="pc-link">
                    <span class="pc-micon">
                    <i class="ti ti-news"></i>
                    </span>
                    <span class="pc-mtext">Config de WebHook</span>
                    </a>
                </li>
                <li class="pc-item">
                    <a href="../configuracao-ceo/" class="pc-link">
                    <span class="pc-micon">
                    <i class="ti ti-world"></i>
                    </span>
                    <span class="pc-mtext">Configuração de Seo</span>
                    </a>
                </li>
                <li class="pc-item pc-caption">
                    <label>Personalização</label>
                    <svg class="pc-icon">
                        <use xlink:href="#custom-presentation-chart"></use>
                    </svg>
                </li>
                <li class="pc-item">
                    <a href="../personalizacao-cores/" class="pc-link">
                    <span class="pc-micon">
                    <i class="ti ti-palette"></i>
                    </span>
                    <span class="pc-mtext">Personalização de Cores</span>
                    </a>
                </li>
                <li class="pc-item">
                    <a href="../personalizacao-textos/" class="pc-link">
                    <span class="pc-micon">
                    <i class="ti ti-file-text"></i>
                    </span>
                    <span class="pc-mtext">Personalização de Textos</span>
                    </a>
                </li>
                <li class="pc-item">
                    <a href="../personalizar-banners/" class="pc-link">
                    <span class="pc-micon">
                    <i class="ti ti-photo "></i>
                    </span>
                    <span class="pc-mtext">Person de Imagens</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Header -->
<header class="pc-header">
    <div class="header-wrapper">
        <div class="me-auto pc-mob-drp">
            <ul class="list-unstyled">
                <li class="pc-h-item pc-sidebar-collapse">
                    <a href="#" class="pc-head-link ms-0" id="sidebar-hide">
                    <i class="ti ti-menu-2"></i>
                    </a>
                </li>
                <li class="pc-h-item pc-sidebar-popup">
                    <a href="#" class="pc-head-link ms-0" id="mobile-collapse">
                    <i class="ti ti-menu-2"></i>
                    </a>
                </li>
            </ul>
        </div>
        <div class="ms-auto">
            <ul class="list-unstyled">
                <li class="dropdown pc-h-item">
                    <a
                        class="pc-head-link dropdown-toggle arrow-none me-0"
                        data-bs-toggle="dropdown"
                        href="#"
                        role="button"
                        aria-haspopup="false"
                        aria-expanded="false"
                        >
                        <svg class="pc-icon">
                            <use xlink:href="#custom-sun-1"></use>
                        </svg>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end pc-h-dropdown">
                        <a href="#!" class="dropdown-item" onclick="layout_change('dark')">
                            <svg class="pc-icon">
                                <use xlink:href="#custom-moon"></use>
                            </svg>
                            <span>Escuro</span>
                        </a>
                        <a href="#!" class="dropdown-item" onclick="layout_change('light')">
                            <svg class="pc-icon">
                                <use xlink:href="#custom-sun-1"></use>
                            </svg>
                            <span>Claro</span>
                        </a>
                        <a href="#!" class="dropdown-item" onclick="layout_change_default()">
                            <svg class="pc-icon">
                                <use xlink:href="#custom-setting-2"></use>
                            </svg>
                            <span>Padrão</span>
                        </a>
                    </div>
                </li>
                <li class="dropdown pc-h-item header-user-profile">
                    <img src="../assets/images/user/avatar-2.jpg" alt="user-image" class="user-avtar" />
                </li>
            </ul>
        </div>
    </div>
</header>

<div class="pc-container">
    <div class="pc-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../dashboard/">Dashboard</a></li>
                            <li class="breadcrumb-item" aria-current="page">Saídas de Usuários</li>
                        </ul>
                    </div>
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h2 class="mb-0">💸 Gestão de Saques PIX</h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status dos Gateways -->
        <?php if (empty($gatewayAtivo)): ?>
        <div class="alert alert-danger" role="alert">
            <div class="d-flex align-items-center">
                <i class="ti ti-alert-triangle me-2"></i>
                <div>
                    <strong>⚠️ Atenção:</strong> Nenhum gateway de pagamento está ativo. 
                    <a href="../configuracao-pagamentos/" class="alert-link">Configure um gateway</a> para processar saques.
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-success" role="alert">
            <div class="d-flex align-items-center">
                <i class="ti ti-check me-2"></i>
                <div>
                    <strong>✅ Gateway Ativo:</strong> <?= htmlspecialchars(reset($gatewayAtivo)['banco']) ?> - Pronto para processar saques
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Estatísticas -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="ti ti-clock" style="font-size: 2rem;"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0 text-white">Pendentes</h6>
                                <h4 class="mb-0 text-white"><?= $stats['pendentes'] ?></h4>
                                <small>R$ <?= number_format($stats['valor_pendente'], 2, ',', '.') ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="ti ti-check" style="font-size: 2rem;"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0 text-white">Aprovados</h6>
                                <h4 class="mb-0 text-white"><?= $stats['aprovados'] ?></h4>
                                <small>R$ <?= number_format($stats['valor_aprovado'], 2, ',', '.') ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="ti ti-x" style="font-size: 2rem;"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0 text-white">Rejeitados</h6>
                                <h4 class="mb-0 text-white"><?= $stats['rejeitados'] ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="ti ti-list" style="font-size: 2rem;"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0 text-white">Total</h><h4 class="mb-0 text-white"><?= $stats['total'] ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="todos" <?= $filtroStatus === 'todos' ? 'selected' : '' ?>>Todos</option>
                                    <option value="Pendente" <?= $filtroStatus === 'Pendente' ? 'selected' : '' ?>>Pendente</option>
                                    <option value="Aprovado" <?= $filtroStatus === 'Aprovado' ? 'selected' : '' ?>>Aprovado</option>
                                    <option value="Rejeitado" <?= $filtroStatus === 'Rejeitado' ? 'selected' : '' ?>>Rejeitado</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Período</label>
                                <select name="data" class="form-select">
                                    <option value="todos" <?= $filtroData === 'todos' ? 'selected' : '' ?>>Todos</option>
                                    <option value="hoje" <?= $filtroData === 'hoje' ? 'selected' : '' ?>>Hoje</option>
                                    <option value="ontem" <?= $filtroData === 'ontem' ? 'selected' : '' ?>>Ontem</option>
                                    <option value="semana" <?= $filtroData === 'semana' ? 'selected' : '' ?>>Última Semana</option>
                                    <option value="mes" <?= $filtroData === 'mes' ? 'selected' : '' ?>>Último Mês</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="ti ti-filter me-1"></i>Filtrar
                                </button>
                                <a href="./" class="btn btn-outline-secondary">
                                    <i class="ti ti-refresh me-1"></i>Limpar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabela -->
        <div class="row">
            <div class="col-12">
                <div class="card table-card">
                    <div class="card-header">
                        <div class="d-sm-flex align-items-center justify-content-between">
                            <h5 class="mb-3 mb-sm-0">
                                📋 Lista de Saques 
                                <?php if($filtroStatus !== 'todos' || $filtroData !== 'todos'): ?>
                                    <small class="text-muted">(Filtrado)</small>
                                <?php endif; ?>
                            </h5>
                        </div>
                    </div>
                    <div class="card-body pt-3">
                        <div class="table-responsive">
                            <table class="table table-hover" id="pc-dt-simple">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>DATA/HORA</th>
                                        <th>NOME</th>
                                        <th>TELEFONE</th>
                                        <th>TIPO CHAVE</th>
                                        <th>CHAVE PIX</th>
                                        <th>VALOR</th>
                                        <th>STATUS</th>
                                        <th>AÇÕES</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($saques)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <i class="ti ti-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                                            <p class="mt-2 text-muted">Nenhum saque encontrado com os filtros aplicados.</p>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($saques as $saque): ?>
                                        <tr>
                                            <td><strong>#<?= htmlspecialchars($saque['id']) ?></strong></td>
                                            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($saque['data']))) ?></td>
                                            <td><?= htmlspecialchars($saque['nome_titular']) ?></td>
                                            <td><?= htmlspecialchars($saque['numero_telefone']) ?></td>
                                            <td><?= htmlspecialchars(strtoupper($saque['tipo_pix'])) ?></td>
                                            <td>
                                                <small class="font-monospace"><?= htmlspecialchars($saque['chave_pix']) ?></small>
                                            </td>
                                            <td><strong>R$ <?= number_format($saque['valor'], 2, ',', '.') ?></strong></td>
                                            <td>
                                                <span class="badge bg-<?= $saque['status'] === 'Aprovado' ? 'success' : ($saque['status'] === 'Rejeitado' ? 'danger' : 'warning') ?>">
                                                    <?= strtoupper(htmlspecialchars($saque['status'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <?php if ($saque['status'] === 'Pendente'): ?>
                                                        <button type="button" class="btn btn-sm btn-success" onclick="approveSaque(<?= $saque['id'] ?>)" title="Aprovar">
                                                            <i class="ti ti-check"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger" onclick="rejectSaque(<?= $saque['id'] ?>)" title="Rejeitar">
                                                            <i class="ti ti-x"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Já processado">
                                                            <i class="ti ti-lock"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteSaque(<?= $saque['id'] ?>)" title="Deletar">
                                                        <i class="ti ti-trash"></i>
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
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="pc-footer">
    <div class="footer-wrapper container-fluid">
       <div class="row">
          <div class="col my-1">
             <p class="m-0">Feito com muito &#9829; por <a href="https://t.me/devcorr3" target="_blank">Correa</a></p>
          </div>
          <div class="col-auto my-1">
             <ul class="list-inline footer-link mb-0">
                <li class="list-inline-item"><a href="../../inicio">Inicio</a></li>
                <li class="list-inline-item"><a href="https://t.me/devcorr3" target="_blank">Support</a></li>
             </ul>
          </div>
       </div>
    </div>
</footer>

<!-- Scripts -->
<script>
function approveSaque(id) {
    <?php if (empty($gatewayAtivo)): ?>
    Swal.fire({
        title: 'Gateway Inativo',
        text: 'Configure um gateway de pagamento antes de aprovar saques.',
        icon: 'error',
        confirmButtonText: 'OK'
    });
    return;
    <?php endif; ?>

    Swal.fire({
        title: '💰 Aprovar Saque',
        text: "Esta ação irá processar o PIX automaticamente.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sim, aprovar!',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "?action=approve&id=" + id;
        }
    });
}

function rejectSaque(id) {
    Swal.fire({
        title: '🔄 Rejeitar Saque',
        text: "Esta ação irá cancelar o saque e devolver o saldo ao usuário.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sim, rejeitar!',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "?action=reject&id=" + id;
        }
    });
}

function deleteSaque(id) {
    Swal.fire({
        title: '🗑️ Deletar Registro',
        text: "Esta ação é IRREVERSÍVEL! O registro será excluído permanentemente.",
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sim, deletar!',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "?action=delete&id=" + id;
        }
    });
}

// Remove loader quando página carrega
window.addEventListener("load", function() {
    const loader = document.querySelector('.loader-container');
    loader.style.display = 'none';
    document.body.style.overflow = 'auto'; 
});
</script>

<!-- Scripts do tema -->
<script src="../assets/js/plugins/popper.min.js"></script>
<script src="../assets/js/plugins/simplebar.min.js"></script>
<script src="../assets/js/plugins/bootstrap.min.js"></script>
<script src="../assets/js/fonts/custom-font.js"></script>
<script src="../assets/js/pcoded.js"></script>
<script src="../assets/js/plugins/feather.min.js"></script>

<script>layout_change('dark');</script>
<script>layout_theme_contrast_change('false');</script>
<script>change_box_container('false');</script>
<script>layout_caption_change('true');</script>
<script>layout_rtl_change('false');</script>
<script>preset_change('preset-1');</script>
<script>main_layout_change('vertical');</script>

<script type="module">
   import {DataTable} from "../assets/js/plugins/module.js"
   window.dt = new DataTable("#pc-dt-simple");
</script>

</body>
</html>