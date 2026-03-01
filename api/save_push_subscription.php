<?php
// api/save_push_subscription.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';

$data = json_decode(file_get_contents('php://input'), true);
$driver_id = $data['driver_id'] ?? $_SESSION['driver_id'] ?? 0;
$subscription = json_encode($data['subscription']);

if (!$driver_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Create push_subscriptions table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS push_subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        driver_id INT NOT NULL UNIQUE,
        subscription TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE
    )
");

// Save or update subscription
$stmt = $pdo->prepare("
    INSERT INTO push_subscriptions (driver_id, subscription) 
    VALUES (?, ?) 
    ON DUPLICATE KEY UPDATE subscription = VALUES(subscription)
");
$stmt->execute([$driver_id, $subscription]);

echo json_encode(['success' => true]);
?>