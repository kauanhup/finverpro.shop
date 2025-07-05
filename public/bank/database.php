<?php
/**
 * ========================================
 * FINVER PRO - CONFIGURAÇÃO DE BANCO
 * Classe Database Atualizada para Estrutura Completa
 * Versão: 3.1 - Compatível com 27 Tabelas (CORRIGIDA)
 * ========================================
 */

// Configurações de Ambiente
define('APP_ENV', 'production'); // 'development' ou 'production'
define('APP_DEBUG', false);

// Configurações do Banco de Dados
const DB_CONFIG = [
    'host' => 'localhost',
    'database' => 'u201575542_meu_site', // Corrigido nome do banco
    'username' => 'u201575542_root',      // Corrigido nome do usuário
    'password' => 'Finverpro.shop22',
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
 * Otimizada para a estrutura completa do FinverPro
 */
class Database {
    private static $instance = null;
    private $connection;
    private static $configCache = [];
    
    private function __construct() {
        $this->connect();
        $this->loadConfigCache();
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
                
                // Configurações adicionais para estabilidade
                $this->connection->exec("SET sql_mode = ''");
                $this->connection->exec("SET time_zone = '+00:00'");
                
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
     * Carregar cache de configurações
     */
    private function loadConfigCache() {
        try {
            // Verificar se a tabela configuracoes existe antes de tentar carregar
            if (!$this->tableExists('configuracoes')) {
                return;
            }
            
            $configs = $this->fetchAll("SELECT categoria, chave, valor, tipo FROM configuracoes");
            foreach ($configs as $config) {
                $key = $config['categoria'] . '.' . $config['chave'];
                self::$configCache[$key] = $this->castConfigValue($config['valor'], $config['tipo']);
            }
        } catch (Exception $e) {
            // Se não conseguir carregar, continua sem cache
            error_log("Erro ao carregar cache de configurações: " . $e->getMessage());
        }
    }
    
    /**
     * Converter valor da configuração para o tipo correto
     */
    private function castConfigValue($value, $type) {
        switch ($type) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'number':
                return is_numeric($value) ? (float)$value : 0;
            case 'json':
                return json_decode($value, true) ?: [];
            default:
                return $value;
        }
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
            return false;
        }
    }
    
    /**
     * Buscar um único registro
     */
    public function fetchOne($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params);
            if ($stmt === false) return false;
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Erro em fetchOne: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Buscar múltiplos registros
     */
    public function fetchAll($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params);
            if ($stmt === false) return [];
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Erro em fetchAll: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Inserir registro e retornar último ID
     */
    public function insert($sql, $params = []) {
        try {
            $this->query($sql, $params);
            return $this->connection->lastInsertId();
        } catch (Exception $e) {
            error_log("Erro em insert: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Atualizar registros e retornar linhas afetadas
     */
    public function update($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params);
            if ($stmt === false) return false;
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Erro em update: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Deletar registros e retornar linhas afetadas
     */
    public function delete($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params);
            if ($stmt === false) return false;
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Erro em delete: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Iniciar transação
     */
    public function beginTransaction() {
        try {
            return $this->connection->beginTransaction();
        } catch (Exception $e) {
            error_log("Erro ao iniciar transação: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Confirmar transação
     */
    public function commit() {
        try {
            return $this->connection->commit();
        } catch (Exception $e) {
            error_log("Erro ao confirmar transação: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Reverter transação
     */
    public function rollback() {
        try {
            return $this->connection->rollback();
        } catch (Exception $e) {
            error_log("Erro ao reverter transação: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar se está em transação
     */
    public function inTransaction() {
        return $this->connection->inTransaction();
    }
    
    /**
     * ========================================
     * MÉTODOS ESPECÍFICOS PARA CONFIGURAÇÕES
     * ========================================
     */
    
    /**
     * Obter configuração do cache ou banco
     */
    public function getConfig($categoria, $chave, $default = null) {
        $key = $categoria . '.' . $chave;
        
        if (isset(self::$configCache[$key])) {
            return self::$configCache[$key];
        }
        
        try {
            if (!$this->tableExists('configuracoes')) {
                return $default;
            }
            
            $config = $this->fetchOne(
                "SELECT valor, tipo FROM configuracoes WHERE categoria = ? AND chave = ?",
                [$categoria, $chave]
            );
            
            if ($config) {
                $value = $this->castConfigValue($config['valor'], $config['tipo']);
                self::$configCache[$key] = $value;
                return $value;
            }
        } catch (Exception $e) {
            error_log("Erro ao buscar configuração {$key}: " . $e->getMessage());
        }
        
        return $default;
    }
    
    /**
     * Definir configuração
     */
    public function setConfig($categoria, $chave, $valor, $tipo = 'string', $descricao = null) {
        try {
            if (!$this->tableExists('configuracoes')) {
                return false;
            }
            
            $exists = $this->fetchOne(
                "SELECT id FROM configuracoes WHERE categoria = ? AND chave = ?",
                [$categoria, $chave]
            );
            
            if ($exists) {
                $this->query(
                    "UPDATE configuracoes SET valor = ?, tipo = ?, updated_at = NOW() WHERE categoria = ? AND chave = ?",
                    [$valor, $tipo, $categoria, $chave]
                );
            } else {
                $this->query(
                    "INSERT INTO configuracoes (categoria, chave, valor, tipo, descricao) VALUES (?, ?, ?, ?, ?)",
                    [$categoria, $chave, $valor, $tipo, $descricao]
                );
            }
            
            // Atualizar cache
            $key = $categoria . '.' . $chave;
            self::$configCache[$key] = $this->castConfigValue($valor, $tipo);
            
            return true;
        } catch (Exception $e) {
            error_log("Erro ao salvar configuração {$categoria}.{$chave}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * ========================================
     * MÉTODOS ESPECÍFICOS PARA ADMINISTRAÇÃO
     * ========================================
     */
    
    /**
     * Obter estatísticas do dashboard
     */
    public function getDashboardStats() {
        try {
            $stats = [
                'usuarios' => [
                    'total' => 0,
                    'hoje' => 0,
                    'ativos' => 0,
                ],
                'investimentos' => [
                    'total' => 0,
                    'valor_total' => 0,
                    'hoje' => 0,
                ],
                'saques' => [
                    'pendentes' => 0,
                    'valor_pendente' => 0,
                    'hoje' => 0,
                ],
                'transacoes' => [
                    'hoje' => 0,
                    'depositos_hoje' => 0,
                ],
                'comissoes' => [
                    'pendentes' => 0,
                    'valor_pendente' => 0,
                ]
            ];
            
            // Verificar se as tabelas existem antes de consultar
            if ($this->tableExists('usuarios')) {
                $result = $this->fetchOne("SELECT COUNT(*) as total FROM usuarios");
                $stats['usuarios']['total'] = $result['total'] ?? 0;
                
                $result = $this->fetchOne("SELECT COUNT(*) as total FROM usuarios WHERE DATE(created_at) = CURDATE()");
                $stats['usuarios']['hoje'] = $result['total'] ?? 0;
                
                $result = $this->fetchOne("SELECT COUNT(*) as total FROM usuarios WHERE status = 'ativo'");
                $stats['usuarios']['ativos'] = $result['total'] ?? 0;
            }
            
            if ($this->tableExists('investimentos')) {
                $result = $this->fetchOne("SELECT COUNT(*) as total FROM investimentos WHERE status = 'ativo'");
                $stats['investimentos']['total'] = $result['total'] ?? 0;
                
                $result = $this->fetchOne("SELECT SUM(valor_investido) as total FROM investimentos WHERE status = 'ativo'");
                $stats['investimentos']['valor_total'] = $result['total'] ?? 0;
                
                $result = $this->fetchOne("SELECT COUNT(*) as total FROM investimentos WHERE DATE(created_at) = CURDATE()");
                $stats['investimentos']['hoje'] = $result['total'] ?? 0;
            }
            
            if ($this->tableExists('saques')) {
                $result = $this->fetchOne("SELECT COUNT(*) as total FROM saques WHERE status = 'pendente'");
                $stats['saques']['pendentes'] = $result['total'] ?? 0;
                
                $result = $this->fetchOne("SELECT SUM(valor_bruto) as total FROM saques WHERE status = 'pendente'");
                $stats['saques']['valor_pendente'] = $result['total'] ?? 0;
                
                $result = $this->fetchOne("SELECT COUNT(*) as total FROM saques WHERE DATE(created_at) = CURDATE()");
                $stats['saques']['hoje'] = $result['total'] ?? 0;
            }
            
            if ($this->tableExists('transacoes')) {
                $result = $this->fetchOne("SELECT COUNT(*) as total FROM transacoes WHERE DATE(created_at) = CURDATE()");
                $stats['transacoes']['hoje'] = $result['total'] ?? 0;
                
                $result = $this->fetchOne("SELECT SUM(valor) as total FROM transacoes WHERE tipo = 'deposito' AND DATE(created_at) = CURDATE() AND status = 'concluido'");
                $stats['transacoes']['depositos_hoje'] = $result['total'] ?? 0;
            }
            
            if ($this->tableExists('comissoes')) {
                $result = $this->fetchOne("SELECT COUNT(*) as total FROM comissoes WHERE status = 'pendente'");
                $stats['comissoes']['pendentes'] = $result['total'] ?? 0;
                
                $result = $this->fetchOne("SELECT SUM(valor_comissao) as total FROM comissoes WHERE status = 'pendente'");
                $stats['comissoes']['valor_pendente'] = $result['total'] ?? 0;
            }
            
            return $stats;
        } catch (Exception $e) {
            error_log("Erro ao obter estatísticas do dashboard: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obter últimos usuários
     */
    public function getLatestUsers($limit = 5) {
        try {
            if (!$this->tableExists('usuarios')) {
                return [];
            }
            
            $sql = "SELECT u.id, u.nome, u.telefone, u.created_at, u.status";
            
            if ($this->tableExists('carteiras')) {
                $sql .= ", c.saldo_principal, c.saldo_bonus, c.saldo_comissao
                        FROM usuarios u
                        LEFT JOIN carteiras c ON u.id = c.usuario_id";
            } else {
                $sql .= " FROM usuarios u";
            }
            
            $sql .= " ORDER BY u.created_at DESC LIMIT ?";
            
            return $this->fetchAll($sql, [$limit]);
        } catch (Exception $e) {
            error_log("Erro ao obter últimos usuários: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obter últimos saques pendentes
     */
    public function getLatestPendingWithdrawals($limit = 5) {
        try {
            if (!$this->tableExists('saques') || !$this->tableExists('usuarios')) {
                return [];
            }
            
            $sql = "SELECT s.id, s.valor_bruto, s.created_at, 
                           u.nome, u.telefone";
            
            if ($this->tableExists('chaves_pix')) {
                $sql .= ", cp.chave_pix, cp.tipo
                        FROM saques s
                        JOIN usuarios u ON s.usuario_id = u.id
                        LEFT JOIN chaves_pix cp ON s.chave_pix_id = cp.id";
            } else {
                $sql .= " FROM saques s
                        JOIN usuarios u ON s.usuario_id = u.id";
            }
            
            $sql .= " WHERE s.status = 'pendente'
                     ORDER BY s.created_at DESC
                     LIMIT ?";
            
            return $this->fetchAll($sql, [$limit]);
        } catch (Exception $e) {
            error_log("Erro ao obter últimos saques pendentes: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obter produtos mais populares
     */
    public function getPopularProducts($limit = 5) {
        try {
            if (!$this->tableExists('produtos')) {
                return [];
            }
            
            $sql = "SELECT p.titulo";
            
            if ($this->tableExists('investimentos')) {
                $sql .= ", COUNT(i.id) as total_investimentos, 
                           SUM(i.valor_investido) as valor_total
                        FROM produtos p
                        LEFT JOIN investimentos i ON p.id = i.produto_id AND i.status = 'ativo'
                        GROUP BY p.id, p.titulo
                        ORDER BY total_investimentos DESC";
            } else {
                $sql .= ", 0 as total_investimentos, 0 as valor_total
                        FROM produtos p
                        ORDER BY p.id DESC";
            }
            
            $sql .= " LIMIT ?";
            
            return $this->fetchAll($sql, [$limit]);
        } catch (Exception $e) {
            error_log("Erro ao obter produtos populares: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * ========================================
     * MÉTODOS DE COMPATIBILIDADE
     * ========================================
     */
    
    /**
     * Verificar se tabela existe
     */
    public function tableExists($tableName) {
        try {
            $result = $this->fetchOne(
                "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = ? AND table_name = ?",
                [DB_CONFIG['database'], $tableName]
            );
            return ($result && $result['count'] > 0);
        } catch (Exception $e) {
            error_log("Erro ao verificar tabela {$tableName}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar se coluna existe
     */
    public function columnExists($tableName, $columnName) {
        try {
            $result = $this->fetchOne(
                "SELECT COUNT(*) as count FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?",
                [DB_CONFIG['database'], $tableName, $columnName]
            );
            return ($result && $result['count'] > 0);
        } catch (Exception $e) {
            error_log("Erro ao verificar coluna {$columnName} na tabela {$tableName}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Migrar dados se necessário
     */
    public function migrateIfNeeded() {
        try {
            // Verificar se é a estrutura antiga e migrar
            if ($this->tableExists('historico_transacoes') && !$this->tableExists('transacoes')) {
                $this->migrateOldStructure();
            }
            
            // Verificar se carteiras existem para todos os usuários
            $this->ensureUserWallets();
            
            return true;
        } catch (Exception $e) {
            error_log("Erro na migração: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Garantir que todos os usuários tenham carteiras
     */
    private function ensureUserWallets() {
        try {
            if (!$this->tableExists('usuarios') || !$this->tableExists('carteiras')) {
                return;
            }
            
            $usersWithoutWallet = $this->fetchAll("
                SELECT u.id FROM usuarios u
                LEFT JOIN carteiras c ON u.id = c.usuario_id
                WHERE c.id IS NULL
            ");
            
            foreach ($usersWithoutWallet as $user) {
                $this->query("INSERT INTO carteiras (usuario_id) VALUES (?)", [$user['id']]);
            }
        } catch (Exception $e) {
            error_log("Erro ao garantir carteiras dos usuários: " . $e->getMessage());
        }
    }
    
    /**
     * Migrar estrutura antiga
     */
    private function migrateOldStructure() {
        try {
            // Migrar transações
            if ($this->tableExists('historico_transacoes') && $this->tableExists('transacoes')) {
                $this->query("
                    INSERT INTO transacoes (usuario_id, tipo, valor, descricao, status, created_at)
                    SELECT user_id, tipo, valor, descricao, status, data_transacao
                    FROM historico_transacoes
                    WHERE user_id IS NOT NULL
                    AND NOT EXISTS (
                        SELECT 1 FROM transacoes t 
                        WHERE t.usuario_id = historico_transacoes.user_id 
                        AND t.created_at = historico_transacoes.data_transacao
                    )
                ");
            }
            
            // Migrar outras tabelas se necessário...
        } catch (Exception $e) {
            error_log("Erro na migração da estrutura antiga: " . $e->getMessage());
        }
    }
    
    /**
     * ========================================
     * TRATAMENTO DE ERROS
     * ========================================
     */
    
    /**
     * Tratar erro de conexão
     */
    private function handleConnectionError($error) {
        $logMessage = "Database Connection Error: " . $error;
        error_log($logMessage);
        
        if (APP_DEBUG) {
            die("Erro de conexão com banco de dados: " . $error);
        } else {
            die("Erro interno do servidor. Tente novamente em alguns minutos.");
        }
    }
    
    /**
     * Tratar erro de query
     */
    private function handleQueryError($exception, $sql, $params) {
        $error = "Query Error: " . $exception->getMessage() . " | SQL: " . $sql . " | Params: " . json_encode($params);
        error_log($error);
        
        if (APP_DEBUG) {
            throw new Exception($error);
        } else {
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
                'message' => 'Conexão com banco de dados estabelecida com sucesso!',
                'version' => $this->connection->getAttribute(PDO::ATTR_SERVER_VERSION),
                'database' => DB_CONFIG['database']
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
 * ========================================
 * FUNÇÕES AUXILIARES GLOBAIS
 * ========================================
 */

/**
 * Obter conexão PDO (compatibilidade com código antigo)
 */
function getDBConnection() {
    try {
        return Database::getInstance()->getConnection();
    } catch (Exception $e) {
        error_log("Erro ao obter conexão PDO: " . $e->getMessage());
        return null;
    }
}

/**
 * Obter instância da classe Database
 */
function getDB() {
    try {
        return Database::getInstance();
    } catch (Exception $e) {
        error_log("Erro ao obter instância do Database: " . $e->getMessage());
        return null;
    }
}

/**
 * Obter configuração do sistema
 */
function getConfig($categoria, $chave, $default = null) {
    try {
        return Database::getInstance()->getConfig($categoria, $chave, $default);
    } catch (Exception $e) {
        error_log("Erro ao obter configuração: " . $e->getMessage());
        return $default;
    }
}

/**
 * Definir configuração do sistema
 */
function setConfig($categoria, $chave, $valor, $tipo = 'string', $descricao = null) {
    try {
        return Database::getInstance()->setConfig($categoria, $chave, $valor, $tipo, $descricao);
    } catch (Exception $e) {
        error_log("Erro ao definir configuração: " . $e->getMessage());
        return false;
    }
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
    try {
        return Database::getInstance()->testConnection();
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Erro ao testar conexão: ' . $e->getMessage()
        ];
    }
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

/**
 * ========================================
 * INICIALIZAÇÃO AUTOMÁTICA
 * ========================================
 */

// Validar configurações ao incluir o arquivo
try {
    validateDatabaseConfig();
    
    // Tentar migração automática se necessário (só se não estiver em debug)
    if (!APP_DEBUG) {
        $db = Database::getInstance();
        $db->migrateIfNeeded();
    }
    
} catch (Exception $e) {
    $logMessage = "Database Config Error: " . $e->getMessage();
    error_log($logMessage);
    
    if (APP_DEBUG) {
        die("Configuração inválida: " . $e->getMessage());
    } else {
        // Em produção, não mata o script, apenas loga o erro
        error_log("Erro de configuração do banco de dados: " . $e->getMessage());
    }
}

/**
 * Constantes úteis
 */
if (!defined('DB_PREFIX')) {
    define('DB_PREFIX', ''); // Prefixo das tabelas, se necessário
}

if (!defined('DB_TIMEZONE')) {
    define('DB_TIMEZONE', '+00:00');
}

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