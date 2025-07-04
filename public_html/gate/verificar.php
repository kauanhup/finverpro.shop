<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
session_start();

date_default_timezone_set('America/Sao_Paulo');
$dueDate = date('Y-m-d H:i:s');

// ✅ INCLUIR CONEXÃO DO BANCO
require_once '../bank/db.php';

// Array para armazenar logs em tempo real
$logs = [];
function addLog($message) {
    global $logs;
    $timestamp = date('Y-m-d H:i:s');
    $logs[] = "[$timestamp] $message";
    // Também salva no arquivo
    file_put_contents('verificar_debug.log', "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

addLog("🚀 VERIFICAR.PHP INICIADO - PIXUP DEBUG");

// ✅ VERIFICAR SE PUSHER EXISTE
if (file_exists('./vendor/autoload.php')) {
    addLog("✅ Vendor/autoload.php encontrado");
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
        addLog("✅ Pusher configurado com sucesso");
    } catch (Exception $e) {
        addLog("❌ ERRO no Pusher: " . $e->getMessage());
        $pusher = null;
    }
} else {
    addLog("❌ vendor/autoload.php NÃO ENCONTRADO!");
    $pusher = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    addLog("📨 Requisição POST recebida");
    
    $jsonRecebido = file_get_contents('php://input');
    addLog("📄 JSON recebido: " . $jsonRecebido);
    
    $dadosRecebidos = json_decode($jsonRecebido, true);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        addLog("✅ JSON válido decodificado");
        
        if (isset($dadosRecebidos['id'])) {
            $externalReference = $dadosRecebidos['id'];
            addLog("🔍 Verificando pagamento PixUp: $externalReference");
            
            // Buscar informações do pagamento no banco
            try {
                $conn = getDBConnection();
                addLog("✅ Conexão com banco estabelecida");
                
                $stmt = $conn->prepare("SELECT * FROM pagamentos WHERE cod_referencia = :ref");
                $stmt->execute(['ref' => $externalReference]);
                $pagamento = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$pagamento) {
                    addLog("❌ Pagamento NÃO ENCONTRADO no banco: $externalReference");
                    echo json_encode(['status' => 'Pagamento não encontrado.', 'logs' => $logs]);
                    exit;
                }
                
                addLog("✅ Pagamento encontrado:");
                addLog("   - Banco: {$pagamento['Banco']}");
                addLog("   - Status atual: {$pagamento['status']}");
                addLog("   - User ID: {$pagamento['user_id']}");
                addLog("   - Valor: R$ {$pagamento['valor']}");
                addLog("   - Criado em: {$pagamento['data']}");
                
                // Se já foi aprovado, retornar sucesso
                if ($pagamento['status'] === 'Aprovado') {
                    addLog("✅ Pagamento JÁ APROVADO anteriormente");
                    echo json_encode(['status' => 'Pagamento aprovado.', 'logs' => $logs]);
                    exit;
                }
                
                // ✅ VERIFICAR PIXUP ESPECIFICAMENTE
                addLog("🔧 Iniciando verificação PixUp - ID: $externalReference");
                verificarPixUp($pagamento, $pusher, $dueDate);
                
            } catch (Exception $e) {
                addLog("❌ ERRO DE BANCO: " . $e->getMessage());
                echo json_encode(['error' => 'Erro de banco: ' . $e->getMessage(), 'logs' => $logs]);
            }
            
        } else {
            addLog("❌ Campo 'id' não encontrado no JSON");
            echo json_encode(['error' => 'ID não fornecido.', 'logs' => $logs]);
        }
    } else {
        addLog("❌ JSON INVÁLIDO - Erro: " . json_last_error_msg());
        echo json_encode(['error' => 'JSON inválido.', 'logs' => $logs]);
    }
} else {
    addLog("❌ Método não é POST: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['error' => 'Método não permitido, use POST', 'logs' => $logs]);
}

// ✅ VERIFICAÇÃO PIXUP MELHORADA
function verificarPixUp($pagamento, $pusher, $dueDate) {
    global $logs;
    addLog("🔍 PIXUP - Iniciando verificação detalhada");
    addLog("🔍 PIXUP - Transaction ID: {$pagamento['cod_referencia']}");
    
    // ✅ BUSCAR CREDENCIAIS DO PIXUP NO BANCO
    $gateway = buscarGatewayConfig('PIXUP');
    if (!$gateway) {
        addLog("❌ PIXUP - Credenciais NÃO ENCONTRADAS na tabela gateway");
        echo json_encode(['status' => 'Erro de configuração PixUp.', 'logs' => $logs]);
        return;
    }
    
    addLog("✅ PIXUP - Credenciais encontradas:");
    addLog("   - Client ID: " . substr($gateway['client_id'], 0, 15) . "...");
    addLog("   - Client Secret: " . substr($gateway['client_secret'], 0, 15) . "...");
    addLog("   - Status: " . $gateway['status']);
    
    // ✅ AUTENTICAR NO PIXUP
    $credentials = $gateway['client_id'] . ':' . $gateway['client_secret'];
    $base64_credentials = base64_encode($credentials);
    
    addLog("🔐 PIXUP - Tentando autenticar...");
    addLog("🔐 PIXUP - Credentials (base64): " . substr($base64_credentials, 0, 20) . "...");
    
    $authResponse = sendPost('https://api.pixupbr.com/v2/oauth/token', '', [
        'Authorization: Basic ' . $base64_credentials,
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    addLog("📡 PIXUP - Resposta de autenticação (primeiros 300 chars): " . substr($authResponse, 0, 300));
    
    $authData = json_decode($authResponse, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        addLog("❌ PIXUP - Erro ao decodificar JSON de auth: " . json_last_error_msg());
        addLog("❌ PIXUP - Resposta completa: " . $authResponse);
        echo json_encode(['status' => 'Erro de decodificação PixUp.', 'logs' => $logs]);
        return;
    }
    
    $access_token = $authData['access_token'] ?? null;
    
    if (!$access_token) {
        addLog("❌ PIXUP - FALHA NA AUTENTICAÇÃO");
        addLog("   - Resposta completa: " . json_encode($authData));
        
        // ✅ VERIFICAR SE TEM ERRO ESPECÍFICO
        if (isset($authData['error'])) {
            addLog("   - Erro: " . $authData['error']);
        }
        if (isset($authData['error_description'])) {
            addLog("   - Descrição: " . $authData['error_description']);
        }
        
        echo json_encode(['status' => 'Erro de autenticação PixUp.', 'auth_response' => $authData, 'logs' => $logs]);
        return;
    }
    
    addLog("✅ PIXUP - Token obtido com sucesso: " . substr($access_token, 0, 20) . "...");
    
    // 🔧 CORREÇÃO: TENTAR DIFERENTES ENDPOINTS E MÉTODOS
    addLog("🔍 PIXUP - Testando diferentes endpoints para consulta...");
    
    // ENDPOINT 1: Tentar POST em /v2/pix/status
    $endpoint1 = 'https://api.pixupbr.com/v2/pix/status';
    $bodyData1 = json_encode(['transactionId' => $pagamento['cod_referencia']]);
    addLog("🧪 Testando: POST $endpoint1");
    
    $response1 = sendPost($endpoint1, $bodyData1, [
        "Authorization: Bearer $access_token",
        'Content-Type: application/json'
    ], 'POST');
    
    addLog("📡 Resposta endpoint 1: " . substr($response1, 0, 200));
    $data1 = json_decode($response1, true);
    
    if (json_last_error() === JSON_ERROR_NONE && isset($data1['status'])) {
        addLog("✅ SUCESSO no endpoint 1!");
        processarResposta($data1, $pagamento, $pusher, $dueDate);
        return;
    }
    
    // ENDPOINT 2: Tentar POST em /v2/transaction/status  
    $endpoint2 = 'https://api.pixupbr.com/v2/transaction/status';
    $bodyData2 = json_encode(['id' => $pagamento['cod_referencia']]);
    addLog("🧪 Testando: POST $endpoint2");
    
    $response2 = sendPost($endpoint2, $bodyData2, [
        "Authorization: Bearer $access_token",
        'Content-Type: application/json'
    ], 'POST');
    
    addLog("📡 Resposta endpoint 2: " . substr($response2, 0, 200));
    $data2 = json_decode($response2, true);
    
    if (json_last_error() === JSON_ERROR_NONE && isset($data2['status'])) {
        addLog("✅ SUCESSO no endpoint 2!");
        processarResposta($data2, $pagamento, $pusher, $dueDate);
        return;
    }
    
    // ENDPOINT 3: Tentar GET (mesmo com 405, vamos ver a resposta)
    $endpoint3 = 'https://api.pixupbr.com/v2/pix/' . $pagamento['cod_referencia'];
    addLog("🧪 Testando: GET $endpoint3 (pode dar 405 mas vamos ver)");
    
    $response3 = sendPost($endpoint3, '', [
        "Authorization: Bearer $access_token",
        'Content-Type: application/json'
    ], 'GET');
    
    addLog("📡 Resposta endpoint 3: " . substr($response3, 0, 200));
    
    // ENDPOINT 4: Listar todas as transações
    $endpoint4 = 'https://api.pixupbr.com/v2/pix';
    addLog("🧪 Testando: GET $endpoint4 (listar transações)");
    
    $response4 = sendPost($endpoint4, '', [
        "Authorization: Bearer $access_token",
        'Content-Type: application/json'
    ], 'GET');
    
    addLog("📡 Resposta endpoint 4: " . substr($response4, 0, 300));
    $data4 = json_decode($response4, true);
    
    if (json_last_error() === JSON_ERROR_NONE && is_array($data4)) {
        addLog("🔍 Procurando transação na lista...");
        foreach ($data4 as $transaction) {
            if (isset($transaction['transactionId']) && $transaction['transactionId'] === $pagamento['cod_referencia']) {
                addLog("✅ ENCONTROU na lista! Processando...");
                processarResposta($transaction, $pagamento, $pusher, $dueDate);
                return;
            }
        }
        addLog("❌ Não encontrou na lista de transações");
    }
    
    // Se chegou até aqui, nenhum endpoint funcionou
    addLog("❌ PIXUP - Nenhum endpoint de consulta funcionou");
    addLog("📋 Respostas obtidas:");
    addLog("   - Endpoint 1: " . substr($response1 ?? 'null', 0, 100));
    addLog("   - Endpoint 2: " . substr($response2 ?? 'null', 0, 100));
    addLog("   - Endpoint 3: " . substr($response3 ?? 'null', 0, 100));
    addLog("   - Endpoint 4: " . substr($response4 ?? 'null', 0, 100));
    
    echo json_encode([
        'status' => 'Não foi possível consultar status no PixUp.',
        'tested_endpoints' => [
            'endpoint1' => $endpoint1,
            'endpoint2' => $endpoint2, 
            'endpoint3' => $endpoint3,
            'endpoint4' => $endpoint4
        ],
        'logs' => $logs
    ]);
}

// ✅ FUNÇÃO: Processar resposta da API
function processarResposta($data, $pagamento, $pusher, $dueDate) {
    global $logs;
    addLog("📊 PIXUP - Processando resposta da API");
    addLog("📊 PIXUP - Keys disponíveis: " . implode(', ', array_keys($data)));
    addLog("📊 PIXUP - Dados completos: " . json_encode($data));
    
    if (isset($data['status'])) {
        addLog("📊 PIXUP - Status retornado: " . $data['status']);
        
        // ✅ VERIFICAR DIFERENTES POSSIBILIDADES DE STATUS
        $status = strtolower(trim($data['status']));
        addLog("📊 PIXUP - Status normalizado: '$status'");
        
        if ($status === 'approved' || $status === 'paid' || $status === 'completed' || $status === 'success') {
            addLog("🎉 PIXUP - STATUS APROVADO! Processando pagamento...");
            aprovarPagamento($pagamento, $pusher, $dueDate);
        } else {
            addLog("⏳ PIXUP - Status ainda não aprovado: $status");
            
            // ✅ VERIFICAR SE TEM CAMPO DE DATA DE PAGAMENTO
            if (isset($data['paid_at']) && !empty($data['paid_at'])) {
                addLog("💰 PIXUP - Campo paid_at encontrado: " . $data['paid_at']);
                addLog("🎉 PIXUP - Considerando como PAGO baseado no paid_at!");
                aprovarPagamento($pagamento, $pusher, $dueDate);
            } else {
                addLog("⏳ PIXUP - Campo paid_at vazio ou inexistente");
                echo json_encode([
                    'status' => 'Pagamento ainda não aprovado.', 
                    'api_status' => $data['status'],
                    'api_data' => $data,
                    'logs' => $logs
                ]);
            }
        }
    } else {
        addLog("❌ PIXUP - Campo 'status' não encontrado na resposta");
        echo json_encode([
            'status' => 'Status não encontrado na resposta da API.', 
            'api_response' => $data,
            'logs' => $logs
        ]);
    }
}

// ✅ FUNÇÃO: Aprovar pagamento
function aprovarPagamento($pagamento, $pusher, $dueDate) {
    global $logs;
    addLog("🎉 INICIANDO APROVAÇÃO DO PAGAMENTO PIXUP");
    addLog("   - User: {$pagamento['user_id']}");
    addLog("   - Valor: R$ {$pagamento['valor']}");
    addLog("   - Transaction: {$pagamento['cod_referencia']}");
    
    try {
        // 1. Atualizar status do pagamento
        addLog("📝 Atualizando status do pagamento...");
        $updateResult = updatePaymentStatus($pagamento['cod_referencia'], 'Aprovado', $dueDate);
        addLog("✅ Status atualizado: " . json_encode($updateResult));
        
        // 2. Atualizar saldo do usuário
        addLog("💰 Atualizando saldo do usuário...");
        $balanceResult = updateUserBalance($pagamento['user_id'], $pagamento['valor']);
        addLog("✅ Saldo atualizado: " . json_encode($balanceResult));
        
        // 3. Disparar Pusher
        if ($pusher) {
            addLog("📡 Disparando Pusher...");
            $pusherData = [
                'user_id' => $pagamento['user_id'],
                'transaction_id' => $pagamento['cod_referencia'],
                'amount' => $pagamento['valor'],
                'message' => 'Pagamento PixUp aprovado automaticamente!'
            ];
            
            $pusher->trigger('payment_channel', 'payment_approved', $pusherData);
            addLog("✅ Pusher disparado: " . json_encode($pusherData));
        } else {
            addLog("❌ Pusher não disponível - notificação não enviada");
        }
        
        addLog("🎊 PIXUP - PAGAMENTO PROCESSADO COM SUCESSO TOTAL!");
        echo json_encode([
            'status' => 'Pagamento aprovado.', 
            'amount' => $pagamento['valor'],
            'user_id' => $pagamento['user_id'],
            'logs' => $logs
        ]);
        
    } catch (Exception $e) {
        addLog("❌ ERRO CRÍTICO ao processar pagamento: " . $e->getMessage());
        echo json_encode([
            'status' => 'Erro ao processar pagamento.',
            'error' => $e->getMessage(),
            'logs' => $logs
        ]);
    }
}

// ✅ FUNÇÃO: Buscar gateway config
function buscarGatewayConfig($banco) {
    global $logs;
    addLog("🔍 Buscando configuração do gateway: $banco");
    
    $conn = getDBConnection();
    try {
        $stmt = $conn->prepare("SELECT * FROM gateway WHERE UPPER(banco) = :banco AND status = 'true'");
        $stmt->execute(['banco' => strtoupper($banco)]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            addLog("✅ Configuração encontrada para $banco");
        } else {
            addLog("❌ Nenhuma configuração ativa encontrada para $banco");
        }
        
        return $result;
    } catch (PDOException $e) {
        addLog("❌ Erro ao buscar config do gateway: " . $e->getMessage());
        return false;
    }
}

// ✅ FUNÇÃO: Requisições HTTP CORRIGIDA
function sendPost($url, $data, $headers, $method = 'POST') {
    global $logs;
    addLog("🌐 Enviando requisição $method para: $url");
    if ($data) {
        addLog("📤 Body: " . substr($data, 0, 200));
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    } elseif ($method === 'GET') {
        curl_setopt($ch, CURLOPT_HTTPGET, true);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($response === false) {
        $error = curl_error($ch);
        addLog("❌ Erro cURL: $error");
        curl_close($ch);
        return false;
    }
    
    addLog("✅ Requisição concluída - HTTP Code: $httpCode");
    curl_close($ch);
    return $response;
}

// ✅ FUNÇÕES AUXILIARES
function updatePaymentStatus($idTransaction, $status, $dueDate) {
    global $logs;
    $conn = getDBConnection();
    try {
        $sql = "UPDATE pagamentos SET status = :status, data = :data WHERE cod_referencia = :idTransaction";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':data', $dueDate);
        $stmt->bindParam(':idTransaction', $idTransaction);
        $stmt->execute();
        
        $rowsAffected = $stmt->rowCount();
        addLog("✅ Status atualizado - Linhas afetadas: $rowsAffected");
        return ['rowsAffected' => $rowsAffected];
    } catch (PDOException $e) {
        addLog("❌ Erro ao atualizar status: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

function updateUserBalance($user_id, $amount) {
    global $logs;
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
        
        addLog("✅ Saldo atualizado - User: $user_id - De: R$ $currentBalance para: R$ $newBalance");
        return ['newBalance' => $newBalance, 'oldBalance' => $currentBalance];
    } catch (PDOException $e) {
        addLog("❌ Erro ao atualizar saldo: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}
?>