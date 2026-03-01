<?php
// api/depart_taxi.php
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
    // Start transaction
    $pdo->beginTransaction();
    
    // Get the taxi that's ready to depart
    $taxi_sql = "SELECT q.*, t.id as taxi_id, d.id as driver_id, 
                        r.fare_amount
                 FROM queue q
                 JOIN taxis t ON q.taxi_id = t.id
                 JOIN routes r ON q.route_id = r.id
                 LEFT JOIN drivers d ON t.id = d.taxi_id
                 WHERE q.route_id = ? AND q.status = 'loading' 
                 LIMIT 1";
    
    $taxi_stmt = $pdo->prepare($taxi_sql);
    $taxi_stmt->execute([$route_id]);
    $taxi = $taxi_stmt->fetch();
    
    if (!$taxi) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'No taxi is currently loading']);
        exit();
    }
    
    if (!$taxi['driver_id']) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Taxi has no assigned driver']);
        exit();
    }
    
    // Get passenger count from session
    $passenger_count = $_SESSION['passenger_count_' . $route_id] ?? 15;
    
    // Calculate totals
    $total_fare = $taxi['fare_amount'] * $passenger_count;
    $levy = $total_fare * 0.10; // 10% levy
    $owner_payout = $total_fare - $levy;
    
    // Insert trip record with departed status
    $trip_sql = "INSERT INTO trips (taxi_id, driver_id, route_id, passenger_count, 
                                     fare_amount, total_fare, association_levy, owner_payout, 
                                     departed_at, trip_status) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'departed')";
    $trip_stmt = $pdo->prepare($trip_sql);
    $trip_stmt->execute([
        $taxi['taxi_id'],
        $taxi['driver_id'],
        $route_id,
        $passenger_count,
        $taxi['fare_amount'],
        $total_fare,
        $levy,
        $owner_payout
    ]);
    
    $trip_id = $pdo->lastInsertId();
    
    // Remove from queue
    $delete_sql = "DELETE FROM queue WHERE id = ?";
    $delete_stmt = $pdo->prepare($delete_sql);
    $delete_stmt->execute([$taxi['id']]);
    
    // Reorder remaining queue
    $reorder_sql = "UPDATE queue SET position = position - 1 
                    WHERE route_id = ? AND position > ? AND status = 'waiting'";
    $reorder_stmt = $pdo->prepare($reorder_sql);
    $reorder_stmt->execute([$route_id, $taxi['position']]);
    
    // Clear passenger count from session
    unset($_SESSION['passenger_count_' . $route_id]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Taxi departed successfully',
        'trip_id' => $trip_id,
        'total_fare' => $total_fare
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("API depart_taxi error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>