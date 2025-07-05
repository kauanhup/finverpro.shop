<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: ../inicio/");
    exit();
}

require 'bank/db.php';
require 'captcha/generate.php';

$pdo = getDBConnection();

// =====================================
// BUSCAR CONFIGURAÇÕES DE PERSONALIZAÇÃO
// =====================================

$stmt = $pdo->query("SELECT link_suporte, pop_up, anuncio, titulo_site, descricao_site, keywords_site, link_site FROM configurar_textos LIMIT 1");
$result = $stmt->fetch(PDO::FETCH_ASSOC);

$linkSuporte = $result['link_suporte'] ?? '/';
$popUp = $result['pop_up'] ?? '';
$anuncio = $result['anuncio'] ?? '';
$titulo_site = $result['titulo_site'] ?? '';
$descricao_site = $result['descricao_site'] ?? '';
$keywords_site = $result['keywords_site'] ?? '';
$link_site = $result['link_site'] ?? '';

// REMOVIDO: busca de imagens
// $stmt = $pdo->query("SELECT logo, tela_login FROM personalizar_imagens LIMIT 1");
// $result = $stmt->fetch(PDO::FETCH_ASSOC);
// $logo = $result['logo'] ?? '3.png';
// $telaLogin = $result['tela_login'] ?? '1.jpg';

$stmt = $pdo->query("SELECT cor_1, cor_2, cor_3, cor_4, cor_5 FROM personalizar_cores LIMIT 1");
$cores = $stmt->fetch(PDO::FETCH_ASSOC);

$defaultColors = [
    'cor_1' => '#121A1E',
    'cor_2' => 'white',
    'cor_3' => '#152731',
    'cor_4' => '#335D67',
    'cor_5' => '#152731',
];

$cores = $cores ?: $defaultColors;

// =====================================
// GERAR CAPTCHA DO BANCO
// =====================================
$captcha = getCaptcha();
$captcha_question = $captcha['question'];

// =====================================
// PROCESSAR LOGIN
// =====================================
$message = '';
$toastType = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $telefone = $_POST['telefone'];
    $senha = $_POST['senha'];
    $captcha_input = $_POST['captcha'] ?? '';

    // Validar CAPTCHA usando o banco
    if (!validateCaptcha($captcha_input)) {
        $message = "Resposta do cálculo incorreta!";
        $toastType = "error";
        
        // Gerar nova CAPTCHA após erro
        $captcha = generateCaptcha();
        $captcha_question = $captcha['question'];
    } else {
        // Limpar telefone
        $telefone = preg_replace('/[^0-9]/', '', $telefone);

        if (strlen($telefone) !== 13 || substr($telefone, 0, 2) !== '55') {
            $message = "Número de telefone inválido. Use o formato: +55 XX XXXXX-XXXX.";
            $toastType = "error";
            
            // Gerar nova CAPTCHA após erro
            $captcha = generateCaptcha();
            $captcha_question = $captcha['question'];
        } else {
            // Rate limiting simples
            $ip = $_SERVER['REMOTE_ADDR'];
            
            // Verificar se tabela existe, se não, criar
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
                $stmt->execute([$ip]);
                $attempts = $stmt->fetchColumn();
            } catch (PDOException $e) {
                // Criar tabela se não existir
                $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ip VARCHAR(45) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX(ip, created_at)
                )");
                $attempts = 0;
            }

            if ($attempts >= 5) {
                $message = "Muitas tentativas. Tente novamente em 15 minutos.";
                $toastType = "error";
                
                // Gerar nova CAPTCHA após erro
                $captcha = generateCaptcha();
                $captcha_question = $captcha['question'];
            } else {
                $stmt = $pdo->prepare("SELECT id, telefone, senha FROM usuarios WHERE telefone = :telefone");
                $stmt->execute([':telefone' => $telefone]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($senha, $user['senha'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['telefone'] = $user['telefone'];

                    header("Location: ../inicio/");
                    exit();
                } else {
                    // Registrar tentativa de login falhada
                    $stmt = $pdo->prepare("INSERT INTO login_attempts (ip, created_at) VALUES (?, NOW())");
                    $stmt->execute([$ip]);
                    
                    $message = "Telefone ou senha incorretos.";
                    $toastType = "error";
                    
                    // Gerar nova CAPTCHA após erro
                    $captcha = generateCaptcha();
                    $captcha_question = $captcha['question'];
                }
            }
        }
    }

    if ($message) {
        header("Location: ./?message=" . urlencode($message) . "&toastType=" . urlencode($toastType));
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Meta Tags -->
    <title><?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?></title>
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
    <link rel="apple-touch-icon" sizes="120x120" href="assets/images/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon/favicon-16x16.png">
    <link rel="manifest" href="assets/images/favicon/site.webmanifest">
    <meta name="msapplication-TileColor" content="#ffffff">
    <meta name="theme-color" content="#ffffff">
    <!-- Fonts & Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
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
        padding: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
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
            radial-gradient(circle at 80% 80%, rgba(139, 92, 246, 0.1) 0%, transparent 50%),
            radial-gradient(circle at 50% 50%, rgba(16, 185, 129, 0.05) 0%, transparent 70%);
        pointer-events: none;
        z-index: -1;
    }

    .container {
        max-width: 420px;
        width: 100%;
        background: var(--blur-bg);
        backdrop-filter: blur(25px);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        padding: 30px;
        position: relative;
        overflow: hidden;
        box-shadow: var(--shadow-lg);
        animation: fadeInUp 0.8s ease-out;
    }

    .container::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--success-color), var(--info-color), var(--purple-color));
        animation: gradientShift 3s ease-in-out infinite;
    }

    .container::after {
        content: '';
        position: absolute;
        top: -50%;
        right: -30%;
        width: 150px;
        height: 150px;
        background: radial-gradient(circle, rgba(59, 130, 246, 0.1) 0%, transparent 70%);
        border-radius: 50%;
        animation: float 8s ease-in-out infinite;
    }

    /* Header */
    .header {
        text-align: center;
        margin-bottom: 40px;
        position: relative;
        z-index: 2;
    }

    .logo-img {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, var(--secondary-color), var(--info-color));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 25px auto;
        font-size: 36px;
        color: white;
        box-shadow: 0 10px 30px -5px rgba(59, 130, 246, 0.4);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        position: relative;
    }

    .logo-img:hover {
        transform: scale(1.05) rotate(5deg);
        box-shadow: 0 15px 40px -5px rgba(59, 130, 246, 0.6);
    }

    .logo-img i {
        font-size: 36px;
        color: white;
    }

    .title {
        font-size: 28px;
        font-weight: 800;
        margin-bottom: 8px;
        background: linear-gradient(135deg, var(--text-color), var(--secondary-color));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .subtitle {
        font-size: 16px;
        color: rgba(255, 255, 255, 0.7);
        margin-bottom: 20px;
    }

    /* REMOVIDO: Banner/Image styles */
    /* .image-banner { ... } */

    /* Form */
    form {
        position: relative;
        z-index: 2;
    }

    .input-group {
        position: relative;
        margin-bottom: 20px;
        transition: all 0.3s ease;
    }

    .input-group:hover {
        transform: translateY(-2px);
    }

    .input-group label {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: rgba(255, 255, 255, 0.7);
        z-index: 1;
        transition: all 0.3s ease;
    }

    .input-group input {
        width: 100%;
        background: rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(20px);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius-sm);
        padding: 15px 15px 15px 50px;
        color: var(--text-color);
        font-size: 16px;
        font-family: 'Inter', sans-serif;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        position: relative;
        z-index: 2;
    }

    .input-group input:focus {
        outline: none;
        border-color: var(--info-color);
        background: rgba(255, 255, 255, 0.12);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        transform: translateY(-2px);
    }

    .input-group input:focus + label {
        color: var(--info-color);
    }

    .input-group input::placeholder {
        color: rgba(255, 255, 255, 0.5);
        transition: opacity 0.3s ease;
    }

    .input-group input:focus::placeholder {
        opacity: 0;
    }

    /* CAPTCHA Input sem ícone */
    .captcha-input {
        padding: 15px !important;
    }

    /* CAPTCHA Question */
    .captcha-question {
        margin-top: 8px;
    }

    .captcha-question strong {
        color: var(--info-color);
        font-size: 16px;
        display: block;
        text-align: center;
        background: rgba(59, 130, 246, 0.15);
        padding: 12px 16px;
        border-radius: 8px;
        border: 1px solid rgba(59, 130, 246, 0.3);
        font-weight: 600;
        letter-spacing: 1px;
    }

    /* Password Toggle */
    .toggle-password {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: rgba(255, 255, 255, 0.7);
        cursor: pointer;
        z-index: 3;
        padding: 5px;
        border-radius: 4px;
        transition: all 0.3s ease;
    }

    .toggle-password:hover {
        color: var(--info-color);
        background: rgba(59, 130, 246, 0.1);
    }

    /* Remember Me Checkbox */
    .checkbox-group {
        display: flex;
        align-items: center;
        margin-bottom: 25px;
        gap: 10px;
    }

    .checkbox-group input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: var(--info-color);
        cursor: pointer;
    }

    .checkbox-group label {
        color: rgba(255, 255, 255, 0.8);
        cursor: pointer;
        font-size: 14px;
        user-select: none;
    }

    /* Submit Button */
    .submit-btn {
        width: 100%;
        background: linear-gradient(135deg, var(--success-color), var(--info-color));
        border: none;
        border-radius: var(--border-radius-sm);
        padding: 18px;
        color: white;
        font-size: 16px;
        font-weight: 700;
        font-family: 'Inter', sans-serif;
        cursor: pointer;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        position: relative;
        overflow: hidden;
        margin-bottom: 20px;
        box-shadow: 0 8px 25px -5px rgba(16, 185, 129, 0.4);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .submit-btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: left 0.6s ease;
    }

    .submit-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 35px -5px rgba(16, 185, 129, 0.6);
    }

    .submit-btn:hover::before {
        left: 100%;
    }

    .submit-btn:active {
        transform: translateY(-1px);
    }

    .submit-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    .submit-btn:disabled:hover {
        transform: none;
        box-shadow: 0 8px 25px -5px rgba(16, 185, 129, 0.4);
    }

    .submit-btn .loading {
        display: none;
    }

    .submit-btn.loading .loading {
        display: inline-block;
        animation: spin 1s linear infinite;
    }

    .submit-btn.loading .text {
        display: none;
    }

    /* Footer Link */
    .footer-link {
        text-align: center;
        font-size: 14px;
        color: rgba(255, 255, 255, 0.7);
    }

    .footer-link a {
        color: var(--info-color);
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
        position: relative;
    }

    .footer-link a::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 0;
        height: 2px;
        background: var(--info-color);
        transition: width 0.3s ease;
    }

    .footer-link a:hover {
        color: var(--secondary-color);
    }

    .footer-link a:hover::after {
        width: 100%;
    }

    /* Input Icons Colors */
    .input-group:nth-child(1) label i { color: var(--info-color); }
    .input-group:nth-child(2) label i { color: var(--warning-color); }

    /* Input Focus Effects */
    .input-group:nth-child(1) input:focus {
        border-color: var(--info-color);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
    }

    .input-group:nth-child(2) input:focus {
        border-color: var(--warning-color);
        box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.15);
    }

    .input-group:nth-child(3) input:focus {
        border-color: var(--success-color);
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
    }

    /* Toast Notification Styles */
    .toast {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 12px;
        color: white;
        font-weight: 600;
        z-index: 1000;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        box-shadow: var(--shadow-lg);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.15);
    }

    .toast.show {
        opacity: 1;
        transform: translateX(0);
    }

    .toast.success {
        background: linear-gradient(135deg, var(--success-color), #059669);
    }

    .toast.error {
        background: linear-gradient(135deg, var(--error-color), #DC2626);
    }

    .toast.info {
        background: linear-gradient(135deg, var(--info-color), #2563EB);
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

    @keyframes gradientShift {
        0%, 100% {
            background-position: 0% 50%;
        }
        50% {
            background-position: 100% 50%;
        }
    }

    @keyframes float {
        0%, 100% {
            transform: translateY(0px) rotate(0deg);
        }
        33% {
            transform: translateY(-10px) rotate(3deg);
        }
        66% {
            transform: translateY(-5px) rotate(-3deg);
        }
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Input Animation */
    .input-group {
        animation: fadeInUp 0.6s ease-out;
        animation-fill-mode: both;
    }

    .input-group:nth-child(1) { animation-delay: 0.1s; }
    .input-group:nth-child(2) { animation-delay: 0.2s; }
    .input-group:nth-child(3) { animation-delay: 0.3s; }

    .checkbox-group {
        animation: fadeInUp 0.6s ease-out;
        animation-delay: 0.4s;
        animation-fill-mode: both;
    }

    .submit-btn {
        animation: fadeInUp 0.6s ease-out;
        animation-delay: 0.5s;
        animation-fill-mode: both;
    }

    .footer-link {
        animation: fadeInUp 0.6s ease-out;
        animation-delay: 0.6s;
        animation-fill-mode: both;
    }

    /* Responsive */
    @media (max-width: 480px) {
        .container {
            margin: 10px;
            padding: 25px 20px;
        }

        .title {
            font-size: 24px;
        }

        .logo-img {
            width: 70px;
            height: 70px;
            font-size: 32px;
        }
    }
</style>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo-img">
                <i class="fas fa-chart-line"></i>
            </div>
            <h1 class="title">Bem-vindo à Finver Pro</h1>
            <p class="subtitle">Acesse sua conta de investimentos</p>
        </div>

        <!-- REMOVIDO: Banner -->
        <!-- <div class="image-banner">...</div> -->

        <!-- Login Form -->
        <form action="" method="post" onsubmit="handleSubmit(this)">
            <!-- Campo Telefone -->
            <div class="input-group">
                <label for="phone">
                    <i class="fa fa-phone"></i>
                </label>
                <input type="tel" name="telefone" id="phone" placeholder="Digite seu número de telefone" required>
            </div>

            <!-- Campo Senha -->
            <div class="input-group">
                <label for="password">
                    <i class="fa fa-lock"></i>
                </label>
                <input type="password" name="senha" id="password" placeholder="Digite sua senha" minlength="6" required>
                <button type="button" class="toggle-password" onclick="togglePassword('password', this)">
                    <i class="fa fa-eye"></i>
                </button>
            </div>

            <!-- Campo CAPTCHA Matemática SEM ÍCONE -->
            <div class="input-group">
                <input type="number" name="captcha" id="captcha" class="captcha-input"
                       placeholder="Digite o resultado" required>
                <div class="captcha-question">
                    <strong>🧮 Resolva: <?= $captcha_question ?> = ?</strong>
                </div>
            </div>

            <!-- Lembrar Conta -->
            <div class="checkbox-group">
                <input type="checkbox" id="lembrar-senha" name="lembrar-senha">
                <label for="lembrar-senha">Lembrar telefone</label>
            </div>

            <!-- Botão de Login -->
            <button type="submit" class="submit-btn">
                <span class="loading"><i class="fas fa-spinner"></i></span>
                <span class="text">
                    <i class="fas fa-sign-in-alt"></i>
                    Entrar
                </span>
            </button>
        </form>

        <!-- Footer -->
        <div class="footer-link">
            <p>Não tem uma conta? <a href="cadastro/">Cadastre-se</a></p>
        </div>
    </div>

    <script>
// Mostrar notificações da URL
        const urlParams = new URLSearchParams(window.location.search);
        const message = urlParams.get('message');
        const toastType = urlParams.get('toastType');

        if (message && toastType) {
            showToast(message, toastType === 'danger' ? 'error' : toastType);
            const cleanUrl = window.location.origin + window.location.pathname;
            window.history.replaceState({}, document.title, cleanUrl);
        }

        // Elementos do formulário
        const phoneInput = document.getElementById('phone');
        const passwordInput = document.getElementById('password');
        const rememberMeCheckbox = document.getElementById('lembrar-senha');

        // Carregar dados salvos
        window.onload = function() {
            if (localStorage.getItem('rememberMe') === 'true') {
                phoneInput.value = localStorage.getItem('phone') || '';
                rememberMeCheckbox.checked = true;
            }
        };

        // Formatação do telefone
        phoneInput.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length > 2 && !value.startsWith('55')) {
                value = '55' + value;
            }
            if (value.length > 13) {
                value = value.slice(0, 13);
            }
            if (value.length === 13) {
                this.value = `+55 ${value.slice(2, 4)} ${value.slice(4, 9)}-${value.slice(9)}`;
            } else if (value.length > 4) {
                this.value = `+55 ${value.slice(2, 4)} ${value.slice(4)}`;
            } else if (value.length > 2) {
                this.value = `+55 ${value.slice(2)}`;
            } else {
                this.value = value;
            }
        });

        // Toggle de senha
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            btn.querySelector('i').classList.toggle('fa-eye');
            btn.querySelector('i').classList.toggle('fa-eye-slash');
        }

        // Submissão do formulário
        function handleSubmit(form) {
            // Salvar dados se "lembrar" estiver marcado
            if (rememberMeCheckbox.checked) {
                localStorage.setItem('rememberMe', 'true');
                localStorage.setItem('phone', phoneInput.value);
            } else {
                localStorage.removeItem('rememberMe');
                localStorage.removeItem('phone');
            }

            // Adicionar loading state
            const submitBtn = form.querySelector('.submit-btn');
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
            
            showToast('Verificando credenciais...', 'info');
        }

        // Função para mostrar toast
        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => toast.classList.add('show'), 100);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    if (document.body.contains(toast)) {
                        document.body.removeChild(toast);
                    }
                }, 400);
            }, 4000);
        }
    </script>
</body>
</html>