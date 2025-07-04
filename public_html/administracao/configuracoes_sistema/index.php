<!doctype html>
<html lang="pt-BR">

<head>
   <title>Configurações Gerais - Dashboard</title>
   <meta charset="utf-8" />
   <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
   <meta http-equiv="X-UA-Compatible" content="IE=edge" />
   <link rel="icon" href="../assets/images/favicon.svg" type="image/x-icon" />
   <link rel="stylesheet" href="../assets/fonts/inter/inter.css" id="main-font-link" />
   <link rel="stylesheet" href="../assets/fonts/tabler-icons.min.css" />
   <link rel="stylesheet" href="../assets/fonts/feather.css" />
   <link rel="stylesheet" href="../assets/fonts/fontawesome.css" />
   <link rel="stylesheet" href="../assets/fonts/material.css" />
   <link rel="stylesheet" href="../assets/css/style.css" id="main-style-link" />
   <link rel="stylesheet" href="../assets/css/style-preset.css" />
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
</head>

<body data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-layout="vertical" data-pc-direction="ltr" data-pc-theme_contrast="" data-pc-theme="dark">
   
   <!-- [ Pre-loader ] start -->
   <div class="page-loader">
      <div class="bar"></div>
   </div>
   <!-- [ Pre-loader ] End -->

   <!-- [ Sidebar Menu ] start -->
   <nav class="pc-sidebar">
      <div class="navbar-wrapper">
         <div class="m-header">
            <a href="../dashboard/" class="b-brand text-primary">
               <img src="../assets/images/logo-white.svg" alt="logo" class="logo logo-lg" />
            </a>
         </div>
         <div class="navbar-content">
            <ul class="pc-navbar">
               <li class="pc-item">
                  <a href="../dashboard/" class="pc-link">
                     <span class="pc-micon"><i class="ph-duotone ph-gauge"></i></span>
                     <span class="pc-mtext">Dashboard</span>
                  </a>
               </li>
               <li class="pc-item pc-caption">
                  <label>Configurações</label>
               </li>
               <li class="pc-item active">
                  <a href="index.php" class="pc-link">
                     <span class="pc-micon"><i class="ph-duotone ph-gear-six"></i></span>
                     <span class="pc-mtext">Configurações Gerais</span>
                  </a>
               </li>
            </ul>
         </div>
      </div>
   </nav>
   <!-- [ Sidebar Menu ] end -->

   <!-- [ Header Topbar ] start -->
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
         <div class="ms-auto">
            <ul class="list-unstyled">
               <li class="dropdown pc-h-item">
                  <a class="pc-head-link dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#" role="button">
                     <i class="ph-duotone ph-user-circle"></i>
                  </a>
                  <div class="dropdown-menu dropdown-menu-end pc-h-dropdown">
                     <a href="../dashboard/" class="dropdown-item">
                        <i class="ph-duotone ph-gauge"></i>
                        <span>Dashboard</span>
                     </a>
                     <a href="#!" class="dropdown-item">
                        <i class="ph-duotone ph-sign-out"></i>
                        <span>Sair</span>
                     </a>
                  </div>
               </li>
            </ul>
         </div>
      </div>
   </header>
   <!-- [ Header ] end -->

   <!-- [ Main Content ] start -->
   <div class="pc-container">
      <div class="pc-content">
         <!-- [ breadcrumb ] start -->
         <div class="page-header">
            <div class="page-block">
               <div class="row align-items-center">
                  <div class="col-md-12">
                     <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../dashboard/">Dashboard</a></li>
                        <li class="breadcrumb-item" aria-current="page">Configurações Gerais</li>
                     </ul>
                  </div>
                  <div class="col-md-12">
                     <div class="page-header-title">
                        <h2 class="mb-0 animate__animated animate__fadeInDown">🎛️ Configurações Gerais</h2>
                        <p class="text-muted animate__animated animate__fadeInUp">Configure todos os parâmetros do sistema de forma centralizada</p>
                     </div>
                  </div>
               </div>
            </div>
         </div>
         <!-- [ breadcrumb ] end -->

         <!-- [ Main Content ] start -->
         <div class="row justify-content-center">
            <div class="col-xl-12">
               
               <!-- Hero Section -->
               <div class="row mb-5">
                  <div class="col-12">
                     <div class="card border-0 hero-card">
                        <div class="card-body text-center py-5">
                           <div class="hero-icon mb-4">
                              <i class="ph-duotone ph-gear-six f-48 text-primary"></i>
                           </div>
                           <h3 class="text-white mb-3">Centro de Configurações</h3>
                           <p class="text-white-75 mb-0 lead">
                              Gerencie todos os aspectos da sua plataforma em um só lugar. 
                              Configure valores, percentuais e regras de negócio.
                           </p>
                        </div>
                     </div>
                  </div>
               </div>

               <!-- Cards de Configuração -->
               <div class="row">
                  
                  <!-- Editar Checklist -->
                  <div class="col-lg-4 col-md-6 mb-4">
                     <div class="card config-card bg-gradient-primary border-0 shadow-lg h-100 animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
                        <div class="card-body text-center d-flex flex-column justify-content-between p-4">
                           <div>
                              <div class="config-icon mb-4">
                                 <div class="icon-wrapper">
                                    <i class="ph-duotone ph-check-square f-32 text-white"></i>
                                 </div>
                              </div>
                              <h4 class="text-white mb-3 fw-bold">Editar Checklist</h4>
                              <p class="text-white-75 mb-4">
                                 Configure os valores de recompensa para cada dia do checklist diário
                              </p>
                           </div>
                           <div>
                              <a href="editar_checklist/" class="btn btn-light btn-lg w-100 config-btn">
                                 <i class="ph-duotone ph-gear me-2"></i>
                                 Configurar Valores
                              </a>
                           </div>
                        </div>
                     </div>
                  </div>

                  <!-- Editar Comissões -->
                  <div class="col-lg-4 col-md-6 mb-4">
                     <div class="card config-card bg-gradient-success border-0 shadow-lg h-100 animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
                        <div class="card-body text-center d-flex flex-column justify-content-between p-4">
                           <div>
                              <div class="config-icon mb-4">
                                 <div class="icon-wrapper">
                                    <i class="ph-duotone ph-percent f-32 text-white"></i>
                                 </div>
                              </div>
                              <h4 class="text-white mb-3 fw-bold">Editar Comissões</h4>
                              <p class="text-white-75 mb-4">
                                 Defina os percentuais de comissão para cada nível da rede de afiliados
                              </p>
                           </div>
                           <div>
                              <a href="editar_comissoes/" class="btn btn-light btn-lg w-100 config-btn">
                                 <i class="ph-duotone ph-gear me-2"></i>
                                 Configurar Percentuais
                              </a>
                           </div>
                        </div>
                     </div>
                  </div>

                  <!-- Editar Saques -->
                  <div class="col-lg-4 col-md-6 mb-4">
                     <div class="card config-card bg-gradient-warning border-0 shadow-lg h-100 animate__animated animate__fadeInUp" style="animation-delay: 0.3s">
                        <div class="card-body text-center d-flex flex-column justify-content-between p-4">
                           <div>
                              <div class="config-icon mb-4">
                                 <div class="icon-wrapper">
                                    <i class="ph-duotone ph-currency-dollar f-32 text-white"></i>
                                 </div>
                              </div>
                              <h4 class="text-white mb-3 fw-bold">Editar Saques</h4>
                              <p class="text-white-75 mb-4">
                                 Configure valores mínimos, máximos, taxas e regras de saque
                              </p>
                           </div>
                           <div>
                              <a href="editar_saque/" class="btn btn-light btn-lg w-100 config-btn">
                                 <i class="ph-duotone ph-gear me-2"></i>
                                 Configurar Saques
                              </a>
                           </div>
                        </div>
                     </div>
                  </div>

                  <!-- Editar Depósitos -->
                  <div class="col-lg-4 col-md-6 mb-4">
                     <div class="card config-card bg-gradient-info border-0 shadow-lg h-100 animate__animated animate__fadeInUp" style="animation-delay: 0.4s">
                        <div class="card-body text-center d-flex flex-column justify-content-between p-4">
                           <div>
                              <div class="config-icon mb-4">
                                 <div class="icon-wrapper">
                                    <i class="ph-duotone ph-credit-card f-32 text-white"></i>
                                 </div>
                              </div>
                              <h4 class="text-white mb-3 fw-bold">Editar Depósitos</h4>
                              <p class="text-white-75 mb-4">
                                 Configure valores mínimos, bônus e métodos de pagamento
                              </p>
                           </div>
                           <div>
                              <a href="editar_deposito/" class="btn btn-light btn-lg w-100 config-btn">
                                 <i class="ph-duotone ph-gear me-2"></i>
                                 Configurar Depósitos
                              </a>
                           </div>
                        </div>
                     </div>
                  </div>

                  <!-- Editar Roleta -->
                  <div class="col-lg-4 col-md-6 mb-4">
                     <div class="card config-card bg-gradient-danger border-0 shadow-lg h-100 animate__animated animate__fadeInUp" style="animation-delay: 0.5s">
                        <div class="card-body text-center d-flex flex-column justify-content-between p-4">
                           <div>
                              <div class="config-icon mb-4">
                                 <div class="icon-wrapper">
                                    <i class="ph-duotone ph-circle-notch f-32 text-white"></i>
                                 </div>
                              </div>
                              <h4 class="text-white mb-3 fw-bold">Editar Roleta</h4>
                              <p class="text-white-75 mb-4">
                                 Configure prêmios, probabilidades e regras da roleta da sorte
                              </p>
                           </div>
                           <div>
                              <a href="editar_roleta/" class="btn btn-light btn-lg w-100 config-btn">
                                 <i class="ph-duotone ph-gear me-2"></i>
                                 Configurar Roleta
                              </a>
                           </div>
                        </div>
                     </div>
                  </div>

                  <!-- Editar Salários -->
                  <div class="col-lg-4 col-md-6 mb-4">
                     <div class="card config-card bg-gradient-salary border-0 shadow-lg h-100 animate__animated animate__fadeInUp" style="animation-delay: 0.6s">
                        <div class="card-body text-center d-flex flex-column justify-content-between p-4">
                           <div>
                              <div class="config-icon mb-4">
                                 <div class="icon-wrapper">
                                    <i class="ph-duotone ph-money f-32 text-white"></i>
                                 </div>
                              </div>
                              <h4 class="text-white mb-3 fw-bold">Editar Salários</h4>
                              <p class="text-white-75 mb-4">
                                 Configure níveis de qualificação, valores de salário e requisitos MLM
                              </p>
                           </div>
                           <div>
                              <a href="editar_salarios/" class="btn btn-light btn-lg w-100 config-btn">
                                 <i class="ph-duotone ph-gear me-2"></i>
                                 Configurar Salários
                              </a>
                           </div>
                        </div>
                     </div>
                  </div>

                  <!-- MODIFICADO: Editar Cadastro (substitui Configurações Avançadas) -->
                  <div class="col-lg-4 col-md-6 mb-4">
                     <div class="card config-card bg-gradient-purple border-0 shadow-lg h-100 animate__animated animate__fadeInUp" style="animation-delay: 0.7s">
                        <div class="card-body text-center d-flex flex-column justify-content-between p-4">
                           <div>
                              <div class="config-icon mb-4">
                                 <div class="icon-wrapper">
                                    <i class="ph-duotone ph-user-circle-gear f-32 text-white"></i>
                                 </div>
                              </div>
                              <h4 class="text-white mb-3 fw-bold">Editar Cadastro</h4>
                              <p class="text-white-75 mb-4">
                                 Gerencie informações de cadastro, dados de usuários e perfis do sistema
                              </p>
                           </div>
                           <div>
                              <a href="editar_cadastro/" class="btn btn-light btn-lg w-100 config-btn">
                                 <i class="ph-duotone ph-user-gear me-2"></i>
                                 Gerenciar Cadastros
                              </a>
                           </div>
                        </div>
                     </div>
                  </div>

               </div>

               <!-- Seção de Informações Úteis -->
               <div class="row mt-5">
                  <div class="col-12">
                     <div class="card border-0 info-card">
                        <div class="card-body">
                           <div class="row align-items-center">
                              <div class="col-md-2 text-center">
                                 <div class="info-icon">
                                    <i class="ph-duotone ph-info f-40 text-info"></i>
                                 </div>
                              </div>
                              <div class="col-md-8">
                                 <h5 class="text-info mb-2">💡 Dicas Importantes</h5>
                                 <p class="mb-2">
                                    <strong>Backup:</strong> Sempre faça backup das configurações antes de grandes alterações.
                                 </p>
                                 <p class="mb-2">
                                    <strong>Teste:</strong> Use ambiente de teste para validar configurações críticas.
                                 </p>
                                 <p class="mb-0">
                                    <strong>Monitoramento:</strong> Acompanhe métricas após mudanças de configuração.
                                 </p>
                              </div>
                              <div class="col-md-2 text-center">
                                 <div class="pulse-animation">
                                    <i class="ph-duotone ph-heart f-24 text-danger"></i>
                                 </div>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>

               <!-- Botão Voltar Estilizado -->
               <div class="row mt-5">
                  <div class="col-12 text-center">
                     <a href="../dashboard/" class="btn btn-outline-light btn-lg back-btn animate__animated animate__fadeInUp" style="animation-delay: 0.8s;">
                        <i class="ph-duotone ph-arrow-left me-2"></i>
                        Voltar ao Dashboard
                     </a>
                  </div>
               </div>

            </div>
         </div>
         <!-- [ Main Content ] end -->
      </div>
   </div>
   <!-- [ Main Content ] end -->

   <script src="../assets/js/plugins/popper.min.js"></script>
   <script src="../assets/js/plugins/simplebar.min.js"></script>
   <script src="../assets/js/plugins/bootstrap.min.js"></script>
   <script src="../assets/js/fonts/custom-font.js"></script>
   <script src="../assets/js/pcoded.js"></script>
   <script src="../assets/js/plugins/feather.min.js"></script>

   <script>
      layout_change('dark');
   </script>

   <style>
      /* ===============================================
         GRADIENTES PERSONALIZADOS
         =============================================== */
      .bg-gradient-primary {
         background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      }
      .bg-gradient-success {
         background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
      }
      .bg-gradient-warning {
         background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
      }
      .bg-gradient-info {
         background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
      }
      .bg-gradient-danger {
         background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
      }
      .bg-gradient-purple {
         background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
      }
      
      /* Gradiente para Editar Salários */
      .bg-gradient-salary {
         background: linear-gradient(135deg, #FF6B6B 0%, #4ECDC4 50%, #45B7D1 100%);
      }

      /* ===============================================
         HERO CARD
         =============================================== */
      .hero-card {
         background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
         border-radius: 20px;
         overflow: hidden;
         position: relative;
      }
      .hero-card::before {
         content: '';
         position: absolute;
         top: 0;
         left: 0;
         right: 0;
         bottom: 0;
         background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
         pointer-events: none;
      }
      .hero-icon {
         display: inline-block;
         padding: 20px;
         background: rgba(255,255,255,0.1);
         border-radius: 50%;
         backdrop-filter: blur(10px);
      }

      /* ===============================================
         CONFIG CARDS
         =============================================== */
      .config-card {
         transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
         border-radius: 20px;
         overflow: hidden;
         position: relative;
      }
      .config-card::before {
         content: '';
         position: absolute;
         top: 0;
         left: 0;
         right: 0;
         bottom: 0;
         background: rgba(255,255,255,0.1);
         opacity: 0;
         transition: opacity 0.3s ease;
      }
      .config-card:hover {
         transform: translateY(-15px) scale(1.02);
         box-shadow: 0 20px 40px rgba(0,0,0,0.2);
      }
      .config-card:hover::before {
         opacity: 1;
      }

      /* ===============================================
         CONFIG ICONS
         =============================================== */
      .config-icon {
         position: relative;
      }
      .icon-wrapper {
         display: inline-block;
         padding: 20px;
         background: rgba(255,255,255,0.15);
         border-radius: 50%;
         backdrop-filter: blur(10px);
         transition: all 0.3s ease;
      }
      .config-card:hover .icon-wrapper {
         transform: scale(1.1) rotate(5deg);
         background: rgba(255,255,255,0.25);
      }

      /* ===============================================
         BOTÕES CORRIGIDOS - VERSÃO PRINCIPAL
         =============================================== */
      .config-btn {
         transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
         border-radius: 15px;
         font-weight: 600;
         text-transform: uppercase;
         letter-spacing: 0.5px;
         display: block;
         padding: 12px 20px;
         
         /* Cores corrigidas para melhor contraste */
         background-color: rgba(255, 255, 255, 0.95);
         color: #2c3e50;
         text-decoration: none;
         border: 2px solid transparent;
         
         /* Efeitos visuais melhorados */
         backdrop-filter: blur(8px);
         box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
         
         /* Garantir que links não herdem estilos problemáticos */
         outline: none;
      }

      .config-btn:hover,
      .config-btn:focus,
      .config-btn:active {
         transform: translateY(-3px);
         box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
         
         /* Estados de hover corrigidos */
         background-color: #ffffff;
         color: #1a252f;
         text-decoration: none;
         border-color: rgba(255, 255, 255, 0.4);
         
         /* Sombra interna sutil */
         box-shadow: 
            0 8px 25px rgba(0, 0, 0, 0.2),
            inset 0 1px 3px rgba(0, 0, 0, 0.1);
      }

      .config-btn:visited {
         color: #2c3e50;
         text-decoration: none;
      }

      /* Fallback para casos onde o contraste ainda não esteja ideal */
      .config-card .config-btn {
         color: #2c3e50;
         background-color: rgba(255, 255, 255, 0.95);
      }

      .config-card .config-btn:hover {
         color: #1a252f;
         background-color: #ffffff;
      }

      /* ===============================================
         BOTÃO VOLTAR CORRIGIDO
         =============================================== */
      .back-btn {
         border-radius: 50px;
         padding: 15px 30px;
         border: 2px solid rgba(255,255,255,0.4);
         background: rgba(255,255,255,0.1);
         backdrop-filter: blur(10px);
         transition: all 0.3s ease;
         color: #ffffff;
         text-decoration: none;
         font-weight: 500;
         display: inline-block;
      }

      .back-btn:hover,
      .back-btn:focus,
      .back-btn:active {
         background: rgba(255,255,255,0.2);
         border-color: rgba(255,255,255,0.7);
         transform: translateY(-2px);
         color: #ffffff;
         text-decoration: none;
         box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
      }

      .back-btn:visited {
         color: #ffffff;
         text-decoration: none;
      }

      /* ===============================================
         INFO CARD
         =============================================== */
      .info-card {
         background: rgba(255,255,255,0.05);
         backdrop-filter: blur(10px);
         border-radius: 20px;
         border: 1px solid rgba(255,255,255,0.1);
      }
      .info-icon {
         padding: 15px;
         background: rgba(23, 162, 184, 0.1);
         border-radius: 50%;
         display: inline-block;
      }

      /* ===============================================
         UTILITY CLASSES
         =============================================== */
      .text-white-75 {
         color: rgba(255,255,255,0.75);
      }
      .fw-bold {
         font-weight: 700;
      }

      /* ===============================================
         ANIMAÇÕES
         =============================================== */
      .pulse-animation {
         animation: pulse 2s infinite;
      }
      @keyframes pulse {
         0% { transform: scale(1); }
         50% { transform: scale(1.1); }
         100% { transform: scale(1); }
      }

      /* ===============================================
         RESPONSIVIDADE
         =============================================== */
      @media (max-width: 768px) {
         .config-card {
            margin-bottom: 20px;
         }
         .hero-card .card-body {
            padding: 30px 20px;
         }
         .config-btn {
            font-size: 14px;
            padding: 10px 15px;
         }
         .back-btn {
            padding: 12px 25px;
            font-size: 14px;
         }
      }

      /* ===============================================
         ACESSIBILIDADE
         =============================================== */
      .config-btn:focus,
      .back-btn:focus {
         outline: 2px solid rgba(255, 255, 255, 0.8);
         outline-offset: 2px;
      }

      /* Garantir que todos os links sejam acessíveis */
      a:focus {
         outline: 2px solid rgba(255, 255, 255, 0.6);
         outline-offset: 2px;
      }

      /* ===============================================
         OVERRIDE DE ESTILOS PROBLEMÁTICOS
         =============================================== */
      /* Remove qualquer !important que possa estar interferindo */
      .btn.config-btn {
         color: #2c3e50;
         background-color: rgba(255, 255, 255, 0.95);
         border-color: transparent;
      }

      .btn.config-btn:hover {
         color: #1a252f;
         background-color: #ffffff;
         border-color: rgba(255, 255, 255, 0.4);
      }

      /* Garantir que Bootstrap não sobrescreva */
      .btn-light.config-btn {
         color: #2c3e50;
         background-color: rgba(255, 255, 255, 0.95);
         border-color: transparent;
      }

      .btn-light.config-btn:hover {
         color: #1a252f;
         background-color: #ffffff;
         border-color: rgba(255, 255, 255, 0.4);
      }
   </style>

</body>
</html>