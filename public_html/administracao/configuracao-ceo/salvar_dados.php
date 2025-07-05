<?php
session_start();

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = $_POST['titulo'] ?? '';
    $descricao = $_POST['descricao'] ?? '';
    $keywords = $_POST['keywords'] ?? '';
    $link = $_POST['link'] ?? '';

    $sqlUpdate = "UPDATE configurar_textos SET 
                  titulo_site = :titulo, 
                  descricao_site = :descricao, 
                  keywords_site = :keywords, 
                  link_site = :link";
    $stmtUpdate = $conn->prepare($sqlUpdate);

    $stmtUpdate->bindParam(':titulo', $titulo, PDO::PARAM_STR);
    $stmtUpdate->bindParam(':descricao', $descricao, PDO::PARAM_STR);
    $stmtUpdate->bindParam(':keywords', $keywords, PDO::PARAM_STR);
    $stmtUpdate->bindParam(':link', $link, PDO::PARAM_STR);

    if ($stmtUpdate->execute()) {
        $_SESSION['status_message'] = ['success', 'Dados atualizados com sucesso!'];
    } else {
        $_SESSION['status_message'] = ['error', 'Erro ao atualizar os dados!'];
    }

    // Redireciona de volta para o index.php
    header('Location: ./');
    exit();
}
?>
