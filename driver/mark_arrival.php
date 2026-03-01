<?php
// driver/mark_arrival.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('driver');

// Get driver's current active trip
$driver_id = $_SESSION['driver_id'];

$trip_sql = "SELECT t.*, r.route_name, tx.registration_number
             FROM trips t
             JOIN routes r ON t.route_id = r.id
             JOIN taxis tx ON t.taxi_id = tx.id
             WHERE t.driver_id = ? AND t.trip_status = 'departed'
             ORDER BY t.departed_at DESC
             LIMIT 1";
$trip_stmt = $pdo->prepare($trip_sql);
$trip_stmt->execute([$driver_id]);
$current_trip = $trip_stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_arrival'])) {
    $trip_id = $_POST['trip_id'];
    
    // Update trip status
    $update_sql = "UPDATE trips SET arrived_at = NOW(), trip_status = 'arrived' WHERE id = ? AND driver_id = ?";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute([$trip_id, $driver_id]);
    
    echo "<script>
        alert('✅ Arrival confirmed! Trip completed.');
        window.location.href = 'portal.php';
    </script>";
    exit();
}
?>

<div class="container">
    <?php if ($current_trip): ?>
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4>Current Trip</h4>
            </div>
            <div class="card-body">
                <p><strong>Taxi:</strong> <?= $current_trip['registration_number'] ?></p>
                <p><strong>Route:</strong> <?= $current_trip['route_name'] ?></p>
                <p><strong>Departed:</strong> <?= date('H:i', strtotime($current_trip['departed_at'])) ?></p>
                <p><strong>Passengers:</strong> <?= $current_trip['passenger_count'] ?></p>
                
                <form method="POST">
                    <input type="hidden" name="trip_id" value="<?= $current_trip['id'] ?>">
                    <button type="submit" name="confirm_arrival" class="btn btn-success btn-lg w-100">
                        <i class="bi bi-check-circle"></i> Confirm Arrival at Destination
                    </button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> No active trip. You are not on the road.
        </div>
    <?php endif; ?>
</div>