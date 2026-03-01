<?php
// driver/my_trips.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('driver');

// Set page title
$page_title = 'My Trips';

// Start output buffering
ob_start();

$driver_id = $_SESSION['driver_id'];
$taxi_id = $_SESSION['taxi_id'];

// Get date range from request or default to current month
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$view_type = $_GET['view'] ?? 'list';

try {
    // Get driver's trips with details
    $trips_sql = "SELECT t.*, 
                         r.route_name, 
                         r.fare_amount as route_fare,
                         tx.registration_number,
                         TIME(t.departed_at) as trip_time,
                         DATE(t.departed_at) as trip_date
                  FROM trips t
                  JOIN routes r ON t.route_id = r.id
                  JOIN taxis tx ON t.taxi_id = tx.id
                  WHERE t.driver_id = :driver_id
                  AND DATE(t.departed_at) BETWEEN :start_date AND :end_date
                  ORDER BY t.departed_at DESC";
    
    $trips_stmt = $pdo->prepare($trips_sql);
    $trips_stmt->execute([
        ':driver_id' => $driver_id,
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    $trips = $trips_stmt->fetchAll();
    
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
    
    // Daily breakdown
    $daily_sql = "SELECT 
                        DATE(departed_at) as trip_date,
                        COUNT(*) as trips,
                        SUM(passenger_count) as passengers,
                        SUM(total_fare) as revenue,
                        SUM(association_levy) as levy,
                        SUM(owner_payout) as owner_payout
                  FROM trips 
                  WHERE driver_id = :driver_id
                  AND DATE(departed_at) BETWEEN :start_date AND :end_date
                  GROUP BY DATE(departed_at)
                  ORDER BY trip_date DESC";
    
    $daily_stmt = $pdo->prepare($daily_sql);
    $daily_stmt->execute([
        ':driver_id' => $driver_id,
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    $daily = $daily_stmt->fetchAll();
    
    // Hourly distribution
    $hourly_sql = "SELECT 
                        HOUR(departed_at) as hour,
                        COUNT(*) as trips,
                        SUM(passenger_count) as passengers
                   FROM trips 
                   WHERE driver_id = :driver_id
                   AND DATE(departed_at) BETWEEN :start_date AND :end_date
                   GROUP BY HOUR(departed_at)
                   ORDER BY hour";
    
    $hourly_stmt = $pdo->prepare($hourly_sql);
    $hourly_stmt->execute([
        ':driver_id' => $driver_id,
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    $hourly = $hourly_stmt->fetchAll();
    
    // Route breakdown
    $route_sql = "SELECT 
                        r.route_name,
                        COUNT(*) as trips,
                        SUM(t.passenger_count) as passengers,
                        SUM(t.total_fare) as revenue,
                        AVG(t.passenger_count) as avg_passengers
                  FROM trips t
                  JOIN routes r ON t.route_id = r.id
                  WHERE t.driver_id = :driver_id
                  AND DATE(t.departed_at) BETWEEN :start_date AND :end_date
                  GROUP BY r.id, r.route_name
                  ORDER BY trips DESC";
    
    $route_stmt = $pdo->prepare($route_sql);
    $route_stmt->execute([
        ':driver_id' => $driver_id,
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    $route_stats = $route_stmt->fetchAll();
    
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
    
} catch (PDOException $e) {
    error_log("My trips error: " . $e->getMessage());
    $trips = [];
    $summary = [];
    $daily = [];
    $hourly = [];
    $route_stats = [];
    $driver_info = [];
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-clock-history"></i> My Trips</h2>
        <div>
            <button class="btn btn-success" onclick="exportTrips()">
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
            <h5 class="mb-0"><i class="bi bi-calendar-range"></i> Filter Trips</h5>
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
                        <option value="list" <?= $view_type == 'list' ? 'selected' : '' ?>>List View</option>
                        <option value="daily" <?= $view_type == 'daily' ? 'selected' : '' ?>>Daily Summary</option>
                        <option value="stats" <?= $view_type == 'stats' ? 'selected' : '' ?>>Statistics</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Apply Filter
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="setToday()">Today</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="setThisWeek()">This Week</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="setThisMonth()">This Month</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="setLastMonth()">Last Month</button>
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
                    <h6 class="text-white-50">Total Fare</h6>
                    <h3 class="mb-0">R <?= number_format($summary['total_revenue'] ?? 0, 2) ?></h3>
                    <small>Avg: R <?= number_format($summary['avg_fare'] ?? 0, 2) ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Association Levy</h6>
                    <h3 class="mb-0">R <?= number_format($summary['total_levy'] ?? 0, 2) ?></h3>
                    <small><?= $summary['total_revenue'] ? round(($summary['total_levy'] / $summary['total_revenue']) * 100, 1) : 0 ?>%</small>
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
                    <h6 class="text-white-50">Days Worked</h6>
                    <h3 class="mb-0"><?= $summary['days_worked'] ?? 0 ?></h3>
                    <small>Out of <?= ceil((strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24)) + 1 ?> days</small>
                </div>
            </div>
        </div>
    </div>

    <?php if ($view_type == 'list'): ?>
        <!-- List View -->
        <div class="card">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list"></i> Trip List</h5>
                <div>
                    <input type="text" class="form-control form-control-sm" placeholder="Search trips..." id="searchInput" style="width: 250px;">
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="tripsTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Taxi</th>
                                <th>Route</th>
                                <th>Passengers</th>
                                <th>Fare</th>
                                <th>Levy</th>
                                <th>Owner Payout</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($trips)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <i class="bi bi-clock-history fs-1 text-muted d-block mb-3"></i>
                                        <h5 class="text-muted">No Trips Found</h5>
                                        <p class="text-muted">No trips recorded for the selected period.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($trips as $trip): ?>
                                <tr>
                                    <td><strong><?= date('d M Y', strtotime($trip['trip_date'])) ?></strong></td>
                                    <td><?= date('H:i', strtotime($trip['departed_at'])) ?></td>
                                    <td><?= htmlspecialchars($trip['registration_number']) ?></td>
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
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif ($view_type == 'daily'): ?>
        <!-- Daily Summary View -->
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-calendar-day"></i> Daily Summary</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Trips</th>
                                <th>Passengers</th>
                                <th>Total Fare</th>
                                <th>Levy</th>
                                <th>Owner Payout</th>
                                <th>Avg Passengers</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($daily)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                        <h5 class="text-muted">No daily data found</h5>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($daily as $day): ?>
                                <tr>
                                    <td><strong><?= date('d M Y', strtotime($day['trip_date'])) ?></strong></td>
                                    <td><?= $day['trips'] ?></td>
                                    <td><?= $day['passengers'] ?></td>
                                    <td>R <?= number_format($day['revenue'], 2) ?></td>
                                    <td>R <?= number_format($day['levy'], 2) ?></td>
                                    <td class="fw-bold text-success">R <?= number_format($day['owner_payout'], 2) ?></td>
                                    <td><?= $day['trips'] ? round($day['passengers'] / $day['trips'], 1) : 0 ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info" onclick="viewDay('<?= $day['trip_date'] ?>')">
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

    <?php elseif ($view_type == 'stats'): ?>
        <!-- Statistics View -->
        <div class="row">
            <div class="col-md-6">
                <!-- Hourly Distribution Chart -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-clock"></i> Hourly Trip Distribution</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="hourlyChart" height="250"></canvas>
                    </div>
                </div>
                
                <!-- Route Statistics -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-signpost"></i> Route Performance</h5>
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
                                        <th>Avg Pass</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($route_stats)): ?>
                                        <tr><td colspan="5" class="text-center">No data</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($route_stats as $route): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($route['route_name']) ?></td>
                                            <td><?= $route['trips'] ?></td>
                                            <td><?= $route['passengers'] ?></td>
                                            <td>R <?= number_format($route['revenue'], 2) ?></td>
                                            <td><?= round($route['avg_passengers'], 1) ?></td>
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
                <!-- Earnings Chart -->
                <div class="card mb-4">
                    <div class="card-header bg-warning text-white">
                        <h5 class="mb-0"><i class="bi bi-graph-up"></i> Daily Earnings</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="earningsChart" height="250"></canvas>
                    </div>
                </div>
                
                <!-- Driver Payment Info -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-info-circle"></i> Payment Information</h5>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <tr>
                                <th>Owner:</th>
                                <td><?= htmlspecialchars($driver_info['owner_name'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <th>Employment Type:</th>
                                <td>
                                    <span class="badge bg-primary"><?= ucfirst($driver_info['employment_type'] ?? 'N/A') ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th>Payment Rate:</th>
                                <td>
                                    <?php if ($driver_info['employment_type'] == 'commission'): ?>
                                        <strong><?= $driver_info['payment_rate'] ?>%</strong> of owner payout
                                    <?php elseif ($driver_info['employment_type'] == 'wage'): ?>
                                        <strong>R <?= number_format($driver_info['payment_rate'] ?? 0, 2) ?></strong> per day
                                    <?php elseif ($driver_info['employment_type'] == 'rental'): ?>
                                        <strong>R <?= number_format($driver_info['payment_rate'] ?? 0, 2) ?></strong> per day (rental)
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Your Estimated Earnings:</th>
                                <td>
                                    <?php
                                    $estimated_earnings = 0;
                                    if ($driver_info['employment_type'] == 'commission') {
                                        $estimated_earnings = ($summary['total_owner_payout'] ?? 0) * ($driver_info['payment_rate'] / 100);
                                    } elseif ($driver_info['employment_type'] == 'wage' || $driver_info['employment_type'] == 'rental') {
                                        $estimated_earnings = ($summary['days_worked'] ?? 0) * ($driver_info['payment_rate'] ?? 0);
                                    }
                                    ?>
                                    <h4 class="text-success mb-0">R <?= number_format($estimated_earnings, 2) ?></h4>
                                    <small class="text-muted">Based on selected period</small>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- View Trip Modal -->
<div class="modal fade" id="viewTripModal" tabindex="-1">
    <div class="modal-dialog">
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
            </div>
        </div>
    </div>
</div>

<!-- Day Trips Modal -->
<div class="modal fade" id="dayTripsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-calendar-day"></i> <span id="dayModalTitle"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="dayTripsDetails">
                <!-- Loaded via AJAX -->
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
// Date shortcuts
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

// View single trip
function viewTrip(id) {
    const modal = new bootstrap.Modal(document.getElementById('viewTripModal'));
    
    fetch(`get_trip.php?id=${id}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('tripDetails').innerHTML = html;
            modal.show();
        });
}

// View day trips
function viewDay(date) {
    const modal = new bootstrap.Modal(document.getElementById('dayTripsModal'));
    document.getElementById('dayModalTitle').textContent = `Trips for ${new Date(date).toLocaleDateString('en-ZA', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}`;
    
    fetch(`get_day_trips.php?date=${date}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('dayTripsDetails').innerHTML = html;
            modal.show();
        });
}

// Export trips
function exportTrips() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const view = document.getElementById('view').value;
    
    window.location.href = `export/export_trips.php?start_date=${startDate}&end_date=${endDate}&view=${view}`;
}

// Print report
function printReport() {
    window.print();
}

// Search functionality
document.getElementById('searchInput')?.addEventListener('keyup', function() {
    const searchText = this.value.toLowerCase();
    const table = document.getElementById('tripsTable');
    if (!table) return;
    
    const rows = table.getElementsByTagName('tr');
    
    for (let i = 1; i < rows.length; i++) {
        const row = rows[i];
        const date = row.cells[0]?.textContent.toLowerCase() || '';
        const taxi = row.cells[2]?.textContent.toLowerCase() || '';
        const route = row.cells[3]?.textContent.toLowerCase() || '';
        
        if (date.includes(searchText) || taxi.includes(searchText) || route.includes(searchText)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    }
});

<?php if ($view_type == 'stats'): ?>
// Hourly Chart
const hourlyCtx = document.getElementById('hourlyChart')?.getContext('2d');
if (hourlyCtx) {
    new Chart(hourlyCtx, {
        type: 'bar',
        data: {
            labels: [<?php 
                $hours = array_fill(0, 24, 0);
                foreach ($hourly as $h) {
                    $hours[$h['hour']] = $h['trips'];
                }
                $labels = [];
                for ($i = 0; $i < 24; $i++) {
                    $labels[] = "'" . sprintf("%02d:00", $i) . "'";
                }
                echo implode(',', $labels);
            ?>],
            datasets: [{
                label: 'Trips',
                data: [<?php echo implode(',', $hours); ?>],
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

// Earnings Chart
const earningsCtx = document.getElementById('earningsChart')?.getContext('2d');
if (earningsCtx) {
    new Chart(earningsCtx, {
        type: 'line',
        data: {
            labels: [<?php 
                $dates = array_reverse(array_column($daily, 'trip_date'));
                echo "'" . implode("','", array_map(function($d) {
                    return date('d M', strtotime($d));
                }, $dates)) . "'";
            ?>],
            datasets: [{
                label: 'Owner Payout',
                data: [<?php 
                    $payouts = array_reverse(array_column($daily, 'owner_payout'));
                    echo implode(',', array_map(function($v) { return $v ?? 0; }, $payouts));
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
.table td {
    vertical-align: middle;
}
.badge {
    font-size: 11px;
    padding: 5px 8px;
}
.btn-outline-secondary {
    margin-right: 5px;
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