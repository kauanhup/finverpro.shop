<?php
session_start();

// Verificar se é admin
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../');
    exit();
}

// Incluir conexão
require '../../bank/db.php';

try {
    $conn = getDBConnection();
    
    // Verificar se é admin
    $stmt = $conn->prepare("SELECT cargo FROM usuarios WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || $user['cargo'] !== 'admin') {
        header('Location: ../../dashboard/');
        exit();
    }
    
    // Buscar configuração atual ou criar se não existir
    $stmt_config = $conn->prepare("SELECT * FROM checklist WHERE user_id = 0 AND tarefa = 'CONFIG_VALORES' LIMIT 1");
    $stmt_config->execute();
    $config = $stmt_config->fetch(PDO::FETCH_ASSOC);
    
    // Se não existir, criar com valores padrão
    if (!$config) {
        $stmt_insert = $conn->prepare("INSERT INTO checklist (user_id, tarefa, concluida, recompensa, valor_dia1, valor_dia2, valor_dia3, valor_dia4, valor_dia5, valor_dia6, valor_dia7) VALUES (0, 'CONFIG_VALORES', 0, 0.00, 1.00, 2.00, 3.00, 5.00, 8.00, 15.00, 25.00)");
        $stmt_insert->execute();
        
        // Buscar novamente
        $stmt_config->execute();
        $config = $stmt_config->fetch(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}

// Processar formulário
if ($_POST) {
    try {
        $valores = $_POST['valores'];
        
        // Validar valores
        foreach ($valores as $dia => $valor) {
            if (!is_numeric($valor) || $valor < 0 || $valor > 999.99) {
                throw new Exception("Valor inválido para o dia $dia");
            }
        }
        
        // Atualizar valores na tabela
        $stmt_update = $conn->prepare("
            UPDATE checklist SET 
            valor_dia1 = :dia1, 
            valor_dia2 = :dia2, 
            valor_dia3 = :dia3, 
            valor_dia4 = :dia4, 
            valor_dia5 = :dia5, 
            valor_dia6 = :dia6, 
            valor_dia7 = :dia7 
            WHERE user_id = 0 AND tarefa = 'CONFIG_VALORES'
        ");
        
        $stmt_update->execute([
            ':dia1' => $valores[1],
            ':dia2' => $valores[2],
            ':dia3' => $valores[3],
            ':dia4' => $valores[4],
            ':dia5' => $valores[5],
            ':dia6' => $valores[6],
            ':dia7' => $valores[7] ?? 25.00
        ]);
        
        $success = "Valores do checklist atualizados com sucesso!";
        
        // Recarregar dados
        $stmt_config->execute();
        $config = $stmt_config->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $error = "Erro ao atualizar: " . $e->getMessage();
    }
}

// Preparar dados para exibição
$valores_atuais = [
    1 => $config['valor_dia1'] ?? 1.00,
    2 => $config['valor_dia2'] ?? 2.00,
    3 => $config['valor_dia3'] ?? 3.00,
    4 => $config['valor_dia4'] ?? 5.00,
    5 => $config['valor_dia5'] ?? 8.00,
    6 => $config['valor_dia6'] ?? 15.00,
    7 => $config['valor_dia7'] ?? 25.00
];

// Calcular estatísticas
$total_6_dias = array_sum(array_slice($valores_atuais, 0, 6)); // Só os 6 primeiros dias
$media_valor = $total_6_dias / 6;
$maior_valor = max(array_slice($valores_atuais, 0, 6));
$menor_valor = min(array_slice($valores_atuais, 0, 6));
?>
<!doctype html>
<html lang="pt-BR">

<head>
   <title>Editar Valores do Checklist - Admin</title>
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
   <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
               <li class="pc-item">
                  <a href="../" class="pc-link">
                     <span class="pc-micon"><i class="ph-duotone ph-gear-six"></i></span>
                     <span class="pc-mtext">Configurações Gerais</span>
                  </a>
               </li>
               <li class="pc-item active">
                  <a href="index.php" class="pc-link">
                     <span class="pc-micon"><i class="ph-duotone ph-check-square"></i></span>
                     <span class="pc-mtext">Editar Checklist</span>
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
                        <li class="breadcrumb-item"><a href="../">Configurações</a></li>
                        <li class="breadcrumb-item" aria-current="page">Editar Checklist</li>
                     </ul>
                  </div>
                  <div class="col-md-12">
                     <div class="page-header-title">
                        <h2 class="mb-0 animate__animated animate__fadeInDown">💰 Editar Valores do Checklist</h2>
                        <p class="text-muted animate__animated animate__fadeInUp">Configure os valores em reais que os usuários ganham em cada dia</p>
                     </div>
                  </div>
               </div>
            </div>
         </div>
         <!-- [ breadcrumb ] end -->

         <!-- [ Main Content ] start -->
         <div class="row">
            
            <!-- Formulário Principal -->
            <div class="col-lg-8">
               <div class="card animate__animated animate__fadeInLeft">
                  <div class="card-header bg-primary">
                     <h5 class="text-white mb-0">
                        <i class="ph-duotone ph-currency-dollar me-2"></i>
                        Configurar Valores dos Dias
                     </h5>
                  </div>
                  <div class="card-body">
                     
                     <?php if (isset($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show animate__animated animate__bounceIn" role="alert">
                           <i class="ph-duotone ph-check-circle me-2"></i>
                           <?= $success ?>
                           <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                     <?php endif; ?>
                     
                     <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show animate__animated animate__shakeX" role="alert">
                           <i class="ph-duotone ph-x-circle me-2"></i>
                           <?= $error ?>
                           <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                     <?php endif; ?>

                     <form method="POST" id="checklistForm">
                        <div class="row">
                           <?php for ($i = 1; $i <= 6; $i++): ?>
                              <div class="col-lg-6 col-md-6 mb-4">
                                 <div class="card checklist-day-card border-0 shadow-sm animate__animated animate__fadeInUp" style="animation-delay: <?= ($i-1) * 0.1 ?>s">
                                    <div class="card-body">
                                       <div class="text-center mb-3">
                                          <div class="day-icon mb-3">
                                             <div class="day-number bg-primary text-white">
                                                <?= $i ?>
                                             </div>
                                          </div>
                                          <h5 class="mb-0 fw-bold">Dia <?= $i ?></h5>
                                          <small class="text-muted">Valor da recompensa</small>
                                       </div>
                                       
                                       <!-- Valor em Reais -->
                                       <div class="mb-3">
                                          <label class="form-label fw-bold">
                                             <i class="ph-duotone ph-currency-dollar me-1"></i>
                                             Valor (R$)
                                          </label>
                                          <div class="input-group input-group-lg">
                                             <span class="input-group-text bg-primary text-white">R$</span>
                                             <input 
                                                type="number" 
                                                step="0.01" 
                                                min="0" 
                                                max="999.99"
                                                class="form-control form-control-lg valor-input text-center fw-bold" 
                                                name="valores[<?= $i ?>]" 
                                                value="<?= number_format($valores_atuais[$i], 2, '.', '') ?>"
                                                placeholder="0.00"
                                                required
                                             />
                                          </div>
                                          <small class="text-muted">
                                             <i class="ph-duotone ph-info me-1"></i>
                                             Aceita centavos (ex: 0.50, 1.25, 10.99)
                                          </small>
                                       </div>

                                       <!-- Preview do valor -->
                                       <div class="alert alert-info alert-sm">
                                          <i class="ph-duotone ph-eye me-1"></i>
                                          <strong>Preview:</strong> "Você ganhou R$ <span class="preview-valor"><?= number_format($valores_atuais[$i], 2, ',', '.') ?></span>!"
                                       </div>
                                    </div>
                                 </div>
                              </div>
                           <?php endfor; ?>
                        </div>

                        <!-- Botões de Ação -->
                        <div class="row mt-4">
                           <div class="col-12">
                              <div class="d-flex justify-content-between flex-wrap gap-2">
                                 <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg animate__animated animate__pulse animate__infinite">
                                       <i class="ph-duotone ph-floppy-disk me-2"></i>
                                       Salvar Configurações
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-lg" onclick="resetForm()">
                                       <i class="ph-duotone ph-arrow-clockwise me-2"></i>
                                       Resetar
                                    </button>
                                 </div>
                                 <div>
                                    <a href="../" class="btn btn-outline-primary btn-lg">
                                       <i class="ph-duotone ph-arrow-left me-2"></i>
                                       Voltar
                                    </a>
                                 </div>
                              </div>
                           </div>
                        </div>
                     </form>
                  </div>
               </div>
            </div>

            <!-- Sidebar com Estatísticas -->
            <div class="col-lg-4">
               
               <!-- Card de Resumo -->
               <div class="card mb-4 animate__animated animate__fadeInRight">
                  <div class="card-header bg-success">
                     <h5 class="text-white mb-0">
                        <i class="ph-duotone ph-chart-bar me-2"></i>
                        Resumo dos Valores
                     </h5>
                  </div>
                  <div class="card-body">
                     <div class="d-flex justify-content-between py-2 border-bottom">
                        <span><i class="ph-duotone ph-plus-circle me-1 text-success"></i> Total dos 6 dias:</span>
                        <strong id="total-valor" class="text-success">R$ <?= number_format($total_6_dias, 2, ',', '.') ?></strong>
                     </div>
                     <div class="d-flex justify-content-between py-2 border-bottom">
                        <span><i class="ph-duotone ph-chart-line me-1 text-info"></i> Média por dia:</span>
                        <strong id="media-valor" class="text-info">R$ <?= number_format($media_valor, 2, ',', '.') ?></strong>
                     </div>
                     <div class="d-flex justify-content-between py-2 border-bottom">
                        <span><i class="ph-duotone ph-arrow-up me-1 text-warning"></i> Maior valor:</span>
                        <strong class="text-warning">R$ <?= number_format($maior_valor, 2, ',', '.') ?></strong>
                     </div>
                     <div class="d-flex justify-content-between py-2">
                        <span><i class="ph-duotone ph-arrow-down me-1 text-danger"></i> Menor valor:</span>
                        <strong class="text-danger">R$ <?= number_format($menor_valor, 2, ',', '.') ?></strong>
                     </div>
                  </div>
               </div>

               <!-- Card de Ações Rápidas -->
               <div class="card mb-4 animate__animated animate__fadeInRight" style="animation-delay: 0.2s">
                  <div class="card-header bg-warning">
                     <h5 class="text-white mb-0">
                        <i class="ph-duotone ph-lightning me-2"></i>
                        Ações Rápidas
                     </h5>
                  </div>
                  <div class="card-body">
                     <div class="d-grid gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="aplicarValoresPadrao()">
                           <i class="ph-duotone ph-magic-wand me-1"></i>
                           Valores Padrão (1, 2, 3, 5, 8, 15)
                        </button>
                        <button type="button" class="btn btn-outline-success btn-sm" onclick="aplicarCentavos()">
                           <i class="ph-duotone ph-coins me-1"></i>
                           Centavos (0.10, 0.25, 0.50...)
                        </button>
                        <button type="button" class="btn btn-outline-warning btn-sm" onclick="calcularProgressivo()">
                           <i class="ph-duotone ph-calculator me-1"></i>
                           Calcular Progressivo
                        </button>
                        <button type="button" class="btn btn-outline-info btn-sm" onclick="duplicarValor()">
                           <i class="ph-duotone ph-copy me-1"></i>
                           Duplicar Primeiro Valor
                        </button>
                     </div>
                  </div>
               </div>

               <!-- Card de Dicas -->
               <div class="card animate__animated animate__fadeInRight" style="animation-delay: 0.4s">
                  <div class="card-header bg-info">
                     <h5 class="text-white mb-0">
                        <i class="ph-duotone ph-lightbulb me-2"></i>
                        Dicas de Configuração
                     </h5>
                  </div>
                  <div class="card-body">
                     <div class="mb-3">
                        <h6 class="text-info"><i class="ph-duotone ph-trend-up me-1"></i> Valores Progressivos</h6>
                        <small class="text-muted">Configure valores crescentes para motivar os usuários.</small>
                     </div>
                     <div class="mb-3">
                        <h6 class="text-warning"><i class="ph-duotone ph-coins me-1"></i> Use Centavos</h6>
                        <small class="text-muted">Valores como 0.25, 0.50, 1.75 são perfeitamente válidos.</small>
                     </div>
                     <div class="mb-0">
                        <h6 class="text-success"><i class="ph-duotone ph-shield-check me-1"></i> Teste Sempre</h6>
                        <small class="text-muted">Teste com usuário de teste antes de aplicar em produção.</small>
                     </div>
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
   </script>

   <script>
      // Atualizar preview e resumo em tempo real
      function atualizarResumo() {
         const inputs = document.querySelectorAll('.valor-input');
         let total = 0;
         
         inputs.forEach((input, index) => {
            const valor = parseFloat(input.value) || 0;
            total += valor;
            
            // Atualizar preview
            const previewElement = input.closest('.card-body').querySelector('.preview-valor');
            if (previewElement) {
               previewElement.textContent = valor.toLocaleString('pt-BR', {
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 2
               });
            }
         });
         
         const media = total / inputs.length;
         
         document.getElementById('total-valor').textContent = 'R$ ' + total.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
         });
         
         document.getElementById('media-valor').textContent = 'R$ ' + media.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
         });
      }
      
      // Event listeners
      document.addEventListener('DOMContentLoaded', function() {
         const inputs = document.querySelectorAll('.valor-input');
         inputs.forEach(input => {
            input.addEventListener('input', atualizarResumo);
            input.addEventListener('focus', function() {
               this.select(); // Seleciona todo o texto ao focar
            });
         });
      });
      
      // Confirmação de salvamento
      document.getElementById('checklistForm').addEventListener('submit', function(e) {
         e.preventDefault();
         
         Swal.fire({
            title: 'Confirmar Alterações',
            text: 'Deseja realmente salvar os novos valores do checklist?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sim, salvar!',
            cancelButtonText: 'Cancelar',
            customClass: {
               confirmButton: 'btn btn-primary',
               cancelButton: 'btn btn-secondary'
            }
         }).then((result) => {
            if (result.isConfirmed) {
               this.submit();
            }
         });
      });
      
      // Funções das ações rápidas
      function aplicarValoresPadrao() {
         const valores = [1.00, 2.00, 3.00, 5.00, 8.00, 15.00];
         
         valores.forEach((valor, index) => {
            const input = document.querySelector(`input[name="valores[${index + 1}]"]`);
            if (input) input.value = valor.toFixed(2);
         });
         
         atualizarResumo();
         mostrarSucesso('Valores padrão aplicados!');
      }
      
      function aplicarCentavos() {
         const valores = [0.10, 0.25, 0.50, 0.75, 1.00, 1.25];
         
         valores.forEach((valor, index) => {
            const input = document.querySelector(`input[name="valores[${index + 1}]"]`);
            if (input) input.value = valor.toFixed(2);
         });
         
         atualizarResumo();
         mostrarSucesso('Valores em centavos aplicados!');
      }
      
      function calcularProgressivo() {
         const valorInicial = parseFloat(prompt('Digite o valor inicial (R$):') || '0.50');
         const incremento = parseFloat(prompt('Digite o incremento por dia (R$):') || '0.25');
         
         for (let i = 1; i <= 6; i++) {
            const valor = valorInicial + (incremento * (i - 1));
            const input = document.querySelector(`input[name="valores[${i}]"]`);
            if (input) input.value = valor.toFixed(2);
         }
         
         atualizarResumo();
         mostrarSucesso('Progressão calculada!');
      }
      
      function duplicarValor() {
         const primeiroValor = document.querySelector('input[name="valores[1]"]').value;
         const inputs = document.querySelectorAll('.valor-input');
         
         inputs.forEach(input => {
            input.value = primeiroValor;
         });
         
         atualizarResumo();
         mostrarSucesso(`Todos os dias agora valem R$ ${primeiroValor}`);
      }
      
      function resetForm() {
         Swal.fire({
            title: 'Resetar formulário?',
            text: 'Isso irá desfazer todas as alterações não salvas.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, resetar!',
            cancelButtonText: 'Cancelar'
         }).then((result) => {
            if (result.isConfirmed) {
               location.reload();
            }
         });
      }
      
      function mostrarSucesso(mensagem) {
         Swal.fire({
            icon: 'success',
            title: 'Sucesso!',
            text: mensagem,
            timer: 2000,
            showConfirmButton: false
         });
      }
   </script>

   <?php if (isset($success)): ?>
   <script>
      Swal.fire({
         icon: 'success',
         title: 'Sucesso!',
         text: '<?= $success ?>',
         timer: 3000,
         showConfirmButton: false
      });
   </script>
   <?php endif; ?>

   <style>
      .checklist-day-card {
         transition: all 0.3s ease;
         border-radius: 15px;
         overflow: hidden;
         border: 2px solid transparent;
      }
      .checklist-day-card:hover {
         transform: translateY(-5px);
         box-shadow: 0 10px 30px rgba(0,0,0,0.15) !important;
         border-color: var(--bs-primary);
      }
      
      .day-icon {
         display: flex;
         justify-content: center;
      }
      .day-number {
         width: 50px;
         height: 50px;
         border-radius: 50%;
         display: flex;
         align-items: center;
         justify-content: center;
         font-size: 20px;
         font-weight: bold;
      }
      
      .valor-input {
         transition: all 0.3s ease;
         font-size: 1.2rem;
      }
      .valor-input:focus {
         border-color: #4680ff;
         box-shadow: 0 0 20px rgba(70, 128, 255, 0.3);
         transform: scale(1.02);
      }
      
      .preview-valor {
         font-weight: bold;
         color: var(--bs-primary);
      }
      
      .animate__pulse.animate__infinite {
         animation-duration: 2s;
      }
      
      .input-group-lg .input-group-text {
         font-size: 1.25rem;
         font-weight: bold;
      }
      
      .alert-sm {
         padding: 0.5rem 0.75rem;
         font-size: 0.875rem;
      }
   </style>

</body>
</html>