<?php
// api/track_install.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';

$data = json_decode(file_get_contents('php://input'), true);
$driver_id = $data['driver_id'] ?? 0;
$platform = $data['platform'] ?? 'web';

if ($driver_id) {
    // Create app_installs table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS app_installs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            driver_id INT NOT NULL,
            platform VARCHAR(20),
            installed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_used TIMESTAMP NULL,
            FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE
        )
    ");
    
    // Record install
    $stmt = $pdo->prepare("
        INSERT INTO app_installs (driver_id, platform, installed_at) 
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE last_used = NOW()
    ");
    $stmt->execute([$driver_id, $platform]);
}

echo json_encode(['success' => true]);
?>