<?php
// api/complete_loading.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('marshal');

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$route_id = $data['route_id'] ?? 0;

if (!$route_id) {
    echo json_encode(['success' => false, 'message' => 'Route ID required']);
    exit();
}

try {
    // Update loading taxi back to waiting (ready for departure)
    $update_sql = "UPDATE queue SET status = 'waiting' 
                   WHERE route_id = ? AND status = 'loading'";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute([$route_id]);
    
    echo json_encode(['success' => true, 'message' => 'Loading completed']);
    
} catch (Exception $e) {
    error_log("API complete_loading error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>