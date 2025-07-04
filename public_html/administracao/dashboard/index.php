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

// Consultar a tabela 'usuarios' para verificar o cargo do usuário
$sql = "SELECT cargo FROM usuarios WHERE id = :user_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();

$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Se o usuário não for encontrado ou o cargo não for 'admin', redireciona para a página de login
if (!$user || $user['cargo'] !== 'admin') {
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
   $stmt = $conn->prepare("SELECT data, valor FROM saques WHERE status = 'Aprovado' ORDER BY data DESC LIMIT 5");
   $stmt->execute();
   $saidas = $stmt->fetchAll(PDO::FETCH_ASSOC);

   // Entradas
   $stmt = $conn->prepare("SELECT data, valor FROM pagamentos WHERE status = 'Aprovado' ORDER BY data DESC LIMIT 5");
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
   $stmt = $conn->prepare("SELECT MONTH(data) AS mes, SUM(valor) AS total FROM pagamentos WHERE status = 'Aprovado' AND YEAR(data) = YEAR(CURRENT_DATE) GROUP BY mes");
   $stmt->execute();
   while ($row = $stmt->fetch()) {
      $depositosMes[$row['mes'] - 1] = $row['total'];
   }

   // Saques mensais
   $stmt = $conn->prepare("SELECT MONTH(data) AS mes, SUM(valor) AS total FROM saques WHERE status = 'Aprovado' AND YEAR(data) = YEAR(CURRENT_DATE) GROUP BY mes");
   $stmt->execute();
   while ($row = $stmt->fetch()) {
      $saquesMes[$row['mes'] - 1] = $row['total'];
   }

   // Cadastros mensais
   $stmt = $conn->prepare("SELECT MONTH(data_criacao) AS mes, COUNT(*) AS total FROM usuarios WHERE YEAR(data_criacao) = YEAR(CURRENT_DATE) GROUP BY mes");
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
   $stmt = $conn->prepare("SELECT SUM(valor) AS total FROM pagamentos WHERE status = 'Aprovado' AND DATE(data) = :today");
   $stmt->execute(['today' => $today]);
   $depositosHoje = $stmt->fetchColumn() ?: 0;

   // Total de Depósitos
   $stmt = $conn->prepare("SELECT SUM(valor) AS total FROM pagamentos WHERE status = 'Aprovado'");
   $stmt->execute();
   $totalDepositos = $stmt->fetchColumn() ?: 0;

   // Cadastros de Hoje
   $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM usuarios WHERE DATE(data_criacao) = :today");
   $stmt->execute(['today' => $today]);
   $cadastrosHoje = $stmt->fetchColumn();

   // Total de Cadastros
   $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM usuarios");
   $stmt->execute();
   $totalCadastros = $stmt->fetchColumn();

   // Total sacado (afiliados)
   $stmt = $conn->prepare("SELECT SUM(valor) AS total FROM saques_comissao WHERE status = 'Aprovado'");
   $stmt->execute();
   $totalSacadoAfiliados = $stmt->fetchColumn() ?: 0;

   // Total sacado (investidores)
   $stmt = $conn->prepare("SELECT SUM(valor) AS total FROM saques WHERE status = 'Aprovado'");
   $stmt->execute();
   $totalSacadoInvestidores = $stmt->fetchColumn() ?: 0;

   // Sacados hoje (afiliados)
   $stmt = $conn->prepare("SELECT SUM(valor) AS total FROM saques_comissao WHERE status = 'Aprovado' AND DATE(data) = :today");
   $stmt->execute(['today' => $today]);
   $sacadosHojeAfiliados = $stmt->fetchColumn() ?: 0;

   // Sacados hoje (investidores)
   $stmt = $conn->prepare("SELECT SUM(valor) AS total FROM saques WHERE status = 'Aprovado' AND DATE(data) = :today");
   $stmt->execute(['today' => $today]);
   $sacadosHojeInvestidores = $stmt->fetchColumn() ?: 0;

   // Saldo da Plataforma
   $saldoPlataforma = $totalDepositos - ($totalSacadoAfiliados + $totalSacadoInvestidores);

   // Saídas da Plataforma
   $saidasPlataforma = $totalSacadoAfiliados + $totalSacadoInvestidores;

   // Total de Investidores
   $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM investidores");
   $stmt->execute();
   $totalInvestidores = $stmt->fetchColumn();

   // Códigos Usados
   $stmt = $conn->prepare("SELECT SUM(qnt_usados) AS total FROM bonus");
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

   <body data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-layout="vertical" data-pc-direction="ltr" data-pc-theme_contrast="" data-pc-theme="dark">
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
                     </a>
            </div>
         </div>
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
                                 <h3 class="mb-1">R$ <?php echo number_format($saidasPlataforma, 2, ',', '.'); ?></h3>
                                 <p class="text-muted mb-0">Saídas da Plataforma</p>
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
                                 <h3 class="mb-1"><?php echo $totalInvestidores; ?></h3>
                                 <p class="text-muted mb-0">Total de Investidores</p>
                              </div>
                              <div class="col-4 text-end">
                                 <i class="ti ti-chart-infographic text-success f-36"></i>
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
                                 <i class="ti ti-gift text-primary f-36"></i>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="row">
                  <div class="col-lg-4 col-md-6">
                     <div class="card">
                        <div class="card-body">
                           <h5 class="mb-0">Depósitos do Mês</h5>
                           <canvas id="deposits-bar-chart"></canvas>
                        </div>
                     </div>
                  </div>
                  <div class="col-lg-4 col-md-6">
                     <div class="card">
                        <div class="card-body">
                           <h5 class="mb-0">Saques do Mês</h5>
                           <canvas id="withdrawals-bar-chart"></canvas>
                        </div>
                     </div>
                  </div>
                  <div class="col-lg-4 col-md-6">
                     <div class="card">
                        <div class="card-body">
                           <h5 class="mb-0">Cadastros do Mês</h5>
                           <canvas id="registrations-bar-chart"></canvas>
                        </div>
                     </div>
                  </div>
               </div>
               <script>
                  // Dados dos gráficos do PHP para JavaScript
                  const depositosMes = <?php echo json_encode($depositosMes); ?>;
                  const saquesMes = <?php echo json_encode($saquesMes); ?>;
                  const cadastrosMes = <?php echo json_encode($cadastrosMes); ?>;

                  // Gráfico de Depósitos
                  const depositsCtx = document.getElementById('deposits-bar-chart').getContext('2d');
                  const depositsChart = new Chart(depositsCtx, {
                     type: 'bar',
                     data: {
                        labels: ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'],
                        datasets: [{
                           label: 'Depósitos',
                           data: depositosMes,
                           backgroundColor: 'rgba(75, 192, 192, 0.2)',
                           borderColor: 'rgba(75, 192, 192, 1)',
                           borderWidth: 1
                        }]
                     },
                     options: {
                        scales: {
                           y: {
                              beginAtZero: true
                           }
                        }
                     }
                  });

                  // Gráfico de Saques
                  const withdrawalsCtx = document.getElementById('withdrawals-bar-chart').getContext('2d');
                  const withdrawalsChart = new Chart(withdrawalsCtx, {
                     type: 'bar',
                     data: {
                        labels: ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'],
                        datasets: [{
                           label: 'Saques',
                           data: saquesMes,
                           backgroundColor: 'rgba(255, 99, 132, 0.2)',
                           borderColor: 'rgba(255, 99, 132, 1)',
                           borderWidth: 1
                        }]
                     },
                     options: {
                        scales: {
                           y: {
                              beginAtZero: true
                           }
                        }
                     }
                  });

                  // Gráfico de Cadastros
                  const registrationsCtx = document.getElementById('registrations-bar-chart').getContext('2d');
                  const registrationsChart = new Chart(registrationsCtx, {
                     type: 'bar',
                     data: {
                        labels: ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'],
                        datasets: [{
                           label: 'Cadastros',
                           data: cadastrosMes,
                           backgroundColor: 'rgba(54, 162, 235, 0.2)',
                           borderColor: 'rgba(54, 162, 235, 1)',
                           borderWidth: 1
                        }]
                     },
                     options: {
                        scales: {
                           y: {
                              beginAtZero: true
                           }
                        }
                     }
                  });
               </script>
               <div class="col-md-6">
                  <div class="card">
                     <div class="card-body border-bottom pb-0">
                        <div class="d-flex align-items-center justify-content-between">
                           <h5 class="mb-0">Transações</h5>
                        </div>
                        <ul class="nav nav-tabs analytics-tab" id="myTab" role="tablist">
                           <li class="nav-item" role="presentation">
                              <button
                                 class="nav-link active"
                                 id="analytics-tab-1"
                                 data-bs-toggle="tab"
                                 data-bs-target="#analytics-tab-1-pane"
                                 type="button"
                                 role="tab"
                                 aria-controls="analytics-tab-1-pane"
                                 aria-selected="true">Todas as Transações</button>
                           </li>
                           <li class="nav-item" role="presentation">
                              <button
                                 class="nav-link"
                                 id="analytics-tab-2"
                                 data-bs-toggle="tab"
                                 data-bs-target="#analytics-tab-2-pane"
                                 type="button"
                                 role="tab"
                                 aria-controls="analytics-tab-2-pane"
                                 aria-selected="false">Entradas</button>
                           </li>
                           <li class="nav-item" role="presentation">
                              <button
                                 class="nav-link"
                                 id="analytics-tab-3"
                                 data-bs-toggle="tab"
                                 data-bs-target="#analytics-tab-3-pane"
                                 type="button"
                                 role="tab"
                                 aria-controls="analytics-tab-3-pane"
                                 aria-selected="false">Saidas</button>
                           </li>
                        </ul>
                     </div>
                     <div class="tab-content" id="myTabContent">
                        <div
                           class="tab-pane fade show active"
                           id="analytics-tab-1-pane"
                           role="tabpanel"
                           aria-labelledby="analytics-tab-1"
                           tabindex="0">
                           <ul class="list-group list-group-flush">
                              <?php foreach ($transacoes as $transacao): ?>
                                 <li class="list-group-item">
                                    <div class="d-flex align-items-center">
                                       <div class="flex-shrink-0">
                                          <div class="avtar avtar-s border"> <?php echo strtoupper(substr($transacao['tipo'], 0, 2)); ?> </div>
                                       </div>
                                       <div class="flex-grow-1 ms-3">
                                          <div class="row g-1">
                                             <div class="col-6">
                                                <h6 class="mb-0"><?php echo ucfirst($transacao['tipo']); ?></h6>
                                                <p class="text-muted mb-0"><small><?php echo date('d/m/Y', strtotime($transacao['data'])); ?></small></p>
                                             </div>
                                             <div class="col-6 text-end">
                                                <h6 class="mb-1">R$ <?php echo number_format($transacao['valor'], 2, ',', '.'); ?></h6>
                                                <?php if ($transacao['tipo'] === 'saida'): ?>
                                                   <p class="text-danger mb-0"><i class="ti ti-arrow-down-left"></i></p>
                                                <?php else: ?>
                                                   <p class="text-success mb-0"><i class="ti ti-arrow-up-right"></i></p>
                                                <?php endif; ?>
                                             </div>
                                          </div>
                                       </div>
                                    </div>
                                 </li>
                              <?php endforeach; ?>
                           </ul>
                        </div>
                        <div class="tab-pane fade" id="analytics-tab-2-pane" role="tabpanel" aria-labelledby="analytics-tab-2" tabindex="0">
                           <ul class="list-group list-group-flush">
                              <?php foreach ($entradas as $entrada): ?>
                                 <li class="list-group-item">
                                    <div class="d-flex align-items-center">
                                       <div class="flex-shrink-0">
                                          <div class="avtar avtar-s border"> EN </div>
                                       </div>
                                       <div class="flex-grow-1 ms-3">
                                          <div class="row g-1">
                                             <div class="col-6">
                                                <h6 class="mb-0">Entrada</h6>
                                                <p class="text-muted mb-0"><small><?php echo date('d/m/Y', strtotime($entrada['data'])); ?></small></p>
                                             </div>
                                             <div class="col-6 text-end">
                                                <h6 class="mb-1">R$ <?php echo number_format($entrada['valor'], 2, ',', '.'); ?></h6>
                                                <p class="text-success mb-0"><i class="ti ti-arrow-up-right"></i></p>
                                             </div>
                                          </div>
                                       </div>
                                    </div>
                                 </li>
                              <?php endforeach; ?>
                           </ul>
                        </div>
                        <div class="tab-pane fade" id="analytics-tab-3-pane" role="tabpanel" aria-labelledby="analytics-tab-3" tabindex="0">
                           <ul class="list-group list-group-flush">
                              <?php foreach ($saidas as $saida): ?>
                                 <li class="list-group-item">
                                    <div class="d-flex align-items-center">
                                       <div class="flex-shrink-0">
                                          <div class="avtar avtar-s border"> SA </div>
                                       </div>
                                       <div class="flex-grow-1 ms-3">
                                          <div class="row g-1">
                                             <div class="col-6">
                                                <h6 class="mb-0">Saída</h6>
                                                <p class="text-muted mb-0"><small><?php echo date('d/m/Y', strtotime($saida['data'])); ?></small></p>
                                             </div>
                                             <div class="col-6 text-end">
                                                <h6 class="mb-1">R$ <?php echo number_format($saida['valor'], 2, ',', '.'); ?></h6>
                                                <p class="text-danger mb-0"><i class="ti ti-arrow-down-left"></i></p>
                                             </div>
                                          </div>
                                       </div>
                                    </div>
                                 </li>
                              <?php endforeach; ?>
                           </ul>
                        </div>
                     </div>
                     <div class="card-footer">
                        <div class="row g-2">
                           <div class="col-md-6">
                              <div class="d-grid">
                                 <a href="../entradas-geral/" class="btn btn-primary d-grid">
                                    <span class="text-truncate w-100">Ver todas as Entradas</span>
                                 </a>

                              </div>
                           </div>
                           <div class="col-md-6">
                              <div class="d-grid">
                                 <a href="../saidas-usuarios/" class="btn btn-primary d-grid">
                                    <span class="text-truncate w-100">Ver todas as Saidas</span>
                                 </a>

                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="col-md-6">
                  <div class="card">
                     <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                           <h5 class="mb-0">Gráfico de Visitas (Off)</h5>
                        </div>
                        <div id="total-income-graph"></div>
                        <div class="row g-3 mt-3">
                           <div class="col-sm-6">
                              <div class="bg-body p-3 rounded">
                                 <div class="d-flex align-items-center mb-2">
                                    <div class="flex-shrink-0">
                                       <span class="p-1 d-block bg-primary rounded-circle">
                                          <span class="visually-hidden">New alerts</span>
                                       </span>
                                    </div>
                                    <div class="flex-grow-1 ms-2">
                                       <p class="mb-0">Visitas agora</p>
                                    </div>
                                 </div>
                                 <h6 class="mb-0">423</h6>
                              </div>
                           </div>
                           <div class="col-sm-6">
                              <div class="bg-body p-3 rounded">
                                 <div class="d-flex align-items-center mb-2">
                                    <div class="flex-shrink-0">
                                       <span class="p-1 d-block bg-warning rounded-circle">
                                          <span class="visually-hidden">New alerts</span>
                                       </span>
                                    </div>
                                    <div class="flex-grow-1 ms-2">
                                       <p class="mb-0">Visitas de Hoje</p>
                                    </div>
                                 </div>
                                 <h6 class="mb-0">534</h6>
                              </div>
                           </div>
                           <div class="col-sm-6">
                              <div class="bg-body p-3 rounded">
                                 <div class="d-flex align-items-center mb-2">
                                    <div class="flex-shrink-0">
                                       <span class="p-1 d-block bg-success rounded-circle">
                                          <span class="visually-hidden">New alerts</span>
                                       </span>
                                    </div>
                                    <div class="flex-grow-1 ms-2">
                                       <p class="mb-0">Visitas do Mes</p>
                                    </div>
                                 </div>
                                 <h6 class="mb-0">42432</h6>
                              </div>
                           </div>
                           <div class="col-sm-6">
                              <div class="bg-body p-3 rounded">
                                 <div class="d-flex align-items-center mb-2">
                                    <div class="flex-shrink-0">
                                       <span class="p-1 d-block bg-danger rounded-circle">
                                          <span class="visually-hidden">New alerts</span>
                                       </span>
                                    </div>
                                    <div class="flex-grow-1 ms-2">
                                       <p class="mb-0">Usuarios Onlines</p>
                                    </div>
                                 </div>
                                 <h6 class="mb-0">4324</h6>
                              </div>
                           </div>
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
      <div class="pct-c-btn">
         <a href="#" data-bs-toggle="offcanvas" data-bs-target="#offcanvas_pc_layout">
            <i class="ph-duotone ph-gear-six"></i>
         </a>
      </div>
      <div class="offcanvas border-0 pct-offcanvas offcanvas-end" tabindex="-1" id="offcanvas_pc_layout">
         <div class="offcanvas-header">
            <h5 class="offcanvas-title">Configuração</h5>
            <button type="button" class="btn btn-icon btn-link-danger ms-auto" data-bs-dismiss="offcanvas" aria-label="Close"><i class="ti ti-x"></i></button>
         </div>
         <div class="pct-body customizer-body">
            <div class="offcanvas-body py-0">
               <ul class="list-group list-group-flush">
                  <li class="list-group-item">
                     <div class="pc-dark">
                        <h6 class="mb-1">Modo Tema</h6>
                        <p class="text-muted text-sm">Escolha o modo claro ou escuro ou Auto</p>
                        <div class="row theme-color theme-layout">
                           <div class="col-4">
                              <div class="d-grid">
                                 <button
                                    class="preset-btn btn active"
                                    data-value="true"
                                    onclick="layout_change('light');"
                                    data-bs-toggle="tooltip"
                                    title="Light">
                                    <svg class="pc-icon text-warning">
                                       <use xlink:href="#custom-sun-1"></use>
                                    </svg>
                                 </button>
                              </div>
                           </div>
                           <div class="col-4">
                              <div class="d-grid">
                                 <button class="preset-btn btn" data-value="false" onclick="layout_change('dark');" data-bs-toggle="tooltip" title="Dark">
                                    <svg class="pc-icon">
                                       <use xlink:href="#custom-moon"></use>
                                    </svg>
                                 </button>
                              </div>
                           </div>
                           <div class="col-4">
                              <div class="d-grid">
                                 <button
                                    class="preset-btn btn"
                                    data-value="default"
                                    onclick="layout_change_default();"
                                    data-bs-toggle="tooltip"
                                    title="Automatically sets the theme based on user's operating system's color scheme.">
                                    <span class="pc-lay-icon d-flex align-items-center justify-content-center">
                                       <i class="ph-duotone ph-cpu"></i>
                                    </span>
                                 </button>
                              </div>
                           </div>
                        </div>
                     </div>
                  </li>
                  <li class="list-group-item">
                     <h6 class="mb-1">Contraste do tema</h6>
                     <p class="text-muted text-sm">Escolha o contraste do tema</p>
                     <div class="row theme-contrast">
                        <div class="col-6">
                           <div class="d-grid">
                              <button
                                 class="preset-btn btn"
                                 data-value="true"
                                 onclick="layout_theme_contrast_change('true');"
                                 data-bs-toggle="tooltip"
                                 title="True">
                                 <svg class="pc-icon">
                                    <use xlink:href="#custom-mask"></use>
                                 </svg>
                              </button>
                           </div>
                        </div>
                        <div class="col-6">
                           <div class="d-grid">
                              <button
                                 class="preset-btn btn active"
                                 data-value="false"
                                 onclick="layout_theme_contrast_change('false');"
                                 data-bs-toggle="tooltip"
                                 title="False">
                                 <svg class="pc-icon">
                                    <use xlink:href="#custom-mask-1-outline"></use>
                                 </svg>
                              </button>
                           </div>
                        </div>
                     </div>
                  </li>
                  <li class="list-group-item">
                     <h6 class="mb-1">Tema personalizado</h6>
                     <p class="text-muted text-sm">Escolha a cor principal do seu tema</p>
                     <div class="theme-color preset-color">
                        <a href="#!" data-bs-toggle="tooltip" title="Azul" class="active" data-value="preset-1"><i class="ti ti-checks"></i></a>
                        <a href="#!" data-bs-toggle="tooltip" title="Lilás" data-value="preset-2"><i class="ti ti-checks"></i></a>
                        <a href="#!" data-bs-toggle="tooltip" title="Roxo" data-value="preset-3"><i class="ti ti-checks"></i></a>
                        <a href="#!" data-bs-toggle="tooltip" title="Rosa" data-value="preset-4"><i class="ti ti-checks"></i></a>
                        <a href="#!" data-bs-toggle="tooltip" title="Vermelho" data-value="preset-5"><i class="ti ti-checks"></i></a>
                        <a href="#!" data-bs-toggle="tooltip" title="Laranja" data-value="preset-6"><i class="ti ti-checks"></i></a>
                        <a href="#!" data-bs-toggle="tooltip" title="Amarelo" data-value="preset-7"><i class="ti ti-checks"></i></a>
                        <a href="#!" data-bs-toggle="tooltip" title="Verde" data-value="preset-8"><i class="ti ti-checks"></i></a>
                        <a href="#!" data-bs-toggle="tooltip" title="Verde Escuro" data-value="preset-9"><i class="ti ti-checks"></i></a>
                        <a href="#!" data-bs-toggle="tooltip" title="Azul Ciano" data-value="preset-10"><i class="ti ti-checks"></i></a>
                     </div>
                  </li>
                  <li class="list-group-item">
                     <h6 class="mb-1">Layout do tema</h6>
                     <p class="text-muted text-sm">Escolha seu layout</p>
                     <div class="theme-main-layout d-flex align-center gap-1 w-100">
                        <a href="#!" data-bs-toggle="tooltip" title="Vertical" class="active" data-value="vertical">
                           <img src="../assets/images/customizer/caption-on.svg" alt="img" class="img-fluid" />
                        </a>
                        <a href="#!" data-bs-toggle="tooltip" title="Horizontal" data-value="horizontal">
                           <img src="../assets/images/customizer/horizontal.svg" alt="img" class="img-fluid" />
                        </a>
                        <a href="#!" data-bs-toggle="tooltip" title="Color Header" data-value="color-header">
                           <img src="../assets/images/customizer/color-header.svg" alt="img" class="img-fluid" />
                        </a>
                        <a href="#!" data-bs-toggle="tooltip" title="Compact" data-value="compact">
                           <img src="../assets/images/customizer/compact.svg" alt="img" class="img-fluid" />
                        </a>
                        <a href="#!" data-bs-toggle="tooltip" title="Tab" data-value="tab">
                           <img src="../assets/images/customizer/tab.svg" alt="img" class="img-fluid" />
                        </a>
                     </div>
                  </li>
                  <li class="list-group-item">
                     <h6 class="mb-1">Escolha seu layout</h6>
                     <p class="text-muted text-sm">Ocultar/Mostrar Legenda da Barra Lateral</p>
                     <div class="row theme-color theme-nav-caption">
                        <div class="col-6">
                           <div class="d-grid">
                              <button
                                 class="preset-btn btn-img btn active"
                                 data-value="true"
                                 onclick="layout_caption_change('true');"
                                 data-bs-toggle="tooltip"
                                 title="Caption Show">
                                 <img src="../assets/images/customizer/caption-on.svg" alt="img" class="img-fluid" />
                              </button>
                           </div>
                        </div>
                        <div class="col-6">
                           <div class="d-grid">
                              <button
                                 class="preset-btn btn-img btn"
                                 data-value="false"
                                 onclick="layout_caption_change('false');"
                                 data-bs-toggle="tooltip"
                                 title="Caption Hide">
                                 <img src="../assets/images/customizer/caption-off.svg" alt="img" class="img-fluid" />
                              </button>
                           </div>
                        </div>
                     </div>
                  </li>
                  <li class="list-group-item">
                     <div class="pc-rtl">
                        <h6 class="mb-1">Layout do tema</h6>
                        <p class="text-muted text-sm">LTR/RTL</p>
                        <div class="row theme-color theme-direction">
                           <div class="col-6">
                              <div class="d-grid">
                                 <button
                                    class="preset-btn btn-img btn active"
                                    data-value="false"
                                    onclick="layout_rtl_change('false');"
                                    data-bs-toggle="tooltip"
                                    title="LTR">
                                    <img src="../assets/images/customizer/ltr.svg" alt="img" class="img-fluid" />
                                 </button>
                              </div>
                           </div>
                           <div class="col-6">
                              <div class="d-grid">
                                 <button
                                    class="preset-btn btn-img btn"
                                    data-value="true"
                                    onclick="layout_rtl_change('true');"
                                    data-bs-toggle="tooltip"
                                    title="RTL">
                                    <img src="../assets/images/customizer/rtl.svg" alt="img" class="img-fluid" />
                                 </button>
                              </div>
                           </div>
                        </div>
                     </div>
                  </li>
                  <li class="list-group-item pc-box-width">
                     <div class="pc-container-width">
                        <h6 class="mb-1">Largura do layout</h6>
                        <p class="text-muted text-sm">Escolha o layout completo ou contêiner</p>
                        <div class="row theme-color theme-container">
                           <div class="col-6">
                              <div class="d-grid">
                                 <button
                                    class="preset-btn btn-img btn active"
                                    data-value="false"
                                    onclick="change_box_container('false')"
                                    data-bs-toggle="tooltip"
                                    title="Full Width">
                                    <img src="../assets/images/customizer/full.svg" alt="img" class="img-fluid" />
                                 </button>
                              </div>
                           </div>
                           <div class="col-6">
                              <div class="d-grid">
                                 <button
                                    class="preset-btn btn-img btn"
                                    data-value="true"
                                    onclick="change_box_container('true')"
                                    data-bs-toggle="tooltip"
                                    title="Fixed Width">
                                    <img src="../assets/images/customizer/fixed.svg" alt="img" class="img-fluid" />
                                 </button>
                              </div>
                           </div>
                        </div>
                     </div>
                  </li>
                  <li class="list-group-item">
                     <div class="d-grid">
                        <button class="btn btn-light-danger" id="layoutreset">Redefinir Layout</button>
                     </div>
                  </li>
               </ul>
            </div>
         </div>
      </div>
      <script src="../assets/js/plugins/apexcharts.min.js"></script>
      <script src="../assets/js/pages/dashboard-default.js"></script>
      <script src="../assets/js/plugins/popper.min.js"></script>
      <script src="../assets/js/plugins/simplebar.min.js"></script>
      <script src="../assets/js/plugins/bootstrap.min.js"></script>
      <script src="../assets/js/fonts/custom-font.js"></script>
      <script src="../assets/js/pcoded.js"></script>
      <script src="../assets/js/plugins/feather.min.js"></script>
      <script>
         layout_change('dark');
      </script>
      <script>
         layout_theme_contrast_change('false');
      </script>
      <script>
         change_box_container('false');
      </script>
      <script>
         layout_caption_change('true');
      </script>
      <script>
         layout_rtl_change('false');
      </script>
      <script>
         preset_change('preset-1');
      </script>
      <script>
         main_layout_change('vertical');
      </script>
   </body>

</html>