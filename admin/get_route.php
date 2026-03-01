<?php
// admin/get_route.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('admin');

header('Content-Type: application/json');

$route_id = $_GET['id'] ?? 0;

if (empty($route_id)) {
    echo json_encode(['error' => 'Route ID required']);
    exit();
}

try {
    $sql = "SELECT * FROM routes WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $route_id]);
    $route = $stmt->fetch();
    
    if ($route) {
        echo json_encode($route);
    } else {
        echo json_encode(['error' => 'Route not found']);
    }
    
} catch (PDOException $e) {
    error_log("Get route error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}
?>