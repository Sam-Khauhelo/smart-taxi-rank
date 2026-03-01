<?php
// api/call_next_taxi.php
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
    // Check if there's already a loading taxi
    $check_sql = "SELECT id FROM queue WHERE route_id = ? AND status = 'loading'";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$route_id]);
    
    if ($check_stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'A taxi is already loading']);
        exit();
    }
    
    // Get next waiting taxi
    $next_sql = "SELECT q.id, q.taxi_id, t.registration_number 
                 FROM queue q
                 JOIN taxis t ON q.taxi_id = t.id
                 WHERE q.route_id = ? AND q.status = 'waiting' 
                 ORDER BY q.position ASC 
                 LIMIT 1";
    $next_stmt = $pdo->prepare($next_sql);
    $next_stmt->execute([$route_id]);
    $next_taxi = $next_stmt->fetch();
    
    if (!$next_taxi) {
        echo json_encode(['success' => false, 'message' => 'No taxis in queue']);
        exit();
    }
    
    // Update status to loading
    $update_sql = "UPDATE queue SET status = 'loading', called_to_load_at = NOW() 
                   WHERE id = ?";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute([$next_taxi['id']]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Next taxi called',
        'taxi_id' => $next_taxi['taxi_id'],
        'taxi_registration' => $next_taxi['registration_number']
    ]);
    
} catch (Exception $e) {
    error_log("API call_next_taxi error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>