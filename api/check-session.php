<?php
session_start();
header('Content-Type: application/json');

$response = array();

if (isset($_SESSION['user_id'])) {
    require_once '../bank/db.php';
    
    try {
        // Verificar se o usuário ainda existe e está ativo
        $stmt = $pdo->prepare("SELECT id, status FROM usuarios WHERE id = ? AND status = 'ativo'");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            $response['valid'] = true;
            $response['user_id'] = $user['id'];
        } else {
            $response['valid'] = false;
            $response['message'] = 'Usuário não encontrado ou inativo';
            session_destroy();
        }
    } catch (PDOException $e) {
        $response['valid'] = false;
        $response['message'] = 'Erro na verificação';
        error_log("Erro check-session: " . $e->getMessage());
    }
} else {
    $response['valid'] = false;
    $response['message'] = 'Sessão não encontrada';
}

echo json_encode($response);
?>
