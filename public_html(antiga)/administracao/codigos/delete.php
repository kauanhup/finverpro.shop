<?php
session_start(); // Inicia a sessão

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../');
    exit(); // Encerra o script
}

// Incluir o arquivo de conexão com o banco de dados
require '../bank/db.php';

try {
    $conn = getDBConnection(); // Chama a função para obter a conexão

    // Verifica se o ID foi passado
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        
        // Exclui o registro
        $sql = "DELETE FROM bonus WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        // Redireciona de volta após a exclusão
        header('Location: ./'); // Altere para a página que deseja redirecionar
        exit();
    }
} catch (Exception $e) {
    die("Erro ao excluir: " . $e->getMessage()); // Mensagem de erro
}
?>
