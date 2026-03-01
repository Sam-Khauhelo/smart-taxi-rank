<?php
// owner/get_taxi_trips.php
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
    // Get taxi registration
    $reg_sql = "SELECT registration_number FROM taxis WHERE id = :id AND owner_id = :owner_id";
    $reg_stmt = $pdo->prepare($reg_sql);
    $reg_stmt->execute([
        ':id' => $taxi_id,
        ':owner_id' => $_SESSION['owner_id']
    ]);
    $taxi = $reg_stmt->fetch();
    
    if (!$taxi) {
        echo json_encode(['error' => 'Taxi not found']);
        exit();
    }
    
    // Get trips
    $trips_sql = "SELECT t.*, r.route_name, u.full_name as driver_name
                  FROM trips t
                  JOIN routes r ON t.route_id = r.id
                  JOIN drivers d ON t.driver_id = d.id
                  JOIN users u ON d.user_id = u.id
                  WHERE t.taxi_id = :id
                  ORDER BY t.departed_at DESC
                  LIMIT 50";
    $trips_stmt = $pdo->prepare($trips_sql);
    $trips_stmt->execute([':id' => $taxi_id]);
    $trips = $trips_stmt->fetchAll();
    
    echo json_encode([
        'registration' => $taxi['registration_number'],
        'trips' => $trips
    ]);
    
} catch (PDOException $e) {
    error_log("Get taxi trips error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}
?>