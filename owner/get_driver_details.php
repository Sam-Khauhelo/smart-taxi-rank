<?php
// owner/get_driver_details.php
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
    // Get driver details
    $sql = "SELECT d.*, 
                   u.id as user_id, u.full_name, u.phone_number, u.username, u.email, u.is_active,
                   t.id as taxi_id, t.registration_number, r.route_name,
                   (SELECT COUNT(*) FROM trips WHERE driver_id = d.id) as total_trips,
                   (SELECT SUM(owner_payout) FROM trips WHERE driver_id = d.id) as total_earnings
            FROM drivers d
            JOIN users u ON d.user_id = u.id
            LEFT JOIN taxis t ON d.taxi_id = t.id
            LEFT JOIN routes r ON t.route_id = r.id
            WHERE d.id = :id AND d.owner_id = :owner_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id' => $driver_id,
        ':owner_id' => $_SESSION['owner_id']
    ]);
    $driver = $stmt->fetch();
    
    if ($driver) {
        // Get recent trips
        $trips_sql = "SELECT t.*, r.route_name, tx.registration_number
                      FROM trips t
                      JOIN routes r ON t.route_id = r.id
                      JOIN taxis tx ON t.taxi_id = tx.id
                      WHERE t.driver_id = :id
                      ORDER BY t.departed_at DESC
                      LIMIT 10";
        $trips_stmt = $pdo->prepare($trips_sql);
        $trips_stmt->execute([':id' => $driver_id]);
        $driver['trips'] = $trips_stmt->fetchAll();
        
        echo json_encode($driver);
    } else {
        echo json_encode(['error' => 'Driver not found']);
    }
    
} catch (PDOException $e) {
    error_log("Get driver details error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}
?>