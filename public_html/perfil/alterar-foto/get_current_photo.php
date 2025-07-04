<?php
session_start();
header('Content-Type: application/json');

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Incluir conexão
    require '../../bank/db.php';
    $pdo = getDBConnection();
    
    // Buscar foto atual do usuário
    $stmt = $pdo->prepare("SELECT foto_perfil FROM usuarios WHERE id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $current_photo = $stmt->fetchColumn();
    
    // Verificar se a consulta foi bem-sucedida
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
        exit;
    }
    
    // Se não tem foto
    if (!$current_photo) {
        echo json_encode([
            'success' => true, 
            'photo' => null,
            'has_photo' => false,
            'message' => 'Usuário não possui foto de perfil'
        ]);
        exit;
    }
    
    // Verificar se o arquivo existe fisicamente
    $file_path = '../../uploads/perfil/' . $current_photo;
    $file_exists = file_exists($file_path);
    
    // Se o arquivo não existe fisicamente, limpar do banco
    if (!$file_exists) {
        // Log do problema
        error_log("Foto de perfil não encontrada fisicamente - User ID: $user_id - Arquivo: $current_photo");
        
        // Limpar do banco de dados
        $stmt_clean = $pdo->prepare("UPDATE usuarios SET foto_perfil = NULL WHERE id = :user_id");
        $stmt_clean->execute(['user_id' => $user_id]);
        
        echo json_encode([
            'success' => true, 
            'photo' => null,
            'has_photo' => false,
            'message' => 'Foto não encontrada, registro limpo'
        ]);
        exit;
    }
    
    // Verificar tamanho do arquivo (opcional)
    $file_size = filesize($file_path);
    $file_modified = filemtime($file_path);
    
    // Retornar dados da foto
    echo json_encode([
        'success' => true, 
        'photo' => $current_photo,
        'has_photo' => true,
        'file_exists' => $file_exists,
        'file_size' => $file_size,
        'upload_date' => date('Y-m-d H:i:s', $file_modified),
        'photo_url' => '../uploads/perfil/' . $current_photo,
        'message' => 'Foto encontrada com sucesso'
    ]);
    
} catch (PDOException $e) {
    error_log("Erro de banco de dados ao buscar foto - User ID: $user_id - Erro: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro de banco de dados']);
    
} catch (Exception $e) {
    error_log("Erro ao buscar foto atual - User ID: $user_id - Erro: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>