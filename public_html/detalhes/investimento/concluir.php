<?php 
require '../../bank/db.php'; 
$conn = getDBConnection(); 
session_start(); 

if (isset($_POST['product_id'])) { 
    $product_id = $_POST['product_id']; 
} else { 
    echo "ID do produto não fornecido."; 
    exit; 
} 

$sql = "SELECT * FROM produtos WHERE id = :id"; 
$stmt = $conn->prepare($sql); 
$stmt->bindParam(':id', $product_id, PDO::PARAM_INT); 
$stmt->execute(); 

if ($stmt->rowCount() > 0) {
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_id = $_SESSION['user_id'];

    // Verificar se o usuário já comprou o produto
    $sql_check = "SELECT * FROM investidores WHERE id_usuario = :user_id AND produto_investido = :product_id";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bindParam(':user_id', $user_id);
    $stmt_check->bindParam(':product_id', $product_id);
    $stmt_check->execute();

    try {
        if ($stmt_check->rowCount() > 0) {
            echo '<script>alert("Você já comprou esse produto!"); window.location.href = "index.php";</script>';
            exit;
        }

        $sql_user = "SELECT saldo, valor_investimento FROM usuarios WHERE id = :user_id";
        $stmt_user = $conn->prepare($sql_user);
        $stmt_user->bindParam(':user_id', $user_id);
        $stmt_user->execute();

        if ($stmt_user->rowCount() > 0) {
            $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
            $user_saldo = $user['saldo'];

            if ($user_saldo >= $product['valor_investimento']) {
                $new_saldo = $user_saldo - $product['valor_investimento'];
                $sql_update = "UPDATE usuarios SET saldo = :new_saldo WHERE id = :user_id";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bindParam(':new_saldo', $new_saldo);
                $stmt_update->bindParam(':user_id', $user_id);
                $stmt_update->execute();

                // Inserindo o id_usuario na tabela de investidores
                $sql_invest = "INSERT INTO investidores (id_usuario, numero_telefone, produto_investido) VALUES (:id_usuario, :numero_telefone, :produto_investido)";
                $stmt_invest = $conn->prepare($sql_invest);
                $numero_telefone = '123456789';
                $stmt_invest->bindParam(':id_usuario', $user_id, PDO::PARAM_INT);
                $stmt_invest->bindParam(':numero_telefone', $numero_telefone);
                $stmt_invest->bindParam(':produto_investido', $product['id']);
                $stmt_invest->execute();

                // Atualizando valor_investimento na tabela usuarios
                $new_valor_investimento = $user['valor_investimento'] + $product['valor_investimento'];
                $sql_update_investimento = "UPDATE usuarios SET valor_investimento = :new_valor_investimento WHERE id = :user_id";
                $stmt_update_investimento = $conn->prepare($sql_update_investimento);
                $stmt_update_investimento->bindParam(':new_valor_investimento', $new_valor_investimento);
                $stmt_update_investimento->bindParam(':user_id', $user_id);
                $stmt_update_investimento->execute();

                echo "sucesso";
            } else {
                echo "insuficiente";
            }
        } else {
            echo "Usuário não encontrado.";
        }
    } catch (Exception $e) {
        echo "Erro ao processar a compra: " . $e->getMessage();
        exit;
    }
} else {
    echo "Produto não encontrado.";
}

$conn = null; 
?>