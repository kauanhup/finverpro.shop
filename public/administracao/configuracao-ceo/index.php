<?php
session_start();

// Verificação de autenticação
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../');
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

// Verificar se tabela existe e criar se necessário
try {
    $checkTable = $conn->query("SHOW TABLES LIKE 'configurar_textos'");
    if ($checkTable->rowCount() == 0) {
        $createTable = "
            CREATE TABLE configurar_textos (
                id INT PRIMARY KEY AUTO_INCREMENT,
                titulo_site VARCHAR(100) DEFAULT '',
                descricao_site VARCHAR(200) DEFAULT '',
                keywords_site TEXT DEFAULT '',
                link_site VARCHAR(300) DEFAULT '',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ";
        $conn->exec($createTable);
        
        // Inserir dados padrão
        $insertDefault = "
            INSERT INTO configurar_textos (titulo_site, descricao_site, keywords_site, link_site) 
            VALUES (
                'Minha Plataforma de Investimentos',
                'A melhor plataforma para seus investimentos online com segurança e rentabilidade garantida.',
                'investimentos, renda extra, bitcoin, forex, trader',
                'https://meusite.com'
            )
        ";
        $conn->exec($insertDefault);
    }
} catch (Exception $e) {
    error_log("Erro ao verificar/criar tabela: " . $e->getMessage());
}

// Carregar dados atuais
$config = [
    'titulo_site' => '',
    'descricao_site' => '',
    'keywords_site' => '',
    'link_site' => ''
];

try {
    $stmt = $conn->query("SELECT titulo_site, descricao_site, keywords_site, link_site FROM configurar_textos LIMIT 1");
    $dbConfig = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($dbConfig) {
        $config = array_merge($config, $dbConfig);
    }
} catch (Exception $e) {
    error_log("Erro ao carregar configurações: " . $e->getMessage());
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar e sanitizar dados
        $titulo_site = trim($_POST['titulo_site'] ?? '');
        $descricao_site = trim($_POST['descricao_site'] ?? '');
        $keywords_site = trim($_POST['keywords_site'] ?? '');
        $link_site = trim($_POST['link_site'] ?? '');

        // Validações básicas
        if (empty($titulo_site)) {
            throw new Exception("O título do site é obrigatório.");
        }

        if (empty($descricao_site)) {
            throw new Exception("A descrição do site é obrigatória.");
        }

        if (!empty($link_site) && !filter_var($link_site, FILTER_VALIDATE_URL)) {
            throw new Exception("URL do site inválida.");
        }

        // Iniciar transação
        $conn->beginTransaction();

        // Verificar se já existe registro
        $checkStmt = $conn->query("SELECT COUNT(*) FROM configurar_textos");
        $recordExists = $checkStmt->fetchColumn() > 0;

        if ($recordExists) {
            // UPDATE se existe
            $stmt = $conn->prepare("UPDATE configurar_textos SET titulo_site = ?, descricao_site = ?, keywords_site = ?, link_site = ? WHERE id = 1");
            $stmt->execute([$titulo_site, $descricao_site, $keywords_site, $link_site]);
        } else {
            // INSERT se não existe
            $stmt = $conn->prepare("INSERT INTO configurar_textos (titulo_site, descricao_site, keywords_site, link_site) VALUES (?, ?, ?, ?)");
            $stmt->execute([$titulo_site, $descricao_site, $keywords_site, $link_site]);
        }

        $conn->commit();
        
        // Atualizar dados locais
        $config['titulo_site'] = $titulo_site;
        $config['descricao_site'] = $descricao_site;
        $config['keywords_site'] = $keywords_site;
        $config['link_site'] = $link_site;
        
        $message = "Configurações de SEO atualizadas com sucesso!";
        $messageType = 'success';

        // Log de sucesso
        error_log("SEO atualizado pelo usuário {$user_id}");

    } catch (Exception $e) {
        $conn->rollback();
        $message = $e->getMessage();
        $messageType = 'error';
        error_log("Erro ao atualizar SEO - Usuário {$user_id}: " . $e->getMessage());
    }
}

// Função para análise SEO simples
function getSimpleSEOScore($config) {
    $score = 0;
    $issues = [];
    
    // Verificar título
    $titleLen = strlen($config['titulo_site']);
    if ($titleLen > 0 && $titleLen <= 60) {
        $score += 25;
    } else {
        $issues[] = $titleLen === 0 ? 'Título vazio' : 'Título muito longo';
    }
    
    // Verificar descrição
    $descLen = strlen($config['descricao_site']);
    if ($descLen >= 120 && $descLen <= 160) {
        $score += 25;
    } else {
        $issues[] = $descLen < 120 ? 'Descrição muito curta' : 'Descrição muito longa';
    }
    
    // Verificar keywords
    if (!empty($config['keywords_site'])) {
        $keywords = array_filter(array_map('trim', explode(',', $config['keywords_site'])));
        if (count($keywords) >= 3 && count($keywords) <= 10) {
            $score += 25;
        } else {
            $issues[] = count($keywords) < 3 ? 'Poucas palavras-chave' : 'Muitas palavras-chave';
        }
    } else {
        $issues[] = 'Sem palavras-chave';
    }
    
    // Verificar URL
    if (!empty($config['link_site']) && filter_var($config['link_site'], FILTER_VALIDATE_URL)) {
        $score += 25;
    } else {
        $issues[] = 'URL inválida ou vazia';
    }
    
    return ['score' => $score, 'issues' => $issues];
}

$seoAnalysis = getSimpleSEOScore($config);
?>

<!doctype html>
<html lang="pt-BR">
<head>
    <title>Configuração de SEO - Dashboard</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <link rel="icon" href="../assets/images/favicon.svg" type="image/x-icon" />
    <link rel="stylesheet" href="../assets/fonts/inter/inter.css" id="main-font-link" />
    <link rel="stylesheet" href="../assets/fonts/tabler-icons.min.css" />
    <link rel="stylesheet" href="../assets/css/style.css" id="main-style-link" />
    <link rel="stylesheet" href="../assets/css/style-preset.css" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        .loader-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #131920;
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        .loader {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: transparent;
            border: 8px solid #ffffff;
            border-top-color: #4680ff;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .loading-text {
            color: #ffffff;
            font-size: 16px;
        }
        .seo-info {
            background: #e8f4fd;
            border: 1px solid #2196f3;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .seo-info h6 {
            color: #1976d2;
            margin-bottom: 10px;
        }
        .char-counter {
            font-size: 11px;
            color: #6c757d;
            text-align: right;
            margin-top: 5px;
        }
        .char-counter.warning {
            color: #ff9800;
        }
        .char-counter.danger {
            color: #f44336;
        }
        .char-counter.success {
            color: #4caf50;
        }
        .seo-score-widget {
            text-align: center;
            padding: 20px;
        }
        .score-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 24px;
            font-weight: bold;
            color: white;
        }
        .score-excellent { background: linear-gradient(135deg, #4caf50, #81c784); }
        .score-good { background: linear-gradient(135deg, #2196f3, #64b5f6); }
        .score-average { background: linear-gradient(135deg, #ff9800, #ffb74d); }
        .score-poor { background: linear-gradient(135deg, #f44336, #e57373); }
        .google-preview {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            font-family: arial, sans-serif;
        }
        .google-preview h3 {
            color: #1a0dab;
            font-size: 20px;
            font-weight: normal;
            margin: 0 0 5px 0;
            text-decoration: underline;
        }
        .google-preview .url {
            color: #006621;
            font-size: 14px;
            margin: 0 0 5px 0;
        }
        .google-preview .description {
            color: #545454;
            font-size: 14px;
            line-height: 1.58;
            margin: 0;
        }
    </style>
</head>

<body data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-layout="vertical" data-pc-direction="ltr" data-pc-theme_contrast="" data-pc-theme="dark">

    <div class="loader-container">
        <div class="loader"></div>
        <div class="loading-text">Carregando configurações...</div>
    </div>

    <!-- Sidebar -->
    <nav class="pc-sidebar">
        <div class="navbar-wrapper">
            <div class="m-header">
                <a href="../dashboard/index.html" class="b-brand text-primary">
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
                                <i class="ti ti-dashboard"></i>
                            </span>
                            <span class="pc-mtext">Dashboard</span>
                        </a>
                    </li>
                    <li class="pc-item pc-caption">
                        <label>Configurações</label>
                    </li>
                    <li class="pc-item">
                        <a href="./" class="pc-link active">
                            <span class="pc-micon">
                                <i class="ti ti-world"></i>
                            </span>
                            <span class="pc-mtext">SEO do Site</span>
                        </a>
                    </li>
                    <li class="pc-item">
                        <a href="../personalizar-banners/" class="pc-link">
                            <span class="pc-micon">
                                <i class="ti ti-photo"></i>
                            </span>
                            <span class="pc-mtext">Imagens</span>
                        </a>
                    </li>
                    <li class="pc-item">
                        <a href="../personalizacao-textos/" class="pc-link">
                            <span class="pc-micon">
                                <i class="ti ti-file-text"></i>
                            </span>
                            <span class="pc-mtext">Textos</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Header -->
    <header class="pc-header">
        <div class="header-wrapper">
            <div class="me-auto pc-mob-drp">
                <ul class="list-unstyled">
                    <li class="pc-h-item pc-sidebar-collapse">
                        <a href="#" class="pc-head-link ms-0" id="sidebar-hide">
                            <i class="ti ti-menu-2"></i>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="ms-auto">
                <ul class="list-unstyled">
                    <li class="dropdown pc-h-item">
                        <a class="pc-head-link dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#" role="button">
                            <i class="ti ti-sun"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end pc-h-dropdown">
                            <a href="#!" class="dropdown-item" onclick="layout_change('dark')">
                                <i class="ti ti-moon"></i>
                                <span>Escuro</span>
                            </a>
                            <a href="#!" class="dropdown-item" onclick="layout_change('light')">
                                <i class="ti ti-sun"></i>
                                <span>Claro</span>
                            </a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <section class="pc-container">
        <div class="pc-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-md-12">
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../dashboard/">Dashboard</a></li>
                                <li class="breadcrumb-item" aria-current="page">SEO do Site</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">
                                    <i class="ti ti-world me-2"></i>
                                    Configuração de SEO
                                </h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SEO Score -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body seo-score-widget">
                            <div class="score-circle <?php 
                                if ($seoAnalysis['score'] >= 80) echo 'score-excellent';
                                elseif ($seoAnalysis['score'] >= 60) echo 'score-good';
                                elseif ($seoAnalysis['score'] >= 40) echo 'score-average';
                                else echo 'score-poor';
                            ?>">
                                <?php echo $seoAnalysis['score']; ?>%
                            </div>
                            <h6>Score SEO</h6>
                            <p class="text-muted">
                                <?php 
                                if ($seoAnalysis['score'] >= 80) echo 'Excelente!';
                                elseif ($seoAnalysis['score'] >= 60) echo 'Bom';
                                elseif ($seoAnalysis['score'] >= 40) echo 'Regular';
                                else echo 'Precisa melhorar';
                                ?>
                            </p>
                            <?php if (!empty($seoAnalysis['issues'])): ?>
                            <div class="mt-3">
                                <small class="text-muted">Problemas encontrados:</small>
                                <ul class="list-unstyled mt-2">
                                    <?php foreach ($seoAnalysis['issues'] as $issue): ?>
                                    <li><small class="text-warning">• <?php echo $issue; ?></small></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="ti ti-eye me-2"></i>Como aparece no Google</h5>
                        </div>
                        <div class="card-body">
                            <div class="google-preview">
                                <h3 id="google-title-preview"><?php echo htmlspecialchars($config['titulo_site'] ?: 'Seu Título Aqui'); ?></h3>
                                <div class="url" id="google-url-preview"><?php echo htmlspecialchars($config['link_site'] ?: 'https://seusite.com'); ?></div>
                                <p class="description" id="google-desc-preview"><?php echo htmlspecialchars($config['descricao_site'] ?: 'Sua descrição aparecerá aqui...'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="ti ti-settings me-2"></i>Configurações Básicas de SEO</h5>
                        </div>
                        <div class="card-body">
                            
                            <!-- Info -->
                            <div class="seo-info">
                                <h6><i class="ti ti-info-circle me-2"></i>O que são essas configurações?</h6>
                                <ul class="mb-0">
                                    <li><strong>Título:</strong> Aparece na aba do navegador e como título no Google</li>
                                    <li><strong>Descrição:</strong> Texto que aparece nos resultados de busca do Google</li>
                                    <li><strong>Palavras-chave:</strong> Ajuda o Google a entender sobre o que é seu site</li>
                                    <li><strong>URL:</strong> Endereço principal do seu site</li>
                                </ul>
                            </div>

                            <form method="POST" onsubmit="return validateForm()">
                                <div class="row">
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">
                                            <i class="ti ti-tag me-2"></i>Título do Site
                                            <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="titulo_site"
                                               name="titulo_site"
                                               value="<?php echo htmlspecialchars($config['titulo_site']); ?>"
                                               placeholder="Ex: Minha Plataforma de Investimentos"
                                               maxlength="60"
                                               oninput="updatePreview(); updateCounter('titulo_site', 'title-counter', 60)"
                                               required />
                                        <div id="title-counter" class="char-counter"></div>
                                        <small class="text-muted">Ideal: 30-60 caracteres</small>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">
                                            <i class="ti ti-link me-2"></i>URL Principal do Site
                                        </label>
                                        <input type="url" 
                                               class="form-control" 
                                               id="link_site"
                                               name="link_site"
                                               value="<?php echo htmlspecialchars($config['link_site']); ?>"
                                               placeholder="https://meusite.com"
                                               oninput="updatePreview()" />
                                        <small class="text-muted">URL completa do seu site</small>
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">
                                            <i class="ti ti-file-text me-2"></i>Descrição do Site
                                            <span class="text-danger">*</span>
                                        </label>
                                        <textarea class="form-control" 
                                                  id="descricao_site"
                                                  name="descricao_site" 
                                                  rows="3" 
                                                  maxlength="160"
                                                  placeholder="Ex: A melhor plataforma para seus investimentos online com segurança e rentabilidade garantida."
                                                  oninput="updatePreview(); updateCounter('descricao_site', 'desc-counter', 160)"
                                                  required><?php echo htmlspecialchars($config['descricao_site']); ?></textarea>
                                        <div id="desc-counter" class="char-counter"></div>
                                        <small class="text-muted">Ideal: 120-160 caracteres</small>
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">
                                            <i class="ti ti-key me-2"></i>Palavras-chave
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="keywords_site"
                                               name="keywords_site"
                                               value="<?php echo htmlspecialchars($config['keywords_site']); ?>"
                                               placeholder="Ex: investimentos, renda extra, bitcoin, forex" />
                                        <small class="text-muted">Separe por vírgulas (ideal: 5-10 palavras-chave)</small>
                                    </div>

                                </div>

                                <hr class="my-4">
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <button type="submit" class="btn btn-primary btn-lg me-3" id="submitBtn">
                                            <i class="ti ti-device-floppy me-2"></i>Salvar Configurações
                                        </button>
                                        <button type="button" class="btn btn-secondary" onclick="location.reload()">
                                            <i class="ti ti-refresh me-2"></i>Cancelar
                                        </button>
                                    </div>
                                    <div class="text-muted">
                                        <small>
                                            <i class="ti ti-clock me-1"></i>
                                            Última atualização: <?php echo date('d/m/Y H:i'); ?>
                                        </small>
                                    </div>
                                </div>
                            </form>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="pc-footer">
        <div class="footer-wrapper container-fluid">
            <div class="row">
                <div class="col my-1">
                    <p class="m-0">Feito com ❤️ por <a href="https://t.me/devcorr3" target="_blank">Correa</a></p>
                </div>
                <div class="col-auto my-1">
                    <ul class="list-inline footer-link mb-0">
                        <li class="list-inline-item"><a href="../dashboard/">Dashboard</a></li>
                        <li class="list-inline-item"><a href="https://t.me/devcorr3" target="_blank">Suporte</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="../assets/js/plugins/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.js"></script>

    <script>
        // Remove loader
        window.addEventListener("load", function() {
            const loader = document.querySelector('.loader-container');
            if (loader) {
                loader.style.display = 'none';
            }
        });

        // Show messages
        <?php if ($message): ?>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: '<?php echo $messageType; ?>',
                title: '<?php echo $messageType === 'success' ? 'Sucesso!' : 'Erro!'; ?>',
                text: '<?php echo addslashes($message); ?>',
                confirmButtonText: 'OK',
                timer: <?php echo $messageType === 'success' ? '3000' : '0'; ?>,
                timerProgressBar: <?php echo $messageType === 'success' ? 'true' : 'false'; ?>
            });
        });
        <?php endif; ?>

        // Character counters
        function updateCounter(inputId, counterId, maxLength) {
            const input = document.getElementById(inputId);
            const counter = document.getElementById(counterId);
            const currentLength = input.value.length;
            
            counter.textContent = `${currentLength}/${maxLength} caracteres`;
            
            if (currentLength > maxLength * 0.9) {
                counter.className = 'char-counter danger';
            } else if (currentLength > maxLength * 0.7) {
                counter.className = 'char-counter warning';
            } else {
                counter.className = 'char-counter success';
            }
        }

        // Update preview
        function updatePreview() {
            const title = document.getElementById('titulo_site').value || 'Seu Título Aqui';
            const description = document.getElementById('descricao_site').value || 'Sua descrição aparecerá aqui...';
            const url = document.getElementById('link_site').value || 'https://seusite.com';
            
            document.getElementById('google-title-preview').textContent = title;
            document.getElementById('google-desc-preview').textContent = description;
            document.getElementById('google-url-preview').textContent = url;
        }

        // Form validation
        function validateForm() {
            const title = document.getElementById('titulo_site').value.trim();
            const description = document.getElementById('descricao_site').value.trim();
            
            if (!title) {
                Swal.fire({
                    icon: 'error',
                    title: 'Campo Obrigatório',
                    text: 'O título do site é obrigatório'
                });
                return false;
            }
            
            if (!description) {
                Swal.fire({
                    icon: 'error',
                    title: 'Campo Obrigatório',
                    text: 'A descrição do site é obrigatória'
                });
                return false;
            }
            
            // Show loading
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.innerHTML = '<i class="ti ti-loader me-2"></i>Salvando...';
            submitBtn.disabled = true;
            
            return true;
        }

        // Initialize counters on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateCounter('titulo_site', 'title-counter', 60);
            updateCounter('descricao_site', 'desc-counter', 160);
            updatePreview();
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+S to save
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                document.getElementById('submitBtn').click();
            }
        });

    </script>

    <!-- Theme Scripts -->
    <script>
        layout_change('dark');
        layout_theme_contrast_change('false');
        change_box_container('false');
        layout_caption_change('true');
        layout_rtl_change('false');
        preset_change('preset-1');
        main_layout_change('vertical');
    </script>

</body>
</html>