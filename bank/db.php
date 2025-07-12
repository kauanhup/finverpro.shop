<?php
// Configurações do banco de dados
$host = 'localhost';
$db = 'meu_site';  // Nome do seu banco de dados
$user = 'root';    // Seu usuário do banco
$pass = '';        // Sua senha do banco

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

// Criar a conexão global $pdo
try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    die("Erro ao conectar com o banco de dados: " . $e->getMessage());
}

// Função para testar a conexão (opcional - para debug)
function testConnection() {
    global $pdo;
    try {
        if ($pdo) {
            return "Conexão com o banco de dados estabelecida com sucesso!";
        }
        return "Variável pdo não encontrada";
    } catch (Exception $e) {
        return "Erro na conexão: " . $e->getMessage();
    }
}

// Função para fechar conexão
function closeConnection($pdo) {
    $pdo = null;
}
?>