<?php
// api/reorder_queue.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('marshal');

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$route_id = $data['route_id'] ?? 0;
$order = $data['order'] ?? [];

if (!$route_id || empty($order)) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Update positions
    $update_sql = "UPDATE queue SET position = ? WHERE id = ? AND route_id = ?";
    $update_stmt = $pdo->prepare($update_sql);
    
    foreach ($order as $item) {
        $update_stmt->execute([$item['position'], $item['id'], $route_id]);
    }
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Queue reordered successfully']);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("API reorder_queue error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>