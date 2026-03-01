<?php
// api/remove_from_queue.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('marshal');

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$queue_id = $data['queue_id'] ?? 0;

if (!$queue_id) {
    echo json_encode(['success' => false, 'message' => 'Queue ID required']);
    exit();
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Get queue info for reordering
    $info_sql = "SELECT route_id, position FROM queue WHERE id = ?";
    $info_stmt = $pdo->prepare($info_sql);
    $info_stmt->execute([$queue_id]);
    $queue = $info_stmt->fetch();
    
    if (!$queue) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Queue item not found']);
        exit();
    }
    
    // Delete from queue
    $delete_sql = "DELETE FROM queue WHERE id = ?";
    $delete_stmt = $pdo->prepare($delete_sql);
    $delete_stmt->execute([$queue_id]);
    
    // Reorder remaining positions
    $reorder_sql = "UPDATE queue SET position = position - 1 
                    WHERE route_id = ? AND position > ? AND status = 'waiting'";
    $reorder_stmt = $pdo->prepare($reorder_sql);
    $reorder_stmt->execute([$queue['route_id'], $queue['position']]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Taxi removed from queue']);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("API remove_from_queue error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>