<?php
// owner/get_taxi_details.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('owner');

header('Content-Type: application/json');

$taxi_id = $_GET['id'] ?? 0;

if (empty($taxi_id)) {
    echo json_encode(['error' => 'Taxi ID required']);
    exit();
}

try {
    // Get taxi details
    $sql = "SELECT t.*, 
                   r.route_name, r.fare_amount,
                   d.id as driver_id, u.full_name as driver_name, u.phone_number as driver_phone,
                   (SELECT COUNT(*) FROM trips WHERE taxi_id = t.id AND DATE(departed_at) = CURDATE()) as today_trips,
                   (SELECT SUM(total_fare) FROM trips WHERE taxi_id = t.id AND DATE(departed_at) = CURDATE()) as today_revenue,
                   (SELECT COUNT(*) FROM trips WHERE taxi_id = t.id) as total_trips,
                   (SELECT SUM(total_fare - association_levy) FROM trips WHERE taxi_id = t.id) as total_earnings
            FROM taxis t
            LEFT JOIN routes r ON t.route_id = r.id
            LEFT JOIN drivers d ON t.id = d.taxi_id
            LEFT JOIN users u ON d.user_id = u.id
            WHERE t.id = :id AND t.owner_id = :owner_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id' => $taxi_id,
        ':owner_id' => $_SESSION['owner_id']
    ]);
    $taxi = $stmt->fetch();
    
    if ($taxi) {
        // Get recent trips
        $trips_sql = "SELECT t.*, r.route_name, u.full_name as driver_name
                      FROM trips t
                      JOIN routes r ON t.route_id = r.id
                      JOIN drivers d ON t.driver_id = d.id
                      JOIN users u ON d.user_id = u.id
                      WHERE t.taxi_id = :id
                      ORDER BY t.departed_at DESC
                      LIMIT 10";
        $trips_stmt = $pdo->prepare($trips_sql);
        $trips_stmt->execute([':id' => $taxi_id]);
        $taxi['trips'] = $trips_stmt->fetchAll();
        
        echo json_encode($taxi);
    } else {
        echo json_encode(['error' => 'Taxi not found']);
    }
    
} catch (PDOException $e) {
    error_log("Get taxi details error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}
?>