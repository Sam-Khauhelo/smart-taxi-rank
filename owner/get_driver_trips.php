<?php
// owner/get_driver_trips.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('owner');

header('Content-Type: application/json');

$driver_id = $_GET['id'] ?? 0;

if (empty($driver_id)) {
    echo json_encode(['error' => 'Driver ID required']);
    exit();
}

try {
    // Get driver name
    $name_sql = "SELECT u.full_name FROM drivers d JOIN users u ON d.user_id = u.id WHERE d.id = :id AND d.owner_id = :owner_id";
    $name_stmt = $pdo->prepare($name_sql);
    $name_stmt->execute([
        ':id' => $driver_id,
        ':owner_id' => $_SESSION['owner_id']
    ]);
    $driver = $name_stmt->fetch();
    
    if (!$driver) {
        echo json_encode(['error' => 'Driver not found']);
        exit();
    }
    
    // Get trips
    $trips_sql = "SELECT t.*, r.route_name, tx.registration_number
                  FROM trips t
                  JOIN routes r ON t.route_id = r.id
                  JOIN taxis tx ON t.taxi_id = tx.id
                  WHERE t.driver_id = :id
                  ORDER BY t.departed_at DESC
                  LIMIT 50";
    $trips_stmt = $pdo->prepare($trips_sql);
    $trips_stmt->execute([':id' => $driver_id]);
    $trips = $trips_stmt->fetchAll();
    
    echo json_encode([
        'driver_name' => $driver['full_name'],
        'trips' => $trips
    ]);
    
} catch (PDOException $e) {
    error_log("Get driver trips error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}
?>