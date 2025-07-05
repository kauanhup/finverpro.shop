<?php
session_start();

// Verifica se o usuário já está logado
if (isset($_SESSION['user_id'])) {
    header("Location: ../inicio/");
    exit();
}

require '../bank/db.php';

// Conexão com o banco de dados
$pdo = getDBConnection();

// =====================================
// BUSCAR CONFIGURAÇÕES DE CADASTRO
// =====================================
try {
    $stmt = $pdo->query("SELECT * FROM configurar_cadastro LIMIT 1");
    $cadastroConfig = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Valores padrão se não existir configuração
    if (!$cadastroConfig) {
        $cadastroConfig = [
            'sms_enabled' => 0,
            'require_username' => 0,
            'twilio_sid' => '',
            'twilio_token' => '',
            'twilio_phone' => '',
            'require_invite_code' => 0,
            'min_password_length' => 6,
            'allow_registration' => 1
        ];
    }
} catch (Exception $e) {
    // Se tabela não existir, usar valores padrão
    $cadastroConfig = [
        'sms_enabled' => 0,
        'require_username' => 0,
        'twilio_sid' => '',
        'twilio_token' => '',
        'twilio_phone' => '',
        'require_invite_code' => 0,
        'min_password_length' => 6,
        'allow_registration' => 1
    ];
}

// Verificar se cadastros estão permitidos
if (!$cadastroConfig['allow_registration']) {
    $registration_disabled = true;
}

// =====================================
// BUSCAR CONFIGURAÇÕES DE PERSONALIZAÇÃO
// =====================================

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

// Consulta as cores do banco de dados
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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Meta Tags -->
    <title><?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?></title>
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
    <link rel="apple-touch-icon" sizes="120x120" href="../assets/images/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon/favicon-16x16.png">
    <link rel="manifest" href="../assets/images/favicon/site.webmanifest">
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

    /* Disabled Registration Message */
    .disabled-message {
        text-align: center;
        padding: 40px 30px;
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.3);
        border-radius: var(--border-radius);
        margin: 20px 0;
    }

    .disabled-message i {
        font-size: 48px;
        color: var(--error-color);
        margin-bottom: 20px;
        display: block;
    }

    .disabled-message h2 {
        color: var(--error-color);
        margin-bottom: 15px;
        font-size: 24px;
    }

    .disabled-message p {
        color: rgba(255, 255, 255, 0.8);
        line-height: 1.6;
        margin-bottom: 20px;
    }

    /* Header */
    .header {
        text-align: center;
        margin-bottom: 35px;
        position: relative;
        z-index: 2;
    }

    .logo-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, var(--success-color), var(--info-color));
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 20px;
        font-size: 36px;
        color: white;
        box-shadow: 0 12px 35px -5px rgba(16, 185, 129, 0.4);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        position: relative;
        overflow: hidden;
    }

    .logo-icon::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
        transform: rotate(45deg);
        animation: shine 3s infinite;
    }

    .logo-icon:hover {
        transform: scale(1.05) rotate(5deg);
        box-shadow: 0 20px 50px -5px rgba(16, 185, 129, 0.6);
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
        margin-bottom: 8px;
    }

    .motivational-text {
        font-size: 14px;
        color: rgba(16, 185, 129, 0.9);
        font-weight: 600;
        padding: 12px 20px;
        background: rgba(16, 185, 129, 0.1);
        border: 1px solid rgba(16, 185, 129, 0.2);
        border-radius: var(--border-radius-sm);
        margin-bottom: 20px;
        position: relative;
        overflow: hidden;
    }

    .motivational-text::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(16, 185, 129, 0.1), transparent);
        animation: motivationalShine 4s infinite;
    }

    .motivational-text i {
        margin-right: 8px;
        animation: pulse 2s infinite;
    }

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

    /* SMS Verification Styles */
    .sms-verification {
        display: none;
        animation: fadeInUp 0.6s ease-out;
    }

    .sms-verification.show {
        display: block;
    }

    .verification-info {
        background: rgba(59, 130, 246, 0.1);
        border: 1px solid rgba(59, 130, 246, 0.3);
        border-radius: var(--border-radius-sm);
        padding: 15px;
        margin-bottom: 20px;
        text-align: center;
    }

    .verification-info i {
        color: var(--info-color);
        font-size: 20px;
        margin-bottom: 10px;
        display: block;
    }

    .verification-info p {
        font-size: 14px;
        color: rgba(255, 255, 255, 0.8);
        margin: 0;
    }

    .code-input-group {
        display: flex;
        gap: 10px;
        justify-content: center;
        margin-bottom: 20px;
    }

    .code-digit {
        width: 50px;
        height: 50px;
        text-align: center;
        font-size: 20px;
        font-weight: 600;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius-sm);
        color: var(--text-color);
        transition: all 0.3s ease;
    }

    .code-digit:focus {
        outline: none;
        border-color: var(--info-color);
        background: rgba(255, 255, 255, 0.12);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        transform: scale(1.05);
    }

    .resend-code {
        text-align: center;
        margin-bottom: 20px;
    }

    .resend-btn {
        background: none;
        border: none;
        color: var(--info-color);
        text-decoration: underline;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .resend-btn:hover {
        color: var(--secondary-color);
    }

    .resend-btn:disabled {
        color: rgba(255, 255, 255, 0.5);
        cursor: not-allowed;
        text-decoration: none;
    }

    .countdown {
        font-size: 12px;
        color: rgba(255, 255, 255, 0.6);
        margin-left: 5px;
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
    .input-group:nth-child(3) label i { color: var(--error-color); }
    .input-group:nth-child(4) label i { color: var(--success-color); }
    .input-group:nth-child(5) label i { color: var(--purple-color); }

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
        border-color: var(--error-color);
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15);
    }

    .input-group:nth-child(4) input:focus {
        border-color: var(--success-color);
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
    }

    .input-group:nth-child(5) input:focus {
        border-color: var(--purple-color);
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.15);
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

    @keyframes shine {
        0% {
            transform: translateX(-100%) translateY(-100%) rotate(45deg);
        }
        100% {
            transform: translateX(100%) translateY(100%) rotate(45deg);
        }
    }

    @keyframes motivationalShine {
        0% {
            left: -100%;
        }
        100% {
            left: 100%;
        }
    }

    @keyframes pulse {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.1);
        }
    }

    /* Input Animation */
    .input-group {
        animation: fadeInUp 0.6s ease-out;
        animation-fill-mode: both;
    }

    .input-group:nth-child(1) { animation-delay: 0.1s; }
    .input-group:nth-child(2) { animation-delay: 0.2s; }
    .input-group:nth-child(3) { animation-delay: 0.3s; }
    .input-group:nth-child(4) { animation-delay: 0.4s; }
    .input-group:nth-child(5) { animation-delay: 0.5s; }

    .submit-btn {
        animation: fadeInUp 0.6s ease-out;
        animation-delay: 0.6s;
        animation-fill-mode: both;
    }

    .footer-link {
        animation: fadeInUp 0.6s ease-out;
        animation-delay: 0.7s;
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

        .logo-icon {
            width: 70px;
            height: 70px;
            font-size: 32px;
        }

        .code-digit {
            width: 45px;
            height: 45px;
            font-size: 18px;
        }

        .motivational-text {
            font-size: 13px;
            padding: 10px 15px;
        }
    }
</style>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <h1 class="title">Criar Conta</h1>
            <p class="subtitle">Junte-se à nossa plataforma de investimentos</p>
            <div class="motivational-text">
                <i class="fas fa-rocket"></i>
                <strong>Seu futuro financeiro começa aqui!</strong> Transforme seus sonhos em realidade com investimentos inteligentes.
            </div>
        </div>

        <?php if (isset($registration_disabled)): ?>
            <!-- Cadastros Desabilitados -->
            <div class="disabled-message">
                <i class="fas fa-user-slash"></i>
                <h2>Cadastros Temporariamente Desabilitados</h2>
                <p>No momento, não estamos aceitando novos registros. Por favor, tente novamente mais tarde ou entre em contato conosco.</p>
                <div class="footer-link">
                    <p><a href="../">Voltar ao Login</a></p>
                </div>
            </div>
        <?php else: ?>
            <!-- Registration Form -->
            <form id="registrationForm" action="autentificacao.php" method="post">
                <div id="step1" class="registration-step">
                    <!-- Campo Telefone (sempre obrigatório) -->
                    <div class="input-group">
                        <label for="phone">
                            <i class="fa fa-phone"></i></label>
                        <input type="tel" name="telefone" id="phone" placeholder="Digite seu número de telefone" pattern="\+55\s\d{2}\s\d{5}-\d{4}" required>
                    </div>

                    <?php if ($cadastroConfig['require_username']): ?>
                    <!-- Campo Nome de Usuário (se habilitado) -->
                    <div class="input-group">
                        <label for="username">
                            <i class="fa fa-user"></i>
                        </label>
                        <input type="text" name="nome" id="username" placeholder="Digite seu nome de usuário" required>
                    </div>
                    <?php endif; ?>

                    <!-- Campo Senha -->
                    <div class="input-group">
                        <label for="password">
                            <i class="fa fa-lock"></i>
                        </label>
                        <input type="password" name="senha" id="password" 
                               placeholder="Digite sua senha" 
                               minlength="<?= $cadastroConfig['min_password_length'] ?>" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('password', this)">
                            <i class="fa fa-eye"></i>
                        </button>
                    </div>

                    <!-- Campo Confirmar Senha -->
                    <div class="input-group">
                        <label for="password_confirm">
                            <i class="fa fa-lock"></i>
                        </label>
                        <input type="password" name="senha_confirm" id="password_confirm" 
                               placeholder="Confirme sua senha" 
                               minlength="<?= $cadastroConfig['min_password_length'] ?>" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('password_confirm', this)">
                            <i class="fa fa-eye"></i>
                        </button>
                    </div>

                    <!-- Campo Código de Convite -->
                    <div class="input-group">
                        <label for="convite">
                            <i class="fa-solid fa-handshake"></i>
                        </label>
                        <input type="text" name="codigo_convite" id="convite" 
                               placeholder="Código de convite <?= $cadastroConfig['require_invite_code'] ? '(OBRIGATÓRIO)' : '(OPCIONAL)' ?>"
                               <?= $cadastroConfig['require_invite_code'] ? 'required' : '' ?>>
                    </div>

                    <?php if ($cadastroConfig['sms_enabled']): ?>
                    <!-- Botão para enviar SMS -->
                    <button type="button" id="sendSmsBtn" class="submit-btn">
                        <span class="loading"><i class="fas fa-spinner"></i></span>
                        <span class="text">
                            <i class="fas fa-mobile-alt"></i>
                            Enviar Código SMS
                        </span>
                    </button>
                    <?php else: ?>
                    <!-- Botão de cadastro direto (sem SMS) -->
                    <button type="submit" class="submit-btn">
                        <span class="loading"><i class="fas fa-spinner"></i></span>
                        <span class="text">
                            <i class="fas fa-rocket"></i>
                            Criar Conta
                        </span>
                    </button>
                    <?php endif; ?>
                </div>

                <?php if ($cadastroConfig['sms_enabled']): ?>
                <!-- SMS Verification Step -->
                <div id="step2" class="registration-step sms-verification">
                    <div class="verification-info">
                        <i class="fas fa-sms"></i>
                        <p>Enviamos um código de verificação para o seu celular. Digite o código recebido abaixo:</p>
                    </div>

                    <div class="code-input-group">
                        <input type="text" class="code-digit" maxlength="1" id="code1" oninput="moveToNext(this, 'code2')" onkeydown="moveToPrev(this, null, event)">
                        <input type="text" class="code-digit" maxlength="1" id="code2" oninput="moveToNext(this, 'code3')" onkeydown="moveToPrev(this, 'code1', event)">
                        <input type="text" class="code-digit" maxlength="1" id="code3" oninput="moveToNext(this, 'code4')" onkeydown="moveToPrev(this, 'code2', event)">
                        <input type="text" class="code-digit" maxlength="1" id="code4" oninput="moveToNext(this, 'code5')" onkeydown="moveToPrev(this, 'code3', event)">
                        <input type="text" class="code-digit" maxlength="1" id="code5" oninput="moveToNext(this, 'code6')" onkeydown="moveToPrev(this, 'code4', event)">
                        <input type="text" class="code-digit" maxlength="1" id="code6" oninput="moveToNext(this, null)" onkeydown="moveToPrev(this, 'code5', event)">
                    </div>

                    <input type="hidden" name="sms_code" id="sms_code">

                    <div class="resend-code">
                        <button type="button" id="resendBtn" class="resend-btn" onclick="resendSmsCode()">
                            Reenviar código
                        </button>
                        <span class="countdown" id="countdown"></span>
                    </div>

                    <button type="submit" id="verifyBtn" class="submit-btn" disabled>
                        <span class="loading"><i class="fas fa-spinner"></i></span>
                        <span class="text">
                            <i class="fas fa-rocket"></i>
                            Verificar e Criar Conta
                        </span>
                    </button>

                    <button type="button" id="backBtn" class="submit-btn" style="background: linear-gradient(135deg, #6B7280, #4B5563); margin-top: 10px;" onclick="goBackToStep1()">
                        <i class="fas fa-arrow-left"></i>
                        Voltar
                    </button>
                </div>
                <?php endif; ?>
            </form>
        <?php endif; ?>

        <div class="footer-link">
            <p>Já tem uma conta? <a href="../">Fazer Login</a></p>
        </div>
    </div>

    <script>
        // Configurações dinâmicas do PHP
        const CONFIG = {
            smsEnabled: <?= $cadastroConfig['sms_enabled'] ? 'true' : 'false' ?>,
            requireUsername: <?= $cadastroConfig['require_username'] ? 'true' : 'false' ?>,
            requireInviteCode: <?= $cadastroConfig['require_invite_code'] ? 'true' : 'false' ?>,
            minPasswordLength: <?= $cadastroConfig['min_password_length'] ?>
        };

        // Variables
        let isPhoneVerified = false;
        let resendTimer = 0;
        let timerInterval;

        // Preencher campo de convite com o código da URL
        const urlParams = new URLSearchParams(window.location.search);
        const codigoConvite = urlParams.get('ref');
        if (codigoConvite) {
            document.getElementById('convite').value = codigoConvite;
        }

        // Show notifications from URL params
        const message = urlParams.get('message');
        const toastType = urlParams.get('toastType');

        if (message && toastType) {
            showToast(message, toastType === 'danger' ? 'error' : toastType);
            const cleanUrl = window.location.origin + window.location.pathname;
            window.history.replaceState({}, document.title, cleanUrl);
        }

        // Phone input formatting
        const phoneInput = document.getElementById('phone');
        phoneInput.addEventListener('input', function () {
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

        // Function to send SMS code (integrate with Twilio here)
        function sendSmsCode(phoneNumber) {
            fetch('send_sms.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    phone: phoneNumber
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSmsStep();
                    showToast('Código SMS enviado com sucesso!', 'success');
                    startResendTimer();
                } else {
                    showToast(data.message || 'Erro ao enviar SMS. Tente novamente.', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showToast('Erro ao enviar SMS. Verifique sua conexão.', 'error');
            });
        }

        // Validação customizada baseada nas configurações
        function validateForm() {
            const phoneValue = phoneInput.value.replace(/\D/g, ''); 
            const password = document.getElementById('password').value;
            const passwordConfirm = document.getElementById('password_confirm').value;
            const username = CONFIG.requireUsername ? document.getElementById('username').value : '';
            const convite = document.getElementById('convite').value;
            
            // Validate phone
            if (phoneValue.length !== 13 || !phoneValue.startsWith('55')) {
                showToast('Por favor, preencha o número de telefone completo no formato: +55 XX XXXXX-XXXX.', 'error');
                return false;
            }
            
            // Validate username if required
            if (CONFIG.requireUsername && !username.trim()) {
                showToast('Nome de usuário é obrigatório!', 'error');
                return false;
            }
            
            // Validate password
            if (password !== passwordConfirm) {
                showToast('As senhas não conferem!', 'error');
                return false;
            }

            if (password.length < CONFIG.minPasswordLength) {
                showToast(`A senha deve ter pelo menos ${CONFIG.minPasswordLength} caracteres!`, 'error');
                return false;
            }

            // Validate invite code if required
            if (CONFIG.requireInviteCode && !convite.trim()) {
                showToast('Código de convite é obrigatório!', 'error');
                return false;
            }

            return true;
        }

        // Send SMS Code (updated) - só existe se SMS estiver ativo
        if (CONFIG.smsEnabled) {
            document.getElementById('sendSmsBtn').addEventListener('click', function() {
                if (!validateForm()) return;

                // Show loading state
                this.classList.add('loading');
                this.disabled = true;

                // Send SMS via API
                const phoneValue = phoneInput.value.replace(/\D/g, '');
                sendSmsCode(phoneValue);
                
                // Hide loading state after response
                setTimeout(() => {
                    this.classList.remove('loading');
                    this.disabled = false;
                }, 3000);
            });

            // Show SMS verification step
            function showSmsStep() {
                document.getElementById('step1').style.display = 'none';
                document.getElementById('step2').classList.add('show');
                
                // Focus on first code input
                setTimeout(() => {
                    document.getElementById('code1').focus();
                }, 300);
            }

            // Go back to step 1
            function goBackToStep1() {
                document.getElementById('step2').classList.remove('show');
                setTimeout(() => {
                    document.getElementById('step2').style.display = 'none';
                    document.getElementById('step1').style.display = 'block';
                }, 300);
                
                // Clear code inputs
                clearCodeInputs();
            }

            // Code input navigation
            function moveToNext(current, nextId) {
                if (current.value.length === 1 && nextId) {
                    document.getElementById(nextId).focus();
                }
                updateSmsCode();
                checkCodeComplete();
            }

            function moveToPrev(current, prevId, event) {
                if (event.key === 'Backspace' && current.value === '' && prevId) {
                    document.getElementById(prevId).focus();
                }
                updateSmsCode();
                checkCodeComplete();
            }

            // Update hidden SMS code field
            function updateSmsCode() {
                let code = '';
                for (let i = 1; i <= 6; i++) {
                    code += document.getElementById(`code${i}`).value;
                }
                document.getElementById('sms_code').value = code;
            }

            // Check if code is complete
            function checkCodeComplete() {
                const code = document.getElementById('sms_code').value;
                const verifyBtn = document.getElementById('verifyBtn');
                
                if (code.length === 6) {
                    verifyBtn.disabled = false;
                    verifyBtn.style.background = 'linear-gradient(135deg, var(--success-color), var(--info-color))';
                } else {
                    verifyBtn.disabled = true;
                    verifyBtn.style.background = 'linear-gradient(135deg, #6B7280, #4B5563)';
                }
            }

            // Clear code inputs
            function clearCodeInputs() {
                for (let i = 1; i <= 6; i++) {
                    document.getElementById(`code${i}`).value = '';
                }
                document.getElementById('sms_code').value = '';
                checkCodeComplete();
            }

            // Resend SMS code
            function resendSmsCode() {
                const phoneValue = phoneInput.value.replace(/\D/g, '');
                sendSmsCode(phoneValue);
                showToast('Código reenviado com sucesso!', 'info');
            }

            // Start resend timer
            function startResendTimer() {
                resendTimer = 60;
                const resendBtn = document.getElementById('resendBtn');
                const countdown = document.getElementById('countdown');
                
                resendBtn.disabled = true;
                
                timerInterval = setInterval(() => {
                    resendTimer--;
                    countdown.textContent = `(${resendTimer}s)`;
                    
                    if (resendTimer <= 0) {
                        clearInterval(timerInterval);
                        resendBtn.disabled = false;
                        countdown.textContent = '';
                    }
                }, 1000);
            }

            // Prevent form submission on enter in code inputs
            document.querySelectorAll('.code-digit').forEach(input => {
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                    }
                    
                    // Only allow numbers
                    if (!/[0-9]/.test(e.key) && !['Backspace', 'Delete', 'Tab', 'ArrowLeft', 'ArrowRight'].includes(e.key)) {
                        e.preventDefault();
                    }
                });
            });
        }

        // Toggle password visibility
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            btn.querySelector('i').classList.toggle('fa-eye');
            btn.querySelector('i').classList.toggle('fa-eye-slash');
        }

        // Form submission
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            if (CONFIG.smsEnabled) {
                const step2 = document.getElementById('step2');
                
                if (step2.classList.contains('show')) {
                    // SMS verification step - proceed with form submission
                    const smsCode = document.getElementById('sms_code').value;
                    
                    if (smsCode.length !== 6) {
                        e.preventDefault();
                        showToast('Digite o código de verificação completo!', 'error');
                        return;
                    }
                    
                    // Add loading state to verify button
                    const verifyBtn = document.getElementById('verifyBtn');
                    verifyBtn.classList.add('loading');
                    verifyBtn.disabled = true;
                    
                    // Form will submit normally to autentificacao.php
                    showToast('Verificando código...', 'info');
                }
            } else {
                // Cadastro direto sem SMS - validar o formulário
                if (!validateForm()) {
                    e.preventDefault();
                    return;
                }
                
                // Add loading state
                const submitBtn = this.querySelector('.submit-btn');
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
                
                showToast('Criando sua conta...', 'info');
            }
        });

        // Toast notification function
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