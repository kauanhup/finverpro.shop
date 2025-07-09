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
$stmt = $pdo->query("SELECT tela_pix FROM personalizar_imagens LIMIT 1");
$result = $stmt->fetch(PDO::FETCH_ASSOC);

// Define valores padrão caso não encontre no banco
$tela_pix = $result['tela_pix'] ?? '1.jpg';

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
    <title><?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?> - Depósito PIX</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="title" content="<?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="author" content="<?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="description" content="<?= htmlspecialchars($descricao_site, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="keywords" content="<?= htmlspecialchars($keywords_site, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="canonical" href="<?= htmlspecialchars($link_site, ENT_QUOTES, 'UTF-8'); ?>">
    
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
                radial-gradient(circle at 80% 80%, rgba(139, 92, 246, 0.1) 0%, transparent 50%);
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
            background: linear-gradient(90deg, var(--secondary-color), var(--info-color));
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
            margin-bottom: 20px;
        }

        /* Banner Image */
        .banner-section {
            padding: 20px;
            text-align: center;
        }

        .banner-image {
            width: 100%;
            max-width: 350px;
            height: 180px;
            object-fit: cover;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            border: 2px solid var(--border-color);
        }

        /* Value Selection */
        .value-section {
            padding: 30px 20px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-color);
            text-align: center;
            margin-bottom: 25px;
        }

        /* Custom Input */
        .custom-input-container {
            margin-bottom: 25px;
        }

        .custom-value-input {
            width: 100%;
            padding: 18px 20px;
            font-size: 18px;
            font-weight: 600;
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            color: var(--text-color);
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
        }

        .custom-value-input:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 4px rgba(51, 93, 103, 0.1);
            transform: translateY(-2px);
        }

        .custom-value-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        /* PIX Button */
        .pix-button {
            width: 100%;
            padding: 20px;
            font-size: 18px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--success-color), #059669);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .pix-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s ease;
        }

        .pix-button:hover {
            transform: translateY(-3px);
            box-shadow: 
                0 25px 50px -12px rgba(0, 0, 0, 0.4),
                0 0 0 4px rgba(16, 185, 129, 0.2);
        }

        .pix-button:hover::before {
            left: 100%;
        }

        .pix-button:active {
            transform: translateY(0);
        }

        .pix-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Loading State */
        .pix-button.loading {
            pointer-events: none;
        }

        .pix-button.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin-left: -10px;
            margin-top: -10px;
            border: 2px solid transparent;
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .pix-button.loading span {
            opacity: 0;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
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

        .header-section,
        .banner-section,
        .value-section {
            animation: fadeInUp 0.6s ease-out;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .header-title {
                font-size: 22px;
            }

            .banner-image {
                height: 160px;
            }

            .custom-value-input {
                padding: 16px;
                font-size: 16px;
            }

            .pix-button {
                padding: 18px;
                font-size: 16px;
            }
        }

        /* PIX Icon Animation */
        @keyframes pixPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .pix-button i {
            animation: pixPulse 2s ease-in-out infinite;
            margin-right: 10px;
        }

        /* SweetAlert Custom Styles */
        .custom-swal-popup {
            background: var(--blur-bg) !important;
            backdrop-filter: blur(20px) !important;
            border: 1px solid var(--border-color) !important;
            border-radius: var(--border-radius) !important;
            color: var(--text-color) !important;
        }
        
        .swal-button {
            background: linear-gradient(135deg, var(--secondary-color), var(--info-color)) !important;
            border: none !important;
            border-radius: var(--border-radius-sm) !important;
            font-weight: 600 !important;
            padding: 12px 24px !important;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <div class="header-section">
        <h1 class="header-title">
            <i class="fas fa-qrcode" style="color: var(--success-color); margin-right: 10px;"></i>
            Depósito PIX
        </h1>
        <p class="header-subtitle">Escolha um valor e realize seu depósito instantâneo</p>
    </div>

    <!-- Banner -->
    <div class="banner-section">
        <img src="../../assets/images/banners/<?= htmlspecialchars($tela_pix) ?>" alt="PIX Banner" class="banner-image">
    </div>

    <!-- Value Selection -->
    <div class="value-section">
        <h2 class="section-title">💰 Selecione o Valor</h2>
        
        <!-- Custom Input -->
        <div class="custom-input-container">
            <input 
                type="number" 
                min="30" 
                max="10000" 
                id="custom-value" 
                class="custom-value-input"
                placeholder="Digite o valor desejado (R$)"
            >
        </div>

        <!-- PIX Button -->
        <button type="submit" class="pix-button" id="pix-button">
            <span>
                <i class="fas fa-qrcode"></i>
                Gerar PIX Instantâneo
            </span>
        </button>
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
        // Elements
        const customValueInput = document.getElementById('custom-value');
        const pixButton = document.getElementById('pix-button');

        // 🔥 PIX Button click - VERSÃO COM VERIFICAÇÃO DE PIX PENDENTE
        pixButton.addEventListener('click', () => {
            const valor = parseFloat(customValueInput.value);

            if (isNaN(valor) || valor < 30) {
                Swal.fire({
                    title: '⚠️ Valor Inválido',
                    text: 'O valor mínimo para depósito é R$ 30,00',
                    icon: 'warning',
                    confirmButtonText: 'Entendido',
                    customClass: {
                        popup: 'custom-swal-popup',
                        confirmButton: 'swal-button'
                    }
                });
                return;
            }

            // Add loading state
            pixButton.classList.add('loading');
            
            // 🔥 NOVA VERIFICAÇÃO: Verificar se tem PIX pendente ANTES de gerar novo
            fetch('./verificar-pendente.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    user_id: <?= $user_id ?>
                })
            })
            .then(response => response.json())
            .then(data => {
                pixButton.classList.remove('loading');
                
                if (data.error) {
                    throw new Error(data.error);
                }
                
                if (data.has_pending) {
                    // TEM PIX PENDENTE - mostrar modal com opções
                    showPendingPaymentModal(data, valor);
                } else {
                    // NÃO TEM PIX PENDENTE - pode gerar novo
                    generateNewPix(valor);
                }
            })
            .catch(error => {
                pixButton.classList.remove('loading');
                console.error('Erro ao verificar PIX pendente:', error);
                
                // Em caso de erro, tentar gerar novo PIX mesmo assim
                generateNewPix(valor);
            });
        });

        // 🔥 NOVA FUNÇÃO: Modal para PIX pendente
        function showPendingPaymentModal(pendingData, newValue) {
            Swal.fire({
                title: '⚠️ Você tem um PIX pendente!',
                html: `
                    <div style="text-align: left; padding: 10px;">
                        <p><strong>PIX atual:</strong> R$ ${pendingData.valor}</p>
                        <p><strong>Tempo restante:</strong> ${pendingData.minutes_left} minutos</p>
                        <p><strong>Criado em:</strong> ${new Date(pendingData.created_at).toLocaleString('pt-BR')}</p>
                        <br>
                        <p style="color: #F59E0B;"><strong>⚠️ Você só pode ter um PIX ativo por vez.</strong></p>
                        <br>
                        <p>O que deseja fazer?</p>
                    </div>
                `,
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonText: '📱 Continuar PIX Atual',
                denyButtonText: '🗑️ Cancelar e Criar Novo',
                cancelButtonText: '❌ Voltar',
                confirmButtonColor: '#10B981',
                denyButtonColor: '#EF4444',
                cancelButtonColor: '#6B7280',
                customClass: {
                    popup: 'custom-swal-popup'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // IR PARA O PIX EXISTENTE
                    Swal.fire({
                        title: '📱 Redirecionando...',
                        text: 'Abrindo seu PIX pendente',
                        icon: 'info',
                        timer: 1500,
                        showConfirmButton: false,
                        customClass: {
                            popup: 'custom-swal-popup'
                        }
                    }).then(() => {
                        // Redirecionar apenas com external_reference
                        // A chave PIX será gerada novamente na página QR Code se necessário
                        window.location.href = `./qr-code.php?externalReference=${encodeURIComponent(pendingData.external_reference)}`;
                    });
                    
                } else if (result.isDenied) {
                    // CANCELAR PIX ATUAL E CRIAR NOVO
                    cancelCurrentPixAndCreateNew(pendingData.external_reference, newValue);
                }
                // Se cancelar (voltar), não faz nada
            });
        }

        // 🔥 NOVA FUNÇÃO: Cancelar PIX atual e criar novo
        function cancelCurrentPixAndCreateNew(externalReference, newValue) {
            Swal.fire({
                title: '🗑️ Cancelando PIX atual...',
                text: 'Aguarde...',
                allowOutsideClick: false,
                showConfirmButton: false,
                customClass: {
                    popup: 'custom-swal-popup'
                },
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('./cancelar-pix.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    external_reference: externalReference
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // PIX cancelado com sucesso, agora gerar novo
                    generateNewPix(newValue);
                } else {
                    Swal.fire({
                        title: '❌ Erro',
                        text: data.message || 'Erro ao cancelar PIX atual',
                        icon: 'error',
                        confirmButtonText: 'OK',
                        customClass: {
                            popup: 'custom-swal-popup',
                            confirmButton: 'swal-button'
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Erro ao cancelar PIX:', error);
                Swal.fire({
                    title: '❌ Erro de Conexão',
                    text: 'Não foi possível cancelar o PIX atual',
                    icon: 'error',
                    confirmButtonText: 'OK',
                    customClass: {
                        popup: 'custom-swal-popup',
                        confirmButton: 'swal-button'
                    }
                });
            });
        }

        // 🔥 FUNÇÃO SEPARADA: Gerar novo PIX (código original)
        function generateNewPix(valor) {
            // Add loading state
            pixButton.classList.add('loading');
            
            // API Call - CÓDIGO ORIGINAL
            fetch('../../gate/api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    valor: valor
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                pixButton.classList.remove('loading');
                
                if (data.status === 'success') {
                    // Success feedback
                    Swal.fire({
                        title: '✅ PIX Gerado!',
                        text: 'Redirecionando para pagamento...',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false,
                        customClass: {
                            popup: 'custom-swal-popup'
                        }
                    }).then(() => {
                        // Redirect to QR code page
                        const qrCodeURL = `./qr-code.php?copiarTexto=${encodeURIComponent(data.copiarTexto)}&externalReference=${encodeURIComponent(data.externalReference)}`;
                        window.location.href = qrCodeURL;
                    });
                } else {
                    Swal.fire({
                        title: '❌ Erro',
                        text: data.message || 'Houve um problema ao gerar o PIX',
                        icon: 'error',
                        confirmButtonText: 'Tentar Novamente',
                        customClass: {
                            popup: 'custom-swal-popup',
                            confirmButton: 'swal-button'
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Erro ao enviar o formulário:', error);
                pixButton.classList.remove('loading');
                
                Swal.fire({
                    title: '🔴 Erro de Conexão',
                    text: 'Verifique sua conexão e tente novamente',
                    icon: 'error',
                    confirmButtonText: 'Tentar Novamente',
                    customClass: {
                        popup: 'custom-swal-popup',
                        confirmButton: 'swal-button'
                    }
                });
            });
        }

        // Add ripple animation styles
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
    </script>
</body>
</html>