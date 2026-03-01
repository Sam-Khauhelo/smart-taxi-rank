<?php
// admin/get_driver.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('admin');

header('Content-Type: application/json');

$driver_id = $_GET['id'] ?? 0;
$include_details = isset($_GET['details']);

if (empty($driver_id)) {
    echo json_encode(['error' => 'Driver ID required']);
    exit();
}

try {
    if ($include_details) {
        // Get driver with full details including trips
        $sql = "SELECT d.id as driver_id, d.user_id, d.owner_id, d.taxi_id, d.id_number, 
                       d.license_expiry_date, d.employment_type, d.payment_rate, d.added_by, d.added_at,
                       u.full_name, u.phone_number, u.username, u.is_active,
                       ou.full_name as owner_name,
                       t.registration_number, r.route_name
                FROM drivers d
                JOIN users u ON d.user_id = u.id
                LEFT JOIN owners o ON d.owner_id = o.id
                LEFT JOIN users ou ON o.user_id = ou.id
                LEFT JOIN taxis t ON d.taxi_id = t.id
                LEFT JOIN routes r ON t.route_id = r.id
                WHERE d.id = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $driver_id]);
        $driver = $stmt->fetch();
        
        if ($driver) {
            // Get driver's recent trips
            $trips_sql = "SELECT t.*, r.route_name 
                          FROM trips t
                          JOIN routes r ON t.route_id = r.id
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
    } else {
        // Basic driver data for editing
        $sql = "SELECT d.id as driver_id, d.user_id, d.owner_id, d.taxi_id, d.id_number, 
                       d.license_expiry_date, d.employment_type, d.payment_rate,
                       u.full_name, u.phone_number, u.username, u.is_active
                FROM drivers d
                JOIN users u ON d.user_id = u.id
                WHERE d.id = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $driver_id]);
        $driver = $stmt->fetch();
        
        if ($driver) {
            echo json_encode($driver);
        } else {
            echo json_encode(['error' => 'Driver not found']);
        }
    }
    
} catch (PDOException $e) {
    error_log("Get driver error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}
?>