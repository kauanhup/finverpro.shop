<?php
// Configurações do banco de dados HOSTINGER
$host = 'localhost';
$db = 'meu_site';  // Prefixo + nome que você digitou
$user = 'root';    // Prefixo + username que você digitou
$pass = '';   // A senha que você criou na Hostinger

// Função para criar a conexão com o banco de dados
function getDBConnection() {
    global $host, $db, $user, $pass;

    // Array de opções de conexão para testar (em ordem de prioridade)
    $options = [
        // Opção 1: Hostinger padrão
        "mysql:host=localhost;dbname=$db;charset=utf8",
        // Opção 2: TCP localhost
        "mysql:host=localhost;port=3306;dbname=$db;charset=utf8",
        // Opção 3: TCP 127.0.0.1
        "mysql:host=127.0.0.1;port=3306;dbname=$db;charset=utf8"
    ];

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