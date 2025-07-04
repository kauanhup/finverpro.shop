<?php
/**
 * ========================================
 * FINVER PRO - CONFIGURAÇÃO DE BANCO
 * Arquivo de Conexão Organizado e Otimizado
 * ========================================
 */

// Configurações de Ambiente
define('APP_ENV', 'production'); // 'development' ou 'production'
define('APP_DEBUG', false);

// Configurações do Banco de Dados
const DB_CONFIG = [
    'host' => 'localhost',
    'database' => 'meu_site',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'port' => 3306,
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]
];

// Configurações de Conexão Alternativas (para fallback)
const DB_FALLBACK_HOSTS = [
    'localhost',
    '127.0.0.1'
];

/**
 * Classe de Conexão com Banco de Dados
 */
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        $this->connect();
    }
    
    /**
     * Singleton para garantir uma única conexão
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Conectar ao banco de dados
     */
    private function connect() {
        $lastError = '';
        
        // Tentar conectar com hosts alternativos
        foreach (DB_FALLBACK_HOSTS as $host) {
            try {
                $dsn = "mysql:host={$host};port=" . DB_CONFIG['port'] . ";dbname=" . DB_CONFIG['database'] . ";charset=" . DB_CONFIG['charset'];
                
                $this->connection = new PDO(
                    $dsn,
                    DB_CONFIG['username'],
                    DB_CONFIG['password'],
                    DB_CONFIG['options']
                );
                
                // Se chegou aqui, conexão bem-sucedida
                return;
                
            } catch (PDOException $e) {
                $lastError = $e->getMessage();
                continue;
            }
        }
        
        // Se chegou aqui, nenhuma conexão funcionou
        $this->handleConnectionError($lastError);
    }
    
    /**
     * Obter conexão PDO
     */
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Executar query preparada
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->handleQueryError($e, $sql, $params);
        }
    }
    
    /**
     * Buscar um único registro
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Buscar múltiplos registros
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Inserir registro e retornar último ID
     */
    public function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->connection->lastInsertId();
    }
    
    /**
     * Atualizar registros e retornar linhas afetadas
     */
    public function update($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Deletar registros e retornar linhas afetadas
     */
    public function delete($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Iniciar transação
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    /**
     * Confirmar transação
     */
    public function commit() {
        return $this->connection->commit();
    }
    
    /**
     * Reverter transação
     */
    public function rollback() {
        return $this->connection->rollback();
    }
    
    /**
     * Verificar se está em transação
     */
    public function inTransaction() {
        return $this->connection->inTransaction();
    }
    
    /**
     * Tratar erro de conexão
     */
    private function handleConnectionError($error) {
        if (APP_DEBUG) {
            die("Erro de conexão com banco de dados: " . $error);
        } else {
            error_log("Database Connection Error: " . $error);
            die("Erro interno do servidor. Tente novamente em alguns minutos.");
        }
    }
    
    /**
     * Tratar erro de query
     */
    private function handleQueryError($exception, $sql, $params) {
        $error = "Query Error: " . $exception->getMessage() . " | SQL: " . $sql . " | Params: " . json_encode($params);
        
        if (APP_DEBUG) {
            die($error);
        } else {
            error_log($error);
            throw new Exception("Erro ao executar operação no banco de dados.");
        }
    }
    
    /**
     * Testar conexão
     */
    public function testConnection() {
        try {
            $this->connection->query("SELECT 1");
            return [
                'success' => true,
                'message' => 'Conexão com banco de dados estabelecida com sucesso!'
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Erro na conexão: ' . $e->getMessage()
            ];
        }
    }
}

/**
 * Funções auxiliares para compatibilidade
 */

/**
 * Obter conexão PDO (compatibilidade com código antigo)
 */
function getDBConnection() {
    return Database::getInstance()->getConnection();
}

/**
 * Obter instância da classe Database
 */
function getDB() {
    return Database::getInstance();
}

/**
 * Fechar conexão (para compatibilidade)
 */
function closeConnection($pdo = null) {
    // PDO fecha automaticamente, mas mantemos para compatibilidade
    $pdo = null;
}

/**
 * Testar conexão (para compatibilidade)
 */
function testConnection() {
    return Database::getInstance()->testConnection();
}

/**
 * Validar configurações do banco
 */
function validateDatabaseConfig() {
    $required = ['host', 'database', 'username'];
    $missing = [];
    
    foreach ($required as $key) {
        if (empty(DB_CONFIG[$key])) {
            $missing[] = $key;
        }
    }
    
    if (!empty($missing)) {
        throw new Exception("Configurações do banco faltando: " . implode(', ', $missing));
    }
    
    return true;
}

// Validar configurações ao incluir o arquivo
try {
    validateDatabaseConfig();
} catch (Exception $e) {
    if (APP_DEBUG) {
        die("Configuração inválida: " . $e->getMessage());
    } else {
        error_log("Database Config Error: " . $e->getMessage());
        die("Erro de configuração do servidor.");
    }
}

/**
 * Constantes úteis
 */
define('DB_PREFIX', ''); // Prefixo das tabelas, se necessário
define('DB_TIMEZONE', '+00:00');

/**
 * Configurar timezone do MySQL
 */
try {
    $db = Database::getInstance();
    $db->query("SET time_zone = ?", [DB_TIMEZONE]);
} catch (Exception $e) {
    error_log("Erro ao configurar timezone: " . $e->getMessage());
}
?>