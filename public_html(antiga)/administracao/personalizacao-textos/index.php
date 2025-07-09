<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../');
    exit();
}

require '../bank/db.php';

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    die("Erro de conexão: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Verificar permissão de administrador
try {
    $sql = "SELECT cargo FROM usuarios WHERE id = :user_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['cargo'] !== 'admin') {
        header('Location: ../');
        exit();
    }
} catch (Exception $e) {
    die("Erro ao verificar permissões: " . $e->getMessage());
}

// Inicializar variáveis
$config = [];

// Carregar dados do banco
try {
    $stmt = $conn->query("SELECT * FROM configurar_textos LIMIT 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$config) {
        // Criar registro padrão se não existir
        $insertDefault = "INSERT INTO configurar_textos (anuncio, pop_up, link_suporte, popup_titulo) VALUES ('Bem-vindo!', 'Cadastre-se e ganhe bônus!', 'https://wa.me/5511999999999', 'Notificação')";
        $conn->exec($insertDefault);
        
        $stmt = $conn->query("SELECT * FROM configurar_textos LIMIT 1");
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Erro ao buscar dados: " . $e->getMessage());
    $message = "Erro ao carregar configurações.";
    $messageType = 'error';
}

// Processar upload de imagem
if (isset($_FILES['popup_imagem']) && $_FILES['popup_imagem']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../assets/images/popup/';
    
    // Criar diretório se não existir
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $fileType = $_FILES['popup_imagem']['type'];
    
    if (in_array($fileType, $allowedTypes)) {
        $fileExtension = pathinfo($_FILES['popup_imagem']['name'], PATHINFO_EXTENSION);
        $fileName = 'popup_' . time() . '.' . $fileExtension;
        $uploadPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['popup_imagem']['tmp_name'], $uploadPath)) {
            $_POST['popup_imagem'] = $fileName;
        } else {
            $message = "Erro ao fazer upload da imagem.";
            $messageType = 'error';
        }
    } else {
        $message = "Tipo de arquivo não permitido. Use: JPG, PNG, GIF ou WEBP.";
        $messageType = 'error';
    }
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$message) {
    try {
        // Construir campos dinâmicos para UPDATE
        $updateFields = [];
        $updateValues = [];
        
        // Campos básicos
        $basicFields = ['anuncio', 'pop_up', 'link_suporte', 'popup_titulo', 'popup_imagem', 'popup_botao_texto', 'popup_delay'];
        foreach ($basicFields as $field) {
            if (isset($_POST[$field])) {
                $updateFields[] = "$field = ?";
                $updateValues[] = trim($_POST[$field]);
            }
        }
        
        // Popup ativo
        $updateFields[] = "popup_ativo = ?";
        $updateValues[] = isset($_POST['popup_ativo']) ? 1 : 0;
        
        // Ticker mensagens (1 a 20)
        for ($i = 1; $i <= 20; $i++) {
            if (isset($_POST["ticker_msg_$i"])) {
                $updateFields[] = "ticker_msg_$i = ?";
                $updateValues[] = trim($_POST["ticker_msg_$i"]);
            }
            
            if (isset($_POST["ticker_icon_$i"])) {
                $updateFields[] = "ticker_icon_$i = ?";
                $updateValues[] = trim($_POST["ticker_icon_$i"]);
            }
            
            // Ticker ativo
            $updateFields[] = "ticker_ativo_$i = ?";
            $updateValues[] = isset($_POST["ticker_ativo_$i"]) ? 1 : 0;
        }
        
        // Executar UPDATE
        $sql = "UPDATE configurar_textos SET " . implode(', ', $updateFields) . " WHERE id = 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute($updateValues);
        
        $message = "Configurações salvas com sucesso!";
        $messageType = 'success';
        
        // Recarregar dados
        $stmt = $conn->query("SELECT * FROM configurar_textos LIMIT 1");
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $message = "Erro ao salvar: " . $e->getMessage();
        $messageType = 'error';
        error_log("Erro ao salvar configurações - Usuário {$user_id}: " . $e->getMessage());
    }
}
?>

<!doctype html>
<html lang="pt-BR">
<head>
    <title>Configuração de Textos - Dashboard</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <link rel="icon" href="../assets/images/favicon.svg" type="image/x-icon" />
    <link rel="stylesheet" href="../assets/fonts/inter/inter.css" id="main-font-link" />
    <link rel="stylesheet" href="../assets/fonts/tabler-icons.min.css" />
    <link rel="stylesheet" href="../assets/fonts/feather.css" />
    <link rel="stylesheet" href="../assets/fonts/fontawesome.css" />
    <link rel="stylesheet" href="../assets/css/style.css" id="main-style-link" />
    <link rel="stylesheet" href="../assets/css/style-preset.css" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-layout="vertical" data-pc-direction="ltr" data-pc-theme="dark">
    
    <style>
        .ticker-preview {
            background: linear-gradient(135deg, #10B981, #059669);
            padding: 8px 0;
            overflow: hidden;
            position: relative;
            margin: 15px 0;
            border-radius: 8px;
        }
        .ticker-content-preview {
            display: flex;
            animation: scroll-left 15s linear infinite;
            white-space: nowrap;
        }
        .ticker-item-preview {
            padding: 0 30px;
            font-size: 12px;
            font-weight: 600;
            color: white;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        @keyframes scroll-left {
            0% { transform: translateX(100%); }
            100% { transform: translateX(-100%); }
        }
        .popup-preview {
            border: 2px solid #4680ff;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            background: #f8f9fa;
            margin: 15px 0;
        }
        .popup-preview img {
            max-width: 80px;
            max-height: 80px;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .ticker-item-config {
            background: #2a2d3a;
            border: 1px solid #3d4465;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .icon-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(60px, 1fr));
            gap: 8px;
            margin: 10px 0;
        }
        .icon-option {
            padding: 8px;
            text-align: center;
            border: 1px solid #3d4465;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            color: #fff;
        }
        .icon-option:hover, .icon-option.selected {
            background: #4680ff;
            border-color: #4680ff;
        }
        .char-counter {
            font-size: 11px;
            color: #6c757d;
            text-align: right;
            margin-top: 5px;
        }
        .section-header {
            background: linear-gradient(135deg, #4680ff, #6366f1);
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin: 20px 0 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>

    <script>
        <?php if ($message): ?>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: '<?php echo $messageType; ?>',
                title: '<?php echo $messageType === 'success' ? 'Sucesso!' : 'Erro!'; ?>',
                text: '<?php echo addslashes($message); ?>',
                confirmButtonText: 'OK',
                confirmButtonColor: '<?php echo $messageType === 'success' ? '#4CAF50' : '#f44336'; ?>',
                timer: <?php echo $messageType === 'success' ? '3000' : '0'; ?>,
                timerProgressBar: <?php echo $messageType === 'success' ? 'true' : 'false'; ?>
            });
        });
        <?php endif; ?>

        // Função para atualizar preview do ticker
        function updateTickerPreview() {
            const preview = document.getElementById('ticker-preview-content');
            let activeMessages = [];
            
            for (let i = 1; i <= 20; i++) {
                const checkbox = document.getElementById(`ticker_ativo_${i}`);
                const message = document.getElementById(`ticker_msg_${i}`);
                const icon = document.getElementById(`ticker_icon_${i}`);
                
                if (checkbox && checkbox.checked && message && message.value.trim()) {
                    activeMessages.push({
                        icon: icon.value || 'fas fa-star',
                        message: message.value.trim()
                    });
                }
            }
            
            if (activeMessages.length > 0) {
                preview.innerHTML = activeMessages.map(msg => 
                    `<div class="ticker-item-preview">
                        <i class="${msg.icon}"></i>
                        ${msg.message}
                    </div>`
                ).join('');
            } else {
                preview.innerHTML = '<div class="ticker-item-preview">Nenhuma mensagem ativa</div>';
            }
        }

        // Função para atualizar preview do popup
        function updatePopupPreview() {
            const titulo = document.getElementById('popup_titulo').value || 'Título';
            const botao = document.getElementById('popup_botao_texto').value || 'Fechar';
            const imagem = document.getElementById('popup_imagem_preview');
            
            document.getElementById('popup-preview-title').textContent = titulo;
            document.getElementById('popup-preview-button').textContent = botao;
        }

        // Seletor de ícones
        function selectIcon(element, inputId) {
            // Remove seleção de outros
            element.parentElement.querySelectorAll('.icon-option').forEach(opt => 
                opt.classList.remove('selected')
            );
            
            // Adiciona seleção ao clicado
            element.classList.add('selected');
            
            // Atualiza input
            document.getElementById(inputId).value = element.dataset.icon;
            
            updateTickerPreview();
        }

        // Preview de upload de imagem
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('popup_imagem_preview').src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Contador de caracteres
        function setupCharCounter(inputId, counterId, maxLength) {
            const input = document.getElementById(inputId);
            const counter = document.getElementById(counterId);
            
            function updateCounter() {
                const currentLength = input.value.length;
                counter.textContent = `${currentLength}/${maxLength}`;
                
                if (currentLength > maxLength * 0.9) {
                    counter.style.color = '#f44336';
                } else if (currentLength > maxLength * 0.7) {
                    counter.style.color = '#ff9800';
                } else {
                    counter.style.color = '#6c757d';
                }
            }
            
            input.addEventListener('input', updateCounter);
            updateCounter();
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Configurar contadores
            setupCharCounter('popup_titulo', 'titulo-counter', 100);
            setupCharCounter('popup_botao_texto', 'botao-counter', 50);
            
            for (let i = 1; i <= 20; i++) {
                setupCharCounter(`ticker_msg_${i}`, `msg-counter-${i}`, 100);
            }
            
            // Atualizar previews iniciais
            updateTickerPreview();
            updatePopupPreview();
            
            // Event listeners para updates automáticos
            document.addEventListener('input', function(e) {
                if (e.target.id.startsWith('ticker_msg_') || e.target.id.startsWith('ticker_icon_')) {
                    updateTickerPreview();
                }
                if (e.target.id.startsWith('popup_')) {
                    updatePopupPreview();
                }
            });
            
            document.addEventListener('change', function(e) {
                if (e.target.id.startsWith('ticker_ativo_')) {
                    updateTickerPreview();
                }
            });
        });
    </script>

    <!-- SIDEBAR COMPLETA -->
    <nav class="pc-sidebar">
        <div class="navbar-wrapper">
            <div class="m-header">
                <a href="../dashboard/" class="b-brand text-primary">
                    <img src="../assets/images/logo-dark.svg" class="img-fluid logo-lg" alt="logo" />
                </a>
            </div>
            <div class="navbar-content">
                <div class="card pc-user-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <img src="../assets/images/user/avatar-2.jpg" alt="user-image" class="user-avtar wid-45 rounded-circle" />
                            </div>
                            <div class="flex-grow-1 ms-3 me-2">
                                <h6 class="mb-0">Administrador</h6>
                                <small>Controle Geral</small>
                            </div>
                            <a class="btn btn-icon btn-link-secondary avtar" data-bs-toggle="collapse" href="#pc_sidebar_userlink">
                                <svg class="pc-icon">
                                    <use xlink:href="#custom-sort-outline"></use>
                                </svg>
                            </a>
                        </div>
                        <div class="collapse pc-user-links" id="pc_sidebar_userlink">
                            <div class="pt-3">
                                <a href="../logout.php">
                                    <i class="ti ti-power"></i>
                                    <span>Sair</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <ul class="pc-navbar">
                    <li class="pc-item pc-caption">
                        <label>Navegação</label>
                    </li>
                    <li class="pc-item">
                        <a href="../dashboard/" class="pc-link">
                            <span class="pc-micon">
                                <svg class="pc-icon">
                                    <use xlink:href="#custom-status-up"></use>
                                </svg>
                            </span>
                            <span class="pc-mtext">Dashboard</span>
                        </a>
                    </li>
                    <li class="pc-item">
                        <a href="../usuarios/" class="pc-link">
                            <span class="pc-micon">
                                <i class="ti ti-users"></i>
                            </span>
                            <span class="pc-mtext">Usuários</span>
                        </a>
                    </li>
                    <li class="pc-item">
                        <a href="../afiliados/" class="pc-link">
                            <span class="pc-micon">
                                <i class="ti ti-users"></i>
                            </span>
                            <span class="pc-mtext">Afiliados</span>
                        </a>
                    </li>
                    <li class="pc-item pc-caption">
                        <label>Financeiro</label>
                    </li>
                    <li class="pc-item">
                        <a href="../entradas-geral/" class="pc-link">
                            <span class="pc-micon">
                                <i class="ti ti-arrow-bar-up"></i>
                            </span>
                            <span class="pc-mtext">Entradas Geral</span>
                        </a>
                    </li>
                    <li class="pc-item">
                        <a href="../saidas-usuarios/" class="pc-link">
                            <span class="pc-micon">
                                <i class="ti ti-arrow-bar-to-down"></i>
                            </span>
                            <span class="pc-mtext">Saídas de Usuários</span>
                        </a>
                    </li>
                    <li class="pc-item">
                        <a href="../saidas-afiliados/" class="pc-link">
                            <span class="pc-micon">
                                <i class="ti ti-arrow-bar-to-down"></i>
                            </span>
                            <span class="pc-mtext">Saídas de Afiliados</span>
                        </a>
                    </li>
                    <li class="pc-item pc-caption">
                        <label>Plataforma</label>
                    </li>
                    <li class="pc-item">
                        <a href="../transacao-investidores/" class="pc-link">
                            <span class="pc-micon">
                                <i class="ti ti-chart-bar"></i>
                            </span>
                            <span class="pc-mtext">Investidores</span>
                        </a>
                    </li>
                    <li class="pc-item">
                        <a href="../configuracao-produtos/" class="pc-link">
                            <span class="pc-micon">
                                <i class="ti ti-shopping-cart"></i>
                            </span>
                            <span class="pc-mtext">Produtos</span>
                        </a>
                    </li>
                    <li class="pc-item">
                        <a href="../codigos/" class="pc-link">
                            <span class="pc-micon">
                                <i class="ti ti-gift"></i>
                            </span>
                            <span class="pc-mtext">Códigos</span>
                        </a>
                    </li>
                    <li class="pc-item pc-caption">
                        <label>Configurações</label>
                    </li>
                    <li class="pc-item">
                        <a href="../configuracao-geral/" class="pc-link">
                            <span class="pc-micon">
                                <i class="ti ti-settings"></i>
                            </span>
                            <span class="pc-mtext">Configurações Geral</span>
                        </a>
                    </li>
                    <li class="pc-item">
                        <a href="../configuracao-pagamentos/" class="pc-link">
                            <span class="pc-micon">
                                <i class="ti ti-building-bank"></i>
                            </span>
                            <span class="pc-mtext">Config de Pagamento</span>
                        </a>
                    </li>
                    <li class="pc-item">
                        <a href="../configuracao-webhook/" class="pc-link">
                            <span class="pc-micon">
                                <i class="ti ti-news"></i>
                            </span>
                            <span class="pc-mtext">Config de WebHook</span>
                        </a>
                    </li>
                    <li class="pc-item">
                        <a href="../configuracao-seo/" class="pc-link">
                            <span class="pc-micon">
                                <i class="ti ti-world"></i>
                            </span>
                            <span class="pc-mtext">Configuração de SEO</span>
                        </a>
                    </li>
                    <li class="pc-item pc-caption">
                        <label>Personalização</label>
                    </li>
                    <li class="pc-item">
                        <a href="../personalizacao-cores/" class="pc-link">
                            <span class="pc-micon">
                                <i class="ti ti-palette"></i>
                            </span>
                            <span class="pc-mtext">Personalização de Cores</span>
                        </a>
                    </li>
                    <li class="pc-item">
                        <a href="./" class="pc-link active">
                            <span class="pc-micon">
                                <i class="ti ti-file-text"></i>
                            </span>
                            <span class="pc-mtext">Personalização de Textos</span>
                        </a>
                    </li>
                    <li class="pc-item">
                        <a href="../personalizar-banners/" class="pc-link">
                            <span class="pc-micon">
                                <i class="ti ti-photo"></i>
                            </span>
                            <span class="pc-mtext">Person de Imagens</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- HEADER COMPLETO -->
    <header class="pc-header">
        <div class="header-wrapper">
            <div class="me-auto pc-mob-drp">
                <ul class="list-unstyled">
                    <li class="pc-h-item pc-sidebar-collapse">
                        <a href="#" class="pc-head-link ms-0" id="sidebar-hide">
                            <i class="ti ti-menu-2"></i>
                        </a>
                    </li>
                    <li class="pc-h-item pc-sidebar-popup">
                        <a href="#" class="pc-head-link ms-0" id="mobile-collapse">
                            <i class="ti ti-menu-2"></i>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="ms-auto">
                <ul class="list-unstyled">
                    <li class="dropdown pc-h-item">
                        <a class="pc-head-link dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#" role="button">
                            <svg class="pc-icon">
                                <use xlink:href="#custom-sun-1"></use>
                            </svg>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end pc-h-dropdown">
                            <a href="#!" class="dropdown-item" onclick="layout_change('dark')">
                                <svg class="pc-icon">
                                    <use xlink:href="#custom-moon"></use>
                                </svg>
                                <span>Escuro</span>
                            </a>
                            <a href="#!" class="dropdown-item" onclick="layout_change('light')">
                                <svg class="pc-icon">
                                    <use xlink:href="#custom-sun-1"></use>
                                </svg>
                                <span>Claro</span>
                            </a>
                            <a href="#!" class="dropdown-item" onclick="layout_change_default()">
                                <svg class="pc-icon">
                                    <use xlink:href="#custom-setting-2"></use>
                                </svg>
                                <span>Padrão</span>
                            </a>
                        </div>
                    </li>
                    <li class="dropdown pc-h-item header-user-profile">
                        <a class="pc-head-link dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false">
                            <img src="../assets/images/user/avatar-2.jpg" alt="user-image" class="user-avtar" />
                        </a>
                        <div class="dropdown-menu dropdown-menu-end pc-h-dropdown">
                            <div class="dropdown-header d-flex align-items-center justify-content-between">
                                <h5 class="m-0">Perfil</h5>
                            </div>
                            <div class="dropdown-body">
                                <div class="profile-notification-scroll position-relative" style="max-height: calc(100vh - 225px)">
                                    <div class="d-flex mb-1">
                                        <div class="flex-shrink-0">
                                            <img src="../assets/images/user/avatar-2.jpg" alt="user-image" class="user-avtar wid-35" />
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="mb-1">Administrador 🖖</h6>
                                            <span>admin@admin.com</span>
                                        </div>
                                    </div>
                                    <hr class="border-secondary border-opacity-50" />
                                    <div class="card">
                                        <div class="card-body py-3">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <h5 class="mb-0 d-inline-flex align-items-center">
                                                    <svg class="pc-icon text-muted me-2">
                                                        <use xlink:href="#custom-notification-outline"></use>
                                                    </svg>
                                                    Notificações
                                                </h5>
                                                <div class="form-check form-switch form-check-reverse m-0">
                                                    <input class="form-check-input f-18" type="checkbox" role="switch" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-span">Gerenciar</p>
                                    <a href="../configuracao-geral/" class="dropdown-item">
                                        <span>
                                            <svg class="pc-icon text-muted me-2">
                                                <use xlink:href="#custom-setting-outline"></use>
                                            </svg>
                                            <span>Configurações</span>
                                        </span>
                                    </a>
                                    <a href="../logout.php" class="dropdown-item">
                                        <span>
                                            <svg class="pc-icon text-muted me-2">
                                                <use xlink:href="#custom-logout-1-outline"></use>
                                            </svg>
                                            <span>Sair</span>
                                        </span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <!-- CONTEÚDO PRINCIPAL -->
    <section class="pc-container">
        <div class="pc-content">
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-md-12">
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../dashboard/">Dashboard</a></li>
                                <li class="breadcrumb-item" aria-current="page">Configuração de Textos</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">Configuração de Textos e Marketing</h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <div class="row">
                    
                    <!-- SEÇÃO 1: CONFIGURAÇÕES BÁSICAS -->
                    <div class="col-md-12">
                        <div class="card">
                            <div class="section-header">
                                <i class="ti ti-settings"></i>
                                <h5 class="mb-0">Configurações Básicas</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Anúncio Header</label>
                                        <input type="text" class="form-control" name="anuncio" 
                                               value="<?= htmlspecialchars($config['anuncio'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                                               placeholder="Texto do cabeçalho">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Link de Suporte</label>
                                        <input type="url" class="form-control" name="link_suporte" 
                                               value="<?= htmlspecialchars($config['link_suporte'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                                               placeholder="https://wa.me/5511999999999">
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Texto do Pop-up Principal</label>
                                        <textarea class="form-control" name="pop_up" rows="3" 
                                                  placeholder="Mensagem principal do pop-up"><?= htmlspecialchars($config['pop_up'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SEÇÃO 2: CONFIGURAÇÃO DO POP-UP -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="section-header">
                                <i class="ti ti-bell"></i>
                                <h5 class="mb-0">Configuração do Pop-up</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Título do Pop-up</label>
                                    <input type="text" class="form-control" id="popup_titulo" name="popup_titulo" 
                                           value="<?= htmlspecialchars($config['popup_titulo'] ?? 'Notificação', ENT_QUOTES, 'UTF-8'); ?>" 
                                           maxlength="100">
                                    <div id="titulo-counter" class="char-counter"></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Texto do Botão</label>
                                    <input type="text" class="form-control" id="popup_botao_texto" name="popup_botao_texto" 
                                           value="<?= htmlspecialchars($config['popup_botao_texto'] ?? 'Fechar', ENT_QUOTES, 'UTF-8'); ?>" 
                                           maxlength="50">
                                    <div id="botao-counter" class="char-counter"></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Delay (milissegundos)</label>
                                    <input type="number" class="form-control" name="popup_delay" 
                                           value="<?= $config['popup_delay'] ?? 3000; ?>" min="1000" max="30000">
                                    <small class="text-muted">Tempo até aparecer (1000 = 1 segundo)</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Imagem do Pop-up</label>
                                    <input type="file" class="form-control" name="popup_imagem" 
                                           accept="image/*" onchange="previewImage(this)">
                                    <small class="text-muted">JPG, PNG, GIF ou WEBP (máx 2MB)</small>
                                </div>
                                
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="popup_ativo" 
                                           <?= ($config['popup_ativo'] ?? 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label">Pop-up Ativo</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SEÇÃO 3: PREVIEW DO POP-UP -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="section-header">
                                <i class="ti ti-eye"></i>
                                <h5 class="mb-0">Preview do Pop-up</h5>
                            </div>
                            <div class="card-body">
                                <div class="popup-preview">
                                    <img id="popup_imagem_preview" 
                                         src="../assets/images/popup/<?= htmlspecialchars($config['popup_imagem'] ?? 'icon.svg', ENT_QUOTES, 'UTF-8'); ?>" 
                                         alt="Preview">
                                    <h6 id="popup-preview-title"><?= htmlspecialchars($config['popup_titulo'] ?? 'Notificação', ENT_QUOTES, 'UTF-8'); ?></h6>
                                    <p><?= htmlspecialchars($config['pop_up'] ?? 'Mensagem do pop-up...', ENT_QUOTES, 'UTF-8'); ?></p>
                                    <button type="button" class="btn btn-primary btn-sm" id="popup-preview-button">
                                        <?= htmlspecialchars($config['popup_botao_texto'] ?? 'Fechar', ENT_QUOTES, 'UTF-8'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SEÇÃO 4: TICKER MESSAGES -->
                    <div class="col-md-12">
                        <div class="card">
                            <div class="section-header">
                                <i class="ti ti-speakerphone"></i>
                                <h5 class="mb-0">Mensagens do Ticker (20 Vagas)</h5>
                            </div>
                            <div class="card-body">
                                <!-- Preview do Ticker -->
                                <div class="ticker-preview">
                                    <div id="ticker-preview-content" class="ticker-content-preview">
                                        <!-- Preview será atualizado dinamicamente -->
                                    </div>
                                </div>
                                
                                <!-- Grid de configuração das mensagens -->
                                <div class="row">
                                    <?php for ($i = 1; $i <= 20; $i++): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="ticker-item-config">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h6 class="mb-0">Mensagem <?= $i ?></h6>
                                                <div class="form-check form-switch">
                                                    <input type="checkbox" class="form-check-input" 
                                                           id="ticker_ativo_<?= $i ?>" name="ticker_ativo_<?= $i ?>"
                                                           <?= ($config["ticker_ativo_$i"] ?? 0) ? 'checked' : ''; ?>>
                                                </div>
                                            </div>
                                            
                                            <textarea class="form-control form-control-sm mb-2" 
                                                      id="ticker_msg_<?= $i ?>" name="ticker_msg_<?= $i ?>" 
                                                      rows="2" maxlength="100"
                                                      placeholder="Digite a mensagem..."><?= htmlspecialchars($config["ticker_msg_$i"] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                                            <div id="msg-counter-<?= $i ?>" class="char-counter"></div>
                                            
                                            <!-- Seletor de ícones -->
                                            <input type="hidden" id="ticker_icon_<?= $i ?>" name="ticker_icon_<?= $i ?>" 
                                                   value="<?= htmlspecialchars($config["ticker_icon_$i"] ?? 'fas fa-star', ENT_QUOTES, 'UTF-8'); ?>">
                                            
                                            <div class="icon-selector">
                                                <?php 
                                                $icons = ['fas fa-fire', 'fas fa-chart-line', 'fas fa-trophy', 'fas fa-rocket', 'fas fa-star', 'fas fa-money-bill-wave', 'fas fa-users', 'fas fa-gem', 'fas fa-crown', 'fas fa-bolt'];
                                                foreach ($icons as $icon): 
                                                ?>
                                                <div class="icon-option <?= ($config["ticker_icon_$i"] ?? 'fas fa-star') === $icon ? 'selected' : ''; ?>" 
                                                     data-icon="<?= $icon ?>" 
                                                     onclick="selectIcon(this, 'ticker_icon_<?= $i ?>')">
                                                    <i class="<?= $icon ?>"></i>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- BOTÃO SALVAR -->
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-body text-center">
                                <button type="submit" class="btn btn-primary btn-lg me-3">
                                    <i class="ti ti-device-floppy me-2"></i>Salvar Todas as Configurações
                                </button>
                                <button type="button" class="btn btn-secondary btn-lg" onclick="location.reload()">
                                    <i class="ti ti-refresh me-2"></i>Cancelar
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </section>

    <!-- Footer -->
    <footer class="pc-footer">
        <div class="footer-wrapper container-fluid">
            <div class="row">
                <div class="col my-1">
                    <p class="m-0">Feito com muito &#9829; por <a href="https://t.me/devcorr3" target="_blank">Correa</a></p>
                </div>
                <div class="col-auto my-1">
                    <ul class="list-inline footer-link mb-0">
                        <li class="list-inline-item"><a href="../inicio/">Início</a></li>
                        <li class="list-inline-item"><a href="https://t.me/devcorr3" target="_blank">Support</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>

    <!-- Customizer -->
    <div class="pct-c-btn">
        <a href="#" data-bs-toggle="offcanvas" data-bs-target="#offcanvas_pc_layout">
            <i class="ph-duotone ph-gear-six"></i>
        </a>
    </div>

    <div class="offcanvas border-0 pct-offcanvas offcanvas-end" tabindex="-1" id="offcanvas_pc_layout">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title">Configuração</h5>
            <button type="button" class="btn btn-icon btn-link-danger ms-auto" data-bs-dismiss="offcanvas" aria-label="Close">
                <i class="ti ti-x"></i>
            </button>
        </div>
        <div class="pct-body customizer-body">
            <div class="offcanvas-body py-0">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item">
                        <div class="pc-dark">
                            <h6 class="mb-1">Modo Tema</h6>
                            <p class="text-muted text-sm">Escolha o modo claro ou escuro ou Auto</p>
                            <div class="row theme-color theme-layout">
                                <div class="col-4">
                                    <div class="d-grid">
                                        <button class="preset-btn btn active" data-value="true" onclick="layout_change('light');" data-bs-toggle="tooltip" title="Light">
                                            <svg class="pc-icon text-warning">
                                                <use xlink:href="#custom-sun-1"></use>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="d-grid">
                                        <button class="preset-btn btn" data-value="false" onclick="layout_change('dark');" data-bs-toggle="tooltip" title="Dark">
                                            <svg class="pc-icon">
                                                <use xlink:href="#custom-moon"></use>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="d-grid">
                                        <button class="preset-btn btn" data-value="default" onclick="layout_change_default();" data-bs-toggle="tooltip" title="Auto">
                                            <span class="pc-lay-icon d-flex align-items-center justify-content-center">
                                                <i class="ph-duotone ph-cpu"></i>
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>
                    <li class="list-group-item">
                        <h6 class="mb-1">Contraste do tema</h6>
                        <p class="text-muted text-sm">Escolha o contraste do tema</p>
                        <div class="row theme-contrast">
                            <div class="col-6">
                                <div class="d-grid">
                                    <button class="preset-btn btn" data-value="true" onclick="layout_theme_contrast_change('true');" data-bs-toggle="tooltip" title="True">
                                        <svg class="pc-icon">
                                            <use xlink:href="#custom-mask"></use>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-grid">
                                    <button class="preset-btn btn active" data-value="false" onclick="layout_theme_contrast_change('false');" data-bs-toggle="tooltip" title="False">
                                        <svg class="pc-icon">
                                            <use xlink:href="#custom-mask-1-outline"></use>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </li>
                    <li class="list-group-item">
                        <h6 class="mb-1">Tema personalizado</h6>
                        <p class="text-muted text-sm">Escolha a cor principal do seu tema</p>
                        <div class="theme-color preset-color">
                            <a href="#!" data-bs-toggle="tooltip" title="Azul" class="active" data-value="preset-1"><i class="ti ti-checks"></i></a>
                            <a href="#!" data-bs-toggle="tooltip" title="Lilás" data-value="preset-2"><i class="ti ti-checks"></i></a>
                            <a href="#!" data-bs-toggle="tooltip" title="Roxo" data-value="preset-3"><i class="ti ti-checks"></i></a>
                            <a href="#!" data-bs-toggle="tooltip" title="Rosa" data-value="preset-4"><i class="ti ti-checks"></i></a>
                            <a href="#!" data-bs-toggle="tooltip" title="Vermelho" data-value="preset-5"><i class="ti ti-checks"></i></a>
                            <a href="#!" data-bs-toggle="tooltip" title="Laranja" data-value="preset-6"><i class="ti ti-checks"></i></a>
                            <a href="#!" data-bs-toggle="tooltip" title="Amarelo" data-value="preset-7"><i class="ti ti-checks"></i></a>
                            <a href="#!" data-bs-toggle="tooltip" title="Verde" data-value="preset-8"><i class="ti ti-checks"></i></a>
                            <a href="#!" data-bs-toggle="tooltip" title="Verde Escuro" data-value="preset-9"><i class="ti ti-checks"></i></a>
                            <a href="#!" data-bs-toggle="tooltip" title="Azul Ciano" data-value="preset-10"><i class="ti ti-checks"></i></a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../assets/js/plugins/apexcharts.min.js"></script>
    <script src="../assets/js/pages/dashboard-default.js"></script>
    <script src="../assets/js/plugins/popper.min.js"></script>
    <script src="../assets/js/plugins/simplebar.min.js"></script>
    <script src="../assets/js/plugins/bootstrap.min.js"></script>
    <script src="../assets/js/fonts/custom-font.js"></script>
    <script src="../assets/js/pcoded.js"></script>
    <script src="../assets/js/plugins/feather.min.js"></script>

    <!-- Configurações de tema -->
    <script>
        layout_change('dark');
        layout_theme_contrast_change('false');
        change_box_container('false');
        layout_caption_change('true');
        layout_rtl_change('false');
        preset_change('preset-1');
        main_layout_change('vertical');
    </script>

    <!-- Script adicional para melhorar a experiência -->
    <script>
        // Salvar automaticamente no localStorage para backup
        function autoSaveForm() {
            const formData = {
                timestamp: new Date().getTime()
            };
            
            try {
                localStorage.setItem('config_textos_backup', JSON.stringify(formData));
            } catch (e) {
                // localStorage não disponível, não fazer nada
            }
        }

        // Atalhos de teclado
        document.addEventListener('keydown', function(e) {
            // Ctrl+S para salvar
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                document.querySelector('button[type="submit"]').click();
            }
            
            // Ctrl+R para resetar (recarregar)
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                if (confirm('Deseja recarregar e perder as alterações não salvas?')) {
                    location.reload();
                }
            }
        });

        // Auto-save a cada 30 segundos
        setInterval(autoSaveForm, 30000);
        
        // Auto-save quando sair da página
        window.addEventListener('beforeunload', autoSaveForm);
    </script>

</body>
</html>