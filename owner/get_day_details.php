<?php
// owner/get_day_details.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('owner');

header('Content-Type: application/json');

$date = $_GET['date'] ?? '';

if (empty($date)) {
    echo json_encode(['error' => 'Date required']);
    exit();
}

try {
    $sql = "SELECT 
                DATE_FORMAT(t.departed_at, '%H:%i') as time,
                t.passenger_count,
                t.total_fare,
                t.association_levy,
                t.owner_payout,
                tx.registration_number,
                u.full_name as driver_name,
                r.route_name
            FROM trips t
            JOIN taxis tx ON t.taxi_id = tx.id
            JOIN drivers d ON t.driver_id = d.id
            JOIN users u ON d.user_id = u.id
            JOIN routes r ON t.route_id = r.id
            WHERE tx.owner_id = :owner_id 
            AND DATE(t.departed_at) = :date
            ORDER BY t.departed_at";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':owner_id' => $_SESSION['owner_id'],
        ':date' => $date
    ]);
    $trips = $stmt->fetchAll();
    
    echo json_encode(['trips' => $trips]);
    
} catch (PDOException $e) {
    error_log("Get day details error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}
?>