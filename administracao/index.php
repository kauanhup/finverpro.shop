<?php
session_start();
require_once '../bank/db.php';

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

// Verificar se já está logado como admin
if (isset($_SESSION['user_id']) && isset($_SESSION['user_tipo']) && $_SESSION['user_tipo'] === 'admin') {
    header('Location: ./dashboard/');
    exit();
}

// Processar login
if ($_POST) {
    $email_or_phone = trim($_POST['email_or_phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $admin_phrase = $_POST['admin_phrase'] ?? '';
    $captcha = intval($_POST['captcha'] ?? 0);
    $captcha_answer = intval($_POST['captcha_answer'] ?? 0);
    
    // Validar CAPTCHA
    if ($captcha !== $captcha_answer) {
        $login_error = 'Código de verificação incorreto';
    } 
    // Validar campos obrigatórios
    else if (empty($email_or_phone) || empty($password) || empty($admin_phrase)) {
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
                // Verificar se é admin
                if ($user['tipo_usuario'] !== 'admin') {
                    $login_error = 'Acesso negado. Você não tem permissão de administrador.';
                    
                    // Registrar tentativa de login não autorizada
                    $log_sql = "INSERT INTO tentativas_login (telefone, ip_address, sucesso, user_agent) 
                               VALUES (?, ?, ?, ?)";
                    $log_stmt = $pdo->prepare($log_sql);
                    $log_stmt->execute([
                        $user['telefone'],
                        $_SERVER['REMOTE_ADDR'] ?? '',
                        0,
                        $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]);
                    
                    // Redirecionar para login de usuário comum após 3 segundos
                    echo '<script>
                        setTimeout(function() {
                            window.location.href = "../index.php";
                        }, 3000);
                    </script>';
                }
                // Verificar frase administrativa
                else if ($admin_phrase !== "hash174727274837277;'akgsvsjfiamqlbxue>#^&÷*$<") {
                    $login_error = 'Frase administrativa incorreta';
                    
                    // Registrar tentativa de login com frase incorreta
                    $log_sql = "INSERT INTO logs_sistema (usuario_id, acao, dados_novos, ip_address, user_agent) 
                               VALUES (?, ?, ?, ?, ?)";
                    $log_stmt = $pdo->prepare($log_sql);
                    $log_stmt->execute([
                        $user['id'],
                        'tentativa_login_admin_frase_incorreta',
                        json_encode(['frase_tentativa' => substr($admin_phrase, 0, 20) . '...']),
                        $_SERVER['REMOTE_ADDR'] ?? '',
                        $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]);
                }
                else {
                    // Login administrativo bem-sucedido
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_nome'] = $user['nome'];
                    $_SESSION['user_telefone'] = $user['telefone'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_tipo'] = $user['tipo_usuario'];
                    $_SESSION['admin_authenticated'] = true;
                    
                    // Atualizar último login
                    $update_sql = "UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?";
                    $update_stmt = $pdo->prepare($update_sql);
                    $update_stmt->execute([$user['id']]);
                    
                    // Registrar login administrativo bem-sucedido
                    $log_sql = "INSERT INTO tentativas_login (telefone, ip_address, sucesso, user_agent) 
                               VALUES (?, ?, ?, ?)";
                    $log_stmt = $pdo->prepare($log_sql);
                    $log_stmt->execute([
                        $user['telefone'],
                        $_SERVER['REMOTE_ADDR'] ?? '',
                        1,
                        $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]);
                    
                    // Log de acesso administrativo
                    $log_admin_sql = "INSERT INTO logs_sistema (usuario_id, acao, dados_novos, ip_address, user_agent) 
                                     VALUES (?, ?, ?, ?, ?)";
                    $log_admin_stmt = $pdo->prepare($log_admin_sql);
                    $log_admin_stmt->execute([
                        $user['id'],
                        'login_admin_sucesso',
                        json_encode(['timestamp' => date('Y-m-d H:i:s')]),
                        $_SERVER['REMOTE_ADDR'] ?? '',
                        $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]);
                    
                    $login_success = 'Login administrativo realizado com sucesso!';
                    
                    header('Location: ./dashboard/');
                    exit();
                }
                
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
            error_log("Admin login error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FinverPro - Login Administrativo</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/login.css">
    
    <style>
        /* Estilo específico para login administrativo */
        .header {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            border-bottom: 3px solid #dc2626;
        }
        
        .welcome-header {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            position: relative;
        }
        
        .welcome-header::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="admin-pattern" patternUnits="userSpaceOnUse" width="20" height="20"><circle cx="10" cy="10" r="1" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23admin-pattern)"/></svg>');
            opacity: 0.3;
        }
        
        .welcome-title {
            color: #ffffff;
            position: relative;
            z-index: 1;
        }
        
        .welcome-title::before {
            content: "🛡️ ";
            margin-right: 8px;
        }
        
        .welcome-subtitle {
            color: #e5e7eb;
            position: relative;
            z-index: 1;
        }
        
        .admin-warning {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            color: #92400e;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }
        
        .admin-warning i {
            margin-right: 8px;
        }
        
        .form-group.admin-phrase {
            background: #f8fafc;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
        }
        
        .form-group.admin-phrase .form-label {
            color: #374151;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .form-group.admin-phrase .form-input {
            font-family: 'Courier New', monospace;
            font-size: 14px;
            background: #ffffff;
            border: 1px solid #d1d5db;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            border: none;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(30, 64, 175, 0.3);
        }
        
        .form-links .link {
            color: #1e40af;
        }
        
        .form-links .link:hover {
            color: #1e3c72;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo-container">
                <div class="logo-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="logo-text">FinverPro Admin</div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="login-container fade-in">
            <!-- Welcome Header -->
            <div class="welcome-header">
                <div class="welcome-content">
                    <h1 class="welcome-title">Painel Administrativo</h1>
                    <p class="welcome-subtitle">Acesso restrito para administradores</p>
                </div>
            </div>

            <!-- Form Section -->
            <div class="form-section">
                <div class="admin-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Área Restrita:</strong> Este é o painel administrativo. Acesso apenas para administradores autorizados.
                </div>

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

                    <div class="form-group admin-phrase">
                        <label class="form-label">
                            <i class="fas fa-key"></i> Frase Administrativa
                        </label>
                        <input type="password" class="form-input" name="admin_phrase" 
                               placeholder="Digite a frase administrativa" required>
                        <div class="error-message" id="error-admin_phrase"></div>
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
                        <i class="fas fa-shield-alt"></i>
                        Acessar Painel Administrativo
                    </button>

                    <div class="form-links">
                        <a href="../index.php" class="link">
                            <i class="fas fa-arrow-left"></i> 
                            Voltar para login de usuário
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- JavaScript -->
    <script src="../assets/js/login.js"></script>
    
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
            
            // Additional validation for admin phrase
            const adminPhrase = document.querySelector('input[name="admin_phrase"]').value;
            if (adminPhrase.trim() === '') {
                showToast('Frase administrativa é obrigatória', 'error');
                e.preventDefault();
                return false;
            }
            
            // Show loading state
            const submitBtn = document.getElementById('submit-btn');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verificando credenciais...';
            submitBtn.disabled = true;
            
            // Allow form to submit normally to PHP
            return true;
        });

        // Enhanced form validation for admin
        function validateForm() {
            let isValid = true;
            const fields = [
                'email_or_phone',
                'password',
                'admin_phrase',
                'captcha'
            ];
            
            fields.forEach(field => {
                const input = document.querySelector(`[name="${field}"]`);
                const errorDiv = document.getElementById(`error-${field}`);
                
                if (!input.value.trim()) {
                    errorDiv.textContent = 'Campo obrigatório';
                    errorDiv.style.display = 'block';
                    isValid = false;
                } else {
                    errorDiv.style.display = 'none';
                }
            });
            
            // Validate captcha
            const captcha = parseInt(document.querySelector('[name="captcha"]').value);
            if (captcha !== captchaAnswer) {
                document.getElementById('error-captcha').textContent = 'Código de verificação incorreto';
                document.getElementById('error-captcha').style.display = 'block';
                isValid = false;
            }
            
            return isValid;
        }

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
                showToast('Redirecionando para painel...', 'info');
            }, 1500);
        });
        <?php endif; ?>

        // Security: Clear admin phrase field on page unload
        window.addEventListener('beforeunload', function() {
            const adminPhraseField = document.querySelector('input[name="admin_phrase"]');
            if (adminPhraseField) {
                adminPhraseField.value = '';
            }
        });
    </script>
</body>
</html>