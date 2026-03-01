<?php
// driver/my_earnings.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('driver');

// Set page title
$page_title = 'My Earnings';

// Start output buffering
ob_start();

$driver_id = $_SESSION['driver_id'];

// Get date range from request or default to current month
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$period = $_GET['period'] ?? 'custom';

try {
    // Get driver's payment info
    $driver_sql = "SELECT d.*, u.full_name,
                          ou.full_name as owner_name,
                          d.employment_type, d.payment_rate
                   FROM drivers d
                   JOIN users u ON d.user_id = u.id
                   JOIN owners o ON d.owner_id = o.id
                   JOIN users ou ON o.user_id = ou.id
                   WHERE d.id = ?";
    $driver_stmt = $pdo->prepare($driver_sql);
    $driver_stmt->execute([$driver_id]);
    $driver_info = $driver_stmt->fetch();
    
    // Summary statistics
    $summary_sql = "SELECT 
                        COUNT(*) as total_trips,
                        SUM(passenger_count) as total_passengers,
                        SUM(total_fare) as total_revenue,
                        SUM(association_levy) as total_levy,
                        SUM(owner_payout) as total_owner_payout,
                        AVG(passenger_count) as avg_passengers,
                        AVG(total_fare) as avg_fare,
                        MAX(passenger_count) as max_passengers,
                        MIN(passenger_count) as min_passengers,
                        COUNT(DISTINCT DATE(departed_at)) as days_worked
                    FROM trips 
                    WHERE driver_id = :driver_id
                    AND DATE(departed_at) BETWEEN :start_date AND :end_date";
    
    $summary_stmt = $pdo->prepare($summary_sql);
    $summary_stmt->execute([
        ':driver_id' => $driver_id,
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    $summary = $summary_stmt->fetch();
    
    // Calculate driver's actual earnings based on employment type
    $driver_earnings = 0;
    $earnings_breakdown = [];
    
    if ($driver_info['employment_type'] == 'commission') {
        // Commission based on owner payout
        $driver_earnings = ($summary['total_owner_payout'] ?? 0) * ($driver_info['payment_rate'] / 100);
        
        // Daily breakdown for commission
        $daily_sql = "SELECT 
                            DATE(departed_at) as date,
                            SUM(owner_payout) as daily_owner_payout,
                            SUM(owner_payout) * :commission_rate as driver_earnings,
                            COUNT(*) as trips
                      FROM trips 
                      WHERE driver_id = :driver_id
                      AND DATE(departed_at) BETWEEN :start_date AND :end_date
                      GROUP BY DATE(departed_at)
                      ORDER BY date DESC";
        $daily_stmt = $pdo->prepare($daily_sql);
        $daily_stmt->execute([
            ':driver_id' => $driver_id,
            ':start_date' => $start_date,
            ':end_date' => $end_date,
            ':commission_rate' => ($driver_info['payment_rate'] / 100)
        ]);
        $earnings_breakdown = $daily_stmt->fetchAll();
        
    } elseif ($driver_info['employment_type'] == 'wage' || $driver_info['employment_type'] == 'rental') {
        // Daily wage or rental
        $days_worked = $summary['days_worked'] ?? 0;
        $driver_earnings = $days_worked * ($driver_info['payment_rate'] ?? 0);
        
        // Daily breakdown for wage
        $daily_sql = "SELECT 
                            DATE(departed_at) as date,
                            :daily_rate as driver_earnings,
                            COUNT(*) as trips
                      FROM trips 
                      WHERE driver_id = :driver_id
                      AND DATE(departed_at) BETWEEN :start_date AND :end_date
                      GROUP BY DATE(departed_at)
                      ORDER BY date DESC";
        $daily_stmt = $pdo->prepare($daily_sql);
        $daily_stmt->execute([
            ':driver_id' => $driver_id,
            ':start_date' => $start_date,
            ':end_date' => $end_date,
            ':daily_rate' => $driver_info['payment_rate'] ?? 0
        ]);
        $earnings_breakdown = $daily_stmt->fetchAll();
    }
    
    // Weekly summary
    $weekly_sql = "SELECT 
                        YEARWEEK(departed_at) as week,
                        MIN(DATE(departed_at)) as week_start,
                        MAX(DATE(departed_at)) as week_end,
                        COUNT(*) as trips,
                        SUM(owner_payout) as total_owner_payout,
                        COUNT(DISTINCT DATE(departed_at)) as days_worked
                   FROM trips 
                   WHERE driver_id = :driver_id
                   AND DATE(departed_at) BETWEEN :start_date AND :end_date
                   GROUP BY YEARWEEK(departed_at)
                   ORDER BY week DESC";
    
    $weekly_stmt = $pdo->prepare($weekly_sql);
    $weekly_stmt->execute([
        ':driver_id' => $driver_id,
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    $weekly = $weekly_stmt->fetchAll();
    
    // Calculate weekly earnings based on employment type
    $weekly_earnings = [];
    foreach ($weekly as $week) {
        $week_earnings = 0;
        if ($driver_info['employment_type'] == 'commission') {
            $week_earnings = $week['total_owner_payout'] * ($driver_info['payment_rate'] / 100);
        } else {
            $week_earnings = $week['days_worked'] * ($driver_info['payment_rate'] ?? 0);
        }
        
        $weekly_earnings[] = [
            'week_start' => $week['week_start'],
            'week_end' => $week['week_end'],
            'trips' => $week['trips'],
            'days_worked' => $week['days_worked'],
            'earnings' => $week_earnings
        ];
    }
    
    // Monthly summary for chart
    $monthly_sql = "SELECT 
                        DATE_FORMAT(departed_at, '%Y-%m') as month,
                        COUNT(*) as trips,
                        SUM(owner_payout) as total_owner_payout,
                        COUNT(DISTINCT DATE(departed_at)) as days_worked
                   FROM trips 
                   WHERE driver_id = :driver_id
                   AND departed_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                   GROUP BY DATE_FORMAT(departed_at, '%Y-%m')
                   ORDER BY month DESC";
    
    $monthly_stmt = $pdo->prepare($monthly_sql);
    $monthly_stmt->execute([':driver_id' => $driver_id]);
    $monthly = $monthly_stmt->fetchAll();
    
    // Calculate monthly earnings
    $monthly_earnings = [];
    foreach ($monthly as $month) {
        $month_earnings = 0;
        if ($driver_info['employment_type'] == 'commission') {
            $month_earnings = $month['total_owner_payout'] * ($driver_info['payment_rate'] / 100);
        } else {
            $month_earnings = $month['days_worked'] * ($driver_info['payment_rate'] ?? 0);
        }
        
        $monthly_earnings[] = [
            'month' => $month['month'],
            'trips' => $month['trips'],
            'earnings' => $month_earnings
        ];
    }
    
    // Best day
    $best_day_sql = "SELECT 
                        DATE(departed_at) as best_date,
                        SUM(owner_payout) as total_payout,
                        COUNT(*) as trips
                     FROM trips 
                     WHERE driver_id = :driver_id
                     GROUP BY DATE(departed_at)
                     ORDER BY total_payout DESC
                     LIMIT 1";
    $best_day_stmt = $pdo->prepare($best_day_sql);
    $best_day_stmt->execute([':driver_id' => $driver_id]);
    $best_day = $best_day_stmt->fetch();
    
    // Best route
    $best_route_sql = "SELECT 
                        r.route_name,
                        COUNT(*) as trips,
                        SUM(owner_payout) as total_payout
                       FROM trips t
                       JOIN routes r ON t.route_id = r.id
                       WHERE t.driver_id = :driver_id
                       GROUP BY r.id, r.route_name
                       ORDER BY total_payout DESC
                       LIMIT 1";
    $best_route_stmt = $pdo->prepare($best_route_sql);
    $best_route_stmt->execute([':driver_id' => $driver_id]);
    $best_route = $best_route_stmt->fetch();
    
} catch (PDOException $e) {
    error_log("My earnings error: " . $e->getMessage());
    $summary = [];
    $driver_info = [];
    $earnings_breakdown = [];
    $weekly_earnings = [];
    $monthly_earnings = [];
    $best_day = [];
    $best_route = [];
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-cash-stack"></i> My Earnings</h2>
        <div>
            <button class="btn btn-success" onclick="exportEarnings()">
                <i class="bi bi-file-excel"></i> Export
            </button>
            <button class="btn btn-primary" onclick="printReport()">
                <i class="bi bi-printer"></i> Print
            </button>
        </div>
    </div>

    <!-- Payment Type Banner -->
    <div class="alert alert-info d-flex justify-content-between align-items-center">
        <div>
            <i class="bi bi-info-circle"></i>
            <strong>Payment Type:</strong> 
            <span class="badge bg-primary fs-6"><?= ucfirst($driver_info['employment_type'] ?? 'N/A') ?></span>
            <?php if ($driver_info['employment_type'] == 'commission'): ?>
                <span class="ms-2">You earn <strong><?= $driver_info['payment_rate'] ?>%</strong> commission on owner payout</span>
            <?php elseif ($driver_info['employment_type'] == 'wage'): ?>
                <span class="ms-2">You earn <strong>R <?= number_format($driver_info['payment_rate'] ?? 0, 2) ?></strong> per day worked</span>
            <?php elseif ($driver_info['employment_type'] == 'rental'): ?>
                <span class="ms-2">You pay <strong>R <?= number_format($driver_info['payment_rate'] ?? 0, 2) ?></strong> per day (rental agreement)</span>
            <?php endif; ?>
        </div>
        <div>
            <span class="badge bg-warning fs-6">Owner: <?= htmlspecialchars($driver_info['owner_name'] ?? 'N/A') ?></span>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="card mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="bi bi-calendar-range"></i> Filter Earnings</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="period" class="form-label">Quick Period</label>
                    <select class="form-select" id="period" name="period" onchange="this.form.submit()">
                        <option value="custom" <?= $period == 'custom' ? 'selected' : '' ?>>Custom Range</option>
                        <option value="today" <?= $period == 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="yesterday" <?= $period == 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
                        <option value="thisweek" <?= $period == 'thisweek' ? 'selected' : '' ?>>This Week</option>
                        <option value="lastweek" <?= $period == 'lastweek' ? 'selected' : '' ?>>Last Week</option>
                        <option value="thismonth" <?= $period == 'thismonth' ? 'selected' : '' ?>>This Month</option>
                        <option value="lastmonth" <?= $period == 'lastmonth' ? 'selected' : '' ?>>Last Month</option>
                        <option value="thisyear" <?= $period == 'thisyear' ? 'selected' : '' ?>>This Year</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?= $start_date ?>">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?= $end_date ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Apply Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Main Earnings Card -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-4 text-center">
                            <h6 class="text-white-50">YOUR EARNINGS</h6>
                            <h1 class="display-3 fw-bold">R <?= number_format($driver_earnings, 2) ?></h1>
                            <p class="mb-0"><?= date('d M Y', strtotime($start_date)) ?> - <?= date('d M Y', strtotime($end_date)) ?></p>
                        </div>
                        <div class="col-md-8">
                            <div class="row text-center">
                                <div class="col-3">
                                    <div class="border-end border-white-50">
                                        <h3><?= number_format($summary['total_trips'] ?? 0) ?></h3>
                                        <small>Total Trips</small>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="border-end border-white-50">
                                        <h3><?= number_format($summary['total_passengers'] ?? 0) ?></h3>
                                        <small>Passengers</small>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="border-end border-white-50">
                                        <h3><?= $summary['days_worked'] ?? 0 ?></h3>
                                        <small>Days Worked</small>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div>
                                        <h3><?= number_format($summary['avg_passengers'] ?? 0, 1) ?></h3>
                                        <small>Avg Pass/Trip</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted">Total Fare Collected</h6>
                            <h3 class="mb-0">R <?= number_format($summary['total_revenue'] ?? 0, 2) ?></h3>
                            <small class="text-muted">Before deductions</small>
                        </div>
                        <div class="bg-light p-3 rounded">
                            <i class="bi bi-cash-stack fs-1 text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted">Association Levy</h6>
                            <h3 class="mb-0">R <?= number_format($summary['total_levy'] ?? 0, 2) ?></h3>
                            <small class="text-muted">Deducted from fares</small>
                        </div>
                        <div class="bg-light p-3 rounded">
                            <i class="bi bi-building fs-1 text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted">Owner Payout</h6>
                            <h3 class="mb-0">R <?= number_format($summary['total_owner_payout'] ?? 0, 2) ?></h3>
                            <small class="text-muted">Net after levy</small>
                        </div>
                        <div class="bg-light p-3 rounded">
                            <i class="bi bi-truck fs-1 text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted">Your Percentage</h6>
                            <h3 class="mb-0">
                                <?php if ($driver_info['employment_type'] == 'commission'): ?>
                                    <?= $driver_info['payment_rate'] ?>%
                                <?php else: ?>
                                    R <?= number_format($driver_info['payment_rate'] ?? 0, 2) ?>/day
                                <?php endif; ?>
                            </h3>
                            <small class="text-muted">of owner payout</small>
                        </div>
                        <div class="bg-light p-3 rounded">
                            <i class="bi bi-pie-chart fs-1 text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Best Performance Row -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card bg-warning">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bi bi-trophy fs-1 text-white"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="text-white">Best Day</h5>
                            <?php if ($best_day): ?>
                                <h3 class="text-white"><?= date('d M Y', strtotime($best_day['best_date'])) ?></h3>
                                <p class="text-white mb-0">
                                    R <?= number_format($driver_info['employment_type'] == 'commission' ? 
                                        $best_day['total_payout'] * ($driver_info['payment_rate'] / 100) : 
                                        $driver_info['payment_rate'] ?? 0, 2) ?> 
                                    earned • <?= $best_day['trips'] ?> trips
                                </p>
                            <?php else: ?>
                                <p class="text-white mb-0">No data available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card bg-success">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bi bi-signpost fs-1 text-white"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="text-white">Best Route</h5>
                            <?php if ($best_route): ?>
                                <h3 class="text-white"><?= htmlspecialchars($best_route['route_name']) ?></h3>
                                <p class="text-white mb-0">
                                    <?= $best_route['trips'] ?> trips • 
                                    R <?= number_format($driver_info['employment_type'] == 'commission' ? 
                                        $best_route['total_payout'] * ($driver_info['payment_rate'] / 100) : 
                                        $driver_info['payment_rate'] ?? 0, 2) ?> earned
                                </p>
                            <?php else: ?>
                                <p class="text-white mb-0">No data available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Earnings Breakdown Chart -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Monthly Earnings (Last 12 Months)</h5>
                </div>
                <div class="card-body">
                    <canvas id="earningsChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Daily/Weekly Breakdown Tabs -->
    <ul class="nav nav-tabs mb-4" id="earningsTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="daily-tab" data-bs-toggle="tab" data-bs-target="#daily" type="button" role="tab">
                <i class="bi bi-calendar-day"></i> Daily Breakdown
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="weekly-tab" data-bs-toggle="tab" data-bs-target="#weekly" type="button" role="tab">
                <i class="bi bi-calendar-week"></i> Weekly Summary
            </button>
        </li>
    </ul>

    <div class="tab-content" id="earningsTabsContent">
        <!-- Daily Breakdown Tab -->
        <div class="tab-pane fade show active" id="daily" role="tabpanel">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-sun"></i> Daily Earnings</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Trips</th>
                                    <th>Owner Payout</th>
                                    <th>Your Earnings</th>
                                    <th>Per Trip</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($earnings_breakdown)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                            <h5 class="text-muted">No earnings data for selected period</h5>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($earnings_breakdown as $day): ?>
                                    <tr>
                                        <td><strong><?= date('d M Y', strtotime($day['date'])) ?></strong></td>
                                        <td><?= $day['trips'] ?></td>
                                        <td>R <?= number_format($day['daily_owner_payout'] ?? 0, 2) ?></td>
                                        <td class="fw-bold text-success">
                                            R <?= number_format($day['driver_earnings'] ?? 0, 2) ?>
                                        </td>
                                        <td>R <?= $day['trips'] ? number_format(($day['driver_earnings'] ?? 0) / $day['trips'], 2) : 0 ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewDay('<?= $day['date'] ?>')">
                                                <i class="bi bi-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Weekly Summary Tab -->
        <div class="tab-pane fade" id="weekly" role="tabpanel">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-calendar-week"></i> Weekly Summary</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Week</th>
                                    <th>Days Worked</th>
                                    <th>Trips</th>
                                    <th>Your Earnings</th>
                                    <th>Daily Average</th>
                                    <th>Per Trip</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($weekly_earnings)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                            <h5 class="text-muted">No weekly data available</h5>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($weekly_earnings as $week): ?>
                                    <tr>
                                        <td>
                                            <?= date('d M', strtotime($week['week_start'])) ?> - 
                                            <?= date('d M', strtotime($week['week_end'])) ?>
                                        </td>
                                        <td><?= $week['days_worked'] ?> days</td>
                                        <td><?= $week['trips'] ?></td>
                                        <td class="fw-bold text-success">R <?= number_format($week['earnings'], 2) ?></td>
                                        <td>R <?= $week['days_worked'] ? number_format($week['earnings'] / $week['days_worked'], 2) : 0 ?></td>
                                        <td>R <?= $week['trips'] ? number_format($week['earnings'] / $week['trips'], 2) : 0 ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Calculation Info -->
    <div class="card mt-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="bi bi-calculator"></i> How Your Earnings Are Calculated</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>Current Payment Structure:</h6>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between">
                            <span>Employment Type:</span>
                            <strong><?= ucfirst($driver_info['employment_type'] ?? 'N/A') ?></strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span>Payment Rate:</span>
                            <strong>
                                <?php if ($driver_info['employment_type'] == 'commission'): ?>
                                    <?= $driver_info['payment_rate'] ?>% of owner payout
                                <?php else: ?>
                                    R <?= number_format($driver_info['payment_rate'] ?? 0, 2) ?> per day
                                <?php endif; ?>
                            </strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span>Owner:</span>
                            <strong><?= htmlspecialchars($driver_info['owner_name'] ?? 'N/A') ?></strong>
                        </li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6>Example Calculation:</h6>
                    <?php if ($driver_info['employment_type'] == 'commission'): ?>
                        <p>If owner payout is <strong>R 1,000</strong>, your earnings would be:</p>
                        <h3 class="text-success">R 1,000 × <?= $driver_info['payment_rate'] ?>% = R <?= number_format(1000 * ($driver_info['payment_rate'] / 100), 2) ?></h3>
                    <?php else: ?>
                        <p>If you work <strong>5 days</strong>, your earnings would be:</p>
                        <h3 class="text-success">5 × R <?= number_format($driver_info['payment_rate'] ?? 0, 2) ?> = R <?= number_format(5 * ($driver_info['payment_rate'] ?? 0), 2) ?></h3>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Day Modal -->
<div class="modal fade" id="viewDayModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-calendar-day"></i> <span id="dayModalTitle"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="dayDetails">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
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
// Handle period selection
document.getElementById('period')?.addEventListener('change', function() {
    const period = this.value;
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    const today = new Date();
    
    switch(period) {
        case 'today':
            startDate.value = today.toISOString().split('T')[0];
            endDate.value = today.toISOString().split('T')[0];
            break;
        case 'yesterday':
            const yesterday = new Date(today);
            yesterday.setDate(yesterday.getDate() - 1);
            startDate.value = yesterday.toISOString().split('T')[0];
            endDate.value = yesterday.toISOString().split('T')[0];
            break;
        case 'thisweek':
            const firstDay = new Date(today.setDate(today.getDate() - today.getDay() + 1));
            const lastDay = new Date(today.setDate(today.getDate() - today.getDay() + 7));
            startDate.value = firstDay.toISOString().split('T')[0];
            lastDay.value = lastDay.toISOString().split('T')[0];
            break;
        case 'lastweek':
            const lastWeekStart = new Date(today.setDate(today.getDate() - today.getDay() - 6));
            const lastWeekEnd = new Date(today.setDate(today.getDate() - today.getDay()));
            startDate.value = lastWeekStart.toISOString().split('T')[0];
            lastWeekEnd.value = lastWeekEnd.toISOString().split('T')[0];
            break;
        case 'thismonth':
            const firstDayMonth = new Date(today.getFullYear(), today.getMonth(), 1);
            const lastDayMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            startDate.value = firstDayMonth.toISOString().split('T')[0];
            lastDayMonth.value = lastDayMonth.toISOString().split('T')[0];
            break;
        case 'lastmonth':
            const firstDayLastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            const lastDayLastMonth = new Date(today.getFullYear(), today.getMonth(), 0);
            startDate.value = firstDayLastMonth.toISOString().split('T')[0];
            lastDayLastMonth.value = lastDayLastMonth.toISOString().split('T')[0];
            break;
        case 'thisyear':
            const firstDayYear = new Date(today.getFullYear(), 0, 1);
            const lastDayYear = new Date(today.getFullYear(), 11, 31);
            startDate.value = firstDayYear.toISOString().split('T')[0];
            lastDayYear.value = lastDayYear.toISOString().split('T')[0];
            break;
    }
});

// View day details
function viewDay(date) {
    const modal = new bootstrap.Modal(document.getElementById('viewDayModal'));
    document.getElementById('dayModalTitle').textContent = `Trips for ${new Date(date).toLocaleDateString('en-ZA', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}`;
    
    fetch(`get_day_trips.php?date=${date}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('dayDetails').innerHTML = html;
            modal.show();
        });
}

// Export earnings
function exportEarnings() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    window.location.href = `export/export_earnings.php?start_date=${startDate}&end_date=${endDate}`;
}

// Print report
function printReport() {
    window.print();
}

// Monthly Earnings Chart
<?php if (!empty($monthly_earnings)): ?>
const ctx = document.getElementById('earningsChart')?.getContext('2d');
if (ctx) {
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: [<?php 
                $months = array_reverse(array_column($monthly_earnings, 'month'));
                echo "'" . implode("','", array_map(function($m) {
                    return date('M Y', strtotime($m . '-01'));
                }, $months)) . "'";
            ?>],
            datasets: [{
                label: 'Your Earnings',
                data: [<?php 
                    $earnings = array_reverse(array_column($monthly_earnings, 'earnings'));
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
    border-radius: 10px;
    overflow: hidden;
}
.card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.border-end {
    border-right: 1px solid rgba(255,255,255,0.3);
}
.table td {
    vertical-align: middle;
}
.badge {
    font-size: 12px;
    padding: 6px 10px;
}
.display-3 {
    font-size: 3.5rem;
    font-weight: 700;
}
@media print {
    .btn, .card-header, footer, .filter-section {
        display: none !important;
    }
}
</style>

<?php
// Get the content
$content = ob_get_clean();

// Include the driver layout
require_once '../layouts/driver_layout.php';
?>