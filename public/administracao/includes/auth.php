<?php
/**
 * ========================================
 * FINVER PRO - MIDDLEWARE DE AUTENTICAÇÃO
 * Sistema de Proteção Administrativo
 * ========================================
 */

// Iniciar sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verificar se o usuário está autenticado como administrador
 */
function checkAdminAuth($redirect = true) {
    if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
        if ($redirect) {
            header("Location: ../index.php");
            exit();
        }
        return false;
    }
    
    // Verificar se a sessão não expirou (opcional - 8 horas)
    $sessionTimeout = 8 * 60 * 60; // 8 horas em segundos
    if (isset($_SESSION['admin_login_time']) && 
        (time() - $_SESSION['admin_login_time']) > $sessionTimeout) {
        
        destroyAdminSession();
        if ($redirect) {
            header("Location: ../index.php?message=" . urlencode("Sessão expirada. Faça login novamente."));
            exit();
        }
        return false;
    }
    
    return true;
}

/**
 * Verificar nível de permissão do administrador
 */
function checkAdminLevel($requiredLevel = 'admin') {
    if (!checkAdminAuth(false)) {
        return false;
    }
    
    $userLevel = $_SESSION['admin_nivel'] ?? 'admin';
    
    $levels = [
        'moderador' => 1,
        'admin' => 2,
        'super' => 3
    ];
    
    $userLevelNum = $levels[$userLevel] ?? 1;
    $requiredLevelNum = $levels[$requiredLevel] ?? 2;
    
    return $userLevelNum >= $requiredLevelNum;
}

/**
 * Obter dados do administrador logado
 */
function getAdminData() {
    if (!checkAdminAuth(false)) {
        return null;
    }
    
    return [
        'id' => $_SESSION['admin_id'],
        'email' => $_SESSION['admin_email'],
        'nome' => $_SESSION['admin_nome'],
        'nivel' => $_SESSION['admin_nivel'],
        'login_time' => $_SESSION['admin_login_time']
    ];
}

/**
 * Destruir sessão administrativa
 */
function destroyAdminSession() {
    // Remover variáveis de sessão administrativa
    $adminKeys = ['admin_id', 'admin_email', 'admin_nome', 'admin_nivel', 'admin_login_time'];
    foreach ($adminKeys as $key) {
        unset($_SESSION[$key]);
    }
    
    // Se não há outras variáveis de sessão, destruir completamente
    if (empty($_SESSION)) {
        session_destroy();
    }
}

/**
 * Logout administrativo
 */
function adminLogout($redirectTo = '../index.php') {
    destroyAdminSession();
    header("Location: $redirectTo");
    exit();
}

/**
 * Verificar permissão para ação específica
 */
function hasPermission($action) {
    $nivel = $_SESSION['admin_nivel'] ?? 'admin';
    
    // Definir permissões por nível
    $permissions = [
        'super' => [
            'usuarios.view', 'usuarios.edit', 'usuarios.delete',
            'produtos.view', 'produtos.edit', 'produtos.delete', 'produtos.create',
            'configuracoes.view', 'configuracoes.edit',
            'saques.view', 'saques.approve', 'saques.reject',
            'pagamentos.view', 'pagamentos.manage',
            'relatorios.view', 'relatorios.export',
            'admin.manage'
        ],
        'admin' => [
            'usuarios.view', 'usuarios.edit',
            'produtos.view', 'produtos.edit', 'produtos.create',
            'configuracoes.view', 'configuracoes.edit',
            'saques.view', 'saques.approve', 'saques.reject',
            'pagamentos.view',
            'relatorios.view'
        ],
        'moderador' => [
            'usuarios.view',
            'produtos.view',
            'saques.view',
            'pagamentos.view',
            'relatorios.view'
        ]
    ];
    
    return in_array($action, $permissions[$nivel] ?? []);
}

/**
 * Registrar ação administrativa (log de auditoria)
 */
function logAdminAction($action, $details = null, $table_affected = null, $record_id = null) {
    try {
        require_once '../../config/database.php';
        $db = Database::getInstance();
        
        $admin = getAdminData();
        if (!$admin) return false;
        
        // Criar tabela de logs se não existir
        $db->query("
            CREATE TABLE IF NOT EXISTS admin_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                admin_id INT NOT NULL,
                admin_email VARCHAR(255),
                action VARCHAR(255) NOT NULL,
                details TEXT,
                table_affected VARCHAR(100),
                record_id INT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_admin_id (admin_id),
                INDEX idx_action (action),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Inserir log
        $db->query("
            INSERT INTO admin_logs 
            (admin_id, admin_email, action, details, table_affected, record_id, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $admin['id'],
            $admin['email'],
            $action,
            $details ? json_encode($details) : null,
            $table_affected,
            $record_id,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Erro ao registrar log administrativo: " . $e->getMessage());
        return false;
    }
}

/**
 * Middleware principal - deve ser chamado no início de cada página administrativa
 */
function requireAdmin($level = 'admin') {
    // Verificar autenticação
    if (!checkAdminAuth()) {
        return false;
    }
    
    // Verificar nível de permissão
    if (!checkAdminLevel($level)) {
        http_response_code(403);
        die("
        <!DOCTYPE html>
        <html>
        <head>
            <title>Acesso Negado</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                .error { background: #fee; border: 1px solid #fcc; padding: 20px; border-radius: 5px; display: inline-block; }
            </style>
        </head>
        <body>
            <div class='error'>
                <h2>🚫 Acesso Negado</h2>
                <p>Você não tem permissão para acessar esta página.</p>
                <p>Nível necessário: <strong>$level</strong></p>
                <p>Seu nível: <strong>{$_SESSION['admin_nivel']}</strong></p>
                <a href='../dashboard/'>← Voltar ao Dashboard</a>
            </div>
        </body>
        </html>
        ");
    }
    
    return true;
}

/**
 * Proteger contra CSRF
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitizar entrada de dados
 */
function sanitizeInput($data, $type = 'string') {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = sanitizeInput($value, $type);
        }
        return $data;
    }
    
    switch ($type) {
        case 'email':
            return filter_var($data, FILTER_SANITIZE_EMAIL);
        case 'int':
            return filter_var($data, FILTER_SANITIZE_NUMBER_INT);
        case 'float':
            return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        case 'url':
            return filter_var($data, FILTER_SANITIZE_URL);
        case 'string':
        default:
            return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Validar dados de entrada
 */
function validateInput($data, $rules) {
    $errors = [];
    
    foreach ($rules as $field => $rule) {
        $value = $data[$field] ?? null;
        
        // Required
        if (isset($rule['required']) && $rule['required'] && empty($value)) {
            $errors[$field] = "O campo {$field} é obrigatório.";
            continue;
        }
        
        if (empty($value)) continue;
        
        // Min length
        if (isset($rule['min']) && strlen($value) < $rule['min']) {
            $errors[$field] = "O campo {$field} deve ter pelo menos {$rule['min']} caracteres.";
        }
        
        // Max length
        if (isset($rule['max']) && strlen($value) > $rule['max']) {
            $errors[$field] = "O campo {$field} deve ter no máximo {$rule['max']} caracteres.";
        }
        
        // Email
        if (isset($rule['email']) && $rule['email'] && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $errors[$field] = "O campo {$field} deve ser um email válido.";
        }
        
        // Numeric
        if (isset($rule['numeric']) && $rule['numeric'] && !is_numeric($value)) {
            $errors[$field] = "O campo {$field} deve ser numérico.";
        }
        
        // Custom validation
        if (isset($rule['custom']) && is_callable($rule['custom'])) {
            $customResult = $rule['custom']($value);
            if ($customResult !== true) {
                $errors[$field] = $customResult;
            }
        }
    }
    
    return $errors;
}

// Verificar autenticação automaticamente se não for a página de login
$currentFile = basename($_SERVER['PHP_SELF']);
if ($currentFile !== 'index.php' && $currentFile !== 'login.php') {
    requireAdmin();
}
?>