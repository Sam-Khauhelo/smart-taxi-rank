<?php
// api/get_active_trips.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('marshal');

header('Content-Type: application/json');

try {
    $trips = $pdo->query("
        SELECT t.*, tx.registration_number, u.full_name as driver_name,
               r.route_name,
               TIMESTAMPDIFF(MINUTE, t.departed_at, NOW()) as minutes_on_road
        FROM trips t
        JOIN taxis tx ON t.taxi_id = tx.id
        JOIN drivers d ON t.driver_id = d.id
        JOIN users u ON d.user_id = u.id
        JOIN routes r ON t.route_id = r.id
        WHERE t.trip_status = 'departed'
        ORDER BY t.departed_at
    ")->fetchAll();
    
    echo json_encode(['success' => true, 'trips' => $trips, 'count' => count($trips)]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>