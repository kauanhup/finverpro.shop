<?php
session_start();
if (!isset($_SESSION['user_id'])) {
   header('Location: ../');
   exit();
}

require 'includes/config.php';
require 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$config = loadRoletaConfig();
$user_spins = loadUserSpins($user_id);
$historico = loadUserHistory($user_id);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>🎰 Roleta da Sorte</title>
   
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
   <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
   <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
   <div class="container">
      <!-- Header -->
      <div class="header-section">
         <h1 class="header-title">🌌 Roleta Cosmic</h1>
         <p class="header-subtitle">Gire através do cosmos e ganhe prêmios!</p>
      </div>

      <!-- Stats -->
      <div class="stats-section">
         <div class="stats-grid">
            <div class="stats-card">
               <i class="fas fa-play-circle"></i>
               <div class="label">Giros Disponíveis</div>
               <div class="value" id="giros-disponiveis"><?= $user_spins['giros_disponiveis'] ?></div>
            </div>
            <div class="stats-card">
               <i class="fas fa-calendar-day"></i>
               <div class="label">Giros Hoje</div>
               <div class="value" id="giros-hoje"><?= $user_spins['giros_hoje'] ?></div>
            </div>
            <div class="stats-card">
               <i class="fas fa-clock"></i>
               <div class="label">Limite Diário</div>
               <div class="value"><?= $config['limite_giros_dia'] ?></div>
            </div>
            <div class="stats-card">
               <i class="fas fa-history"></i>
               <div class="label">Total de Giros</div>
               <div class="value"><?= $user_spins['total_giros_historico'] ?></div>
            </div>
         </div>
      </div>

      <!-- Roleta -->
      <div class="roleta-section">
         <h2 class="roleta-title">🎯 Gire e Ganhe!</h2>
         
         <div class="wheel-container">
            <svg class="wheel-svg" id="cosmicWheel" viewBox="0 0 320 320">
               <?php renderWheelSegments($config); ?>
               <circle cx="160" cy="160" r="40" fill="rgba(0,0,0,0.3)" stroke="rgba(255,255,255,0.3)" stroke-width="2"/>
            </svg>

            <div class="pointer"></div>
            <button class="center-button" id="spinBtn">
               <div class="center-icon">🚀</div>
               <div class="center-text">GIRAR</div>
            </button>
         </div>

         <div class="status-text" id="status-text">Clique no botão para girar através do cosmos!</div>
      </div>

      <!-- Info -->
      <div class="info-section">
         <h3 class="info-title">
            <i class="fas fa-lightbulb"></i>
            Como Ganhar Giros
         </h3>
         <?php renderInfoItems($config); ?>
      </div>

      <!-- Histórico -->
      <div class="history-section">
         <h3 class="history-title">
            <i class="fas fa-history"></i>
            Últimos Giros
         </h3>
         <div class="history-list" id="history-list">
            <?php renderHistoryItems($historico); ?>
         </div>
      </div>
   </div>

   <!-- Bottom Nav -->
   <nav class="bottom-nav">
      <a href="../dashboard/"><i class="fas fa-home"></i>Início</a>
      <a href="../investimentos/"><i class="fas fa-wallet"></i>Investimentos</a>
      <a href="../team/"><i class="fas fa-users"></i>Equipe</a>
      <a href="../perfil/"><i class="fas fa-user"></i>Perfil</a>
   </nav>

   <script>
   // Dados do PHP para JavaScript
   window.roletaData = {
      userId: <?= $user_id ?>,
      userSpins: <?= json_encode($user_spins) ?>,
      config: <?= json_encode($config) ?>,
      premios: <?= json_encode(getPremiosArray($config)) ?>
   };
   </script>
   <script src="assets/js/roleta.js"></script>
</body>
</html>