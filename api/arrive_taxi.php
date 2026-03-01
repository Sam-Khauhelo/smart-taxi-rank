<?php
// api/driver_arrive.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('driver');

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$trip_id = $data['trip_id'] ?? 0;

if (!$trip_id) {
    echo json_encode(['success' => false, 'message' => 'Trip ID required']);
    exit();
}

try {
    // Check if trip belongs to this driver
    $check_sql = "SELECT t.* FROM trips t
                  WHERE t.id = ? AND t.driver_id = ? AND t.trip_status = 'departed'";
    
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$trip_id, $_SESSION['driver_id']]);
    $trip = $check_stmt->fetch();
    
    if (!$trip) {
        echo json_encode(['success' => false, 'message' => 'Trip not found or not authorized']);
        exit();
    }
    
    // Update trip with arrival time
    $update_sql = "UPDATE trips SET arrived_at = NOW(), trip_status = 'arrived' WHERE id = ?";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute([$trip_id]);
    
    // Update taxi status
    $update_taxi_sql = "UPDATE taxis SET status = 'active' WHERE id = ?";
    $update_taxi_stmt = $pdo->prepare($update_taxi_sql);
    $update_taxi_stmt->execute([$trip['taxi_id']]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Arrival confirmed. You can now return to rank.',
        'can_return_to_queue' => true
    ]);
    
} catch (Exception $e) {
    error_log("Driver arrive error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>