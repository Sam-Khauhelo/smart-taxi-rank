<?php
// owner/get_settlement.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('owner');

$settlement_id = $_GET['id'] ?? 0;

if (empty($settlement_id)) {
    echo "<div class='alert alert-danger'>Settlement ID required</div>";
    exit();
}

try {
    // Get settlement details
    $sql = "SELECT s.*, 
                   o.bank_name, o.account_number, o.branch_code,
                   u.full_name as owner_name
            FROM owner_settlements s
            JOIN owners o ON s.owner_id = o.id
            JOIN users u ON o.user_id = u.id
            WHERE s.id = :id AND s.owner_id = :owner_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id' => $settlement_id,
        ':owner_id' => $_SESSION['owner_id']
    ]);
    $settlement = $stmt->fetch();
    
    if (!$settlement) {
        echo "<div class='alert alert-danger'>Settlement not found</div>";
        exit();
    }
    
    // Get trips included in this settlement
    $trips_sql = "SELECT 
                    t.departed_at,
                    t.passenger_count,
                    t.total_fare,
                    t.association_levy,
                    t.owner_payout,
                    tx.registration_number,
                    u.full_name as driver_name,
                    r.route_name
                  FROM trips t
                  JOIN taxis tx ON t.taxi_id = tx.id
                  JOIN drivers d ON t.driver_id = d.id
                  JOIN users u ON d.user_id = u.id
                  JOIN routes r ON t.route_id = r.id
                  WHERE tx.owner_id = :owner_id 
                  AND DATE(t.departed_at) BETWEEN :start AND :end
                  ORDER BY t.departed_at";
    
    $trips_stmt = $pdo->prepare($trips_sql);
    $trips_stmt->execute([
        ':owner_id' => $_SESSION['owner_id'],
        ':start' => $settlement['period_start'],
        ':end' => $settlement['period_end']
    ]);
    $trips = $trips_stmt->fetchAll();
    
    ?>
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-12 text-center">
                <h4>Settlement Statement</h4>
                <p class="text-muted">
                    Period: <?= date('d M Y', strtotime($settlement['period_start'])) ?> - 
                    <?= date('d M Y', strtotime($settlement['period_end'])) ?>
                </p>
            </div>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <table class="table table-sm">
                    <tr>
                        <th>Owner:</th>
                        <td><?= htmlspecialchars($settlement['owner_name']) ?></td>
                    </tr>
                    <tr>
                        <th>Bank:</th>
                        <td><?= htmlspecialchars($settlement['bank_name']) ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-sm">
                    <tr>
                        <th>Account:</th>
                        <td><?= htmlspecialchars($settlement['account_number']) ?></td>
                    </tr>
                    <tr>
                        <th>Branch Code:</th>
                        <td><?= htmlspecialchars($settlement['branch_code']) ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <table class="table table-sm table-bordered">
            <thead class="table-dark">
                <tr>
                    <th>Date</th>
                    <th>Taxi</th>
                    <th>Driver</th>
                    <th>Route</th>
                    <th>Pass</th>
                    <th>Fare</th>
                    <th>Levy</th>
                    <th>Payout</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total_fare = 0;
                $total_levy = 0;
                $total_payout = 0;
                foreach ($trips as $trip): 
                    $total_fare += $trip['total_fare'];
                    $total_levy += $trip['association_levy'];
                    $total_payout += $trip['owner_payout'];
                ?>
                <tr>
                    <td><?= date('d/m H:i', strtotime($trip['departed_at'])) ?></td>
                    <td><?= $trip['registration_number'] ?></td>
                    <td><?= $trip['driver_name'] ?></td>
                    <td><?= $trip['route_name'] ?></td>
                    <td class="text-center"><?= $trip['passenger_count'] ?></td>
                    <td class="text-end">R <?= number_format($trip['total_fare'], 2) ?></td>
                    <td class="text-end">R <?= number_format($trip['association_levy'], 2) ?></td>
                    <td class="text-end">R <?= number_format($trip['owner_payout'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-primary">
                <tr>
                    <th colspan="5" class="text-end">TOTALS:</th>
                    <th class="text-end">R <?= number_format($total_fare, 2) ?></th>
                    <th class="text-end">R <?= number_format($total_levy, 2) ?></th>
                    <th class="text-end">R <?= number_format($total_payout, 2) ?></th>
                </tr>
            </tfoot>
        </table>
        
        <div class="row mt-3">
            <div class="col-md-6">
                <p><strong>Requested:</strong> <?= date('d M Y H:i', strtotime($settlement['created_at'])) ?></p>
                <?php if ($settlement['paid_at']): ?>
                    <p><strong>Paid:</strong> <?= date('d M Y H:i', strtotime($settlement['paid_at'])) ?></p>
                <?php endif; ?>
            </div>
            <div class="col-md-6 text-end">
                <h5>Settlement Amount: <span class="text-success">R <?= number_format($settlement['amount'], 2) ?></span></h5>
                <span class="badge bg-<?= $settlement['status'] == 'paid' ? 'success' : 'warning' ?> fs-6">
                    <?= strtoupper($settlement['status']) ?>
                </span>
            </div>
        </div>
    </div>
    <?php
    
} catch (PDOException $e) {
    error_log("Get settlement error: " . $e->getMessage());
    echo "<div class='alert alert-danger'>Database error</div>";
}
?>