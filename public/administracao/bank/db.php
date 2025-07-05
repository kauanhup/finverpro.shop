<?php
// Função para criar a conexão com o banco de dados
function getDBConnection() {
    // Detectar se está no ambiente local (Termux) ou produção
    $isLocal = (strpos(php_uname(), 'android') !== false) || (file_exists('/data/data/com.termux'));

    if ($isLocal) {
        // Configurações LOCAL (Termux)
        $host = 'localhost';
        $db = 'meu_site';
        $user = 'root';
        $pass = '';
        
        // Array de opções de conexão para testar (em ordem de prioridade)
        $options = [
            // Opção 1: Socket do Termux (mais rápido se disponível)
            "mysql:unix_socket=/data/data/com.termux/files/usr/var/run/mysqld.sock;dbname=$db;charset=utf8",
            // Opção 2: TCP localhost
            "mysql:host=localhost;port=3306;dbname=$db;charset=utf8",
            // Opção 3: TCP 127.0.0.1
            "mysql:host=127.0.0.1;port=3306;dbname=$db;charset=utf8",
            // Opção 4: Socket alternativo
            "mysql:unix_socket=/tmp/mysql.sock;dbname=$db;charset=utf8",
            // Opção 5: Socket padrão do MySQL
            "mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=$db;charset=utf8"
        ];
    } else {
        // Configurações PRODUÇÃO
        $host = 'localhost';
        $db = 'u608666286_prmd';
        $user = 'u608666286_prmd';
        $pass = 'Prmd1234%';
        
        $options = [
            "mysql:host=$host;dbname=$db;charset=utf8"
        ];
    }

    $lastError = '';
    
    foreach ($options as $dsn) {
        try {
            $pdo = new PDO($dsn, $user, $pass);
            
            // Configurações importantes do PDO
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            
            // Configurar charset
            $pdo->exec("SET NAMES utf8");
            
            return $pdo;
        } catch (PDOException $e) {
            $lastError = $e->getMessage();
            continue;
        }
    }
    
    // Se chegou aqui, nenhuma opção funcionou
    die("Conexão falhou: " . $lastError);
}

// Função para testar a conexão (opcional - para debug)
function testConnection() {
    try {
        $pdo = getDBConnection();
        return "Conexão com o banco de dados estabelecida com sucesso!";
    } catch (Exception $e) {
        return "Erro na conexão: " . $e->getMessage();
    }
}

// Função para fechar conexão
function closeConnection($pdo) {
    $pdo = null;
}
?>