<?php
session_start();
require_once '../../../bank/db.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../");
    exit();
}

// Incluir o arquivo de conexão com o banco de dados
$pdo = getDBConnection();

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
    header('Location: ../../../');
    exit();
}

// Processar formulário se foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Configurações de cadastro - CORRIGINDO OS VALORES
        $sms_enabled = (isset($_POST['sms_enabled']) && $_POST['sms_enabled'] === '1') ? 1 : 0;
        $require_username = (isset($_POST['require_username']) && $_POST['require_username'] === '1') ? 1 : 0;
        $twilio_sid = $_POST['twilio_sid'] ?? '';
        $twilio_token = $_POST['twilio_token'] ?? '';
        $twilio_phone = $_POST['twilio_phone'] ?? '';
        $require_invite_code = (isset($_POST['require_invite_code']) && $_POST['require_invite_code'] === '1') ? 1 : 0;
        $min_password_length = intval($_POST['min_password_length'] ?? 6);
        $allow_registration = (isset($_POST['allow_registration']) && $_POST['allow_registration'] === '1') ? 1 : 0;
        $bonus_cadastro = floatval($_POST['bonus_cadastro'] ?? 0); // NOVO CAMPO
        
        // Verificar se já existe configuração
        $stmt = $pdo->query("SELECT id FROM configurar_cadastro LIMIT 1");
        $exists = $stmt->fetch();
        
        if ($exists) {
            // Atualizar configuração existente
            $stmt = $pdo->prepare("
                UPDATE configurar_cadastro SET 
                    sms_enabled = ?, 
                    require_username = ?, 
                    twilio_sid = ?, 
                    twilio_token = ?, 
                    twilio_phone = ?, 
                    require_invite_code = ?, 
                    min_password_length = ?, 
                    allow_registration = ?,
                    bonus_cadastro = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([
                $sms_enabled, $require_username, $twilio_sid, $twilio_token, 
                $twilio_phone, $require_invite_code, $min_password_length, 
                $allow_registration, $bonus_cadastro, $exists['id']
            ]);
        } else {
            // Inserir nova configuração
            $stmt = $pdo->prepare("
                INSERT INTO configurar_cadastro 
                (sms_enabled, require_username, twilio_sid, twilio_token, twilio_phone, require_invite_code, min_password_length, allow_registration, bonus_cadastro) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $sms_enabled, $require_username, $twilio_sid, $twilio_token, 
                $twilio_phone, $require_invite_code, $min_password_length, $allow_registration, $bonus_cadastro
            ]);
        }
        
        $pdo->commit();
        $success_message = "Configurações de cadastro atualizadas com sucesso!";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Erro ao salvar configurações: " . $e->getMessage();
    }
}

// Buscar configurações atuais
try {
    $stmt = $pdo->query("SELECT * FROM configurar_cadastro LIMIT 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Valores padrão se não existir configuração
    if (!$config) {
        $config = [
            'sms_enabled' => 0,
            'require_username' => 0,
            'twilio_sid' => '',
            'twilio_token' => '',
            'twilio_phone' => '',
            'require_invite_code' => 0,
            'min_password_length' => 6,
            'allow_registration' => 1,
            'bonus_cadastro' => 0.00 // NOVO CAMPO
        ];
    }
} catch (Exception $e) {
    // Tabela não existe ainda, usar valores padrão
    $config = [
        'sms_enabled' => 0,
        'require_username' => 0,
        'twilio_sid' => '',
        'twilio_token' => '',
        'twilio_phone' => '',
        'require_invite_code' => 0,
        'min_password_length' => 6,
        'allow_registration' => 1,
        'bonus_cadastro' => 0.00 // NOVO CAMPO
    ];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>👤 Configurações de Cadastro</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
            min-height: 100vh;
            color: #e2e8f0;
            line-height: 1.6;
        }

        /* Container Principal */
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 40px;
            padding: 40px 30px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(59, 130, 246, 0.3);
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 10px;
            position: relative;
            z-index: 2;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
            position: relative;
            z-index: 2;
        }

        /* Navegação */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 30px;
            padding: 15px 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }

        .breadcrumb a {
            color: #94a3b8;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .breadcrumb a:hover {
            color: #3b82f6;
        }

        .breadcrumb span {
            color: #64748b;
        }

        /* Alertas */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border-left: 4px solid;
            backdrop-filter: blur(10px);
            animation: slideInDown 0.5s ease-out;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border-color: #22c55e;
            color: #86efac;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border-color: #ef4444;
            color: #fca5a5;
        }

        /* Cards */
        .config-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            margin-bottom: 30px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .config-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .card-header {
            padding: 25px 30px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
        }

        .card-header.bg-primary {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        }

        .card-header.bg-info {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
        }

        .card-header.bg-success {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .card-header.bg-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .card-header h5 {
            font-size: 1.3rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-body {
            padding: 30px;
        }

        /* Form Controls */
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 8px;
            color: #f1f5f9;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            color: #f1f5f9;
            font-size: 1rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
            background: rgba(255, 255, 255, 0.12);
        }

        .form-control::placeholder {
            color: rgba(241, 245, 249, 0.5);
        }

        /* Switch Toggle CORRIGIDO */
        .switch-container {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            min-height: 80px;
        }

        .switch-container:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .switch {
            position: relative;
            width: 60px;
            height: 30px;
            background: #475569;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .switch.active {
            background: #3b82f6;
        }

        .switch-slider {
            position: absolute;
            top: 3px;
            left: 3px;
            width: 24px;
            height: 24px;
            background: white;
            border-radius: 50%;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .switch.active .switch-slider {
            transform: translateX(30px);
        }

        .switch-label {
            flex: 1;
            min-width: 0;
        }

        .switch-label strong {
            display: block;
            font-size: 1.1rem;
            margin-bottom: 5px;
            color: #f1f5f9;
            line-height: 1.3;
        }

        .switch-label small {
            color: #94a3b8;
            font-size: 0.9rem;
            line-height: 1.4;
            display: block;
        }

        /* Destaque para campo de bônus */
        .bonus-field {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            padding: 20px;
            border-radius: 12px;
            position: relative;
        }

        .bonus-field::before {
            content: '💰';
            position: absolute;
            top: -10px;
            left: 20px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 14px;
        }

        .bonus-field .form-control {
            border-color: rgba(245, 158, 11, 0.5);
            background: rgba(245, 158, 11, 0.05);
        }

        .bonus-field .form-control:focus {
            border-color: #f59e0b;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
        }

        /* Preview Section */
        .preview-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .preview-field {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            border-left: 4px solid #3b82f6;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }

        .preview-field:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: translateX(5px);
        }

        .preview-field i {
            font-size: 1.2rem;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge.required {
            background: #dc2626;
            color: white;
        }

        .badge.optional {
            background: #6b7280;
            color: white;
        }

        .status-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .status-item:last-child {
            border-bottom: none;
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .status-success { background: #22c55e; }
        .status-danger { background: #ef4444; }
        .status-warning { background: #f59e0b; }
        .status-secondary { background: #6b7280; }

        /* Botões */
        .btn-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 40px;
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            backdrop-filter: blur(10px);
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(59, 130, 246, 0.4);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #e2e8f0;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }

        .btn-info {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            color: white;
        }

        .btn-info:hover {
            transform: translateY(-2px);
        }

        /* SMS Config Section */
        .sms-config {
            margin-top: 20px;
            padding: 25px;
            background: rgba(14, 165, 233, 0.1);
            border-radius: 12px;
            border: 1px solid rgba(14, 165, 233, 0.3);
        }

        .info-alert {
            padding: 15px;
            background: rgba(59, 130, 246, 0.1);
            border-left: 4px solid #3b82f6;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .info-alert a {
            color: #60a5fa;
            text-decoration: none;
        }

        .info-alert a:hover {
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-container {
                padding: 15px;
            }

            .preview-grid {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .btn-group {
                flex-direction: column;
            }

            .header h1 {
                font-size: 2rem;
            }

            .switch-container {
                flex-direction: column;
                text-align: center;
                gap: 10px;
                min-height: auto;
                padding: 15px;
            }

            .switch-label {
                order: 2;
            }

            .switch {
                order: 1;
            }
        }

        /* Animações */
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .config-card {
            animation: fadeInUp 0.6s ease-out;
        }

        .config-card:nth-child(1) { animation-delay: 0.1s; }
        .config-card:nth-child(2) { animation-delay: 0.2s; }
        .config-card:nth-child(3) { animation-delay: 0.3s; }
        .config-card:nth-child(4) { animation-delay: 0.4s; }
        .config-card:nth-child(5) { animation-delay: 0.5s; }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Header -->
        <div class="header">
            <h1>👤 Configurações de Cadastro</h1>
            <p>Configure campos, validações, integrações e recompensas do formulário de cadastro</p>
        </div>

        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="../../../dashboard/">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <span><i class="fas fa-chevron-right"></i></span>
            <a href="../../">Configurações</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <span>Editar Cadastro</span>
        </div>

        <!-- Mensagens -->
        <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($success_message) ?>
        </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error_message) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="" id="configForm">
            <!-- Configurações Gerais -->
            <div class="config-card">
                <div class="card-header bg-primary">
                    <h5><i class="fas fa-cog"></i> Configurações Gerais</h5>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="switch-container">
                            <div class="switch <?= $config['allow_registration'] ? 'active' : '' ?>" onclick="toggleSwitch(this, 'allow_registration')">
                                <div class="switch-slider"></div>
                            </div>
                            <input type="hidden" name="allow_registration" id="allow_registration" value="<?= $config['allow_registration'] ? '1' : '0' ?>">
                            <div class="switch-label">
                                <strong>Permitir Novos Cadastros</strong>
                                <small>Ative para permitir que novos usuários se registrem na plataforma. Desative temporariamente durante manutenção ou para controlar o acesso.</small>
                            </div>
                        </div>

                        <div class="switch-container">
                            <div class="switch <?= $config['require_invite_code'] ? 'active' : '' ?>" onclick="toggleSwitch(this, 'require_invite_code')">
                                <div class="switch-slider"></div>
                            </div>
                            <input type="hidden" name="require_invite_code" id="require_invite_code" value="<?= $config['require_invite_code'] ? '1' : '0' ?>">
                            <div class="switch-label">
                                <strong>Código de Convite Obrigatório</strong>
                                <small>Quando ativo, todos os novos usuários precisarão de um código de convite válido para se cadastrar. Útil para sistemas de referência ou controle de acesso.</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Tamanho Mínimo da Senha</label>
                            <input type="number" class="form-control" name="min_password_length" id="min_password_length" 
                                   value="<?= $config['min_password_length'] ?>" min="4" max="20">
                            <small style="color: #94a3b8;">Defina quantos caracteres mínimos a senha deve ter. Recomendado: 6-8 caracteres para boa segurança.</small>
                        </div>

                        <div class="switch-container">
                            <div class="switch <?= $config['require_username'] ? 'active' : '' ?>" onclick="toggleSwitch(this, 'require_username')">
                                <div class="switch-slider"></div>
                            </div>
                            <input type="hidden" name="require_username" id="require_username" value="<?= $config['require_username'] ? '1' : '0' ?>">
                            <div class="switch-label">
                                <strong>Campo Nome de Usuário</strong>
                                <small>Adiciona um campo obrigatório de nome de usuário no formulário de cadastro. Os usuários poderão personalizar como querem ser identificados na plataforma.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- NOVO: Configurações de Bônus -->
            <div class="config-card">
                <div class="card-header bg-warning">
                    <h5><i class="fas fa-gift"></i> Recompensas e Bônus</h5>
                </div>
                <div class="card-body">
                    <div class="bonus-field">
                        <div class="form-group">
                            <label class="form-label">💰 Saldo de Boas-vindas (R$)</label>
                            <input type="number" class="form-control" name="bonus_cadastro" id="bonus_cadastro"
                                   value="<?= number_format($config['bonus_cadastro'] ?? 0, 2, '.', '') ?>" 
                                   min="0" step="0.01" placeholder="0.00">
                            <small style="color: #94a3b8;">
                                <strong>Valor em reais que cada usuário receberá automaticamente ao se cadastrar.</strong><br>
                                • Digite 0.00 para desativar o bônus<br>
                                • Digite 10.00 para dar R$ 10,00 de saldo inicial<br>
                                • O valor será creditado instantaneamente após o cadastro
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Configurações SMS -->
            <div class="config-card">
                <div class="card-header bg-info">
                    <h5><i class="fas fa-mobile-alt"></i> Verificação por SMS</h5>
                </div>
                <div class="card-body">
                    <div class="switch-container">
                        <div class="switch <?= $config['sms_enabled'] ? 'active' : '' ?>" onclick="toggleSwitch(this, 'sms_enabled'); toggleSmsConfig();">
                            <div class="switch-slider"></div>
                        </div>
                        <input type="hidden" name="sms_enabled" id="sms_enabled" value="<?= $config['sms_enabled'] ? '1' : '0' ?>">
                        <div class="switch-label">
                            <strong>Ativar Verificação por SMS</strong>
                            <small>Adiciona uma etapa de verificação por SMS no cadastro. Aumenta a segurança garantindo que o telefone seja válido e pertença ao usuário.</small>
                        </div>
                    </div>

                    <div id="sms_config" class="sms-config" style="display: <?= $config['sms_enabled'] ? 'block' : 'none' ?>;">
                        <div class="info-alert">
                            <i class="fas fa-info-circle"></i>
                            <strong>Configuração Twilio:</strong> Você precisa de uma conta Twilio para enviar SMS. 
                            <a href="https://www.twilio.com" target="_blank">Criar conta aqui</a>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Account SID</label>
                                <input type="text" class="form-control" name="twilio_sid" 
                                       value="<?= htmlspecialchars($config['twilio_sid']) ?>" 
                                       placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                                <small style="color: #94a3b8;">Seu Account SID do painel Twilio</small>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Auth Token</label>
                                <input type="password" class="form-control" name="twilio_token" 
                                       value="<?= htmlspecialchars($config['twilio_token']) ?>" 
                                       placeholder="••••••••••••••••••••••••••••••••">
                                <small style="color: #94a3b8;">Seu Auth Token do painel Twilio</small>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Número Twilio</label>
                                <input type="text" class="form-control" name="twilio_phone" 
                                       value="<?= htmlspecialchars($config['twilio_phone']) ?>" 
                                       placeholder="+1234567890">
                                <small style="color: #94a3b8;">Número do Twilio para envio dos SMS</small>
                            </div>
                        </div>

                        <button type="button" class="btn btn-info" onclick="testSms()">
                            <i class="fas fa-mobile-alt"></i>
                            Testar Configuração SMS
                        </button>
                    </div>
                </div>
            </div>

            <!-- Preview do Formulário -->
            <div class="config-card">
                <div class="card-header bg-success">
                    <h5><i class="fas fa-eye"></i> Preview do Formulário</h5>
                </div>
                <div class="card-body">
                    <div class="preview-grid">
                        <div>
                            <h6 style="margin-bottom: 20px; color: #f1f5f9;">Campos que aparecerão no cadastro:</h6>
                            <div id="form_preview">
                                <div class="preview-field">
                                    <i class="fas fa-phone" style="color: #3b82f6;"></i>
                                    <strong>Telefone</strong>
                                    <span class="badge required">Obrigatório</span>
                                </div>
                                
                                <div class="preview-field" id="username_preview" style="display: <?= $config['require_username'] ? 'block' : 'none' ?>;">
                                    <i class="fas fa-user" style="color: #f59e0b;"></i>
                                    <strong>Nome de Usuário</strong>
                                    <span class="badge required">Obrigatório</span>
                                </div>
                                
                                <div class="preview-field">
                                    <i class="fas fa-lock" style="color: #ef4444;"></i>
                                    <strong>Senha</strong>
                                    <span class="badge required">Obrigatório</span>
                                    <small style="color: #94a3b8;">(mín. <span id="min_pass_preview"><?= $config['min_password_length'] ?></span> caracteres)</small>
                                </div>
                                
                                <div class="preview-field">
                                    <i class="fas fa-lock" style="color: #ef4444;"></i>
                                    <strong>Confirmar Senha</strong>
                                    <span class="badge required">Obrigatório</span>
                                </div>
                                
                                <div class="preview-field">
                                    <i class="fas fa-handshake" style="color: #22c55e;"></i>
                                    <strong>Código de Convite</strong>
                                    <span id="invite_preview" class="badge <?= $config['require_invite_code'] ? 'required' : 'optional' ?>">
                                        <?= $config['require_invite_code'] ? 'Obrigatório' : 'Opcional' ?>
                                    </span>
                                </div>
                                
                                <div class="preview-field" id="sms_preview" style="display: <?= $config['sms_enabled'] ? 'block' : 'none' ?>;">
                                    <i class="fas fa-sms" style="color: #8b5cf6;"></i>
                                    <strong>Verificação SMS</strong>
                                    <span class="badge required">Obrigatório</span>
                                </div>

                                <!-- NOVO: Preview do Bônus -->
                                <div class="preview-field" id="bonus_preview" style="display: <?= (isset($config['bonus_cadastro']) && $config['bonus_cadastro'] > 0) ? 'block' : 'none' ?>;">
                                    <i class="fas fa-gift" style="color: #f59e0b;"></i>
                                    <strong>Bônus de Boas-vindas</strong>
                                    <span class="badge" style="background: #f59e0b;">R$ <span id="bonus_value_preview"><?= number_format($config['bonus_cadastro'] ?? 0, 2, ',', '.') ?></span></span>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h6 style="margin-bottom: 20px; color: #f1f5f9;">Status das funcionalidades:</h6>
                            <div class="status-list">
                                <div class="status-item">
                                    <span class="status-indicator status-<?= $config['allow_registration'] ? 'success' : 'danger' ?>"></span>
                                    <span>Cadastros: <?= $config['allow_registration'] ? 'Habilitados' : 'Desabilitados' ?></span>
                                </div>
                                <div class="status-item">
                                    <span class="status-indicator status-<?= $config['sms_enabled'] ? 'success' : 'secondary' ?>"></span>
                                    <span>Verificação SMS: <?= $config['sms_enabled'] ? 'Ativa' : 'Inativa' ?></span>
                                </div>
                                <div class="status-item">
                                    <span class="status-indicator status-<?= $config['require_username'] ? 'success' : 'secondary' ?>"></span>
                                    <span>Campo Username: <?= $config['require_username'] ? 'Ativo' : 'Inativo' ?></span>
                                </div>
                                <div class="status-item">
                                    <span class="status-indicator status-<?= $config['require_invite_code'] ? 'warning' : 'secondary' ?>"></span>
                                    <span>Convite Obrigatório: <?= $config['require_invite_code'] ? 'Sim' : 'Não' ?></span>
                                </div>
                                <div class="status-item">
                                    <span class="status-indicator status-<?= (isset($config['bonus_cadastro']) && $config['bonus_cadastro'] > 0) ? 'warning' : 'secondary' ?>"></span>
                                    <span>Bônus de Cadastro: <?= (isset($config['bonus_cadastro']) && $config['bonus_cadastro'] > 0) ? 'R$ ' . number_format($config['bonus_cadastro'], 2, ',', '.') : 'Desativado' ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botões de Ação -->
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Salvar Configurações
                </button>
                <a href="../../" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Voltar
                </a>
            </div>
        </form>
    </div>

    <script>
        // Toggle Switch Function
        function toggleSwitch(switchElement, inputName) {
            switchElement.classList.toggle('active');
            const input = document.getElementById(inputName);
            input.value = switchElement.classList.contains('active') ? '1' : '0';
            
            // Update previews
            updatePreviews();
        }

        // Toggle SMS Config Visibility
        function toggleSmsConfig() {
            const smsConfig = document.getElementById('sms_config');
            const smsPreview = document.getElementById('sms_preview');
            const smsEnabled = document.getElementById('sms_enabled').value === '1';
            
            smsConfig.style.display = smsEnabled ? 'block' : 'none';
            smsPreview.style.display = smsEnabled ? 'block' : 'none';
        }

        // Update Previews
        function updatePreviews() {
            // Username preview
            const usernamePreview = document.getElementById('username_preview');
            const requireUsername = document.getElementById('require_username').value === '1';
            usernamePreview.style.display = requireUsername ? 'block' : 'none';

            // Invite code preview
            const invitePreview = document.getElementById('invite_preview');
            const requireInvite = document.getElementById('require_invite_code').value === '1';
            invitePreview.className = 'badge ' + (requireInvite ? 'required' : 'optional');
            invitePreview.textContent = requireInvite ? 'Obrigatório' : 'Opcional';

            // SMS preview
            const smsPreview = document.getElementById('sms_preview');
            const smsEnabled = document.getElementById('sms_enabled').value === '1';
            smsPreview.style.display = smsEnabled ? 'block' : 'none';

            // Bonus preview
            updateBonusPreview();
        }

        // Update bonus preview
        function updateBonusPreview() {
            const bonusPreview = document.getElementById('bonus_preview');
            const bonusValuePreview = document.getElementById('bonus_value_preview');
            const bonusInput = document.getElementById('bonus_cadastro');
            const bonusValue = parseFloat(bonusInput.value) || 0;
            
            if (bonusValue > 0) {
                bonusPreview.style.display = 'block';
                bonusValuePreview.textContent = bonusValue.toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            } else {
                bonusPreview.style.display = 'none';
            }
        }

        // Update password length preview
        document.getElementById('min_password_length').addEventListener('input', function() {
            document.getElementById('min_pass_preview').textContent = this.value;
        });

        // Update bonus preview on input
        document.getElementById('bonus_cadastro').addEventListener('input', updateBonusPreview);

        // Test SMS function
        function testSms() {
            alert('Funcionalidade de teste SMS será implementada em breve!');
        }

        // Form validation
        document.getElementById('configForm').addEventListener('submit', function(e) {
            const smsEnabled = document.getElementById('sms_enabled').value === '1';
            
            if (smsEnabled) {
                const sid = document.querySelector('input[name="twilio_sid"]').value;
                const token = document.querySelector('input[name="twilio_token"]').value;
                const phone = document.querySelector('input[name="twilio_phone"]').value;
                
                if (!sid || !token || !phone) {
                    e.preventDefault();
                    alert('Para ativar SMS, preencha todas as credenciais do Twilio.');
                    return false;
                }
            }
        });
    </script>
</body>
</html>