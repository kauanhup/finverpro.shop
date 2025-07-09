<?php
session_start();
require_once '../bank/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    $pdo = getPDO();
    
    switch ($action) {
        case 'verificar_telefone':
            verificarTelefone($pdo);
            break;
            
        case 'verificar_codigo':
            verificarCodigo($pdo);
            break;
            
        case 'cadastrar_usuario':
            cadastrarUsuario($pdo);
            break;
            
        case 'verificar_convite':
            verificarConvite($pdo);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }
    
} catch (Exception $e) {
    error_log("Erro na autenticação: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}

function verificarTelefone($pdo) {
    $telefone = limparTelefone($_POST['telefone'] ?? '');
    
    if (empty($telefone) || strlen($telefone) < 10) {
        echo json_encode(['success' => false, 'message' => 'Telefone inválido']);
        return;
    }
    
    // Verificar se telefone já está cadastrado
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE telefone = ?");
    $stmt->execute([$telefone]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Este telefone já está cadastrado']);
        return;
    }
    
    // Simular envio de SMS (em produção, integrar com API de SMS)
    $codigo = mt_rand(100000, 999999);
    
    // Salvar código na sessão temporariamente
    $_SESSION['codigo_verificacao'] = $codigo;
    $_SESSION['telefone_verificacao'] = $telefone;
    $_SESSION['tempo_codigo'] = time();
    
    // Em produção, enviar SMS aqui
    error_log("Código SMS para {$telefone}: {$codigo}");
    
    echo json_encode([
        'success' => true,
        'message' => 'Código enviado via SMS',
        'telefone' => $telefone,
        'debug_codigo' => $codigo // Remover em produção
    ]);
}

function verificarCodigo($pdo) {
    $codigo = $_POST['codigo'] ?? '';
    $telefone = $_POST['telefone'] ?? '';
    
    if (empty($codigo) || empty($telefone)) {
        echo json_encode(['success' => false, 'message' => 'Código e telefone são obrigatórios']);
        return;
    }
    
    // Verificar código da sessão
    if (!isset($_SESSION['codigo_verificacao']) || 
        !isset($_SESSION['telefone_verificacao']) ||
        !isset($_SESSION['tempo_codigo'])) {
        echo json_encode(['success' => false, 'message' => 'Sessão inválida']);
        return;
    }
    
    // Verificar se código não expirou (5 minutos)
    if (time() - $_SESSION['tempo_codigo'] > 300) {
        unset($_SESSION['codigo_verificacao'], $_SESSION['telefone_verificacao'], $_SESSION['tempo_codigo']);
        echo json_encode(['success' => false, 'message' => 'Código expirado']);
        return;
    }
    
    // Verificar código
    if ($codigo != $_SESSION['codigo_verificacao'] || $telefone != $_SESSION['telefone_verificacao']) {
        echo json_encode(['success' => false, 'message' => 'Código inválido']);
        return;
    }
    
    // Marcar telefone como verificado
    $_SESSION['telefone_verificado'] = $telefone;
    
    echo json_encode([
        'success' => true,
        'message' => 'Telefone verificado com sucesso'
    ]);
}

function verificarConvite($pdo) {
    $codigo_convite = strtoupper(trim($_POST['codigo_convite'] ?? ''));
    
    if (empty($codigo_convite)) {
        echo json_encode(['success' => false, 'message' => 'Código de convite obrigatório']);
        return;
    }
    
    // Buscar usuário pelo código de referência
    $stmt = $pdo->prepare("
        SELECT id, nome, telefone, status 
        FROM usuarios 
        WHERE codigo_referencia = ? AND status = 'ativo'
    ");
    $stmt->execute([$codigo_convite]);
    $referenciador = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$referenciador) {
        echo json_encode(['success' => false, 'message' => 'Código de convite inválido']);
        return;
    }
    
    $_SESSION['referenciador_id'] = $referenciador['id'];
    $_SESSION['codigo_convite'] = $codigo_convite;
    
    echo json_encode([
        'success' => true,
        'message' => 'Convite válido',
        'referenciador' => [
            'nome' => $referenciador['nome'],
            'telefone' => substr($referenciador['telefone'], 0, 2) . '*****' . substr($referenciador['telefone'], -2)
        ]
    ]);
}

function cadastrarUsuario($pdo) {
    $telefone = limparTelefone($_POST['telefone'] ?? '');
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';
    
    // Validações
    if (empty($telefone) || empty($nome) || empty($senha)) {
        echo json_encode(['success' => false, 'message' => 'Todos os campos obrigatórios devem ser preenchidos']);
        return;
    }
    
    if (strlen($senha) < 6) {
        echo json_encode(['success' => false, 'message' => 'Senha deve ter pelo menos 6 caracteres']);
        return;
    }
    
    if ($senha !== $confirmar_senha) {
        echo json_encode(['success' => false, 'message' => 'Senhas não conferem']);
        return;
    }
    
    // Verificar se telefone foi verificado
    if (!isset($_SESSION['telefone_verificado']) || $_SESSION['telefone_verificado'] !== $telefone) {
        echo json_encode(['success' => false, 'message' => 'Telefone não foi verificado']);
        return;
    }
    
    // Verificar configuração se exige convite
    $stmt = $pdo->prepare("
        SELECT valor FROM configuracoes 
        WHERE categoria = 'cadastro' AND chave = 'requer_convite'
    ");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    $requer_convite = $config && $config['valor'] == '1';
    
    $referenciado_por = null;
    if ($requer_convite) {
        if (!isset($_SESSION['referenciador_id'])) {
            echo json_encode(['success' => false, 'message' => 'Código de convite obrigatório']);
            return;
        }
        $referenciado_por = $_SESSION['referenciador_id'];
    } else {
        $referenciado_por = $_SESSION['referenciador_id'] ?? null;
    }
    
    $pdo->beginTransaction();
    
    try {
        // Verificar novamente se telefone não está em uso
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE telefone = ?");
        $stmt->execute([$telefone]);
        if ($stmt->fetch()) {
            throw new Exception('Telefone já cadastrado');
        }
        
        // Verificar email se fornecido
        if (!empty($email)) {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                throw new Exception('Email já cadastrado');
            }
        }
        
        // Gerar código de referência único
        do {
            $codigo_referencia = gerarCodigoReferencia();
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE codigo_referencia = ?");
            $stmt->execute([$codigo_referencia]);
        } while ($stmt->fetch());
        
        // Hash da senha
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        
        // Inserir usuário
        $stmt = $pdo->prepare("
            INSERT INTO usuarios 
            (telefone, email, nome, senha, codigo_referencia, referenciado_por, tipo_usuario, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'usuario', 'ativo')
        ");
        $stmt->execute([
            $telefone, 
            $email ?: null, 
            $nome, 
            $senha_hash, 
            $codigo_referencia,
            $referenciado_por
        ]);
        
        $user_id = $pdo->lastInsertId();
        
        // Criar carteira para o usuário
        $stmt = $pdo->prepare("
            INSERT INTO carteiras (usuario_id) VALUES (?)
        ");
        $stmt->execute([$user_id]);
        
        // Buscar bônus de boas-vindas
        $stmt = $pdo->prepare("
            SELECT valor FROM configuracoes 
            WHERE categoria = 'cadastro' AND chave = 'bonus_boas_vindas'
        ");
        $stmt->execute();
        $config_bonus = $stmt->fetch(PDO::FETCH_ASSOC);
        $bonus_valor = $config_bonus ? (float)$config_bonus['valor'] : 0;
        
        // Aplicar bônus de boas-vindas
        if ($bonus_valor > 0) {
            $stmt = $pdo->prepare("
                UPDATE carteiras 
                SET saldo_bonus = saldo_bonus + ? 
                WHERE usuario_id = ?
            ");
            $stmt->execute([$bonus_valor, $user_id]);
        }
        
        // Processar bônus para o referenciador se houver
        if ($referenciado_por) {
            $stmt = $pdo->prepare("
                SELECT valor FROM configuracoes 
                WHERE categoria = 'afiliacao' AND chave = 'bonus_indicacao'
            ");
            $stmt->execute();
            $config_indicacao = $stmt->fetch(PDO::FETCH_ASSOC);
            $bonus_indicacao = $config_indicacao ? (float)$config_indicacao['valor'] : 0;
            
            if ($bonus_indicacao > 0) {
                $stmt = $pdo->prepare("
                    UPDATE carteiras 
                    SET saldo_bonus = saldo_bonus + ? 
                    WHERE usuario_id = ?
                ");
                $stmt->execute([$bonus_indicacao, $referenciado_por]);
                
                // Log do bônus de indicação
                $stmt = $pdo->prepare("
                    INSERT INTO logs_sistema 
                    (usuario_id, acao, tabela_afetada, dados_novos, ip_address) 
                    VALUES (?, 'bonus_indicacao', 'carteiras', ?, ?)
                ");
                $dados_log = json_encode([
                    'valor_bonus' => $bonus_indicacao,
                    'novo_usuario_id' => $user_id,
                    'novo_usuario_nome' => $nome
                ]);
                $stmt->execute([
                    $referenciado_por,
                    $dados_log,
                    $_SERVER['REMOTE_ADDR'] ?? 'cadastro'
                ]);
            }
        }
        
        // Log do cadastro
        $stmt = $pdo->prepare("
            INSERT INTO logs_sistema 
            (usuario_id, acao, tabela_afetada, registro_id, dados_novos, ip_address) 
            VALUES (?, 'usuario_cadastrado', 'usuarios', ?, ?, ?)
        ");
        $dados_log = json_encode([
            'nome' => $nome,
            'telefone' => $telefone,
            'codigo_referencia' => $codigo_referencia,
            'referenciado_por' => $referenciado_por,
            'bonus_boas_vindas' => $bonus_valor
        ]);
        $stmt->execute([
            $user_id,
            $dados_log,
            $_SERVER['REMOTE_ADDR'] ?? 'cadastro'
        ]);
        
        $pdo->commit();
        
        // Limpar dados da sessão
        unset($_SESSION['codigo_verificacao'], $_SESSION['telefone_verificacao'], 
              $_SESSION['tempo_codigo'], $_SESSION['telefone_verificado'],
              $_SESSION['referenciador_id'], $_SESSION['codigo_convite']);
        
        // Logar usuário automaticamente
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_nome'] = $nome;
        $_SESSION['user_telefone'] = $telefone;
        $_SESSION['user_tipo'] = 'usuario';
        
        echo json_encode([
            'success' => true,
            'message' => 'Cadastro realizado com sucesso!',
            'dados' => [
                'user_id' => $user_id,
                'nome' => $nome,
                'codigo_referencia' => $codigo_referencia,
                'bonus_boas_vindas' => $bonus_valor,
                'redirect' => '../inicio/'
            ]
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function limparTelefone($telefone) {
    return preg_replace('/[^0-9]/', '', $telefone);
}

function gerarCodigoReferencia() {
    $caracteres = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $codigo = '';
    for ($i = 0; $i < 8; $i++) {
        $codigo .= $caracteres[mt_rand(0, strlen($caracteres) - 1)];
    }
    return $codigo;
}
?>