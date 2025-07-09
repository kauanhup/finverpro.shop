<?php
session_start(); // Inicia a sessão

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../');
    exit();
}

// Incluir o arquivo de conexão com o banco de dados
require '../bank/db.php';

// Criar a conexão
try {
    $conn = getDBConnection(); // Chama a função para obter a conexão
} catch (Exception $e) {
    die("Erro de conexão: " . $e->getMessage()); // Mensagem de erro
}

// Verifica se os dados foram enviados via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtém os dados do formulário
    $id = $_POST['id'];
    $telefone = preg_replace('/[^0-9]/', '', $_POST['telefone']); // Remove tudo que não é número (+ - () espaços etc)
    $saldo = $_POST['saldo'];
    $saldo_comissao = $_POST['saldo_comissao'];

    // Atualiza os dados do usuário no banco de dados
    $sql = "UPDATE usuarios SET 
                telefone = :telefone, 
                saldo = :saldo, 
                saldo_comissao = :saldo_comissao 
            WHERE id = :id";

    $stmt = $conn->prepare($sql);

    // Bind dos parâmetros
    $stmt->bindParam(':telefone', $telefone);
    $stmt->bindParam(':saldo', $saldo);
    $stmt->bindParam(':saldo_comissao', $saldo_comissao);
    $stmt->bindParam(':id', $id);

    // Executa a consulta
    if ($stmt->execute()) {
        $_SESSION['message'] = 'Usuário atualizado com sucesso!';
        $_SESSION['message_type'] = 'success'; // Tipo da mensagem
    } else {
        $_SESSION['message'] = 'Erro ao atualizar usuário.';
        $_SESSION['message_type'] = 'error'; // Tipo da mensagem
    }
    
    // Redireciona de volta para editar.php
    header('Location: editar.php?id=' . $id);
    exit();
} else {
    // Redireciona se não for uma requisição POST
    header('Location: ./');
    exit();
}
?>