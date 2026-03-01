<?php
// api/get_stats.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('marshal');

header('Content-Type: application/json');

try {
    // Get waiting count
    $waiting_sql = "SELECT COUNT(*) as count FROM queue WHERE status = 'waiting'";
    $waiting_stmt = $pdo->query($waiting_sql);
    $waiting = $waiting_stmt->fetch()['count'];
    
    // Get loading count
    $loading_sql = "SELECT COUNT(*) as count FROM queue WHERE status = 'loading'";
    $loading_stmt = $pdo->query($loading_sql);
    $loading = $loading_stmt->fetch()['count'];
    
    // Get today's trips
    $trips_sql = "SELECT COUNT(*) as count FROM trips WHERE DATE(departed_at) = CURDATE()";
    $trips_stmt = $pdo->query($trips_sql);
    $trips = $trips_stmt->fetch()['count'];
    
    // Get today's passengers
    $passengers_sql = "SELECT COALESCE(SUM(passenger_count), 0) as total FROM trips WHERE DATE(departed_at) = CURDATE()";
    $passengers_stmt = $pdo->query($passengers_sql);
    $passengers = $passengers_stmt->fetch()['total'];
    
    echo json_encode([
        'waiting' => (int)$waiting,
        'loading' => (int)$loading,
        'trips' => (int)$trips,
        'passengers' => (int)$passengers
    ]);
    
} catch (PDOException $e) {
    error_log("API get_stats error: " . $e->getMessage());
    echo json_encode([
        'waiting' => 0,
        'loading' => 0,
        'trips' => 0,
        'passengers' => 0
    ]);
}
?>