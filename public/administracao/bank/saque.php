<?php
require_once __DIR__ . '/db.php';

// ============================================================================
// SISTEMA DE SAQUE MELHORADO - TODOS OS GATEWAYS E TIPOS PIX CORRIGIDO
// ============================================================================

// Função global de log
function logDebugGlobal($message, $logFile = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    
    // Log principal
    $mainLogFile = $logFile ?? __DIR__ . '/debug_saque.log';
    file_put_contents($mainLogFile, $logMessage, FILE_APPEND);
    
    // Log unificado (todos os saques)
    file_put_contents(__DIR__ . '/saques_unified.log', $logMessage, FILE_APPEND);
}

function efetuarSaque($id)
{
    logDebugGlobal("🚀 INICIANDO SAQUE ID: $id");
    
    try {
        $conn = getDBConnection();
        logDebugGlobal("✅ Conexão com banco estabelecida");
        
        // 1. Buscar gateway ativo (incluindo webhook_url)
        logDebugGlobal("🔍 Buscando gateway ativo...");
        $stmt = $conn->prepare("SELECT * FROM gateway WHERE status = 'true' ORDER BY RAND() LIMIT 1");
        $stmt->execute();
        $gateway = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$gateway) {
            logDebugGlobal("❌ ERRO: Nenhum gateway ativo encontrado");
            return 'Erro: Nenhum gateway de pagamento está ativo. Entre em contato com o suporte.';
        }
        
        logDebugGlobal("✅ Gateway encontrado: " . $gateway['banco']);
        logDebugGlobal("   - Client ID: " . substr($gateway['client_id'] ?? 'N/A', 0, 10) . "...");
        logDebugGlobal("   - Client Secret: " . substr($gateway['client_secret'] ?? 'N/A', 0, 10) . "...");
        logDebugGlobal("   - Webhook URL: " . ($gateway['webhook_url'] ?? 'NÃO CONFIGURADA'));
        logDebugGlobal("   - Status: " . $gateway['status']);
        
        // 2. Buscar dados do saque
        logDebugGlobal("🔍 Buscando dados do saque ID: $id");
        $stmt = $conn->prepare("SELECT * FROM operacoes_financeiras WHERE id = :id AND tipo = 'saque'");
        $stmt->execute(['id' => $id]);
        $saque = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$saque) {
            logDebugGlobal("❌ ERRO: Saque não encontrado. ID: $id");
            return 'Erro: Saque não encontrado.';
        }
        
        logDebugGlobal("✅ Saque encontrado:");
        logDebugGlobal("   - User ID: " . $saque['user_id']);
        logDebugGlobal("   - Valor: R$ " . $saque['valor']);
        logDebugGlobal("   - Nome: " . $saque['nome_titular']);
        logDebugGlobal("   - Chave PIX: " . $saque['chave_pix']);
        logDebugGlobal("   - Tipo PIX: " . $saque['tipo_pix']);
        logDebugGlobal("   - Status atual: " . $saque['status']);
        
        // 3. Validações
        if ($saque['status'] === 'Aprovado') {
            logDebugGlobal("⚠️ AVISO: Saque já foi aprovado anteriormente");
            return 'Saque já foi processado anteriormente.';
        }
        
        if ($saque['valor'] <= 0) {
            logDebugGlobal("❌ ERRO: Valor inválido: " . $saque['valor']);
            return 'Erro: Valor do saque inválido.';
        }
        
        // 4. Processar saque baseado no gateway
        $banco = strtoupper($gateway['banco']);
        logDebugGlobal("🔧 Processando gateway: $banco");
        
        switch ($banco) {
            case 'FIVEPAY':
                logDebugGlobal("📡 Chamando processarSaqueFivePay...");
                return processarSaqueFivePay($gateway, $saque);
                
            case 'SYNCPAY':
                logDebugGlobal("📡 Chamando processarSaqueSyncPay...");
                return processarSaqueSyncPay($gateway, $saque);
                
            case 'SUITPAY':
                logDebugGlobal("📡 Chamando processarSaqueSuitPay...");
                return processarSaqueSuitPay($gateway, $saque);
                
            case 'PIXUP':
                logDebugGlobal("📡 Chamando processarSaquePixUp...");
                return processarSaquePixUp($gateway, $saque);
                
            case 'VENTUREPAY':
                logDebugGlobal("📡 Chamando processarSaqueVenturePay...");
                return processarSaqueVenturePay($gateway, $saque);
                
            default:
                logDebugGlobal("❌ ERRO: Gateway não implementado: $banco");
                return "Erro: Gateway $banco não suporta saques ainda.";
        }
        
    } catch (Exception $e) {
        logDebugGlobal("❌ EXCEÇÃO CRÍTICA: " . $e->getMessage());
        logDebugGlobal("   - Arquivo: " . $e->getFile());
        logDebugGlobal("   - Linha: " . $e->getLine());
        return 'Erro interno: ' . $e->getMessage();
    }
}

// ============================================================================
// IMPLEMENTAÇÕES DOS GATEWAYS - PIXUP CORRIGIDO
// ============================================================================

function processarSaquePixUp($gateway, $saque) {
    logDebugGlobal("🔵 PIXUP - Iniciando processamento");
    
    $client_id = $gateway['client_id'];
    $client_secret = $gateway['client_secret'];
    $webhook_url = $gateway['webhook_url'] ?? '';
    
    if (!$client_id || !$client_secret) {
        logDebugGlobal("❌ PIXUP - Credenciais não encontradas");
        return 'Erro: Credenciais do PixUp não configuradas.';
    }
    
    logDebugGlobal("✅ PIXUP - Credenciais encontradas");
    
    try {
        // 1. Autenticar no PixUp
        logDebugGlobal("🔐 PIXUP - Iniciando autenticação...");
        $credentials = $client_id . ':' . $client_secret;
        $base64_credentials = base64_encode($credentials);
        
        $authResponse = sendpost('https://api.pixupbr.com/v2/oauth/token', 'grant_type=client_credentials', [
            'Authorization: Basic ' . $base64_credentials,
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        logDebugGlobal("📥 PIXUP - Resposta auth (primeiros 100 chars): " . substr($authResponse, 0, 100));
        
        $authData = json_decode($authResponse, true);
        $access_token = $authData['access_token'] ?? null;
        
        if (!$access_token) {
            logDebugGlobal("❌ PIXUP - Falha na autenticação");
            logDebugGlobal("   - Resposta completa: " . $authResponse);
            return 'Erro: Falha na autenticação PixUp.';
        }
        
        logDebugGlobal("✅ PIXUP - Token obtido: " . substr($access_token, 0, 20) . "...");
        
        // 2. DETECÇÃO CORRIGIDA DE TIPO PIX
        $tipoPixDetectado = determinarTipoPixCorreto($saque['chave_pix']);
        logDebugGlobal("🔍 PIXUP - Chave analisada: '{$saque['chave_pix']}'");
        logDebugGlobal("🔍 PIXUP - Tipo PIX detectado: $tipoPixDetectado");
        
        // 3. TESTAR MÚLTIPLAS ESTRUTURAS DE API
        $estruturas = [
            // Estrutura 1: Como está atualmente
            [
                'name' => 'Estrutura Atual',
                'url' => 'https://api.pixupbr.com/v2/pix/payment',
                'data' => [
                    'amount' => floatval($saque['valor']),
                    'description' => 'Saque PIX - ID ' . $saque['id'],
                    'external_id' => 'SAQUE_' . $saque['id'],
                    'creditParty' => [
                        'name' => $saque['nome_titular'],
                        'pixKey' => $saque['chave_pix'],
                        'pixKeyType' => $tipoPixDetectado
                    ]
                ]
            ],
            
            // Estrutura 2: Cashout (como FivePay)
            [
                'name' => 'Estrutura Cashout',
                'url' => 'https://api.pixupbr.com/v2/pix/cashout',
                'data' => [
                    'amount' => floatval($saque['valor']),
                    'name' => $saque['nome_titular'],
                    'pixKey' => $saque['chave_pix'],
                    'pixKeyType' => $tipoPixDetectado,
                    'description' => 'Saque PIX - ID ' . $saque['id'],
                    'externalId' => 'SAQUE_' . $saque['id']
                ]
            ],
            
            // Estrutura 3: Transfer
            [
                'name' => 'Estrutura Transfer',
                'url' => 'https://api.pixupbr.com/v2/pix/transfer',
                'data' => [
                    'value' => floatval($saque['valor']),
                    'pixKey' => $saque['chave_pix'],
                    'pixKeyType' => $tipoPixDetectado,
                    'recipientName' => $saque['nome_titular'],
                    'description' => 'Saque PIX - ID ' . $saque['id'],
                    'external_id' => 'SAQUE_' . $saque['id']
                ]
            ],
            
            // Estrutura 4: Simplificada
            [
                'name' => 'Estrutura Simples',
                'url' => 'https://api.pixupbr.com/v2/pix/payment',
                'data' => [
                    'amount' => floatval($saque['valor']),
                    'pixKey' => $saque['chave_pix'],
                    'pixKeyType' => $tipoPixDetectado,
                    'recipient' => [
                        'name' => $saque['nome_titular']
                    ],
                    'external_id' => 'SAQUE_' . $saque['id']
                ]
            ]
        ];
        
        // Adicionar webhook se configurado
        if (!empty($webhook_url)) {
            foreach ($estruturas as &$estrutura) {
                $estrutura['data']['postbackUrl'] = $webhook_url;
                $estrutura['data']['webhook_url'] = $webhook_url; // Backup
            }
            logDebugGlobal("✅ PIXUP - Webhook URL adicionada: $webhook_url");
        }
        
        // Testar cada estrutura
        foreach ($estruturas as $estrutura) {
            logDebugGlobal("🧪 PIXUP - Testando: " . $estrutura['name']);
            logDebugGlobal("📤 PIXUP - URL: " . $estrutura['url']);
            logDebugGlobal("📤 PIXUP - Dados: " . json_encode($estrutura['data'], JSON_PRETTY_PRINT));
            
            $headers = [
                "Authorization: Bearer $access_token",
                'Content-Type: application/json',
                'Accept: application/json'
            ];
            
            $saqueResponse = sendpost($estrutura['url'], json_encode($estrutura['data']), $headers);
            logDebugGlobal("📥 PIXUP - Resposta: " . $saqueResponse);
            
            // Log específico do PixUp
            file_put_contents(__DIR__ . '/log_saque_pixup.log', date('Y-m-d H:i:s') . " - " . $estrutura['name'] . " - " . $saqueResponse . PHP_EOL, FILE_APPEND);
            
            $saqueResult = json_decode($saqueResponse, true);
            
            // Verificar se foi sucesso
            if (isset($saqueResult['transactionId']) || 
                isset($saqueResult['id']) ||
                (isset($saqueResult['status']) && in_array(strtolower($saqueResult['status']), ['approved', 'paid', 'success', 'completed', 'processing']))) {
                
                logDebugGlobal("🎉 PIXUP - SAQUE CRIADO COM SUCESSO!");
                logDebugGlobal("   - Estrutura que funcionou: " . $estrutura['name']);
                logDebugGlobal("   - Transaction ID: " . ($saqueResult['transactionId'] ?? $saqueResult['id'] ?? 'N/A'));
                
                $conn = getDBConnection();
                $stmt_update = $conn->prepare("UPDATE operacoes_financeiras SET status = 'Aprovado', data_processamento = NOW() WHERE id = :id");
                $stmt_update->execute(['id' => $saque['id']]);
                
                logDebugGlobal("✅ PIXUP - Status atualizado no banco");
                return 'OK';
            }
            
            // Se não é erro 401, pode ser que a estrutura esteja quase certa
            if (!str_contains($saqueResponse, '"statusCode":401')) {
                logDebugGlobal("🤔 PIXUP - Resposta diferente de 401 em " . $estrutura['name']);
            }
        }
        
        // Se chegou aqui, houve erro em todas as estruturas
        logDebugGlobal("❌ PIXUP - Todas as estruturas falharam");
        
        $errorMessage = 'Erro no processamento PixUp - Todas as estruturas testadas falharam.';
        return $errorMessage;
        
    } catch (Exception $e) {
        logDebugGlobal("❌ PIXUP - Exceção: " . $e->getMessage());
        return 'Erro interno no PixUp: ' . $e->getMessage();
    }
}

function processarSaqueFivePay($gateway, $saque) {
    logDebugGlobal("🔥 FIVEPAY - Iniciando processamento");
    
    $api_key = $gateway['client_secret'];
    $webhook_url = $gateway['webhook_url'] ?? '';
    
    if (!$api_key) {
        logDebugGlobal("❌ FIVEPAY - API Key não encontrada");
        return 'Erro: Credenciais do FivePay não configuradas.';
    }
    
    logDebugGlobal("✅ FIVEPAY - API Key encontrada: " . substr($api_key, 0, 10) . "...");
    
    $requestData = [
        'amount' => floatval($saque['valor']),
        'name' => $saque['nome_titular'],
        'cpf' => extrairDocumento($saque['chave_pix']),
        'keypix' => $saque['chave_pix'],
        'api-key' => $api_key,
        'external_reference' => 'SAQUE_' . $saque['id']
    ];
    
    if (!empty($webhook_url)) {
        $requestData['webhook_url'] = $webhook_url;
        logDebugGlobal("✅ FIVEPAY - Webhook URL adicionada: $webhook_url");
    }
    
    logDebugGlobal("📤 FIVEPAY - Enviando dados: " . json_encode($requestData));
    
    try {
        $response = sendpost('https://api.fivepay.net/c1/cashout/', json_encode($requestData), [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        
        logDebugGlobal("📥 FIVEPAY - Resposta recebida: " . $response);
        
        file_put_contents(__DIR__ . '/log_saque_fivepay.log', date('Y-m-d H:i:s') . " - " . $response . PHP_EOL, FILE_APPEND);
        
        $resp_json = json_decode($response, true);
        
        $successIndicators = ['paid', 'approved', 'success', 'completed'];
        $responseText = strtolower($response);
        
        $isSuccess = false;
        foreach ($successIndicators as $indicator) {
            if (strpos($responseText, $indicator) !== false) {
                $isSuccess = true;
                break;
            }
        }
        
        if ($isSuccess || (isset($resp_json['status']) && in_array(strtolower($resp_json['status']), $successIndicators))) {
            logDebugGlobal("🎉 FIVEPAY - SAQUE APROVADO!");
            
            $conn = getDBConnection();
            $stmt_update = $conn->prepare("UPDATE operacoes_financeiras SET status = 'Aprovado', data_processamento = NOW() WHERE id = :id");
            $stmt_update->execute(['id' => $saque['id']]);
            
            logDebugGlobal("✅ FIVEPAY - Status atualizado no banco");
            return 'OK';
        }
        
        logDebugGlobal("❌ FIVEPAY - Saque não foi aprovado");
        return $resp_json['message'] ?? $resp_json['error'] ?? 'Erro desconhecido no FivePay';
        
    } catch (Exception $e) {
        logDebugGlobal("❌ FIVEPAY - Exceção: " . $e->getMessage());
        return 'Erro interno FivePay: ' . $e->getMessage();
    }
}

function processarSaqueSyncPay($gateway, $saque) {
    logDebugGlobal("🔄 SYNCPAY - Iniciando processamento");
    
    $api_key = $gateway['client_secret'];
    $webhook_url = $gateway['webhook_url'] ?? '';
    
    if (!$api_key) {
        logDebugGlobal("❌ SYNCPAY - API Key não encontrada");
        return 'Erro: Credenciais do SyncPay não configuradas.';
    }
    
    $tipoPixDetectado = determinarTipoPixCorreto($saque['chave_pix']);
    logDebugGlobal("🔍 SYNCPAY - Tipo PIX detectado: $tipoPixDetectado");
    
    $requestData = [
        'amount' => floatval($saque['valor']),
        'pix_key' => $saque['chave_pix'],
        'pix_key_type' => $tipoPixDetectado,
        'recipient_name' => $saque['nome_titular'],
        'external_reference' => 'SAQUE_' . $saque['id'],
        'description' => 'Saque PIX - ID ' . $saque['id']
    ];
    
    if (!empty($webhook_url)) {
        $requestData['webhook_url'] = $webhook_url;
    }
    
    logDebugGlobal("📤 SYNCPAY - Enviando dados: " . json_encode($requestData));
    
    try {
        $response = sendpost('https://api.syncpay.pro/v1/pix/payment', json_encode($requestData), [
            "Authorization: Basic " . base64_encode($api_key),
            "Content-Type: application/json",
            "Accept: application/json"
        ]);
        
        logDebugGlobal("📥 SYNCPAY - Resposta: " . $response);
        
        $resp_json = json_decode($response, true);
        
        if (isset($resp_json['status']) && in_array(strtolower($resp_json['status']), ['approved', 'paid', 'success'])) {
            logDebugGlobal("🎉 SYNCPAY - SAQUE APROVADO!");
            
            $conn = getDBConnection();
            $stmt_update = $conn->prepare("UPDATE operacoes_financeiras SET status = 'Aprovado', data_processamento = NOW() WHERE id = :id");
            $stmt_update->execute(['id' => $saque['id']]);
            
            return 'OK';
        }
        
        return $resp_json['message'] ?? 'Erro no SyncPay';
        
    } catch (Exception $e) {
        logDebugGlobal("❌ SYNCPAY - Exceção: " . $e->getMessage());
        return 'SyncPay: Entre em contato com o suporte. (' . $e->getMessage() . ')';
    }
}

function processarSaqueSuitPay($gateway, $saque) {
    logDebugGlobal("💼 SUITPAY - Iniciando processamento");
    
    $client_id = $gateway['client_id'];
    $client_secret = $gateway['client_secret'];
    $webhook_url = $gateway['webhook_url'] ?? '';
    
    if (!$client_id || !$client_secret) {
        logDebugGlobal("❌ SUITPAY - Credenciais não encontradas");
        return 'Erro: Credenciais do SuitPay não configuradas.';
    }
    
    $tipoPixDetectado = determinarTipoPixCorreto($saque['chave_pix']);
    logDebugGlobal("🔍 SUITPAY - Tipo PIX detectado: $tipoPixDetectado");
    
    $requestData = [
        'amount' => floatval($saque['valor']),
        'pix_key' => $saque['chave_pix'],
        'pix_key_type' => $tipoPixDetectado,
        'recipient_name' => $saque['nome_titular'],
        'external_reference' => 'SAQUE_' . $saque['id'],
        'description' => 'Saque PIX - ID ' . $saque['id']
    ];
    
    if (!empty($webhook_url)) {
        $requestData['webhook_url'] = $webhook_url;
    }
    
    logDebugGlobal("📤 SUITPAY - Enviando dados: " . json_encode($requestData));
    
    try {
        $response = sendpost('https://ws.suitpay.app/api/v1/pix/payment', json_encode($requestData), [
            'Content-Type: application/json',
            "ci: $client_id",
            "cs: $client_secret"
        ]);
        
        logDebugGlobal("📥 SUITPAY - Resposta: " . $response);
        
        $resp_json = json_decode($response, true);
        
        if (isset($resp_json['status']) && in_array(strtolower($resp_json['status']), ['approved', 'paid', 'success'])) {
            logDebugGlobal("🎉 SUITPAY - SAQUE APROVADO!");
            
            $conn = getDBConnection();
            $stmt_update = $conn->prepare("UPDATE operacoes_financeiras SET status = 'Aprovado', data_processamento = NOW() WHERE id = :id");
            $stmt_update->execute(['id' => $saque['id']]);
            
            return 'OK';
        }
        
        return $resp_json['message'] ?? 'Erro no SuitPay';
        
    } catch (Exception $e) {
        logDebugGlobal("❌ SUITPAY - Exceção: " . $e->getMessage());
        return 'SuitPay: Entre em contato com o suporte. (' . $e->getMessage() . ')';
    }
}

function processarSaqueVenturePay($gateway, $saque) {
    logDebugGlobal("🚀 VENTUREPAY - Iniciando processamento");
    
    $token = $gateway['client_secret'] ?? '73ca08583cf493090ec00368f168011c3169a5f2f3c3fd84751f8d92b548354376ab82c574d004ff';
    $webhook_url = $gateway['webhook_url'] ?? '';
    
    $requestData = [
        'amount' => floatval($saque['valor']) * 100, // VenturePay usa centavos
        'pix_key' => $saque['chave_pix'],
        'recipient_name' => $saque['nome_titular'],
        'external_reference' => 'SAQUE_' . $saque['id'],
        'description' => 'Saque PIX - ID ' . $saque['id']
    ];
    
    if (!empty($webhook_url)) {
        $requestData['webhook_url'] = $webhook_url;
    }
    
    logDebugGlobal("📤 VENTUREPAY - Enviando dados: " . json_encode($requestData));
    
    try {
        $response = sendpost('https://venturepay.com.br/api/pix/payment', json_encode($requestData), [
            "Authorization: $token",
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        
        logDebugGlobal("📥 VENTUREPAY - Resposta: " . $response);
        
        $resp_json = json_decode($response, true);
        
        if (isset($resp_json['status']) && in_array(strtolower($resp_json['status']), ['approved', 'paid', 'success'])) {
            logDebugGlobal("🎉 VENTUREPAY - SAQUE APROVADO!");
            
            $conn = getDBConnection();
            $stmt_update = $conn->prepare("UPDATE operacoes_financeiras SET status = 'Aprovado', data_processamento = NOW() WHERE id = :id");
            $stmt_update->execute(['id' => $saque['id']]);
            
            return 'OK';
        }
        
        return $resp_json['message'] ?? 'Erro no VenturePay';
        
    } catch (Exception $e) {
        logDebugGlobal("❌ VENTUREPAY - Exceção: " . $e->getMessage());
        return 'VenturePay: Entre em contato com o suporte. (' . $e->getMessage() . ')';
    }
}

// ============================================================================
// FUNÇÕES AUXILIARES CORRIGIDAS - DETECÇÃO PRECISA DE TIPOS PIX
// ============================================================================

function determinarTipoPixCorreto($chave) {
    $chave = trim($chave);
    logDebugGlobal("🔍 Analisando chave PIX: '$chave'");
    
    // Email - verificação rigorosa
    if (filter_var($chave, FILTER_VALIDATE_EMAIL)) {
        logDebugGlobal("✅ Detectado como EMAIL");
        return 'email';
    }
    
    // Remover caracteres não numéricos para análise
    $numeros = preg_replace('/[^0-9]/', '', $chave);
    logDebugGlobal("🔢 Números extraídos: '$numeros' (tamanho: " . strlen($numeros) . ")");
    
    // CPF (11 dígitos) - verificação CORRETA
    if (strlen($numeros) == 11) {
        // Verificar se NÃO é telefone (padrão brasileiro: DD9XXXXXXXX)
        if (preg_match('/^[1-9][1-9][9][0-9]{8}$/', $numeros)) {
            logDebugGlobal("✅ Detectado como TELEFONE (padrão: DD9XXXXXXXX)");
            return 'phone';
        } else {
            logDebugGlobal("✅ Detectado como CPF (11 dígitos, não é telefone)");
            return 'document';
        }
    }
    
    // CNPJ (14 dígitos)
    if (strlen($numeros) == 14) {
        logDebugGlobal("✅ Detectado como CNPJ (14 dígitos)");
        return 'document';
    }
    
    // Telefone com código do país (13 dígitos: 55 + DD + 9XXXXXXXX)
    if (strlen($numeros) == 13 && substr($numeros, 0, 2) == '55') {
        logDebugGlobal("✅ Detectado como TELEFONE com código do país (+55)");
        return 'phone';
    }
    
    // Telefone sem DDD (apenas 9 dígitos: 9XXXXXXXX)
    if (strlen($numeros) == 9 && substr($numeros, 0, 1) == '9') {
        logDebugGlobal("✅ Detectado como TELEFONE (9 dígitos)");
        return 'phone';
    }
    
    // Telefone com DDD sem código país (10 dígitos: DD + 8XXXXXXX ou 11 sem o 9 inicial)
    if (strlen($numeros) == 10) {
        logDebugGlobal("✅ Detectado como TELEFONE (10 dígitos - linha fixa)");
        return 'phone';
    }
    
    // Chave aleatória EVP (32 caracteres alfanuméricos)
    if (strlen($chave) == 32 && ctype_alnum($chave)) {
        logDebugGlobal("✅ Detectado como CHAVE ALEATÓRIA (32 chars alfanuméricos)");
        return 'random';
    }
    
    // UUID padrão (xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx)
    if (preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $chave)) {
        logDebugGlobal("✅ Detectado como UUID (chave aleatória)");
        return 'random';
    }
    
    // Default - assumir documento se não conseguir identificar
    logDebugGlobal("⚠️ Tipo não identificado, assumindo DOCUMENT como padrão");
    return 'document';
}

function extrairDocumento($chave) {
    // Se for CPF/CNPJ, retorna limpo
    $limpo = preg_replace('/[^0-9]/', '', $chave);
    if (strlen($limpo) == 11 || strlen($limpo) == 14) {
        return $limpo;
    }
    
    // Se não for documento, retorna a chave original
    return $chave;
}

function sendpost($url, $data, $headers) {
    logDebugGlobal("🌐 cURL - Enviando para: $url");
    if ($data) {
        logDebugGlobal("📤 cURL - Data (primeiros 200 chars): " . substr($data, 0, 200));
    }
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    logDebugGlobal("📥 cURL - HTTP Code: $httpCode");
    
    if ($response === false) {
        $error = curl_error($curl);
        logDebugGlobal("❌ cURL - Erro: $error");
        curl_close($curl);
        throw new Exception('Erro na requisição cURL: ' . $error);
    }
    
    curl_close($curl);
    return $response;
}

// ============================================================================
// FUNÇÕES DE LOG COMPATÍVEIS
// ============================================================================

function logError($message) {
    logDebugGlobal("ERROR: $message");
}

function logSuccess($message) {
    logDebugGlobal("SUCCESS: $message");
}

function logInfo($message) {
    logDebugGlobal("INFO: $message");
}

function logWarning($message) {
    logDebugGlobal("WARNING: $message");
}

// ============================================================================
// FUNÇÃO PARA CONSULTAR STATUS DO SAQUE
// ============================================================================

function consultarStatusSaque($id) {
    logDebugGlobal("🔍 CONSULTANDO STATUS DO SAQUE ID: $id");
    
    try {
        $conn = getDBConnection();
        
        // Buscar dados do saque
        $stmt = $conn->prepare("SELECT * FROM operacoes_financeiras WHERE id = :id AND tipo = 'saque'");
        $stmt->execute(['id' => $id]);
        $saque = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$saque) {
            logDebugGlobal("❌ Saque não encontrado: $id");
            return ['status' => 'error', 'message' => 'Saque não encontrado'];
        }
        
        // Buscar gateway usado
        $stmt = $conn->prepare("SELECT * FROM gateway WHERE status = 'true' ORDER BY RAND() LIMIT 1");
        $stmt->execute();
        $gateway = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$gateway) {
            logDebugGlobal("❌ Gateway não encontrado");
            return ['status' => 'error', 'message' => 'Gateway não encontrado'];
        }
        
        logDebugGlobal("✅ Consultando status no gateway: " . $gateway['banco']);
        
        // Consultar status baseado no gateway
        $banco = strtoupper($gateway['banco']);
        
        switch ($banco) {
            case 'PIXUP':
                return consultarStatusPixUp($gateway, $saque);
            case 'FIVEPAY':
                return consultarStatusFivePay($gateway, $saque);
            case 'SYNCPAY':
                return consultarStatusSyncPay($gateway, $saque);
            case 'SUITPAY':
                return consultarStatusSuitPay($gateway, $saque);
            case 'VENTUREPAY':
                return consultarStatusVenturePay($gateway, $saque);
            default:
                return ['status' => 'error', 'message' => 'Gateway não suportado para consulta'];
        }
        
    } catch (Exception $e) {
        logDebugGlobal("❌ Erro na consulta: " . $e->getMessage());
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

function consultarStatusPixUp($gateway, $saque) {
    logDebugGlobal("🔵 PIXUP - Consultando status");
    
    try {
        // Autenticar
        $credentials = $gateway['client_id'] . ':' . $gateway['client_secret'];
        $base64_credentials = base64_encode($credentials);
        
        $authResponse = sendpost('https://api.pixupbr.com/v2/oauth/token', 'grant_type=client_credentials', [
            'Authorization: Basic ' . $base64_credentials,
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $authData = json_decode($authResponse, true);
        $access_token = $authData['access_token'] ?? null;
        
        if (!$access_token) {
            return ['status' => 'error', 'message' => 'Falha na autenticação PixUp'];
        }
        
        // Consultar status usando external_id
        $external_id = 'SAQUE_' . $saque['id'];
        $response = sendget("https://api.pixupbr.com/v2/pix/payment/status/$external_id", [
            "Authorization: Bearer $access_token",
            'Accept: application/json'
        ]);
        
        $result = json_decode($response, true);
        
        return [
            'status' => 'success',
            'data' => $result,
            'payment_status' => $result['status'] ?? 'unknown'
        ];
        
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

function consultarStatusFivePay($gateway, $saque) {
    logDebugGlobal("🔥 FIVEPAY - Consultando status");
    
    try {
        $external_reference = 'SAQUE_' . $saque['id'];
        $api_key = $gateway['client_secret'];
        
        $response = sendget("https://api.fivepay.net/c1/cashout/status/$external_reference", [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ]);
        
        $result = json_decode($response, true);
        
        return [
            'status' => 'success',
            'data' => $result,
            'payment_status' => $result['status'] ?? 'unknown'
        ];
        
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

function consultarStatusSyncPay($gateway, $saque) {
    logDebugGlobal("🔄 SYNCPAY - Consultando status");
    
    try {
        $external_reference = 'SAQUE_' . $saque['id'];
        $api_key = $gateway['client_secret'];
        
        $response = sendget("https://api.syncpay.pro/v1/pix/payment/status/$external_reference", [
            "Authorization: Basic " . base64_encode($api_key),
            'Accept: application/json'
        ]);
        
        $result = json_decode($response, true);
        
        return [
            'status' => 'success',
            'data' => $result,
            'payment_status' => $result['status'] ?? 'unknown'
        ];
        
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

function consultarStatusSuitPay($gateway, $saque) {
    logDebugGlobal("💼 SUITPAY - Consultando status");
    
    try {
        $external_reference = 'SAQUE_' . $saque['id'];
        
        $response = sendget("https://ws.suitpay.app/api/v1/pix/payment/status/$external_reference", [
            "ci: " . $gateway['client_id'],
            "cs: " . $gateway['client_secret'],
            'Accept: application/json'
        ]);
        
        $result = json_decode($response, true);
        
        return [
            'status' => 'success',
            'data' => $result,
            'payment_status' => $result['status'] ?? 'unknown'
        ];
        
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

function consultarStatusVenturePay($gateway, $saque) {
    logDebugGlobal("🚀 VENTUREPAY - Consultando status");
    
    try {
        $external_reference = 'SAQUE_' . $saque['id'];
        $token = $gateway['client_secret'] ?? '73ca08583cf493090ec00368f168011c3169a5f2f3c3fd84751f8d92b548354376ab82c574d004ff';
        
        $response = sendget("https://venturepay.com.br/api/pix/payment/status/$external_reference", [
            "Authorization: $token",
            'Accept: application/json'
        ]);
        
        $result = json_decode($response, true);
        
        return [
            'status' => 'success',
            'data' => $result,
            'payment_status' => $result['status'] ?? 'unknown'
        ];
        
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

// Função GET auxiliar (estava faltando)
function sendget($url, $headers) {
    logDebugGlobal("🌐 cURL GET - Enviando para: $url");
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    logDebugGlobal("📥 cURL GET - HTTP Code: $httpCode");
    
    if ($response === false) {
        $error = curl_error($curl);
        logDebugGlobal("❌ cURL GET - Erro: $error");
        curl_close($curl);
        throw new Exception('Erro na requisição cURL GET: ' . $error);
    }
    
    curl_close($curl);
    return $response;
}

?>