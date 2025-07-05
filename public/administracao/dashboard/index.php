<?php
session_start(); // Inicia a sessão

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
   // Se não estiver logado, redireciona para a página de login
   header('Location: ../../');
   exit(); // Encerra o script
}

// Incluir o arquivo de conexão com o banco de dados
require '../bank/db.php';

// Criar a conexão
try {
   $conn = getDBConnection(); // Chama a função para obter a conexão
} catch (Exception $e) {
   die("Erro de conexão: " . $e->getMessage()); // Mensagem de erro
}

// Obtém o id do usuário logado
$user_id = $_SESSION['user_id']; 

// Consultar a tabela 'usuarios' para verificar o tipo_usuario do usuário
$sql = "SELECT tipo_usuario FROM usuarios WHERE id = :user_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();

$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Se o usuário não for encontrado ou o tipo_usuario não for 'admin', redireciona para a página de login
if (!$user || $user['tipo_usuario'] !== 'admin') {
   // O usuário não é um administrador, redireciona para a página de login ou página inicial
   header('Location: ../');
   exit(); // Encerra o script
}

// Configuração de timezone e consultas
date_default_timezone_set('America/Sao_Paulo');
$today = date('Y-m-d');

// Inicializando arrays para armazenar entradas e saídas
$saidas = [];
$entradas = [];

// Consultas para entradas e saídas
try {
   // Saídas
   $stmt = $conn->prepare("SELECT created_at as data, valor_liquido as valor FROM operacoes_financeiras WHERE status = 'aprovado' AND tipo = 'saque' ORDER BY created_at DESC LIMIT 5");
   $stmt->execute();
   $saidas = $stmt->fetchAll(PDO::FETCH_ASSOC);

   // Entradas
   $stmt = $conn->prepare("SELECT created_at as data, valor_liquido as valor FROM operacoes_financeiras WHERE status = 'aprovado' AND tipo = 'deposito' ORDER BY created_at DESC LIMIT 5");
   $stmt->execute();
   $entradas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
   die("Erro ao realizar as consultas: " . $e->getMessage());
}

// Combinar entradas e saídas
$transacoes = [];

// Adiciona saídas ao array de transações
foreach ($saidas as $saida) {
   $transacoes[] = [
      'data' => $saida['data'],
      'valor' => $saida['valor'],
      'tipo' => 'saida' // Tipo de transação
   ];
}

// Adiciona entradas ao array de transações
foreach ($entradas as $entrada) {
   $transacoes[] = [
      'data' => $entrada['data'],
      'valor' => $entrada['valor'],
      'tipo' => 'entrada' // Tipo de transação
   ];
}

// Ordena as transações por data (mais recente primeiro)
usort($transacoes, function ($a, $b) {
   return strtotime($b['data']) - strtotime($a['data']);
});

// Limita a 5 transações
$transacoes = array_slice($transacoes, 0, 5);

// Arrays para armazenar dados mensais
$depositosMes = array_fill(0, 12, 0);
$saquesMes = array_fill(0, 12, 0);
$cadastrosMes = array_fill(0, 12, 0);

// Consultas SQL para obter dados mensais
try {
   // Depósitos mensais
   $stmt = $conn->prepare("SELECT MONTH(created_at) AS mes, SUM(valor_liquido) AS total FROM operacoes_financeiras WHERE status = 'aprovado' AND tipo = 'deposito' AND YEAR(created_at) = YEAR(CURRENT_DATE) GROUP BY mes");
   $stmt->execute();
   while ($row = $stmt->fetch()) {
      $depositosMes[$row['mes'] - 1] = $row['total'];
   }

   // Saques mensais
   $stmt = $conn->prepare("SELECT MONTH(created_at) AS mes, SUM(valor_liquido) AS total FROM operacoes_financeiras WHERE status = 'aprovado' AND tipo = 'saque' AND YEAR(created_at) = YEAR(CURRENT_DATE) GROUP BY mes");
   $stmt->execute();
   while ($row = $stmt->fetch()) {
      $saquesMes[$row['mes'] - 1] = $row['total'];
   }

   // Cadastros mensais
   $stmt = $conn->prepare("SELECT MONTH(created_at) AS mes, COUNT(*) AS total FROM usuarios WHERE YEAR(created_at) = YEAR(CURRENT_DATE) GROUP BY mes");
   $stmt->execute();
   while ($row = $stmt->fetch()) {
      $cadastrosMes[$row['mes'] - 1] = $row['total'];
   }
} catch (Exception $e) {
   die("Erro ao realizar as consultas: " . $e->getMessage());
}

// Consultas SQL
try {
   // Depósitos de Hoje
   $stmt = $conn->prepare("SELECT SUM(valor_liquido) AS total FROM operacoes_financeiras WHERE status = 'aprovado' AND tipo = 'deposito' AND DATE(created_at) = :today");
   $stmt->execute(['today' => $today]);
   $depositosHoje = $stmt->fetchColumn() ?: 0;

   // Total de Depósitos
   $stmt = $conn->prepare("SELECT SUM(valor_liquido) AS total FROM operacoes_financeiras WHERE status = 'aprovado' AND tipo = 'deposito'");
   $stmt->execute();
   $totalDepositos = $stmt->fetchColumn() ?: 0;

   // Cadastros de Hoje
   $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM usuarios WHERE DATE(created_at) = :today");
   $stmt->execute(['today' => $today]);
   $cadastrosHoje = $stmt->fetchColumn();

   // Total de Cadastros
   $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM usuarios");
   $stmt->execute();
   $totalCadastros = $stmt->fetchColumn();

   // Total sacado (afiliados)
   $stmt = $conn->prepare("SELECT SUM(valor_liquido) AS total FROM operacoes_financeiras WHERE status = 'aprovado' AND tipo = 'saque' AND usuario_id IN (SELECT id FROM usuarios WHERE tipo_usuario = 'usuario')");
   $stmt->execute();
   $totalSacadoAfiliados = $stmt->fetchColumn() ?: 0;

   // Total sacado (investidores)
   $stmt = $conn->prepare("SELECT SUM(valor_liquido) AS total FROM operacoes_financeiras WHERE status = 'aprovado' AND tipo = 'saque'");
   $stmt->execute();
   $totalSacadoInvestidores = $stmt->fetchColumn() ?: 0;

   // Sacados hoje (afiliados)
   $stmt = $conn->prepare("SELECT SUM(valor_liquido) AS total FROM operacoes_financeiras WHERE status = 'aprovado' AND tipo = 'saque' AND usuario_id IN (SELECT id FROM usuarios WHERE tipo_usuario = 'usuario') AND DATE(created_at) = :today");
   $stmt->execute(['today' => $today]);
   $sacadosHojeAfiliados = $stmt->fetchColumn() ?: 0;

   // Sacados hoje (investidores)
   $stmt = $conn->prepare("SELECT SUM(valor_liquido) AS total FROM operacoes_financeiras WHERE status = 'aprovado' AND tipo = 'saque' AND DATE(created_at) = :today");
   $stmt->execute(['today' => $today]);
   $sacadosHojeInvestidores = $stmt->fetchColumn() ?: 0;

   // Saldo da Plataforma
   $saldoPlataforma = $totalDepositos - $totalSacadoInvestidores;

   // Saídas da Plataforma
   $saidasPlataforma = $totalSacadoInvestidores;

   // Total de Investidores
   $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM investimentos");
   $stmt->execute();
   $totalInvestidores = $stmt->fetchColumn();

   // Códigos Usados
   $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM bonus_utilizados");
   $stmt->execute();
   $codigosUsados = $stmt->fetchColumn() ?: 0;
} catch (Exception $e) {
   die("Erro ao realizar as consultas: " . $e->getMessage());
}
?>
<!doctype html>
<html lang="en">

<head>
   <title>Investimentos - Dashboard</title>
   <meta charset="utf-8" />
   <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
   <meta http-equiv="X-UA-Compatible" content="IE=edge" />
   <meta name="author" content="seemniick" />
   <link rel="icon" href="../assets/images/favicon.svg" type="image/x-icon" />
   <link rel="stylesheet" href="../assets/fonts/inter/inter.css" id="main-font-link" />
   <link rel="stylesheet" href="../assets/fonts/tabler-icons.min.css" />
   <link rel="stylesheet" href="../assets/fonts/feather.css" />
   <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
   <link rel="stylesheet" href="../assets/fonts/fontawesome.css" />
   <link rel="stylesheet" href="../assets/fonts/material.css" />
   <link rel="stylesheet" href="../assets/css/style.css" id="main-style-link" />
   <link rel="stylesheet" href="../assets/css/style-preset.css" />
</head>

<body data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-layout="vertical" data-pc-direction="ltr" data-pc-theme_contrast="" data-pc-theme="dark">
   <style>
      .loader-container {
         position: fixed;
         top: 0;
         left: 0;
         width: 100%;
         height: 100%;
         background: #131920;
         z-index: 9999;
         display: flex;
         justify-content: center;
         align-items: center;
         flex-direction: column;
      }

      .loader {
         width: 60px;
         height: 60px;
         border-radius: 50%;
         background-color: transparent;
         border: 8px solid #ffffff;
         border-top-color: #4680ff;
         animation: spin 1s linear infinite;
         margin-bottom: 20px;
      }

      @keyframes spin {
         0% {
            transform: rotate(0deg);
         }

         100% {
            transform: rotate(360deg);
         }
      }

      .loading-text {
         color: #ffffff;
         font-size: 16px;
      }
   </style>
   <script>
      window.addEventListener("load", function() {
         const loader = document.querySelector('.loader-container');
         loader.style.display = 'none';
         document.body.style.overflow = 'auto';
      });
   </script>

   <div class="loader-container">
      <div class="loader"></div>
      <div class="loading-text">Carregando recursos, aguarde um pouco...</div>
   </div>
   <nav class="pc-sidebar">
      <div class="navbar-wrapper">
         <div class="m-header">
            <a href="../dashboard/index.html" class="b-brand text-primary">
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
               <li class="pc-item active">
                  <a href="./" class="pc-link">
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
                  <a href="../saidas-usuarios/" class="pc-link">
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
                     aria-expanded="false">
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
         <div class="row">
            <div class="col-12">
               <div class="card welcome-banner bg-blue-800">
                  <div class="card-body">
                     <div class="row">
                        <div class="col-sm-6">
                           <div class="p-4">
                              <h2 class="text-white">Olá, Administrador 👋</h2>
                              <p class="text-white">Seja bem vindo ao painel administrativo da Piramide.</p>
                              <a href="../../inicio" class="btn btn-outline-light">Plataforma</a>
                           </div>
                        </div>
                        <div class="col-sm-6 text-center">
                           <div class="img-welcome-banner">
                              <img src="../assets/images/widget/welcome-banner.png" alt="img" class="img-fluid">
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
            <div class="row">
               <div class="col-lg-3 col-md-6">
                  <div class="card">
                     <div class="card-body">
                        <div class="row align-items-center">
                           <div class="col-8">
                              <h3 class="mb-1">R$ <?php echo number_format($depositosHoje, 2, ',', '.'); ?></h3>
                              <p class="text-muted mb-0">Depósitos de Hoje</p>
                           </div>
                           <div class="col-4 text-end">
                              <i class="ti ti-cash text-success f-36"></i>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="col-lg-3 col-md-6">
                  <div class="card">
                     <div class="card-body">
                        <div class="row align-items-center">
                           <div class="col-8">
                              <h3 class="mb-1">R$ <?php echo number_format($totalDepositos, 2, ',', '.'); ?></h3>
                              <p class="text-muted mb-0">Total de Depósitos</p>
                           </div>
                           <div class="col-4 text-end">
                              <i class="ti ti-cash text-success f-36"></i>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="col-lg-3 col-md-6">
                  <div class="card">
                     <div class="card-body">
                        <div class="row align-items-center">
                           <div class="col-8">
                              <h3 class="mb-1"><?php echo $cadastrosHoje; ?></h3>
                              <p class="text-muted mb-0">Cadastros de Hoje</p>
                           </div>
                           <div class="col-4 text-end">
                              <i class="ti ti-users text-primary f-36"></i>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="col-lg-3 col-md-6">
                  <div class="card">
                     <div class="card-body">
                        <div class="row align-items-center">
                           <div class="col-8">
                              <h3 class="mb-1"><?php echo $totalCadastros; ?></h3>
                              <p class="text-muted mb-0">Total de Cadastros</p>
                           </div>
                           <div class="col-4 text-end">
                              <i class="ti ti-users text-primary f-36"></i>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="col-lg-3 col-md-6">
                  <div class="card">
                     <div class="card-body">
                        <div class="row align-items-center">
                           <div class="col-8">
                              <h3 class="mb-1">R$ <?php echo number_format($totalSacadoAfiliados, 2, ',', '.'); ?></h3>
                              <p class="text-muted mb-0">Total Sacado (Afiliados)</p>
                           </div>
                           <div class="col-4 text-end">
                              <i class="ti ti-sort-descending-2 text-danger f-36"></i>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="col-lg-3 col-md-6">
                  <div class="card">
                     <div class="card-body">
                        <div class="row align-items-center">
                           <div class="col-8">
                              <h3 class="mb-1">R$ <?php echo number_format($totalSacadoInvestidores, 2, ',', '.'); ?></h3>
                              <p class="text-muted mb-0">Total Sacado (Investidores)</p>
                           </div>
                           <div class="col-4 text-end">
                              <i class="ti ti-sort-descending-2 text-danger f-36"></i>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="col-lg-3 col-md-6">
                  <div class="card">
                     <div class="card-body">
                        <div class="row align-items-center">
                           <div class="col-8">
                              <h3 class="mb-1">R$ <?php echo number_format($sacadosHojeAfiliados, 2, ',', '.'); ?></h3>
                              <p class="text-muted mb-0">Sacados Hoje (Afiliados)</p>
                           </div>
                           <div class="col-4 text-end">
                              <i class="ti ti-sort-descending-2 text-danger f-36"></i>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="col-lg-3 col-md-6">
                  <div class="card">
                     <div class="card-body">
                        <div class="row align-items-center">
                           <div class="col-8">
                              <h3 class="mb-1">R$ <?php echo number_format($sacadosHojeInvestidores, 2, ',', '.'); ?></h3>
                              <p class="text-muted mb-0">Sacados Hoje (Investidores)</p>
                           </div>
                           <div class="col-4 text-end">
                              <i class="ti ti-sort-descending-2 text-danger f-36"></i>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="col-lg-3 col-md-6">
                  <div class="card">
                     <div class="card-body">
                        <div class="row align-items-center">
                           <div class="col-8">
                              <h3 class="mb-1">R$ <?php echo number_format($saldoPlataforma, 2, ',', '.'); ?></h3>
                              <p class="text-muted mb-0">Saldo da Plataforma</p>
                           </div>
                           <div class="col-4 text-end">
                              <i class="ti ti-wallet text-warning f-36"></i>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="col-lg-3 col-md-6">
                  <div class="card">
                     <div class="card-body">
                        <div class="row align-items-center">
                           <div class="col-8">
                              <h3 class="mb-1">R$ <?php echo number_format($saidasPlataforma, 2, ',', '.'); ?></h3>
                              <p class="text-muted mb-0">Saídas da Plataforma</p>
                           </div>
                           <div class="col-4 text-end">
                              <i class="ti ti-trending-down text-danger f-36"></i>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="col-lg-3 col-md-6">
                  <div class="card">
                     <div class="card-body">
                        <div class="row align-items-center">
                           <div class="col-8">
                              <h3 class="mb-1"><?php echo $totalInvestidores; ?></h3>
                              <p class="text-muted mb-0">Total de Investidores</p>
                           </div>
                           <div class="col-4 text-end">
                              <i class="ti ti-chart-line text-success f-36"></i>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="col-lg-3 col-md-6">
                  <div class="card">
                     <div class="card-body">
                        <div class="row align-items-center">
                           <div class="col-8">
                              <h3 class="mb-1"><?php echo $codigosUsados; ?></h3>
                              <p class="text-muted mb-0">Códigos Usados</p>
                           </div>
                           <div class="col-4 text-end">
                              <i class="ti ti-gift text-info f-36"></i>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>
   </div>
   <script src="../assets/js/plugins/popper.min.js"></script>
   <script src="../assets/js/plugins/simplebar.min.js"></script>
   <script src="../assets/js/plugins/bootstrap.min.js"></script>
   <script src="../assets/js/fonts/custom-font.js"></script>
   <script src="../assets/js/pcoded.js"></script>
   <script src="../assets/js/plugins/feather.min.js"></script>
   <script>
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
</body>
</html>