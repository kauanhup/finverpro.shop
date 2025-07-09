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

    // Verifica se o formulário foi enviado
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Obtém os dados do formulário
        $codigo = $_POST['titulo']; // Corrigido para 'titulo'
        $quantidade = $_POST['descricao']; // Corrigido para 'descricao'
        $data_vencimento = $_POST['valor_investimento']; // Corrigido para 'valor_investimento'
        $saldo = $_POST['renda_diaria']; // Corrigido para 'renda_diaria'

        // Prepara a instrução SQL para inserir os dados
        $sql = "INSERT INTO bonus (codigo, qnt_usos, qnt_usados, data_vencimento, saldo, data_criacao) VALUES (:codigo, :quantidade, 0, :data_vencimento, :saldo, NOW())";
        $stmt = $conn->prepare($sql);

        // Vincula os parâmetros
        $stmt->bindParam(':codigo', $codigo);
        $stmt->bindParam(':quantidade', $quantidade);
        $stmt->bindParam(':data_vencimento', $data_vencimento);
        $stmt->bindParam(':saldo', $saldo);

        // Executa a consulta
        if ($stmt->execute()) {
            // Se a inserção for bem-sucedida, retorna um JSON de sucesso
            echo json_encode(['status' => 'success', 'message' => 'Código criado com sucesso!']);
        } else {
            // Se houver um erro, retorna um JSON de erro
            echo json_encode(['status' => 'error', 'message' => 'Erro ao adicionar o código.']);
        }
    }
} catch (Exception $e) {
    // Retorna um JSON de erro em caso de exceção
    echo json_encode(['status' => 'error', 'message' => 'Erro: ' . $e->getMessage()]);
}
?>
