<?php
// admin/reports.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('admin');

// Set page title
$page_title = 'Reports & Analytics';

// Start output buffering
ob_start();

// Get date range from request or default to current month
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$report_type = $_GET['report_type'] ?? 'summary';

try {
    // Summary Statistics
    $summary_sql = "SELECT 
                        COUNT(DISTINCT t.id) as total_trips,
                        COALESCE(SUM(t.passenger_count), 0) as total_passengers,
                        COALESCE(SUM(t.total_fare), 0) as total_revenue,
                        COALESCE(SUM(t.association_levy), 0) as total_levy,
                        COALESCE(SUM(t.owner_payout), 0) as total_owner_payout,
                        COUNT(DISTINCT t.taxi_id) as active_taxis,
                        COUNT(DISTINCT t.driver_id) as active_drivers
                    FROM trips t
                    WHERE DATE(t.departed_at) BETWEEN :start_date AND :end_date";
    
    $summary_stmt = $pdo->prepare($summary_sql);
    $summary_stmt->execute([
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    $summary = $summary_stmt->fetch();
    
    // Daily breakdown
    $daily_sql = "SELECT 
                    DATE(departed_at) as date,
                    COUNT(*) as trips,
                    SUM(passenger_count) as passengers,
                    SUM(total_fare) as revenue,
                    SUM(association_levy) as levy,
                    SUM(owner_payout) as owner_payout
                  FROM trips
                  WHERE DATE(departed_at) BETWEEN :start_date AND :end_date
                  GROUP BY DATE(departed_at)
                  ORDER BY date DESC";
    
    $daily_stmt = $pdo->prepare($daily_sql);
    $daily_stmt->execute([
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    $daily = $daily_stmt->fetchAll();
    
    // Top routes
    $routes_sql = "SELECT 
                    r.route_name,
                    COUNT(t.id) as trips,
                    SUM(t.passenger_count) as passengers,
                    SUM(t.total_fare) as revenue,
                    SUM(t.association_levy) as levy,
                    AVG(t.passenger_count) as avg_passengers
                  FROM trips t
                  JOIN routes r ON t.route_id = r.id
                  WHERE DATE(t.departed_at) BETWEEN :start_date AND :end_date
                  GROUP BY r.id, r.route_name
                  ORDER BY revenue DESC
                  LIMIT 10";
    
    $routes_stmt = $pdo->prepare($routes_sql);
    $routes_stmt->execute([
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    $top_routes = $routes_stmt->fetchAll();
    
    // Top drivers
    $drivers_sql = "SELECT 
                    u.full_name as driver_name,
                    COUNT(t.id) as trips,
                    SUM(t.passenger_count) as passengers,
                    SUM(t.total_fare) as revenue,
                    SUM(t.association_levy) as levy,
                    AVG(t.passenger_count) as avg_passengers
                  FROM trips t
                  JOIN drivers d ON t.driver_id = d.id
                  JOIN users u ON d.user_id = u.id
                  WHERE DATE(t.departed_at) BETWEEN :start_date AND :end_date
                  GROUP BY d.id, u.full_name
                  ORDER BY revenue DESC
                  LIMIT 10";
    
    $drivers_stmt = $pdo->prepare($drivers_sql);
    $drivers_stmt->execute([
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    $top_drivers = $drivers_stmt->fetchAll();
    
    // Top taxis
    $taxis_sql = "SELECT 
                    tx.registration_number,
                    u.full_name as owner_name,
                    COUNT(t.id) as trips,
                    SUM(t.passenger_count) as passengers,
                    SUM(t.total_fare) as revenue,
                    SUM(t.association_levy) as levy,
                    AVG(t.passenger_count) as avg_passengers
                  FROM trips t
                  JOIN taxis tx ON t.taxi_id = tx.id
                  JOIN owners o ON tx.owner_id = o.id
                  JOIN users u ON o.user_id = u.id
                  WHERE DATE(t.departed_at) BETWEEN :start_date AND :end_date
                  GROUP BY tx.id, tx.registration_number, u.full_name
                  ORDER BY revenue DESC
                  LIMIT 10";
    
    $taxis_stmt = $pdo->prepare($taxis_sql);
    $taxis_stmt->execute([
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    $top_taxis = $taxis_stmt->fetchAll();
    
    // Top owners
    $owners_sql = "SELECT 
                    u.full_name as owner_name,
                    COUNT(DISTINCT tx.id) as taxis,
                    COUNT(t.id) as trips,
                    SUM(t.passenger_count) as passengers,
                    SUM(t.total_fare) as revenue,
                    SUM(t.association_levy) as levy,
                    SUM(t.owner_payout) as payout
                  FROM trips t
                  JOIN taxis tx ON t.taxi_id = tx.id
                  JOIN owners o ON tx.owner_id = o.id
                  JOIN users u ON o.user_id = u.id
                  WHERE DATE(t.departed_at) BETWEEN :start_date AND :end_date
                  GROUP BY o.id, u.full_name
                  ORDER BY payout DESC
                  LIMIT 10";
    
    $owners_stmt = $pdo->prepare($owners_sql);
    $owners_stmt->execute([
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    $top_owners = $owners_stmt->fetchAll();
    
    // Hourly distribution
    $hourly_sql = "SELECT 
                    HOUR(departed_at) as hour,
                    COUNT(*) as trips,
                    SUM(passenger_count) as passengers
                  FROM trips
                  WHERE DATE(departed_at) BETWEEN :start_date AND :end_date
                  GROUP BY HOUR(departed_at)
                  ORDER BY hour";
    
    $hourly_stmt = $pdo->prepare($hourly_sql);
    $hourly_stmt->execute([
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    $hourly = $hourly_stmt->fetchAll();
    
    // Payment summary by employment type
    $payment_sql = "SELECT 
                    d.employment_type,
                    COUNT(DISTINCT d.id) as drivers,
                    COUNT(t.id) as trips,
                    SUM(t.total_fare) as revenue,
                    AVG(t.passenger_count) as avg_passengers
                  FROM trips t
                  JOIN drivers d ON t.driver_id = d.id
                  WHERE DATE(t.departed_at) BETWEEN :start_date AND :end_date
                  GROUP BY d.employment_type";
    
    $payment_stmt = $pdo->prepare($payment_sql);
    $payment_stmt->execute([
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    $payment_stats = $payment_stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Reports error: " . $e->getMessage());
    $summary = [];
    $daily = [];
    $top_routes = [];
    $top_drivers = [];
    $top_taxis = [];
    $top_owners = [];
    $hourly = [];
    $payment_stats = [];
}

// Safe division function
function safeDivide($numerator, $denominator, $decimals = 2) {
    if ($denominator == 0 || $denominator == null) {
        return 0;
    }
    return round($numerator / $denominator, $decimals);
}
?>

<!-- Add Bootstrap and Custom CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<style>
    body {
        background: #f8f9fa;
    }
    .card {
        border: none;
        box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        transition: transform 0.2s;
        margin-bottom: 20px;
    }
    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .card-header {
        font-weight: 600;
    }
    .table th {
        border-top: none;
        background-color: #f8f9fa;
        font-weight: 600;
    }
    .badge {
        padding: 5px 10px;
    }
    .summary-card {
        border-radius: 10px;
        overflow: hidden;
    }
    .summary-card .card-body {
        padding: 1.5rem;
    }
    .summary-card h3 {
        font-size: 2rem;
        font-weight: 700;
        margin: 10px 0;
    }
    .summary-card small {
        font-size: 0.8rem;
        opacity: 0.9;
    }
    .filter-section {
        background: white;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 30px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .btn-export {
        margin-right: 10px;
    }
    .chart-container {
        position: relative;
        height: 300px;
        width: 100%;
    }
</style>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-graph-up"></i> Reports & Analytics</h2>
        <div>
            <span class="badge bg-info"><?= date('d M Y', strtotime($start_date)) ?> - <?= date('d M Y', strtotime($end_date)) ?></span>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="filter-section">
        <h5 class="mb-3"><i class="bi bi-funnel"></i> Filter Reports</h5>
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?= $start_date ?>">
            </div>
            <div class="col-md-3">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?= $end_date ?>">
            </div>
            <div class="col-md-3">
                <label for="report_type" class="form-label">Report Type</label>
                <select class="form-select" id="report_type" name="report_type">
                    <option value="summary" <?= $report_type == 'summary' ? 'selected' : '' ?>>Summary</option>
                    <option value="routes" <?= $report_type == 'routes' ? 'selected' : '' ?>>Routes Analysis</option>
                    <option value="drivers" <?= $report_type == 'drivers' ? 'selected' : '' ?>>Drivers Performance</option>
                    <option value="taxis" <?= $report_type == 'taxis' ? 'selected' : '' ?>>Taxis Performance</option>
                    <option value="owners" <?= $report_type == 'owners' ? 'selected' : '' ?>>Owners Earnings</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Generate Report
                </button>
            </div>
            <div class="col-12 mt-3">
                <button type="button" class="btn btn-success btn-export" onclick="exportToExcel()">
                    <i class="bi bi-file-excel"></i> Export to Excel
                </button>
                <button type="button" class="btn btn-danger btn-export" onclick="exportToPDF()">
                    <i class="bi bi-file-pdf"></i> Export to PDF
                </button>
            </div>
        </form>
    </div>

    <!-- No Data Warning -->
    <?php if (($summary['total_trips'] ?? 0) == 0): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i> No data found for the selected period. 
            Try selecting a different date range or add some trips first.
        </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-xl-2 col-md-4">
            <div class="card summary-card bg-primary text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Total Trips</h6>
                    <h3><?= number_format($summary['total_trips'] ?? 0) ?></h3>
                    <small><?= date('d M Y', strtotime($start_date)) ?> - <?= date('d M Y', strtotime($end_date)) ?></small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4">
            <div class="card summary-card bg-success text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Passengers</h6>
                    <h3><?= number_format($summary['total_passengers'] ?? 0) ?></h3>
                    <small>Avg: <?= safeDivide($summary['total_passengers'] ?? 0, $summary['total_trips'] ?? 0, 1) ?> per trip</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4">
            <div class="card summary-card bg-warning text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Total Revenue</h6>
                    <h3>R <?= number_format($summary['total_revenue'] ?? 0, 2) ?></h3>
                    <small>R <?= number_format(safeDivide($summary['total_revenue'] ?? 0, $summary['total_trips'] ?? 0, 2), 2) ?> per trip</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4">
            <div class="card summary-card bg-info text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Association Levy</h6>
                    <h3>R <?= number_format($summary['total_levy'] ?? 0, 2) ?></h3>
                    <small><?= safeDivide(($summary['total_levy'] ?? 0) * 100, $summary['total_revenue'] ?? 1, 1) ?>% of revenue</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4">
            <div class="card summary-card bg-secondary text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Owner Payout</h6>
                    <h3>R <?= number_format($summary['total_owner_payout'] ?? 0, 2) ?></h3>
                    <small>Net after levy</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4">
            <div class="card summary-card bg-dark text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Active</h6>
                    <h3><?= $summary['active_taxis'] ?? 0 ?> Taxis</h3>
                    <h3><?= $summary['active_drivers'] ?? 0 ?> Drivers</h3>
                </div>
            </div>
        </div>
    </div>

    <?php if ($report_type == 'summary'): ?>
        <!-- Daily Breakdown -->
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-calendar-day"></i> Daily Breakdown</h5>
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
                                <th>Owner Payout</th>
                                <th>Avg Passengers</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($daily)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                                        No data for selected period
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $total_revenue = 0;
                                $total_levy = 0;
                                $total_payout = 0;
                                foreach ($daily as $day): 
                                    $total_revenue += $day['revenue'];
                                    $total_levy += $day['levy'];
                                    $total_payout += $day['owner_payout'];
                                ?>
                                <tr>
                                    <td><strong><?= date('d M Y', strtotime($day['date'])) ?></strong></td>
                                    <td><?= $day['trips'] ?></td>
                                    <td><?= $day['passengers'] ?></td>
                                    <td>R <?= number_format($day['revenue'], 2) ?></td>
                                    <td>R <?= number_format($day['levy'], 2) ?></td>
                                    <td>R <?= number_format($day['owner_payout'], 2) ?></td>
                                    <td><?= safeDivide($day['passengers'], $day['trips'], 1) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="table-primary fw-bold">
                                    <td>TOTAL</td>
                                    <td><?= array_sum(array_column($daily, 'trips')) ?></td>
                                    <td><?= array_sum(array_column($daily, 'passengers')) ?></td>
                                    <td>R <?= number_format($total_revenue, 2) ?></td>
                                    <td>R <?= number_format($total_levy, 2) ?></td>
                                    <td>R <?= number_format($total_payout, 2) ?></td>
                                    <td><?= safeDivide(array_sum(array_column($daily, 'passengers')), array_sum(array_column($daily, 'trips')), 1) ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="bi bi-clock"></i> Hourly Trip Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="hourlyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Payment Type Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="paymentChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($report_type == 'routes'): ?>
        <!-- Top Routes -->
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-signpost"></i> Route Performance</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Route Name</th>
                                <th>Trips</th>
                                <th>Passengers</th>
                                <th>Revenue</th>
                                <th>Levy</th>
                                <th>Avg Passengers</th>
                                <th>% of Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_routes)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                                        No data for selected period
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $total_route_revenue = array_sum(array_column($top_routes, 'revenue'));
                                foreach ($top_routes as $index => $route): 
                                ?>
                                <tr>
                                    <td><span class="badge bg-<?= $index == 0 ? 'warning' : ($index == 1 ? 'secondary' : 'primary') ?>">#<?= $index + 1 ?></span></td>
                                    <td><strong><?= htmlspecialchars($route['route_name']) ?></strong></td>
                                    <td><?= $route['trips'] ?></td>
                                    <td><?= $route['passengers'] ?></td>
                                    <td>R <?= number_format($route['revenue'], 2) ?></td>
                                    <td>R <?= number_format($route['levy'], 2) ?></td>
                                    <td><?= round($route['avg_passengers'], 1) ?></td>
                                    <td><?= safeDivide($route['revenue'] * 100, $total_route_revenue, 1) ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif ($report_type == 'drivers'): ?>
        <!-- Top Drivers -->
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-person-badge"></i> Driver Performance</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Driver Name</th>
                                <th>Trips</th>
                                <th>Passengers</th>
                                <th>Revenue</th>
                                <th>Levy</th>
                                <th>Avg Passengers</th>
                                <th>Revenue/Trip</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_drivers)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                                        No data for selected period
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($top_drivers as $index => $driver): ?>
                                <tr>
                                    <td><span class="badge bg-<?= $index == 0 ? 'warning' : ($index == 1 ? 'secondary' : 'primary') ?>">#<?= $index + 1 ?></span></td>
                                    <td><strong><?= htmlspecialchars($driver['driver_name']) ?></strong></td>
                                    <td><?= $driver['trips'] ?></td>
                                    <td><?= $driver['passengers'] ?></td>
                                    <td>R <?= number_format($driver['revenue'], 2) ?></td>
                                    <td>R <?= number_format($driver['levy'], 2) ?></td>
                                    <td><?= round($driver['avg_passengers'], 1) ?></td>
                                    <td>R <?= number_format(safeDivide($driver['revenue'], $driver['trips'], 2), 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif ($report_type == 'taxis'): ?>
        <!-- Top Taxis -->
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-truck"></i> Taxi Performance</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Registration</th>
                                <th>Owner</th>
                                <th>Trips</th>
                                <th>Passengers</th>
                                <th>Revenue</th>
                                <th>Levy</th>
                                <th>Avg Passengers</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_taxis)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                                        No data for selected period
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($top_taxis as $index => $taxi): ?>
                                <tr>
                                    <td><span class="badge bg-<?= $index == 0 ? 'warning' : ($index == 1 ? 'secondary' : 'primary') ?>">#<?= $index + 1 ?></span></td>
                                    <td><strong><?= htmlspecialchars($taxi['registration_number']) ?></strong></td>
                                    <td><?= htmlspecialchars($taxi['owner_name']) ?></td>
                                    <td><?= $taxi['trips'] ?></td>
                                    <td><?= $taxi['passengers'] ?></td>
                                    <td>R <?= number_format($taxi['revenue'], 2) ?></td>
                                    <td>R <?= number_format($taxi['levy'], 2) ?></td>
                                    <td><?= round($taxi['avg_passengers'], 1) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif ($report_type == 'owners'): ?>
        <!-- Top Owners -->
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-briefcase"></i> Owner Earnings</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Owner Name</th>
                                <th>Taxis</th>
                                <th>Trips</th>
                                <th>Passengers</th>
                                <th>Revenue</th>
                                <th>Levy</th>
                                <th>Payout</th>
                                <th>Per Taxi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_owners)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                                        No data for selected period
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($top_owners as $index => $owner): ?>
                                <tr>
                                    <td><span class="badge bg-<?= $index == 0 ? 'warning' : ($index == 1 ? 'secondary' : 'primary') ?>">#<?= $index + 1 ?></span></td>
                                    <td><strong><?= htmlspecialchars($owner['owner_name']) ?></strong></td>
                                    <td><?= $owner['taxis'] ?></td>
                                    <td><?= $owner['trips'] ?></td>
                                    <td><?= $owner['passengers'] ?></td>
                                    <td>R <?= number_format($owner['revenue'], 2) ?></td>
                                    <td>R <?= number_format($owner['levy'], 2) ?></td>
                                    <td class="text-success fw-bold">R <?= number_format($owner['payout'], 2) ?></td>
                                    <td>R <?= number_format(safeDivide($owner['payout'], $owner['taxis'], 2), 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Chart Scripts -->
<script>
<?php if ($report_type == 'summary' && !empty($hourly)): ?>
// Hourly Chart
const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
new Chart(hourlyCtx, {
    type: 'bar',
    data: {
        labels: [<?php 
            $hours = [];
            for($i = 0; $i < 24; $i++) {
                $hours[] = "'" . sprintf("%02d:00", $i) . "'";
            }
            echo implode(',', $hours);
        ?>],
        datasets: [{
            label: 'Trips',
            data: [<?php 
                $hourly_data = array_fill(0, 24, 0);
                foreach($hourly as $h) {
                    $hourly_data[$h['hour']] = $h['trips'];
                }
                echo implode(',', $hourly_data);
            ?>],
            backgroundColor: 'rgba(54, 162, 235, 0.5)',
            borderColor: 'rgb(54, 162, 235)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Number of Trips'
                }
            }
        }
    }
});
<?php endif; ?>

<?php if ($report_type == 'summary' && !empty($payment_stats)): ?>
// Payment Chart
const paymentCtx = document.getElementById('paymentChart').getContext('2d');
new Chart(paymentCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php 
            $labels = [];
            foreach($payment_stats as $stat) {
                $labels[] = "'" . ucfirst($stat['employment_type']) . "'";
            }
            echo implode(',', $labels);
        ?>],
        datasets: [{
            data: [<?php 
                $values = [];
                foreach($payment_stats as $stat) {
                    $values[] = $stat['trips'];
                }
                echo implode(',', $values);
            ?>],
            backgroundColor: [
                'rgb(255, 99, 132)',
                'rgb(54, 162, 235)',
                'rgb(255, 205, 86)'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
<?php endif; ?>

// Export functions
function exportToExcel() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const reportType = document.getElementById('report_type').value;
    
    // Show message that export is coming soon
    alert('Excel export feature will be available in the next update!');
    // window.location.href = `export/export_excel.php?start_date=${startDate}&end_date=${endDate}&report_type=${reportType}`;
}

function exportToPDF() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const reportType = document.getElementById('report_type').value;
    
    // Show message that export is coming soon
    alert('PDF export feature will be available in the next update!');
    // window.location.href = `export/export_pdf.php?start_date=${startDate}&end_date=${endDate}&report_type=${reportType}`;
}

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.transition = 'opacity 1s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 1000);
    });
}, 5000);
</script>

<?php
// Get the content
$content = ob_get_clean();

// Include the admin layout
require_once '../layouts/admin_layout.php';
?>