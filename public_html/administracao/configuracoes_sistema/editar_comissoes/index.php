<?php
session_start();
require_once '../../bank/db.php';

// Verificar se é admin
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../");
    exit();
}

$pdo = getDBConnection();
$user_id = $_SESSION['user_id']; 

$sql = "SELECT cargo FROM usuarios WHERE id = :user_id";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['cargo'] !== 'admin') {
    header('Location: ../../');
    exit();
}

// Buscar configurações atuais de comissão
try {
    $stmt = $pdo->query("SELECT * FROM configuracao_comissoes ORDER BY nivel ASC");
    $comissoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular estatísticas
    $total_niveis = count($comissoes);
    $niveis_ativos = count(array_filter($comissoes, fn($c) => $c['ativo']));
    $percentual_total = array_sum(array_column($comissoes, 'percentual'));
    
} catch (Exception $e) {
    $comissoes = [];
    $error_message = "Erro ao carregar configurações: " . $e->getMessage();
}
?>

<!doctype html>
<html lang="pt-BR">
<head>
   <title>Configurar Comissões - Dashboard</title>
   <meta charset="utf-8" />
   <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
   <meta http-equiv="X-UA-Compatible" content="IE=edge" />
   <link rel="icon" href="../../assets/images/favicon.svg" type="image/x-icon" />
   <link rel="stylesheet" href="../../assets/fonts/inter/inter.css" id="main-font-link" />
   <link rel="stylesheet" href="../../assets/fonts/tabler-icons.min.css" />
   <link rel="stylesheet" href="../../assets/fonts/feather.css" />
   <link rel="stylesheet" href="../../assets/fonts/fontawesome.css" />
   <link rel="stylesheet" href="../../assets/fonts/material.css" />
   <link rel="stylesheet" href="../../assets/css/style.css" id="main-style-link" />
   <link rel="stylesheet" href="../../assets/css/style-preset.css" />
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
   <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            <a href="../../dashboard/" class="b-brand text-primary">
               <img src="../../assets/images/logo-white.svg" alt="logo" class="logo logo-lg" />
            </a>
         </div>
         <div class="navbar-content">
            <ul class="pc-navbar">
               <li class="pc-item">
                  <a href="../../dashboard/" class="pc-link">
                     <span class="pc-micon"><i class="ph-duotone ph-gauge"></i></span>
                     <span class="pc-mtext">Dashboard</span>
                  </a>
               </li>
               <li class="pc-item pc-caption">
                  <label>Configurações</label>
               </li>
               <li class="pc-item">
                  <a href="../" class="pc-link">
                     <span class="pc-micon"><i class="ph-duotone ph-gear-six"></i></span>
                     <span class="pc-mtext">Configurações Gerais</span>
                  </a>
               </li>
               <li class="pc-item active">
                  <a href="index.php" class="pc-link">
                     <span class="pc-micon"><i class="ph-duotone ph-percent"></i></span>
                     <span class="pc-mtext">Configurar Comissões</span>
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
                     <a href="../../dashboard/" class="dropdown-item">
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
                        <li class="breadcrumb-item"><a href="../../dashboard/">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="../">Configurações Gerais</a></li>
                        <li class="breadcrumb-item" aria-current="page">Configurar Comissões</li>
                     </ul>
                  </div>
                  <div class="col-md-12">
                     <div class="page-header-title">
                        <h2 class="mb-0 animate__animated animate__fadeInDown">💰 Configurar Comissões</h2>
                        <p class="text-muted animate__animated animate__fadeInUp">Configure os percentuais de comissão para cada nível da rede de afiliados</p>
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
                              <i class="ph-duotone ph-percent f-48 text-white"></i>
                           </div>
                           <h3 class="text-white mb-3">Sistema de Comissões MLM</h3>
                           <p class="text-white-75 mb-0 lead">
                              Configure percentuais, ative/desative níveis e defina a estrutura de remuneração da sua rede.
                           </p>
                        </div>
                     </div>
                  </div>
               </div>

               <!-- Estatísticas -->
               <div class="row mb-5">
                  <div class="col-md-3">
                     <div class="card bg-gradient-primary border-0 shadow-lg animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
                        <div class="card-body text-center">
                           <div class="d-flex align-items-center justify-content-center">
                              <div class="flex-grow-1">
                                 <h3 class="text-white mb-1"><?= $total_niveis ?></h3>
                                 <p class="text-white-75 mb-0">Total de Níveis</p>
                              </div>
                              <div class="flex-shrink-0">
                                 <i class="ph-duotone ph-stack f-36 text-white"></i>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
                  <div class="col-md-3">
                     <div class="card bg-gradient-success border-0 shadow-lg animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
                        <div class="card-body text-center">
                           <div class="d-flex align-items-center justify-content-center">
                              <div class="flex-grow-1">
                                 <h3 class="text-white mb-1"><?= $niveis_ativos ?></h3>
                                 <p class="text-white-75 mb-0">Níveis Ativos</p>
                              </div>
                              <div class="flex-shrink-0">
                                 <i class="ph-duotone ph-check-circle f-36 text-white"></i>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
                  <div class="col-md-3">
                     <div class="card bg-gradient-warning border-0 shadow-lg animate__animated animate__fadeInUp" style="animation-delay: 0.3s">
                        <div class="card-body text-center">
                           <div class="d-flex align-items-center justify-content-center">
                              <div class="flex-grow-1">
                                 <h3 class="text-white mb-1"><?= number_format($percentual_total, 1) ?>%</h3>
                                 <p class="text-white-75 mb-0">Total Percentual</p>
                              </div>
                              <div class="flex-shrink-0">
                                 <i class="ph-duotone ph-percent f-36 text-white"></i>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
                  <div class="col-md-3">
                     <div class="card bg-gradient-info border-0 shadow-lg animate__animated animate__fadeInUp" style="animation-delay: 0.4s">
                        <div class="card-body text-center">
                           <div class="d-flex align-items-center justify-content-center">
                              <div class="flex-grow-1">
                                 <h3 class="text-white mb-1">MLM</h3>
                                 <p class="text-white-75 mb-0">Sistema Ativo</p>
                              </div>
                              <div class="flex-shrink-0">
                                 <i class="ph-duotone ph-network f-36 text-white"></i>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>

               <!-- Ações Rápidas -->
               <div class="row mb-5">
                  <div class="col-12">
                     <div class="card border-0 action-card">
                        <div class="card-body">
                           <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                              <h5 class="mb-0">Ações Rápidas</h5>
                              <div class="d-flex gap-2 flex-wrap">
                                 <button class="btn btn-primary" onclick="adicionarNivel()">
                                    <i class="ph-duotone ph-plus me-2"></i>
                                    Adicionar Nível
                                 </button>
                                 <button class="btn btn-success" onclick="ativarTodos()">
                                    <i class="ph-duotone ph-check-circle me-2"></i>
                                    Ativar Todos
                                 </button>
                                 <button class="btn btn-warning" onclick="desativarTodos()">
                                    <i class="ph-duotone ph-x-circle me-2"></i>
                                    Desativar Todos
                                 </button>
                                 <button class="btn btn-info" onclick="restaurarPadrao()">
                                    <i class="ph-duotone ph-arrow-clockwise me-2"></i>
                                    Restaurar Padrão
                                 </button>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>

               <!-- Lista de Comissões -->
               <div class="row">
                  <?php if (empty($comissoes)): ?>
                  <div class="col-12">
                     <div class="card border-0 text-center py-5">
                        <div class="card-body">
                           <i class="ph-duotone ph-percent f-64 text-muted mb-3"></i>
                           <h4 class="text-muted">Nenhuma comissão configurada</h4>
                           <p class="text-muted mb-4">Configure os níveis de comissão para ativar o sistema MLM</p>
                           <button class="btn btn-primary" onclick="adicionarNivel()">
                              <i class="ph-duotone ph-plus me-2"></i>
                              Criar Primeiro Nível
                           </button>
                        </div>
                     </div>
                  </div>
                  <?php else: ?>
                  <?php foreach ($comissoes as $index => $comissao): ?>
                  <div class="col-lg-6 col-xl-4 mb-4">
                     <div class="card commission-card border-0 shadow-lg animate__animated animate__fadeInUp" style="animation-delay: <?= $index * 0.1 ?>s">
                        <div class="card-body">
                           <!-- Header do Card -->
                           <div class="d-flex justify-content-between align-items-center mb-3">
                              <div class="commission-level">
                                 Nível <?= $comissao['nivel'] ?>
                              </div>
                              <div class="commission-status">
                                 <label class="toggle-switch">
                                    <input type="checkbox" <?= $comissao['ativo'] ? 'checked' : '' ?> 
                                           onchange="alterarStatus(<?= $comissao['id'] ?>, this.checked)">
                                    <span class="slider"></span>
                                 </label>
                              </div>
                           </div>

                           <!-- Percentual -->
                           <div class="percentage-display text-center mb-4">
                              <?= number_format($comissao['percentual'], 2) ?>%
                           </div>

                           <!-- Formulário de Edição -->
                           <form onsubmit="atualizarComissao(event, <?= $comissao['id'] ?>)">
                              <div class="form-group mb-3">
                                 <label class="form-label">Percentual (%)</label>
                                 <input type="number" 
                                        class="form-control" 
                                        name="percentual" 
                                        value="<?= $comissao['percentual'] ?>" 
                                        step="0.01" 
                                        min="0" 
                                        max="50" 
                                        required>
                              </div>
                              
                              <div class="form-group mb-4">
                                 <label class="form-label">Descrição</label>
                                 <input type="text" 
                                        class="form-control" 
                                        name="descricao" 
                                        value="<?= htmlspecialchars($comissao['descricao']) ?>" 
                                        placeholder="Descrição do nível">
                              </div>

                              <!-- Botões de Ação -->
                              <div class="d-flex gap-2">
                                 <button type="submit" class="btn btn-primary flex-fill">
                                    <i class="ph-duotone ph-floppy-disk me-2"></i>
                                    Salvar
                                 </button>
                                 <button type="button" class="btn btn-danger" 
                                         onclick="excluirNivel(<?= $comissao['id'] ?>, <?= $comissao['nivel'] ?>)">
                                    <i class="ph-duotone ph-trash"></i>
                                 </button>
                              </div>
                           </form>
                        </div>
                     </div>
                  </div>
                  <?php endforeach; ?>
                  <?php endif; ?>
               </div>

               <!-- Simulador de Comissões -->
               <div class="row mt-5">
                  <div class="col-12">
                     <div class="card border-0 simulator-card">
                        <div class="card-header bg-gradient-info text-white">
                           <h5 class="mb-0">
                              <i class="ph-duotone ph-calculator me-2"></i>
                              Simulador de Comissões
                           </h5>
                        </div>
                        <div class="card-body">
                           <div class="row align-items-center">
                              <div class="col-md-4">
                                 <label class="form-label">Valor do Investimento (R$)</label>
                                 <input type="number" id="valorSimulacao" class="form-control" value="1000" 
                                        step="0.01" min="1" onchange="calcularSimulacao()">
                              </div>
                              <div class="col-md-8">
                                 <div id="resultadoSimulacao" class="mt-3">
                                    <!-- Resultado será preenchido via JavaScript -->
                                 </div>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>

               <!-- Botão Voltar -->
               <div class="row mt-5">
                  <div class="col-12 text-center">
                     <a href="../" class="btn btn-outline-light btn-lg back-btn animate__animated animate__fadeInUp">
                        <i class="ph-duotone ph-arrow-left me-2"></i>
                        Voltar às Configurações
                     </a>
                  </div>
               </div>

            </div>
         </div>
         <!-- [ Main Content ] end -->
      </div>
   </div>
   <!-- [ Main Content ] end -->

   <script src="../../assets/js/plugins/popper.min.js"></script>
   <script src="../../assets/js/plugins/simplebar.min.js"></script>
   <script src="../../assets/js/plugins/bootstrap.min.js"></script>
   <script src="../../assets/js/fonts/custom-font.js"></script>
   <script src="../../assets/js/pcoded.js"></script>
   <script src="../../assets/js/plugins/feather.min.js"></script>

   <script>
      layout_change('dark');

      // Dados das comissões atuais
      const comissoesAtuais = <?= json_encode($comissoes) ?>;

      // Atualizar comissão
      function atualizarComissao(event, id) {
         event.preventDefault();
         
         const form = event.target;
         const formData = new FormData(form);
         
         const data = {
            acao: 'atualizar',
            id: id,
            percentual: formData.get('percentual'),
            descricao: formData.get('descricao')
         };

         fetch('processar_comissoes.php', {
            method: 'POST',
            headers: {
               'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
         })
         .then(response => response.json())
         .then(data => {
            if (data.success) {
               Swal.fire('Sucesso', data.message, 'success').then(() => {
                  location.reload();
               });
            } else {
               Swal.fire('Erro', data.message, 'error');
            }
         })
         .catch(error => {
            console.error('Erro:', error);
            Swal.fire('Erro', 'Erro de conexão', 'error');
         });
      }

      // Alterar status ativo/inativo
      function alterarStatus(id, ativo) {
         const data = {
            acao: 'alterar_status',
            id: id,
            ativo: ativo
         };

         fetch('processar_comissoes.php', {
            method: 'POST',
            headers: {
               'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
         })
         .then(response => response.json())
         .then(data => {
            if (data.success) {
               Swal.fire({
                  icon: 'success',
                  title: 'Status Atualizado',
                  text: data.message,
                  toast: true,
                  position: 'top-end',
                  showConfirmButton: false,
                  timer: 3000
               });
            } else {
               Swal.fire('Erro', data.message, 'error');
               // Reverter o toggle se deu erro
               location.reload();
            }
         })
         .catch(error => {
            console.error('Erro:', error);
            Swal.fire('Erro', 'Erro de conexão', 'error');
            location.reload();
         });
      }

      // Adicionar novo nível
      function adicionarNivel() {
         Swal.fire({
            title: 'Adicionar Novo Nível',
            html: `
               <div class="text-start">
                  <div class="mb-3">
                     <label class="form-label">Nível</label>
                     <input type="number" id="novoNivel" class="form-control" min="1" max="10" value="${comissoesAtuais.length + 1}">
                  </div>
                  <div class="mb-3">
                     <label class="form-label">Percentual (%)</label>
                     <input type="number" id="novoPercentual" class="form-control" step="0.01" min="0" max="50" value="1.00">
                  </div>
                  <div class="mb-3">
                     <label class="form-label">Descrição</label>
                     <input type="text" id="novaDescricao" class="form-control" placeholder="Descrição do nível">
                  </div>
               </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Adicionar',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
               const nivel = document.getElementById('novoNivel').value;
               const percentual = document.getElementById('novoPercentual').value;
               const descricao = document.getElementById('novaDescricao').value;
               
               if (!nivel || !percentual) {
                  Swal.showValidationMessage('Preencha todos os campos obrigatórios');
                  return false;
               }
               
               return { nivel, percentual, descricao };
            }
         }).then((result) => {
            if (result.isConfirmed) {
               const data = {
                  acao: 'adicionar',
                  ...result.value
               };

               fetch('processar_comissoes.php', {
                  method: 'POST',
                  headers: {
                     'Content-Type': 'application/json',
                  },
                  body: JSON.stringify(data)
               })
               .then(response => response.json())
               .then(data => {
                  if (data.success) {
                     Swal.fire('Sucesso', data.message, 'success').then(() => {
                        location.reload();
                     });
                  } else {
                     Swal.fire('Erro', data.message, 'error');
                  }
               })
               .catch(error => {
                  console.error('Erro:', error);
                  Swal.fire('Erro', 'Erro de conexão', 'error');
               });
            }
         });
      }

      // Excluir nível
      function excluirNivel(id, nivel) {
         Swal.fire({
            title: 'Excluir Nível',
            text: `Tem certeza que deseja excluir o Nível ${nivel}? Esta ação não pode ser desfeita!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, excluir',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545'
         }).then((result) => {
            if (result.isConfirmed) {
               const data = {
                  acao: 'excluir',
                  id: id
               };

               fetch('processar_comissoes.php', {
                  method: 'POST',
                  headers: {
                     'Content-Type': 'application/json',
                  },
                  body: JSON.stringify(data)
               })
               .then(response => response.json())
               .then(data => {
                  if (data.success) {
                     Swal.fire('Excluído', data.message, 'success').then(() => {
                        location.reload();
                     });
                  } else {
                     Swal.fire('Erro', data.message, 'error');
                  }
               })
               .catch(error => {
                  console.error('Erro:', error);
                  Swal.fire('Erro', 'Erro de conexão', 'error');
               });
            }
         });
      }

      // Ativar todos os níveis
      function ativarTodos() {
         Swal.fire({
            title: 'Ativar Todos os Níveis',
            text: 'Deseja ativar todos os níveis de comissão?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sim, ativar',
            cancelButtonText: 'Cancelar'
         }).then((result) => {
            if (result.isConfirmed) {
               const data = {
                  acao: 'ativar_todos'
               };

               fetch('processar_comissoes.php', {
                  method: 'POST',
                  headers: {
                     'Content-Type': 'application/json',
                  },
                  body: JSON.stringify(data)
               })
               .then(response => response.json())
               .then(data => {
                  if (data.success) {
                     Swal.fire('Sucesso', data.message, 'success').then(() => {
                        location.reload();
                     });
                  } else {
                     Swal.fire('Erro', data.message, 'error');
                  }
               })
               .catch(error => {
                  console.error('Erro:', error);
                  Swal.fire('Erro', 'Erro de conexão', 'error');
               });
            }
         });
      }

      // Desativar todos os níveis
      function desativarTodos() {
         Swal.fire({
            title: 'Desativar Todos os Níveis',
            text: 'Deseja desativar todos os níveis de comissão?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, desativar',
            cancelButtonText: 'Cancelar'
         }).then((result) => {
            if (result.isConfirmed) {
               const data = {
                  acao: 'desativar_todos'
               };

               fetch('processar_comissoes.php', {
                  method: 'POST',
                  headers: {
                     'Content-Type': 'application/json',
                  },
                  body: JSON.stringify(data)
               })
               .then(response => response.json())
               .then(data => {
                  if (data.success) {
                     Swal.fire('Sucesso', data.message, 'success').then(() => {
                        location.reload();
                     });
                  } else {
                     Swal.fire('Erro', data.message, 'error');
                  }
               })
               .catch(error => {
                  console.error('Erro:', error);
                  Swal.fire('Erro', 'Erro de conexão', 'error');
               });
            }
         });
      }

      // Restaurar configuração padrão
      function restaurarPadrao() {
         Swal.fire({
            title: 'Restaurar Configuração Padrão',
            text: 'Isso irá restaurar os valores padrão: Nível 1 (5%), Nível 2 (3%), Nível 3 (2%). Confirmar?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sim, restaurar',
            cancelButtonText:'Cancelar'
         }).then((result) => {
            if (result.isConfirmed) {
               const data = {
                  acao: 'restaurar_padrao'
               };

               fetch('processar_comissoes.php', {
                  method: 'POST',
                  headers: {
                     'Content-Type': 'application/json',
                  },
                  body: JSON.stringify(data)
               })
               .then(response => response.json())
               .then(data => {
                  if (data.success) {
                     Swal.fire('Sucesso', data.message, 'success').then(() => {
                        location.reload();
                     });
                  } else {
                     Swal.fire('Erro', data.message, 'error');
                  }
               })
               .catch(error => {
                  console.error('Erro:', error);
                  Swal.fire('Erro', 'Erro de conexão', 'error');
               });
            }
         });
      }

      // Calcular simulação de comissões
      function calcularSimulacao() {
         const valor = parseFloat(document.getElementById('valorSimulacao').value) || 0;
         const resultadoDiv = document.getElementById('resultadoSimulacao');
         
         if (valor <= 0) {
            resultadoDiv.innerHTML = '<p class="text-muted">Digite um valor válido para simular</p>';
            return;
         }

         let htmlResultado = '<h6 class="mb-3">Simulação de Comissões:</h6>';
         let totalComissoes = 0;

         // Filtrar apenas comissões ativas
         const comissoesAtivas = comissoesAtuais.filter(c => c.ativo);
         
         if (comissoesAtivas.length === 0) {
            resultadoDiv.innerHTML = '<p class="text-warning">Nenhum nível ativo para simular</p>';
            return;
         }

         comissoesAtivas.forEach((comissao, index) => {
            const valorComissao = (valor * comissao.percentual) / 100;
            totalComissoes += valorComissao;
            
            htmlResultado += `
               <div class="row align-items-center mb-2">
                  <div class="col-md-4">
                     <span class="badge bg-primary">Nível ${comissao.nivel}</span>
                  </div>
                  <div class="col-md-4">
                     <span class="text-muted">${comissao.percentual}%</span>
                  </div>
                  <div class="col-md-4">
                     <strong class="text-success">R$ ${valorComissao.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</strong>
                  </div>
               </div>
            `;
         });

         htmlResultado += `
            <hr>
            <div class="row align-items-center">
               <div class="col-md-8">
                  <strong>Total de Comissões:</strong>
               </div>
               <div class="col-md-4">
                  <strong class="text-primary fs-5">R$ ${totalComissoes.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</strong>
               </div>
            </div>
            <div class="row align-items-center mt-2">
               <div class="col-md-8">
                  <span class="text-muted">Valor Líquido (após comissões):</span>
               </div>
               <div class="col-md-4">
                  <span class="text-muted">R$ ${(valor - totalComissoes).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
               </div>
            </div>
         `;

         resultadoDiv.innerHTML = htmlResultado;
      }

      // Executar simulação inicial
      document.addEventListener('DOMContentLoaded', function() {
         calcularSimulacao();
      });
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
         COMMISSION CARDS
         =============================================== */
      .commission-card {
         transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
         border-radius: 20px;
         overflow: hidden;
         position: relative;
         background: rgba(255,255,255,0.05);
         backdrop-filter: blur(10px);
         border: 1px solid rgba(255,255,255,0.1);
      }
      .commission-card:hover {
         transform: translateY(-10px) scale(1.02);
         box-shadow: 0 20px 40px rgba(0,0,0,0.2);
      }

      .commission-level {
         background: linear-gradient(135deg, #3b82f6, #1d4ed8);
         color: white;
         padding: 8px 16px;
         border-radius: 20px;
         font-weight: bold;
         font-size: 14px;
         display: inline-block;
      }

      .percentage-display {
         font-size: 2.5rem;
         font-weight: bold;
         color: #10b981;
         text-shadow: 0 2px 4px rgba(0,0,0,0.1);
      }

      /* ===============================================
         TOGGLE SWITCH
         =============================================== */
      .toggle-switch {
         position: relative;
         display: inline-block;
         width: 60px;
         height: 30px;
      }
      .toggle-switch input {
         opacity: 0;
         width: 0;
         height: 0;
      }
      .slider {
         position: absolute;
         cursor: pointer;
         top: 0;
         left: 0;
         right: 0;
         bottom: 0;
         background-color: #ccc;
         transition: .4s;
         border-radius: 34px;
      }
      .slider:before {
         position: absolute;
         content: "";
         height: 22px;
         width: 22px;
         left: 4px;
         bottom: 4px;
         background-color: white;
         transition: .4s;
         border-radius: 50%;
      }
      input:checked + .slider {
         background-color: #10b981;
      }
      input:checked + .slider:before {
         transform: translateX(30px);
      }

      /* ===============================================
         ACTION CARD
         =============================================== */
      .action-card {
         background: rgba(255,255,255,0.05);
         backdrop-filter: blur(10px);
         border-radius: 20px;
         border: 1px solid rgba(255,255,255,0.1);
      }

      /* ===============================================
         SIMULATOR CARD
         =============================================== */
      .simulator-card {
         background: rgba(255,255,255,0.05);
         backdrop-filter: blur(10px);
         border-radius: 20px;
         border: 1px solid rgba(255,255,255,0.1);
         overflow: hidden;
      }

      /* ===============================================
         FORM CONTROLS
         =============================================== */
      .form-control {
         background: rgba(255,255,255,0.1);
         border: 1px solid rgba(255,255,255,0.2);
         color: white;
         border-radius: 10px;
         transition: all 0.3s ease;
      }
      .form-control:focus {
         background: rgba(255,255,255,0.15);
         border-color: #3b82f6;
         box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
         color: white;
      }
      .form-control::placeholder {
         color: rgba(255,255,255,0.6);
      }

      .form-label {
         color: rgba(255,255,255,0.9);
         font-weight: 500;
         margin-bottom: 8px;
      }

      /* ===============================================
         BUTTONS
         =============================================== */
      .btn {
         border-radius: 10px;
         font-weight: 600;
         transition: all 0.3s ease;
         border: none;
      }
      .btn:hover {
         transform: translateY(-2px);
         box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      }

      /* ===============================================
         BACK BUTTON
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
      .back-btn:hover {
         background: rgba(255,255,255,0.2);
         border-color: rgba(255,255,255,0.7);
         transform: translateY(-2px);
         color: #ffffff;
         text-decoration: none;
         box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
      }

      /* ===============================================
         UTILITY CLASSES
         =============================================== */
      .text-white-75 {
         color: rgba(255,255,255,0.75);
      }

      /* ===============================================
         RESPONSIVIDADE
         =============================================== */
      @media (max-width: 768px) {
         .commission-card {
            margin-bottom: 20px;
         }
         .hero-card .card-body {
            padding: 30px 20px;
         }
         .percentage-display {
            font-size: 2rem;
         }
         .d-flex.gap-2.flex-wrap {
            flex-direction: column;
         }
         .btn {
            width: 100%;
            margin-bottom: 10px;
         }
      }
   </style>

</body>
</html>