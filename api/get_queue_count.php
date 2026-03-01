<?php
// api/get_queue_count.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('marshal');

header('Content-Type: application/json');

try {
    $sql = "SELECT COUNT(*) as count FROM queue WHERE status = 'waiting'";
    $stmt = $pdo->query($sql);
    $count = $stmt->fetch()['count'];
    
    echo json_encode(['count' => (int)$count]);
    
} catch (PDOException $e) {
    error_log("API get_queue_count error: " . $e->getMessage());
    echo json_encode(['count' => 0]);
}
?>