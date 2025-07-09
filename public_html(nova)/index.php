<?php
session_start();
require 'bank/db.php';

// Redirecionar se já estiver logado
if (isset($_SESSION['user_id'])) {
    header("Location: inicio/");
    exit();
}

try {
    $pdo = getDBConnection();
    
    // ===================================
    // BUSCAR CONFIGURAÇÕES DO SITE
    // ===================================
    $stmt = $pdo->query("SELECT * FROM configuracoes WHERE categoria IN ('sistema', 'design') ORDER BY categoria, chave");
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organizar configurações por chave
    $config = [];
    foreach ($configs as $item) {
        $config[$item['chave']] = $item['valor'];
    }
    
    // Valores padrão se não existirem
    $titulo_site = $config['nome_site'] ?? 'Finver Pro';
    $descricao_site = $config['descricao_site'] ?? 'Mude sua vida financeira: deixe nossa IA investir por você e conquiste a liberdade';
    $keywords_site = $config['keywords_site'] ?? 'investimentos com inteligência artificial, robô de investimento automático, renda passiva online';
    $link_site = $config['url_site'] ?? 'https://finverpro.shop';
    
    // Buscar configurações de cores da tabela personalizacao
    $stmt = $pdo->query("SELECT elemento, valor FROM personalizacao WHERE categoria = 'cores'");
    $cores_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $cores = [
        'cor_1' => '#0F172A',
        'cor_2' => '#FFFFFF', 
        'cor_3' => '#3B82F6',
        'cor_4' => '#10B981',
        'cor_5' => '#1E293B'
    ];
    
    foreach ($cores_result as $cor) {
        $cores[$cor['elemento']] = $cor['valor'];
    }
    
} catch (Exception $e) {
    error_log("Erro ao buscar configurações: " . $e->getMessage());
    // Valores padrão em caso de erro
    $titulo_site = 'Finver Pro';
    $descricao_site = 'Mude sua vida financeira: deixe nossa IA investir por você e conquiste a liberdade';
    $keywords_site = 'investimentos com inteligência artificial, robô de investimento automático, renda passiva online';
    $link_site = 'https://finverpro.shop';
    $cores = [
        'cor_1' => '#0F172A',
        'cor_2' => '#FFFFFF', 
        'cor_3' => '#3B82F6',
        'cor_4' => '#10B981',
        'cor_5' => '#1E293B'
    ];
}

// ===================================
// GERAÇÃO DE CAPTCHA
// ===================================
function generateCaptcha() {
    $num1 = rand(1, 10);
    $num2 = rand(1, 10);
    $operations = ['+', '-', '*'];
    $operation = $operations[array_rand($operations)];
    
    switch ($operation) {
        case '+':
            $answer = $num1 + $num2;
            break;
        case '-':
            if ($num1 < $num2) [$num1, $num2] = [$num2, $num1];
            $answer = $num1 - $num2;
            break;
        case '*':
            $answer = $num1 * $num2;
            break;
    }
    
    $question = "$num1 $operation $num2";
    
    // Salvar no banco com sessão atual
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO captcha_sessions (session_id, question, answer, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([session_id(), $question, $answer, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {
        error_log("Erro ao salvar captcha: " . $e->getMessage());
    }
    
    return ['question' => $question, 'answer' => $answer];
}

// Gerar captcha inicial
$captcha = generateCaptcha();
$captcha_question = $captcha['question'];

// ===================================
// PROCESSAR LOGIN
// ===================================
$message = '';
$toastType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $telefone = $_POST['telefone'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $captcha_answer = $_POST['captcha'] ?? '';
    
    // Verificar captcha
    $stmt = $pdo->prepare("
        SELECT answer FROM captcha_sessions 
        WHERE session_id = ? AND ip_address = ? AND used = 0 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([session_id(), $_SERVER['REMOTE_ADDR'] ?? '']);
    $captcha_row = $stmt->fetch();
    
    if (!$captcha_row || (int)$captcha_answer !== (int)$captcha_row['answer']) {
        $message = "Captcha incorreto.";
        $toastType = "error";
        $captcha = generateCaptcha();
        $captcha_question = $captcha['question'];
    } else {
        // Marcar captcha como usado
        $stmt = $pdo->prepare("UPDATE captcha_sessions SET used = 1 WHERE session_id = ? AND ip_address = ?");
        $stmt->execute([session_id(), $_SERVER['REMOTE_ADDR'] ?? '']);
        
        // Verificar tentativas de login
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as attempts 
            FROM tentativas_login 
            WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $stmt->execute([$ip]);
        $attempts = $stmt->fetchColumn();
        
        if ($attempts >= 5) {
            $message = "Muitas tentativas. Tente novamente em 15 minutos.";
            $toastType = "error";
            $captcha = generateCaptcha();
            $captcha_question = $captcha['question'];
        } else {
            // CORREÇÃO: Buscar dados atualizados da tabela usuarios
            $stmt = $pdo->prepare("SELECT id, telefone, senha, tipo_usuario, status FROM usuarios WHERE telefone = :telefone");
            $stmt->execute([':telefone' => $telefone]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($senha, $user['senha'])) {
                // Verificar se usuário está ativo
                if ($user['status'] !== 'ativo') {
                    $message = "Sua conta está inativa. Entre em contato com o suporte.";
                    $toastType = "error";
                } else {
                    // Registrar tentativa de login bem-sucedida
                    $stmt = $pdo->prepare("INSERT INTO tentativas_login (telefone, ip_address, sucesso, created_at) VALUES (?, ?, 1, NOW())");
                    $stmt->execute([$telefone, $ip]);
                    
                    // Atualizar último login
                    $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['telefone'] = $user['telefone'];
                    $_SESSION['tipo_usuario'] = $user['tipo_usuario'];
                    $_SESSION['status'] = $user['status'];

                    header("Location: inicio/");
                    exit();
                }
            } else {
                // Registrar tentativa de login falhada
                $stmt = $pdo->prepare("INSERT INTO tentativas_login (telefone, ip_address, sucesso, created_at) VALUES (?, ?, 0, NOW())");
                $stmt->execute([$telefone, $ip]);
                
                $message = "Telefone ou senha incorretos.";
                $toastType = "error";
                $captcha = generateCaptcha();
                $captcha_question = $captcha['question'];
            }
        }
    }

    if ($message) {
        header("Location: ./?message=" . urlencode($message) . "&toastType=" . urlencode($toastType));
        exit();
    }
}

// Verificar mensagens da URL
if (isset($_GET['message'])) {
    $message = $_GET['message'];
    $toastType = $_GET['toastType'] ?? 'info';
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

    .toast.warning {
        background: linear-gradient(135deg, var(--warning-color), #D97706);
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
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }

    @keyframes float {
        0%, 100% { transform: translate(0, 0) rotate(0deg); }
        50% { transform: translate(-20px, -20px) rotate(180deg); }
    }

    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    /* Responsive */
    @media (max-width: 480px) {
        body {
            padding: 10px;
        }
        
        .container {
            padding: 20px;
        }
        
        .title {
            font-size: 24px;
        }
        
        .subtitle {
            font-size: 14px;
        }
    }
</style>

<body>
    <div class="container">
        <div class="header">
            <div class="logo-img">
                <i class="fas fa-chart-line"></i>
            </div>
            <h1 class="title"><?= htmlspecialchars($titulo_site) ?></h1>
            <p class="subtitle">Acesse sua conta para começar</p>
        </div>

        <form method="POST" action="" id="loginForm">
            <div class="input-group">
                <label for="telefone"><i class="fas fa-phone"></i></label>
                <input type="tel" 
                       name="telefone" 
                       id="telefone" 
                       placeholder="Digite seu telefone" 
                       required
                       value="<?= isset($_POST['telefone']) ? htmlspecialchars($_POST['telefone']) : '' ?>">
            </div>

            <div class="input-group">
                <label for="senha"><i class="fas fa-lock"></i></label>
                <input type="password" 
                       name="senha" 
                       id="senha" 
                       placeholder="Digite sua senha" 
                       required>
                <button type="button" class="toggle-password" onclick="togglePassword('senha')">
                    <i class="fas fa-eye"></i>
                </button>
            </div>

            <div class="input-group">
                <input type="number" 
                       name="captcha" 
                       id="captcha" 
                       placeholder="Resultado da operação"
                       class="captcha-input"
                       required>
                <div class="captcha-question">
                    <strong>Quanto é: <?= htmlspecialchars($captcha_question) ?> = ?</strong>
                </div>
            </div>

            <div class="checkbox-group">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Lembrar de mim</label>
            </div>

            <button type="submit" class="submit-btn" id="submitBtn">
                <i class="fas fa-spinner fa-spin loading"></i>
                <span class="text">
                    <i class="fas fa-sign-in-alt"></i>
                    Entrar
                </span>
            </button>
        </form>

        <div class="footer-link">
            Não tem uma conta? <a href="cadastro/">Criar conta</a>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="toast <?= htmlspecialchars($toastType) ?>" id="toast">
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <script>
        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Form submission
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.classList.add('loading');
            btn.disabled = true;
        });

        // Phone input formatting
        document.getElementById('telefone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            if (value.length > 13) value = value.slice(0, 13);
            
            if (value.length >= 3) {
                if (value.length <= 5) {
                    value = `(${value.slice(0, 2)}) ${value.slice(2)}`;
                } else if (value.length <= 10) {
                    value = `(${value.slice(0, 2)}) ${value.slice(2, 7)}-${value.slice(7)}`;
                } else {
                    value = `(${value.slice(0, 2)}) ${value.slice(2, 7)}-${value.slice(7, 11)}`;
                }
            }
            
            e.target.value = value;
        });

        // Show toast notification
        <?php if ($message): ?>
        window.addEventListener('load', function() {
            const toast = document.getElementById('toast');
            if (toast) {
                toast.classList.add('show');
                setTimeout(function() {
                    toast.classList.remove('show');
                }, 5000);
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>