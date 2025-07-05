<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
   header('Location: ../../../');
   exit();
}

// Incluir o arquivo de conexão com o banco de dados
require '../../bank/db.php';

// Criar a conexão
try {
   $pdo = getDBConnection();
} catch (Exception $e) {
   die("Erro de conexão: " . $e->getMessage());
}

// Obtém o id do usuário logado
$user_id = $_SESSION['user_id']; 

// Consultar a tabela 'usuarios' para verificar o cargo do usuário
$sql = "SELECT cargo FROM usuarios WHERE id = :user_id";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();

$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Se o usuário não for encontrado ou o cargo não for 'admin', redireciona
if (!$user || $user['cargo'] !== 'admin') {
   header('Location: ../../');
   exit();
}

// ===== CARREGAMENTO DE DADOS DA ROLETA =====
try {
   $stmt = $pdo->query("SELECT * FROM roleta WHERE id = 1");
   $config = $stmt->fetch(PDO::FETCH_ASSOC);
   
   if (!$config) {
      // Criar registro padrão se não existir
      $pdo->exec("INSERT INTO roleta (id) VALUES (1)");
      $stmt = $pdo->query("SELECT * FROM roleta WHERE id = 1");
      $config = $stmt->fetch(PDO::FETCH_ASSOC);
   }
} catch(Exception $e) {
   die("Erro ao carregar configurações da roleta: " . $e->getMessage());
}

// ===== PROCESSAMENTO DOS FORMULÁRIOS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   try {
      if (isset($_POST['save_quick'])) {
         // SALVAR CONFIGURAÇÕES RÁPIDAS NA TABELA ROLETA
         $sql = "UPDATE roleta SET 
                 roleta_ativa = ?,
                 valor_minimo_investimento = ?,
                 giros_por_investimento = ?,
                 giros_por_indicacao = ?,
                 limite_giros_dia = ?,
                 data_atualizacao = NOW()
                 WHERE id = 1";
         
         $stmt = $pdo->prepare($sql);
         $result = $stmt->execute([
            isset($_POST['roleta_ativa']) ? 1 : 0,
            $_POST['valor_minimo_investimento'],
            $_POST['giros_por_investimento'],
            $_POST['giros_por_indicacao'],
            $_POST['limite_giros_dia']
         ]);
         
         if ($result) {
            $success_message = "✅ Configurações rápidas salvas com sucesso!";
         }
      }
      
      if (isset($_POST['save_prizes'])) {
         // SALVAR TODOS OS 8 PRÊMIOS NA TABELA ROLETA
         $sqlParts = [];
         $params = [];
         
         for ($i = 1; $i <= 8; $i++) {
            $sqlParts[] = "premio_{$i}_nome = ?";
            $sqlParts[] = "premio_{$i}_tipo = ?";
            $sqlParts[] = "premio_{$i}_valor = ?";
            $sqlParts[] = "premio_{$i}_cor = ?";
            $sqlParts[] = "premio_{$i}_chance = ?";
            
            $params[] = $_POST["premio_{$i}_nome"];
            $params[] = $_POST["premio_{$i}_tipo"];
            $params[] = $_POST["premio_{$i}_valor"];
            $params[] = $_POST["premio_{$i}_cor"];
            $params[] = $_POST["premio_{$i}_chance"];
         }
         
         $sql = "UPDATE roleta SET " . implode(', ', $sqlParts) . ", data_atualizacao = NOW() WHERE id = 1";
         $stmt = $pdo->prepare($sql);
         $result = $stmt->execute($params);
         
         if ($result) {
            $success_message = "🎁 Todos os prêmios foram salvos com sucesso!";
         }
      }
      
      // Recarregar dados após salvar
      if (isset($success_message)) {
         header("Location: " . $_SERVER['PHP_SELF'] . "?success=" . urlencode($success_message));
         exit;
      }
      
   } catch(Exception $e) {
      $error_message = "❌ Erro ao salvar: " . $e->getMessage();
   }
}

// Verificar mensagens de sucesso na URL
if (isset($_GET['success'])) {
   $success_message = $_GET['success'];
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
   <title>Configurar Roleta - Dashboard</title>
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
                     <span class="pc-micon"><i class="ph-duotone ph-circle-notch"></i></span>
                     <span class="pc-mtext">Configurar Roleta</span>
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
                        <li class="breadcrumb-item" aria-current="page">Configurar Roleta</li>
                     </ul>
                  </div>
                  <div class="col-md-12">
                     <div class="page-header-title">
                        <h2 class="mb-0 animate__animated animate__fadeInDown">🎰 Configurar Roleta</h2>
                        <p class="text-muted animate__animated animate__fadeInUp">Configure prêmios, probabilidades e regras da roleta da sorte</p>
                     </div>
                  </div>
               </div>
            </div>
         </div>
         <!-- [ breadcrumb ] end -->

         <!-- Alerts -->
         <div id="alert-container">
            <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show animate__animated animate__fadeInDown" role="alert">
               <?= $success_message ?>
               <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show animate__animated animate__fadeInDown" role="alert">
               <?= $error_message ?>
               <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
         </div>

         <!-- [ Main Content ] start -->
         <div class="row justify-content-center">
            <div class="col-xl-12">
               
               <!-- Hero Section com Estatísticas -->
               <div class="row mb-5">
                  <div class="col-12">
                     <div class="card border-0 hero-card">
                        <div class="card-body text-center py-5">
                           <div class="hero-icon mb-4">
                              <i class="ph-duotone ph-circle-notch f-48 text-primary"></i>
                           </div>
                           <h3 class="text-white mb-3">Centro de Configuração da Roleta</h3>
                           <p class="text-white-75 mb-4 lead">
                              Configure todos os prêmios, probabilidades e regras da roleta da sorte.
                           </p>
                           <div class="hero-stats row text-center">
                              <div class="col-3">
                                 <h4 class="text-white mb-1" id="total-probability">
                                    <?= number_format(
                                       ($config['premio_1_chance'] ?? 0) + ($config['premio_2_chance'] ?? 0) + 
                                       ($config['premio_3_chance'] ?? 0) + ($config['premio_4_chance'] ?? 0) + 
                                       ($config['premio_5_chance'] ?? 0) + ($config['premio_6_chance'] ?? 0) + 
                                       ($config['premio_7_chance'] ?? 0) + ($config['premio_8_chance'] ?? 0), 1
                                    ) ?>%
                                 </h4>
                                 <small class="text-white-75">Probabilidade Total</small>
                              </div>
                              <div class="col-3">
                                 <h4 class="text-white mb-1" id="active-prizes">8</h4>
                                 <small class="text-white-75">Prêmios Ativos</small>
                              </div>
                              <div class="col-3">
                                 <h4 class="text-white mb-1" id="min-investment">R$ <?= number_format($config['valor_minimo_investimento'] ?? 200, 0) ?></h4>
                                 <small class="text-white-75">Investimento Mín.</small>
                              </div>
                              <div class="col-3">
                                 <h4 class="text-white mb-1" id="probability-status">
                                    <?php
                                    $total_chance = ($config['premio_1_chance'] ?? 0) + ($config['premio_2_chance'] ?? 0) + 
                                                   ($config['premio_3_chance'] ?? 0) + ($config['premio_4_chance'] ?? 0) + 
                                                   ($config['premio_5_chance'] ?? 0) + ($config['premio_6_chance'] ?? 0) + 
                                                   ($config['premio_7_chance'] ?? 0) + ($config['premio_8_chance'] ?? 0);
                                    echo ($total_chance == 100) ? '✅' : '⚠️';
                                    ?>
                                 </h4>
                                 <small class="text-white-75">Status</small>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>

               <!-- Configurações Rápidas -->
               <div class="row mb-4">
                  <div class="col-12">
                     <div class="card border-0 quick-config-card">
                        <div class="card-body">
                           <h5 class="text-info mb-4">⚡ Configurações Rápidas</h5>
                           <form id="quick-settings-form" method="POST" action="">
                              <div class="row align-items-center">
                                 <div class="col-lg-2 col-md-4 col-6 mb-3">
                                    <label class="form-label text-white">Status da Roleta</label>
                                    <div class="form-check form-switch">
                                       <input class="form-check-input" type="checkbox" id="roleta-ativa" name="roleta_ativa" <?= ($config['roleta_ativa'] ?? 1) ? 'checked' : '' ?>>
                                       <label class="form-check-label text-white" for="roleta-ativa">Ativa</label>
                                    </div>
                                 </div>
                                 <div class="col-lg-2 col-md-4 col-6 mb-3">
                                    <label class="form-label text-white">Investimento Mínimo</label>
                                    <input type="number" class="form-control" id="valor-minimo" name="valor_minimo_investimento" value="<?= $config['valor_minimo_investimento'] ?? 200 ?>" min="0" step="0.01" onchange="updateStatistics()">
                                 </div>
                                 <div class="col-lg-2 col-md-4 col-6 mb-3">
                                    <label class="form-label text-white">Giros/Investimento</label>
                                    <input type="number" class="form-control" id="giros-investimento" name="giros_por_investimento" value="<?= $config['giros_por_investimento'] ?? 1 ?>" min="1" max="10">
                                 </div>
                                 <div class="col-lg-2 col-md-4 col-6 mb-3">
                                    <label class="form-label text-white">Giros/Indicação</label>
                                    <input type="number" class="form-control" id="giros-indicacao" name="giros_por_indicacao" value="<?= $config['giros_por_indicacao'] ?? 1 ?>" min="1" max="10">
                                 </div>
                                 <div class="col-lg-2 col-md-4 col-6 mb-3">
                                    <label class="form-label text-white">Limite/Dia</label>
                                    <input type="number" class="form-control" id="limite-dia" name="limite_giros_dia" value="<?= $config['limite_giros_dia'] ?? 5 ?>" min="1" max="50">
                                 </div>
                                 <div class="col-lg-2 col-md-4 col-6 mb-3">
                                    <label class="form-label text-white">&nbsp;</label>
                                    <button type="submit" name="save_quick" class="btn btn-success w-100">
                                       <i class="ph-duotone ph-floppy-disk me-1"></i> Salvar
                                    </button>
                                 </div>
                              </div>
                           </form>
                        </div>
                     </div>
                  </div>
               </div>

               <!-- Configuração dos Prêmios -->
               <div class="row">
                  <div class="col-12">
                     <div class="card border-0 prizes-card">
                        <div class="card-header bg-transparent">
                           <div class="d-flex justify-content-between align-items-center">
                              <h5 class="text-white mb-0">🎁 Configurar Prêmios (8 segmentos)</h5>
                              <div>
                                 <button class="btn btn-info btn-sm me-2" onclick="autoBalance()">
                                    <i class="ph-duotone ph-calculator me-1"></i> Auto-Balancear 100%
                                 </button>
                                 <button class="btn btn-warning btn-sm me-2" onclick="location.reload()">
                                    <i class="ph-duotone ph-arrow-clockwise me-1"></i> Recarregar
                                 </button>
                              </div>
                           </div>
                        </div>
                        <div class="card-body">
                           
                           <form id="prizes-form" method="POST" action="">
                              <!-- Prêmios Grid -->
                              <div class="row" id="prizes-container">
                                 
                                 <?php for($i = 1; $i <= 8; $i++): ?>
                                 <!-- Prêmio <?= $i ?> -->
                                 <div class="col-lg-6 col-xl-4 mb-4">
                                    <div class="prize-card" data-prize="<?= $i ?>">
                                       <div class="prize-header">
                                          <div class="prize-number"><?= $i ?></div>
                                          <div class="prize-color-picker">
                                             <input type="color" class="form-control color-input" name="premio_<?= $i ?>_cor" value="<?= $config["premio_{$i}_cor"] ?? '#FF6B6B' ?>" onchange="updatePreview()">
                                          </div>
                                       </div>
                                       <div class="prize-content">
                                          <div class="mb-3">
                                             <label class="form-label">Nome do Prêmio</label>
                                             <input type="text" class="form-control prize-name" name="premio_<?= $i ?>_nome" value="<?= htmlspecialchars($config["premio_{$i}_nome"] ?? "Prêmio $i") ?>" onchange="updatePreview()">
                                          </div>
                                          <div class="row mb-3">
                                             <div class="col-6">
                                                <label class="form-label">Tipo</label>
                                                <select class="form-control prize-type" name="premio_<?= $i ?>_tipo" onchange="updatePrizeType(<?= $i ?>, this.value); updatePreview()">
                                                   <option value="produto" <?= ($config["premio_{$i}_tipo"] ?? 'produto') == 'produto' ? 'selected' : '' ?>>Produto</option>
                                                   <option value="dinheiro" <?= ($config["premio_{$i}_tipo"] ?? 'produto') == 'dinheiro' ? 'selected' : '' ?>>Dinheiro</option>
                                                   <option value="nada" <?= ($config["premio_{$i}_tipo"] ?? 'produto') == 'nada' ? 'selected' : '' ?>>Nada</option>
                                                </select>
                                             </div>
                                             <div class="col-6">
                                                <label class="form-label">Valor (R$)</label>
                                                <input type="number" class="form-control prize-value" name="premio_<?= $i ?>_valor" value="<?= $config["premio_{$i}_valor"] ?? 0 ?>" min="0" step="0.01" <?= ($config["premio_{$i}_tipo"] ?? 'produto') != 'dinheiro' ? 'disabled' : '' ?>>
                                             </div>
                                          </div>
                                          <div class="mb-3">
                                             <label class="form-label">Probabilidade (%)</label>
                                             <input type="number" class="form-control prize-chance" name="premio_<?= $i ?>_chance" value="<?= $config["premio_{$i}_chance"] ?? 12.5 ?>" min="0" max="100" step="0.01" onchange="updatePreview()">
                                          </div>
                                       </div>
                                    </div>
                                 </div>
                                 <?php endfor; ?>

                              </div>
                              
                              <div class="text-center mt-4">
                                 <button type="submit" name="save_prizes" class="btn btn-success btn-lg">
                                    <i class="ph-duotone ph-floppy-disk me-2"></i> Salvar Todos os Prêmios
                                 </button>
                              </div>
                           </form>
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

      // ===== FUNÇÕES DE PREVIEW E VALIDAÇÃO =====
      function updatePreview() {
         updateStatistics();
      }

      function updateStatistics() {
         let totalProbability = 0;
         let activePrizes = 0;

         // Calcular probabilidades
         for (let i = 1; i <= 8; i++) {
            const chanceInput = document.querySelector(`[data-prize="${i}"] .prize-chance`);
            const nameInput = document.querySelector(`[data-prize="${i}"] .prize-name`);
            
            if (chanceInput && nameInput) {
               const chance = parseFloat(chanceInput.value) || 0;
               totalProbability += chance;
               
               if (nameInput.value.trim() !== '') {
                  activePrizes++;
               }
            }
         }

         // Atualizar display
         document.getElementById('total-probability').textContent = totalProbability.toFixed(1) + '%';
         document.getElementById('active-prizes').textContent = activePrizes;

         // Status da probabilidade
         const statusElement = document.getElementById('probability-status');
         if (Math.abs(totalProbability - 100) < 0.1) {
            statusElement.textContent = '✅';
            statusElement.style.color = '#27AE60';
         } else if (totalProbability > 100) {
            statusElement.textContent = '⚠️';
            statusElement.style.color = '#F39C12';
         } else {
            statusElement.textContent = '❌';
            statusElement.style.color = '#E74C3C';
         }

         // Investimento mínimo
         const minInvestmentInput = document.getElementById('valor-minimo');
         if (minInvestmentInput) {
            document.getElementById('min-investment').textContent = 'R$ ' + parseFloat(minInvestmentInput.value || 0).toFixed(0);
         }
      }

      function updatePrizeType(prizeNumber, type) {
         const valueInput = document.querySelector(`[data-prize="${prizeNumber}"] .prize-value`);
         if (valueInput) {
            if (type === 'dinheiro') {
               valueInput.disabled = false;
               valueInput.style.opacity = '1';
            } else {
               valueInput.disabled = true;
               valueInput.style.opacity = '0.5';
               if (type === 'nada') {
                  valueInput.value = '0';
               }
            }
         }
      }

      // ===== FUNÇÃO AUTO-BALANCEAMENTO =====
      function autoBalance() {
         if (confirm('Deseja redistribuir automaticamente as probabilidades para totalizar 100%?')) {
            // Valores sugeridos equilibrados
            const suggestedValues = [2, 15, 25, 10, 3, 20, 20, 5]; // Total = 100%
            
            for (let i = 1; i <= 8; i++) {
               const chanceInput = document.querySelector(`[data-prize="${i}"] .prize-chance`);
               if (chanceInput) {
                  chanceInput.value = suggestedValues[i - 1];
               }
            }
            
            updateStatistics();
            alert('🎯 Probabilidades balanceadas automaticamente para 100%!');
         }
      }

      // ===== INICIALIZAÇÃO =====
      document.addEventListener('DOMContentLoaded', function() {
         updateStatistics();
         
         // Inicializar tipos de prêmios
         for (let i = 1; i <= 8; i++) {
            const typeSelect = document.querySelector(`[data-prize="${i}"] .prize-type`);
            if (typeSelect) {
               updatePrizeType(i, typeSelect.value);
            }
         }
         
         console.log('🎰 Sistema de configuração da roleta carregado com dados do banco!');
      });
   </script>

   <style>
      /* ===== ESTILOS GERAIS ===== */
      .text-white-75 { color: rgba(255,255,255,0.75); }

      /* ===== HERO CARD ===== */
      .hero-card {
         background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
         border-radius: 20px;
         overflow: hidden;
      }

      /* ===== CARDS ===== */
      .quick-config-card, .prizes-card, .stats-card, .actions-card {
         background: rgba(255,255,255,0.05);
         backdrop-filter: blur(10px);
         border-radius: 20px;
         border: 1px solid rgba(255,255,255,0.1);
      }

      /* ===== PRIZE CARDS ===== */
      .prize-card {
         background: rgba(255,255,255,0.08);
         border-radius: 15px;
         border: 1px solid rgba(255,255,255,0.1);
         overflow: hidden;
         transition: all 0.3s ease;
      }

      .prize-card:hover {
         transform: translateY(-5px);
         border-color: rgba(255,255,255,0.2);
         box-shadow: 0 10px 30px rgba(0,0,0,0.2);
      }

      .prize-header {
         background: rgba(255,255,255,0.1);
         padding: 15px;
         display: flex;
         justify-content: space-between;
         align-items: center;
      }

      .prize-number {
         background: linear-gradient(135deg, #667eea, #764ba2);
         color: white;
         width: 35px;
         height: 35px;
         border-radius: 50%;
         display: flex;
         align-items: center;
         justify-content: center;
         font-weight: bold;
         font-size: 16px;
      }

      .color-input {
         width: 50px;
         height: 35px;
         border: none;
         border-radius: 8px;
         cursor: pointer;
      }

      .prize-content {
         padding: 20px;
      }

      .form-label {
         color: rgba(255,255,255,0.9);
         font-weight: 500;
         margin-bottom: 8px;
      }

      .form-control {
         background: rgba(255,255,255,0.1);
         border: 1px solid rgba(255,255,255,0.2);
         color: white;
         border-radius: 8px;
      }

      .form-control:focus {
         background: rgba(255,255,255,0.15);
         border-color: rgba(255,255,255,0.4);
         color: white;
         box-shadow: 0 0 0 0.2rem rgba(255,255,255,0.1);
      }

      .form-control::placeholder {
         color: rgba(255,255,255,0.5);
      }

      /* ===== BOTÕES ===== */
      .btn {
         border-radius: 10px;
         font-weight: 500;
         transition: all 0.3s ease;
      }

      .btn:hover {
         transform: translateY(-2px);
      }

      .back-btn {
         border-radius: 50px;
         padding: 15px 30px;
         border: 2px solid rgba(255,255,255,0.4);
         background: rgba(255,255,255,0.1);
         backdrop-filter: blur(10px);
         color: #fff;
         text-decoration: none;
      }
      
      .back-btn:hover {
         background: rgba(255,255,255,0.2);
         border-color: rgba(255,255,255,0.7);
         color: #fff;
         text-decoration: none;
      }

      /* ===== RESPONSIVIDADE ===== */
      @media (max-width: 768px) {
         .prize-card {
            margin-bottom: 20px;
         }
         
         .hero-stats .col-3 {
            margin-bottom: 15px;
         }
         
         .d-flex.justify-content-between {
            flex-direction: column;
            gap: 10px;
         }
      }

      @media (max-width: 576px) {
         .hero-stats .col-3 {
            flex: 0 0 50%;
            max-width: 50%;
         }
         
         .col-lg-2.col-md-4.col-6 {
            flex: 0 0 100%;
            max-width: 100%;
         }
      }
   </style>

</body>
</html>