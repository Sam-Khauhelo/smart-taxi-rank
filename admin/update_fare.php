<?php
// admin/update_fare.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('admin');

$route_id = $_GET['id'] ?? 0;
$new_fare = $_GET['fare'] ?? 0;

if (!$route_id || !$new_fare) {
    echo "<script>
        alert('❌ Invalid request! Missing route ID or fare amount.');
        window.location.href = 'route_optimization.php';
    </script>";
    exit();
}

try {
    // Get current route details before update
    $current_sql = "SELECT route_name, fare_amount FROM routes WHERE id = ?";
    $current_stmt = $pdo->prepare($current_sql);
    $current_stmt->execute([$route_id]);
    $current = $current_stmt->fetch();
    
    if (!$current) {
        echo "<script>
            alert('❌ Route not found!');
            window.location.href = 'route_optimization.php';
        </script>";
        exit();
    }
    
    $old_fare = $current['fare_amount'];
    $route_name = $current['route_name'];
    
    // Update the fare
    $update_sql = "UPDATE routes SET fare_amount = ? WHERE id = ?";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute([$new_fare, $route_id]);
    
    // Log the fare change
    $log_sql = "INSERT INTO fare_change_log (route_id, old_fare, new_fare, changed_by, changed_at) 
                VALUES (?, ?, ?, ?, NOW())";
    $log_stmt = $pdo->prepare($log_sql);
    $log_stmt->execute([$route_id, $old_fare, $new_fare, $_SESSION['full_name']]);
    
    // Create notification for marshals and drivers
    $notify_sql = "INSERT INTO notifications (user_id, message, type, created_at) 
                   SELECT id, ?, 'fare_change', NOW() FROM users WHERE role IN ('marshal', 'driver')";
    $notify_stmt = $pdo->prepare($notify_sql);
    $notify_stmt->execute(["Fare changed for $route_name: R" . number_format($old_fare, 2) . " → R" . number_format($new_fare, 2)]);
    
    echo "<script>
        alert('✅ Fare updated successfully!\\n\\nRoute: $route_name\\nOld Fare: R" . number_format($old_fare, 2) . "\\nNew Fare: R" . number_format($new_fare, 2) . "');
        window.location.href = 'route_optimization.php';
    </script>";
    
} catch (PDOException $e) {
    error_log("Fare update error: " . $e->getMessage());
    echo "<script>
        alert('❌ Error updating fare: " . addslashes($e->getMessage()) . "');
        window.location.href = 'route_optimization.php';
    </script>";
}
?>