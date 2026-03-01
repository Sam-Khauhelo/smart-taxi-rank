<?php
// admin/get_taxi.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('admin');

header('Content-Type: application/json');

$taxi_id = $_GET['id'] ?? 0;
$include_details = isset($_GET['details']);

if (empty($taxi_id)) {
    echo json_encode(['error' => 'Taxi ID required']);
    exit();
}

try {
    if ($include_details) {
        // Get taxi with full details including trips
        $sql = "SELECT t.*, 
                       o.id as owner_id, u.full_name as owner_name,
                       r.route_name, r.fare_amount,
                       d.id as driver_id, du.full_name as driver_name,
                       (SELECT COUNT(*) FROM trips WHERE taxi_id = t.id AND DATE(departed_at) = CURDATE()) as today_trips,
                       (SELECT SUM(total_fare) FROM trips WHERE taxi_id = t.id AND DATE(departed_at) = CURDATE()) as today_revenue,
                       (SELECT COUNT(*) FROM trips WHERE taxi_id = t.id) as total_trips,
                       (SELECT SUM(total_fare - association_levy) FROM trips WHERE taxi_id = t.id) as total_earnings
                FROM taxis t
                LEFT JOIN owners o ON t.owner_id = o.id
                LEFT JOIN users u ON o.user_id = u.id
                LEFT JOIN routes r ON t.route_id = r.id
                LEFT JOIN drivers d ON t.id = d.taxi_id
                LEFT JOIN users du ON d.user_id = du.id
                WHERE t.id = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $taxi_id]);
        $taxi = $stmt->fetch();
        
        if ($taxi) {
            // Get taxi's recent trips
            $trips_sql = "SELECT t.*, r.route_name, 
                          u.full_name as driver_name
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
    } else {
        // Basic taxi data for editing
        $sql = "SELECT * FROM taxis WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $taxi_id]);
        $taxi = $stmt->fetch();
        
        if ($taxi) {
            echo json_encode($taxi);
        } else {
            echo json_encode(['error' => 'Taxi not found']);
        }
    }
    
} catch (PDOException $e) {
    error_log("Get taxi error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}
?>