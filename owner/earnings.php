<?php
// owner/earnings.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('owner');

// Set page title
$page_title = 'My Earnings';

// Start output buffering
ob_start();

$owner_id = $_SESSION['owner_id'];

// Get date range from request or default to current month
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$view_type = $_GET['view'] ?? 'summary';

try {
    // Get owner's taxis for dropdown
    $taxis_sql = "SELECT id, registration_number FROM taxis WHERE owner_id = ? ORDER BY registration_number";
    $stmt = $pdo->prepare($taxis_sql);
    $stmt->execute([$owner_id]);
    $taxis = $stmt->fetchAll();
    
    // Summary statistics for selected period
    $summary_sql = "SELECT 
                        COUNT(DISTINCT t.id) as total_trips,
                        COALESCE(SUM(t.passenger_count), 0) as total_passengers,
                        COALESCE(SUM(t.total_fare), 0) as total_revenue,
                        COALESCE(SUM(t.association_levy), 0) as total_levy,
                        COALESCE(SUM(t.owner_payout), 0) as total_earnings,
                        COUNT(DISTINCT t.taxi_id) as active_taxis,
                        COUNT(DISTINCT t.driver_id) as active_drivers,
                        COALESCE(AVG(t.passenger_count), 0) as avg_passengers
                    FROM trips t
                    JOIN taxis tx ON t.taxi_id = tx.id
                    WHERE tx.owner_id = :owner_id 
                    AND DATE(t.departed_at) BETWEEN :start_date AND :end_date";
    
    $summary_stmt = $pdo->prepare($summary_sql);
    $summary_stmt->execute([
        ':owner_id' => $owner_id,
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    $summary = $summary_stmt->fetch();
    
    // Daily earnings breakdown
    $daily_sql = "SELECT 
                    DATE(t.departed_at) as date,
                    COUNT(*) as trips,
                    SUM(t.passenger_count) as passengers,
                    SUM(t.total_fare) as revenue,
                    SUM(t.association_levy) as levy,
                    SUM(t.owner_payout) as earnings
                  FROM trips t
                  JOIN taxis tx ON t.taxi_id = tx.id
                  WHERE tx.owner_id = :owner_id 
                  AND DATE(t.departed_at) BETWEEN :start_date AND :end_date
                  GROUP BY DATE(t.departed_at)
                  ORDER BY date DESC";
    
    $daily_stmt = $pdo->prepare($daily_sql);
    $daily_stmt->execute([
        ':owner_id' => $owner_id,
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    $daily = $daily_stmt->fetchAll();
    
    // Earnings by taxi
    $taxi_earnings_sql = "SELECT 
                            tx.id as taxi_id,
                            tx.registration_number,
                            COUNT(t.id) as trips,
                            SUM(t.passenger_count) as passengers,
                            SUM(t.total_fare) as revenue,
                            SUM(t.association_levy) as levy,
                            SUM(t.owner_payout) as earnings,
                            AVG(t.passenger_count) as avg_passengers
                          FROM taxis tx
                          LEFT JOIN trips t ON tx.id = t.taxi_id 
                              AND DATE(t.departed_at) BETWEEN :start_date AND :end_date
                          WHERE tx.owner_id = :owner_id
                          GROUP BY tx.id, tx.registration_number
                          ORDER BY earnings DESC";
    
    $taxi_earnings_stmt = $pdo->prepare($taxi_earnings_sql);
    $taxi_earnings_stmt->execute([
        ':owner_id' => $owner_id,
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    $taxi_earnings = $taxi_earnings_stmt->fetchAll();
    
    // Earnings by driver
    $driver_earnings_sql = "SELECT 
                            d.id as driver_id,
                            u.full_name as driver_name,
                            COUNT(t.id) as trips,
                            SUM(t.passenger_count) as passengers,
                            SUM(t.total_fare) as revenue,
                            SUM(t.association_levy) as levy,
                            SUM(t.owner_payout) as earnings,
                            AVG(t.passenger_count) as avg_passengers
                          FROM drivers d
                          JOIN users u ON d.user_id = u.id
                          LEFT JOIN trips t ON d.id = t.driver_id 
                              AND DATE(t.departed_at) BETWEEN :start_date AND :end_date
                          WHERE d.owner_id = :owner_id
                          GROUP BY d.id, u.full_name
                          ORDER BY earnings DESC";
    
    $driver_earnings_stmt = $pdo->prepare($driver_earnings_sql);
    $driver_earnings_stmt->execute([
        ':owner_id' => $owner_id,
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    $driver_earnings = $driver_earnings_stmt->fetchAll();
    
    // Monthly summary for chart
    $monthly_sql = "SELECT 
                    DATE_FORMAT(t.departed_at, '%Y-%m') as month,
                    COUNT(*) as trips,
                    SUM(t.total_fare) as revenue,
                    SUM(t.association_levy) as levy,
                    SUM(t.owner_payout) as earnings
                  FROM trips t
                  JOIN taxis tx ON t.taxi_id = tx.id
                  WHERE tx.owner_id = :owner_id 
                  AND t.departed_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                  GROUP BY DATE_FORMAT(t.departed_at, '%Y-%m')
                  ORDER BY month DESC";
    
    $monthly_stmt = $pdo->prepare($monthly_sql);
    $monthly_stmt->execute([':owner_id' => $owner_id]);
    $monthly = $monthly_stmt->fetchAll();
    
    // Weekly comparison
    $weekly_sql = "SELECT 
                    WEEK(t.departed_at) as week_num,
                    MIN(DATE(t.departed_at)) as week_start,
                    MAX(DATE(t.departed_at)) as week_end,
                    COUNT(*) as trips,
                    SUM(t.total_fare) as revenue,
                    SUM(t.association_levy) as levy,
                    SUM(t.owner_payout) as earnings
                  FROM trips t
                  JOIN taxis tx ON t.taxi_id = tx.id
                  WHERE tx.owner_id = :owner_id 
                  AND t.departed_at >= DATE_SUB(NOW(), INTERVAL 8 WEEK)
                  GROUP BY WEEK(t.departed_at)
                  ORDER BY week_num DESC";
    
    $weekly_stmt = $pdo->prepare($weekly_sql);
    $weekly_stmt->execute([':owner_id' => $owner_id]);
    $weekly = $weekly_stmt->fetchAll();
    
    // Pending settlements
    $settlements_sql = "SELECT * FROM owner_settlements 
                        WHERE owner_id = ? AND status = 'pending'
                        ORDER BY period_end DESC";
    $stmt = $pdo->prepare($settlements_sql);
    $stmt->execute([$owner_id]);
    $pending_settlements = $stmt->fetchAll();
    
    // Paid settlements
    $paid_sql = "SELECT * FROM owner_settlements 
                WHERE owner_id = ? AND status = 'paid'
                ORDER BY period_end DESC
                LIMIT 5";
    $stmt = $pdo->prepare($paid_sql);
    $stmt->execute([$owner_id]);
    $paid_settlements = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Earnings error: " . $e->getMessage());
    $summary = [];
    $daily = [];
    $taxi_earnings = [];
    $driver_earnings = [];
    $monthly = [];
    $weekly = [];
    $pending_settlements = [];
    $paid_settlements = [];
}

// Safe division function
function safeDivide($numerator, $denominator, $decimals = 2) {
    if ($denominator == 0 || $denominator == null) {
        return 0;
    }
    return round($numerator / $denominator, $decimals);
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-cash-stack"></i> My Earnings</h2>
        <div>
            <button class="btn btn-success" onclick="exportToExcel()">
                <i class="bi bi-file-excel"></i> Export
            </button>
            <button class="btn btn-primary" onclick="printReport()">
                <i class="bi bi-printer"></i> Print
            </button>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="card mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="bi bi-calendar-range"></i> Filter by Date</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?= $start_date ?>">
                </div>
                <div class="col-md-4">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?= $end_date ?>">
                </div>
                <div class="col-md-4">
                    <label for="view" class="form-label">View Type</label>
                    <select class="form-select" id="view" name="view">
                        <option value="summary" <?= $view_type == 'summary' ? 'selected' : '' ?>>Summary View</option>
                        <option value="daily" <?= $view_type == 'daily' ? 'selected' : '' ?>>Daily Breakdown</option>
                        <option value="taxis" <?= $view_type == 'taxis' ? 'selected' : '' ?>>By Taxi</option>
                        <option value="drivers" <?= $view_type == 'drivers' ? 'selected' : '' ?>>By Driver</option>
                        <option value="weekly" <?= $view_type == 'weekly' ? 'selected' : '' ?>>Weekly Comparison</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Apply Filter
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="setToday()">Today</button>
                    <button type="button" class="btn btn-secondary" onclick="setThisWeek()">This Week</button>
                    <button type="button" class="btn btn-secondary" onclick="setThisMonth()">This Month</button>
                    <button type="button" class="btn btn-secondary" onclick="setLastMonth()">Last Month</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-xl-2 col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Total Trips</h6>
                    <h3 class="mb-0"><?= number_format($summary['total_trips'] ?? 0) ?></h3>
                    <small><?= date('d M', strtotime($start_date)) ?> - <?= date('d M', strtotime($end_date)) ?></small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Passengers</h6>
                    <h3 class="mb-0"><?= number_format($summary['total_passengers'] ?? 0) ?></h3>
                    <small>Avg: <?= safeDivide($summary['total_passengers'] ?? 0, $summary['total_trips'] ?? 1, 1) ?> per trip</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Total Revenue</h6>
                    <h3 class="mb-0">R <?= number_format($summary['total_revenue'] ?? 0, 2) ?></h3>
                    <small>Gross income</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Association Levy</h6>
                    <h3 class="mb-0">R <?= number_format($summary['total_levy'] ?? 0, 2) ?></h3>
                    <small><?= safeDivide(($summary['total_levy'] ?? 0) * 100, $summary['total_revenue'] ?? 1, 1) ?>% of revenue</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h6 class="text-white-50">My Earnings</h6>
                    <h3 class="mb-0">R <?= number_format($summary['total_earnings'] ?? 0, 2) ?></h3>
                    <small>Net profit</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4">
            <div class="card bg-dark text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Active</h6>
                    <h3 class="mb-0"><?= $summary['active_taxis'] ?? 0 ?> Taxis</h3>
                    <h3 class="mb-0"><?= $summary['active_drivers'] ?? 0 ?> Drivers</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Settlements Alert -->
    <?php if (!empty($pending_settlements)): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            You have <strong><?= count($pending_settlements) ?></strong> pending settlement(s) totaling 
            <strong>R <?= number_format(array_sum(array_column($pending_settlements, 'amount')), 2) ?></strong>
            <a href="settlements.php" class="alert-link float-end">View Settlements <i class="bi bi-arrow-right"></i></a>
        </div>
    <?php endif; ?>

    <?php if ($view_type == 'summary'): ?>
        <!-- Summary View - Daily Breakdown -->
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-calendar-day"></i> Daily Earnings Breakdown</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Trips</th>
                                <th>Passengers</th>
                                <th>Revenue</th>
                                <th>Levy</th>
                                <th>My Earnings</th>
                                <th>Avg Passengers</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($daily)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                        <h5 class="text-muted">No earnings data for selected period</h5>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $total_revenue = 0;
                                $total_levy = 0;
                                $total_earnings = 0;
                                foreach ($daily as $day): 
                                    $total_revenue += $day['revenue'];
                                    $total_levy += $day['levy'];
                                    $total_earnings += $day['earnings'];
                                ?>
                                <tr>
                                    <td><strong><?= date('d M Y', strtotime($day['date'])) ?></strong></td>
                                    <td><?= $day['trips'] ?></td>
                                    <td><?= $day['passengers'] ?></td>
                                    <td>R <?= number_format($day['revenue'], 2) ?></td>
                                    <td>R <?= number_format($day['levy'], 2) ?></td>
                                    <td class="fw-bold text-success">R <?= number_format($day['earnings'], 2) ?></td>
                                    <td><?= safeDivide($day['passengers'], $day['trips'], 1) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info" onclick="viewDayDetails('<?= $day['date'] ?>')">
                                            <i class="bi bi-eye"></i> Details
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="table-primary fw-bold">
                                    <td>TOTAL</td>
                                    <td><?= array_sum(array_column($daily, 'trips')) ?></td>
                                    <td><?= array_sum(array_column($daily, 'passengers')) ?></td>
                                    <td>R <?= number_format($total_revenue, 2) ?></td>
                                    <td>R <?= number_format($total_levy, 2) ?></td>
                                    <td class="text-success">R <?= number_format($total_earnings, 2) ?></td>
                                    <td><?= safeDivide(array_sum(array_column($daily, 'passengers')), array_sum(array_column($daily, 'trips')), 1) ?></td>
                                    <td></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Monthly Chart -->
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-graph-up"></i> Monthly Earnings (Last 12 Months)</h5>
            </div>
            <div class="card-body">
                <canvas id="monthlyChart" height="100"></canvas>
            </div>
        </div>

    <?php elseif ($view_type == 'daily'): ?>
        <!-- Detailed Daily View -->
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-calendar-day"></i> Detailed Daily Report</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="dailyTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Taxi</th>
                                <th>Driver</th>
                                <th>Route</th>
                                <th>Passengers</th>
                                <th>Fare</th>
                                <th>Levy</th>
                                <th>My Earnings</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Get detailed trips for the period
                            $details_sql = "SELECT 
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
                                          AND DATE(t.departed_at) BETWEEN :start_date AND :end_date
                                          ORDER BY t.departed_at DESC";
                            
                            $details_stmt = $pdo->prepare($details_sql);
                            $details_stmt->execute([
                                ':owner_id' => $owner_id,
                                ':start_date' => $start_date,
                                ':end_date' => $end_date
                            ]);
                            $details = $details_stmt->fetchAll();
                            
                            if (empty($details)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                        <h5 class="text-muted">No trip details for selected period</h5>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($details as $trip): ?>
                                <tr>
                                    <td><?= date('d M Y', strtotime($trip['departed_at'])) ?></td>
                                    <td><strong><?= htmlspecialchars($trip['registration_number']) ?></strong></td>
                                    <td><?= htmlspecialchars($trip['driver_name']) ?></td>
                                    <td><?= htmlspecialchars($trip['route_name']) ?></td>
                                    <td><?= $trip['passenger_count'] ?></td>
                                    <td>R <?= number_format($trip['total_fare'], 2) ?></td>
                                    <td>R <?= number_format($trip['association_levy'], 2) ?></td>
                                    <td class="fw-bold text-success">R <?= number_format($trip['owner_payout'], 2) ?></td>
                                    <td><?= date('H:i', strtotime($trip['departed_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif ($view_type == 'taxis'): ?>
        <!-- Earnings by Taxi -->
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-truck"></i> Earnings by Taxi</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Taxi</th>
                                <th>Trips</th>
                                <th>Passengers</th>
                                <th>Revenue</th>
                                <th>Levy</th>
                                <th>My Earnings</th>
                                <th>Avg Passengers</th>
                                <th>Earnings/Trip</th>
                                <th>% of Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($taxi_earnings)): ?>
                                <tr>
                                    <td colspan="10" class="text-center py-4">
                                        <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                        <h5 class="text-muted">No taxi earnings data</h5>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $total_earnings = array_sum(array_column($taxi_earnings, 'earnings'));
                                foreach ($taxi_earnings as $index => $taxi): 
                                ?>
                                <tr>
                                    <td><span class="badge bg-<?= $index == 0 ? 'warning' : ($index == 1 ? 'secondary' : 'primary') ?>">#<?= $index + 1 ?></span></td>
                                    <td><strong><?= htmlspecialchars($taxi['registration_number']) ?></strong></td>
                                    <td><?= $taxi['trips'] ?></td>
                                    <td><?= $taxi['passengers'] ?></td>
                                    <td>R <?= number_format($taxi['revenue'], 2) ?></td>
                                    <td>R <?= number_format($taxi['levy'], 2) ?></td>
                                    <td class="fw-bold text-success">R <?= number_format($taxi['earnings'], 2) ?></td>
                                    <td><?= safeDivide($taxi['passengers'], $taxi['trips'], 1) ?></td>
                                    <td>R <?= safeDivide($taxi['earnings'], $taxi['trips'], 2) ?></td>
                                    <td><?= safeDivide($taxi['earnings'] * 100, $total_earnings, 1) ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif ($view_type == 'drivers'): ?>
        <!-- Earnings by Driver -->
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-people"></i> Earnings by Driver</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Driver</th>
                                <th>Trips</th>
                                <th>Passengers</th>
                                <th>Revenue</th>
                                <th>Levy</th>
                                <th>My Earnings</th>
                                <th>Avg Passengers</th>
                                <th>Earnings/Trip</th>
                                <th>% of Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($driver_earnings)): ?>
                                <tr>
                                    <td colspan="10" class="text-center py-4">
                                        <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                        <h5 class="text-muted">No driver earnings data</h5>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $total_earnings = array_sum(array_column($driver_earnings, 'earnings'));
                                foreach ($driver_earnings as $index => $driver): 
                                ?>
                                <tr>
                                    <td><span class="badge bg-<?= $index == 0 ? 'warning' : ($index == 1 ? 'secondary' : 'primary') ?>">#<?= $index + 1 ?></span></td>
                                    <td><strong><?= htmlspecialchars($driver['driver_name']) ?></strong></td>
                                    <td><?= $driver['trips'] ?></td>
                                    <td><?= $driver['passengers'] ?></td>
                                    <td>R <?= number_format($driver['revenue'], 2) ?></td>
                                    <td>R <?= number_format($driver['levy'], 2) ?></td>
                                    <td class="fw-bold text-success">R <?= number_format($driver['earnings'], 2) ?></td>
                                    <td><?= safeDivide($driver['passengers'], $driver['trips'], 1) ?></td>
                                    <td>R <?= safeDivide($driver['earnings'], $driver['trips'], 2) ?></td>
                                    <td><?= safeDivide($driver['earnings'] * 100, $total_earnings, 1) ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif ($view_type == 'weekly'): ?>
        <!-- Weekly Comparison -->
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-calendar-week"></i> Weekly Comparison (Last 8 Weeks)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Week</th>
                                <th>Period</th>
                                <th>Trips</th>
                                <th>Passengers</th>
                                <th>Revenue</th>
                                <th>Levy</th>
                                <th>My Earnings</th>
                                <th>Change</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($weekly)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                        <h5 class="text-muted">No weekly data available</h5>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $prev_earnings = 0;
                                foreach ($weekly as $week): 
                                    $change = $prev_earnings > 0 ? (($week['earnings'] - $prev_earnings) / $prev_earnings) * 100 : 0;
                                    $change_class = $change > 0 ? 'text-success' : ($change < 0 ? 'text-danger' : 'text-muted');
                                    $change_icon = $change > 0 ? '📈' : ($change < 0 ? '📉' : '➡️');
                                ?>
                                <tr>
                                    <td><strong>Week <?= $week['week_num'] ?></strong></td>
                                    <td><?= date('d M', strtotime($week['week_start'])) ?> - <?= date('d M', strtotime($week['week_end'])) ?></td>
                                    <td><?= $week['trips'] ?></td>
                                    <td><?= $week['passengers'] ?></td>
                                    <td>R <?= number_format($week['revenue'], 2) ?></td>
                                    <td>R <?= number_format($week['levy'], 2) ?></td>
                                    <td class="fw-bold text-success">R <?= number_format($week['earnings'], 2) ?></td>
                                    <td class="<?= $change_class ?>">
                                        <?= $change_icon ?> <?= $change > 0 ? '+' : '' ?><?= number_format($change, 1) ?>%
                                    </td>
                                </tr>
                                <?php 
                                    $prev_earnings = $week['earnings'];
                                endforeach; 
                                ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Weekly Chart -->
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-graph-up"></i> Weekly Earnings Trend</h5>
            </div>
            <div class="card-body">
                <canvas id="weeklyChart" height="100"></canvas>
            </div>
        </div>
    <?php endif; ?>

    <!-- Recent Settlements Card -->
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-warning">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Pending Settlements</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_settlements)): ?>
                        <p class="text-muted text-center py-3">No pending settlements</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($pending_settlements as $settlement): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= date('d M Y', strtotime($settlement['period_start'])) ?> - <?= date('d M Y', strtotime($settlement['period_end'])) ?></strong>
                                        <br>
                                        <small class="text-muted">Created: <?= date('d M Y', strtotime($settlement['created_at'])) ?></small>
                                    </div>
                                    <div>
                                        <span class="badge bg-warning fs-6">R <?= number_format($settlement['amount'], 2) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-check-circle"></i> Recent Paid Settlements</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($paid_settlements)): ?>
                        <p class="text-muted text-center py-3">No paid settlements yet</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($paid_settlements as $settlement): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= date('d M Y', strtotime($settlement['period_start'])) ?> - <?= date('d M Y', strtotime($settlement['period_end'])) ?></strong>
                                        <br>
                                        <small class="text-muted">Paid: <?= date('d M Y', strtotime($settlement['paid_at'])) ?></small>
                                    </div>
                                    <div>
                                        <span class="badge bg-success fs-6">R <?= number_format($settlement['amount'], 2) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Day Details Modal -->
<div class="modal fade" id="dayDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-calendar-day"></i> <span id="dayDetailsTitle"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm" id="dayDetailsTable">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Taxi</th>
                                <th>Driver</th>
                                <th>Route</th>
                                <th>Passengers</th>
                                <th>Fare</th>
                                <th>Levy</th>
                                <th>Earnings</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot id="dayDetailsTotal"></tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Set date shortcuts
function setToday() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('start_date').value = today;
    document.getElementById('end_date').value = today;
    document.querySelector('form').submit();
}

function setThisWeek() {
    const today = new Date();
    const firstDay = new Date(today.setDate(today.getDate() - today.getDay() + 1));
    const lastDay = new Date(today.setDate(today.getDate() - today.getDay() + 7));
    
    document.getElementById('start_date').value = firstDay.toISOString().split('T')[0];
    document.getElementById('end_date').value = lastDay.toISOString().split('T')[0];
    document.querySelector('form').submit();
}

function setThisMonth() {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    
    document.getElementById('start_date').value = firstDay.toISOString().split('T')[0];
    document.getElementById('end_date').value = lastDay.toISOString().split('T')[0];
    document.querySelector('form').submit();
}

function setLastMonth() {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth() - 1, 1);
    const lastDay = new Date(today.getFullYear(), today.getMonth(), 0);
    
    document.getElementById('start_date').value = firstDay.toISOString().split('T')[0];
    document.getElementById('end_date').value = lastDay.toISOString().split('T')[0];
    document.querySelector('form').submit();
}

// View day details
function viewDayDetails(date) {
    document.getElementById('dayDetailsTitle').textContent = `Trips for ${new Date(date).toLocaleDateString('en-ZA', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}`;
    
    fetch(`get_day_details.php?date=${date}`)
        .then(response => response.json())
        .then(data => {
            let html = '';
            let totalFare = 0;
            let totalLevy = 0;
            let totalEarnings = 0;
            
            if (data.trips && data.trips.length > 0) {
                data.trips.forEach(trip => {
                    html += `<tr>
                        <td>${trip.time}</td>
                        <td>${trip.registration_number}</td>
                        <td>${trip.driver_name}</td>
                        <td>${trip.route_name}</td>
                        <td>${trip.passenger_count}</td>
                        <td>R ${parseFloat(trip.total_fare).toFixed(2)}</td>
                        <td>R ${parseFloat(trip.association_levy).toFixed(2)}</td>
                        <td class="fw-bold text-success">R ${parseFloat(trip.owner_payout).toFixed(2)}</td>
                    </tr>`;
                    
                    totalFare += parseFloat(trip.total_fare);
                    totalLevy += parseFloat(trip.association_levy);
                    totalEarnings += parseFloat(trip.owner_payout);
                });
            } else {
                html = '<tr><td colspan="8" class="text-center">No trips on this day</td></tr>';
            }
            
            document.querySelector('#dayDetailsTable tbody').innerHTML = html;
            document.getElementById('dayDetailsTotal').innerHTML = `
                <tr class="table-primary fw-bold">
                    <td colspan="5" class="text-end">TOTAL:</td>
                    <td>R ${totalFare.toFixed(2)}</td>
                    <td>R ${totalLevy.toFixed(2)}</td>
                    <td class="text-success">R ${totalEarnings.toFixed(2)}</td>
                </tr>
            `;
            
            new bootstrap.Modal(document.getElementById('dayDetailsModal')).show();
        });
}

// Export to Excel
function exportToExcel() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const view = document.getElementById('view').value;
    
    window.location.href = `export/export_earnings.php?start_date=${startDate}&end_date=${endDate}&view=${view}`;
}

// Print report
function printReport() {
    window.print();
}

<?php if (!empty($monthly)): ?>
// Monthly Chart
const monthlyCtx = document.getElementById('monthlyChart')?.getContext('2d');
if (monthlyCtx) {
    new Chart(monthlyCtx, {
        type: 'line',
        data: {
            labels: [<?php 
                $months = array_reverse(array_column($monthly, 'month'));
                echo "'" . implode("','", array_map(function($m) {
                    return date('M Y', strtotime($m . '-01'));
                }, $months)) . "'";
            ?>],
            datasets: [{
                label: 'Revenue',
                data: [<?php 
                    $revenues = array_reverse(array_column($monthly, 'revenue'));
                    echo implode(',', array_map(function($v) { return $v ?? 0; }, $revenues));
                ?>],
                borderColor: 'rgb(255, 205, 86)',
                backgroundColor: 'rgba(255, 205, 86, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'My Earnings',
                data: [<?php 
                    $earnings = array_reverse(array_column($monthly, 'earnings'));
                    echo implode(',', array_map(function($v) { return $v ?? 0; }, $earnings));
                ?>],
                borderColor: 'rgb(40, 167, 69)',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.4,
                fill: true
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
}
<?php endif; ?>

<?php if (!empty($weekly)): ?>
// Weekly Chart
const weeklyCtx = document.getElementById('weeklyChart')?.getContext('2d');
if (weeklyCtx) {
    new Chart(weeklyCtx, {
        type: 'bar',
        data: {
            labels: [<?php 
                $weeks = array_reverse(array_column($weekly, 'week_num'));
                echo "'" . implode("','", array_map(function($w) {
                    return 'Week ' . $w;
                }, $weeks)) . "'";
            ?>],
            datasets: [{
                label: 'Weekly Earnings',
                data: [<?php 
                    $earnings = array_reverse(array_column($weekly, 'earnings'));
                    echo implode(',', array_map(function($v) { return $v ?? 0; }, $earnings));
                ?>],
                backgroundColor: 'rgba(40, 167, 69, 0.7)',
                borderColor: 'rgb(40, 167, 69)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
}
<?php endif; ?>
</script>

<style>
.card {
    border: none;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    margin-bottom: 20px;
}
.card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.table td {
    vertical-align: middle;
}
.badge {
    font-size: 11px;
    padding: 5px 8px;
}
@media print {
    .btn, .filter-section, .card-header, footer {
        display: none !important;
    }
}
</style>

<?php
// Get the content
$content = ob_get_clean();

// Include the owner layout
require_once '../layouts/owner_layout.php';
?>