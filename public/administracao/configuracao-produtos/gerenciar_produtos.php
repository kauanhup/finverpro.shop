<?php
session_start();
header('Content-Type: application/json');

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit();
}

require '../bank/db.php';

try {
    $conn = getDBConnection();
    
    // Verificar se o usuário é admin
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT cargo FROM usuarios WHERE id = :user_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['cargo'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Acesso negado']);
        exit();
    }

    // Ler dados do POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['acao'])) {
        echo json_encode(['success' => false, 'message' => 'Ação não especificada']);
        exit();
    }

    $acao = $input['acao'];
    $id = isset($input['id']) ? (int)$input['id'] : 0;

    switch ($acao) {
        case 'alterar_status':
            if (!isset($input['status']) || !in_array($input['status'], ['ativo', 'arquivado', 'inativo'])) {
                echo json_encode(['success' => false, 'message' => 'Status inválido']);
                exit();
            }
            
            $status = $input['status'];
            
            // Verificar se o produto existe
            $check_sql = "SELECT id, titulo FROM produtos WHERE id = :id";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $check_stmt->execute();
            $produto = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$produto) {
                echo json_encode(['success' => false, 'message' => 'Produto não encontrado']);
                exit();
            }
            
            // Atualizar status
            $sql = "UPDATE produtos SET status = :status, data_atualizacao = CURRENT_TIMESTAMP WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $status_texto = [
                    'ativo' => 'ativado',
                    'arquivado' => 'arquivado', 
                    'inativo' => 'desativado'
                ];
                
                echo json_encode([
                    'success' => true, 
                    'message' => "Produto '{$produto['titulo']}' foi {$status_texto[$status]} com sucesso"
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao alterar status do produto']);
            }
            break;

        case 'excluir':
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID de produto inválido']);
                exit();
            }
            
            // Verificar se o produto existe
            $check_sql = "SELECT id, titulo FROM produtos WHERE id = :id";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $check_stmt->execute();
            $produto = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$produto) {
                echo json_encode(['success' => false, 'message' => 'Produto não encontrado']);
                exit();
            }
            
            // Verificar se existem investimentos ativos neste produto
            $investimentos_sql = "SELECT COUNT(*) as total FROM investimentos WHERE produto_id = :id";
            $investimentos_stmt = $conn->prepare($investimentos_sql);
            $investimentos_stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $investimentos_stmt->execute();
            $result = $investimentos_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['total'] > 0) {
                echo json_encode([
                    'success' => false, 
                    'message' => "Não é possível excluir o produto '{$produto['titulo']}' pois existem {$result['total']} investimento(s) ativo(s) vinculado(s) a ele"
                ]);
                exit();
            }
            
            // Excluir produto
            $sql = "DELETE FROM produtos WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true, 
                    'message' => "Produto '{$produto['titulo']}' foi excluído permanentemente"
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao excluir produto do banco de dados']);
            }
            break;

        case 'atualizar_vendidos':
            if (!isset($input['vendidos']) || $input['vendidos'] < 0) {
                echo json_encode(['success' => false, 'message' => 'Quantidade vendida deve ser um número positivo']);
                exit();
            }
            
            $vendidos = (int)$input['vendidos'];
            
            // Verificar se o produto existe e pegar limite de compras
            $check_sql = "SELECT id, titulo, limite_compras FROM produtos WHERE id = :id";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $check_stmt->execute();
            $produto = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$produto) {
                echo json_encode(['success' => false, 'message' => 'Produto não encontrado']);
                exit();
            }
            
            // Verificar se não excede o limite
            if ($vendidos > $produto['limite_compras']) {
                echo json_encode([
                    'success' => false, 
                    'message' => "Quantidade vendida ({$vendidos}) não pode ser maior que o limite de compras ({$produto['limite_compras']})"
                ]);
                exit();
            }
            
            // Atualizar vendidos
            $sql = "UPDATE produtos SET vendidos = :vendidos, data_atualizacao = CURRENT_TIMESTAMP WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':vendidos', $vendidos, PDO::PARAM_INT);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $restantes = $produto['limite_compras'] - $vendidos;
                echo json_encode([
                    'success' => true, 
                    'message' => "Quantidade vendida atualizada para {$vendidos}. Restam {$restantes} unidades disponíveis"
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao atualizar quantidade vendida']);
            }
            break;

        case 'obter_detalhes':
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID de produto inválido']);
                exit();
            }
            
            // Buscar detalhes completos do produto
            $sql = "SELECT 
                        *,
                        COALESCE(limite_compras, 100) as limite_compras_safe,
                        COALESCE(vendidos, 0) as vendidos_safe,
                        (COALESCE(limite_compras, 100) - COALESCE(vendidos, 0)) as restantes,
                        COALESCE(duracao_dias, 30) as duracao_dias_safe,
                        COALESCE(robot_number, CONCAT('R', id + 50)) as robot_number_safe,
                        COALESCE(tipo_rendimento, 'diario') as tipo_rendimento_safe,
                        COALESCE(status, 'ativo') as status_safe
                    FROM produtos 
                    WHERE id = :id";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $produto = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($produto) {
                echo json_encode(['success' => true, 'produto' => $produto]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Produto não encontrado']);
            }
            break;

        case 'duplicar':
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID de produto inválido']);
                exit();
            }
            
            // Buscar produto original
            $sql = "SELECT * FROM produtos WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $produto_original = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$produto_original) {
                echo json_encode(['success' => false, 'message' => 'Produto original não encontrado']);
                exit();
            }
            
            // Criar cópia com novo robot_number
            $novo_robot = 'R' . (time() % 9999); // Gerar número único baseado no timestamp
            $novo_titulo = $produto_original['titulo'] . ' (Cópia)';
            
            $insert_sql = "INSERT INTO produtos (
                titulo, descricao, foto, robot_number, valor_investimento, 
                renda_diaria, receita_total, duracao_dias, limite_compras, 
                limite_dias_venda, tipo_rendimento, vendidos, status, validade
            ) VALUES (
                :titulo, :descricao, :foto, :robot_number, :valor_investimento,
                :renda_diaria, :receita_total, :duracao_dias, :limite_compras,
                :limite_dias_venda, :tipo_rendimento, 0, 'inativo', :validade
            )";
            
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bindParam(':titulo', $novo_titulo);
            $insert_stmt->bindParam(':descricao', $produto_original['descricao']);
            $insert_stmt->bindParam(':foto', $produto_original['foto']);
            $insert_stmt->bindParam(':robot_number', $novo_robot);
            $insert_stmt->bindParam(':valor_investimento', $produto_original['valor_investimento']);
            $insert_stmt->bindParam(':renda_diaria', $produto_original['renda_diaria']);
            $insert_stmt->bindParam(':receita_total', $produto_original['receita_total']);
            $insert_stmt->bindParam(':duracao_dias', $produto_original['duracao_dias']);
            $insert_stmt->bindParam(':limite_compras', $produto_original['limite_compras']);
            $insert_stmt->bindParam(':limite_dias_venda', $produto_original['limite_dias_venda']);
            $insert_stmt->bindParam(':tipo_rendimento', $produto_original['tipo_rendimento']);
            $insert_stmt->bindParam(':validade', $produto_original['validade']);
            
            if ($insert_stmt->execute()) {
                $novo_id = $conn->lastInsertId();
                echo json_encode([
                    'success' => true, 
                    'message' => "Produto duplicado com sucesso! Novo ID: {$novo_id}",
                    'novo_id' => $novo_id
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao duplicar produto']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Ação não reconhecida: ' . $acao]);
            break;
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro de banco de dados: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}