<?php
session_start();

// Inclui a conexão com o banco de dados
require '../bank/db.php';

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Conecta ao banco de dados
        $conn = getDBConnection();
        
        // Recebe e limpa os dados do formulário
        $banco = trim($_POST['banco']);
        $client_id = trim($_POST['client_id']);
        $client_secret = trim($_POST['client_secret']);
        $status = trim($_POST['status']);
        // ✅ NOVO: Receber webhook_url
        $webhook_url = trim($_POST['webhook_url']);
        
        // Validação básica
        if (empty($banco)) {
            throw new Exception("Nome do banco é obrigatório");
        }
        
        // ✅ NOVO: Validação da URL do webhook
        if (!empty($webhook_url) && !filter_var($webhook_url, FILTER_VALIDATE_URL)) {
            throw new Exception("URL do webhook inválida. Use formato: https://seudominio.com/gate/webhook.php");
        }
        
        // Verifica o tamanho dos dados antes de salvar
        if (strlen($client_id) > 500) {
            throw new Exception("Client ID muito longo. Máximo de 500 caracteres.");
        }
        
        if (strlen($client_secret) > 1000) {
            throw new Exception("Client Secret muito longo. Máximo de 1000 caracteres.");
        }
        
        // ✅ NOVO: Validação do tamanho da webhook_url
        if (strlen($webhook_url) > 255) {
            throw new Exception("Webhook URL muito longa. Máximo de 255 caracteres.");
        }
        
        // Verifica se o registro já existe
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM gateway WHERE banco = ?");
        $checkStmt->execute([$banco]);
        $exists = $checkStmt->fetchColumn();
        
        if ($exists > 0) {
            // ✅ ATUALIZADO: Incluir webhook_url no UPDATE
            $stmt = $conn->prepare("
                UPDATE gateway 
                SET client_id = ?, client_secret = ?, status = ?, webhook_url = ? 
                WHERE banco = ?
            ");
            $stmt->execute([$client_id, $client_secret, $status, $webhook_url, $banco]);
        } else {
            // ✅ ATUALIZADO: Incluir webhook_url no INSERT
            $stmt = $conn->prepare("
                INSERT INTO gateway (banco, client_id, client_secret, status, webhook_url) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$banco, $client_id, $client_secret, $status, $webhook_url]);
        }
        
        // Log de sucesso (opcional)
        error_log("Gateway '$banco' salvo com sucesso. Webhook: $webhook_url");
        
        // Redireciona com mensagem de sucesso
        header("Location: ./?success=1");
        exit;
        
    } catch (PDOException $e) {
        // Log do erro (opcional)
        error_log("Erro ao salvar gateway: " . $e->getMessage());
        
        // Trata erros específicos do MySQL
        if ($e->getCode() == '22001') {
            $errorMessage = "Dados muito longos para salvar. Verifique o tamanho dos campos.";
        } elseif ($e->getCode() == '23000') {
            $errorMessage = "Erro de integridade dos dados. Verifique se todos os campos estão corretos.";
        } elseif ($e->getCode() == '42S22') {
            $errorMessage = "Coluna webhook_url não existe. Execute: ALTER TABLE gateway ADD COLUMN webhook_url VARCHAR(255);";
        } else {
            $errorMessage = "Erro ao salvar os dados: " . $e->getMessage();
        }
        
        // Redireciona com mensagem de erro
        header("Location: ./?error=" . urlencode($errorMessage));
        exit;
        
    } catch (Exception $e) {
        // Trata outros erros
        error_log("Erro de validação: " . $e->getMessage());
        header("Location: ./?error=" . urlencode($e->getMessage()));
        exit;
    }
} else {
    // Se não foi um POST, redireciona para a página principal
    header("Location: ./");
    exit;
}
?>