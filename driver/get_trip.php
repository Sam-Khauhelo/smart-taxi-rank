<?php
// driver/get_day_trips.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('driver');

$date = $_GET['date'] ?? '';

if (empty($date)) {
    echo "<div class='alert alert-danger'>Date required</div>";
    exit();
}

try {
    $sql = "SELECT t.*, 
                   r.route_name,
                   tx.registration_number,
                   TIME(t.departed_at) as trip_time
            FROM trips t
            JOIN routes r ON t.route_id = r.id
            JOIN taxis tx ON t.taxi_id = tx.id
            WHERE t.driver_id = :driver_id AND DATE(t.departed_at) = :date
            ORDER BY t.departed_at";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':driver_id' => $_SESSION['driver_id'],
        ':date' => $date
    ]);
    $trips = $stmt->fetchAll();
    
    if (empty($trips)) {
        echo "<div class='alert alert-warning'>No trips on this day</div>";
        exit();
    }
    
    $total_fare = 0;
    $total_levy = 0;
    $total_payout = 0;
    ?>
    
    <div class="table-responsive">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Taxi</th>
                    <th>Route</th>
                    <th>Pass</th>
                    <th>Fare</th>
                    <th>Levy</th>
                    <th>Payout</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($trips as $trip): 
                    $total_fare += $trip['total_fare'];
                    $total_levy += $trip['association_levy'];
                    $total_payout += $trip['owner_payout'];
                ?>
                <tr>
                    <td><?= date('H:i', strtotime($trip['departed_at'])) ?></td>
                    <td><?= htmlspecialchars($trip['registration_number']) ?></td>
                    <td><?= htmlspecialchars($trip['route_name']) ?></td>
                    <td class="text-center"><?= $trip['passenger_count'] ?></td>
                    <td>R <?= number_format($trip['total_fare'], 2) ?></td>
                    <td>R <?= number_format($trip['association_levy'], 2) ?></td>
                    <td class="fw-bold">R <?= number_format($trip['owner_payout'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-primary">
                <tr>
                    <th colspan="4" class="text-end">TOTALS:</th>
                    <th>R <?= number_format($total_fare, 2) ?></th>
                    <th>R <?= number_format($total_levy, 2) ?></th>
                    <th>R <?= number_format($total_payout, 2) ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
    
    <?php
    // Get driver's payment info for this day
    $driver_sql = "SELECT employment_type, payment_rate FROM drivers WHERE id = ?";
    $driver_stmt = $pdo->prepare($driver_sql);
    $driver_stmt->execute([$_SESSION['driver_id']]);
    $driver = $driver_stmt->fetch();
    
    if ($driver && $driver['employment_type'] == 'commission'):
        $commission = $total_payout * ($driver['payment_rate'] / 100);
    ?>
        <div class="alert alert-success mt-3">
            <strong>Your Commission for this day:</strong>
            <h4 class="mb-0">R <?= number_format($commission, 2) ?></h4>
            <small><?= $driver['payment_rate'] ?>% of owner payout</small>
        </div>
    <?php endif; ?>
    
    <?php
    
} catch (PDOException $e) {
    error_log("Get day trips error: " . $e->getMessage());
    echo "<div class='alert alert-danger'>Database error</div>";
}
?>