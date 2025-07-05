<?php
session_start();
header('Content-Type: application/json');

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Verificar se arquivo foi enviado
if (!isset($_FILES['foto'])) {
    echo json_encode(['success' => false, 'message' => 'Nenhum arquivo enviado']);
    exit;
}

$file = $_FILES['foto'];
$user_id = $_SESSION['user_id'];

// Validações
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
$allowed_extensions = ['jpg', 'jpeg', 'png'];
$max_size = 5 * 1024 * 1024; // 5MB

// Verificar tipo MIME
if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Formato de arquivo não permitido. Use JPG ou PNG.']);
    exit;
}

// Verificar extensão do arquivo
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($file_extension, $allowed_extensions)) {
    echo json_encode(['success' => false, 'message' => 'Extensão de arquivo não permitida.']);
    exit;
}

// Verificar tamanho
if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'Arquivo muito grande. Máximo permitido: 5MB']);
    exit;
}

// Verificar se houve erro no upload
if ($file['error'] !== UPLOAD_ERR_OK) {
    $error_messages = [
        UPLOAD_ERR_INI_SIZE => 'Arquivo excede o tamanho máximo permitido pelo servidor',
        UPLOAD_ERR_FORM_SIZE => 'Arquivo excede o tamanho máximo permitido',
        UPLOAD_ERR_PARTIAL => 'Upload foi interrompido',
        UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado',
        UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária não encontrada',
        UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar arquivo no disco',
        UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão'
    ];
    
    $message = $error_messages[$file['error']] ?? 'Erro desconhecido no upload';
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

try {
    // Incluir conexão
    require '../../bank/db.php';
    $pdo = getDBConnection();
    
    // Criar diretório se não existir
    $upload_dir = '../../uploads/perfil/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            echo json_encode(['success' => false, 'message' => 'Erro ao criar diretório de upload']);
            exit;
        }
    }
    
    // Verificar permissões do diretório
    if (!is_writable($upload_dir)) {
        echo json_encode(['success' => false, 'message' => 'Diretório de upload sem permissão de escrita']);
        exit;
    }
    
    // Buscar foto atual para deletar depois
    $stmt = $pdo->prepare("SELECT foto_perfil FROM usuarios WHERE id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $current_photo = $stmt->fetchColumn();
    
    // Gerar nome único para o arquivo
    $extension = $file_extension;
    $filename = 'user_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    // Verificar se é realmente uma imagem (segurança extra)
    $image_info = getimagesize($file['tmp_name']);
    if ($image_info === false) {
        echo json_encode(['success' => false, 'message' => 'Arquivo não é uma imagem válida']);
        exit;
    }
    
    // Mover arquivo para diretório
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar arquivo no servidor']);
        exit;
    }
    
    // Redimensionar imagem (se GD estiver disponível)
    if (extension_loaded('gd')) {
        $resize_result = resizeImage($filepath, $filepath, 400, 400);
        if (!$resize_result) {
            // Se falhou o resize, continua com a imagem original
            error_log("Falha ao redimensionar imagem: " . $filepath);
        }
    }
    
    // Atualizar banco de dados
    $stmt = $pdo->prepare("UPDATE usuarios SET foto_perfil = :filename WHERE id = :user_id");
    $update_result = $stmt->execute([
        'filename' => $filename,
        'user_id' => $user_id
    ]);
    
    if (!$update_result) {
        // Se falhou a atualização do BD, deletar arquivo
        unlink($filepath);
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar banco de dados']);
        exit;
    }
    
    // Deletar foto anterior se existir
    if ($current_photo && $current_photo !== $filename) {
        $old_file_path = $upload_dir . $current_photo;
        if (file_exists($old_file_path)) {
            unlink($old_file_path);
        }
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Foto atualizada com sucesso!',
        'filename' => $filename
    ]);
    
} catch (Exception $e) {
    // Se deu erro, deletar arquivo se foi criado
    if (isset($filepath) && file_exists($filepath)) {
        unlink($filepath);
    }
    
    error_log("Erro no upload de foto - User ID: $user_id - Erro: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor. Tente novamente.']);
}

// Função para redimensionar imagem
function resizeImage($source, $destination, $max_width, $max_height, $quality = 85) {
    try {
        $info = getimagesize($source);
        if ($info === false) {
            return false;
        }
        
        $mime = $info['mime'];
        
        // Criar imagem a partir do arquivo
        switch ($mime) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($source);
                break;
            case 'image/png':
                $image = imagecreatefrompng($source);
                break;
            default:
                return false;
        }
        
        if ($image === false) {
            return false;
        }
        
        $original_width = imagesx($image);
        $original_height = imagesy($image);
        
        // Calcular novas dimensões mantendo proporção
        $ratio = min($max_width / $original_width, $max_height / $original_height);
        
        // Se a imagem já é menor que o máximo, não redimensionar
        if ($ratio >= 1) {
            imagedestroy($image);
            return true;
        }
        
        $new_width = round($original_width * $ratio);
        $new_height = round($original_height * $ratio);
        
        // Criar nova imagem
        $new_image = imagecreatetruecolor($new_width, $new_height);
        
        if ($new_image === false) {
            imagedestroy($image);
            return false;
        }
        
        // Para PNG com transparência
        if ($mime == 'image/png') {
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
            $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
            imagefill($new_image, 0, 0, $transparent);
        }
        
        // Redimensionar
        $resize_result = imagecopyresampled(
            $new_image, $image, 
            0, 0, 0, 0, 
            $new_width, $new_height, 
            $original_width, $original_height
        );
        
        if (!$resize_result) {
            imagedestroy($image);
            imagedestroy($new_image);
            return false;
        }
        
        // Salvar
        $save_result = false;
        switch ($mime) {
            case 'image/jpeg':
                $save_result = imagejpeg($new_image, $destination, $quality);
                break;
            case 'image/png':
                // PNG quality é de 0-9, converter de 0-100
                $png_quality = round((100 - $quality) / 10);
                $save_result = imagepng($new_image, $destination, $png_quality);
                break;
        }
        
        imagedestroy($image);
        imagedestroy($new_image);
        
        return $save_result;
        
    } catch (Exception $e) {
        error_log("Erro ao redimensionar imagem: " . $e->getMessage());
        return false;
    }
}
?>