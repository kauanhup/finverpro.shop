<?php
session_start();
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

try {
    if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Acesso negado');
    }

    require '../includes/config.php';
    require '../includes/functions.php';

    $user_id = $_SESSION['user_id'];
    
    // Carregar dados
    $config = loadRoletaConfig();
    $user_spins = loadUserSpins($user_id);
    
    // Validações
    validateSpin($user_spins, $config);
    
    // Sortear prêmio
    $premio_sorteado = sortearPremio($config);
    
    // DEBUG - ver o que está sendo sorteado
    error_log("Premio sorteado: " . print_r($premio_sorteado, true));
    
    // Processar giro
    $result = processarGiro($user_id, $premio_sorteado, $user_spins);
    
    // Retorno JSON
    $response = [
        'success' => true,
        'premio' => $premio_sorteado,  // ← Garantir que está aqui
        'giros_restantes' => $result['giros_restantes'],
        'giros_hoje' => $result['giros_hoje']
    ];
    
    error_log("Response: " . json_encode($response));
    echo json_encode($response);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
exit;
?>