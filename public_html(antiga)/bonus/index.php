<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../');
    exit();
}

require '../bank/db.php';
$pdo = getDBConnection();

// Função para tratar datas de forma segura
function safe_date_format($date_string, $format = 'd/m/Y às H:i') {
    if (empty($date_string) || $date_string === null || $date_string === '0000-00-00 00:00:00') {
        return 'Data não disponível';
    }
    
    $timestamp = strtotime($date_string);
    if ($timestamp === false) {
        return 'Data inválida';
    }
    
    return date($format, $timestamp);
}

// Consulta configurações
$stmt = $pdo->query("SELECT link_suporte, pop_up, anuncio, titulo_site, descricao_site, keywords_site, link_site FROM configurar_textos LIMIT 1");
$result = $stmt->fetch(PDO::FETCH_ASSOC);

$linkSuporte = $result['link_suporte'] ?? '/';
$popUp = $result['pop_up'] ?? '';
$anuncio = $result['anuncio'] ?? '';
$titulo_site = $result['titulo_site'] ?? '';
$descricao_site = $result['descricao_site'] ?? '';
$keywords_site = $result['keywords_site'] ?? '';
$link_site = $result['link_site'] ?? '';

// Consulta imagens e cores
$stmt = $pdo->query("SELECT logo FROM personalizar_imagens LIMIT 1");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$logo = $result['logo'] ?? '3.png';

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

try {
    $conn = getDBConnection();
    
    // Criar tabelas se não existirem
    $conn->exec("CREATE TABLE IF NOT EXISTS bonus_codigos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        codigo VARCHAR(20) UNIQUE NOT NULL,
        valor DECIMAL(10,2) NOT NULL,
        descricao TEXT,
        max_usos INT DEFAULT 1,
        usos_atuais INT DEFAULT 0,
        ativo BOOLEAN DEFAULT TRUE,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        data_expiracao DATETIME NULL
    )");
    
    $conn->exec("CREATE TABLE IF NOT EXISTS bonus_resgatados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        codigo VARCHAR(20) NOT NULL,
        valor DECIMAL(10,2) NOT NULL,
        data_resgate TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Inserir códigos de exemplo se não existirem
    $checkCodigos = $conn->query("SELECT COUNT(*) FROM bonus_codigos")->fetchColumn();
    if ($checkCodigos == 0) {
        $codigosExemplo = [
            ['WELCOME50', 50.00, 'Bônus de boas-vindas', 100],
            ['BONUS100', 100.00, 'Bônus promocional especial', 50],
            ['VIP200', 200.00, 'Bônus VIP exclusivo', 25],
            ['PROMO25', 25.00, 'Promoção limitada', 200],
            ['INICIO10', 10.00, 'Bônus para iniciantes', 500]
        ];
        
        $stmt = $conn->prepare("INSERT INTO bonus_codigos (codigo, valor, descricao, max_usos) VALUES (?, ?, ?, ?)");
        foreach ($codigosExemplo as $codigo) {
            $stmt->execute($codigo);
        }
    }
    
    // Consultar últimos códigos ativados pelo usuário
    $bonus_resgatados = [];
    try {
        $stmt = $conn->prepare("SELECT * FROM bonus_resgatados WHERE user_id = ? ORDER BY data_resgate DESC LIMIT 5");
        $stmt->execute([$_SESSION['user_id']]);
        $bonus_resgatados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $bonus_resgatados = [];
    }
    
    // Consultar saldo atual do usuário
    $saldo_atual = 0;
    try {
        $stmt = $conn->prepare("SELECT saldo FROM usuarios WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $saldo_atual = $stmt->fetchColumn() ?? 0;
    } catch (Exception $e) {
        $saldo_atual = 0;
    }
    
    // Calcular total de bônus resgatados
    $total_bonus = 0;
    try {
        $stmt = $conn->prepare("SELECT COALESCE(SUM(valor), 0) as total_bonus FROM bonus_resgatados WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $total_bonus = $stmt->fetchColumn() ?? 0;
    } catch (Exception $e) {
        $total_bonus = 0;
    }
    
} catch (Exception $e) {
    // Em caso de erro, definir valores padrão
    $bonus_resgatados = [];
    $saldo_atual = 0;
    $total_bonus = 0;
    error_log("Erro no sistema de bônus: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?> - Código de Bônus</title>
    
    <!-- Fonts & Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
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
            padding: 20px 0 80px 0;
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
                radial-gradient(circle at 80% 80%, rgba(139, 92, 246, 0.1) 0%, transparent 50%);
            pointer-events: none;
            z-index: -1;
        }

        .container {
            max-width: 400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header */
        .header-section {
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-color);
            padding: 25px 20px;
            position: relative;
            text-align: center;
            margin-bottom: 30px;
        }

        .header-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--pink-color), var(--purple-color));
        }

        .header-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 8px;
        }

        .header-subtitle {
            color: rgba(255, 255, 255, 0.8);
            font-size: 16px;
            font-weight: 500;
        }

        /* Stats Cards */
        .stats-section {
            margin-bottom: 30px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .stats-card {
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient);
        }

        .stats-card:nth-child(1) { --gradient: linear-gradient(90deg, var(--success-color), #059669); }
        .stats-card:nth-child(2) { --gradient: linear-gradient(90deg, var(--purple-color), var(--pink-color)); }

        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow);
        }

        .stats-card i {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .stats-card:nth-child(1) i { color: var(--success-color); }
        .stats-card:nth-child(2) i { color: var(--purple-color); }

        .stats-card .label {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 8px;
            font-weight: 500;
        }

        .stats-card .value {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-color);
        }

        /* Code Input Section */
        .code-section {
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 30px 25px;
            margin-bottom: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .code-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--pink-color), var(--purple-color));
        }

        .gift-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--pink-color), var(--purple-color));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 35px;
            color: white;
            box-shadow: 0 10px 25px rgba(236, 72, 153, 0.4);
            position: relative;
        }

        .gift-icon::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            animation: shine 3s ease-in-out infinite;
        }

        .code-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 10px;
        }

        .code-description {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 25px;
            line-height: 1.5;
        }

        .code-input-container {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }

        .code-input {
            flex: 1;
            background: var(--dark-background);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 15px 20px;
            font-size: 16px;
            color: var(--text-color);
            text-align: center;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            transition: all 0.3s ease;
        }

        .code-input:focus {
            outline: none;
            border-color: var(--pink-color);
            box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.2);
        }

        .code-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
            text-transform: none;
            letter-spacing: normal;
        }

        .redeem-btn {
            background: linear-gradient(135deg, var(--pink-color), var(--purple-color));
            color: white;
            border: none;
            border-radius: 12px;
            padding: 15px 25px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(236, 72, 153, 0.3);
            flex-shrink: 0;
        }

        .redeem-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(236, 72, 153, 0.4);
        }

        .redeem-btn:active {
            transform: translateY(0);
        }

        /* History Section */
        .history-section {
            margin-bottom: 30px;
        }

        .history-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .history-title i {
            color: var(--success-color);
        }

        .history-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .history-item {
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 20px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .history-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--success-color), #059669);
        }

        .history-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .history-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .history-code {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-color);
            font-family: 'Courier New', monospace;
            background: var(--dark-background);
            padding: 4px 8px;
            border-radius: 6px;
        }

        .history-value {
            font-size: 16px;
            font-weight: 700;
            color: var(--success-color);
        }

        .history-date {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
        }

        .empty-history {
            text-align: center;
            padding: 40px 20px;
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
        }

        .empty-history i {
            font-size: 48px;
            color: rgba(255, 255, 255, 0.3);
            margin-bottom: 15px;
        }

        .empty-history h3 {
            font-size: 18px;
            color: var(--text-color);
            margin-bottom: 8px;
        }

        .empty-history p {
            color: rgba(255, 255, 255, 0.7);
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

        .bottom-nav a:hover,
        .bottom-nav a.active {
            color: var(--pink-color);
            background: rgba(236, 72, 153, 0.15);
        }

        .bottom-nav a i {
            font-size: 20px;
        }

        /* SweetAlert2 Customização */
        .swal2-popup {
            background: var(--primary-color) !important;
            color: var(--text-color) !important;
            border: 1px solid var(--border-color) !important;
            border-radius: var(--border-radius) !important;
        }

        .swal2-title {
            color: var(--text-color) !important;
            font-size: 18px !important;
            font-weight: 700 !important;
        }

        .swal2-html-container {
            color: var(--text-color) !important;
            font-size: 14px !important;
        }

        .swal2-confirm {
            background: linear-gradient(135deg, var(--pink-color), var(--purple-color)) !important;
            color: white !important;
            border: none !important;
            border-radius: 8px !important;
            padding: 10px 20px !important;
            font-weight: 600 !important;
        }

        /* Animations */
        @keyframes shine {
            0%, 100% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            50% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }

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

        .header-section,
        .stats-section,
        .code-section,
        .history-section {
            animation: fadeInUp 0.6s ease-out;
        }

        .stats-card:nth-child(2) { animation-delay: 0.1s; }

        /* Responsive */
        @media (max-width: 480px) {
            .container {
                padding: 0 15px;
            }

            .header-section {
                padding: 20px 15px;
            }

            .code-section {
                padding: 25px 20px;
            }

            .code-input-container {
                flex-direction: column;
            }

            .redeem-btn {
                width: 100%;
            }

            .history-item {
                padding: 15px;
            }

            .history-item-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header-section">
            <h1 class="header-title">🎁 Código de Bônus</h1>
            <p class="header-subtitle">Digite seu código e desbloqueie benefícios exclusivos</p>
        </div>

        <!-- Stats Section -->
        <div class="stats-section">
            <div class="stats-grid">
                <div class="stats-card">
                    <i class="fas fa-wallet"></i>
                    <div class="label">Saldo Atual</div>
                    <div class="value">R$ <?= number_format((float)$saldo_atual, 2, ',', '.') ?></div>
                </div>
                <div class="stats-card">
                    <i class="fas fa-gift"></i>
                    <div class="label">Total Bônus</div>
                    <div class="value">R$ <?= number_format((float)$total_bonus, 2, ',', '.') ?></div>
                </div>
            </div>
        </div>

        <!-- Code Input Section -->
        <div class="code-section">
            <div class="gift-icon">
                <i class="fas fa-gift"></i>
            </div>
            <h2 class="code-title">Resgatar Código</h2>
            <p class="code-description">Insira seu código promocional abaixo para ativar o bônus</p>
            
            <div class="code-input-container">
                <input type="text" class="code-input" placeholder="Digite o código" maxlength="20" id="codeInput">
                <button class="redeem-btn" id="redeemBtn">
                    <i class="fas fa-magic"></i>
                </button>
            </div>
        </div>

        <!-- History Section -->
        <div class="history-section">
            <h3 class="history-title">
                <i class="fas fa-history"></i>
                Últimos Códigos Ativados
            </h3>
            
            <div class="history-list">
                <?php if (!empty($bonus_resgatados)): ?>
                    <?php foreach ($bonus_resgatados as $bonus): ?>
                        <div class="history-item">
                            <div class="history-item-header">
                                <span class="history-code"><?= htmlspecialchars($bonus['codigo']) ?></span>
                                <span class="history-value">+R$ <?= number_format((float)$bonus['valor'], 2, ',', '.') ?></span>
                            </div>
                            <div class="history-date">
                                <?= safe_date_format($bonus['data_resgate']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-history">
                        <i class="fas fa-inbox"></i>
                        <h3>Nenhum código ativado</h3>
                        <p>Você ainda não resgatou nenhum código de bônus</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="../inicio/">
            <i class="fas fa-home"></i>
            Início
        </a>
        <a href="../investimentos/">
            <i class="fas fa-wallet"></i>
            Investimentos
        </a>
        <a href="../team/">
            <i class="fas fa-users"></i>
            Equipe
        </a>
        <a href="../perfil/">
            <i class="fas fa-user"></i>
            Perfil
        </a>
    </nav>

    <script>
        const codeInput = document.getElementById('codeInput');
        const redeemBtn = document.getElementById('redeemBtn');

        // Formatação automática do código
        codeInput.addEventListener('input', function(e) {
            let value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            e.target.value = value;
        });

        // Enter para ativar
        codeInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                redeemCode();
            }
        });

        // Botão de ativar
        redeemBtn.addEventListener('click', redeemCode);

        function redeemCode() {
            const codigo = codeInput.value.trim();
            
            if (!codigo) {
                Swal.fire({
                    icon: 'warning',
                    title: '⚠️ Atenção!',
                    text: 'Por favor, digite um código válido.',
                    confirmButtonText: 'OK',
                    background: 'var(--primary-color)',
                    color: 'var(--text-color)'
                });
                return;
            }

            // Loading
            redeemBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            redeemBtn.disabled = true;

            fetch('verifica.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ codigo: codigo })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '🎉 Bônus Ativado!',
                        html: `
                            <div style="text-align: center; margin: 20px 0;">
                                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, var(--success-color), #059669); border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; font-size: 30px; color: white;">
                                    💰
                                </div>
                                <p style="font-size: 18px; margin-bottom: 10px; color: var(--text-color);">
                                    <strong>R$ ${parseFloat(data.valor).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</strong> adicionados à sua conta!
                                </p>
                                <p style="font-size: 14px; color: rgba(255,255,255,0.8);">
                                    Código: <strong>${codigo}</strong>
                                </p>
                            </div>
                        `,
                        confirmButtonText: '✨ Continuar',
                        background: 'var(--primary-color)',
                        color: 'var(--text-color)'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '❌ Código Inválido',
                        text: data.message,
                        confirmButtonText: 'Tentar Novamente',
                        background: 'var(--primary-color)',
                        color: 'var(--text-color)'
                    });
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire({
                    icon: 'error',
                    title: '❌ Erro de Conexão',
                    text: 'Não foi possível processar o código. Tente novamente.',
                    confirmButtonText: 'OK',
                    background: 'var(--primary-color)',
                    color: 'var(--text-color)'
                });
            })
            .finally(() => {
                redeemBtn.innerHTML = '<i class="fas fa-magic"></i>';
                redeemBtn.disabled = false;
            });
        }

        // Ripple effect
        redeemBtn.addEventListener('click', function(e) {
            const ripple = document.createElement('div');
            ripple.style.cssText = `
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.3);
                transform: scale(0);
                animation: ripple 0.6s ease-out;
                pointer-events: none;
            `;
            
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            
            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });

        // CSS para animação do ripple
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);

        // Auto-focus no input quando a página carregar
        window.addEventListener('load', function() {
            codeInput.focus();
        });

        // Feedback visual no input
        codeInput.addEventListener('focus', function() {
            this.style.transform = 'scale(1.02)';
        });

        codeInput.addEventListener('blur', function() {
            this.style.transform = 'scale(1)';
        });
    </script>
</body>
</html>