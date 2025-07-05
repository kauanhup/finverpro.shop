<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../');
    exit();
}

require '../bank/db.php';

try {
    $conn = getDBConnection();

    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $sql = "DELETE FROM usuarios WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        // Redireciona de volta para a página anterior
        header('Location: ./'); // Altere para o caminho correto
        exit();
    }
} catch (Exception $e) {
    die("Erro ao excluir usuário: " . $e->getMessage());
}
?>
