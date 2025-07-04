<?php
session_start();

// Verificação de autenticação
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

// Configurações de upload
$config = [
    'icons_dir' => "../../assets/images/icons/",
    'banners_dir' => "../../assets/images/banners/",
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
    'allowed_mimes' => ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'],
    'max_size' => 5 * 1024 * 1024, // 5MB
    'max_width' => 2000,
    'max_height' => 2000
];

// Criar diretórios se não existirem
foreach ([$config['icons_dir'], $config['banners_dir']] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

/**
 * Função melhorada para processar uploads
 */
function processUpload($inputName, $directory, $config) {
    if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $file = $_FILES[$inputName];
    
    // Validar se arquivo foi realmente enviado
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new Exception("Arquivo {$inputName} não foi enviado corretamente");
    }
    
    // Validar tamanho
    if ($file['size'] > $config['max_size']) {
        $maxSizeMB = round($config['max_size'] / 1024 / 1024, 1);
        throw new Exception("Arquivo {$inputName} muito grande. Máximo: {$maxSizeMB}MB");
    }
    
    if ($file['size'] == 0) {
        throw new Exception("Arquivo {$inputName} está vazio");
    }

    // Validar tipo MIME real do arquivo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $config['allowed_mimes'])) {
        throw new Exception("Tipo de arquivo inválido para {$inputName}. Use apenas: JPG, PNG, GIF ou WebP");
    }

    // Validar extensão
    $originalName = $file['name'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    
    if (!in_array($extension, $config['allowed_extensions'])) {
        throw new Exception("Extensão de arquivo inválida para {$inputName}. Use: " . implode(', ', $config['allowed_extensions']));
    }

    // Validar se é realmente uma imagem
    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        throw new Exception("O arquivo {$inputName} não é uma imagem válida");
    }
    
    // Validar dimensões
    list($width, $height) = $imageInfo;
    if ($width > $config['max_width'] || $height > $config['max_height']) {
        throw new Exception("Dimensões muito grandes para {$inputName}. Máximo: {$config['max_width']}x{$config['max_height']}px. Atual: {$width}x{$height}px");
    }

    // Gerar nome único e seguro
    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '', pathinfo($originalName, PATHINFO_FILENAME));
    $safeName = substr($safeName, 0, 50); // Limitar tamanho
    $fileName = $safeName . '_' . uniqid() . '_' . time() . '.' . $extension;
    $targetPath = $directory . $fileName;

    // Verificar se diretório é gravável
    if (!is_writable($directory)) {
        throw new Exception("Diretório {$directory} não tem permissão de escrita");
    }

    // Mover arquivo
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception("Erro ao salvar arquivo {$inputName} no servidor");
    }

    // Verificar se arquivo foi salvo corretamente
    if (!file_exists($targetPath)) {
        throw new Exception("Arquivo {$inputName} não foi salvo corretamente");
    }

    return $fileName;
}

/**
 * Função para fazer backup da imagem anterior
 */
function backupOldImage($imageName, $directory) {
    if (!$imageName) return;
    
    $oldImagePath = $directory . $imageName;
    if (file_exists($oldImagePath)) {
        $backupDir = $directory . 'backup/';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $backupName = 'backup_' . date('Y-m-d_H-i-s') . '_' . $imageName;
        copy($oldImagePath, $backupDir . $backupName);
    }
}

/**
 * Buscar imagens atuais para backup - CORRIGIDO
 */
function getCurrentImages($conn) {
    try {
        // Primeiro verifica se a tabela existe
        $checkTable = $conn->query("SHOW TABLES LIKE 'personalizar_imagens'");
        if ($checkTable->rowCount() == 0) {
            return [];
        }
        
        $stmt = $conn->prepare("SELECT * FROM personalizar_imagens LIMIT 1");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        error_log("Erro ao buscar imagens atuais: " . $e->getMessage());
        return [];
    }
}

/**
 * Função para garantir que existe um registro na tabela - NOVA
 */
function ensureConfigRecord($conn) {
    try {
        // Verifica se existe algum registro
        $stmt = $conn->prepare("SELECT COUNT(*) FROM personalizar_imagens");
        $stmt->execute();
        $count = $stmt->fetchColumn();
        
        if ($count == 0) {
            // Cria registro inicial com valores padrão
            $insertStmt = $conn->prepare("
                INSERT INTO personalizar_imagens (
                    logo, tela_pix, tela_retirada, tela_login, inicio, 
                    tela_avatar, tela_bonus, tela_perfil, checklist_image,
                    created_at, updated_at
                ) VALUES (
                    NULL, '1.jpg', 'retirada.jpg', NULL, '2.jpg',
                    'avatar.jpg', '1.jpg', NULL, NULL,
                    NOW(), NOW()
                )
            ");
            
            if (!$insertStmt->execute()) {
                throw new Exception("Erro ao criar registro inicial");
            }
            
            return $conn->lastInsertId();
        }
        
        // Retorna o ID do primeiro registro
        $stmt = $conn->prepare("SELECT id FROM personalizar_imagens LIMIT 1");
        $stmt->execute();
        return $stmt->fetchColumn();
        
    } catch (Exception $e) {
        throw new Exception("Erro ao garantir registro de configuração: " . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verificar se pelo menos um arquivo foi enviado
        $hasFiles = false;
        foreach ($_FILES as $file) {
            if ($file['error'] === UPLOAD_ERR_OK) {
                $hasFiles = true;
                break;
            }
        }
        
        if (!$hasFiles) {
            throw new Exception("Nenhum arquivo foi selecionado para upload");
        }

        // Garantir que existe um registro na tabela
        $configId = ensureConfigRecord($conn);
        
        // Buscar imagens atuais para backup
        $currentImages = getCurrentImages($conn);

        // Processar uploads
        $uploads = [];
        $uploads['logo'] = processUpload('logo', $config['icons_dir'], $config);
        $uploads['tela_login'] = processUpload('tela_login', $config['banners_dir'], $config);
        $uploads['inicio'] = processUpload('inicio', $config['banners_dir'], $config);
        $uploads['tela_perfil'] = processUpload('tela_perfil', $config['banners_dir'], $config);
        $uploads['tela_avatar'] = processUpload('tela_avatar', $config['banners_dir'], $config);
        $uploads['tela_pix'] = processUpload('tela_pix', $config['banners_dir'], $config);
        $uploads['tela_retirada'] = processUpload('tela_retirada', $config['banners_dir'], $config);
        $uploads['tela_bonus'] = processUpload('tela_bonus', $config['banners_dir'], $config);
        $uploads['checklist_image'] = processUpload('checklist_image', $config['banners_dir'], $config);

        // Filtrar apenas uploads válidos
        $validUploads = array_filter($uploads, function($value) {
            return $value !== null;
        });

        if (empty($validUploads)) {
            throw new Exception("Nenhum arquivo válido foi processado");
        }

        // Iniciar transação para atomicidade
        $conn->beginTransaction();

        try {
            // Fazer backup das imagens que serão substituídas
            foreach ($validUploads as $field => $newFileName) {
                if (isset($currentImages[$field]) && $currentImages[$field]) {
                    $directory = ($field === 'logo') ? $config['icons_dir'] : $config['banners_dir'];
                    backupOldImage($currentImages[$field], $directory);
                }
            }

            // Construir SQL dinamicamente apenas para campos com novos uploads
            $setParts = [];
            $params = ['id' => $configId];
            
            foreach ($validUploads as $field => $fileName) {
                $setParts[] = "{$field} = :{$field}";
                $params[$field] = $fileName;
            }
            
            // Sempre atualizar o updated_at
            $setParts[] = "updated_at = NOW()";

            // SEMPRE usar UPDATE pois garantimos que o registro existe
            $sql = "UPDATE personalizar_imagens SET " . implode(', ', $setParts) . " WHERE id = :id";
            
            $stmt = $conn->prepare($sql);
            
            // Vincular parâmetros
            foreach ($params as $param => $value) {
                $stmt->bindValue(":{$param}", $value);
            }

            if (!$stmt->execute()) {
                throw new Exception("Erro ao salvar no banco de dados: " . implode(', ', $stmt->errorInfo()));
            }
            
            // Verificar se alguma linha foi afetada
            if ($stmt->rowCount() == 0) {
                throw new Exception("Nenhum registro foi atualizado. Verifique se o ID existe.");
            }

            // Commit da transação
            $conn->commit();
            
            $uploadCount = count($validUploads);
            $uploadList = implode(', ', array_keys($validUploads));
            $message = "{$uploadCount} imagem(ns) atualizada(s) com sucesso: {$uploadList}";
            $messageType = 'success';
            
            // Log de sucesso (opcional)
            error_log("Upload realizado com sucesso pelo usuário {$user_id}: " . $uploadList);

        } catch (Exception $e) {
            // Rollback da transação
            $conn->rollback();
            
            // Limpar arquivos que foram salvos em caso de erro na transação
            foreach ($validUploads as $fileName) {
                if ($fileName) {
                    $paths = [
                        $config['banners_dir'] . $fileName,
                        $config['icons_dir'] . $fileName
                    ];
                    foreach ($paths as $path) {
                        if (file_exists($path)) {
                            unlink($path);
                        }
                    }
                }
            }
            
            throw $e;
        }

    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
        
        // Log de erro
        error_log("Erro no upload - Usuário {$user_id}: " . $e->getMessage());
    }
}

// Buscar imagens atuais para exibição
$currentImages = getCurrentImages($conn);
?>

<!doctype html>
<html lang="pt-BR">
<head>
    <title>Personalização de Imagens - Dashboard</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="author" content="seemniick" />
    <link rel="icon" href="../assets/images/favicon.svg" type="image/x-icon" />
    <link rel="stylesheet" href="../assets/fonts/inter/inter.css" id="main-font-link" />
    <link rel="stylesheet" href="../assets/fonts/tabler-icons.min.css" />
    <link rel="stylesheet" href="../assets/fonts/feather.css" />
    <link rel="stylesheet" href="../assets/fonts/fontawesome.css" />
    <link rel="stylesheet" href="../assets/fonts/material.css" />
    <link rel="stylesheet" href="../assets/css/style.css" id="main-style-link" />
    <link rel="stylesheet" href="../assets/css/style-preset.css" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-layout="vertical" data-pc-direction="ltr" data-pc-theme_contrast="" data-pc-theme="dark">
    
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
        
        .image-preview {
            max-width: 150px;
            max-height: 100px;
            margin-top: 10px;
            border-radius: 5px;
            border: 1px solid #ddd;
            object-fit: cover;
        }
        
        .upload-info {
            font-size: 11px;
            color: #6c757d;
            margin-top: 5px;
            line-height: 1.3;
        }
        
        .current-image-info {
            background: #f8f9fa;
            padding: 8px;
            border-radius: 4px;
            margin-top: 8px;
            font-size: 11px;
            color: #495057;
        }
        
        .form-file {
            position: relative;
        }
        
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }
        
        .upload-requirements {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .upload-requirements h6 {
            color: #1976d2;
            margin-bottom: 10px;
        }
        
        .upload-requirements ul {
            margin: 0;
            padding-left: 20px;
            color: #424242;
        }
        
        .alert-info {
            background-color: #d1ecf1;
            border-color: #bee5eb;
            color: #0c5460;
        }
    </style>

    <script>
        window.addEventListener("load", function() {
            const loader = document.querySelector('.loader-container');
            if (loader) {
                loader.style.display = 'none';
            }
            document.body.style.overflow = 'auto'; 
        });
        
        <?php if ($message): ?>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: '<?php echo $messageType; ?>',
                title: '<?php echo $messageType === 'success' ? 'Sucesso!' : 'Erro!'; ?>',
                text: '<?php echo addslashes($message); ?>',
                confirmButtonText: 'OK',
                timer: <?php echo $messageType === 'success' ? '3000' : '0'; ?>,
                timerProgressBar: <?php echo $messageType === 'success' ? 'true' : 'false'; ?>
            }).then(() => {
                <?php if ($messageType === 'success'): ?>
                window.location.reload();
                <?php endif; ?>
            });
        });
        <?php endif; ?>
        
        // Preview de imagens
        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Validação básica no frontend
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!validTypes.includes(file.type)) {
                    alert('Tipo de arquivo inválido. Use apenas: JPG, PNG, GIF ou WebP');
                    input.value = '';
                    preview.style.display = 'none';
                    return;
                }
                
                const maxSize = 5 * 1024 * 1024; // 5MB
                if (file.size > maxSize) {
                    alert('Arquivo muito grande. Tamanho máximo: 5MB');
                    input.value = '';
                    preview.style.display = 'none';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        }
        
        // Validação do formulário
        function validateForm() {
            const fileInputs = document.querySelectorAll('input[type="file"]');
            let hasValidFile = false;
            
            for (let input of fileInputs) {
                if (input.files.length > 0) {
                    hasValidFile = true;
                    break;
                }
            }
            
            if (!hasValidFile) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Atenção!',
                    text: 'Selecione pelo menos um arquivo para upload.',
                    confirmButtonText: 'OK'
                });
                return false;
            }
            
            // Mostrar loading
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.innerHTML = '<i class="ti ti-loader-2 me-2"></i>Enviando...';
            submitBtn.disabled = true;
            
            return true;
        }
        
        // Contador de arquivos selecionados
        function updateFileCounter() {
            const fileInputs = document.querySelectorAll('input[type="file"]');
            let selectedCount = 0;
            
            fileInputs.forEach(input => {
                if (input.files.length > 0) {
                    selectedCount++;
                }
            });
            
            const submitBtn = document.getElementById('submitBtn');
            if (selectedCount > 0) {
                submitBtn.innerHTML = `<i class="ti ti-upload me-2"></i>Atualizar ${selectedCount} Imagem(ns)`;
                submitBtn.classList.remove('btn-primary');
                submitBtn.classList.add('btn-success');
            } else {
                submitBtn.innerHTML = '<i class="ti ti-upload me-2"></i>Salvar Alterações';
                submitBtn.classList.remove('btn-success');
                submitBtn.classList.add('btn-primary');
            }
        }

        // Adicionar event listeners quando a página carregar
        document.addEventListener('DOMContentLoaded', function() {
            const fileInputs = document.querySelectorAll('input[type="file"]');
            fileInputs.forEach(input => {
                input.addEventListener('change', updateFileCounter);
            });
        });
    </script>

    <div class="loader-container">
        <div class="loader"></div>
        <div class="loading-text">Carregando recursos, aguarde um pouco...</div>
    </div>

    <!-- Sidebar -->
    <nav class="pc-sidebar">
        <div class="navbar-wrapper">
            <div class="m-header">
                <a href="../dashboard/" class="b-brand text-primary">
                    <img src="../assets/images/logo-dark.svg" alt="logo" class="logo logo-lg">
                </a>
            </div>
            <div class="navbar-content">
                <div class="card pc-user-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <img src="../assets/images/user/avatar-1.jpg" alt="user-image" class="user-avtar wid-45 rounded-circle">
                            </div>
                            <div class="flex-grow-1 ms-3 me-2">
                                <h6 class="mb-0">Admin</h6>
                                <small>Administrador</small>
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
                                <svg class="pc-icon"><use xlink:href="#custom-home"></use></svg>
                            </span>
                            <span class="pc-mtext">Dashboard</span>
                        </a>
                    </li>
                    
                    <li class="pc-item pc-caption">
                        <label>Personalização</label>
                    </li>
                    <li class="pc-item">
                        <a href="#!" class="pc-link">
                            <span class="pc-micon">
                                <svg class="pc-icon"><use xlink:href="#custom-image"></use></svg>
                            </span>
                            <span class="pc-mtext">Imagens</span>
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
                        <a class="pc-head-link dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false">
                            <svg class="pc-icon">
                                <use xlink:href="#custom-sun-1"></use>
                            </svg>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end pc-h-dropdown">
                            <a href="#!" class="dropdown-item" onclick="layout_change('dark')">
                                <svg class="pc-icon">
                                    <use xlink:href="#custom-moon"></use>
                                </svg>
                                <span>Dark</span>
                            </a>
                            <a href="#!" class="dropdown-item" onclick="layout_change('light')">
                                <svg class="pc-icon">
                                    <use xlink:href="#custom-sun-1"></use>
                                </svg>
                                <span>Light</span>
                            </a>
                            <a href="#!" class="dropdown-item" onclick="layout_change_default()">
                                <svg class="pc-icon">
                                    <use xlink:href="#custom-setting-2"></use>
                                </svg>
                                <span>Default</span>
                            </a>
                        </div>
                    </li>
                    <li class="dropdown pc-h-item">
                        <a class="pc-head-link dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false">
                            <img src="../assets/images/user/avatar-2.jpg" alt="user-image" class="user-avtar">
                        </a>
                        <div class="dropdown-menu dropdown-menu-end pc-h-dropdown">
                            <div class="dropdown-header d-flex align-items-center justify-content-between">
                                <h5 class="m-0">Profile</h5>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a href="../" class="dropdown-item">
                                <svg class="pc-icon">
                                    <use xlink:href="#custom-logout-1"></use>
                                </svg>
                                <span>Logout</span>
                            </a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </header>
    
    <section class="pc-container">
        <div class="pc-content">
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-md-12">
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../dashboard/index.html">Dashboard</a></li>
                                <li class="breadcrumb-item" aria-current="page">Personalização de Banners</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">Banners da Plataforma</h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
           
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="ti ti-photo me-2"></i>Personalização de Imagens</h5>
                        </div>
                        <div class="card-body">
                            
                            <!-- Aviso sobre a estrutura da tabela -->
                            <?php if (empty($currentImages)): ?>
                            <div class="alert alert-info mb-4">
                                <h6><i class="ti ti-info-circle me-2"></i>Primeira Configuração</h6>
                                <p class="mb-0">Sistema detectou que é a primeira vez configurando as imagens. Um registro inicial será criado automaticamente.</p>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Requisitos de Upload -->
                            <div class="upload-requirements">
                                <h6><i class="ti ti-info-circle me-2"></i>Requisitos para Upload</h6>
                                <ul>
                                    <li><strong>Formatos aceitos:</strong> JPG, PNG, GIF, WebP</li>
                                    <li><strong>Tamanho máximo:</strong> 5MB por arquivo</li>
                                    <li><strong>Dimensões máximas:</strong> 2000x2000 pixels</li>
                                    <li><strong>Backup:</strong> Imagens antigas são salvas automaticamente</li>
                                    <li><strong>Segurança:</strong> Apenas imagens válidas são aceitas</li>
                                </ul>
                            </div>

                            <form method="POST" enctype="multipart/form-data" id="uploadForm" onsubmit="return validateForm()">
                                <div class="row">
                                    
                                    <!-- Logo -->
                                    <div class="col-md-4">
                                        <div class="form-file mb-3">
                                            <label class="form-label">
                                                <i class="ti ti-photo me-2"></i>LOGO da Plataforma
                                            </label>
                                            <input type="file" class="form-control" name="logo" 
                                                   accept="image/*" onchange="previewImage(this, 'preview_logo')">
                                            
                                            <?php if (!empty($currentImages['logo'])): ?>
                                                <div class="current-image-info">
                                                    <strong>Imagem atual:</strong> <?php echo htmlspecialchars($currentImages['logo']); ?>
                                                    <br><img src="../assets/images/icons/<?php echo htmlspecialchars($currentImages['logo']); ?>" 
                                                         class="image-preview" alt="Logo atual">
                                                </div>
                                            <?php endif; ?>
                                            
                                            <img id="preview_logo" class="image-preview" style="display:none;" alt="Preview nova imagem">
                                            <div class="upload-info">Recomendado: 200x60px | Para cabeçalho</div>
                                        </div>
                                    </div>

                                    <!-- Tela Login -->
                                    <div class="col-md-4">
                                        <div class="form-file mb-3">
                                            <label class="form-label">
                                                <i class="ti ti-login me-2"></i>Imagem Entrar/Cadastro
                                            </label>
                                            <input type="file" class="form-control" name="tela_login" 
                                                   accept="image/*" onchange="previewImage(this, 'preview_tela_login')">
                                            
                                            <?php if (!empty($currentImages['tela_login'])): ?>
                                                <div class="current-image-info">
                                                    <strong>Imagem atual:</strong> <?php echo htmlspecialchars($currentImages['tela_login']); ?>
                                                    <br><img src="../assets/images/banners/<?php echo htmlspecialchars($currentImages['tela_login']); ?>" 
                                                         class="image-preview" alt="Banner login atual">
                                                </div>
                                            <?php endif; ?>
                                            
                                            <img id="preview_tela_login" class="image-preview" style="display:none;" alt="Preview nova imagem">
                                            <div class="upload-info">Recomendado: 1920x1080px | Tela de login</div>
                                        </div>
                                    </div>

                                    <!-- Início -->
                                    <div class="col-md-4">
                                        <div class="form-file mb-3">
                                            <label class="form-label">
                                                <i class="ti ti-home me-2"></i>Imagem Início
                                            </label>
                                            <input type="file" class="form-control" name="inicio" 
                                                   accept="image/*" onchange="previewImage(this, 'preview_inicio')">
                                            
                                            <?php if (!empty($currentImages['inicio'])): ?>
                                                <div class="current-image-info">
                                                    <strong>Imagem atual:</strong> <?php echo htmlspecialchars($currentImages['inicio']); ?>
                                                    <br><img src="../assets/images/banners/<?php echo htmlspecialchars($currentImages['inicio']); ?>" 
                                                         class="image-preview" alt="Banner início atual">
                                                </div>
                                            <?php endif; ?>
                                            
                                            <img id="preview_inicio" class="image-preview" style="display:none;" alt="Preview nova imagem">
                                            <div class="upload-info">Recomendado: 1200x400px | Página inicial</div>
                                        </div>
                                    </div>

                                    <!-- Perfil -->
                                    <div class="col-md-4">
                                        <div class="form-file mb-3">
                                            <label class="form-label">
                                                <i class="ti ti-user me-2"></i>Imagem Perfil
                                            </label>
                                            <input type="file" class="form-control" name="tela_perfil" 
                                                   accept="image/*" onchange="previewImage(this, 'preview_tela_perfil')">
                                            
                                            <?php if (!empty($currentImages['tela_perfil'])): ?>
                                                <div class="current-image-info">
                                                    <strong>Imagem atual:</strong> <?php echo htmlspecialchars($currentImages['tela_perfil']); ?>
                                                    <br><img src="../assets/images/banners/<?php echo htmlspecialchars($currentImages['tela_perfil']); ?>" 
                                                         class="image-preview" alt="Banner perfil atual">
                                                </div>
                                            <?php endif; ?>
                                            
                                            <img id="preview_tela_perfil" class="image-preview" style="display:none;" alt="Preview nova imagem">
                                            <div class="upload-info">Recomendado: 1200x300px | Tela de perfil</div>
                                        </div>
                                    </div>

                                    <!-- Avatar -->
                                    <div class="col-md-4">
                                        <div class="form-file mb-3">
                                            <label class="form-label">
                                                <i class="ti ti-photo me-2"></i>Imagem Avatar Perfil
                                            </label>
                                            <input type="file" class="form-control" name="tela_avatar" 
                                                   accept="image/*" onchange="previewImage(this, 'preview_tela_avatar')">
                                            
                                            <?php if (!empty($currentImages['tela_avatar'])): ?>
                                                <div class="current-image-info">
                                                    <strong>Imagem atual:</strong> <?php echo htmlspecialchars($currentImages['tela_avatar']); ?>
                                                    <br><img src="../assets/images/banners/<?php echo htmlspecialchars($currentImages['tela_avatar']); ?>" 
                                                         class="image-preview" alt="Avatar padrão atual">
                                                </div>
                                            <?php endif; ?>
                                            
                                            <img id="preview_tela_avatar" class="image-preview" style="display:none;" alt="Preview nova imagem">
                                            <div class="upload-info">Recomendado: 150x150px | Avatar padrão</div>
                                        </div>
                                    </div>

                                    <!-- PIX -->
                                    <div class="col-md-4">
                                        <div class="form-file mb-3">
                                            <label class="form-label">
                                                <i class="ti ti-qrcode me-2"></i>Imagem PIX
                                            </label>
                                            <input type="file" class="form-control" name="tela_pix" 
                                                   accept="image/*" onchange="previewImage(this, 'preview_tela_pix')">
                                            
                                            <?php if (!empty($currentImages['tela_pix'])): ?>
                                                <div class="current-image-info">
                                                    <strong>Imagem atual:</strong> <?php echo htmlspecialchars($currentImages['tela_pix']); ?>
                                                    <br><img src="../assets/images/banners/<?php echo htmlspecialchars($currentImages['tela_pix']); ?>" 
                                                         class="image-preview" alt="Banner PIX atual">
                                                </div>
                                            <?php endif; ?>
                                            
                                            <img id="preview_tela_pix" class="image-preview" style="display:none;" alt="Preview nova imagem">
                                            <div class="upload-info">Recomendado: 800x400px | Tela PIX</div>
                                        </div>
                                    </div>

                                    <!-- Retirada -->
                                    <div class="col-md-4">
                                        <div class="form-file mb-3">
                                            <label class="form-label">
                                                <i class="ti ti-cash me-2"></i>Imagem Retirada
                                            </label>
                                            <input type="file" class="form-control" name="tela_retirada" 
                                                   accept="image/*" onchange="previewImage(this, 'preview_tela_retirada')">
                                            
                                            <?php if (!empty($currentImages['tela_retirada'])): ?>
                                                <div class="current-image-info">
                                                    <strong>Imagem atual:</strong> <?php echo htmlspecialchars($currentImages['tela_retirada']); ?>
                                                    <br><img src="../assets/images/banners/<?php echo htmlspecialchars($currentImages['tela_retirada']); ?>" 
                                                         class="image-preview" alt="Banner retirada atual">
                                                </div>
                                            <?php endif; ?>
                                            
                                            <img id="preview_tela_retirada" class="image-preview" style="display:none;" alt="Preview nova imagem">
                                            <div class="upload-info">Recomendado: 800x400px | Tela de retirada</div>
                                        </div>
                                    </div>

                                    <!-- Bônus -->
                                    <div class="col-md-4">
                                        <div class="form-file mb-3">
                                            <label class="form-label">
                                                <i class="ti ti-gift me-2"></i>Imagem Código Bônus
                                            </label>
                                            <input type="file" class="form-control" name="tela_bonus" 
                                                   accept="image/*" onchange="previewImage(this, 'preview_tela_bonus')">
                                            
                                            <?php if (!empty($currentImages['tela_bonus'])): ?>
                                                <div class="current-image-info">
                                                    <strong>Imagem atual:</strong> <?php echo htmlspecialchars($currentImages['tela_bonus']); ?>
                                                    <br><img src="../assets/images/banners/<?php echo htmlspecialchars($currentImages['tela_bonus']); ?>" 
                                                         class="image-preview" alt="Banner bônus atual">
                                                </div>
                                            <?php endif; ?>
                                            
                                            <img id="preview_tela_bonus" class="image-preview" style="display:none;" alt="Preview nova imagem">
                                            <div class="upload-info">Recomendado: 800x400px | Tela de bônus</div>
                                        </div>
                                    </div>

                                    <!-- Checklist -->
                                    <div class="col-md-4">
                                        <div class="form-file mb-3">
                                            <label class="form-label">
                                                <i class="ti ti-checklist me-2"></i>Imagem Checklist
                                            </label>
                                            <input type="file" class="form-control" name="checklist_image" 
                                                   accept="image/*" onchange="previewImage(this, 'preview_checklist_image')">
                                            
                                            <?php if (!empty($currentImages['checklist_image'])): ?>
                                                <div class="current-image-info">
                                                    <strong>Imagem atual:</strong> <?php echo htmlspecialchars($currentImages['checklist_image']); ?>
                                                    <br><img src="../assets/images/banners/<?php echo htmlspecialchars($currentImages['checklist_image']); ?>" 
                                                         class="image-preview" alt="Banner checklist atual">
                                                </div>
                                            <?php endif; ?>
                                            
                                            <img id="preview_checklist_image" class="image-preview" style="display:none;" alt="Preview nova imagem">
                                            <div class="upload-info">Recomendado: 600x300px | Imagem checklist</div>
                                        </div>
                                    </div>

                                </div>
                                
                                <!-- Botões de Ação -->
                                <div class="row">
                                    <div class="col-12">
                                        <hr class="my-4">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <button type="submit" class="btn btn-primary btn-lg me-3" id="submitBtn">
                                                    <i class="ti ti-upload me-2"></i>Salvar Alterações
                                                </button>
                                                <button type="button" class="btn btn-secondary" onclick="clearAllPreviews()">
                                                    <i class="ti ti-refresh me-2"></i>Limpar Formulário
                                                </button>
                                            </div>
                                            <div class="text-muted">
                                                <small>
                                                    <i class="ti ti-info-circle me-1"></i>
                                                    Apenas os campos selecionados serão atualizados
                                                </small>
                                            </div>
                                        </div>
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
                    <p class="m-0">Feito com muito &#9829; por <a href="https://t.me/devcorr3" target="_blank">Correa</a></p>
                </div>
                <div class="col-auto my-1">
                    <ul class="list-inline footer-link mb-0">
                        <li class="list-inline-item"><a href="../../inicio">Inicio</a></li>
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
                </ul>
            </div>
        </div>
    </div>

    <!-- Required JS -->
    <script src="../assets/js/plugins/popper.min.js"></script>
    <script src="../assets/js/plugins/simplebar.min.js"></script>
    <script src="../assets/js/plugins/bootstrap.min.js"></script>
    <script src="../assets/js/fonts/custom-font.js"></script>
    <script src="../assets/js/pcoded.js"></script>
    <script src="../assets/js/plugins/feather.min.js"></script>

    <!-- Theme Config -->
    <script>
        layout_change('dark');
        layout_theme_contrast_change('false');
        change_box_container('false');
        layout_caption_change('true');
        layout_rtl_change('false');
        preset_change('preset-1');
        main_layout_change('vertical');
    </script>

    <!-- Custom Scripts -->
    <script>
        // Função para limpar previews
        function clearAllPreviews() {
            document.querySelectorAll('.image-preview').forEach(preview => {
                if (preview.id.startsWith('preview_')) {
                    preview.style.display = 'none';
                    preview.src = '';
                }
            });
            document.querySelectorAll('input[type="file"]').forEach(input => {
                input.value = '';
                // Remover classes de validação
                const label = input.closest('.form-file').querySelector('label');
                label.classList.remove('text-success', 'text-danger');
            });
            updateFileCounter();
        }

        // Drag and drop funcionalidade
        document.querySelectorAll('.form-file').forEach(fileArea => {
            const input = fileArea.querySelector('input[type="file"]');
            
            // Efeitos visuais para drag and drop
            fileArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                fileArea.style.backgroundColor = '#f8f9fa';
                fileArea.style.borderColor = '#007bff';
                fileArea.style.transform = 'scale(1.02)';
                fileArea.style.transition = 'all 0.2s ease';
            });
            
            fileArea.addEventListener('dragleave', (e) => {
                e.preventDefault();
                fileArea.style.backgroundColor = '';
                fileArea.style.borderColor = '';
                fileArea.style.transform = '';
            });
            
            fileArea.addEventListener('drop', (e) => {
                e.preventDefault();
                fileArea.style.backgroundColor = '';
                fileArea.style.borderColor = '';
                fileArea.style.transform = '';
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    input.files = files;
                    input.dispatchEvent(new Event('change'));
                }
            });
        });

        // Mostrar progresso de upload detalhado
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            
            let progress = 0;
            const steps = [
                'Validando arquivos...',
                'Processando imagens...',
                'Fazendo backup...',
                'Salvando no servidor...',
                'Atualizando banco de dados...',
                'Finalizando...'
            ];
            let currentStep = 0;
            
            const progressInterval = setInterval(() => {
                progress += Math.random() * 10;
                if (progress > 95) progress = 95;
                
                if (progress > (currentStep + 1) * 15 && currentStep < steps.length - 1) {
                    currentStep++;
                }
                
                submitBtn.innerHTML = `<i class="ti ti-loader-2 me-2"></i>${steps[currentStep]} ${Math.round(progress)}%`;
            }, 300);
            
            // Limpar interval quando a página recarregar
            window.addEventListener('beforeunload', () => {
                clearInterval(progressInterval);
            });
        });

        // Validação em tempo real melhorada
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function() {
                const label = this.closest('.form-file').querySelector('label');
                const uploadInfo = this.closest('.form-file').querySelector('.upload-info');
                
                // Remover classes anteriores
                label.classList.remove('text-success', 'text-danger');
                
                if (this.files.length > 0) {
                    const file = this.files[0];
                    
                    // Validação detalhada
                    const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                    const maxSize = 5 * 1024 * 1024; // 5MB
                    
                    let isValid = true;
                    let errorMessage = '';
                    
                    if (!validTypes.includes(file.type)) {
                        isValid = false;
                        errorMessage = 'Tipo de arquivo inválido';
                    } else if (file.size > maxSize) {
                        isValid = false;
                        errorMessage = 'Arquivo muito grande (máx. 5MB)';
                    } else if (file.size === 0) {
                        isValid = false;
                        errorMessage = 'Arquivo vazio';
                    }
                    
                    if (!isValid) {
                        label.classList.add('text-danger');
                        uploadInfo.innerHTML = `<span class="text-danger">${errorMessage}</span>`;
                        this.value = '';
                    } else {
                        label.classList.add('text-success');
                        const fileSizeMB = (file.size / 1024 / 1024).toFixed(2);
                        uploadInfo.innerHTML = `<span class="text-success">Arquivo válido (${fileSizeMB} MB)</span>`;
                    }
                } else {
                    // Restaurar texto original
                    uploadInfo.innerHTML = uploadInfo.getAttribute('data-original') || uploadInfo.innerHTML;
                }
                
                updateFileCounter();
            });
        });

        // Salvar textos originais dos upload-info
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.upload-info').forEach(info => {
                info.setAttribute('data-original', info.innerHTML);
            });
        });

        // Animação de sucesso
        function showSuccessAnimation() {
            const card = document.querySelector('.card');
            card.style.transform = 'scale(1.02)';
            card.style.boxShadow = '0 10px 30px rgba(76, 175, 80, 0.3)';
            card.style.transition = 'all 0.3s ease';
            
            setTimeout(() => {
                card.style.transform = '';
                card.style.boxShadow = '';
            }, 500);
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + Enter para submeter
            if (e.ctrlKey && e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('uploadForm').dispatchEvent(new Event('submit'));
            }
            
            // Ctrl + R para limpar
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                clearAllPreviews();
            }
        });

        // Tooltip para atalhos
        document.addEventListener('DOMContentLoaded', function() {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.title = 'Ctrl + Enter para enviar rapidamente';
            
            const clearBtn = document.querySelector('.btn-secondary');
            if (clearBtn) {
                clearBtn.title = 'Ctrl + R para limpar rapidamente';
            }
        });
    </script>

</body>
</html>