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

$user_id = $_SESSION['user_id'];

try {
    // Incluir conexão
    require '../../bank/db.php';
    $pdo = getDBConnection();
    
    // Buscar foto atual do usuário
    $stmt = $pdo->prepare("SELECT foto_perfil FROM usuarios WHERE id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $current_photo = $stmt->fetchColumn();
    
    // Verificar se o usuário tem foto
    if (!$current_photo) {
        echo json_encode(['success' => false, 'message' => 'Usuário não possui foto de perfil']);
        exit;
    }
    
    // Iniciar transação para garantir consistência
    $pdo->beginTransaction();
    
    try {
        // Remover foto do banco de dados
        $stmt = $pdo->prepare("UPDATE usuarios SET foto_perfil = NULL WHERE id = :user_id");
        $update_result = $stmt->execute(['user_id' => $user_id]);
        
        if (!$update_result) {
            throw new Exception('Erro ao atualizar banco de dados');
        }
        
        // Verificar se a atualização foi bem-sucedida
        if ($stmt->rowCount() === 0) {
            throw new Exception('Nenhum registro foi atualizado');
        }
        
        // Caminho do arquivo
        $file_path = '../../uploads/perfil/' . $current_photo;
        
        // Tentar deletar o arquivo físico
        $file_deleted = false;
        if (file_exists($file_path)) {
            $file_deleted = unlink($file_path);
            
            if (!$file_deleted) {
                // Log do erro mas não falha a operação
                error_log("Aviso: Não foi possível deletar o arquivo físico: " . $file_path);
            }
        } else {
            // Arquivo não existe fisicamente, mas isso não é um erro crítico
            error_log("Aviso: Arquivo de foto não encontrado: " . $file_path);
            $file_deleted = true; // Considera como "deletado" pois não existe
        }
        
        // Confirmar transação
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Foto de perfil removida com sucesso!',
            'file_deleted' => $file_deleted
        ]);
        
    } catch (Exception $e) {
        // Reverter transação em caso de erro
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Erro de banco de dados ao remover foto - User ID: $user_id - Erro: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro de banco de dados. Tente novamente.']);
    
} catch (Exception $e) {
    error_log("Erro ao remover foto - User ID: $user_id - Erro: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>