<?php
// admin/advanced_reports.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('admin');

$page_title = 'Advanced Reports & Analytics';
ob_start();

// Get date range from request
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$report_type = $_GET['report_type'] ?? 'summary';
$compare_with = $_GET['compare_with'] ?? 'none'; // 'previous_period', 'last_year'

// Calculate comparison dates
$prev_start = date('Y-m-d', strtotime($start_date . ' - ' . (strtotime($end_date) - strtotime($start_date)) . ' seconds'));
$prev_end = date('Y-m-d', strtotime($start_date . ' -1 day'));
$last_year_start = date('Y-m-d', strtotime($start_date . ' -1 year'));
$last_year_end = date('Y-m-d', strtotime($end_date . ' -1 year'));

try {
    // 1. PASSENGER DEMOGRAPHIC REPORTS
    $passenger_demographics = $pdo->query("
        SELECT 
            HOUR(departed_at) as time_of_day,
            COUNT(*) as trip_count,
            SUM(passenger_count) as total_passengers,
            AVG(passenger_count) as avg_passengers,
            DAYNAME(departed_at) as day_of_week
        FROM trips
        WHERE DATE(departed_at) BETWEEN '$start_date' AND '$end_date'
        GROUP BY HOUR(departed_at), DAYNAME(departed_at)
        ORDER BY total_passengers DESC
        LIMIT 20
    ")->fetchAll();

    // 2. PEAK HOUR ANALYSIS
    $peak_hours = $pdo->query("
        SELECT 
            HOUR(departed_at) as hour,
            COUNT(*) as trips,
            SUM(passenger_count) as passengers,
            AVG(passenger_count) as avg_passengers,
            SUM(total_fare) as revenue
        FROM trips
        WHERE DATE(departed_at) BETWEEN '$start_date' AND '$end_date'
        GROUP BY HOUR(departed_at)
        ORDER BY trips DESC
    ")->fetchAll();

    // 3. REVENUE FORECASTING (based on last 30 days trend)
    $revenue_trend = $pdo->query("
        SELECT 
            DATE(departed_at) as date,
            SUM(total_fare) as daily_revenue,
            SUM(owner_payout) as daily_profit
        FROM trips
        WHERE departed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(departed_at)
        ORDER BY date
    ")->fetchAll();

    // Calculate forecast (simple linear regression)
    $total_days = count($revenue_trend);
    $avg_daily_revenue = array_sum(array_column($revenue_trend, 'daily_revenue')) / max($total_days, 1);
    $forecast_next_7_days = $avg_daily_revenue * 7;

    // 4. DRIVER PERFORMANCE RANKINGS
    $driver_rankings = $pdo->query("
        SELECT 
            d.id,
            u.full_name as driver_name,
            COUNT(t.id) as total_trips,
            SUM(t.passenger_count) as total_passengers,
            SUM(t.total_fare) as total_revenue,
            SUM(t.owner_payout) as total_owner_payout,
            AVG(t.passenger_count) as avg_passengers,
            COUNT(DISTINCT DATE(t.departed_at)) as days_worked,
            ROUND(COUNT(t.id) / COUNT(DISTINCT DATE(t.departed_at)), 1) as trips_per_day
        FROM drivers d
        JOIN users u ON d.user_id = u.id
        LEFT JOIN trips t ON d.id = t.driver_id AND DATE(t.departed_at) BETWEEN '$start_date' AND '$end_date'
        GROUP BY d.id, u.full_name
        HAVING total_trips > 0
        ORDER BY total_revenue DESC
    ")->fetchAll();

    // 5. TAXI UTILIZATION RATES
    $taxi_utilization = $pdo->query("
        SELECT 
            tx.id,
            tx.registration_number,
            COUNT(t.id) as total_trips,
            SUM(t.passenger_count) as total_passengers,
            SUM(t.total_fare) as total_revenue,
            COUNT(DISTINCT DATE(t.departed_at)) as days_used,
            DATEDIFF('$end_date', '$start_date') + 1 as total_days,
            ROUND((COUNT(DISTINCT DATE(t.departed_at)) / (DATEDIFF('$end_date', '$start_date') + 1)) * 100, 1) as utilization_rate,
            ROUND(AVG(t.passenger_count), 1) as avg_passengers
        FROM taxis tx
        LEFT JOIN trips t ON tx.id = t.taxi_id AND DATE(t.departed_at) BETWEEN '$start_date' AND '$end_date'
        GROUP BY tx.id, tx.registration_number
        ORDER BY utilization_rate DESC
    ")->fetchAll();

    // 6. ROUTE PROFITABILITY ANALYSIS
    $route_profitability = $pdo->query("
        SELECT 
            r.id,
            r.route_name,
            r.fare_amount,
            COUNT(t.id) as total_trips,
            SUM(t.passenger_count) as total_passengers,
            SUM(t.total_fare) as total_revenue,
            SUM(t.association_levy) as total_levy,
            SUM(t.owner_payout) as net_profit,
            AVG(t.passenger_count) as avg_passengers,
            (SUM(t.owner_payout) / NULLIF(COUNT(t.id), 0)) as profit_per_trip
        FROM routes r
        LEFT JOIN trips t ON r.id = t.route_id AND DATE(t.departed_at) BETWEEN '$start_date' AND '$end_date'
        GROUP BY r.id, r.route_name, r.fare_amount
        HAVING total_trips > 0
        ORDER BY net_profit DESC
    ")->fetchAll();

    // 7. COMPARATIVE REPORTS (this year vs last year)
    $current_year = $pdo->query("
        SELECT 
            MONTH(departed_at) as month,
            COUNT(*) as trips,
            SUM(passenger_count) as passengers,
            SUM(total_fare) as revenue,
            SUM(owner_payout) as profit
        FROM trips
        WHERE YEAR(departed_at) = YEAR(CURDATE())
        GROUP BY MONTH(departed_at)
        ORDER BY month
    ")->fetchAll();

    $last_year = $pdo->query("
        SELECT 
            MONTH(departed_at) as month,
            COUNT(*) as trips,
            SUM(passenger_count) as passengers,
            SUM(total_fare) as revenue,
            SUM(owner_payout) as profit
        FROM trips
        WHERE YEAR(departed_at) = YEAR(CURDATE()) - 1
        GROUP BY MONTH(departed_at)
        ORDER BY month
    ")->fetchAll();

    // 8. SUMMARY STATISTICS
    $summary = $pdo->query("
        SELECT 
            COUNT(DISTINCT t.id) as total_trips,
            COUNT(DISTINCT t.taxi_id) as active_taxis,
            COUNT(DISTINCT t.driver_id) as active_drivers,
            COUNT(DISTINCT r.id) as routes_used,
            SUM(t.passenger_count) as total_passengers,
            SUM(t.total_fare) as total_revenue,
            SUM(t.association_levy) as total_levy,
            SUM(t.owner_payout) as total_profit,
            AVG(t.passenger_count) as avg_passengers,
            AVG(t.total_fare) as avg_fare,
            SUM(t.total_fare) / NULLIF(COUNT(DISTINCT DATE(t.departed_at)), 0) as avg_daily_revenue
        FROM trips t
        LEFT JOIN routes r ON t.route_id = r.id
        WHERE DATE(t.departed_at) BETWEEN '$start_date' AND '$end_date'
    ")->fetch();

} catch (PDOException $e) {
    error_log("Advanced reports error: " . $e->getMessage());
    $passenger_demographics = [];
    $peak_hours = [];
    $revenue_trend = [];
    $driver_rankings = [];
    $taxi_utilization = [];
    $route_profitability = [];
    $current_year = [];
    $last_year = [];
    $summary = [];
}
?>

<style>
    .report-card {
        transition: transform 0.2s, box-shadow 0.2s;
        border: none;
        border-radius: 15px;
        overflow: hidden;
    }
    .report-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        line-height: 1.2;
    }
    .stat-label {
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #6c757d;
    }
    .ranking-badge {
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-weight: bold;
    }
    .ranking-1 { background: gold; color: #000; }
    .ranking-2 { background: silver; color: #000; }
    .ranking-3 { background: #cd7f32; color: #fff; }
    .table th {
        border-top: none;
        font-weight: 600;
        color: #495057;
    }
    .nav-tabs .nav-link {
        color: #495057;
        font-weight: 500;
    }
    .nav-tabs .nav-link.active {
        color: #007bff;
        font-weight: 600;
        border-bottom: 3px solid #007bff;
    }
    .export-btn-group {
        position: sticky;
        top: 10px;
        z-index: 100;
    }
</style>

<div class="container-fluid">
    <!-- Header with Export Options -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-bar-chart-steps"></i> Advanced Reports & Analytics</h2>
        <div class="export-btn-group">
            <div class="btn-group">
                <button class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Export
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#" onclick="exportReport('excel')">Excel (XLSX)</a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportReport('csv')">CSV</a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportReport('pdf')">PDF</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" onclick="scheduleReport()">Schedule Email Report</a></li>
                </ul>
            </div>
            <button class="btn btn-primary" onclick="window.print()">
                <i class="bi bi-printer"></i> Print
            </button>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="card mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="bi bi-calendar-range"></i> Report Period</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Report Type</label>
                    <select name="report_type" class="form-select">
                        <option value="summary" <?= $report_type == 'summary' ? 'selected' : '' ?>>Summary Dashboard</option>
                        <option value="drivers" <?= $report_type == 'drivers' ? 'selected' : '' ?>>Driver Performance</option>
                        <option value="taxis" <?= $report_type == 'taxis' ? 'selected' : '' ?>>Taxi Utilization</option>
                        <option value="routes" <?= $report_type == 'routes' ? 'selected' : '' ?>>Route Profitability</option>
                        <option value="comparative" <?= $report_type == 'comparative' ? 'selected' : '' ?>>Year Comparison</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Compare With</label>
                    <select name="compare_with" class="form-select">
                        <option value="none" <?= $compare_with == 'none' ? 'selected' : '' ?>>No Comparison</option>
                        <option value="previous" <?= $compare_with == 'previous' ? 'selected' : '' ?>>Previous Period</option>
                        <option value="last_year" <?= $compare_with == 'last_year' ? 'selected' : '' ?>>Last Year</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Generate Report
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="setQuickDates('today')">Today</button>
                    <button type="button" class="btn btn-secondary" onclick="setQuickDates('week')">This Week</button>
                    <button type="button" class="btn btn-secondary" onclick="setQuickDates('month')">This Month</button>
                    <button type="button" class="btn btn-secondary" onclick="setQuickDates('quarter')">This Quarter</button>
                    <button type="button" class="btn btn-secondary" onclick="setQuickDates('year')">This Year</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Statistics Cards -->
    <?php if ($report_type == 'summary' || $report_type == 'all'): ?>
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card report-card bg-primary text-white">
                <div class="card-body">
                    <div class="stat-label text-white-50">Total Trips</div>
                    <div class="stat-value"><?= number_format($summary['total_trips'] ?? 0) ?></div>
                    <small><?= date('d M', strtotime($start_date)) ?> - <?= date('d M', strtotime($end_date)) ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card report-card bg-success text-white">
                <div class="card-body">
                    <div class="stat-label text-white-50">Total Passengers</div>
                    <div class="stat-value"><?= number_format($summary['total_passengers'] ?? 0) ?></div>
                    <small>Avg: <?= number_format($summary['avg_passengers'] ?? 0, 1) ?> per trip</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card report-card bg-warning text-white">
                <div class="card-body">
                    <div class="stat-label text-white-50">Total Revenue</div>
                    <div class="stat-value">R <?= number_format($summary['total_revenue'] ?? 0, 0) ?></div>
                    <small>Avg: R <?= number_format($summary['avg_fare'] ?? 0, 2) ?> per trip</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card report-card bg-info text-white">
                <div class="card-body">
                    <div class="stat-label text-white-50">Net Profit</div>
                    <div class="stat-value">R <?= number_format($summary['total_profit'] ?? 0, 0) ?></div>
                    <small>After levies: R <?= number_format($summary['total_levy'] ?? 0, 0) ?></small>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Report Tabs -->
    <ul class="nav nav-tabs mb-4" id="reportTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $report_type == 'summary' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#demographics">
                <i class="bi bi-people"></i> Demographics
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#peak-hours">
                <i class="bi bi-clock"></i> Peak Hours
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#forecast">
                <i class="bi bi-graph-up"></i> Revenue Forecast
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#drivers">
                <i class="bi bi-trophy"></i> Driver Rankings
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#taxis">
                <i class="bi bi-truck"></i> Taxi Utilization
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#routes">
                <i class="bi bi-signpost"></i> Route Profitability
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#comparison">
                <i class="bi bi-calendar2-range"></i> Year Comparison
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- 1. Passenger Demographics Tab -->
        <div class="tab-pane fade <?= $report_type == 'summary' ? 'show active' : '' ?>" id="demographics">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-people"></i> Passenger Demographics</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <canvas id="demographicsChart" height="300"></canvas>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Time of Day</th>
                                        <th>Day</th>
                                        <th>Trips</th>
                                        <th>Passengers</th>
                                        <th>Avg</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($passenger_demographics, 0, 10) as $demo): ?>
                                    <tr>
                                        <td><?= $demo['time_of_day'] ?>:00</td>
                                        <td><?= substr($demo['day_of_week'], 0, 3) ?></td>
                                        <td><?= $demo['trip_count'] ?></td>
                                        <td><?= $demo['total_passengers'] ?></td>
                                        <td><?= number_format($demo['avg_passengers'], 1) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Peak Hours Tab -->
        <div class="tab-pane fade" id="peak-hours">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-clock"></i> Peak Hour Analysis</h5>
                </div>
                <div class="card-body">
                    <canvas id="peakHoursChart" height="100"></canvas>
                    <div class="row mt-4">
                        <?php foreach ($peak_hours as $peak): ?>
                        <div class="col-md-2 mb-3">
                            <div class="card bg-<?= $peak['trips'] > 50 ? 'danger' : ($peak['trips'] > 30 ? 'warning' : 'info') ?> text-white">
                                <div class="card-body text-center">
                                    <h3><?= $peak['hour'] ?>:00</h3>
                                    <h5><?= $peak['trips'] ?> trips</h5>
                                    <small><?= $peak['passengers'] ?> passengers</small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Revenue Forecast Tab -->
        <div class="tab-pane fade" id="forecast">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> Revenue Forecasting</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <canvas id="forecastChart" height="100"></canvas>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h6 class="text-white-50">Forecast Next 7 Days</h6>
                                    <h2 class="mb-0">R <?= number_format($forecast_next_7_days, 0) ?></h2>
                                    <small>Based on last 30 days average</small>
                                </div>
                            </div>
                            <div class="card bg-info text-white mt-3">
                                <div class="card-body">
                                    <h6 class="text-white-50">Daily Average</h6>
                                    <h3 class="mb-0">R <?= number_format($avg_daily_revenue, 0) ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Driver Rankings Tab -->
        <div class="tab-pane fade" id="drivers">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-trophy"></i> Driver Performance Rankings</h5>
                </div>
                <div class="card-body">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Driver</th>
                                <th>Trips</th>
                                <th>Passengers</th>
                                <th>Revenue</th>
                                <th>Owner Payout</th>
                                <th>Days Worked</th>
                                <th>Trips/Day</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($driver_rankings as $index => $driver): ?>
                            <tr>
                                <td>
                                    <div class="ranking-badge ranking-<?= $index + 1 ?>">
                                        <?= $index + 1 ?>
                                    </div>
                                </td>
                                <td><strong><?= htmlspecialchars($driver['driver_name']) ?></strong></td>
                                <td><?= $driver['total_trips'] ?></td>
                                <td><?= $driver['total_passengers'] ?></td>
                                <td>R <?= number_format($driver['total_revenue'], 0) ?></td>
                                <td class="text-success">R <?= number_format($driver['total_owner_payout'], 0) ?></td>
                                <td><?= $driver['days_worked'] ?></td>
                                <td><?= $driver['trips_per_day'] ?></td>
                                <td>
                                    <?php
                                    $performance = ($driver['total_trips'] / max($driver_rankings[0]['total_trips'], 1)) * 100;
                                    ?>
                                    <div class="progress">
                                        <div class="progress-bar bg-success" style="width: <?= $performance ?>%">
                                            <?= round($performance) ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 5. Taxi Utilization Tab -->
        <div class="tab-pane fade" id="taxis">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-truck"></i> Taxi Utilization Rates</h5>
                </div>
                <div class="card-body">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Registration</th>
                                <th>Trips</th>
                                <th>Passengers</th>
                                <th>Revenue</th>
                                <th>Days Used</th>
                                <th>Utilization</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($taxi_utilization as $index => $taxi): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><strong><?= htmlspecialchars($taxi['registration_number']) ?></strong></td>
                                <td><?= $taxi['total_trips'] ?></td>
                                <td><?= $taxi['total_passengers'] ?></td>
                                <td>R <?= number_format($taxi['total_revenue'], 0) ?></td>
                                <td><?= $taxi['days_used'] ?>/<?= $taxi['total_days'] ?></td>
                                <td>
                                    <div class="progress">
                                        <div class="progress-bar bg-<?= $taxi['utilization_rate'] > 70 ? 'success' : ($taxi['utilization_rate'] > 40 ? 'warning' : 'danger') ?>" 
                                             style="width: <?= $taxi['utilization_rate'] ?>%">
                                            <?= $taxi['utilization_rate'] ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($taxi['utilization_rate'] > 70): ?>
                                        <span class="badge bg-success">High Usage</span>
                                    <?php elseif ($taxi['utilization_rate'] > 40): ?>
                                        <span class="badge bg-warning">Medium Usage</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Low Usage</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 6. Route Profitability Tab -->
        <div class="tab-pane fade" id="routes">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-signpost"></i> Route Profitability Analysis</h5>
                </div>
                <div class="card-body">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Route</th>
                                <th>Fare</th>
                                <th>Trips</th>
                                <th>Passengers</th>
                                <th>Revenue</th>
                                <th>Levy</th>
                                <th>Net Profit</th>
                                <th>Profit/Trip</th>
                                <th>ROI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_profit = array_sum(array_column($route_profitability, 'net_profit'));
                            foreach ($route_profitability as $index => $route): 
                            $roi = ($route['net_profit'] / max($total_profit, 1)) * 100;
                            ?>
                            <tr>
                                <td><span class="badge bg-<?= $index == 0 ? 'warning' : ($index == 1 ? 'secondary' : 'primary') ?>">#<?= $index + 1 ?></span></td>
                                <td><strong><?= htmlspecialchars($route['route_name']) ?></strong></td>
                                <td>R <?= number_format($route['fare_amount'], 2) ?></td>
                                <td><?= $route['total_trips'] ?></td>
                                <td><?= $route['total_passengers'] ?></td>
                                <td>R <?= number_format($route['total_revenue'], 0) ?></td>
                                <td>R <?= number_format($route['total_levy'], 0) ?></td>
                                <td class="text-success">R <?= number_format($route['net_profit'], 0) ?></td>
                                <td>R <?= number_format($route['profit_per_trip'], 2) ?></td>
                                <td>
                                    <div class="progress">
                                        <div class="progress-bar bg-success" style="width: <?= $roi ?>%">
                                            <?= number_format($roi, 1) ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 7. Year Comparison Tab -->
        <div class="tab-pane fade" id="comparison">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-calendar2-range"></i> Year Comparison (<?= date('Y') ?> vs <?= date('Y')-1 ?>)</h5>
                </div>
                <div class="card-body">
                    <canvas id="comparisonChart" height="100"></canvas>
                    
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <h6><?= date('Y') ?> Summary</h6>
                            <table class="table">
                                <?php 
                                $current_total = array_sum(array_column($current_year, 'revenue'));
                                $last_total = array_sum(array_column($last_year, 'revenue'));
                                $growth = $last_total > 0 ? (($current_total - $last_total) / $last_total) * 100 : 0;
                                ?>
                                <tr>
                                    <th>Total Revenue:</th>
                                    <td>R <?= number_format($current_total, 0) ?></td>
                                    <td class="<?= $growth > 0 ? 'text-success' : 'text-danger' ?>">
                                        <?= $growth > 0 ? '+' : '' ?><?= number_format($growth, 1) ?>%
                                    </td>
                                </tr>
                                <tr>
                                    <th>Total Trips:</th>
                                    <td><?= number_format(array_sum(array_column($current_year, 'trips')), 0) ?></td>
                                </tr>
                                <tr>
                                    <th>Total Passengers:</th>
                                    <td><?= number_format(array_sum(array_column($current_year, 'passengers')), 0) ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6><?= date('Y')-1 ?> Summary</h6>
                            <table class="table">
                                <tr>
                                    <th>Total Revenue:</th>
                                    <td>R <?= number_format($last_total, 0) ?></td>
                                </tr>
                                <tr>
                                    <th>Total Trips:</th>
                                    <td><?= number_format(array_sum(array_column($last_year, 'trips')), 0) ?></td>
                                </tr>
                                <tr>
                                    <th>Total Passengers:</th>
                                    <td><?= number_format(array_sum(array_column($last_year, 'passengers')), 0) ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Quick date functions
function setQuickDates(period) {
    const today = new Date();
    let startDate, endDate;
    
    switch(period) {
        case 'today':
            startDate = endDate = today.toISOString().split('T')[0];
            break;
        case 'week':
            const firstDay = new Date(today.setDate(today.getDate() - today.getDay() + 1));
            const lastDay = new Date(today.setDate(today.getDate() - today.getDay() + 7));
            startDate = firstDay.toISOString().split('T')[0];
            endDate = lastDay.toISOString().split('T')[0];
            break;
        case 'month':
            startDate = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
            endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().split('T')[0];
            break;
        case 'quarter':
            const quarter = Math.floor(today.getMonth() / 3);
            startDate = new Date(today.getFullYear(), quarter * 3, 1).toISOString().split('T')[0];
            endDate = new Date(today.getFullYear(), quarter * 3 + 3, 0).toISOString().split('T')[0];
            break;
        case 'year':
            startDate = new Date(today.getFullYear(), 0, 1).toISOString().split('T')[0];
            endDate = new Date(today.getFullYear(), 11, 31).toISOString().split('T')[0];
            break;
    }
    
    document.querySelector('[name="start_date"]').value = startDate;
    document.querySelector('[name="end_date"]').value = endDate;
    document.querySelector('form').submit();
}

// Export functions
function exportReport(format) {
    const startDate = document.querySelector('[name="start_date"]').value;
    const endDate = document.querySelector('[name="end_date"]').value;
    const reportType = document.querySelector('[name="report_type"]').value;
    
    window.location.href = `export/export_report.php?format=${format}&start=${startDate}&end=${endDate}&type=${reportType}`;
}

function scheduleReport() {
    // Show scheduling modal
    alert('Email scheduling feature coming soon!');
}

// Charts
<?php if (!empty($passenger_demographics)): ?>
// Demographics Chart
new Chart(document.getElementById('demographicsChart'), {
    type: 'bar',
    data: {
        labels: [<?php foreach(array_slice($passenger_demographics,0,7) as $d) echo "'" . $d['time_of_day'] . ":00',"; ?>],
        datasets: [{
            label: 'Passengers',
            data: [<?php foreach(array_slice($passenger_demographics,0,7) as $d) echo $d['total_passengers'] . ","; ?>],
            backgroundColor: 'rgba(54, 162, 235, 0.7)'
        }]
    }
});
<?php endif; ?>

<?php if (!empty($peak_hours)): ?>
// Peak Hours Chart
new Chart(document.getElementById('peakHoursChart'), {
    type: 'line',
    data: {
        labels: [<?php foreach($peak_hours as $p) echo "'" . $p['hour'] . ":00',"; ?>],
        datasets: [{
            label: 'Trips',
            data: [<?php foreach($peak_hours as $p) echo $p['trips'] . ","; ?>],
            borderColor: 'rgb(255, 99, 132)',
            tension: 0.4
        }]
    }
});
<?php endif; ?>

<?php if (!empty($revenue_trend)): ?>
// Forecast Chart
new Chart(document.getElementById('forecastChart'), {
    type: 'line',
    data: {
        labels: [<?php foreach($revenue_trend as $r) echo "'" . date('d M', strtotime($r['date'])) . "',"; ?>],
        datasets: [{
            label: 'Daily Revenue',
            data: [<?php foreach($revenue_trend as $r) echo $r['daily_revenue'] . ","; ?>],
            borderColor: 'rgb(75, 192, 192)',
            fill: true
        }]
    }
});
<?php endif; ?>

<?php if (!empty($current_year) && !empty($last_year)): ?>
// Comparison Chart
new Chart(document.getElementById('comparisonChart'), {
    type: 'bar',
    data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        datasets: [{
            label: '<?= date('Y') ?>',
            data: [<?php 
                $curr = array_fill(0,12,0);
                foreach($current_year as $c) $curr[$c['month']-1] = $c['revenue'];
                echo implode(',', $curr);
            ?>],
            backgroundColor: 'rgba(54, 162, 235, 0.7)'
        }, {
            label: '<?= date('Y')-1 ?>',
            data: [<?php 
                $last = array_fill(0,12,0);
                foreach($last_year as $l) $last[$l['month']-1] = $l['revenue'];
                echo implode(',', $last);
            ?>],
            backgroundColor: 'rgba(255, 99, 132, 0.7)'
        }]
    }
});
<?php endif; ?>
</script>

<?php
$content = ob_get_clean();
require_once '../layouts/admin_layout.php';
?>