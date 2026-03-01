<?php
// owner/dashboard.php
// Set page title
$page_title = 'Owner Dashboard';

// Start output buffering
ob_start();

require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('owner');

$owner_id = $_SESSION['owner_id'];

try {
    // Get owner's taxis with stats
    $taxis_sql = "SELECT t.*, r.route_name,
                  (SELECT COUNT(*) FROM trips WHERE taxi_id = t.id AND DATE(departed_at) = CURDATE()) as today_trips,
                  (SELECT SUM(total_fare) FROM trips WHERE taxi_id = t.id AND DATE(departed_at) = CURDATE()) as today_revenue,
                  (SELECT SUM(total_fare - association_levy) FROM trips WHERE taxi_id = t.id) as total_earnings,
                  d.id as driver_id, u.full_name as driver_name
                  FROM taxis t
                  LEFT JOIN routes r ON t.route_id = r.id
                  LEFT JOIN drivers d ON t.id = d.taxi_id
                  LEFT JOIN users u ON d.user_id = u.id
                  WHERE t.owner_id = ?
                  ORDER BY t.id";
    
    $stmt = $pdo->prepare($taxis_sql);
    $stmt->execute([$owner_id]);
    $taxis = $stmt->fetchAll();
    
    // Get weekly earnings
    $weekly_sql = "SELECT DATE(departed_at) as date, 
                   SUM(total_fare) as total,
                   SUM(association_levy) as levies,
                   SUM(total_fare - association_levy) as owner_payout,
                   COUNT(*) as trips
                   FROM trips t
                   JOIN taxis tx ON t.taxi_id = tx.id
                   WHERE tx.owner_id = ?
                   AND departed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                   GROUP BY DATE(departed_at)
                   ORDER BY date DESC";
    
    $stmt = $pdo->prepare($weekly_sql);
    $stmt->execute([$owner_id]);
    $weekly = $stmt->fetchAll();
    
    // Get pending settlements
    $settlements_sql = "SELECT * FROM owner_settlements 
                        WHERE owner_id = ? AND status = 'pending'
                        ORDER BY period_end DESC";
    $stmt = $pdo->prepare($settlements_sql);
    $stmt->execute([$owner_id]);
    $settlements = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Owner dashboard error: " . $e->getMessage());
    $taxis = [];
    $weekly = [];
    $settlements = [];
}
?>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <h5 class="card-title">My Taxis</h5>
                <h2><?= count($taxis) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <h5 class="card-title">Today's Trips</h5>
                <h2>
                    <?= array_sum(array_column($taxis, 'today_trips')) ?>
                </h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <h5 class="card-title">Today's Revenue</h5>
                <h2>R <?= number_format(array_sum(array_column($taxis, 'today_revenue')), 2) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <h5 class="card-title">Pending Settlement</h5>
                <h2>R <?= number_format(array_sum(array_column($settlements, 'amount')), 2) ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- My Taxis -->
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-truck"></i> My Taxis</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Registration</th>
                                <th>Route</th>
                                <th>Driver</th>
                                <th>Today Trips</th>
                                <th>Today Revenue</th>
                                <th>Total Earnings</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($taxis as $taxi): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($taxi['registration_number']) ?></strong></td>
                                    <td><?= htmlspecialchars($taxi['route_name'] ?? 'Not assigned') ?></td>
                                    <td><?= htmlspecialchars($taxi['driver_name'] ?? 'No driver') ?></td>
                                    <td><?= $taxi['today_trips'] ?></td>
                                    <td>R <?= number_format($taxi['today_revenue'] ?? 0, 2) ?></td>
                                    <td>R <?= number_format($taxi['total_earnings'] ?? 0, 2) ?></td>
                                    <td>
                                        <?php if ($taxi['status'] == 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php elseif ($taxi['status'] == 'on_trip'): ?>
                                            <span class="badge bg-warning">On Trip</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Off Rank</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-lightning"></i> Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="my_taxis.php" class="btn btn-outline-primary btn-lg">
                        <i class="bi bi-truck"></i> Manage Taxis
                    </a>
                    <a href="my_drivers.php" class="btn btn-outline-success btn-lg">
                        <i class="bi bi-people"></i> Manage Drivers
                    </a>
                    <a href="earnings.php" class="btn btn-outline-warning btn-lg">
                        <i class="bi bi-graph-up"></i> View Earnings
                    </a>
                    <a href="settlements.php" class="btn btn-outline-info btn-lg">
                        <i class="bi bi-cash-stack"></i> Settlements
                    </a>
                </div>
            </div>
        </div>

        <!-- Pending Settlements -->
        <?php if (!empty($settlements)): ?>
            <div class="card">
                <div class="card-header bg-warning">
                    <h5 class="mb-0">Pending Settlements</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($settlements as $settlement): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <small>
                                    <?= date('d M', strtotime($settlement['period_start'])) ?> - 
                                    <?= date('d M', strtotime($settlement['period_end'])) ?>
                                </small>
                            </div>
                            <div>
                                <strong>R <?= number_format($settlement['amount'], 2) ?></strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Earnings Chart -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">Last 7 Days Earnings</h5>
            </div>
            <div class="card-body">
                <canvas id="earningsChart" height="100"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Earnings Chart
    const ctx = document.getElementById('earningsChart').getContext('2d');
    const chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [<?php 
                $dates = array_reverse(array_column($weekly, 'date'));
                echo "'" . implode("','", array_map(function($d) {
                    return date('D', strtotime($d));
                }, $dates)) . "'";
            ?>],
            datasets: [{
                label: 'Total Fare',
                data: [<?php 
                    $totals = array_reverse(array_column($weekly, 'total'));
                    echo implode(',', array_map(function($v) { 
                        return $v ?? 0; 
                    }, $totals));
                ?>],
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1
            }, {
                label: 'Owner Payout',
                data: [<?php 
                    $payouts = array_reverse(array_column($weekly, 'owner_payout'));
                    echo implode(',', array_map(function($v) { 
                        return $v ?? 0; 
                    }, $payouts));
                ?>],
                borderColor: 'rgb(255, 99, 132)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                }
            }
        }
    });
</script>

<?php
// Get the content
$content = ob_get_clean();

// Include the owner layout
require_once '../layouts/owner_layout.php';
?>