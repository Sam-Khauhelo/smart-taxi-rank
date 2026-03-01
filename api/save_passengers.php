<?php
// api/save_passengers.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('marshal');

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$route_id = $data['route_id'] ?? 0;
$passenger_count = $data['passenger_count'] ?? 0;

if (!$route_id || $passenger_count < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

// For now, we'll store passenger count in session
// In a production environment, you might want to store this temporarily in a database table
$_SESSION['passenger_count_' . $route_id] = $passenger_count;

echo json_encode(['success' => true, 'message' => 'Passenger count saved']);
?>