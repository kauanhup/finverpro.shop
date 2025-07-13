<?php
session_start();
require_once 'bank/db.php';

$login_error = '';
$login_success = '';

// Função para verificar senha (hash ou texto plano)
function verifyPassword($password, $hash) {
    // Primeiro tenta verificar se é um hash do password_hash()
    if (password_verify($password, $hash)) {
        return true;
    }
    
    // Se não funcionou, verifica se é texto plano (compatibilidade)
    if ($password === $hash) {
        return true;
    }
    
    // Também pode verificar MD5 se você usava antes
    if (md5($password) === $hash) {
        return true;
    }
    
    // Ou SHA1 se você usava antes
    if (sha1($password) === $hash) {
        return true;
    }
    
    return false;
}

// Verificar se já está logado
if (isset($_SESSION['user_id'])) {
    header('Location: ./inicio/');
    exit();
}

// Processar login
if ($_POST) {
    $email_or_phone = trim($_POST['email_or_phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $captcha = intval($_POST['captcha'] ?? 0);
    $captcha_answer = intval($_POST['captcha_answer'] ?? 0);
    
    // Validar CAPTCHA
    if ($captcha !== $captcha_answer) {
        $login_error = 'Código de verificação incorreto';
    } 
    // Validar campos obrigatórios
    else if (empty($email_or_phone) || empty($password)) {
        $login_error = 'Preencha todos os campos';
    }
    else {
        try {
            // Buscar usuário por telefone ou email
            $sql = "SELECT id, telefone, email, nome, senha, status, tipo_usuario 
                   FROM usuarios 
                   WHERE (telefone = ? OR email = ?) 
                   AND status = 'ativo'";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email_or_phone, $email_or_phone]);
            $user = $stmt->fetch();
            
            if ($user && verifyPassword($password, $user['senha'])) {
                // Login bem-sucedido
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_nome'] = $user['nome'];
                $_SESSION['user_telefone'] = $user['telefone'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_tipo'] = $user['tipo_usuario'];
                
                // Atualizar último login
                $update_sql = "UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?";
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute([$user['id']]);
                
                // Registrar tentativa de login bem-sucedida
                $log_sql = "INSERT INTO tentativas_login (telefone, ip_address, sucesso, user_agent) 
                           VALUES (?, ?, ?, ?)";
                $log_stmt = $pdo->prepare($log_sql);
                $log_stmt->execute([
                    $user['telefone'],
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    1,
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
                
                $login_success = 'Login realizado com sucesso!';
                
                // Redirecionar baseado no tipo de usuário
                if ($user['tipo_usuario'] === 'admin') {
                    header('Location: ./administracao/dashboard/');
                } else {
                    header('Location: ./inicio/');
                }
                exit();
                
            } else {
                // Login falhou
                $login_error = 'Email/telefone ou senha incorretos';
                
                // Registrar tentativa de login falhada
                $log_sql = "INSERT INTO tentativas_login (telefone, ip_address, sucesso, user_agent) 
                           VALUES (?, ?, ?, ?)";
                $log_stmt = $pdo->prepare($log_sql);
                $log_stmt->execute([
                    $email_or_phone,
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    0,
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            }
            
        } catch (PDOException $e) {
            $login_error = 'Erro interno. Tente novamente.';
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FinverPro - Login</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/login.css">
</head>

<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo-container">
                <div class="logo-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="logo-text">FinverPro</div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="login-container fade-in">
            <!-- Welcome Header -->
            <div class="welcome-header">
                <div class="welcome-content">
                    <h1 class="welcome-title">Bem-vindo de volta</h1>
                    <p class="welcome-subtitle">Continue sua jornada de investimentos</p>
                </div>
            </div>

            <!-- Form Section -->
            <div class="form-section">
                <!-- Login Form -->
                <form class="login-form" id="login-form" method="POST">
                    <?php if ($login_error): ?>
                        <div class="error-message" style="text-align: center; padding: 12px; background: #FEF2F2; border: 1px solid #FECACA; border-radius: 8px; color: #DC2626; margin-bottom: 20px;">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($login_error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($login_success): ?>
                        <div class="success-message" style="text-align: center; padding: 12px; background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 8px; color: #059669; margin-bottom: 20px;">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($login_success); ?>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label class="form-label">Email ou Telefone</label>
                        <input type="text" class="form-input" name="email_or_phone" 
                               value="<?php echo htmlspecialchars($_POST['email_or_phone'] ?? ''); ?>"
                               placeholder="Digite seu email ou telefone" required>
                        <div class="error-message" id="error-email_or_phone"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Senha</label>
                        <div class="password-container">
                            <input type="password" class="form-input" name="password" placeholder="Digite sua senha" required>
                            <button type="button" class="password-toggle" onclick="togglePassword()">
                                <i class="fas fa-eye" id="password-icon"></i>
                            </button>
                        </div>
                        <div class="error-message" id="error-password"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Verificação de Segurança</label>
                        <div class="captcha-container">
                            <div class="captcha-question" id="captcha-question">5 + 3 = ?</div>
                            <input type="number" class="form-input captcha-input" name="captcha" placeholder="?" required>
                        </div>
                        <button type="button" class="captcha-refresh" onclick="generateCaptcha()">
                            <i class="fas fa-sync-alt"></i>
                            Gerar novo cálculo
                        </button>
                        <div class="error-message" id="error-captcha"></div>
                        <input type="hidden" name="captcha_answer" id="captcha_answer" value="">
                    </div>

                    <button type="submit" class="btn btn-primary" id="submit-btn">
                        <i class="fas fa-sign-in-alt"></i>
                        Entrar na Plataforma
                    </button>

                    <div class="form-links">
                        <a href="recuperacao/recuperacao.html" class="link">Esqueceu sua senha?</a>
                        <div class="signup-link">
                            Ainda não tem uma conta? 
                            <a href="cadastro/cadastro.html">Cadastre-se gratuitamente</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- JavaScript -->
    <script src="assets/js/login.js"></script>
    
    <script>
        // Override form submission to include captcha validation
        document.getElementById('login-form').addEventListener('submit', function(e) {
            // Update hidden input with current captcha answer
            document.getElementById('captcha_answer').value = captchaAnswer;
            
            // Validate form on client side
            if (!validateForm()) {
                e.preventDefault();
                return false;
            }
            
            // Show loading state
            const submitBtn = document.getElementById('submit-btn');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Entrando...';
            submitBtn.disabled = true;
            
            // Allow form to submit normally to PHP
            return true;
        });

        // Regenerate captcha on page load if there was an error
        <?php if ($login_error): ?>
        document.addEventListener('DOMContentLoaded', function() {
            generateCaptcha();
            showToast('<?php echo addslashes($login_error); ?>', 'error');
        });
        <?php endif; ?>
        
        // Show success message if login was successful
        <?php if ($login_success): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showToast('<?php echo addslashes($login_success); ?>', 'success');
            setTimeout(() => {
                showToast('Redirecionando...', 'info');
            }, 1500);
        });
        <?php endif; ?>
    </script>
</body>
</html>