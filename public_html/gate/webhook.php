<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json");

// ✅ CORREÇÃO: Permitir todos os User-Agents (inclusive APIs)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

date_default_timezone_set('America/Sao_Paulo');
$dueDate = date('Y-m-d H:i:s');

// ✅ CORREÇÃO: Incluir conexão do banco
require_once '../bank/db.php';

// ✅ CORREÇÃO: Verificar se Pusher existe
if (file_exists('./vendor/autoload.php')) {
    require './vendor/autoload.php';
    
    try {
        $options = array(
            'cluster' => 'mt1',
            'useTLS' => true
        );
        $pusher = new Pusher\Pusher(
            'e2fe6ed48f9680332d9e',
            '2fc7bb3da690acfcf849',
            '1840990',
            $options
        );
        logWebhook("✅ Pusher configurado com sucesso");
    } catch (Exception $e) {
        logWebhook("❌ ERRO no Pusher: " . $e->getMessage());
        $pusher = null;
    }
} else {
    logWebhook("❌ Vendor/autoload.php NÃO ENCONTRADO");
    $pusher = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jsonRecebido = file_get_contents('php://input');
    $dadosRecebidos = json_decode($jsonRecebido, true);
    
    // ✅ LOG MELHORADO: Headers + IP + User-Agent
    logWebhook("🔔 WEBHOOK RECEBIDO:");
    logWebhook("   - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'N/A'));
    logWebhook("   - User-Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A'));
    logWebhook("   - Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'N/A'));
    logWebhook("   - Data: " . $jsonRecebido);

    if (json_last_error() === JSON_ERROR_NONE) {
        $banco = verificarBanco($dadosRecebidos);
        $isPago = verificarSePago($dadosRecebidos, $banco);
        
        logWebhook("🏦 Banco: $banco");
        logWebhook("💰 Status pago: " . ($isPago ? 'SIM' : 'NÃO'));
        
        if (!$isPago) {
            logWebhook("⏳ Pagamento ainda pendente");
            echo json_encode(['status' => 'pending', 'message' => 'Aguardando pagamento']);
            exit;
        }

        // ✅ CORREÇÃO: Identificar transaction_id corretamente
        $transactionId = getTransactionId($dadosRecebidos, $banco);
        
        if ($transactionId) {
            logWebhook("🔍 Transaction ID: $transactionId");
            $result = processTransaction($transactionId, $dueDate, $pusher);
            echo json_encode($result);
        } else {
            logWebhook("❌ Transaction ID não encontrado");
            echo json_encode(['error' => 'Transaction ID não identificado']);
        }

    } else {
        logWebhook("❌ ERRO JSON: " . json_last_error_msg());
        echo json_encode(['error' => 'Erro no JSON recebido']);
    }
} else {
    // ✅ CORREÇÃO: Permitir GET para teste manual
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        logWebhook("🌐 TESTE MANUAL DO WEBHOOK - IP: " . $_SERVER['REMOTE_ADDR']);
        echo json_encode([
            'status' => 'webhook_online',
            'message' => 'Webhook funcionando corretamente!',
            'timestamp' => date('Y-m-d H:i:s'),
            'server' => $_SERVER['SERVER_NAME'] ?? 'localhost'
        ]);
    } else {
        echo json_encode(['error' => 'Método não permitido, use POST']);
    }
}

// ✅ FUNÇÃO MELHORADA: Extrair transaction ID baseado no banco
function getTransactionId($dadosRecebidos, $banco) {
    switch ($banco) {
        case 'PixUp Soluções de Pagamentos':
            return $dadosRecebidos['requestBody']['transactionId'] ?? 
                   $dadosRecebidos['transactionId'] ?? 
                   $dadosRecebidos['id'] ?? null;
            
        case 'SuitPay Instituição':
            return $dadosRecebidos['idTransaction'] ?? 
                   $dadosRecebidos['transactionId'] ?? null;
            
        case 'FIVEPAY':
            return $dadosRecebidos['idTransaction'] ?? 
                   $dadosRecebidos['transaction_id'] ?? null;
            
        case 'SYNCPAY':
            return $dadosRecebidos['idTransaction'] ?? 
                   $dadosRecebidos['transaction_id'] ?? null;
            
        case 'VENTUREPAY':
            return $dadosRecebidos['id'] ?? 
                   $dadosRecebidos['transaction_id'] ?? null;
            
        default:
            logWebhook("❌ Banco não reconhecido: $banco");
            return null;
    }
}

// ✅ FUNÇÃO MELHORADA: Verificar se foi pago
function verificarSePago($dadosRecebidos, $banco) {
    switch ($banco) {
        case 'PixUp Soluções de Pagamentos':
            $status = $dadosRecebidos['requestBody']['status'] ?? 
                     $dadosRecebidos['status'] ?? '';
            return in_array(strtolower($status), ['approved', 'paid', 'success', 'completed']);
                   
        case 'SuitPay Instituição':
            $status = $dadosRecebidos['statusTransaction'] ?? 
                     $dadosRecebidos['status'] ?? '';
            return in_array(strtolower($status), ['paid', 'approved', 'completed']);
                   
        case 'FIVEPAY':
            $status = $dadosRecebidos['status'] ?? '';
            return in_array(strtolower($status), ['approved', 'paid', 'success']);
                   
        case 'SYNCPAY':
            $status = $dadosRecebidos['status'] ?? '';
            return in_array(strtolower($status), ['approved', 'paid', 'success']);
                   
        case 'VENTUREPAY':
            $status = $dadosRecebidos['status'] ?? '';
            return in_array(strtolower($status), ['paid', 'approved', 'completed']);
                   
        default:
            logWebhook("❌ Status não identificado para banco: $banco");
            return false;
    }
}

// ✅ FUNÇÃO COMPLETAMENTE CORRIGIDA
function processTransaction($idTransaction, $dueDate, $pusher) {
    logWebhook("🔄 Processando transação: $idTransaction");
    
    try {
        $conn = getDBConnection();
        
        // ✅ BUSCAR DADOS COMPLETOS DA TRANSAÇÃO
        $stmt = $conn->prepare("SELECT user_id, valor, status FROM pagamentos WHERE cod_referencia = :id");
        $stmt->execute(['id' => $idTransaction]);
        $pagamento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$pagamento) {
            logWebhook("❌ Transação não encontrada: $idTransaction");
            return ['error' => 'Transação não encontrada'];
        }
        
        if ($pagamento['status'] === 'Aprovado') {
            logWebhook("⚠️ Transação já processada: $idTransaction");
            return ['status' => 'already_processed'];
        }
        
        logWebhook("👤 User ID: {$pagamento['user_id']}");
        logWebhook("💵 Valor: R$ {$pagamento['valor']}");
        
        // ✅ ATUALIZAR STATUS
        $updateResult = updatePaymentStatus($idTransaction, 'Aprovado', $dueDate);
        logWebhook("📝 Update result: " . json_encode($updateResult));
        
        // ✅ ATUALIZAR SALDO (user_id correto)
        $balanceResult = updateUserBalance($pagamento['user_id'], $pagamento['valor']);
        logWebhook("💰 Balance result: " . json_encode($balanceResult));
        
        if (!isset($updateResult['error']) && !isset($balanceResult['error'])) {
            // ✅ PUSHER COM user_id ESPECÍFICO
            if ($pusher) {
                try {
                    $pusherData = [
                        'user_id' => $pagamento['user_id'],
                        'transaction_id' => $idTransaction,
                        'amount' => $pagamento['valor'],
                        'message' => 'Pagamento aprovado automaticamente!'
                    ];
                    
                    $pusher->trigger('payment_channel', 'payment_approved', $pusherData);
                    logWebhook("📡 Pusher enviado: " . json_encode($pusherData));
                } catch (Exception $e) {
                    logWebhook("❌ Erro no Pusher: " . $e->getMessage());
                }
            }
            
            logWebhook("🎉 PAGAMENTO APROVADO AUTOMATICAMENTE: $idTransaction");
            return [
                'status' => 'approved', 
                'amount' => $pagamento['valor'],
                'user_id' => $pagamento['user_id']
            ];
        } else {
            logWebhook("❌ Erro ao processar: Update=" . json_encode($updateResult) . ", Balance=" . json_encode($balanceResult));
            return ['error' => 'Falha ao processar pagamento'];
        }
        
    } catch (Exception $e) {
        logWebhook("❌ EXCEPTION: " . $e->getMessage());
        return ['error' => 'Erro interno: ' . $e->getMessage()];
    }
}

// ✅ FUNÇÃO MELHORADA: Identificar banco
function verificarBanco($webhookData) {
    // PixUp - Múltiplas verificações
    if (isset($webhookData['requestBody']['debitParty']['bank']) || 
        isset($webhookData['requestBody']['transactionId']) ||
        (isset($webhookData['transactionId']) && strpos(json_encode($webhookData), 'pixup') !== false)) {
        return 'PixUp Soluções de Pagamentos';
    }
    // SuitPay
    elseif (isset($webhookData['statusTransaction']) || 
            (isset($webhookData['paymentCode']) && strpos($webhookData['paymentCode'], 'SUITPAY') !== false)) {
        return 'SuitPay Instituição';
    }
    // FivePay
    elseif (isset($webhookData['paymentMethod']) && isset($webhookData['merchant']) && 
            strpos(strtolower($webhookData['merchant']), 'five') !== false) {
        return 'FIVEPAY';
    }
    // VenturePay
    elseif (isset($webhookData['payment_method']) && $webhookData['payment_method'] === 'pix') {
        return 'VENTUREPAY';
    }
    // SyncPay 
    elseif (isset($webhookData['idTransaction']) && isset($webhookData['status']) && 
            !isset($webhookData['statusTransaction'])) {
        return 'SYNCPAY';
    }

    logWebhook("❌ Banco não identificado. Dados completos:");
    logWebhook(json_encode($webhookData, JSON_PRETTY_PRINT));
    return 'Banco desconhecido';
}

// ✅ FUNÇÕES AUXILIARES IMPLEMENTADAS
function updatePaymentStatus($idTransaction, $status, $dueDate) {
    try {
        $conn = getDBConnection();
        $sql = "UPDATE pagamentos SET status = :status, data = :data WHERE cod_referencia = :idTransaction";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':data', $dueDate);
        $stmt->bindParam(':idTransaction', $idTransaction);
        $stmt->execute();
        
        $rowsAffected = $stmt->rowCount();
        return ['rowsAffected' => $rowsAffected, 'success' => true];
    } catch (PDOException $e) {
        return ['error' => $e->getMessage()];
    }
}

function updateUserBalance($user_id, $amount) {
    try {
        $conn = getDBConnection();
        
        // Buscar saldo atual
        $sqlSelect = "SELECT saldo FROM usuarios WHERE id = :user_id";
        $stmtSelect = $conn->prepare($sqlSelect);
        $stmtSelect->bindParam(':user_id', $user_id);
        $stmtSelect->execute();
        $currentBalance = $stmtSelect->fetchColumn();

        if ($currentBalance === false) {
            return ['error' => 'Usuário não encontrado'];
        }

        $newBalance = $currentBalance + $amount;

        // Atualizar saldo
        $sqlUpdate = "UPDATE usuarios SET saldo = :newBalance WHERE id = :user_id";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        $stmtUpdate->bindParam(':newBalance', $newBalance);
        $stmtUpdate->bindParam(':user_id', $user_id);
        $stmtUpdate->execute();
        
        return [
            'success' => true,
            'oldBalance' => $currentBalance,
            'newBalance' => $newBalance,
            'added' => $amount
        ];
    } catch (PDOException $e) {
        return ['error' => $e->getMessage()];
    }
}

function logWebhook($data, $logFile = 'webhook_log.txt') {
    $message = is_string($data) ? $data : json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $logEntry = "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}
?>