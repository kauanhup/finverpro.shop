<?php
// Incluir o arquivo de conexão com o banco de dados
require '../bank/db.php';

// Criar a conexão
$conn = getDBConnection(); // Chama a função para obter a conexão

// Se a conexão não for estabelecida, o erro já será tratado no db.php
if (!$conn) {
    die("A conexão com o banco de dados falhou.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obter os dados do formulário
    $titulo = $_POST['titulo'];
    $descricao = $_POST['descricao'];
    $valor_investimento = $_POST['valor_investimento'];
    $renda_diaria = $_POST['renda_diaria'];
    $validade = $_POST['validade'];

    // Calcular a receita total
    $receita_total = $renda_diaria * $validade;

    // Upload da imagem
    $foto = $_FILES['foto']['name'];
    $target_dir = "../../assets/images/produtos/";
    $target_file = $target_dir . basename($foto);

    if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_file)) {
        $sql = "INSERT INTO produtos (titulo, descricao, foto, valor_investimento, renda_diaria, validade, receita_total) 
                VALUES (:titulo, :descricao, :foto, :valor_investimento, :renda_diaria, :validade, :receita_total)";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':titulo', $titulo);
        $stmt->bindParam(':descricao', $descricao);
        $stmt->bindParam(':foto', $foto);
        $stmt->bindParam(':valor_investimento', $valor_investimento);
        $stmt->bindParam(':renda_diaria', $renda_diaria);
        $stmt->bindParam(':validade', $validade);
        $stmt->bindParam(':receita_total', $receita_total);

        if ($stmt->execute()) {
            header("Location: ./criar.html?status=success&message=Produto adicionado com sucesso!");
        } else {
            header("Location: ./criar.html?status=error&message=Erro ao adicionar produto: " . $stmt->errorInfo()[2]);
        }
    } else {
        header("Location: ./criar.html?status=error&message=Erro ao fazer upload da imagem.");
    }

    $conn = null;
    exit();
}
