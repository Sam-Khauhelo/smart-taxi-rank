<?php
// api/add_to_queue.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('marshal');

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$route_id = $data['route_id'] ?? 0;
$taxi_id = $data['taxi_id'] ?? 0;
$position = $data['position'] ?? null;

if (!$route_id || !$taxi_id) {
    echo json_encode(['success' => false, 'message' => 'Route ID and Taxi ID required']);
    exit();
}

try {
    // Check if taxi is already in queue
    $check_sql = "SELECT id FROM queue WHERE taxi_id = ? AND status IN ('waiting', 'loading')";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$taxi_id]);
    
    if ($check_stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Taxi is already in queue']);
        exit();
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    if ($position) {
        // Shift positions to make room
        $shift_sql = "UPDATE queue SET position = position + 1 
                      WHERE route_id = ? AND position >= ? AND status = 'waiting'";
        $shift_stmt = $pdo->prepare($shift_sql);
        $shift_stmt->execute([$route_id, $position]);
        
        $new_position = $position;
    } else {
        // Get next available position
        $pos_sql = "SELECT COALESCE(MAX(position), 0) + 1 as next_pos 
                    FROM queue WHERE route_id = ? AND status = 'waiting'";
        $pos_stmt = $pdo->prepare($pos_sql);
        $pos_stmt->execute([$route_id]);
        $new_position = $pos_stmt->fetch()['next_pos'];
    }
    
    // Insert into queue
    $insert_sql = "INSERT INTO queue (taxi_id, route_id, position, status, entered_queue_at) 
                   VALUES (?, ?, ?, 'waiting', NOW())";
    $insert_stmt = $pdo->prepare($insert_sql);
    $insert_stmt->execute([$taxi_id, $route_id, $new_position]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Taxi added to queue', 'position' => $new_position]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("API add_to_queue error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>