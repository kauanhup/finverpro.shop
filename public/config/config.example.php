<?php
/**
 * ========================================
 * FINVER PRO - CONFIGURAÇÕES DE EXEMPLO
 * Copie este arquivo como config.php e configure
 * ========================================
 */

// Configurações de Ambiente
define('APP_ENV', 'production'); // 'development' ou 'production'
define('APP_DEBUG', false); // true para desenvolvimento
define('APP_URL', 'https://seudominio.com');
define('APP_NAME', 'Finver Pro');

// Configurações de Segurança
define('SESSION_TIMEOUT', 8 * 60 * 60); // 8 horas
define('ADMIN_SESSION_TIMEOUT', 4 * 60 * 60); // 4 horas para admin
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 15 * 60); // 15 minutos

// Configurações de Email (para notificações futuras)
const EMAIL_CONFIG = [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'seu-email@gmail.com',
    'smtp_password' => 'sua-senha-app',
    'from_email' => 'noreply@finverpro.com',
    'from_name' => 'Finver Pro'
];

// Configurações de Upload
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'webp']);
define('UPLOAD_PATH', '/uploads/');

// Configurações de Cache
define('CACHE_ENABLED', true);
define('CACHE_TTL', 3600); // 1 hora

// Configurações de API
define('API_RATE_LIMIT', 100); // requests por minuto
define('API_VERSION', 'v1');

// Configurações de Logs
define('LOG_LEVEL', 'info'); // debug, info, warning, error
define('LOG_MAX_FILES', 30); // manter 30 dias de logs

// Configurações Específicas do Sistema
const SYSTEM_CONFIG = [
    // Valores padrão para novos usuários
    'default_user_bonus' => 5.00,
    'default_referral_bonus' => 10.00,
    'min_withdrawal_amount' => 30.00,
    'withdrawal_fee_percentage' => 8.0,
    
    // Configurações de investimento
    'min_investment_amount' => 10.00,
    'max_investment_amount' => 100000.00,
    'daily_interest_calculation_hour' => 2, // 2:00 AM
    
    // Configurações de comissão
    'commission_levels' => [
        1 => 10.0, // 10% nível 1
        2 => 6.0,  // 6% nível 2  
        3 => 1.0   // 1% nível 3
    ],
    
    // Configurações de VIP
    'vip_levels' => [
        'bronze' => ['min_investment' => 0, 'benefits' => []],
        'silver' => ['min_investment' => 1000, 'benefits' => ['bonus_rate' => 0.5]],
        'gold' => ['min_investment' => 5000, 'benefits' => ['bonus_rate' => 1.0]],
        'diamond' => ['min_investment' => 10000, 'benefits' => ['bonus_rate' => 2.0]]
    ]
];

// Configurações de Backup
const BACKUP_CONFIG = [
    'auto_backup_enabled' => true,
    'backup_frequency' => 'daily', // daily, weekly, monthly
    'backup_retention_days' => 30,
    'backup_path' => '/backups/',
    'backup_time' => '03:00' // 3:00 AM
];

// Configurações de Notificações
const NOTIFICATION_CONFIG = [
    'email_notifications' => true,
    'sms_notifications' => false,
    'push_notifications' => false,
    
    // Eventos que geram notificações
    'notify_on' => [
        'new_user_registration' => true,
        'withdrawal_request' => true,
        'investment_completed' => true,
        'commission_earned' => true,
        'system_errors' => true
    ]
];

// Configurações de Manutenção
const MAINTENANCE_CONFIG = [
    'maintenance_mode' => false,
    'maintenance_message' => 'Sistema em manutenção. Voltamos em breve!',
    'allowed_ips' => ['127.0.0.1'], // IPs que podem acessar durante manutenção
    'maintenance_page' => 'maintenance.html'
];

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Configurações PHP
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);

// Headers de Segurança
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    if (APP_ENV === 'production') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/**
 * Função para obter configuração
 */
function getConfig($key, $default = null) {
    $keys = explode('.', $key);
    $value = $GLOBALS;
    
    foreach ($keys as $keyPart) {
        if (isset($value[$keyPart])) {
            $value = $value[$keyPart];
        } else {
            return $default;
        }
    }
    
    return $value;
}

/**
 * Função para verificar se está em modo de manutenção
 */
function isMaintenanceMode() {
    if (!MAINTENANCE_CONFIG['maintenance_mode']) {
        return false;
    }
    
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
    return !in_array($clientIP, MAINTENANCE_CONFIG['allowed_ips']);
}

/**
 * Função para log personalizado
 */
function writeLog($level, $message, $context = []) {
    $levels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];
    $currentLevel = $levels[LOG_LEVEL] ?? 1;
    
    if ($levels[$level] < $currentLevel) {
        return;
    }
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => strtoupper($level),
        'message' => $message,
        'context' => $context,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    $logLine = json_encode($logEntry) . PHP_EOL;
    
    $logFile = 'logs/' . date('Y-m-d') . '.log';
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

// Exemplo de uso das configurações:
/*
// Verificar manutenção
if (isMaintenanceMode()) {
    die(MAINTENANCE_CONFIG['maintenance_message']);
}

// Log de exemplo
writeLog('info', 'Sistema iniciado', ['version' => '2.0']);

// Obter configuração
$minWithdrawal = SYSTEM_CONFIG['min_withdrawal_amount'];
*/
?>