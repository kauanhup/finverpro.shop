<?php
session_start();
header('Content-Type: application/json');

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit();
}

require '../../bank/db.php';

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

    switch ($acao) {
        case 'atualizar':
            if (!isset($input['id']) || !isset($input['percentual'])) {
                echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
                exit();
            }
            
            $id = intval($input['id']);
            $percentual = floatval($input['percentual']);
            $descricao = trim($input['descricao']) ?? '';
            
            // Validações
            if ($percentual < 0 || $percentual > 50) {
                echo json_encode(['success' => false, 'message' => 'Percentual deve estar entre 0% e 50%']);
                exit();
            }
            
            // Verificar se o registro existe
            $check_sql = "SELECT nivel FROM configuracao_comissoes WHERE id = :id";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $check_stmt->execute();
            $registro = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$registro) {
                echo json_encode(['success' => false, 'message' => 'Configuração não encontrada']);
                exit();
            }
            
            // Atualizar
            $sql = "UPDATE configuracao_comissoes SET 
                    percentual = :percentual, 
                    descricao = :descricao,
                    data_atualizacao = CURRENT_TIMESTAMP 
                    WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':percentual', $percentual);
            $stmt->bindParam(':descricao', $descricao);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true, 
                    'message' => "Nível {$registro['nivel']} atualizado com sucesso! Percentual: {$percentual}%"
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao atualizar configuração']);
            }
            break;

        case 'alterar_status':
            if (!isset($input['id']) || !isset($input['ativo'])) {
                echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
                exit();
            }
            
            $id = intval($input['id']);
            $ativo = $input['ativo'] ? 1 : 0;
            
            // Verificar se existe
            $check_sql = "SELECT nivel FROM configuracao_comissoes WHERE id = :id";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $check_stmt->execute();
            $registro = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$registro) {
                echo json_encode(['success' => false, 'message' => 'Configuração não encontrada']);
                exit();
            }
            
            // Atualizar status
            $sql = "UPDATE configuracao_comissoes SET 
                    ativo = :ativo,
                    data_atualizacao = CURRENT_TIMESTAMP 
                    WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':ativo', $ativo, PDO::PARAM_INT);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $status_texto = $ativo ? 'ativado' : 'desativado';
                echo json_encode([
                    'success' => true, 
                    'message' => "Nível {$registro['nivel']} foi {$status_texto} com sucesso"
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao alterar status']);
            }
            break;

        case 'adicionar':
            if (!isset($input['nivel']) || !isset($input['percentual'])) {
                echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
                exit();
            }
            
            $nivel = intval($input['nivel']);
            $percentual = floatval($input['percentual']);
            $descricao = trim($input['descricao']) ?? "Comissão Nível {$nivel}";
            
            // Validações
            if ($nivel <= 0 || $nivel > 10) {
                echo json_encode(['success' => false, 'message' => 'Nível deve estar entre 1 e 10']);
                exit();
            }
            
            if ($percentual < 0 || $percentual > 50) {
                echo json_encode(['success' => false, 'message' => 'Percentual deve estar entre 0% e 50%']);
                exit();
            }
            
            // Verificar se o nível já existe
            $check_sql = "SELECT id FROM configuracao_comissoes WHERE nivel = :nivel";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bindParam(':nivel', $nivel, PDO::PARAM_INT);
            $check_stmt->execute();
            
            if ($check_stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => "Nível {$nivel} já existe"]);
                exit();
            }
            
            // Inserir novo nível
            $sql = "INSERT INTO configuracao_comissoes (nivel, percentual, descricao, ativo) 
                    VALUES (:nivel, :percentual, :descricao, 1)";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':nivel', $nivel, PDO::PARAM_INT);
            $stmt->bindParam(':percentual', $percentual);
            $stmt->bindParam(':descricao', $descricao);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true, 
                    'message' => "Nível {$nivel} adicionado com sucesso! Percentual: {$percentual}%"
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao adicionar nível']);
            }
            break;

        case 'excluir':
            if (!isset($input['id'])) {
                echo json_encode(['success' => false, 'message' => 'ID não especificado']);
                exit();
            }
            
            $id = intval($input['id']);
            
            // Verificar se existe
            $check_sql = "SELECT nivel FROM configuracao_comissoes WHERE id = :id";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $check_stmt->execute();
            $registro = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$registro) {
                echo json_encode(['success' => false, 'message' => 'Configuração não encontrada']);
                exit();
            }
            
            // Verificar se existem comissões já processadas neste nível
            try {
                $comissoes_sql = "SELECT COUNT(*) as total FROM comissoes WHERE nivel = :nivel";
                $comissoes_stmt = $conn->prepare($comissoes_sql);
                $comissoes_stmt->bindParam(':nivel', $registro['nivel'], PDO::PARAM_INT);
                $comissoes_stmt->execute();
                $result = $comissoes_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result && $result['total'] > 0) {
                    echo json_encode([
                        'success' => false, 
                        'message' => "Não é possível excluir o Nível {$registro['nivel']} pois existem {$result['total']} comissão(ões) já processada(s)"
                    ]);
                    exit();
                }
            } catch (Exception $e) {
                // Se tabela comissoes não existir, continuar
            }
            
            // Excluir
            $sql = "DELETE FROM configuracao_comissoes WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true, 
                    'message' => "Nível {$registro['nivel']} excluído com sucesso"
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao excluir nível']);
            }
            break;

        case 'ativar_todos':
            $sql = "UPDATE configuracao_comissoes SET 
                    ativo = 1,
                    data_atualizacao = CURRENT_TIMESTAMP";
            $stmt = $conn->prepare($sql);
            
            if ($stmt->execute()) {
                $count = $stmt->rowCount();
                echo json_encode([
                    'success' => true, 
                    'message' => "{$count} níveis foram ativados com sucesso"
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao ativar níveis']);
            }
            break;

        case 'desativar_todos':
            $sql = "UPDATE configuracao_comissoes SET 
                    ativo = 0,
                    data_atualizacao = CURRENT_TIMESTAMP";
            $stmt = $conn->prepare($sql);
            
            if ($stmt->execute()) {
                $count = $stmt->rowCount();
                echo json_encode([
                    'success' => true, 
                    'message' => "{$count} níveis foram desativados com sucesso"
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao desativar níveis']);
            }
            break;

        case 'restaurar_padrao':
            // Iniciar transação
            $conn->beginTransaction();
            
            try {
                // Limpar configurações existentes
                $conn->exec("DELETE FROM configuracao_comissoes");
                
                // Inserir configurações padrão
                $configuracoes_padrao = [
                    ['nivel' => 1, 'percentual' => 5.00, 'descricao' => 'Comissão Nível 1 - Indicação Direta'],
                    ['nivel' => 2, 'percentual' => 3.00, 'descricao' => 'Comissão Nível 2 - Segundo Nível'],
                    ['nivel' => 3, 'percentual' => 2.00, 'descricao' => 'Comissão Nível 3 - Terceiro Nível']
                ];
                
                $sql = "INSERT INTO configuracao_comissoes (nivel, percentual, descricao, ativo) VALUES (:nivel, :percentual, :descricao, 1)";
                $stmt = $conn->prepare($sql);
                
                foreach ($configuracoes_padrao as $config) {
                    $stmt->bindParam(':nivel', $config['nivel'], PDO::PARAM_INT);
                    $stmt->bindParam(':percentual', $config['percentual']);
                    $stmt->bindParam(':descricao', $config['descricao']);
                    $stmt->execute();
                }
                
                $conn->commit();
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Configurações restauradas para padrão: Nível 1 (5%), Nível 2 (3%), Nível 3 (2%)'
                ]);
                
            } catch (Exception $e) {
                $conn->rollBack();
                echo json_encode(['success' => false, 'message' => 'Erro ao restaurar configurações: ' . $e->getMessage()]);
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
?>