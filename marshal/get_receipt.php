<?php
// marshal/get_receipt.php
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
                   tx.registration_number,
                   u.full_name as driver_name,
                   ow.full_name as owner_name,
                   DATE_FORMAT(t.departed_at, '%d/%m/%Y %H:%i') as datetime
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
    <div class="text-center mb-3">
        <h5>SMART TAXI RANK</h5>
        <p class="mb-0">Trip Receipt</p>
        <small><?= $trip['datetime'] ?></small>
    </div>
    
    <hr>
    
    <table class="table table-sm table-borderless">
        <tr>
            <td><strong>Taxi:</strong></td>
            <td><?= htmlspecialchars($trip['registration_number']) ?></td>
        </tr>
        <tr>
            <td><strong>Driver:</strong></td>
            <td><?= htmlspecialchars($trip['driver_name']) ?></td>
        </tr>
        <tr>
            <td><strong>Owner:</strong></td>
            <td><?= htmlspecialchars($trip['owner_name']) ?></td>
        </tr>
        <tr>
            <td><strong>Route:</strong></td>
            <td><?= htmlspecialchars($trip['route_name']) ?></td>
        </tr>
        <tr>
            <td><strong>Passengers:</strong></td>
            <td><?= $trip['passenger_count'] ?></td>
        </tr>
    </table>
    
    <hr>
    
    <table class="table table-sm table-borderless">
        <tr>
            <td><strong>Total Fare:</strong></td>
            <td class="text-end">R <?= number_format($trip['total_fare'], 2) ?></td>
        </tr>
        <tr>
            <td><strong>Association Levy:</strong></td>
            <td class="text-end text-danger">- R <?= number_format($trip['association_levy'], 2) ?></td>
        </tr>
        <tr class="border-top">
            <td><strong>Owner Payout:</strong></td>
            <td class="text-end text-success h5">R <?= number_format($trip['owner_payout'], 2) ?></td>
        </tr>
    </table>
    
    <hr>
    
    <div class="text-center text-muted small">
        <p>Thank you for using Smart Taxi Rank</p>
        <p>Receipt #<?= str_pad($trip['id'], 6, '0', STR_PAD_LEFT) ?></p>
    </div>
    <?php
    
} catch (PDOException $e) {
    error_log("Get receipt error: " . $e->getMessage());
    echo "<div class='alert alert-danger'>Database error</div>";
}
?>