<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

require '../../bank/db.php';

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'adicionar':
            adicionarChave($pdo, $user_id);
            break;
            
        case 'ativar':
            ativarChave($pdo, $user_id);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }
} catch (Exception $e) {
    error_log("Erro ao gerenciar chave PIX: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}

function adicionarChave($pdo, $user_id) {
    $tipo_pix = trim($_POST['tipo_pix'] ?? '');
    $chave_pix = trim($_POST['chave_pix'] ?? '');
    $nome_titular = trim($_POST['nome_titular'] ?? '');
    $apelido = trim($_POST['apelido'] ?? '');
    
    // Validações
    if (empty($tipo_pix) || empty($chave_pix) || empty($nome_titular)) {
        echo json_encode(['success' => false, 'message' => 'Todos os campos obrigatórios devem ser preenchidos']);
        return;
    }
    
    $tipos_validos = ['cpf', 'email', 'celular', 'chave_aleatoria'];
    if (!in_array($tipo_pix, $tipos_validos)) {
        echo json_encode(['success' => false, 'message' => 'Tipo de chave PIX inválido']);
        return;
    }
    
    // Validar formato da chave baseado no tipo
    if (!validarFormatoChave($tipo_pix, $chave_pix)) {
        echo json_encode(['success' => false, 'message' => 'Formato da chave PIX inválido para o tipo selecionado']);
        return;
    }
    
    // CORREÇÃO: Verificar limite de chaves usando 'usuario_id'
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM chaves_pix WHERE usuario_id = ? AND status = 'ativo'");
    $stmt->execute([$user_id]);
    $total_chaves = $stmt->fetchColumn();
    
    if ($total_chaves >= 3) {
        echo json_encode(['success' => false, 'message' => 'Você já atingiu o limite máximo de 3 chaves PIX']);
        return;
    }
    
    // Verificar se a chave já existe
    $stmt = $pdo->prepare("SELECT id FROM chaves_pix WHERE chave = ? AND status = 'ativo'");
    $stmt->execute([$chave_pix]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Esta chave PIX já está cadastrada no sistema']);
        return;
    }
    
    // Determinar se será a primeira chave (fica ativa automaticamente)
    $primeira_chave = ($total_chaves == 0);
    
    // CORREÇÃO: Inserir nova chave usando 'usuario_id'
    $stmt = $pdo->prepare("INSERT INTO chaves_pix (usuario_id, tipo, chave, nome_titular, apelido, ativa, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'ativo', NOW(), NOW())");
    $result = $stmt->execute([
        $user_id,
        $tipo_pix,
        $chave_pix,
        $nome_titular,
        $apelido ?: null,
        $primeira_chave ? 1 : 0
    ]);
    
    if ($result) {
        $message = $primeira_chave ? 
            'Chave PIX adicionada e ativada com sucesso! Esta é sua chave principal para saques.' :
            'Chave PIX adicionada com sucesso! Para usá-la nos saques, ative-a.';
            
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'primeira_chave' => $primeira_chave
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar chave PIX']);
    }
}

function ativarChave($pdo, $user_id) {
    $chave_id = (int)($_POST['chave_id'] ?? 0);
    
    if ($chave_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID da chave inválido']);
        return;
    }
    
    // CORREÇÃO: Verificar se a chave pertence ao usuário usando 'usuario_id'
    $stmt = $pdo->prepare("SELECT id FROM chaves_pix WHERE id = ? AND usuario_id = ? AND status = 'ativo'");
    $stmt->execute([$chave_id, $user_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Chave PIX não encontrada ou não pertence a você']);
        return;
    }
    
    // Iniciar transação
    $pdo->beginTransaction();
    
    try {
        // CORREÇÃO: Desativar todas as chaves do usuário usando 'usuario_id'
        $stmt = $pdo->prepare("UPDATE chaves_pix SET ativa = 0 WHERE usuario_id = ?");
        $stmt->execute([$user_id]);
        
        // Ativar a chave selecionada
        $stmt = $pdo->prepare("UPDATE chaves_pix SET ativa = 1, updated_at = NOW() WHERE id = ? AND usuario_id = ?");
        $result = $stmt->execute([$chave_id, $user_id]);
        
        if ($result && $stmt->rowCount() > 0) {
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Chave ativada com sucesso!']);
        } else {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Erro ao ativar chave PIX']);
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function validarFormatoChave($tipo, $chave) {
    switch ($tipo) {
        case 'cpf':
            // Remove formatação e verifica se tem 11 dígitos
            $cpf = preg_replace('/[^0-9]/', '', $chave);
            return strlen($cpf) === 11 && is_numeric($cpf);
            
        case 'email':
            return filter_var($chave, FILTER_VALIDATE_EMAIL) !== false;
            
        case 'celular':
            // Remove formatação e verifica se começa com +55 e tem 13-14 dígitos total
            $celular = preg_replace('/[^0-9+]/', '', $chave);
            return preg_match('/^\+55\d{10,11}$/', $celular);
            
        case 'chave_aleatoria':
            // Chave aleatória deve ter pelo menos 32 caracteres
            return strlen($chave) >= 32;
            
        default:
            return false;
    }
}

function verificarChaveAtiva($pdo, $user_id) {
    // CORREÇÃO: Buscar chave ativa usando 'usuario_id'
    $stmt = $pdo->prepare("SELECT ativa FROM chaves_pix WHERE id = ? AND usuario_id = ? AND status = 'ativo'");
    $stmt->execute([$chave_id, $user_id]);
    $chave = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $chave && $chave['ativa'] == 1;
}

function removerChavesInativas($pdo, $user_id) {
    // Função para limpeza futura - remove chaves não ativas há mais de 30 dias
    // CORREÇÃO: Usar 'usuario_id'
    $stmt = $pdo->prepare("
        SELECT id FROM chaves_pix 
        WHERE usuario_id = ? AND ativa = 0 AND status = 'ativo' 
        AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY created_at ASC 
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $chave_antiga = $stmt->fetch();
    
    if ($chave_antiga) {
        $stmt = $pdo->prepare("UPDATE chaves_pix SET status = 'removida' WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$chave_antiga['id'], $user_id]);
        
        return true;
    }
    
    return false;
}
?>