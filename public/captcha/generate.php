<?php
// captcha/generate.php

// Forçar o caminho correto
$db_path = dirname(__DIR__) . '/bank/db.php';

if (!file_exists($db_path)) {
    die("Erro: db.php não encontrado em: " . $db_path);
}

require_once $db_path;

function generateCaptcha() {
    try {
        $pdo = getDBConnection();
        
        // Criar tabela se não existir
        $pdo->exec("CREATE TABLE IF NOT EXISTS captcha_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(255) NOT NULL,
            question VARCHAR(100) NOT NULL,
            answer INT NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            used TINYINT(1) DEFAULT 0,
            UNIQUE KEY session_id (session_id)
        )");
        
        // Limpar antigas
        $stmt = $pdo->prepare("DELETE FROM captcha_sessions WHERE created_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE) OR used = 1");
        $stmt->execute();
        
        // Gerar nova
        $num1 = rand(1, 10);
        $num2 = rand(1, 10);
        $operation = rand(0, 1) ? '+' : '-';
        $answer = ($operation === '+') ? $num1 + $num2 : $num1 - $num2;
        $question = "$num1 $operation $num2";
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        // Deletar anterior e inserir nova
        $stmt = $pdo->prepare("DELETE FROM captcha_sessions WHERE session_id = ?");
        $stmt->execute([session_id()]);
        
        $stmt = $pdo->prepare("INSERT INTO captcha_sessions (session_id, question, answer, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([session_id(), $question, $answer, $ip]);
        
        return ['question' => $question];
        
    } catch (Exception $e) {
        // Fallback simples
        $num1 = rand(1, 10);
        $num2 = rand(1, 10);
        $operation = rand(0, 1) ? '+' : '-';
        return ['question' => "$num1 $operation $num2"];
    }
}

function getCaptcha() {
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("SELECT question FROM captcha_sessions WHERE session_id = ? AND used = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE) LIMIT 1");
        $stmt->execute([session_id()]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return ['question' => $result['question']];
        }
        
    } catch (Exception $e) {
        // Ignorar erro e gerar nova
    }
    
    return generateCaptcha();
}

function validateCaptcha($userAnswer) {
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("SELECT id, answer FROM captcha_sessions WHERE session_id = ? AND used = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE) LIMIT 1");
        $stmt->execute([session_id()]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return false;
        }
        
        $isValid = ($userAnswer == $result['answer']);
        
        if ($isValid) {
            $stmt = $pdo->prepare("UPDATE captcha_sessions SET used = 1 WHERE id = ?");
            $stmt->execute([$result['id']]);
        }
        
        return $isValid;
        
    } catch (Exception $e) {
        return false;
    }
}
?>