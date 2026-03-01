<?php
// owner/calculate_estimate.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('owner');

header('Content-Type: application/json');

$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';

if (empty($start) || empty($end)) {
    echo json_encode(['amount' => 0]);
    exit();
}

try {
    $sql = "SELECT COALESCE(SUM(owner_payout), 0) as total
            FROM trips t
            JOIN taxis tx ON t.taxi_id = tx.id
            WHERE tx.owner_id = :owner_id 
            AND DATE(t.departed_at) BETWEEN :start AND :end";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':owner_id' => $_SESSION['owner_id'],
        ':start' => $start,
        ':end' => $end
    ]);
    $total = $stmt->fetch()['total'];
    
    echo json_encode(['amount' => floatval($total)]);
    
} catch (PDOException $e) {
    error_log("Calculate estimate error: " . $e->getMessage());
    echo json_encode(['amount' => 0]);
}
?>