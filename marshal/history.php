<?php
// marshal/history.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('marshal');

// Set page title
$page_title = 'Trip History';

// Start output buffering
ob_start();

// Get filter parameters
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$route_filter = $_GET['route'] ?? 'all';
$driver_filter = $_GET['driver'] ?? 'all';
$taxi_filter = $_GET['taxi'] ?? 'all';
$sort_by = $_GET['sort_by'] ?? 'departed_at';
$sort_order = $_GET['sort_order'] ?? 'DESC';
$limit = $_GET['limit'] ?? 100;

// Safe division function
function safeDivide($numerator, $denominator, $decimals = 2) {
    if ($denominator == 0 || $denominator == null || $numerator == 0) {
        return 0;
    }
    return round($numerator / $denominator, $decimals);
}

// Safe percentage function
function safePercentage($part, $whole, $decimals = 1) {
    if ($whole == 0 || $whole == null || $part == 0) {
        return 0;
    }
    return round(($part / $whole) * 100, $decimals);
}

try {
    // Get all routes for filter dropdown
    $routes_sql = "SELECT id, route_name FROM routes WHERE is_active = 1 ORDER BY route_name";
    $routes = $pdo->query($routes_sql)->fetchAll();
    
    // Get all drivers for filter dropdown
    $drivers_sql = "SELECT d.id, u.full_name as driver_name 
                    FROM drivers d
                    JOIN users u ON d.user_id = u.id
                    WHERE u.is_active = 1
                    ORDER BY u.full_name";
    $drivers = $pdo->query($drivers_sql)->fetchAll();
    
    // Get all taxis for filter dropdown
    $taxis_sql = "SELECT id, registration_number FROM taxis ORDER BY registration_number";
    $taxis = $pdo->query($taxis_sql)->fetchAll();
    
    // Build the main query
    $query = "SELECT t.*, 
                     r.route_name,
                     tx.registration_number,
                     u.full_name as driver_name,
                     u.phone_number as driver_phone,
                     ow.full_name as owner_name,
                     DATE_FORMAT(t.departed_at, '%d/%m/%Y') as formatted_date,
                     DATE_FORMAT(t.departed_at, '%H:%i') as formatted_time,
                     DATE_FORMAT(t.departed_at, '%W') as day_of_week,
                     WEEK(t.departed_at) as week_number,
                     MONTH(t.departed_at) as month_number,
                     YEAR(t.departed_at) as year_number
              FROM trips t
              JOIN routes r ON t.route_id = r.id
              JOIN taxis tx ON t.taxi_id = tx.id
              JOIN drivers d ON t.driver_id = d.id
              JOIN users u ON d.user_id = u.id
              JOIN owners o ON tx.owner_id = o.id
              JOIN users ow ON o.user_id = ow.id
              WHERE DATE(t.departed_at) BETWEEN :start_date AND :end_date";
    
    $params = [
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ];
    
    if ($route_filter != 'all') {
        $query .= " AND t.route_id = :route_id";
        $params[':route_id'] = $route_filter;
    }
    
    if ($driver_filter != 'all') {
        $query .= " AND t.driver_id = :driver_id";
        $params[':driver_id'] = $driver_filter;
    }
    
    if ($taxi_filter != 'all') {
        $query .= " AND t.taxi_id = :taxi_id";
        $params[':taxi_id'] = $taxi_filter;
    }
    
    // Validate sort column
    $allowed_sort = ['departed_at', 'total_fare', 'passenger_count', 'route_name', 'driver_name'];
    $sort_by = in_array($sort_by, $allowed_sort) ? $sort_by : 'departed_at';
    $sort_order = strtoupper($sort_order) == 'ASC' ? 'ASC' : 'DESC';
    
    $query .= " ORDER BY $sort_by $sort_order LIMIT :limit";
    $params[':limit'] = (int)$limit;
    
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        if ($key == ':limit') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }
    $stmt->execute();
    $trips = $stmt->fetchAll();
    
    // Get summary statistics
    $summary_sql = "SELECT 
                        COUNT(*) as total_trips,
                        COALESCE(SUM(passenger_count), 0) as total_passengers,
                        COALESCE(SUM(total_fare), 0) as total_revenue,
                        COALESCE(SUM(association_levy), 0) as total_levy,
                        COALESCE(SUM(owner_payout), 0) as total_owner_payout,
                        COALESCE(AVG(passenger_count), 0) as avg_passengers,
                        COALESCE(AVG(total_fare), 0) as avg_fare,
                        MIN(total_fare) as min_fare,
                        MAX(total_fare) as max_fare,
                        COUNT(DISTINCT taxi_id) as unique_taxis,
                        COUNT(DISTINCT driver_id) as unique_drivers,
                        COUNT(DISTINCT DATE(departed_at)) as days_with_trips
                    FROM trips 
                    WHERE DATE(departed_at) BETWEEN :start_date AND :end_date";
    
    $summary_params = [':start_date' => $start_date, ':end_date' => $end_date];
    
    if ($route_filter != 'all') {
        $summary_sql .= " AND route_id = :route_id";
        $summary_params[':route_id'] = $route_filter;
    }
    
    if ($driver_filter != 'all') {
        $summary_sql .= " AND driver_id = :driver_id";
        $summary_params[':driver_id'] = $driver_filter;
    }
    
    if ($taxi_filter != 'all') {
        $summary_sql .= " AND taxi_id = :taxi_id";
        $summary_params[':taxi_id'] = $taxi_filter;
    }
    
    $summary_stmt = $pdo->prepare($summary_sql);
    $summary_stmt->execute($summary_params);
    $summary = $summary_stmt->fetch();
    
    // Get monthly breakdown
    $monthly_sql = "SELECT 
                        DATE_FORMAT(departed_at, '%Y-%m') as month,
                        COUNT(*) as trips,
                        SUM(passenger_count) as passengers,
                        SUM(total_fare) as revenue,
                        SUM(association_levy) as levy,
                        SUM(owner_payout) as payout
                    FROM trips 
                    WHERE DATE(departed_at) BETWEEN :start_date AND :end_date
                    GROUP BY DATE_FORMAT(departed_at, '%Y-%m')
                    ORDER BY month DESC";
    
    $monthly_stmt = $pdo->prepare($monthly_sql);
    $monthly_stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
    $monthly = $monthly_stmt->fetchAll();
    
    // Get daily breakdown
    $daily_sql = "SELECT 
                        DATE(departed_at) as date,
                        COUNT(*) as trips,
                        SUM(passenger_count) as passengers,
                        SUM(total_fare) as revenue
                    FROM trips 
                    WHERE DATE(departed_at) BETWEEN :start_date AND :end_date
                    GROUP BY DATE(departed_at)
                    ORDER BY date DESC";
    
    $daily_stmt = $pdo->prepare($daily_sql);
    $daily_stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
    $daily = $daily_stmt->fetchAll();
    
    // Get peak hours
    $peak_sql = "SELECT 
                    HOUR(departed_at) as hour,
                    COUNT(*) as trips,
                    AVG(passenger_count) as avg_passengers
                 FROM trips 
                 WHERE DATE(departed_at) BETWEEN :start_date AND :end_date
                 GROUP BY HOUR(departed_at)
                 ORDER BY trips DESC
                 LIMIT 5";
    
    $peak_stmt = $pdo->prepare($peak_sql);
    $peak_stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
    $peak_hours = $peak_stmt->fetchAll();
    
    // Get top routes
    $top_routes_sql = "SELECT 
                            r.route_name,
                            COUNT(*) as trips,
                            SUM(t.passenger_count) as passengers,
                            SUM(t.total_fare) as revenue
                        FROM trips t
                        JOIN routes r ON t.route_id = r.id
                        WHERE DATE(t.departed_at) BETWEEN :start_date AND :end_date
                        GROUP BY r.id, r.route_name
                        ORDER BY trips DESC
                        LIMIT 5";
    
    $top_routes_stmt = $pdo->prepare($top_routes_sql);
    $top_routes_stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
    $top_routes = $top_routes_stmt->fetchAll();
    
    // Get top drivers
    $top_drivers_sql = "SELECT 
                            u.full_name as driver_name,
                            COUNT(*) as trips,
                            SUM(t.passenger_count) as passengers,
                            SUM(t.total_fare) as revenue
                        FROM trips t
                        JOIN drivers d ON t.driver_id = d.id
                        JOIN users u ON d.user_id = u.id
                        WHERE DATE(t.departed_at) BETWEEN :start_date AND :end_date
                        GROUP BY d.id, u.full_name
                        ORDER BY trips DESC
                        LIMIT 5";
    
    $top_drivers_stmt = $pdo->prepare($top_drivers_sql);
    $top_drivers_stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
    $top_drivers_history = $top_drivers_stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("History page error: " . $e->getMessage());
    $trips = [];
    $summary = [
        'total_trips' => 0,
        'total_passengers' => 0,
        'total_revenue' => 0,
        'total_levy' => 0,
        'total_owner_payout' => 0,
        'avg_passengers' => 0,
        'avg_fare' => 0,
        'min_fare' => 0,
        'max_fare' => 0,
        'unique_taxis' => 0,
        'unique_drivers' => 0,
        'days_with_trips' => 0
    ];
    $monthly = [];
    $daily = [];
    $peak_hours = [];
    $top_routes = [];
    $top_drivers_history = [];
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-calendar-range"></i> Trip History</h2>
        <div>
            <button class="btn btn-success" onclick="exportHistory()">
                <i class="bi bi-file-excel"></i> Export
            </button>
            <button class="btn btn-primary" onclick="printReport()">
                <i class="bi bi-printer"></i> Print
            </button>
            <button class="btn btn-info" onclick="refreshData()">
                <i class="bi bi-arrow-repeat"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Advanced Filter Section -->
    <div class="card mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="bi bi-sliders"></i> Advanced Filters</h5>
        </div>
        <div class="card-body">
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
                    <label for="route" class="form-label">Route</label>
                    <select class="form-select" id="route" name="route">
                        <option value="all" <?= $route_filter == 'all' ? 'selected' : '' ?>>All Routes</option>
                        <?php foreach ($routes as $route): ?>
                            <option value="<?= $route['id'] ?>" <?= $route_filter == $route['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($route['route_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="driver" class="form-label">Driver</label>
                    <select class="form-select" id="driver" name="driver">
                        <option value="all" <?= $driver_filter == 'all' ? 'selected' : '' ?>>All Drivers</option>
                        <?php foreach ($drivers as $driver): ?>
                            <option value="<?= $driver['id'] ?>" <?= $driver_filter == $driver['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($driver['driver_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="taxi" class="form-label">Taxi</label>
                    <select class="form-select" id="taxi" name="taxi">
                        <option value="all" <?= $taxi_filter == 'all' ? 'selected' : '' ?>>All Taxis</option>
                        <?php foreach ($taxis as $taxi): ?>
                            <option value="<?= $taxi['id'] ?>" <?= $taxi_filter == $taxi['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($taxi['registration_number']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="sort_by" class="form-label">Sort By</label>
                    <select class="form-select" id="sort_by" name="sort_by">
                        <option value="departed_at" <?= $sort_by == 'departed_at' ? 'selected' : '' ?>>Date</option>
                        <option value="total_fare" <?= $sort_by == 'total_fare' ? 'selected' : '' ?>>Fare Amount</option>
                        <option value="passenger_count" <?= $sort_by == 'passenger_count' ? 'selected' : '' ?>>Passengers</option>
                        <option value="route_name" <?= $sort_by == 'route_name' ? 'selected' : '' ?>>Route</option>
                        <option value="driver_name" <?= $sort_by == 'driver_name' ? 'selected' : '' ?>>Driver</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="sort_order" class="form-label">Order</label>
                    <select class="form-select" id="sort_order" name="sort_order">
                        <option value="DESC" <?= $sort_order == 'DESC' ? 'selected' : '' ?>>Descending</option>
                        <option value="ASC" <?= $sort_order == 'ASC' ? 'selected' : '' ?>>Ascending</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="limit" class="form-label">Results Limit</label>
                    <select class="form-select" id="limit" name="limit">
                        <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                        <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                        <option value="250" <?= $limit == 250 ? 'selected' : '' ?>>250</option>
                        <option value="500" <?= $limit == 500 ? 'selected' : '' ?>>500</option>
                        <option value="1000" <?= $limit == 1000 ? 'selected' : '' ?>>1000</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Apply Filters
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="resetFilters()">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                    </button>
                    <span class="float-end text-muted">
                        Showing <?= count($trips) ?> of <?= $summary['total_trips'] ?> trips
                    </span>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Total Trips</h6>
                    <h3 class="mb-0"><?= number_format($summary['total_trips'] ?? 0) ?></h3>
                    <small><?= $summary['days_with_trips'] ?> days with trips</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Passengers</h6>
                    <h3 class="mb-0"><?= number_format($summary['total_passengers'] ?? 0) ?></h3>
                    <small>Avg: <?= number_format($summary['avg_passengers'] ?? 0, 1) ?> per trip</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Total Revenue</h6>
                    <h3 class="mb-0">R <?= number_format($summary['total_revenue'] ?? 0, 2) ?></h3>
                    <small>Avg: R <?= number_format($summary['avg_fare'] ?? 0, 2) ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Fare Range</h6>
                    <h3 class="mb-0">R <?= number_format($summary['min_fare'] ?? 0, 2) ?> - R <?= number_format($summary['max_fare'] ?? 0, 2) ?></h3>
                    <small>Min / Max</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Analytics Row -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> Daily Trip Trends</h5>
                </div>
                <div class="card-body">
                    <canvas id="trendsChart" height="100"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-clock"></i> Peak Hours</h5>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <?php if (empty($peak_hours)): ?>
                            <p class="text-muted text-center py-3">No data available</p>
                        <?php else: ?>
                            <?php foreach ($peak_hours as $peak): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><?= str_pad($peak['hour'], 2, '0', STR_PAD_LEFT) ?>:00 - <?= str_pad($peak['hour'] + 1, 2, '0', STR_PAD_LEFT) ?>:00</span>
                                    <span class="badge bg-primary rounded-pill"><?= $peak['trips'] ?> trips</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Performers Row -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-signpost"></i> Top Routes</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Route</th>
                                    <th>Trips</th>
                                    <th>Passengers</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($top_routes)): ?>
                                    <tr><td colspan="4" class="text-center py-3">No data available</td></tr>
                                <?php else: ?>
                                    <?php foreach ($top_routes as $route): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($route['route_name']) ?></td>
                                        <td><?= $route['trips'] ?></td>
                                        <td><?= $route['passengers'] ?></td>
                                        <td>R <?= number_format($route['revenue'], 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-trophy"></i> Top Drivers</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Driver</th>
                                    <th>Trips</th>
                                    <th>Passengers</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($top_drivers_history)): ?>
                                    <tr><td colspan="4" class="text-center py-3">No data available</td></tr>
                                <?php else: ?>
                                    <?php foreach ($top_drivers_history as $driver): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($driver['driver_name']) ?></td>
                                        <td><?= $driver['trips'] ?></td>
                                        <td><?= $driver['passengers'] ?></td>
                                        <td>R <?= number_format($driver['revenue'], 2) ?></td>
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

    <!-- Trip History Table -->
    <div class="card">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-clock-history"></i> Trip History</h5>
            <div>
                <input type="text" class="form-control form-control-sm" placeholder="Search trips..." id="searchInput" style="width: 250px;">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="historyTable">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Day</th>
                            <th>Taxi</th>
                            <th>Driver</th>
                            <th>Route</th>
                            <th>Pass</th>
                            <th>Fare</th>
                            <th>Levy</th>
                            <th>Payout</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($trips)): ?>
                            <tr>
                                <td colspan="11" class="text-center py-5">
                                    <i class="bi bi-clock-history fs-1 text-muted d-block mb-3"></i>
                                    <h5 class="text-muted">No Trip History Found</h5>
                                    <p class="text-muted">Try adjusting your filters or selecting a different date range.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($trips as $trip): ?>
                            <tr>
                                <td><strong><?= $trip['formatted_date'] ?></strong></td>
                                <td><?= $trip['formatted_time'] ?></td>
                                <td><?= substr($trip['day_of_week'], 0, 3) ?></td>
                                <td><?= htmlspecialchars($trip['registration_number']) ?></td>
                                <td>
                                    <?= htmlspecialchars($trip['driver_name']) ?>
                                    <br>
                                    <small class="text-muted"><?= htmlspecialchars($trip['driver_phone']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($trip['route_name']) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-info"><?= $trip['passenger_count'] ?></span>
                                </td>
                                <td>R <?= number_format($trip['total_fare'], 2) ?></td>
                                <td>R <?= number_format($trip['association_levy'], 2) ?></td>
                                <td class="fw-bold text-success">R <?= number_format($trip['owner_payout'], 2) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-info" onclick="viewTrip(<?= $trip['id'] ?>)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-warning" onclick="printReceipt(<?= $trip['id'] ?>)">
                                        <i class="bi bi-receipt"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <div class="text-muted">
                <i class="bi bi-info-circle"></i> Showing <?= count($trips) ?> trips
            </div>
            <div>
                <button class="btn btn-sm btn-outline-primary" onclick="loadMore()" id="loadMoreBtn" style="<?= count($trips) < $limit ? 'display: none;' : '' ?>">
                    <i class="bi bi-plus-circle"></i> Load More
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Trip Modal -->
<div class="modal fade" id="viewTripModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-eye"></i> Trip Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="tripDetails">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printReceipt(currentTripId)">
                    <i class="bi bi-printer"></i> Print Receipt
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Print Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-receipt"></i> Trip Receipt</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="receiptContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-success" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" onclick="window.print()">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let currentTripId = null;
let currentPage = 1;
const limit = <?= $limit ?>;

// Search functionality
document.getElementById('searchInput')?.addEventListener('keyup', function() {
    const searchText = this.value.toLowerCase();
    const table = document.getElementById('historyTable');
    if (!table) return;
    
    const rows = table.getElementsByTagName('tr');
    
    for (let i = 1; i < rows.length; i++) {
        const row = rows[i];
        const taxi = row.cells[3]?.textContent.toLowerCase() || '';
        const driver = row.cells[4]?.textContent.toLowerCase() || '';
        const route = row.cells[5]?.textContent.toLowerCase() || '';
        
        if (taxi.includes(searchText) || driver.includes(searchText) || route.includes(searchText)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    }
});

// View trip details
function viewTrip(id) {
    currentTripId = id;
    const modal = new bootstrap.Modal(document.getElementById('viewTripModal'));
    
    fetch(`get_trip.php?id=${id}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('tripDetails').innerHTML = html;
            modal.show();
        })
        .catch(error => {
            document.getElementById('tripDetails').innerHTML = '<div class="alert alert-danger">Error loading trip details</div>';
        });
}

// Print receipt
function printReceipt(id) {
    fetch(`get_receipt.php?id=${id}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('receiptContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('receiptModal')).show();
        })
        .catch(error => {
            document.getElementById('receiptContent').innerHTML = '<div class="alert alert-danger">Error loading receipt</div>';
        });
}

// Export history
function exportHistory() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const route = document.getElementById('route').value;
    const driver = document.getElementById('driver').value;
    const taxi = document.getElementById('taxi').value;
    
    alert('Export feature will be available in the next update!');
    // window.location.href = `export/export_history.php?start_date=${startDate}&end_date=${endDate}&route=${route}&driver=${driver}&taxi=${taxi}`;
}

// Print report
function printReport() {
    window.print();
}

// Refresh data
function refreshData() {
    window.location.reload();
}

// Reset filters
function resetFilters() {
    window.location.href = 'history.php';
}

// Load more results
function loadMore() {
    currentPage++;
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const route = document.getElementById('route').value;
    const driver = document.getElementById('driver').value;
    const taxi = document.getElementById('taxi').value;
    const sortBy = document.getElementById('sort_by').value;
    const sortOrder = document.getElementById('sort_order').value;
    
    window.location.href = `history.php?start_date=${startDate}&end_date=${endDate}&route=${route}&driver=${driver}&taxi=${taxi}&sort_by=${sortBy}&sort_order=${sortOrder}&limit=${limit}&page=${currentPage}`;
}

<?php if (!empty($daily)): ?>
// Trends Chart
const trendsCtx = document.getElementById('trendsChart')?.getContext('2d');
if (trendsCtx) {
    new Chart(trendsCtx, {
        type: 'line',
        data: {
            labels: [<?php 
                $dates = array_reverse(array_column($daily, 'date'));
                echo "'" . implode("','", array_map(function($d) {
                    return date('d M', strtotime($d));
                }, $dates)) . "'";
            ?>],
            datasets: [{
                label: 'Trips',
                data: [<?php 
                    $trips_data = array_reverse(array_column($daily, 'trips'));
                    echo implode(',', $trips_data);
                ?>],
                borderColor: 'rgb(54, 162, 235)',
                backgroundColor: 'rgba(54, 162, 235, 0.1)',
                tension: 0.4,
                fill: true,
                yAxisID: 'y'
            }, {
                label: 'Passengers',
                data: [<?php 
                    $passengers_data = array_reverse(array_column($daily, 'passengers'));
                    echo implode(',', $passengers_data);
                ?>],
                borderColor: 'rgb(255, 99, 132)',
                backgroundColor: 'rgba(255, 99, 132, 0.1)',
                tension: 0.4,
                fill: true,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Number of Trips'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    grid: {
                        drawOnChartArea: false,
                    },
                    title: {
                        display: true,
                        text: 'Number of Passengers'
                    }
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
.table td {
    vertical-align: middle;
}
.badge {
    font-size: 11px;
    padding: 5px 8px;
}
.list-group-item {
    border: none;
    border-bottom: 1px solid #f0f0f0;
}
.list-group-item:last-child {
    border-bottom: none;
}
</style>

<?php
// Get the content
$content = ob_get_clean();

// Include the marshal layout
require_once '../layouts/marshal_layout.php';
?>