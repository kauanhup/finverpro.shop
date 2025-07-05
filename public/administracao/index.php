<!doctype html>
<html lang="en">

<head>
   <title>Investimentos - Dashboard</title>
   <meta charset="utf-8" />
   <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
   <meta http-equiv="X-UA-Compatible" content="IE=edge" />
   <meta name="author" content="seemniick" />
   <link rel="icon" href="./assets/images/favicon.svg" type="image/x-icon" />
   <link rel="stylesheet" href="./assets/fonts/inter/inter.css" id="main-font-link" />
   <link rel="stylesheet" href="./assets/fonts/tabler-icons.min.css" />
   <link rel="stylesheet" href="./assets/fonts/feather.css" />
   <link rel="stylesheet" href="./assets/fonts/fontawesome.css" />
   <link rel="stylesheet" href="./assets/fonts/material.css" />
   <link rel="stylesheet" href="./assets/css/style.css" id="main-style-link" />
   <link rel="stylesheet" href="./assets/css/style-preset.css" />
</head>

<body data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-layout="vertical" data-pc-direction="ltr" data-pc-theme_contrast="" data-pc-theme="dark">
   <div class="page-loader">
      <div class="bar"></div>
   </div>
   <div class="auth-main">
      <div class="auth-wrapper v1">
         <div class="auth-form">
            <div class="card my-5">
               <div class="card-body">
                  <div class="text-center">
                     <a href="#"><img src="./assets/images/logo-white.svg" alt="img" /></a>
                  </div>
                  <div class="saprator my-3"></div>
                  <h4 class="text-center f-w-500 mb-3">Bem vindo ao Painel Adm</h4>
                  <form>
                     <div class="d-grid mt-4">
                        <a href="dashboard/" class="btn btn-primary text-center">Entrar na Dashboard</a>
                     </div>
                  </form>
               </div>
            </div>
         </div>
      </div>
   </div>

   <script src="./assets/js/plugins/popper.min.js"></script>
   <script src="./assets/js/plugins/simplebar.min.js"></script>
   <script src="./assets/js/plugins/bootstrap.min.js"></script>
   <script src="./assets/js/fonts/custom-font.js"></script>
   <script src="./assets/js/pcoded.js"></script>
   <script src="./assets/js/plugins/feather.min.js"></script>

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
                        <img src="./assets/images/customizer/caption-on.svg" alt="img" class="img-fluid" />
                     </a>
                     <a href="#!" data-bs-toggle="tooltip" title="Horizontal" data-value="horizontal">
                        <img src="./assets/images/customizer/horizontal.svg" alt="img" class="img-fluid" />
                     </a>
                     <a href="#!" data-bs-toggle="tooltip" title="Color Header" data-value="color-header">
                        <img src="./assets/images/customizer/color-header.svg" alt="img" class="img-fluid" />
                     </a>
                     <a href="#!" data-bs-toggle="tooltip" title="Compact" data-value="compact">
                        <img src="./assets/images/customizer/compact.svg" alt="img" class="img-fluid" />
                     </a>
                     <a href="#!" data-bs-toggle="tooltip" title="Tab" data-value="tab">
                        <img src="./assets/images/customizer/tab.svg" alt="img" class="img-fluid" />
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
                              <img src="./assets/images/customizer/caption-on.svg" alt="img" class="img-fluid" />
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
                              <img src="./assets/images/customizer/caption-off.svg" alt="img" class="img-fluid" />
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
                                 <img src="./assets/images/customizer/ltr.svg" alt="img" class="img-fluid" />
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
                                 <img src="./assets/images/customizer/rtl.svg" alt="img" class="img-fluid" />
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
                                 <img src="./assets/images/customizer/full.svg" alt="img" class="img-fluid" />
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
                                 <img src="./assets/images/customizer/fixed.svg" alt="img" class="img-fluid" />
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

</body>

</html>