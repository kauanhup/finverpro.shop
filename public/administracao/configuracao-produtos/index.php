<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../');
    exit();
}

require '../bank/db.php';

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    die("Erro de conexão: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];

// Verificar se é admin
$sql = "SELECT tipo_usuario FROM usuarios WHERE id = :user_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['tipo_usuario'] !== 'admin') {
   header('Location: ../');
   exit();
}

// Buscar produtos
$sql = "SELECT * FROM produtos ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <title>Configuração de Produtos - Dashboard</title>
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
</head>
<body data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-layout="vertical" data-pc-direction="ltr" data-pc-theme_contrast="" data-pc-theme="dark">

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
                <li class="pc-item">
                    <a href="../usuarios/" class="pc-link">
                        <span class="pc-micon">
                            <i class="ti ti-users"></i>
                        </span>
                        <span class="pc-mtext">Usuarios</span>
                    </a>
                </li>
                <li class="pc-item pc-caption">
                    <label>Plataforma</label>
                </li>
                <li class="pc-item">
                    <a href="../transacao-investidores/" class="pc-link">
                        <span class="pc-micon">
                            <i class="ti ti-chart-bar"></i>
                        </span>
                        <span class="pc-mtext">Investidores</span>
                    </a>
                </li>
                <li class="pc-item active">
                    <a href="./" class="pc-link">
                        <span class="pc-micon">
                            <i class="ti ti-shopping-cart"></i>
                        </span>
                        <span class="pc-mtext">Produtos</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<header class="pc-header">
    <div class="header-wrapper">
        <div class="me-auto pc-mob-drp">
            <ul class="list-unstyled">
                <li class="pc-h-item pc-sidebar-collapse">
                    <a href="#" class="pc-head-link ms-0" id="sidebar-hide">
                        <i class="ti ti-menu-2"></i>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>

<div class="pc-container">
    <div class="pc-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../dashboard/">Dashboard</a></li>
                            <li class="breadcrumb-item" aria-current="page">Produtos</li>
                        </ul>
                    </div>
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h2 class="mb-0">Configuração de Produtos</h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="card table-card">
                    <div class="card-header">
                        <div class="d-sm-flex align-items-center justify-content-between">
                            <h5 class="mb-3 mb-sm-0">Gerenciar produtos de investimento</h5>
                        </div>
                    </div>
                    <div class="card-body pt-3">
                        <div class="table-responsive">
                            <table class="table table-hover" id="pc-dt-simple">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>TÍTULO</th>
                                        <th>CATEGORIA</th>
                                        <th>CÓDIGO</th>
                                        <th>VALOR MIN</th>
                                        <th>VALOR MAX</th>
                                        <th>RENDIMENTO</th>
                                        <th>DURAÇÃO</th>
                                        <th>STATUS</th>
                                        <th>VENDIDOS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($produtos as $produto): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($produto['id']) ?></td>
                                            <td><?= htmlspecialchars($produto['titulo']) ?></td>
                                            <td><?= htmlspecialchars($produto['categoria']) ?></td>
                                            <td><?= htmlspecialchars($produto['codigo_produto']) ?></td>
                                            <td>R$ <?= number_format($produto['valor_minimo'], 2, ',', '.') ?></td>
                                            <td>
                                                <?php if ($produto['valor_maximo']): ?>
                                                    R$ <?= number_format($produto['valor_maximo'], 2, ',', '.') ?>
                                                <?php else: ?>
                                                    Ilimitado
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($produto['tipo_rendimento'] == 'percentual_diario'): ?>
                                                    <?= number_format($produto['rendimento_diario'], 2, ',', '.') ?>% / dia
                                                <?php else: ?>
                                                    R$ <?= number_format($produto['rendimento_diario'], 2, ',', '.') ?> / dia
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $produto['duracao_dias'] ?> dias</td>
                                            <td>
                                                <span class="badge text-bg-<?= $produto['status'] === 'ativo' ? 'success' : 'danger' ?>">
                                                    <?= strtoupper($produto['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= $produto['vendidos'] ?></td>
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

<script src="../assets/js/plugins/popper.min.js"></script>
<script src="../assets/js/plugins/simplebar.min.js"></script>
<script src="../assets/js/plugins/bootstrap.min.js"></script>
<script src="../assets/js/fonts/custom-font.js"></script>
<script src="../assets/js/pcoded.js"></script>
<script src="../assets/js/plugins/feather.min.js"></script>
<script src="../assets/js/plugins/simple-datatables.js"></script>
<script>
   const dataTable = new simpleDatatables.DataTable('#pc-dt-simple', {
      sortable: false,
      perPage: 10,
      perPageSelect: false,
      labels: {
         placeholder: "Buscar...",
         perPage: "Mostrar {select} por página",
         noRows: "Nenhum dado encontrado",
         info: "Mostrando {start} até {end} de {rows} registros"
      }
   });
</script>
</body>
</html>