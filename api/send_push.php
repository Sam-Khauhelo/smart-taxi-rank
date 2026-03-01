<?php
// api/send_push.php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('admin'); // Only admins can send push

$driver_id = $_POST['driver_id'] ?? 0;
$title = $_POST['title'] ?? 'Notification';
$message = $_POST['message'] ?? '';
$url = $_POST['url'] ?? '/driver/portal.php';

// Get driver's push subscription
$stmt = $pdo->prepare("SELECT subscription FROM push_subscriptions WHERE driver_id = ?");
$stmt->execute([$driver_id]);
$sub = $stmt->fetch();

if ($sub) {
    $subscription = json_decode($sub['subscription'], true);
    
    // Send push notification
    // You'll need to implement web-push-php or similar
    // This is a placeholder
    sendWebPush($subscription, $title, $message, $url);
}

function sendWebPush($subscription, $title, $message, $url) {
    // Implement using web-push-php library
    // For now, just log
    error_log("Push to " . json_encode($subscription) . ": $title - $message");
}

echo json_encode(['success' => true]);
?>