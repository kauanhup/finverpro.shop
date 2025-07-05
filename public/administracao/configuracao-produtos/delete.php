<?php
require '../bank/db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (isset($data['id'])) {
    $id = $data['id'];
    
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("DELETE FROM produtos WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
}
?>
