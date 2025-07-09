<?php
session_start(); // Inicia a sessão

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
   header('Location: ../');
   exit();
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

// Função helper para formatar valores monetários
function formatMoney($value) {
    return 'R$ ' . number_format($value ?? 0, 2, ',', '.');
}

// Consultar os usuários com COALESCE para evitar valores null
$sql = "SELECT u.id, u.created_at as data_criacao, u.telefone, 
           COALESCE(c.saldo_principal, 0) as saldo, 
           COALESCE(c.saldo_comissao, 0) as saldo_comissao,
           (SELECT COUNT(*) FROM usuarios WHERE referenciado_por = u.id) AS convidados,
           COALESCE((SELECT SUM(valor_investido) FROM investimentos WHERE usuario_id = u.id), 0) AS total_investido,
           (SELECT COUNT(*) FROM investimentos WHERE usuario_id = u.id) AS produtos,
           COALESCE((SELECT SUM(valor_liquido) FROM operacoes_financeiras WHERE usuario_id = u.id AND tipo = 'deposito' AND status = 'aprovado'), 0) AS depositos,
           COALESCE((SELECT SUM(valor_liquido) FROM operacoes_financeiras WHERE usuario_id = u.id AND tipo = 'saque' AND status = 'aprovado'), 0) AS saques
           FROM usuarios u
           LEFT JOIN carteiras c ON u.id = c.usuario_id";

$stmt = $conn->prepare($sql);
$stmt->execute();
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
   <title>Investimentos - Dashboard</title>
   <meta charset="utf-8" />
   <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
   <meta http-equiv="X-UA-Compatible" content="IE=edge" />
   <meta name="author" content="seemniick" />
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
               <li class="pc-item active">
                  <a href="./" class="pc-link">
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
         <div class="page-header">
            <div class="page-block">
               <div class="row align-items-center">
                  <div class="col-md-12">
                     <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../dashboard/">Dashboard</a></li>
                        <li class="breadcrumb-item" aria-current="page">Usuários</li>
                     </ul>
                  </div>
                  <div class="col-md-12">
                     <div class="page-header-title">
                        <h2 class="mb-0">Todos os usuários</h2>
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
                        <h5 class="mb-3 mb-sm-0">Tenha controle de todos os usuários da plataforma.</h5>
                     </div>
                  </div>
                  <div class="card-body pt-3">
                     <div class="table-responsive">
                        <table class="table table-hover" id="pc-dt-simple">
                           <thead>
                              <tr>
                                 <th>ID</th>
                                 <th>DATA</th>
                                 <th>TELEFONE</th>
                                 <th>SALDO</th>
                                 <th>SALDO COMISSAO</th>
                                 <th>CONVIDADOS</th>
                                 <th>TOTAL INVESTIDO</th>
                                 <th>PRODUTOS</th>
                                 <th>DEPÓSITOS</th>
                                 <th>SAQUES</th>
                                 <th>AÇÃO</th>
                              </tr>
                           </thead>
                           <tbody>
                              <?php foreach ($usuarios as $usuario): ?>
                                 <tr>
                                    <td><?= htmlspecialchars($usuario['id']) ?></td>
                                    <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($usuario['data_criacao']))) ?></td>
                                    <td><?= htmlspecialchars($usuario['telefone']) ?></td>
                                    <td><?= formatMoney($usuario['saldo']) ?></td>
                                    <td><?= formatMoney($usuario['saldo_comissao']) ?></td>
                                    <td><?= htmlspecialchars($usuario['convidados']) ?></td>
                                    <td><?= formatMoney($usuario['total_investido']) ?></td>
                                    <td><?= htmlspecialchars($usuario['produtos']) ?></td>
                                    <td><?= formatMoney($usuario['depositos']) ?></td>
                                    <td><?= formatMoney($usuario['saques']) ?></td>
                                    <td>
                                       <a href="editar.php?id=<?= $usuario['id'] ?>" class="avtar avtar-xs btn-link-secondary">
                                          <i class="ti ti-edit f-20"></i>
                                       </a>
                                       <a href="#" class="avtar avtar-xs btn-link-secondary" onclick="confirmDelete(<?= $usuario['id'] ?>)">
                                          <i class="ti ti-trash f-20"></i>
                                       </a>
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

   <script>
      function confirmDelete(usuarioId) {
         Swal.fire({
            title: 'Você tem certeza?',
            text: "Esta ação não pode ser desfeita!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sim, excluir!',
            cancelButtonText: 'Não, cancelar'
         }).then((result) => {
            if (result.isConfirmed) {
               // Redireciona para o script de exclusão
               window.location.href = 'excluir.php?id=' + usuarioId;
            }
         });
      }

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