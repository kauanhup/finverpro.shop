<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../');
    exit();
}

// Incluir o arquivo de conexão com o banco de dados
require '../bank/db.php';

try {
    // Criar a conexão
    $conn = getDBConnection();
    
    // Verificar se o usuário é admin
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT cargo FROM usuarios WHERE id = :user_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['cargo'] !== 'admin') {
        header('Location: ../dashboard/?status=error&message=Acesso negado');
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validar dados obrigatórios
        $campos_obrigatorios = ['titulo', 'valor_investimento', 'renda_diaria', 'validade'];
        $erros = [];
        
        foreach ($campos_obrigatorios as $campo) {
            if (empty($_POST[$campo])) {
                $erros[] = "O campo " . ucfirst(str_replace('_', ' ', $campo)) . " é obrigatório";
            }
        }
        
        if (!empty($erros)) {
            $message = implode(', ', $erros);
            header("Location: ./criar.html?status=error&message=" . urlencode($message));
            exit();
        }

        // Obter os dados do formulário
        $titulo = trim($_POST['titulo']);
        $descricao = trim($_POST['descricao']) ?? '';
        $valor_investimento = floatval($_POST['valor_investimento']);
        $renda_diaria = floatval($_POST['renda_diaria']);
        $validade = intval($_POST['validade']);
        
        // Novos campos opcionais
        $robot_number = trim($_POST['robot_number']) ?? null;
        $duracao_dias = intval($_POST['duracao_dias']) ?: 30;
        $limite_compras = intval($_POST['limite_compras']) ?: 100;
        $limite_dias_venda = !empty($_POST['limite_dias_venda']) ? intval($_POST['limite_dias_venda']) : null;
        $tipo_rendimento = $_POST['tipo_rendimento'] ?? 'diario';
        $status = $_POST['status'] ?? 'ativo';

        // Validações adicionais
        if ($valor_investimento <= 0) {
            header("Location: ./criar.html?status=error&message=" . urlencode("Valor de investimento deve ser maior que zero"));
            exit();
        }
        
        if ($renda_diaria < 0) {
            header("Location: ./criar.html?status=error&message=" . urlencode("Renda diária não pode ser negativa"));
            exit();
        }
        
        if ($validade <= 0) {
            header("Location: ./criar.html?status=error&message=" . urlencode("Validade deve ser maior que zero"));
            exit();
        }

        // Calcular a receita total
        if ($tipo_rendimento === 'diario') {
            $receita_total = $renda_diaria * $duracao_dias;
        } else {
            // Para tipo 'final', a receita total é o valor total pago no final
            $receita_total = $renda_diaria; // Neste caso, renda_diaria representa o valor final
        }

        // Gerar robot_number se não fornecido
        if (empty($robot_number)) {
            // Buscar o último ID para gerar um número único
            $stmt = $conn->query("SELECT MAX(id) as max_id FROM produtos");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $next_id = ($result['max_id'] ?? 0) + 1;
            $robot_number = 'R' . ($next_id + 50);
        } else {
            // Verificar se o robot_number já existe
            $stmt = $conn->prepare("SELECT id FROM produtos WHERE robot_number = :robot_number");
            $stmt->bindParam(':robot_number', $robot_number);
            $stmt->execute();
            if ($stmt->fetch()) {
                header("Location: ./criar.html?status=error&message=" . urlencode("Código do robô já existe: $robot_number"));
                exit();
            }
        }

        // Processar upload da imagem
        $foto = 'produto-default.jpg'; // Valor padrão
        
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = $_FILES['foto']['type'];
            $file_size = $_FILES['foto']['size'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file_type, $allowed_types)) {
                header("Location: ./criar.html?status=error&message=" . urlencode("Tipo de arquivo não permitido. Use: JPG, PNG, GIF ou WebP"));
                exit();
            }
            
            if ($file_size > $max_size) {
                header("Location: ./criar.html?status=error&message=" . urlencode("Arquivo muito grande. Máximo 5MB"));
                exit();
            }
            
            // Gerar nome único para o arquivo
            $file_extension = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $foto = 'produto_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
            
            $target_dir = "../assets/images/produtos/";
            
            // Criar diretório se não existir
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $target_file = $target_dir . $foto;
            
            if (!move_uploaded_file($_FILES['foto']['tmp_name'], $target_file)) {
                header("Location: ./criar.html?status=error&message=" . urlencode("Erro ao fazer upload da imagem"));
                exit();
            }
        }

        // Inserir produto no banco de dados
        $sql = "INSERT INTO produtos (
                    titulo, descricao, foto, robot_number, valor_investimento, 
                    renda_diaria, receita_total, duracao_dias, limite_compras, 
                    limite_dias_venda, tipo_rendimento, vendidos, status, validade
                ) VALUES (
                    :titulo, :descricao, :foto, :robot_number, :valor_investimento,
                    :renda_diaria, :receita_total, :duracao_dias, :limite_compras,
                    :limite_dias_venda, :tipo_rendimento, 0, :status, :validade
                )";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':titulo', $titulo);
        $stmt->bindParam(':descricao', $descricao);
        $stmt->bindParam(':foto', $foto);
        $stmt->bindParam(':robot_number', $robot_number);
        $stmt->bindParam(':valor_investimento', $valor_investimento);
        $stmt->bindParam(':renda_diaria', $renda_diaria);
        $stmt->bindParam(':receita_total', $receita_total);
        $stmt->bindParam(':duracao_dias', $duracao_dias);
        $stmt->bindParam(':limite_compras', $limite_compras);
        $stmt->bindParam(':limite_dias_venda', $limite_dias_venda);
        $stmt->bindParam(':tipo_rendimento', $tipo_rendimento);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':validade', $validade);

        if ($stmt->execute()) {
            $produto_id = $conn->lastInsertId();
            $success_message = "Produto '$titulo' (ID: $produto_id, Robô: $robot_number) adicionado com sucesso!";
            header("Location: ./criar.html?status=success&message=" . urlencode($success_message));
        } else {
            $error_info = $stmt->errorInfo();
            header("Location: ./criar.html?status=error&message=" . urlencode("Erro ao adicionar produto: " . $error_info[2]));
        }

    } else {
        // Se não for POST, redirecionar para a página de criação
        header("Location: ./criar.html");
    }

} catch (PDOException $e) {
    header("Location: ./criar.html?status=error&message=" . urlencode("Erro de banco de dados: " . $e->getMessage()));
} catch (Exception $e) {
    header("Location: ./criar.html?status=error&message=" . urlencode("Erro interno: " . $e->getMessage()));
} finally {
    // Fechar conexão
    $conn = null;
}

exit();
?>