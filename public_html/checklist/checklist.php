<?php
// CORREÇÃO PARA O CHECKLIST.PHP - ADICIONAR LÓGICA DE REINÍCIO

session_start();
require '../bank/db.php';

header('Content-Type: application/json');

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit();
}

$id_usuario = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$dia = $input['dia'] ?? null;

// Validar o dia
if ($dia === null || !is_numeric($dia) || $dia < 1 || $dia > 6) {
    echo json_encode(['success' => false, 'message' => 'Dia inválido.']);
    exit();
}

$dia = (int)$dia;

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro de conexão: ' . $e->getMessage()]);
    exit();
}

// BUSCAR VALORES DINÂMICOS DA TABELA CHECKLIST
try {
    $stmt_config = $conn->prepare("SELECT valor_dia1, valor_dia2, valor_dia3, valor_dia4, valor_dia5, valor_dia6, valor_dia7 FROM checklist WHERE user_id = 0 AND tarefa = 'CONFIG_VALORES' LIMIT 1");
    $stmt_config->execute();
    $config = $stmt_config->fetch(PDO::FETCH_ASSOC);
    
    if (!$config) {
        $stmt_insert = $conn->prepare("INSERT INTO checklist (user_id, tarefa, concluida, recompensa, valor_dia1, valor_dia2, valor_dia3, valor_dia4, valor_dia5, valor_dia6, valor_dia7) VALUES (0, 'CONFIG_VALORES', 0, 0.00, 1.00, 2.00, 3.00, 5.00, 8.00, 15.00, 25.00)");
        $stmt_insert->execute();
        
        $stmt_config->execute();
        $config = $stmt_config->fetch(PDO::FETCH_ASSOC);
    }
    
    $valores_por_dia = [
        1 => floatval($config['valor_dia1']),
        2 => floatval($config['valor_dia2']),
        3 => floatval($config['valor_dia3']),
        4 => floatval($config['valor_dia4']),
        5 => floatval($config['valor_dia5']),
        6 => floatval($config['valor_dia6']),
        7 => floatval($config['valor_dia7'])
    ];
    
    $valor_do_dia = $valores_por_dia[$dia] ?? 1.00;
    
} catch (Exception $e) {
    $valor_do_dia = 1.00;
    error_log("Erro ao buscar configuração do checklist: " . $e->getMessage());
}

// Obtém dados atuais do usuário
$sql = "SELECT saldo, checklist, data_checklist FROM usuarios WHERE id = :id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':id', $id_usuario, PDO::PARAM_INT);
$stmt->execute();
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userData) {
    echo json_encode(['success' => false, 'message' => 'Usuário não encontrado.']);
    exit();
}

$saldo = floatval($userData['saldo']);
$checklist = (int)$userData['checklist'];
$data_checklist = $userData['data_checklist'];
$hoje = date("Y-m-d");

// *** NOVA LÓGICA: VERIFICAR SE PRECISA REINICIAR O CHECKLIST ***
if ($data_checklist && $data_checklist < $hoje) {
    // Se a data do último checklist é anterior a hoje, verificar se completou o ciclo
    if ($checklist >= 6) {
        // Completou todos os 6 dias, reiniciar para 0
        $checklist = 0;
        $sql_reset = "UPDATE usuarios SET checklist = 0 WHERE id = :id";
        $stmt_reset = $conn->prepare($sql_reset);
        $stmt_reset->bindParam(':id', $id_usuario, PDO::PARAM_INT);
        $stmt_reset->execute();
    }
}

// Lógica para validar se o dia pode ser desbloqueado
$pode_desbloquear = false;

if ($dia == 1 && $checklist == 0) {
    // Primeiro dia do ciclo
    if ($data_checklist === null || $data_checklist < $hoje) {
        $pode_desbloquear = true;
    } else {
        echo json_encode(['success' => false, 'message' => 'Você já fez o checklist hoje. Volte amanhã!']);
        exit();
    }
} elseif ($dia == $checklist + 1) {
    // Próximo dia na sequência
    if ($data_checklist === null || $data_checklist < $hoje) {
        $pode_desbloquear = true;
    } else {
        echo json_encode(['success' => false, 'message' => 'Você já fez o checklist hoje. Volte amanhã!']);
        exit();
    }
} elseif ($dia <= $checklist) {
    echo json_encode(['success' => false, 'message' => 'Este dia já foi desbloqueado.']);
    exit();
} else {
    echo json_encode(['success' => false, 'message' => 'Você precisa completar os dias anteriores primeiro.']);
    exit();
}

if ($pode_desbloquear) {
    $novo_saldo = $saldo + $valor_do_dia;
    $novo_checklist = $checklist + 1;
    
    // *** VERIFICAR SE COMPLETOU O CICLO DE 6 DIAS ***
    $mensagem_especial = "";
    if ($novo_checklist >= 6) {
        $mensagem_especial = " 🎉 Parabéns! Você completou todo o ciclo! Amanhã um novo ciclo começará.";
    }

    // Atualiza saldo, checklist e data_checklist
    $sql_update = "UPDATE usuarios SET saldo = :saldo, checklist = :checklist, data_checklist = :data_checklist WHERE id = :id";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bindParam(':saldo', $novo_saldo);
    $stmt_update->bindParam(':checklist', $novo_checklist, PDO::PARAM_INT);
    $stmt_update->bindParam(':data_checklist', $hoje);
    $stmt_update->bindParam(':id', $id_usuario, PDO::PARAM_INT);
    
    if ($stmt_update->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => "Parabéns! Dia $dia desbloqueado. Você ganhou R$ " . number_format($valor_do_dia, 2, ',', '.') . "!" . $mensagem_especial,
            'checklist_dia' => $novo_checklist,
            'ciclo_completo' => ($novo_checklist >= 6)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar o banco de dados.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Erro inesperado.']);
}
?>