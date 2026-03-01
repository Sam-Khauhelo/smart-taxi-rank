<?php
// marshal/trips.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('marshal');

// Set page title
$page_title = 'Today\'s Trips';

// Start output buffering
ob_start();

// Get date range from request or default to today
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$route_filter = $_GET['route'] ?? 'all';

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
    
    // Get trips with details
    $trips_sql = "SELECT t.*, 
                         r.route_name,
                         tx.registration_number,
                         u.full_name as driver_name,
                         ow.full_name as owner_name,
                         TIME(t.departed_at) as trip_time,
                         DATE(t.departed_at) as trip_date,
                         TIMEDIFF(NOW(), t.departed_at) as time_ago
                  FROM trips t
                  JOIN routes r ON t.route_id = r.id
                  JOIN taxis tx ON t.taxi_id = tx.id
                  JOIN drivers d ON t.driver_id = d.id
                  JOIN users u ON d.user_id = u.id
                  JOIN owners o ON tx.owner_id = o.id
                  JOIN users ow ON o.user_id = ow.id
                  WHERE DATE(t.departed_at) BETWEEN :start_date AND :end_date";
    
    if ($route_filter != 'all') {
        $trips_sql .= " AND t.route_id = :route_id";
    }
    
    $trips_sql .= " ORDER BY t.departed_at DESC";
    
    $trips_stmt = $pdo->prepare($trips_sql);
    $params = [
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ];
    
    if ($route_filter != 'all') {
        $params[':route_id'] = $route_filter;
    }
    
    $trips_stmt->execute($params);
    $trips = $trips_stmt->fetchAll();
    
    // Summary statistics
    $summary_sql = "SELECT 
                        COUNT(*) as total_trips,
                        COALESCE(SUM(passenger_count), 0) as total_passengers,
                        COALESCE(SUM(total_fare), 0) as total_revenue,
                        COALESCE(SUM(association_levy), 0) as total_levy,
                        COALESCE(SUM(owner_payout), 0) as total_owner_payout,
                        COALESCE(AVG(passenger_count), 0) as avg_passengers,
                        COALESCE(AVG(total_fare), 0) as avg_fare,
                        COUNT(DISTINCT taxi_id) as unique_taxis,
                        COUNT(DISTINCT driver_id) as unique_drivers
                    FROM trips 
                    WHERE DATE(departed_at) BETWEEN :start_date AND :end_date";
    
    if ($route_filter != 'all') {
        $summary_sql .= " AND route_id = :route_id";
    }
    
    $summary_stmt = $pdo->prepare($summary_sql);
    $summary_stmt->execute($params);
    $summary = $summary_stmt->fetch();
    
    // Hourly breakdown
    $hourly_sql = "SELECT 
                        HOUR(departed_at) as hour,
                        COUNT(*) as trips,
                        SUM(passenger_count) as passengers,
                        SUM(total_fare) as revenue
                   FROM trips 
                   WHERE DATE(departed_at) BETWEEN :start_date AND :end_date";
    
    if ($route_filter != 'all') {
        $hourly_sql .= " AND route_id = :route_id";
    }
    
    $hourly_sql .= " GROUP BY HOUR(departed_at) ORDER BY hour";
    
    $hourly_stmt = $pdo->prepare($hourly_sql);
    $hourly_stmt->execute($params);
    $hourly = $hourly_stmt->fetchAll();
    
    // Route breakdown
    $route_breakdown_sql = "SELECT 
                                r.route_name,
                                COUNT(*) as trips,
                                SUM(t.passenger_count) as passengers,
                                SUM(t.total_fare) as revenue,
                                COALESCE(AVG(t.passenger_count), 0) as avg_passengers,
                                SUM(t.association_levy) as levy
                             FROM trips t
                             JOIN routes r ON t.route_id = r.id
                             WHERE DATE(t.departed_at) BETWEEN :start_date AND :end_date
                             GROUP BY r.id, r.route_name
                             ORDER BY trips DESC";
    
    $route_breakdown_stmt = $pdo->prepare($route_breakdown_sql);
    $route_breakdown_stmt->execute([
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    $route_breakdown = $route_breakdown_stmt->fetchAll();
    
    // Top drivers
    $top_drivers_sql = "SELECT 
                            u.full_name as driver_name,
                            COUNT(*) as trips,
                            SUM(t.passenger_count) as passengers,
                            SUM(t.total_fare) as revenue,
                            COALESCE(AVG(t.passenger_count), 0) as avg_passengers
                         FROM trips t
                         JOIN drivers d ON t.driver_id = d.id
                         JOIN users u ON d.user_id = u.id
                         WHERE DATE(t.departed_at) BETWEEN :start_date AND :end_date
                         GROUP BY d.id, u.full_name
                         ORDER BY trips DESC
                         LIMIT 5";
    
    $top_drivers_stmt = $pdo->prepare($top_drivers_sql);
    $top_drivers_stmt->execute([
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    $top_drivers = $top_drivers_stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Trips page error: " . $e->getMessage());
    $trips = [];
    $summary = [
        'total_trips' => 0,
        'total_passengers' => 0,
        'total_revenue' => 0,
        'total_levy' => 0,
        'total_owner_payout' => 0,
        'avg_passengers' => 0,
        'avg_fare' => 0,
        'unique_taxis' => 0,
        'unique_drivers' => 0
    ];
    $hourly = [];
    $route_breakdown = [];
    $top_drivers = [];
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-clock-history"></i> Trip Management</h2>
        <div>
            <button class="btn btn-success" onclick="exportTrips()">
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

    <!-- Filter Section -->
    <div class="card mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="bi bi-funnel"></i> Filter Trips</h5>
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
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-2 col-6">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Total Trips</h6>
                    <h3 class="mb-0"><?= number_format($summary['total_trips'] ?? 0) ?></h3>
                    <small><?= date('d/m', strtotime($start_date)) ?> - <?= date('d/m', strtotime($end_date)) ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Passengers</h6>
                    <h3 class="mb-0"><?= number_format($summary['total_passengers'] ?? 0) ?></h3>
                    <small>Avg: <?= number_format($summary['avg_passengers'] ?? 0, 1) ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Total Revenue</h6>
                    <h3 class="mb-0">R <?= number_format($summary['total_revenue'] ?? 0, 2) ?></h3>
                    <small>Gross income</small>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Association Levy</h6>
                    <h3 class="mb-0">R <?= number_format($summary['total_levy'] ?? 0, 2) ?></h3>
                    <small><?= safePercentage($summary['total_levy'] ?? 0, $summary['total_revenue'] ?? 1) ?>% of revenue</small>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Owner Payout</h6>
                    <h3 class="mb-0">R <?= number_format($summary['total_owner_payout'] ?? 0, 2) ?></h3>
                    <small>Net after levy</small>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card bg-dark text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Active</h6>
                    <h3 class="mb-0"><?= $summary['unique_taxis'] ?? 0 ?> Taxis</h3>
                    <h3 class="mb-0"><?= $summary['unique_drivers'] ?? 0 ?> Drivers</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row - Only show if there is data -->
    <?php if (!empty($hourly) || !empty($route_breakdown)): ?>
    <div class="row mb-4">
        <?php if (!empty($hourly)): ?>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Hourly Trip Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="hourlyChart" height="100"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($route_breakdown)): ?>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Route Breakdown</h5>
                </div>
                <div class="card-body">
                    <canvas id="routeChart" height="200"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- No Data Warning -->
    <?php if (empty($trips)): ?>
        <div class="alert alert-warning text-center py-5 mb-4">
            <i class="bi bi-inbox fs-1 d-block mb-3"></i>
            <h4>No Trips Found</h4>
            <p class="mb-3">There are no trips recorded for the selected period.</p>
            <p class="text-muted">Try selecting a different date range or check back later.</p>
        </div>
    <?php endif; ?>

    <!-- Tabs for different views - Only show if there are trips -->
    <?php if (!empty($trips)): ?>
    <ul class="nav nav-tabs mb-4" id="tripTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="list-tab" data-bs-toggle="tab" data-bs-target="#list" type="button" role="tab">
                <i class="bi bi-list"></i> Trip List
            </button>
        </li>
        <?php if (!empty($route_breakdown)): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="routes-tab" data-bs-toggle="tab" data-bs-target="#routes" type="button" role="tab">
                <i class="bi bi-signpost"></i> Route Summary
            </button>
        </li>
        <?php endif; ?>
        <?php if (!empty($top_drivers)): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="drivers-tab" data-bs-toggle="tab" data-bs-target="#drivers" type="button" role="tab">
                <i class="bi bi-people"></i> Top Drivers
            </button>
        </li>
        <?php endif; ?>
    </ul>

    <div class="tab-content" id="tripTabsContent">
        <!-- Trip List Tab -->
        <div class="tab-pane fade show active" id="list" role="tabpanel">
            <div class="card">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-list"></i> Trip List</h5>
                    <div>
                        <input type="text" class="form-control form-control-sm" placeholder="Search trips..." id="searchInput" style="width: 250px;">
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="tripsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Time</th>
                                    <th>Taxi</th>
                                    <th>Driver</th>
                                    <th>Owner</th>
                                    <th>Route</th>
                                    <th>Pass</th>
                                    <th>Fare</th>
                                    <th>Levy</th>
                                    <th>Payout</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trips as $trip): ?>
                                <tr>
                                    <td>
                                        <strong><?= date('H:i', strtotime($trip['departed_at'])) ?></strong>
                                        <br>
                                        <small class="text-muted"><?= date('d/m', strtotime($trip['departed_at'])) ?></small>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($trip['registration_number']) ?></strong>
                                    </td>
                                    <td>
                                        <i class="bi bi-person"></i> <?= htmlspecialchars($trip['driver_name']) ?>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars($trip['owner_name']) ?></small>
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
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer text-muted">
                    <i class="bi bi-info-circle"></i> Showing <?= count($trips) ?> trips
                </div>
            </div>
        </div>

        <!-- Route Summary Tab -->
        <?php if (!empty($route_breakdown)): ?>
        <div class="tab-pane fade" id="routes" role="tabpanel">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-signpost"></i> Route Performance Summary</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Route</th>
                                    <th>Trips</th>
                                    <th>Passengers</th>
                                    <th>Revenue</th>
                                    <th>Levy</th>
                                    <th>Avg Passengers</th>
                                    <th>Revenue/Trip</th>
                                    <th>% of Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_route_revenue = array_sum(array_column($route_breakdown, 'revenue'));
                                foreach ($route_breakdown as $route): 
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($route['route_name']) ?></strong></td>
                                    <td><?= $route['trips'] ?></td>
                                    <td><?= $route['passengers'] ?></td>
                                    <td>R <?= number_format($route['revenue'], 2) ?></td>
                                    <td>R <?= number_format($route['levy'], 2) ?></td>
                                    <td><?= number_format($route['avg_passengers'], 1) ?></td>
                                    <td>R <?= safeDivide($route['revenue'], $route['trips'], 2) ?></td>
                                    <td><?= safePercentage($route['revenue'], $total_route_revenue, 1) ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Top Drivers Tab -->
        <?php if (!empty($top_drivers)): ?>
        <div class="tab-pane fade" id="drivers" role="tabpanel">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-trophy"></i> Top Performing Drivers</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($top_drivers as $index => $driver): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card driver-card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <span class="badge bg-<?= $index == 0 ? 'warning' : ($index == 1 ? 'secondary' : 'primary') ?> rounded-circle p-3">
                                                <i class="bi bi-trophy fs-4"></i>
                                            </span>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h5 class="mb-1"><?= htmlspecialchars($driver['driver_name']) ?></h5>
                                            <p class="mb-0">
                                                <?= $driver['trips'] ?> trips • 
                                                <?= $driver['passengers'] ?> passengers
                                            </p>
                                            <small class="text-success">
                                                R <?= number_format($driver['revenue'], 2) ?> revenue
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
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
                <!-- Loaded via AJAX -->
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
                <!-- Loaded via AJAX -->
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

// Search functionality
document.getElementById('searchInput')?.addEventListener('keyup', function() {
    const searchText = this.value.toLowerCase();
    const table = document.getElementById('tripsTable');
    if (!table) return;
    
    const rows = table.getElementsByTagName('tr');
    
    for (let i = 1; i < rows.length; i++) {
        const row = rows[i];
        const taxi = row.cells[1]?.textContent.toLowerCase() || '';
        const driver = row.cells[2]?.textContent.toLowerCase() || '';
        const route = row.cells[4]?.textContent.toLowerCase() || '';
        
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
        });
}

// Print receipt
function printReceipt(id) {
    fetch(`get_receipt.php?id=${id}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('receiptContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('receiptModal')).show();
        });
}

// Export trips
function exportTrips() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const route = document.getElementById('route').value;
    
    // Show message that export is coming soon
    alert('Export feature will be available in the next update!');
    // window.location.href = `export/export_trips.php?start_date=${startDate}&end_date=${endDate}&route=${route}`;
}

// Print report
function printReport() {
    window.print();
}

// Refresh data
function refreshData() {
    window.location.reload();
}

<?php if (!empty($hourly)): ?>
// Hourly Chart
const hourlyCtx = document.getElementById('hourlyChart')?.getContext('2d');
if (hourlyCtx) {
    const hours = Array.from({length: 24}, (_, i) => i);
    const tripData = Array(24).fill(0);
    
    <?php foreach ($hourly as $h): ?>
    tripData[<?= $h['hour'] ?>] = <?= $h['trips'] ?>;
    <?php endforeach; ?>
    
    new Chart(hourlyCtx, {
        type: 'bar',
        data: {
            labels: hours.map(h => h.toString().padStart(2, '0') + ':00'),
            datasets: [{
                label: 'Number of Trips',
                data: tripData,
                backgroundColor: 'rgba(54, 162, 235, 0.7)',
                borderColor: 'rgb(54, 162, 235)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}
<?php endif; ?>

<?php if (!empty($route_breakdown)): ?>
// Route Chart
const routeCtx = document.getElementById('routeChart')?.getContext('2d');
if (routeCtx) {
    new Chart(routeCtx, {
        type: 'doughnut',
        data: {
            labels: [<?php 
                $labels = array_column($route_breakdown, 'route_name');
                echo "'" . implode("','", array_map('htmlspecialchars', $labels)) . "'";
            ?>],
            datasets: [{
                data: [<?php 
                    $values = array_column($route_breakdown, 'trips');
                    echo implode(',', $values);
                ?>],
                backgroundColor: [
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 206, 86, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(153, 102, 255, 0.7)',
                    'rgba(255, 159, 64, 0.7)'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
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
.driver-card {
    transition: transform 0.2s;
}
.driver-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.nav-tabs .nav-link {
    font-weight: 600;
    color: #495057;
}
.nav-tabs .nav-link.active {
    color: #007bff;
    border-bottom: 3px solid #007bff;
}
.alert {
    border-left: 4px solid #ffc107;
}
</style>

<?php
// Get the content
$content = ob_get_clean();

// Include the marshal layout
require_once '../layouts/marshal_layout.php';
?>