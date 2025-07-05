<?php
session_start();
require '../bank/db.php';

// Redirecionar se já estiver logado
if (isset($_SESSION['user_id'])) {
    header("Location: ../inicio/");
    exit();
}

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ./");
    exit();
}

try {
    $pdo = getDBConnection();
    
    // =====================================
    // BUSCAR CONFIGURAÇÕES DE CADASTRO
    // =====================================
    $stmt = $pdo->query("SELECT * FROM configurar_cadastro LIMIT 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Valores padrão se não existir configuração
    if (!$config) {
        $config = [
            'sms_enabled' => 0,
            'require_username' => 0,
            'require_invite_code' => 0,
            'min_password_length' => 6,
            'allow_registration' => 1
        ];
    }
    
    // =====================================
    // VERIFICAR SE CADASTROS ESTÃO PERMITIDOS
    // =====================================
    if (!$config['allow_registration']) {
        $message = "Cadastros estão temporariamente desabilitados";
        $toastType = "error";
        header("Location: ./?message=" . urlencode($message) . "&toastType=" . $toastType);
        exit();
    }
    
    // =====================================
    // RECEBER E VALIDAR DADOS DO FORMULÁRIO
    // =====================================
    $telefone = $_POST['telefone'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $senhaConfirm = $_POST['senha_confirm'] ?? '';
    $nome = $_POST['nome'] ?? ''; // Campo username (usando campo nome da tabela)
    $codigoConvite = $_POST['codigo_convite'] ?? '';
    $smsCode = $_POST['sms_code'] ?? '';
    
    // =====================================
    // VALIDAÇÕES BÁSICAS
    // =====================================
    $errors = [];
    
    // Validar telefone
    $telefoneClean = preg_replace('/\D/', '', $telefone);
    if (strlen($telefoneClean) !== 13 || !str_starts_with($telefoneClean, '55')) {
        $errors[] = "Formato de telefone inválido";
    }
    $telefoneFormatted = '+' . $telefoneClean;
    
    // Validar username se obrigatório
    if ($config['require_username'] && empty(trim($nome))) {
        $errors[] = "Nome de usuário é obrigatório";
    }
    
    // Validar senhas
    if (empty($senha)) {
        $errors[] = "Senha é obrigatória";
    } elseif (strlen($senha) < $config['min_password_length']) {
        $errors[] = "Senha deve ter pelo menos {$config['min_password_length']} caracteres";
    }
    
    if ($senha !== $senhaConfirm) {
        $errors[] = "As senhas não conferem";
    }
    
    // Validar código de convite se obrigatório
    if ($config['require_invite_code'] && empty(trim($codigoConvite))) {
        $errors[] = "Código de convite é obrigatório";
    }
    
    // =====================================
    // VERIFICAR DUPLICATAS
    // =====================================
    
    // Verificar se telefone já existe
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE telefone = ?");
    $stmt->execute([$telefoneFormatted]);
    if ($stmt->fetch()) {
        $errors[] = "Este número de telefone já está cadastrado";
    }
    
    // Verificar se username já existe (se fornecido)
    if (!empty(trim($nome))) {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE nome = ?");
        $stmt->execute([trim($nome)]);
        if ($stmt->fetch()) {
            $errors[] = "Este nome de usuário já está em uso";
        }
    }
    
    // =====================================
    // VALIDAR CÓDIGO DE CONVITE (se fornecido)
    // =====================================
    $referenciadorId = null;
    if (!empty(trim($codigoConvite))) {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE codigo_referencia = ?");
        $stmt->execute([trim($codigoConvite)]);
        $referenciador = $stmt->fetch();
        
        if (!$referenciador) {
            $errors[] = "Código de convite inválido";
        } else {
            $referenciadorId = $referenciador['id'];
        }
    }
    
    // =====================================
    // VALIDAR CÓDIGO SMS (se habilitado)
    // =====================================
    if ($config['sms_enabled']) {
        if (empty($smsCode) || strlen($smsCode) !== 6) {
            $errors[] = "Código SMS inválido";
        } else {
            // Verificar código SMS no banco
            $stmt = $pdo->prepare("
                SELECT id FROM sms_codes 
                WHERE telefone = ? AND codigo = ? AND expires_at > NOW() AND used = 0
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$telefoneFormatted, $smsCode]);
            $smsRecord = $stmt->fetch();
            
            if (!$smsRecord) {
                $errors[] = "Código SMS inválido ou expirado";
            }
        }
    }
    
    // =====================================
    // SE HOUVER ERROS, RETORNAR
    // =====================================
    if (!empty($errors)) {
        $message = implode(". ", $errors);
        $toastType = "error";
        header("Location: ./?message=" . urlencode($message) . "&toastType=" . $toastType);
        exit();
    }
    
    // =====================================
    // INICIAR TRANSAÇÃO PARA CADASTRO
    // =====================================
    $pdo->beginTransaction();
    
    try {
        // =====================================
        // GERAR CÓDIGO DE REFERÊNCIA ÚNICO
        // =====================================
        do {
            $codigoReferencia = strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE codigo_referencia = ?");
            $stmt->execute([$codigoReferencia]);
        } while ($stmt->fetch());
        
        // =====================================
        // HASH DA SENHA
        // =====================================
        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
        
        // =====================================
        // INSERIR USUÁRIO NO BANCO
        // =====================================
        $stmt = $pdo->prepare("
            INSERT INTO usuarios (
                telefone, 
                senha, 
                nome, 
                codigo_referencia, 
                referenciado_por, 
                referenciador_id,
                referencia_convite,
                cargo,
                data_criacao,
                data_cadastro
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $telefoneFormatted,
            $senhaHash,
            trim($nome) ?: null,
            $codigoReferencia,
            $referenciadorId,
            $referenciadorId,
            $codigoConvite ?: null,
            'usuario'
        ]);
        
        $userId = $pdo->lastInsertId();
        
        // =====================================
        // MARCAR CÓDIGO SMS COMO USADO (se aplicável)
        // =====================================
        if ($config['sms_enabled'] && isset($smsRecord)) {
            $stmt = $pdo->prepare("UPDATE sms_codes SET used = 1 WHERE id = ?");
            $stmt->execute([$smsRecord['id']]);
        }
        
        // =====================================
        // SALDO BÔNUS DE CADASTRO
        // =====================================
        if (isset($config['bonus_cadastro']) && $config['bonus_cadastro'] > 0) {
            // Adicionar bônus ao saldo do usuário
            $stmt = $pdo->prepare("UPDATE usuarios SET saldo = saldo + ? WHERE id = ?");
            $stmt->execute([$config['bonus_cadastro'], $userId]);
            
            // Registrar transação do bônus
            $stmt = $pdo->prepare("
                INSERT INTO transacoes (user_id, tipo, valor, descricao, status, data_transacao) 
                VALUES (?, 'bonus', ?, 'Bônus de boas-vindas por cadastro', 'aprovado', NOW())
            ");
            $stmt->execute([$userId, $config['bonus_cadastro']]);
        }
        
        // =====================================
        // PROCESSAR SISTEMA DE REFERÊNCIA (se aplicável)
        // =====================================
        if ($referenciadorId) {
            // Incrementar contador de indicações do referenciador
            $stmt = $pdo->prepare("
                UPDATE usuarios 
                SET total_indicacoes = COALESCE(total_indicacoes, 0) + 1 
                WHERE id = ?
            ");
            $stmt->execute([$referenciadorId]);
            
            // Registrar na tabela de indicações (CORRIGIDO - usando nomes corretos das colunas)
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO indicacoes (user_id, indicado_id, data_indicacao, bonus)
                    VALUES (?, ?, NOW(), 0.00)
                ");
                $stmt->execute([$referenciadorId, $userId]);
            } catch (Exception $e) {
                // Tabela indicacoes pode não existir, continuar sem erro
                error_log("Erro ao registrar indicação: " . $e->getMessage());
            }
        }
        
        // =====================================
        // LIMPAR CÓDIGOS SMS EXPIRADOS
        // =====================================
        if ($config['sms_enabled']) {
            $pdo->exec("DELETE FROM sms_codes WHERE expires_at < NOW() OR used = 1");
        }
        
        // =====================================
        // COMMIT DA TRANSAÇÃO
        // =====================================
        $pdo->commit();
        
        // =====================================
        // LOG DE SUCESSO
        // =====================================
        error_log("Usuário cadastrado com sucesso: ID {$userId}, Telefone: {$telefoneFormatted}");
        
        // =====================================
        // CRIAR SESSÃO AUTOMÁTICA (OPCIONAL)
        // =====================================
        $_SESSION['user_id'] = $userId;
        $_SESSION['telefone'] = $telefoneFormatted;
        $_SESSION['nome'] = trim($nome) ?: null;
        $_SESSION['cargo'] = 'usuario';
        $_SESSION['codigo_referencia'] = $codigoReferencia;
        
        // =====================================
        // REDIRECIONAR PARA ÁREA LOGADA
        // =====================================
        $message = "Conta criada com sucesso! Bem-vindo!";
        $toastType = "success";
        
        // Redirecionar para página inicial ou dashboard
        header("Location: ../inicio/?message=" . urlencode($message) . "&toastType=" . $toastType);
        exit();
        
    } catch (Exception $e) {
        // =====================================
        // ROLLBACK EM CASO DE ERRO
        // =====================================
        $pdo->rollBack();
        
        error_log("Erro ao cadastrar usuário: " . $e->getMessage());
        
        $message = "Erro interno. Tente novamente.";
        $toastType = "error";
        header("Location: ./?message=" . urlencode($message) . "&toastType=" . $toastType);
        exit();
    }
    
} catch (Exception $e) {
    // =====================================
    // ERRO GERAL
    // =====================================
    error_log("Erro geral no cadastro: " . $e->getMessage());
    
    $message = "Erro interno do servidor. Tente novamente.";
    $toastType = "error";
    header("Location: ./?message=" . urlencode($message) . "&toastType=" . $toastType);
    exit();
}
?>