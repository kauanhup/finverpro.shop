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

try {
   $conn = getDBConnection();

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

   // Consulta para buscar as cores
   $stmt = $conn->prepare("SELECT cor_1, cor_2, cor_3, cor_4, cor_5 FROM personalizar_cores LIMIT 1");
   $stmt->execute();
   $cores = $stmt->fetch(PDO::FETCH_ASSOC);

   // Caso a consulta não retorne valores, inicialize com padrões
   if (!$cores) {
      $cores = [
         'cor_1' => '#FFFFFF',
         'cor_2' => '#FFFFFF',
         'cor_3' => '#FFFFFF',
         'cor_4' => '#FFFFFF',
         'cor_5' => '#FFFFFF',
      ];
   }
} catch (Exception $e) {
   die("Erro ao buscar cores: " . $e->getMessage());
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
   <link rel="stylesheet" href="../assets/fonts/fontawesome.css" />
   <link rel="stylesheet" href="../assets/fonts/material.css" />
   <link rel="stylesheet" href="../assets/css/style.css" id="main-style-link" />
   <link rel="stylesheet" href="../assets/css/style-preset.css" />
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
                     <a href="./" class="pc-link">
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
      <section class="pc-container">
         <div class="pc-content">
            <div class="page-header">
               <div class="page-block">
                  <div class="row align-items-center">
                     <div class="col-md-12">
                        <ul class="breadcrumb">
                           <li class="breadcrumb-item"><a href="../dashboard/index.html">Dashboard</a></li>
                           <li class="breadcrumb-item" aria-current="page">Personalização de Cores</li>
                        </ul>
                     </div>
                     <div class="col-md-12">
                        <div class="page-header-title">
                           <h2 class="mb-0">Cores da Plataforma</h2>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
            <div class="row">
               <div class="col-md-12">
                  <div class="card">
                     <div class="card-header">
                        <h5>Cores Global</h5>
                     </div>
                     <div class="card-body" id="afiliados">
                        <form method="POST" action="salvar_cores.php">
                           <div class="row">
                              <div class="col-md-3 mb-3">
                                 <label class="form-label">Cor Primária</label>
                                 <div style="position: relative;">
                                    <input type="text" class="form-control color-input" id="color-input-1" name="cor_1" placeholder="Padrão: #FFFFFF" value="<?= htmlspecialchars($cores['cor_1']) ?>" />
                                    <input type="color" class="color-picker" id="color-picker-1" value="<?= htmlspecialchars($cores['cor_1']) ?>" />
                                 </div>
                              </div>
                              <div class="col-md-3 mb-3">
                                 <label class="form-label">Cor Secundária</label>
                                 <div style="position: relative;">
                                    <input type="text" class="form-control color-input" id="color-input-2" name="cor_2" placeholder="Padrão: #FFFFFF" value="<?= htmlspecialchars($cores['cor_2']) ?>" />
                                    <input type="color" class="color-picker" id="color-picker-2" value="<?= htmlspecialchars($cores['cor_2']) ?>" />
                                 </div>
                              </div>
                              <div class="col-md-3 mb-3">
                                 <label class="form-label">Cor Terciária</label>
                                 <div style="position: relative;">
                                    <input type="text" class="form-control color-input" id="color-input-3" name="cor_3" placeholder="Padrão: #FFFFFF" value="<?= htmlspecialchars($cores['cor_3']) ?>" />
                                    <input type="color" class="color-picker" id="color-picker-3" value="<?= htmlspecialchars($cores['cor_3']) ?>" />
                                 </div>
                              </div>
                              <div class="col-md-3 mb-3">
                                 <label class="form-label">Cor Quaternária</label>
                                 <div style="position: relative;">
                                    <input type="text" class="form-control color-input" id="color-input-4" name="cor_4" placeholder="Padrão: #FFFFFF" value="<?= htmlspecialchars($cores['cor_4']) ?>" />
                                    <input type="color" class="color-picker" id="color-picker-4" value="<?= htmlspecialchars($cores['cor_4']) ?>" />
                                 </div>
                              </div>
                              <div class="col-md-3 mb-3">
                                 <label class="form-label">Cor Quinternária</label>
                                 <div style="position: relative;">
                                    <input type="text" class="form-control color-input" id="color-input-5" name="cor_5" placeholder="Padrão: #FFFFFF" value="<?= htmlspecialchars($cores['cor_5']) ?>" />
                                    <input type="color" class="color-picker" id="color-picker-5" value="<?= htmlspecialchars($cores['cor_5']) ?>" />
                                 </div>
                              </div>

                           </div>
                           <button type="submit" class="btn btn-primary mb-4">Salvar Alterações</button>
                        </form>


                     </div>
                  </div>
               </div>
            </div>
         </div>
         </div>
      </section>
      <script>
         // Captura o evento de envio do formulário
         document.querySelector('form').addEventListener('submit', function(e) {
            e.preventDefault(); // Previne o envio padrão

            const formData = new FormData(this); // Captura os dados do formulário

            // Envia os dados para o PHP via fetch
            fetch(this.action, {
                  method: 'POST',
                  body: formData,
               })
               .then(response => response.json()) // Converte a resposta para JSON
               .then(data => {
                  if (data.success) {
                     // Mensagem de sucesso
                     Swal.fire({
                        icon: 'success',
                        title: 'Sucesso',
                        text: data.message,
                     });
                  } else {
                     // Mensagem de erro
                     Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: data.message,
                     });
                  }
               })
               .catch(() => {
                  // Mensagem de erro genérico
                  Swal.fire({
                     icon: 'error',
                     title: 'Erro',
                     text: 'Ocorreu um erro inesperado. Tente novamente mais tarde.',
                  });
               });
         });
      </script>
      <script>
         function updateColorIndicator(inputElement, color) {
            inputElement.style.background = `url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"><circle cx="12" cy="12" r="12" fill="${encodeURIComponent(color)}"/></svg>') no-repeat calc(100% - 10px) center`;
         }

         function setupColorInput(inputId, pickerId, defaultColor) {
            const colorInput = document.getElementById(inputId);
            const colorPicker = document.getElementById(pickerId);

            colorInput.addEventListener('input', function() {
               const color = this.value;
               const isValidColor = /^#[0-9A-F]{6}$/i.test(color);
               const colorIndicator = isValidColor ? color : 'transparent';
               updateColorIndicator(this, colorIndicator);

               if (isValidColor) {
                  colorPicker.value = color;
               }
            });

            colorPicker.addEventListener('input', function() {
               colorInput.value = this.value;
               colorInput.dispatchEvent(new Event('input')); // Trigger input event to update the circle color
            });

            colorInput.addEventListener('click', function(event) {
               if (event.offsetX > this.clientWidth - 40) {
                  colorPicker.click();
               }
            });

            // Set default color on load
            window.addEventListener('load', function() {
               updateColorIndicator(colorInput, defaultColor);
            });
         }

         // Setup color inputs
         setupColorInput('color-input-1', 'color-picker-1', '<?= htmlspecialchars($cores['cor_1']) ?>');
         setupColorInput('color-input-2', 'color-picker-2', '<?= htmlspecialchars($cores['cor_2']) ?>');
         setupColorInput('color-input-3', 'color-picker-3', '<?= htmlspecialchars($cores['cor_3']) ?>');
         setupColorInput('color-input-4', 'color-picker-4', '<?= htmlspecialchars($cores['cor_4']) ?>');
         setupColorInput('color-input-5', 'color-picker-5', '<?= htmlspecialchars($cores['cor_5']) ?>');
      </script>
      <style>
         .color-input {
            padding-right: 40px;
            /* Espaço para a bolinha no lado direito */
            position: relative;
            background-repeat: no-repeat;
            background-position: calc(100% - 10px) center;
         }

         .color-picker {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 24px;
            height: 24px;
            opacity: 0;
            cursor: pointer;
         }
      </style>
      <footer class="pc-footer">
         <div class="footer-wrapper container-fluid">
            <div class="row">
               <div class="col my-1">
                  <p class="m-0">Feito com muito &#9829; por <a href="https://t.me/devcorr3" target="_blank">Correa</a></p>
               </div>
               <div class="col-auto my-1">
                  <ul class="list-inline footer-link mb-0">
                     <li class="list-inline-item"><a href="../../inicio">Inicio</a></li>
                     <li class="list-inline-item"><a href="https://t.me/devcorr3" target="_blank">Correa</a></li>
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
      <script type="module">
         import {
            DataTable
         } from "../assets/js/plugins/module.js"
         window.dt = new DataTable("#pc-dt-simple");
      </script>
   </body>

</html>