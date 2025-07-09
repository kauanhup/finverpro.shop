<?php
// ============================================================================
// API.PHP ATUALIZADO - USANDO WEBHOOK_URL DO BANCO
// ============================================================================

// Incluir a conexão centralizada
require_once '../bank/db.php';

// ============================================================================
// CARREGAR GATEWAYS DO BANCO (incluindo webhook_url)
// ============================================================================
function carregarGatewaysAtivos() {
    $conn = getDBConnection();
    try {
        // ✅ ATUALIZADO: Incluir webhook_url na consulta
        $stmt = $conn->prepare("SELECT * FROM gateway WHERE status = 'true'");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError("Erro ao carregar gateways: " . $e->getMessage());
        return [];
    }
}

function escolherGatewayAleatorio($gateways) {
    if (!empty($gateways)) {
        return $gateways[array_rand($gateways)];
    }
    return null;
}

// ============================================================================
// INÍCIO DO PROCESSAMENTO
// ============================================================================
session_start();
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Carregar gateways ativos do banco
$gatewaysAtivos = carregarGatewaysAtivos();
$gatewayEscolhido = escolherGatewayAleatorio($gatewaysAtivos);

if (!$gatewayEscolhido) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Nenhum gateway de pagamento está ativo no momento.'
    ]);
    exit;
}

// ✅ VALIDAR SE TEM WEBHOOK_URL CONFIGURADA
if (empty($gatewayEscolhido['webhook_url'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Gateway ' . $gatewayEscolhido['banco'] . ' não tem webhook URL configurada.'
    ]);
    exit;
}

date_default_timezone_set('America/Sao_Paulo');
$dueDate = date('Y-m-d H:i:s');
$user_id = $_SESSION['user_id'];
$numero_telefone = getUserPhoneNumber($user_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recebendo JSON do corpo da requisição
    $jsonRecebido = file_get_contents('php://input');
    $dadosRecebidos = json_decode($jsonRecebido, true); 
    
    // Processar pagamento baseado no gateway escolhido
    processarPagamento($gatewayEscolhido, $dadosRecebidos, $user_id, $numero_telefone, $dueDate);
    
} else {
    echo json_encode(['erro' => 'Método não permitido, use POST']);
}

// ============================================================================
// FUNÇÃO PARA PROCESSAR PAGAMENTO
// ============================================================================
function processarPagamento($gateway, $dadosRecebidos, $user_id, $numero_telefone, $dueDate) {
    $banco = strtoupper($gateway['banco']);
    
    switch ($banco) {
        case 'SYNCPAY':
            processarSyncPay($gateway, $dadosRecebidos, $user_id, $numero_telefone, $dueDate);
            break;
            
        case 'FIVEPAY':
            processarFivePay($gateway, $dadosRecebidos, $user_id, $numero_telefone, $dueDate);
            break;
            
        case 'SUITPAY':
            processarSuitPay($gateway, $dadosRecebidos, $user_id, $numero_telefone, $dueDate);
            break;
            
        case 'PIXUP':
            processarPixUp($gateway, $dadosRecebidos, $user_id, $numero_telefone, $dueDate);
            break;
            
        case 'VENTUREPAY':
            processarVenturePay($gateway, $dadosRecebidos, $user_id, $numero_telefone, $dueDate);
            break;
            
        default:
            echo json_encode([
                'status' => 'error',
                'message' => 'Gateway não implementado: ' . $banco
            ]);
    }
}

// ============================================================================
// IMPLEMENTAÇÕES DOS GATEWAYS - ATUALIZADAS COM WEBHOOK_URL DO BANCO
// ============================================================================

function processarSyncPay($gateway, $dadosRecebidos, $user_id, $numero_telefone, $dueDate) {
    $apiKey = $gateway['client_secret'];
    // ✅ USANDO WEBHOOK_URL DO BANCO
    $webhookUri = $gateway['webhook_url'];

    $payload = [
        'amount' => $dadosRecebidos['valor'],
        'customer' => [
            'name' => $dadosRecebidos['nome'] ?? 'Usuário ' . rand(1000, 9999),
            'email' => $dadosRecebidos['email'] ?? 'usuario' . rand(1000, 9999) . '@exemplo.com',
            'cpf' => $dadosRecebidos['cpf'] ?? gerarCPF(),
            'phone' => $numero_telefone,
        ],
        'pix' => [
            'expiresInDays' => 2,
        ],
        'postbackUrl' => $webhookUri,
        'ip' => $_SERVER['REMOTE_ADDR']
    ];

    $response = sendpost('https://api.syncpay.pro/v1/gateway/api/', json_encode($payload), [
        "Authorization: Basic " . base64_encode($apiKey),
        "Content-Type: application/json",
        "Accept: application/json"
    ]);

    file_put_contents('syncpay_debug.log', $response . PHP_EOL, FILE_APPEND);
    $res = json_decode($response, true);

    if (isset($res['paymentCode'])) {
        dbOperation('insert', 'pagamentos', [
            'user_id' => $user_id,
            'valor' => $dadosRecebidos['valor'],
            'cod_referencia' => $res['idTransaction'],
            'status' => 'Pendente',
            'data' => $dueDate,
            'Banco' => 'SYNCPAY',
            'numero_telefone' => $numero_telefone,
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Solicitação de depósito enviada com sucesso.',
            'copiarTexto' => $res['paymentCode'],
            'externalReference' => $res['idTransaction'],
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Erro ao gerar Pix.',
            'details' => $res,
        ]);
    }
}

function processarFivePay($gateway, $dadosRecebidos, $user_id, $numero_telefone, $dueDate) {
    $apiKey = $gateway['client_secret'];
    // ✅ USANDO WEBHOOK_URL DO BANCO
    $webhookUri = $gateway['webhook_url'];
    
    $response = sendpost('https://api.fivepay.net/v1/gateway/', json_encode([
        'amount' => $dadosRecebidos['valor'],
        'client' => [
            'name' => 'Apl Investimentos LTDA',
            'document' => '47288489906',
            'telefone' => $numero_telefone,
            'email' => 'Apple.br@gmail.com',
        ],
        'api-key' => $apiKey,
        'postback' => $webhookUri,
    ]), [
        'Content-Type: application/json',
        'Accept: application/json',
    ]);

    $res = json_decode($response, true);

    if (isset($res['paymentCode'])) {
        dbOperation('insert', 'pagamentos', [
            'user_id' => $user_id,
            'valor' => $dadosRecebidos['valor'],
            'cod_referencia' => $res['idTransaction'],
            'status' => 'Pendente',
            'data' => $dueDate,
            'Banco' => 'FIVEPAY',
            'numero_telefone' => $numero_telefone,
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Solicitação de depósito enviada com sucesso.',
            'copiarTexto' => $res['paymentCode'],
            'externalReference' => $res['idTransaction'],
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Erro ao gerar Pix.',
            'details' => $res,
        ]);
    }
}

function processarSuitPay($gateway, $dadosRecebidos, $user_id, $numero_telefone, $dueDate) {
    // ✅ USANDO WEBHOOK_URL DO BANCO
    $webhookUri = $gateway['webhook_url'];
    
    $response = sendpost('https://ws.suitpay.app/api/v1/gateway/request-qrcode', json_encode([
        'requestNumber' => "Apple.br@gmail.com",
        'dueDate' => $dueDate,
        'amount' => $dadosRecebidos['valor'],
        'client' => [
            'name' => "Apl Investimentos LTDA",
            'email' => "Apple.br@gmail.com",
            'document' => "47288489906",
        ],
        'callbackUrl' => $webhookUri,
    ]), [
        'Content-Type: application/json',
        "ci: {$gateway['client_id']}",
        "cs: {$gateway['client_secret']}"
    ]);
    
    $res = json_decode($response, true);
    if (isset($res['paymentCode'])) { 
        dbOperation('insert', 'pagamentos', [
            'user_id' => $user_id,
            'valor' => $dadosRecebidos['valor'],
            'cod_referencia' => $res['idTransaction'],
            'status' => 'Pendente',
            'data' => $dueDate,
            'Banco' => 'SUITPAY',
            'numero_telefone' => $numero_telefone 
        ]);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Solicitação de depósito enviada com sucesso.',
            'copiarTexto' => $res['paymentCode'],
            'externalReference' => $res['idTransaction'],
        ]);
    } else {
        echo json_encode(['erro' => $res]);
    }
}

function processarPixUp($gateway, $dadosRecebidos, $user_id, $numero_telefone, $dueDate) {
    $credentials = $gateway['client_id'] . ':' . $gateway['client_secret'];
    $base64_credentials = base64_encode($credentials);
    // ✅ USANDO WEBHOOK_URL DO BANCO
    $webhookUri = $gateway['webhook_url'];
    
    $autenticacao = sendpost('https://api.pixupbr.com/v2/oauth/token', [], [
        'Authorization: Basic '.$base64_credentials,
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    $data = json_decode($autenticacao, true);
    $access_token = $data['access_token'] ?? null;
    
    if (!$access_token) {
        echo json_encode(['erro' => 'Token de acesso não obtido']);
        return;
    }
    
    $response = sendpost('https://api.pixupbr.com/v2/pix/qrcode', json_encode([
        "amount" => $dadosRecebidos['valor'], 
        "postbackUrl" => $webhookUri, 
        "payer" => [
            "name" => "Apl Investimentos LTDA",
            "document" => "47288489906",
            "email" => "Apple.br@gmail.com"
        ]
    ]), [
        "Authorization: Bearer {$access_token}",
        'Content-Type: application/json',
        'Accept: application/json' 
    ]);
    
    $res = json_decode($response, true);
    if (isset($res['qrcode'])) {
        dbOperation('insert', 'pagamentos', [
            'user_id' => $user_id,
            'valor' => $dadosRecebidos['valor'],
            'cod_referencia' => $res['transactionId'],
            'status' => 'Pendente',
            'data' => $dueDate,
            'Banco' => 'PIXUP',
            'numero_telefone' => $numero_telefone 
        ]);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Solicitação de depósito enviada com sucesso.',
            'copiarTexto' => $res['qrcode'],
            'externalReference' => $res['transactionId'],
        ]);
    } else {
        echo json_encode(['erro' => $res]);
    }
}

function processarVenturePay($gateway, $dadosRecebidos, $user_id, $numero_telefone, $dueDate) {
    $token = '73ca08583cf493090ec00368f168011c3169a5f2f3c3fd84751f8d92b548354376ab82c574d004ff';
    $valorEmCentavos = $dadosRecebidos['valor'] * 100;
    // ✅ USANDO WEBHOOK_URL DO BANCO
    $webhookUri = $gateway['webhook_url'];

    $webhookResponse = sendpost('https://venturepay.com.br/api/webhook/transaction/', json_encode([
        'url' => $webhookUri,
    ]), [
        "Authorization: {$token}",
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    $transactionResponse = sendpost('https://venturepay.com.br/api/create/transaction/', json_encode([
        'amount' => $valorEmCentavos, 
        'nome' => 'Apl Investimentos LTDA',
        'cpf' => '12962752217', 
        'email' => 'exemplo@gmail.com',
        'number_phone' => '999999999', 
        'area_code' => '11', 
    ]), [
        "Authorization: {$token}",
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    $res = json_decode($transactionResponse, true);

    if (isset($res['last_transaction']['qr_code'])) {
        dbOperation('insert', 'pagamentos', [
            'user_id' => $user_id,
            'valor' => $dadosRecebidos['valor'], 
            'cod_referencia' => $res['id'],
            'status' => 'Pendente',
            'data' => $dueDate,
            'Banco' => 'VENTUREPAY',
            'numero_telefone' => $numero_telefone 
        ]);
        
        echo json_encode([
            'status' => 'success',
            'webhook_response' => $webhookResponse,
            'message' => 'Solicitação de depósito enviada com sucesso.',
            'copiarTexto' => $res['last_transaction']['qr_code'],
            'externalReference' => $res['id'],
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Falha ao criar a transação.',
            'details' => $res 
        ]);
    }
}

// ============================================================================
// FUNÇÕES AUXILIARES (mantidas do código original)
// ============================================================================

function gerarCPF() {
    $n1 = rand(0, 9); $n2 = rand(0, 9); $n3 = rand(0, 9);
    $n4 = rand(0, 9); $n5 = rand(0, 9); $n6 = rand(0, 9);
    $n7 = rand(0, 9); $n8 = rand(0, 9); $n9 = rand(0, 9);

    $d1 = 10 * $n1 + 9 * $n2 + 8 * $n3 + 7 * $n4 + 6 * $n5 + 5 * $n6 + 4 * $n7 + 3 * $n8 + 2 * $n9;
    $d1 = 11 - ($d1 % 11);
    $d1 = ($d1 >= 10) ? 0 : $d1;

    $d2 = 11 * $n1 + 10 * $n2 + 9 * $n3 + 8 * $n4 + 7 * $n5 + 6 * $n6 + 5 * $n7 + 4 * $n8 + 3 * $n9 + 2 * $d1;
    $d2 = 11 - ($d2 % 11);
    $d2 = ($d2 >= 10) ? 0 : $d2;

    return "$n1$n2$n3.$n4$n5$n6.$n7$n8$n9-$d1$d2";
}

function getUserPhoneNumber($user_id) {
    $conn = getDBConnection();
    try {
        $sql = "SELECT telefone FROM usuarios WHERE id = :user_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        logError("Erro ao buscar número de telefone: " . $e->getMessage());
        return null;
    }
}

function sendpost($url, $data, $headers) {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    
    $response = curl_exec($curl);
    if ($response === false) {
        logError('Erro na requisição cURL: ' . curl_error($curl));
        return false;
    }
    curl_close($curl);
    return $response;
}

function updateUserBalance($user_id, $amount) {
    $conn = getDBConnection();
    try {
        $sqlSelect = "SELECT saldo FROM usuarios WHERE id = :user_id";
        $stmtSelect = $conn->prepare($sqlSelect);
        $stmtSelect->bindParam(':user_id', $user_id);
        $stmtSelect->execute();
        $currentBalance = $stmtSelect->fetchColumn();

        $newBalance = $currentBalance + $amount;

        $sqlUpdate = "UPDATE usuarios SET saldo = :newBalance WHERE id = :user_id";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        $stmtUpdate->bindParam(':newBalance', $newBalance);
        $stmtUpdate->bindParam(':user_id', $user_id);

        $stmtUpdate->execute();
        return ['newBalance' => $newBalance];
    } catch (PDOException $e) {
        logError($e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

function dbOperation($operation, $table, $data = [], $conditions = '', $columns = '*') {
    $conn = getDBConnection();
    try {
        switch (strtolower($operation)) {
            case 'insert':
                $columns = implode(", ", array_keys($data));
                $placeholders = ":" . implode(", :", array_keys($data));
                $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
                break;

            case 'select':
                $sql = "SELECT $columns FROM $table";
                if ($conditions) $sql .= " WHERE $conditions";
                break;

            case 'update':
                $set = '';
                foreach ($data as $key => $value) {
                    $set .= "$key = :$key, ";
                }
                $set = rtrim($set, ', ');
                $sql = "UPDATE $table SET $set";
                if ($conditions) $sql .= " WHERE $conditions";
                break;

            case 'delete':
                $sql = "DELETE FROM $table";
                if ($conditions) $sql .= " WHERE $conditions";
                break;

            default:
                throw new Exception('Operação desconhecida');
        }

        $stmt = $conn->prepare($sql);

        if ($operation == 'insert' || $operation == 'update') {
            foreach ($data as $key => &$value) {
                $stmt->bindParam(":$key", $value);
            }
        }

        $stmt->execute();

        if ($operation == 'select') {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $stmt->rowCount();
    } catch (Exception $e) {
        logError($e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

function logError($message) {
    file_put_contents('error_log.txt', date('Y-m-d H:i:s') . " - " . $message . PHP_EOL, FILE_APPEND);
}
?>