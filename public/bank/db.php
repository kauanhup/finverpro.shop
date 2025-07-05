<?php
// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'finverpro');
define('DB_USER', 'root');
define('DB_PASS', '');

function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Erro de conexão com banco: " . $e->getMessage());
        throw new Exception("Erro de conexão com banco de dados");
    }
}

// Função auxiliar para verificar autenticação
function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /');
        exit();
    }
}

// Função auxiliar para verificar se é admin
function checkAdmin() {
    if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
        header('Location: /inicio/');
        exit();
    }
}
?>