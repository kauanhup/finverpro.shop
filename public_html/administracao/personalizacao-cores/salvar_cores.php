<?php
session_start(); // Inicia a sessão


// Inclui a conexão com o banco de dados
require '../bank/db.php';

try {
    // Conecta ao banco de dados
    $conn = getDBConnection();

    // Verifica se os dados foram enviados via POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Obtém as cores do formulário (verifique se os nomes coincidem com o HTML)
        $cor1 = $_POST['cor_1'] ?? '#FFFFFF';
        $cor2 = $_POST['cor_2'] ?? '#FFFFFF';
        $cor3 = $_POST['cor_3'] ?? '#FFFFFF';
        $cor4 = $_POST['cor_4'] ?? '#FFFFFF';
        $cor5 = $_POST['cor_5'] ?? '#FFFFFF';

        // Atualiza as cores no banco de dados
        $stmt = $conn->prepare("
            UPDATE personalizar_cores 
            SET cor_1 = ?, cor_2 = ?, cor_3 = ?, cor_4 = ?, cor_5 = ?
            LIMIT 1
        ");

        // Executa a query com os valores enviados
        $stmt->execute([$cor1, $cor2, $cor3, $cor4, $cor5]);

        // Retorna uma mensagem de sucesso
        echo json_encode(['success' => true, 'message' => 'Cores salvas com sucesso!']);
    } else {
        throw new Exception('Método inválido');
    }
} catch (Exception $e) {
    // Retorna uma mensagem de erro
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
}
