<?php
header('Content-Type: application/json');
session_start();

require '../bank/db.php';

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Obter dados JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['phone'])) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

$phone = $input['phone'];

// Validar formato do telefone
if (!preg_match('/^55\d{11}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Formato de telefone inválido']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // =====================================
    // BUSCAR CONFIGURAÇÕES DE SMS
    // =====================================
    $stmt = $pdo->query("SELECT sms_enabled, twilio_sid, twilio_token, twilio_phone FROM configurar_cadastro LIMIT 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar se SMS está habilitado
    if (!$config || !$config['sms_enabled']) {
        echo json_encode(['success' => false, 'message' => 'SMS não está habilitado']);
        exit;
    }
    
    // Verificar se credenciais Twilio estão configuradas
    if (empty($config['twilio_sid']) || empty($config['twilio_token']) || empty($config['twilio_phone'])) {
        echo json_encode(['success' => false, 'message' => 'Credenciais Twilio não configuradas']);
        exit;
    }
    
    // =====================================
    // VERIFICAR SE TELEFONE JÁ ESTÁ CADASTRADO
    // =====================================
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE telefone = ?");
    $stmt->execute(['+' . $phone]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Este número de telefone já está cadastrado']);
        exit;
    }
    
    // =====================================
    // LIMPAR CÓDIGOS EXPIRADOS
    // =====================================
    $pdo->exec("DELETE FROM sms_codes WHERE expires_at < NOW()");
    
    // =====================================
    // VERIFICAR RATE LIMITING (máximo 3 SMS por hora por telefone)
    // =====================================
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sms_codes WHERE telefone = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt->execute(['+' . $phone]);
    $recentSMS = $stmt->fetchColumn();
    
    if ($recentSMS >= 3) {
        echo json_encode([
            'success' => false, 
            'message' => 'Limite de SMS excedido. Tente novamente em uma hora.'
        ]);
        exit;
    }
    
    // =====================================
    // GERAR CÓDIGO DE 6 DÍGITOS
    // =====================================
    $codigo = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    
    // =====================================
    // FORMATAR TELEFONE PARA TWILIO
    // =====================================
    $phoneFormatted = '+' . $phone; // +5511999999999
    
    // =====================================
    // PREPARAR MENSAGEM SMS
    // =====================================
    $message = "Seu código de verificação é: {$codigo}\n\nEste código expira em 10 minutos.\n\nNão compartilhe este código com ninguém.";
    
    // =====================================
    // ENVIAR SMS VIA TWILIO
    // =====================================
    $twilioSid = $config['twilio_sid'];
    $twilioToken = $config['twilio_token'];
    $twilioPhone = $config['twilio_phone'];
    
    // Preparar dados para Twilio
    $postData = http_build_query([
        'From' => $twilioPhone,
        'To' => $phoneFormatted,
        'Body' => $message
    ]);
    
    // Configurar cURL para Twilio
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.twilio.com/2010-04-01/Accounts/{$twilioSid}/Messages.json",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded'
        ],
        CURLOPT_USERPWD => $twilioSid . ':' . $twilioToken
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);
    
    // Verificar se houve erro no cURL
    if ($curlError) {
        error_log("Twilio cURL Error: " . $curlError);
        echo json_encode(['success' => false, 'message' => 'Erro de conexão com o serviço SMS']);
        exit;
    }
    
    // Decodificar resposta da Twilio
    $twilioResponse = json_decode($response, true);
    
    // Verificar se SMS foi enviado com sucesso
    if ($httpCode === 201 && isset($twilioResponse['sid'])) {
        // =====================================
        // SALVAR CÓDIGO NO BANCO
        // =====================================
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        $stmt = $pdo->prepare("
            INSERT INTO sms_codes (telefone, codigo, expires_at) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$phoneFormatted, $codigo, $expiresAt]);
        
        // Log de sucesso
        error_log("SMS enviado com sucesso para {$phoneFormatted}. SID: " . $twilioResponse['sid']);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Código SMS enviado com sucesso!',
            'phone' => $phoneFormatted
        ]);
        
    } else {
        // =====================================
        // TRATAR ERROS DA TWILIO
        // =====================================
        $errorMessage = 'Erro ao enviar SMS';
        
        if (isset($twilioResponse['message'])) {
            $errorMessage = $twilioResponse['message'];
        } elseif (isset($twilioResponse['error_message'])) {
            $errorMessage = $twilioResponse['error_message'];
        }
        
        // Log do erro
        error_log("Twilio Error (HTTP {$httpCode}): " . $response);
        
        // Mapear erros comuns da Twilio para mensagens amigáveis
        $friendlyMessages = [
            21211 => 'Número de telefone inválido',
            21612 => 'Número de telefone não pode receber SMS',
            21614 => 'Número de telefone inválido',
            21408 => 'Permissão negada - verifique as credenciais',
            21606 => 'Número de origem não verificado',
            30007 => 'Falha na entrega - tente novamente'
        ];
        
        if (isset($twilioResponse['code']) && isset($friendlyMessages[$twilioResponse['code']])) {
            $errorMessage = $friendlyMessages[$twilioResponse['code']];
        }
        
        echo json_encode([
            'success' => false, 
            'message' => $errorMessage
        ]);
    }
    
} catch (PDOException $e) {
    error_log("SMS Database Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
    
} catch (Exception $e) {
    error_log("SMS General Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>