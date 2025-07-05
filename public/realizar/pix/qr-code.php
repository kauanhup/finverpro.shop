<?php
session_start(); // Inicia a sessão

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
   // Se não estiver logado, redireciona para a página de login
   header('Location: ../');
   exit(); // Encerra o script
}

// Incluir o arquivo de conexão com o banco de dados
require '../../bank/db.php';

// Conexão com o banco de dados
$pdo = getDBConnection();

// Consulta as colunas link_suporte, pop_up e anuncio na tabela configurar_textos
$stmt = $pdo->query("SELECT link_suporte, pop_up, anuncio, titulo_site, descricao_site, keywords_site, link_site FROM configurar_textos LIMIT 1");
$result = $stmt->fetch(PDO::FETCH_ASSOC);

// Define valores padrão caso não encontre no banco
$linkSuporte = $result['link_suporte'] ?? '/';
$popUp = $result['pop_up'] ?? '';
$anuncio = $result['anuncio'] ?? '';
$titulo_site = $result['titulo_site'] ?? '';
$descricao_site = $result['descricao_site'] ?? '';
$keywords_site = $result['keywords_site'] ?? '';
$link_site = $result['link_site'] ?? '';

// Consulta as colunas logo e tela_login na tabela personalizar_imagens
$pdo = getDBConnection();
$stmt = $pdo->query("SELECT logo FROM personalizar_imagens LIMIT 1");
$result = $stmt->fetch(PDO::FETCH_ASSOC);

// Define valores padrão caso não encontre no banco
$logo = $result['logo'] ?? '3.png';

// Consulta as cores do banco de dados
$pdo = getDBConnection();
$stmt = $pdo->query("SELECT cor_1, cor_2, cor_3, cor_4, cor_5 FROM personalizar_cores LIMIT 1");
$cores = $stmt->fetch(PDO::FETCH_ASSOC);

// Define as cores padrão caso nenhuma cor seja encontrada
$defaultColors = [
   'cor_1' => '#121A1E',
   'cor_2' => 'white',
   'cor_3' => '#152731',
   'cor_4' => '#335D67',
   'cor_5' => '#152731',
];

$cores = $cores ?: $defaultColors;

// Criar a conexão
try {
   $conn = getDBConnection(); // Chama a função para obter a conexão
} catch (Exception $e) {
   die("Erro de conexão: " . $e->getMessage()); // Mensagem de erro
}

// Obter ID do usuário da sessão
$user_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <!-- Meta Tags -->
   <title><?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?> - Pagamento PIX</title>
   <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
   <meta name="title" content="<?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?>">
   <meta name="author" content="<?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?>">
   <meta name="description" content="<?= htmlspecialchars($descricao_site, ENT_QUOTES, 'UTF-8'); ?>">
   <meta name="keywords" content="<?= htmlspecialchars($keywords_site, ENT_QUOTES, 'UTF-8'); ?>" />
   <link rel="canonical" href="<?= htmlspecialchars($link_site, ENT_QUOTES, 'UTF-8'); ?>">
   
   <!-- Facebook -->
   <meta property="og:type" content="website">
   <meta property="og:url" content="<?= htmlspecialchars($link_site, ENT_QUOTES, 'UTF-8'); ?>">
   <meta property="og:title" content="<?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?>">
   <meta property="og:description" content="<?= htmlspecialchars($descricao_site, ENT_QUOTES, 'UTF-8'); ?>">
   
   <!-- Twitter -->
   <meta property="twitter:card" content="summary_large_image">
   <meta property="twitter:url" content="<?= htmlspecialchars($link_site, ENT_QUOTES, 'UTF-8'); ?>">
   <meta property="twitter:title" content="<?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?>">
   <meta property="twitter:description" content="<?= htmlspecialchars($descricao_site, ENT_QUOTES, 'UTF-8'); ?>">
   
   <!-- Favicon -->
   <link rel="apple-touch-icon" sizes="120x120" href="../../assets/images/favicon/apple-touch-icon.png">
   <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/favicon/favicon-32x32.png">
   <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/favicon/favicon-16x16.png">
   <link rel="manifest" href="../../assets/images/favicon/site.webmanifest">
   <meta name="msapplication-TileColor" content="#ffffff">
   <meta name="theme-color" content="#ffffff">
   
   <!-- Fonts & Icons -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
   <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
   <script src="https://js.pusher.com/7.0/pusher.min.js"></script>
   
   <style>
       :root {
           --background-color: <?= htmlspecialchars($cores['cor_1']) ?>;
           --text-color: <?= htmlspecialchars($cores['cor_2']) ?>;
           --primary-color: <?= htmlspecialchars($cores['cor_3']) ?>;
           --secondary-color: <?= htmlspecialchars($cores['cor_4']) ?>;
           --dark-background: <?= htmlspecialchars($cores['cor_5']) ?>;
           --success-color: #10B981;
           --error-color: #EF4444;
           --warning-color: #F59E0B;
           --info-color: #3B82F6;
           --purple-color: #8B5CF6;
           --pink-color: #EC4899;
           --orange-color: #F97316;
           --border-radius: 16px;
           --border-radius-sm: 8px;
           --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
           --shadow-lg: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
           --blur-bg: rgba(255, 255, 255, 0.08);
           --border-color: rgba(255, 255, 255, 0.15);
       }

       * {
           margin: 0;
           padding: 0;
           box-sizing: border-box;
       }

       body {
           font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
           background: linear-gradient(135deg, var(--background-color) 0%, var(--dark-background) 100%);
           min-height: 100vh;
           color: var(--text-color);
           padding: 0 0 80px 0;
       }

       body::before {
           content: '';
           position: fixed;
           top: 0;
           left: 0;
           right: 0;
           bottom: 0;
           background: 
               radial-gradient(circle at 20% 20%, rgba(51, 93, 103, 0.15) 0%, transparent 50%),
               radial-gradient(circle at 80% 80%, rgba(16, 185, 129, 0.1) 0%, transparent 50%);
           pointer-events: none;
           z-index: -1;
       }

       /* Header */
       .header-section {
           background: var(--blur-bg);
           backdrop-filter: blur(20px);
           border-bottom: 1px solid var(--border-color);
           padding: 25px 20px;
           text-align: center;
           position: relative;
           overflow: hidden;
       }

       .header-section::before {
           content: '';
           position: absolute;
           top: 0;
           left: 0;
           right: 0;
           height: 4px;
           background: linear-gradient(90deg, var(--success-color), var(--info-color));
       }

       .header-title {
           font-size: 24px;
           font-weight: 700;
           color: var(--text-color);
           margin-bottom: 8px;
       }

       .header-subtitle {
           font-size: 16px;
           color: rgba(255, 255, 255, 0.8);
           line-height: 1.5;
       }

       /* Container */
       .container {
           max-width: 500px;
           margin: 0 auto;
           padding: 0 20px;
       }

       /* Status Indicator */
       .status-indicator {
           position: fixed;
           top: 20px;
           left: 50%;
           transform: translateX(-50%);
           background: var(--blur-bg);
           backdrop-filter: blur(20px);
           border: 1px solid var(--border-color);
           border-radius: var(--border-radius);
           padding: 8px 16px;
           font-size: 13px;
           font-weight: 600;
           color: var(--warning-color);
           z-index: 1000;
           animation: pulse 2s ease-in-out infinite;
           white-space: nowrap;
       }

       /* Timer Section */
       .timer-section {
           background: var(--blur-bg);
           backdrop-filter: blur(20px);
           border: 2px solid var(--border-color);
           border-radius: var(--border-radius);
           padding: 20px;
           margin-bottom: 25px;
           text-align: center;
           position: relative;
           overflow: hidden;
           animation: fadeInUp 0.6s ease-out 0.15s both;
       }

       .timer-section::before {
           content: '';
           position: absolute;
           top: 0;
           left: 0;
           right: 0;
           height: 3px;
           background: linear-gradient(90deg, var(--warning-color), var(--error-color));
       }

       .timer-title {
           font-size: 16px;
           font-weight: 600;
           color: var(--text-color);
           margin-bottom: 10px;
           display: flex;
           align-items: center;
           justify-content: center;
           gap: 8px;
       }

       .timer-display {
           font-size: 32px;
           font-weight: 800;
           color: var(--warning-color);
           font-family: 'Courier New', monospace;
           text-shadow: 0 0 10px rgba(245, 158, 11, 0.3);
           transition: all 0.3s ease;
       }

       .timer-display.warning {
           color: var(--error-color);
           animation: timerPulse 1s ease-in-out infinite;
       }

       .timer-subtitle {
           font-size: 13px;
           color: rgba(255, 255, 255, 0.7);
           margin-top: 8px;
       }

       @keyframes timerPulse {
           0%, 100% { transform: scale(1); }
           50% { transform: scale(1.05); }
       }

       /* QR Code Section */
       .qr-section {
           padding: 30px 20px;
           text-align: center;
       }

       .qr-container {
           background: var(--blur-bg);
           backdrop-filter: blur(20px);
           border: 2px solid var(--border-color);
           border-radius: var(--border-radius);
           padding: 30px;
           margin-bottom: 25px;
           position: relative;
           overflow: hidden;
           animation: fadeInUp 0.6s ease-out;
       }

       .qr-container::before {
           content: '';
           position: absolute;
           top: 0;
           left: 0;
           right: 0;
           height: 3px;
           background: linear-gradient(90deg, var(--success-color), var(--info-color));
       }

       .qr-title {
           font-size: 20px;
           font-weight: 700;
           color: var(--text-color);
           margin-bottom: 20px;
           display: flex;
           align-items: center;
           justify-content: center;
           gap: 10px;
       }

       .qr-code {
           width: 200px;
           height: 200px;
           border-radius: var(--border-radius-sm);
           background: white;
           padding: 10px;
           box-shadow: var(--shadow-lg);
           border: 2px solid var(--border-color);
           transition: all 0.3s ease;
       }

       .qr-code:hover {
           transform: scale(1.05);
           box-shadow: 
               var(--shadow-lg),
               0 0 0 4px rgba(16, 185, 129, 0.1);
       }

       /* Copy Section */
       .copy-section {
           background: var(--blur-bg);
           backdrop-filter: blur(20px);
           border: 2px solid var(--border-color);
           border-radius: var(--border-radius);
           padding: 25px;
           margin-bottom: 25px;
           animation: fadeInUp 0.6s ease-out 0.1s both;
       }

       .copy-title {
           font-size: 18px;
           font-weight: 600;
           color: var(--text-color);
           margin-bottom: 15px;
           display: flex;
           align-items: center;
           gap: 10px;
       }

       .copy-input-container {
           display: flex;
           gap: 10px;
           margin-bottom: 15px;
       }

       .copy-input {
           flex: 1;
           padding: 15px;
           font-size: 14px;
           background: var(--primary-color);
           border: 1px solid var(--border-color);
           border-radius: var(--border-radius-sm);
           color: var(--text-color);
           font-family: 'Courier New', monospace;
           word-break: break-all;
       }

       .copy-input:focus {
           outline: none;
           border-color: var(--success-color);
           box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
       }

       .copy-btn {
           padding: 15px 20px;
           font-size: 14px;
           font-weight: 600;
           background: linear-gradient(135deg, var(--success-color), #059669);
           color: white;
           border: none;
           border-radius: var(--border-radius-sm);
           cursor: pointer;
           transition: all 0.3s ease;
           text-transform: uppercase;
           letter-spacing: 0.5px;
           min-width: 100px;
       }

       .copy-btn:hover {
           transform: translateY(-2px);
           box-shadow: var(--shadow-lg);
       }

       .copy-btn:active {
           transform: translateY(0);
       }

       /* Payment Steps */
       .payment-steps {
           background: var(--blur-bg);
           backdrop-filter: blur(20px);
           border: 2px solid var(--border-color);
           border-radius: var(--border-radius);
           padding: 25px;
           animation: fadeInUp 0.6s ease-out 0.3s both;
       }

       .steps-title {
           font-size: 20px;
           font-weight: 700;
           color: var(--text-color);
           margin-bottom: 20px;
           display: flex;
           align-items: center;
           gap: 10px;
       }

       .payment-steps ol {
           padding-left: 20px;
           color: rgba(255, 255, 255, 0.9);
           line-height: 1.8;
       }

       .payment-steps li {
           margin-bottom: 12px;
           font-size: 15px;
       }

       .payment-steps li::marker {
           color: var(--success-color);
           font-weight: 600;
       }

       /* Bottom Navigation */
       .bottom-nav {
           position: fixed;
           bottom: 0;
           left: 0;
           right: 0;
           background: var(--blur-bg);
           backdrop-filter: blur(25px);
           border-top: 1px solid var(--border-color);
           padding: 15px 0;
           display: flex;
           justify-content: space-around;
           z-index: 100;
       }

       .bottom-nav a {
           color: rgba(255, 255, 255, 0.7);
           text-decoration: none;
           display: flex;
           flex-direction: column;
           align-items: center;
           gap: 5px;
           font-size: 12px;
           font-weight: 500;
           transition: all 0.3s ease;
           padding: 8px 12px;
           border-radius: var(--border-radius-sm);
       }

       .bottom-nav a.active,
       .bottom-nav a:hover {
           color: var(--secondary-color);
           background: rgba(51, 93, 103, 0.15);
       }

       .bottom-nav a i {
           font-size: 20px;
       }

       /* Animations */
       @keyframes fadeInUp {
           from {
               opacity: 0;
               transform: translateY(30px);
           }
           to {
               opacity: 1;
               transform: translateY(0);
           }
       }

       @keyframes pulse {
           0%, 100% { opacity: 1; }
           50% { opacity: 0.7; }
       }

       @keyframes ripple {
           to {
               transform: scale(4);
               opacity: 0;
           }
       }

       /* SweetAlert Custom Styles */
       .custom-swal-popup {
           background: var(--blur-bg) !important;
           backdrop-filter: blur(20px) !important;
           border: 1px solid var(--border-color) !important;
           border-radius: var(--border-radius) !important;
           color: var(--text-color) !important;
       }
       
       .custom-confirm-button {
           background: linear-gradient(135deg, var(--secondary-color), var(--info-color)) !important;
           border: none !important;
           border-radius: var(--border-radius-sm) !important;
           font-weight: 600 !important;
           padding: 12px 24px !important;
       }

       /* Responsive */
       @media (max-width: 480px) {
           .container {
               padding: 0 15px;
           }

           .qr-container {
               padding: 20px;
           }

           .qr-code {
               width: 180px;
               height: 180px;
           }

           .copy-input-container {
               flex-direction: column;
           }

           .copy-btn {
               width: 100%;
           }

           .header-title {
               font-size: 20px;
           }

           .header-subtitle {
               font-size: 14px;
           }

           .timer-display {
               font-size: 28px;
           }
       }
   </style>
</head>

<body>
   <!-- Status Indicator -->
   <div class="status-indicator">
       <i class="fas fa-clock"></i> Aguardando Pagamento
   </div>

   <!-- Header -->
   <div class="header-section">
       <h1 class="header-title">
           <i class="fas fa-qrcode" style="color: var(--success-color);"></i>
           Pagamento PIX
       </h1>
       <p class="header-subtitle">Realize seu pagamento com o QR-Code ou copie a chave abaixo e cole no seu banco</p>
   </div>

   <div class="container">
       <!-- Timer Section -->
       <div class="timer-section">
           <h3 class="timer-title">
               <i class="fas fa-stopwatch" style="color: var(--warning-color);"></i>
               Tempo para Pagamento
           </h3>
           <div class="timer-display" id="timer">10:00</div>
           <p class="timer-subtitle">PIX expira automaticamente</p>
       </div>

       <!-- QR Code Section -->
       <div class="qr-section">
           <div class="qr-container">
               <h2 class="qr-title">
                   <i class="fas fa-mobile-alt" style="color: var(--info-color);"></i>
                   Escaneie o QR Code
               </h2>
               <img src="" alt="QR Code" class="qr-code" id="qrCode">
           </div>

           <!-- Copy Section -->
           <div class="copy-section">
               <h3 class="copy-title">
                   <i class="fas fa-copy" style="color: var(--warning-color);"></i>
                   Chave Copia e Cola
               </h3>
               <div class="copy-input-container">
                   <input type="text" id="copyKey" class="copy-input" value="chave-copia-e-cola-aqui" readonly>
                   <button class="copy-btn" onclick="copyToClipboard()">
                       <i class="fas fa-copy"></i>
                       COPIAR
                   </button>
               </div>
           </div>

           <!-- Payment Steps -->
           <div class="payment-steps">
               <h2 class="steps-title">
                   <i class="fas fa-list-ol" style="color: var(--purple-color);"></i>
                   Como Fazer o Pagamento
               </h2>
               <ol>
                   <li>Copie o código clicando no botão "COPIAR"</li>
                   <li>Abra o aplicativo do seu banco e vá até a opção de pagamento via "PIX" ou "Chave PIX"</li>
                   <li>Escolha a opção "Colar código" ou "Chave Copia e Cola"</li>
                   <li>Cole a chave copiada no campo correspondente</li>
                   <li>Confirme os detalhes do pagamento e finalize a transação</li>
               </ol>
           </div>
       </div>
   </div>

   <!-- Bottom Navigation -->
   <nav class="bottom-nav">
       <a href="../../inicio/">
           <i class="fas fa-home"></i>
           Início
       </a>
       <a href="../../investimentos/">
           <i class="fas fa-wallet"></i>
           Investimentos
       </a>
       <a href="../../team/">
           <i class="fas fa-users"></i>
           Equipe
       </a>
       <a href="../../perfil/">
           <i class="fas fa-user"></i>
           Perfil
       </a>
   </nav>

   <script>
       let timerInterval;
       let paymentApproved = false;

       function getCurrentUserId() {
           return <?= $user_id ?>;
       }

       function getQueryParams() {
           const params = {};
           const queryString = window.location.search.substring(1);
           const queries = queryString.split("&");
           queries.forEach(query => {
               const [key, value] = query.split("=");
               params[decodeURIComponent(key)] = decodeURIComponent(value || "");
           });
           return params;
       }

       function initializePersistentTimer() {
           const params = getQueryParams();
           const externalReference = params['externalReference'];
           
           if (!externalReference) {
               return initializeDefaultTimer();
           }

           const storageKey = `timer_${externalReference}`;
           const creationKey = `created_${externalReference}`;
           
           let timeLeft;
           let creationTime = localStorage.getItem(creationKey);
           
           if (!creationTime) {
               creationTime = Date.now();
               localStorage.setItem(creationKey, creationTime);
               timeLeft = 10 * 60;
               localStorage.setItem(storageKey, timeLeft);
           } else {
               const elapsedSeconds = Math.floor((Date.now() - parseInt(creationTime)) / 1000);
               timeLeft = Math.max(0, (10 * 60) - elapsedSeconds);
           }
           
           return startTimer(timeLeft, storageKey, externalReference);
       }

       function startTimer(initialTime, storageKey, externalReference) {
           let timeLeft = initialTime;
           const timerDisplay = document.getElementById('timer');
           
           function updateTimer() {
               if (paymentApproved) return;
               
               const minutes = Math.floor(timeLeft / 60);
               const seconds = timeLeft % 60;
               
               const formattedTime = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
               timerDisplay.textContent = formattedTime;
               
               if (timeLeft <= 120) {
                   timerDisplay.classList.add('warning');
               }
               
               if (timeLeft <= 0) {
                   clearInterval(timerInterval);
                   
                   localStorage.removeItem(storageKey);
                   localStorage.removeItem(`created_${externalReference}`);
                   
                   cleanupExpiredPayment(externalReference);
                   
                   Swal.fire({
                       icon: 'warning',
                       title: '⏰ PIX Expirado!',
                       text: 'O tempo para pagamento expirou. O registro foi removido automaticamente.',
                       confirmButtonText: 'Gerar Novo PIX',
                       customClass: {
                           popup: 'custom-swal-popup',
                           confirmButton: 'custom-confirm-button'
                       }
                   }).then(() => {
                       window.location.href = '../';
                   });
                   return;
               }
               
               timeLeft--;
               
               if (timeLeft % 5 === 0) {
                   localStorage.setItem(storageKey, timeLeft);
               }
           }
           
           const timerInterval = setInterval(updateTimer, 1000);
           updateTimer();
           
           return timerInterval;
       }

       function initializeDefaultTimer() {
           let timeLeft = 10 * 60;
           const timerDisplay = document.getElementById('timer');
           
           function updateTimer() {
               if (paymentApproved) return;
               
               const minutes = Math.floor(timeLeft / 60);
               const seconds = timeLeft % 60;
               
               const formattedTime = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
               timerDisplay.textContent = formattedTime;
               
               if (timeLeft <= 120) {
                   timerDisplay.classList.add('warning');
               }
               
               if (timeLeft <= 0) {
                   clearInterval(timerInterval);
                   Swal.fire({
                       icon: 'warning',
                       title: '⏰ PIX Expirado!',
                       text: 'O tempo para pagamento expirou. Gere um novo PIX.',
                       confirmButtonText: 'Gerar Novo PIX',
                       customClass: {
                           popup: 'custom-swal-popup',
                           confirmButton: 'custom-confirm-button'
                       }
                   }).then(() => {
                       window.location.href = '../';
                   });
                   return;
               }
               
               timeLeft--;
           }
           
           const timerInterval = setInterval(updateTimer, 1000);
           updateTimer();
           
           return timerInterval;
       }

       function cleanupExpiredPayment(externalReference) {
           if (!externalReference) return;
           
           fetch('../../gate/cleanup.php', {
               method: 'POST',
               headers: {
                   'Content-Type': 'application/json',
               },
               body: JSON.stringify({
                   action: 'cleanup_expired',
                   external_reference: externalReference,
                   user_id: getCurrentUserId()
               })
           })
           .then(response => response.json())
           .then(data => {
               // Silencioso - não mostrar resultado da limpeza
           })
           .catch(error => {
               // Silencioso - não mostrar erros
           });
       }

       document.addEventListener("DOMContentLoaded", () => {
           const params = getQueryParams();
           const copiarTexto = params['copiarTexto'] || '';

           if (copiarTexto) {
               document.getElementById("copyKey").value = copiarTexto;
               const qrCodeUrl = `https://quickchart.io/qr?text=${encodeURIComponent(copiarTexto)}&size=200`;
               document.getElementById("qrCode").src = qrCodeUrl;
           }

           timerInterval = initializePersistentTimer();
       });

       function copyToClipboard() {
           const copyText = document.getElementById("copyKey");
           copyText.select();
           copyText.setSelectionRange(0, 99999);
           
           navigator.clipboard.writeText(copyText.value).then(() => {
               Swal.fire({
                   icon: 'success',
                   title: '✅ Chave copiada!',
                   text: 'Agora cole no seu banco para efetuar o pagamento',
                   confirmButtonText: 'OK',
                   timer: 2000,
                   customClass: {
                       popup: 'custom-swal-popup',
                       confirmButton: 'custom-confirm-button'
                   }
               });

               createRipple(event.target, event);
           }).catch(err => {
               copyText.select();
               document.execCommand('copy');
               
               Swal.fire({
                   icon: 'success',
                   title: '✅ Chave copiada!',
                   text: 'Cole no seu banco para efetuar o pagamento',
                   timer: 2000,
                   customClass: {
                       popup: 'custom-swal-popup',
                       confirmButton: 'custom-confirm-button'
                   }
               });
           });
       }

       function createRipple(element, event) {
           const ripple = document.createElement('div');
           ripple.style.cssText = `
               position: absolute;
               border-radius: 50%;
               background: rgba(16, 185, 129, 0.3);
               transform: scale(0);
               animation: ripple 0.6s ease-out;
               pointer-events: none;
               z-index: 100;
           `;
           
           const rect = element.getBoundingClientRect();
           const size = Math.max(rect.width, rect.height);
           ripple.style.width = ripple.style.height = size + 'px';
           ripple.style.left = (event.clientX - rect.left - size / 2) + 'px';
           ripple.style.top = (event.clientY - rect.top - size / 2) + 'px';
           
           element.style.position = 'relative';
           element.appendChild(ripple);
           
           setTimeout(() => {
               ripple.remove();
           }, 600);
       }

       var pusher = new Pusher('e2fe6ed48f9680332d9e', {
           cluster: 'mt1',
           encrypted: true
       });

       const channel = pusher.subscribe('payment_channel');

       channel.bind('payment_approved', function(data) {
           const currentUserId = getCurrentUserId();
           
           if (data.user_id && data.user_id == currentUserId) {
               approvePayment(data);
           }
       });

       function approvePayment(data) {
           paymentApproved = true;
           
           const statusIndicator = document.querySelector('.status-indicator');
           if (statusIndicator) {
               statusIndicator.innerHTML = '<i class="fas fa-check-circle"></i> Pagamento Aprovado';
               statusIndicator.style.color = 'var(--success-color)';
               statusIndicator.style.animation = 'none';
           }

           if (timerInterval) {
               clearInterval(timerInterval);
           }
           
           const timerDisplay = document.getElementById('timer');
           if (timerDisplay) {
               timerDisplay.textContent = 'PAGO';
               timerDisplay.style.color = 'var(--success-color)';
               timerDisplay.classList.remove('warning');
           }

           const params = getQueryParams();
           const externalReference = params['externalReference'];
           if (externalReference) {
               localStorage.removeItem(`timer_${externalReference}`);
               localStorage.removeItem(`created_${externalReference}`);
           }

           Swal.fire({
               icon: 'success',
               title: '🎉 Pagamento Confirmado!',
               text: 'Seu pagamento foi aprovado com sucesso! Redirecionando...',
               confirmButtonText: 'Continuar',
               customClass: {
                   popup: 'custom-swal-popup',
                   confirmButton: 'custom-confirm-button'
               },
               didClose: () => {
                   window.location.href = '../../inicio/';
               }
           });
       }

       setInterval(() => {
           if (!paymentApproved && timerInterval) {
               const params = getQueryParams();
               const externalReference = params['externalReference'];
               
               if (externalReference) {
                   fetch('../../gate/verificar.php', {
                       method: 'POST',
                       headers: {
                           'Content-Type': 'application/json',
                       },
                       body: JSON.stringify({
                           id: externalReference
                       })
                   })
                   .then(response => response.json())
                   .then(data => {
                       if (data.status == "Pagamento aprovado.") {
                           const fakeEvent = {
                               user_id: getCurrentUserId(),
                               transaction_id: externalReference,
                               amount: data.amount || 0,
                               message: 'Pagamento aprovado via verificação manual!'
                           };
                           
                           approvePayment(fakeEvent);
                       }
                   })
                   .catch(error => {
                       // Silencioso
                   });
               }
           }
       }, 5000);

       setInterval(() => {
           const qrCode = document.getElementById('qrCode');
           if (qrCode && qrCode.src && !paymentApproved) {
               const currentSrc = qrCode.src.split('&timestamp=')[0];
               qrCode.src = currentSrc + '&timestamp=' + new Date().getTime();
           }
       }, 30000);

   </script>
</body>
</html>