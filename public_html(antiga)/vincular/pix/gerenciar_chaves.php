<?php
session_start();
header('Content-Type: application/json');

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit();
}

require '../../bank/db.php';

try {
    // Recebe os dados JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['acao'])) {
        echo json_encode(['success' => false, 'message' => 'Ação não especificada']);
        exit();
    }
    
    $userId = $_SESSION['user_id'];
    $acao = $input['acao'];
    $pdo = getDBConnection();
    
    switch ($acao) {
        case 'adicionar':
            // Validar dados
            $tipoPix = $input['tipo_pix'] ?? '';
            $nomeTitular = $input['nome_titular'] ?? '';
            $chavePix = $input['chave_pix'] ?? '';
            $apelido = $input['apelido'] ?? null;
            
            if (empty($tipoPix) || empty($nomeTitular) || empty($chavePix)) {
                echo json_encode(['success' => false, 'message' => 'Todos os campos obrigatórios devem ser preenchidos']);
                exit();
            }
            
            // Verificar limite de 3 chaves
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM chaves_pix WHERE user_id = ? AND status = 'ativo'");
            $stmt->execute([$userId]);
            $totalChaves = $stmt->fetchColumn();
            
            if ($totalChaves >= 3) {
                echo json_encode(['success' => false, 'message' => 'Você já possui 3 chaves PIX. Remova uma para adicionar nova.']);
                exit();
            }
            
            // Verificar se a chave já existe
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM chaves_pix WHERE chave_pix = ? AND status = 'ativo'");
            $stmt->execute([$chavePix]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Esta chave PIX já está cadastrada']);
                exit();
            }
            
            // Verificar se é a primeira chave (será ativa por padrão)
            $primeiraChave = ($totalChaves == 0) ? 1 : 0;
            
            // Apelido vazio vira NULL
            if (empty($apelido)) {
                $apelido = null;
            }
            
            // Inserir nova chave
            $stmt = $pdo->prepare("INSERT INTO chaves_pix (user_id, tipo_pix, nome_titular, chave_pix, apelido, ativa, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'ativo', NOW(), NOW())");
            $stmt->execute([$userId, $tipoPix, $nomeTitular, $chavePix, $apelido, $primeiraChave]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Chave PIX cadastrada com sucesso',
                'primeira_chave' => ($primeiraChave == 1)
            ]);
            break;
            
        case 'ativar':
            $chaveId = $input['chave_id'] ?? 0;
            
            if (!$chaveId) {
                echo json_encode(['success' => false, 'message' => 'ID da chave não informado']);
                exit();
            }
            
            // Verificar se a chave pertence ao usuário
            $stmt = $pdo->prepare("SELECT id FROM chaves_pix WHERE id = ? AND user_id = ? AND status = 'ativo'");
            $stmt->execute([$chaveId, $userId]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Chave não encontrada']);
                exit();
            }
            
            // Desativar todas as chaves do usuário
            $stmt = $pdo->prepare("UPDATE chaves_pix SET ativa = 0 WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Ativar a chave selecionada
            $stmt = $pdo->prepare("UPDATE chaves_pix SET ativa = 1, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$chaveId]);
            
            echo json_encode(['success' => true, 'message' => 'Chave ativada com sucesso']);
            break;
            
        case 'remover':
            $chaveId = $input['chave_id'] ?? 0;
            
            if (!$chaveId) {
                echo json_encode(['success' => false, 'message' => 'ID da chave não informado']);
                exit();
            }
            
            // Verificar se a chave pertence ao usuário
            $stmt = $pdo->prepare("SELECT ativa FROM chaves_pix WHERE id = ? AND user_id = ? AND status = 'ativo'");
            $stmt->execute([$chaveId, $userId]);
            $chave = $stmt->fetch();
            
            if (!$chave) {
                echo json_encode(['success' => false, 'message' => 'Chave não encontrada']);
                exit();
            }
            
            // Remover a chave (marcar como inativo)
            $stmt = $pdo->prepare("UPDATE chaves_pix SET status = 'inativo', ativa = 0, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$chaveId]);
            
            // Se era a chave ativa, ativar outra automaticamente
            if ($chave['ativa']) {
                $stmt = $pdo->prepare("SELECT id FROM chaves_pix WHERE user_id = ? AND status = 'ativo' ORDER BY created_at ASC LIMIT 1");
                $stmt->execute([$userId]);
                $proximaChave = $stmt->fetch();
                
                if ($proximaChave) {
                    $stmt = $pdo->prepare("UPDATE chaves_pix SET ativa = 1 WHERE id = ?");
                    $stmt->execute([$proximaChave['id']]);
                }
            }
            
            echo json_encode(['success' => true, 'message' => 'Chave removida com sucesso']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Erro ao gerenciar chaves PIX: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor: ' . $e->getMessage()]);
}
?>