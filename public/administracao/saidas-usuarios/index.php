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

$sql = "SELECT tipo_usuario FROM usuarios WHERE id = :user_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['tipo_usuario'] !== 'admin') {
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
        $stmt = $conn->prepare("SELECT usuario_id, valor_liquido FROM operacoes_financeiras WHERE id = :id AND tipo = 'saque'");
        $stmt->execute(['id' => $id]);
        $saque = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($saque) {
            $usuario_id = $saque['usuario_id'];
            $valor = $saque['valor_liquido'];

            // Devolver o saldo ao usuário na tabela carteiras
            $stmt = $conn->prepare("UPDATE carteiras SET saldo_principal = saldo_principal + :valor WHERE usuario_id = :usuario_id");
            $stmt->execute(['valor' => $valor, 'usuario_id' => $usuario_id]);

            // Atualizar o status do saque para rejeitado
            $stmt = $conn->prepare("UPDATE operacoes_financeiras SET status = 'rejeitado' WHERE id = :id");
            $stmt->execute(['id' => $id]);

            // Log da ação
            $timestamp = date('Y-m-d H:i:s');
            file_put_contents('admin_actions.log', "[$timestamp] REJECT: Admin rejeitou saque ID $id, valor R$ $valor devolvido ao usuário $usuario_id" . PHP_EOL, FILE_APPEND);

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
        $stmt = $conn->prepare("DELETE FROM operacoes_financeiras WHERE id = :id");
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
$whereConditions = ["tipo = 'saque'"];
$params = [];

if ($filtroStatus !== 'todos') {
    $whereConditions[] = "status = :status";
    $params['status'] = $filtroStatus;
}

if ($filtroData !== 'todos') {
    switch ($filtroData) {
        case 'hoje':
            $whereConditions[] = "DATE(created_at) = CURDATE()";
            break;
        case 'ontem':
            $whereConditions[] = "DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'semana':
            $whereConditions[] = "created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'mes':
            $whereConditions[] = "created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
    }
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

$sql = "SELECT id, usuario_id, metodo, chave_pix, nome_titular, valor_liquido, status, created_at 
        FROM operacoes_financeiras $whereClause 
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue(":$key", $value);
}
$stmt->execute();
$saques = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estatísticas com proteção contra NULL
$statsQuery = "SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN status = 'pendente' THEN 1 END) as pendentes,
    COUNT(CASE WHEN status = 'aprovado' THEN 1 END) as aprovados,
    COUNT(CASE WHEN status = 'rejeitado' THEN 1 END) as rejeitados,
    COALESCE(SUM(CASE WHEN status = 'aprovado' THEN valor_liquido ELSE 0 END), 0) as valor_aprovado,
    COALESCE(SUM(CASE WHEN status = 'pendente' THEN valor_liquido ELSE 0 END), 0) as valor_pendente
    FROM operacoes_financeiras $whereClause";

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
                            <a href="../sair/">
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
                <li class="pc-item active">
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
                    <a href="../configuracoes_sistema/" class="pc-link">
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
                    <a href="#" class="pc-link">
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

<!-- Main Content -->
<div class="pc-container">
    <div class="pc-content">
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
                            <h2 class="mb-0">Saídas de Usuários</h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estatísticas -->
        <div class="row">
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h4 class="mb-0"><?= $stats['total'] ?></h4>
                                <p class="text-muted mb-0">Total</p>
                            </div>
                            <div class="col-4 text-end">
                                <i class="ti ti-list text-primary f-36"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h4 class="mb-0"><?= $stats['pendentes'] ?></h4>
                                <p class="text-muted mb-0">Pendentes</p>
                            </div>
                            <div class="col-4 text-end">
                                <i class="ti ti-clock text-warning f-36"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h4 class="mb-0"><?= $stats['aprovados'] ?></h4>
                                <p class="text-muted mb-0">Aprovados</p>
                            </div>
                            <div class="col-4 text-end">
                                <i class="ti ti-check text-success f-36"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h4 class="mb-0">R$ <?= number_format($stats['valor_pendente'], 2, ',', '.') ?></h4>
                                <p class="text-muted mb-0">Valor Pendente</p>
                            </div>
                            <div class="col-4 text-end">
                                <i class="ti ti-currency-dollar text-danger f-36"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>Filtros</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="todos" <?= $filtroStatus === 'todos' ? 'selected' : '' ?>>Todos</option>
                                    <option value="pendente" <?= $filtroStatus === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                                    <option value="aprovado" <?= $filtroStatus === 'aprovado' ? 'selected' : '' ?>>Aprovado</option>
                                    <option value="rejeitado" <?= $filtroStatus === 'rejeitado' ? 'selected' : '' ?>>Rejeitado</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Período</label>
                                <select name="data" class="form-select">
                                    <option value="todos" <?= $filtroData === 'todos' ? 'selected' : '' ?>>Todos</option>
                                    <option value="hoje" <?= $filtroData === 'hoje' ? 'selected' : '' ?>>Hoje</option>
                                    <option value="ontem" <?= $filtroData === 'ontem' ? 'selected' : '' ?>>Ontem</option>
                                    <option value="semana" <?= $filtroData === 'semana' ? 'selected' : '' ?>>Última Semana</option>
                                    <option value="mes" <?= $filtroData === 'mes' ? 'selected' : '' ?>>Último Mês</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary d-block">Filtrar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabela de Saques -->
        <div class="row">
            <div class="col-12">
                <div class="card table-card">
                    <div class="card-header">
                        <div class="d-sm-flex align-items-center justify-content-between">
                            <h5 class="mb-3 mb-sm-0">Solicitações de Saque</h5>
                            <?php if (empty($gatewayAtivo)): ?>
                                <div class="alert alert-warning mb-0">
                                    <i class="ti ti-alert-triangle"></i> Nenhum gateway ativo
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body pt-3">
                        <div class="table-responsive">
                            <table class="table table-hover" id="pc-dt-simple">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>DATA/HORA</th>
                                        <th>USUÁRIO</th>
                                        <th>TIPO PIX</th>
                                        <th>CHAVE PIX</th>
                                        <th>TITULAR</th>
                                        <th>VALOR</th>
                                        <th>STATUS</th>
                                        <th>AÇÕES</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($saques as $saque): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($saque['id']) ?></td>
                                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($saque['created_at']))) ?></td>
                                        <td><?= htmlspecialchars($saque['usuario_id']) ?></td>
                                        <td><?= htmlspecialchars($saque['metodo'] ?? 'PIX') ?></td>
                                        <td><?= htmlspecialchars($saque['chave_pix']) ?></td>
                                        <td><?= htmlspecialchars($saque['nome_titular']) ?></td>
                                        <td>R$ <?= number_format($saque['valor_liquido'], 2, ',', '.') ?></td>
                                        <td>
                                            <span class="badge text-bg-<?= $saque['status'] === 'aprovado' ? 'success' : ($saque['status'] === 'pendente' ? 'warning' : 'danger') ?>">
                                                <?= strtoupper(htmlspecialchars($saque['status'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <?php if ($saque['status'] === 'pendente'): ?>
                                                    <a href="?action=approve&id=<?= $saque['id'] ?>" class="btn btn-sm btn-success" title="Aprovar">
                                                        <i class="ti ti-check"></i>
                                                    </a>
                                                    <a href="?action=reject&id=<?= $saque['id'] ?>" class="btn btn-sm btn-warning" title="Rejeitar">
                                                        <i class="ti ti-x"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <a href="?action=delete&id=<?= $saque['id'] ?>" class="btn btn-sm btn-danger" title="Excluir">
                                                    <i class="ti ti-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
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
    // Remover loader quando página carregar
    window.addEventListener("load", function() {
        const loader = document.querySelector('.loader-container');
        loader.style.display = 'none';
        document.body.style.overflow = 'auto';
    });
    
    // Função para alterar tema
    function layout_change(theme) {
        if (theme === 'light') {
            document.body.setAttribute('data-pc-theme', 'light');
        } else {
            document.body.setAttribute('data-pc-theme', 'dark');
        }
    }
    
    function layout_change_default() {
        document.body.setAttribute('data-pc-theme', 'dark');
    }
</script>

<script src="../assets/js/plugins/popper.min.js"></script>
<script src="../assets/js/plugins/simplebar.min.js"></script>
<script src="../assets/js/plugins/bootstrap.min.js"></script>
<script src="../assets/js/fonts/custom-font.js"></script>
<script src="../assets/js/plugins/feather.min.js"></script>
<script src="../assets/js/component.js"></script>
<script src="../assets/js/plugins/simple-datatables.js"></script>
<script>
    const dataTable = new simpleDatatables.DataTable("#pc-dt-simple", {
        paging: true,
        perPage: 10,
        columns: [
            {
                select: 8,
                sortable: false
            }
        ]
    });
</script>
</body>
</html>