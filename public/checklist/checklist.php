<?php
session_start();
require_once '../bank/db.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar autenticação
if (!checkAuth()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action !== 'checkin') {
    echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    exit;
}

try {
    $pdo = getPDO();
    $pdo->beginTransaction();
    
    // Buscar dados do usuário e saldo atual
    $stmt = $pdo->prepare("
        SELECT 
            u.nome, 
            u.telefone,
            c.saldo_bonus
        FROM usuarios u 
        JOIN carteiras c ON u.id = c.usuario_id 
        WHERE u.id = ? AND u.status = 'ativo'
    ");
    $stmt->execute([$user_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
        exit;
    }
    
    // Buscar dados do checklist diário
    $stmt = $pdo->prepare("
        SELECT 
            dia_consecutivo, 
            ultimo_checkin, 
            total_dias, 
            valor_acumulado 
        FROM checklist_diario 
        WHERE usuario_id = ?
    ");
    $stmt->execute([$user_id]);
    $checklist = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$checklist) {
        // Criar registro se não existir
        $stmt = $pdo->prepare("
            INSERT INTO checklist_diario (usuario_id) VALUES (?)
        ");
        $stmt->execute([$user_id]);
        
        $checklist = [
            'dia_consecutivo' => 0,
            'ultimo_checkin' => null,
            'total_dias' => 0,
            'valor_acumulado' => 0.00
        ];
    }
    
    // Verificar se pode fazer checkin hoje
    $hoje = date('Y-m-d');
    
    if ($checklist['ultimo_checkin']) {
        $ultimo_checkin = date('Y-m-d', strtotime($checklist['ultimo_checkin']));
        
        if ($ultimo_checkin === $hoje) {
            echo json_encode(['success' => false, 'message' => 'Você já fez check-in hoje']);
            exit;
        }
        
        // Verificar se quebrou a sequência (perdeu um dia)
        if ($ultimo_checkin < date('Y-m-d', strtotime('-1 day'))) {
            $checklist['dia_consecutivo'] = 0;
        }
    }
    
    // Calcular novo dia consecutivo
    $novo_dia_consecutivo = $checklist['dia_consecutivo'] + 1;
    
    // Definir recompensas por dia
    $recompensas = [
        1 => 2.00,
        2 => 2.50,
        3 => 3.00,
        4 => 3.50,
        5 => 4.00,
        6 => 4.50,
        7 => 10.00, // Bônus especial no 7º dia
    ];
    
    // Calcular recompensa do dia atual
    $dia_recompensa = $novo_dia_consecutivo;
    if ($dia_recompensa > 7) {
        $dia_recompensa = (($dia_recompensa - 1) % 7) + 1;
    }
    
    $valor_recompensa = $recompensas[$dia_recompensa] ?? 2.00;
    $bonus_especial = ($dia_recompensa === 7);
    
    // Reset após 7 dias consecutivos
    if ($novo_dia_consecutivo > 7) {
        $novo_dia_consecutivo = 1;
    }
    
    // Creditar bônus na carteira
    $stmt = $pdo->prepare("
        UPDATE carteiras 
        SET saldo_bonus = saldo_bonus + ? 
        WHERE usuario_id = ?
    ");
    $stmt->execute([$valor_recompensa, $user_id]);
    
    // Atualizar checklist
    $stmt = $pdo->prepare("
        UPDATE checklist_diario 
        SET dia_consecutivo = ?, 
            ultimo_checkin = CURRENT_DATE,
            total_dias = total_dias + 1,
            valor_acumulado = valor_acumulado + ?
        WHERE usuario_id = ?
    ");
    $stmt->execute([$novo_dia_consecutivo, $valor_recompensa, $user_id]);
    
    // Log da operação
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema 
        (usuario_id, acao, tabela_afetada, dados_novos, ip_address) 
        VALUES (?, 'checkin_diario', 'checklist_diario', ?, ?)
    ");
    $dados_log = json_encode([
        'dia_consecutivo' => $novo_dia_consecutivo,
        'valor_recompensa' => $valor_recompensa,
        'bonus_especial' => $bonus_especial,
        'data_checkin' => $hoje
    ]);
    $stmt->execute([
        $user_id,
        $dados_log,
        $_SERVER['REMOTE_ADDR'] ?? 'cliente'
    ]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Check-in realizado com sucesso!',
        'dados' => [
            'dia_consecutivo' => $novo_dia_consecutivo,
            'valor_recompensa' => $valor_recompensa,
            'novo_saldo_bonus' => $usuario['saldo_bonus'] + $valor_recompensa,
            'total_dias' => $checklist['total_dias'] + 1,
            'valor_acumulado' => $checklist['valor_acumulado'] + $valor_recompensa,
            'bonus_especial' => $bonus_especial,
            'proximo_checkin' => date('d/m/Y', strtotime('+1 day'))
        ]
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Erro no checklist: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno: ' . $e->getMessage()
    ]);
}
?>