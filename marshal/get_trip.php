<?php
// marshal/get_trip.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('marshal');

$trip_id = $_GET['id'] ?? 0;

if (empty($trip_id)) {
    echo "<div class='alert alert-danger'>Trip ID required</div>";
    exit();
}

try {
    $sql = "SELECT t.*, 
                   r.route_name,
                   r.fare_amount as route_fare,
                   tx.registration_number,
                   u.full_name as driver_name,
                   u.phone_number as driver_phone,
                   ow.full_name as owner_name,
                   ow.phone_number as owner_phone,
                   DATE_FORMAT(t.departed_at, '%d %M %Y') as formatted_date,
                   DATE_FORMAT(t.departed_at, '%H:%i') as formatted_time,
                   DATE_FORMAT(t.departed_at, '%W') as day_of_week
            FROM trips t
            JOIN routes r ON t.route_id = r.id
            JOIN taxis tx ON t.taxi_id = tx.id
            JOIN drivers d ON t.driver_id = d.id
            JOIN users u ON d.user_id = u.id
            JOIN owners o ON tx.owner_id = o.id
            JOIN users ow ON o.user_id = ow.id
            WHERE t.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$trip_id]);
    $trip = $stmt->fetch();
    
    if (!$trip) {
        echo "<div class='alert alert-danger'>Trip not found</div>";
        exit();
    }
    
    ?>
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-6">
                <strong>Date:</strong>
                <p><?= $trip['formatted_date'] ?> (<?= $trip['day_of_week'] ?>)</p>
            </div>
            <div class="col-6">
                <strong>Time:</strong>
                <p><?= $trip['formatted_time'] ?></p>
            </div>
        </div>
        
        <div class="row mb-3">
            <div class="col-6">
                <strong>Taxi:</strong>
                <p class="h5"><?= htmlspecialchars($trip['registration_number']) ?></p>
            </div>
            <div class="col-6">
                <strong>Driver:</strong>
                <p><?= htmlspecialchars($trip['driver_name']) ?><br>
                <small><?= htmlspecialchars($trip['driver_phone']) ?></small></p>
            </div>
        </div>
        
        <div class="row mb-3">
            <div class="col-6">
                <strong>Owner:</strong>
                <p><?= htmlspecialchars($trip['owner_name']) ?><br>
                <small><?= htmlspecialchars($trip['owner_phone']) ?></small></p>
            </div>
            <div class="col-6">
                <strong>Route:</strong>
                <p><?= htmlspecialchars($trip['route_name']) ?></p>
            </div>
        </div>
        
        <div class="row mb-3">
            <div class="col-4">
                <strong>Passengers:</strong>
                <p class="h3"><?= $trip['passenger_count'] ?></p>
            </div>
            <div class="col-4">
                <strong>Fare per person:</strong>
                <p>R <?= number_format($trip['route_fare'], 2) ?></p>
            </div>
            <div class="col-4">
                <strong>Total Fare:</strong>
                <p class="h4">R <?= number_format($trip['total_fare'], 2) ?></p>
            </div>
        </div>
        
        <div class="row mb-3">
            <div class="col-6">
                <strong>Association Levy:</strong>
                <p class="text-danger h5">- R <?= number_format($trip['association_levy'], 2) ?></p>
            </div>
            <div class="col-6">
                <strong>Owner Payout:</strong>
                <p class="text-success h4">R <?= number_format($trip['owner_payout'], 2) ?></p>
            </div>
        </div>
        
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> 
            Levy rate: <?= round(($trip['association_levy'] / $trip['total_fare']) * 100, 1) ?>% of total fare
        </div>
    </div>
    <?php
    
} catch (PDOException $e) {
    error_log("Get trip error: " . $e->getMessage());
    echo "<div class='alert alert-danger'>Database error</div>";
}
?>