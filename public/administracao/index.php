<?php
/**
 * ========================================
 * FINVER PRO - PAINEL ADMINISTRATIVO
 * Página de Login Administrativo
 * ========================================
 */

session_start();

// Verificar se já está logado como admin
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard/");
    exit();
}

require_once '../config/database.php';

$message = '';
$messageType = '';

// Processar login administrativo
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $senha = $_POST['senha'];
    
    if (empty($email) || empty($senha)) {
        $message = 'Por favor, preencha todos os campos.';
        $messageType = 'error';
    } else {
        try {
            $db = Database::getInstance();
            
            // Buscar administrador
            $admin = $db->fetchOne(
                "SELECT id, email, senha, nome, nivel, ativo FROM administrador WHERE email = ? AND ativo = 1",
                [$email]
            );
            
            if ($admin && password_verify($senha, $admin['senha'])) {
                // Login bem-sucedido
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_nome'] = $admin['nome'];
                $_SESSION['admin_nivel'] = $admin['nivel'];
                $_SESSION['admin_login_time'] = time();
                
                // Registrar log de login (opcional)
                $db->query(
                    "INSERT INTO login_attempts (ip, created_at) VALUES (?, NOW())",
                    [$_SERVER['REMOTE_ADDR']]
                );
                
                header("Location: dashboard/");
                exit();
            } else {
                $message = 'Credenciais inválidas ou conta desativada.';
                $messageType = 'error';
            }
            
        } catch (Exception $e) {
            error_log("Erro no login administrativo: " . $e->getMessage());
            $message = 'Erro interno. Tente novamente.';
            $messageType = 'error';
        }
    }
}

// Obter configurações de personalização (se existirem)
try {
    $db = Database::getInstance();
    $textos = $db->fetchOne("SELECT titulo_site FROM configurar_textos LIMIT 1");
    $titulo_site = $textos['titulo_site'] ?? 'Finver Pro';
    
    $cores = $db->fetchOne("SELECT cor_1, cor_2, cor_3, cor_4, cor_5 FROM personalizar_cores LIMIT 1");
    if (!$cores) {
        $cores = [
            'cor_1' => '#121A1E',
            'cor_2' => '#FFFFFF', 
            'cor_3' => '#152731',
            'cor_4' => '#335D67',
            'cor_5' => '#152731'
        ];
    }
} catch (Exception $e) {
    $titulo_site = 'Finver Pro';
    $cores = [
        'cor_1' => '#121A1E',
        'cor_2' => '#FFFFFF',
        'cor_3' => '#152731', 
        'cor_4' => '#335D67',
        'cor_5' => '#152731'
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo_site) ?> - Painel Administrativo</title>
    
    <!-- Favicon -->
    <link rel="icon" href="assets/images/favicon.svg" type="image/x-icon">
    
    <!-- Fonts & Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: <?= htmlspecialchars($cores['cor_3']) ?>;
            --secondary-color: <?= htmlspecialchars($cores['cor_4']) ?>;
            --background-color: <?= htmlspecialchars($cores['cor_1']) ?>;
            --text-color: <?= htmlspecialchars($cores['cor_2']) ?>;
            --accent-color: <?= htmlspecialchars($cores['cor_5']) ?>;
            --success-color: #10B981;
            --error-color: #EF4444;
            --warning-color: #F59E0B;
            --border-radius: 12px;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, var(--background-color) 0%, var(--accent-color) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-color);
            padding: 20px;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius);
            padding: 2.5rem;
            width: 100%;
            max-width: 420px;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }
        
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--success-color), var(--secondary-color), var(--warning-color));
        }
        
        .logo-section {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: white;
            box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.3);
        }
        
        .title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--text-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .subtitle {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.875rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.9);
        }
        
        .form-input {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 3rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius);
            color: var(--text-color);
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--secondary-color);
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
        }
        
        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.6);
            margin-top: 0.75rem;
        }
        
        .btn-login {
            width: 100%;
            padding: 0.875rem;
            background: linear-gradient(135deg, var(--success-color), var(--secondary-color));
            border: none;
            border-radius: var(--border-radius);
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 0.875rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #FEE2E2;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #D1FAE5;
        }
        
        .footer-links {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .footer-links a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-size: 0.875rem;
            transition: color 0.3s ease;
        }
        
        .footer-links a:hover {
            color: var(--secondary-color);
        }
        
        /* Animações */
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
        
        .login-container {
            animation: fadeInUp 0.6s ease-out;
        }
        
        /* Responsividade */
        @media (max-width: 480px) {
            .login-container {
                padding: 1.5rem;
                margin: 1rem;
            }
            
            .title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-section">
            <div class="logo">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h1 class="title">Painel Admin</h1>
            <p class="subtitle">Acesso Administrativo - <?= htmlspecialchars($titulo_site) ?></p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <i class="fas fa-<?= $messageType === 'error' ? 'exclamation-triangle' : 'check-circle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email" class="form-label">Email</label>
                <div style="position: relative;">
                    <i class="fas fa-envelope input-icon"></i>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-input" 
                        placeholder="admin@exemplo.com"
                        required
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    >
                </div>
            </div>
            
            <div class="form-group">
                <label for="senha" class="form-label">Senha</label>
                <div style="position: relative;">
                    <i class="fas fa-lock input-icon"></i>
                    <input 
                        type="password" 
                        id="senha" 
                        name="senha" 
                        class="form-input" 
                        placeholder="Digite sua senha"
                        required
                    >
                </div>
            </div>
            
            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Entrar no Painel
            </button>
        </form>
        
        <div class="footer-links">
            <a href="../">← Voltar ao Site</a>
        </div>
    </div>
    
    <script>
        // Adicionar foco automático no campo email
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('email').focus();
        });
        
        // Animação no botão de login
        document.querySelector('.btn-login').addEventListener('click', function(e) {
            if (this.querySelector('.fas')) {
                this.querySelector('.fas').className = 'fas fa-spinner fa-spin';
            }
        });
    </script>
</body>
</html>