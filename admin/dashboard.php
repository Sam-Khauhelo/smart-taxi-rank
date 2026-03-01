<?php
// admin/dashboard.php
// Set page title
$page_title = 'Admin Dashboard';

// Start output buffering
ob_start();

// Include database connection
require_once '../config/db.php';

// Get statistics for dashboard
try {
    // Total users count by role
    $users_stats = $pdo->query("
        SELECT 
            SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as total_admins,
            SUM(CASE WHEN role = 'marshal' THEN 1 ELSE 0 END) as total_marshals,
            SUM(CASE WHEN role = 'owner' THEN 1 ELSE 0 END) as total_owners,
            SUM(CASE WHEN role = 'driver' THEN 1 ELSE 0 END) as total_drivers,
            COUNT(*) as total_users
        FROM users 
        WHERE is_active = 1
    ")->fetch();

    // Total taxis
    $taxis_count = $pdo->query("SELECT COUNT(*) as total FROM taxis WHERE status != 'off_rank'")->fetch()['total'];
    
    // Active routes
    $routes_count = $pdo->query("SELECT COUNT(*) as total FROM routes WHERE is_active = 1")->fetch()['total'];
    
    // Today's trips
    $today_trips = $pdo->query("
        SELECT 
            COUNT(*) as total_trips,
            SUM(passenger_count) as total_passengers,
            SUM(total_fare) as total_revenue
        FROM trips 
        WHERE DATE(departed_at) = CURDATE()
    ")->fetch();
    
    // Queue status
    $queue_status = $pdo->query("
        SELECT 
            COUNT(*) as waiting_taxis,
            SUM(CASE WHEN status = 'loading' THEN 1 ELSE 0 END) as loading_taxis
        FROM queue 
        WHERE status IN ('waiting', 'loading')
    ")->fetch();
    
    // Failed registration attempts count (last 24 hours)
    $failed_attempts = $pdo->query("
        SELECT 
            COUNT(*) as total_failed,
            COUNT(DISTINCT ip_address) as unique_ips
        FROM failed_registration_attempts 
        WHERE attempt_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ")->fetch();
    
    // RANK MANAGEMENT STATS
    // Total ranks
    $ranks_count = $pdo->query("SELECT COUNT(*) as total FROM ranks")->fetch()['total'] ?? 0;
    
    // Marshals on duty today
    $marshals_today = $pdo->query("
        SELECT COUNT(*) as total FROM marshal_shifts 
        WHERE shift_date = CURDATE() AND status = 'active'
    ")->fetch()['total'] ?? 0;
    
    // Special events today
    $events_today = $pdo->query("
        SELECT COUNT(*) as total FROM special_events 
        WHERE event_date = CURDATE() AND status = 'upcoming'
    ")->fetch()['total'] ?? 0;
    
    // Weather condition (example - you'd get this from weather_log)
    $weather = $pdo->query("
        SELECT weather_condition, is_raining FROM weather_log 
        ORDER BY recorded_at DESC LIMIT 1
    ")->fetch();
    
    // ROUTE OPTIMIZATION STATS
    // Peak hour performance
    $peak_hours = $pdo->query("
        SELECT HOUR(departed_at) as hour, COUNT(*) as trip_count
        FROM trips
        WHERE departed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY HOUR(departed_at)
        ORDER BY trip_count DESC
        LIMIT 3
    ")->fetchAll();
    
    // Underperforming routes (less than 5 trips today)
    $underperforming = $pdo->query("
        SELECT COUNT(*) as total FROM routes r
        LEFT JOIN trips t ON r.id = t.route_id AND DATE(t.departed_at) = CURDATE()
        WHERE t.id IS NULL
    ")->fetch()['total'] ?? 0;
    
    // Profitable routes (top 3 by revenue)
    $profitable_routes = $pdo->query("
        SELECT r.route_name, SUM(t.total_fare) as revenue
        FROM trips t
        JOIN routes r ON t.route_id = r.id
        WHERE t.departed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY r.id, r.route_name
        ORDER BY revenue DESC
        LIMIT 3
    ")->fetchAll();
    
    // Fare change recommendations count
    $fare_recommendations = $pdo->query("
        SELECT COUNT(*) as total FROM fare_recommendations 
        WHERE implemented_at IS NULL
    ")->fetch()['total'] ?? 0;
    
    // Recent activities
    $recent_trips = $pdo->query("
        SELECT t.*, tx.registration_number, r.route_name, u.full_name as driver_name
        FROM trips t
        JOIN taxis tx ON t.taxi_id = tx.id
        JOIN routes r ON t.route_id = r.id
        JOIN drivers d ON t.driver_id = d.id
        JOIN users u ON d.user_id = u.id
        ORDER BY t.departed_at DESC
        LIMIT 10
    ")->fetchAll();
    
    // Top routes today
    $top_routes = $pdo->query("
        SELECT r.route_name, COUNT(*) as trip_count, SUM(t.total_fare) as revenue
        FROM trips t
        JOIN routes r ON t.route_id = r.id
        WHERE DATE(t.departed_at) = CURDATE()
        GROUP BY r.id, r.route_name
        ORDER BY trip_count DESC
        LIMIT 5
    ")->fetchAll();
    
    // Weekly earnings
    $weekly_earnings = $pdo->query("
        SELECT 
            DATE(departed_at) as date,
            SUM(total_fare) as daily_revenue,
            COUNT(*) as daily_trips
        FROM trips
        WHERE departed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(departed_at)
        ORDER BY date DESC
    ")->fetchAll();
    
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $users_stats = ['total_users' => 0, 'total_admins' => 0, 'total_marshals' => 0, 'total_owners' => 0, 'total_drivers' => 0];
    $taxis_count = 0;
    $routes_count = 0;
    $today_trips = ['total_trips' => 0, 'total_passengers' => 0, 'total_revenue' => 0];
    $queue_status = ['waiting_taxis' => 0, 'loading_taxis' => 0];
    $failed_attempts = ['total_failed' => 0, 'unique_ips' => 0];
    $ranks_count = 0;
    $marshals_today = 0;
    $events_today = 0;
    $underperforming = 0;
    $fare_recommendations = 0;
    $profitable_routes = [];
    $peak_hours = [];
    $recent_trips = [];
    $top_routes = [];
    $weekly_earnings = [];
}
?>

<!-- Statistics Cards Row -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-white-50 mb-2">Total Users</h6>
                        <h2 class="mb-0"><?= number_format($users_stats['total_users'] ?? 0) ?></h2>
                    </div>
                    <div>
                        <i class="bi bi-people fs-1 opacity-50"></i>
                    </div>
                </div>
                <div class="mt-3 small">
                    <span class="me-2"><i class="bi bi-shield"></i> <?= $users_stats['total_admins'] ?? 0 ?> Admins</span>
                    <span class="me-2"><i class="bi bi-briefcase"></i> <?= $users_stats['total_owners'] ?? 0 ?> Owners</span>
                    <span><i class="bi bi-person-badge"></i> <?= $users_stats['total_drivers'] ?? 0 ?> Drivers</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-white-50 mb-2">Fleet Status</h6>
                        <h2 class="mb-0"><?= number_format($taxis_count) ?></h2>
                    </div>
                    <div>
                        <i class="bi bi-truck fs-1 opacity-50"></i>
                    </div>
                </div>
                <div class="mt-3 small">
                    <span class="me-2"><i class="bi bi-signpost"></i> <?= $routes_count ?> Routes</span>
                    <span><i class="bi bi-clock"></i> <?= $queue_status['waiting_taxis'] ?? 0 ?> Waiting</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card bg-warning text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-white-50 mb-2">Today's Trips</h6>
                        <h2 class="mb-0"><?= number_format($today_trips['total_trips'] ?? 0) ?></h2>
                    </div>
                    <div>
                        <i class="bi bi-clock-history fs-1 opacity-50"></i>
                    </div>
                </div>
                <div class="mt-3 small">
                    <span class="me-2"><i class="bi bi-people"></i> <?= number_format($today_trips['total_passengers'] ?? 0) ?> Passengers</span>
                    <span><i class="bi bi-cash"></i> R <?= number_format($today_trips['total_revenue'] ?? 0, 2) ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card bg-info text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-white-50 mb-2">Queue Status</h6>
                        <h2 class="mb-0"><?= number_format($queue_status['waiting_taxis'] ?? 0) ?></h2>
                    </div>
                    <div>
                        <i class="bi bi-list-ol fs-1 opacity-50"></i>
                    </div>
                </div>
                <div class="mt-3 small">
                    <span class="me-2"><i class="bi bi-arrow-right-circle"></i> <?= $queue_status['loading_taxis'] ?? 0 ?> Loading Now</span>
                    <span><i class="bi bi-taxi-front"></i> Total in Queue</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Second Row - Rank Management Stats -->
<div class="row g-4 mb-4">
    <div class="col-xl-4">
        <div class="card border-primary">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-pin-map"></i> Rank Management</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-4">
                        <h3><?= number_format($ranks_count) ?></h3>
                        <small class="text-muted">Total Ranks</small>
                    </div>
                    <div class="col-4">
                        <h3><?= number_format($marshals_today) ?></h3>
                        <small class="text-muted">Marshals On Duty</small>
                    </div>
                    <div class="col-4">
                        <h3><?= number_format($events_today) ?></h3>
                        <small class="text-muted">Events Today</small>
                    </div>
                </div>
                
                <?php if ($weather): ?>
                <div class="mt-3 d-flex align-items-center">
                    <i class="bi bi-cloud-sun fs-4 me-2"></i>
                    <span>
                        Weather: <?= ucfirst($weather['weather_condition'] ?? 'Unknown') ?>
                        <?php if ($weather['is_raining'] ?? false): ?>
                            <span class="badge bg-info ms-2">🌧️ Rain expected</span>
                        <?php endif; ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <div class="mt-3">
                    <a href="ranks.php" class="btn btn-outline-primary btn-sm w-100">
                        <i class="bi bi-pin-map"></i> Manage Ranks
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-4">
        <div class="card border-success">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-graph-up"></i> Route Optimization</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-4">
                        <h3><?= count($peak_hours) ?></h3>
                        <small class="text-muted">Peak Hours</small>
                    </div>
                    <div class="col-4">
                        <h3><?= number_format($underperforming) ?></h3>
                        <small class="text-muted">Underperforming</small>
                    </div>
                    <div class="col-4">
                        <h3><?= number_format($fare_recommendations) ?></h3>
                        <small class="text-muted">Fare Suggestions</small>
                    </div>
                </div>
                
                <div class="mt-3">
                    <small class="text-muted">Peak Hours:</small>
                    <div class="d-flex gap-2 mt-1">
                        <?php foreach ($peak_hours as $peak): ?>
                            <span class="badge bg-warning"><?= $peak['hour'] ?>:00</span>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="mt-3">
                    <a href="route_optimization.php" class="btn btn-outline-success btn-sm w-100">
                        <i class="bi bi-graph-up"></i> Optimize Routes
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-4">
        <div class="card border-warning">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="bi bi-trophy"></i> Top Routes (7 Days)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($profitable_routes)): ?>
                    <p class="text-muted text-center py-3">No data available</p>
                <?php else: ?>
                    <?php foreach ($profitable_routes as $index => $route): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>
                                <span class="badge bg-<?= $index == 0 ? 'warning' : ($index == 1 ? 'secondary' : 'bronze') ?> me-2">
                                    #<?= $index + 1 ?>
                                </span>
                                <?= htmlspecialchars($route['route_name']) ?>
                            </span>
                            <span class="fw-bold text-success">R <?= number_format($route['revenue'], 2) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Third Row - Security and System Alerts -->
<div class="row g-4 mb-4">
    <div class="col-xl-6">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-shield-exclamation"></i> Security Monitor</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <div class="text-center">
                            <h1 class="display-4 text-danger"><?= number_format($failed_attempts['total_failed'] ?? 0) ?></h1>
                            <p class="text-muted">Failed Attempts (24h)</p>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center">
                            <h1 class="display-4 text-warning"><?= number_format($failed_attempts['unique_ips'] ?? 0) ?></h1>
                            <p class="text-muted">Unique IPs</p>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="failed_attempts.php" class="btn btn-outline-danger w-100">
                        <i class="bi bi-eye"></i> View Failed Attempts
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-6">
        <div class="card border-warning">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> System Alerts</h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php if (($failed_attempts['total_failed'] ?? 0) > 10): ?>
                    <div class="list-group-item list-group-item-danger">
                        <i class="bi bi-shield-exclamation"></i>
                        High number of failed registration attempts detected!
                    </div>
                    <?php endif; ?>
                    
                    <?php if (($queue_status['waiting_taxis'] ?? 0) > 20): ?>
                    <div class="list-group-item list-group-item-warning">
                        <i class="bi bi-clock"></i>
                        Queue is getting long (<?= $queue_status['waiting_taxis'] ?> taxis waiting)
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($underperforming > 3): ?>
                    <div class="list-group-item list-group-item-warning">
                        <i class="bi bi-graph-down"></i>
                        <?= $underperforming ?> routes have no trips today
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($fare_recommendations > 0): ?>
                    <div class="list-group-item list-group-item-info">
                        <i class="bi bi-tag"></i>
                        <?= $fare_recommendations ?> fare adjustment recommendations available
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($events_today > 0): ?>
                    <div class="list-group-item list-group-item-primary">
                        <i class="bi bi-calendar-event"></i>
                        <?= $events_today ?> special event(s) happening today
                    </div>
                    <?php endif; ?>
                    
                    <?php if (($today_trips['total_trips'] ?? 0) == 0): ?>
                    <div class="list-group-item list-group-item-info">
                        <i class="bi bi-info-circle"></i>
                        No trips recorded today yet
                    </div>
                    <?php endif; ?>
                    
                    <?php if (($queue_status['waiting_taxis'] ?? 0) == 0 && ($today_trips['total_trips'] ?? 0) > 0): ?>
                    <div class="list-group-item list-group-item-success">
                        <i class="bi bi-check-circle"></i>
                        System operating normally
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Weekly Revenue Overview</h5>
                <div>
                    <span class="badge bg-primary me-2">Last 7 Days</span>
                    <button class="btn btn-sm btn-light" onclick="refreshChart()">
                        <i class="bi bi-arrow-repeat"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <canvas id="weeklyChart" height="100"></canvas>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i>User Distribution</h5>
            </div>
            <div class="card-body">
                <canvas id="userChart" height="200"></canvas>
                <div class="mt-3 text-center">
                    <div class="row">
                        <div class="col-6">
                            <small class="text-muted">Admins</small>
                            <h6><?= $users_stats['total_admins'] ?? 0 ?></h6>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Marshals</small>
                            <h6><?= $users_stats['total_marshals'] ?? 0 ?></h6>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Owners</small>
                            <h6><?= $users_stats['total_owners'] ?? 0 ?></h6>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Drivers</small>
                            <h6><?= $users_stats['total_drivers'] ?? 0 ?></h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity and Top Routes -->
<div class="row g-4">
    <div class="col-xl-7">
        <div class="card">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Trips</h5>
                <a href="reports.php" class="btn btn-sm btn-outline-light">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Time</th>
                                <th>Taxi</th>
                                <th>Route</th>
                                <th>Driver</th>
                                <th>Pass.</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_trips)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                                        No recent trips
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_trips as $trip): ?>
                                <tr>
                                    <td><?= date('H:i', strtotime($trip['departed_at'])) ?></td>
                                    <td><strong><?= htmlspecialchars($trip['registration_number']) ?></strong></td>
                                    <td><?= htmlspecialchars($trip['route_name']) ?></td>
                                    <td><?= htmlspecialchars($trip['driver_name']) ?></td>
                                    <td><?= $trip['passenger_count'] ?></td>
                                    <td>R <?= number_format($trip['total_fare'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer text-muted small">
                <i class="bi bi-info-circle"></i> Showing last 10 trips
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-trophy me-2"></i>Top Routes Today</h5>
            </div>
            <div class="card-body">
                <?php if (empty($top_routes)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-signpost fs-1 d-block mb-2"></i>
                        No trips recorded today
                    </div>
                <?php else: ?>
                    <?php foreach ($top_routes as $index => $route): ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="badge bg-<?= $index == 0 ? 'warning' : ($index == 1 ? 'secondary' : 'primary') ?> me-2">
                                #<?= $index + 1 ?>
                            </span>
                            <strong><?= htmlspecialchars($route['route_name']) ?></strong>
                        </div>
                        <div>
                            <span class="badge bg-info me-2"><?= $route['trip_count'] ?> trips</span>
                            <span class="text-success">R <?= number_format($route['revenue'], 2) ?></span>
                        </div>
                    </div>
                    <?php if ($index < count($top_routes) - 1): ?>
                        <hr class="my-2">
                    <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions Card -->
        <div class="card mt-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-lightning-charge me-2"></i>Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-6">
                        <a href="register_user.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-person-plus"></i> New User
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="manage_taxis.php" class="btn btn-outline-success w-100">
                            <i class="bi bi-truck"></i> Add Taxi
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="manage_routes.php" class="btn btn-outline-warning w-100">
                            <i class="bi bi-signpost"></i> New Route
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="ranks.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-pin-map"></i> Manage Ranks
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="route_optimization.php" class="btn btn-outline-success w-100">
                            <i class="bi bi-graph-up"></i> Route Optimization
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="failed_attempts.php" class="btn btn-outline-danger w-100">
                            <i class="bi bi-shield-exclamation"></i> Failed Attempts
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Chart.js for graphs -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Weekly Revenue Chart
const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
const weeklyChart = new Chart(weeklyCtx, {
    type: 'line',
    data: {
        labels: [<?php 
            $dates = array_reverse(array_column($weekly_earnings, 'date'));
            echo "'" . implode("','", array_map(function($d) {
                return date('D', strtotime($d));
            }, $dates)) . "'";
        ?>],
        datasets: [{
            label: 'Revenue (R)',
            data: [<?php 
                $revenues = array_reverse(array_column($weekly_earnings, 'daily_revenue'));
                echo implode(',', array_map(function($v) { 
                    return $v ?? 0; 
                }, $revenues));
            ?>],
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.4,
            fill: true
        }, {
            label: 'Number of Trips',
            data: [<?php 
                $trips = array_reverse(array_column($weekly_earnings, 'daily_trips'));
                echo implode(',', array_map(function($v) { 
                    return $v ?? 0; 
                }, $trips));
            ?>],
            borderColor: 'rgb(255, 159, 64)',
            backgroundColor: 'rgba(255, 159, 64, 0.2)',
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
                    text: 'Revenue (R)'
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
                    text: 'Number of Trips'
                }
            }
        }
    }
});

// User Distribution Pie Chart
const userCtx = document.getElementById('userChart').getContext('2d');
const userChart = new Chart(userCtx, {
    type: 'doughnut',
    data: {
        labels: ['Admins', 'Marshals', 'Owners', 'Drivers'],
        datasets: [{
            data: [
                <?= $users_stats['total_admins'] ?? 0 ?>,
                <?= $users_stats['total_marshals'] ?? 0 ?>,
                <?= $users_stats['total_owners'] ?? 0 ?>,
                <?= $users_stats['total_drivers'] ?? 0 ?>
            ],
            backgroundColor: [
                'rgb(255, 99, 132)',
                'rgb(54, 162, 235)',
                'rgb(255, 205, 86)',
                'rgb(75, 192, 192)'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom',
            }
        }
    }
});

// Refresh chart function
function refreshChart() {
    window.location.reload();
}

// Auto-refresh dashboard every 60 seconds
setTimeout(function() {
    window.location.reload();
}, 60000);
</script>

<!-- Custom CSS for dashboard -->
<style>
.card {
    border: none;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    transition: transform 0.2s;
}
.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.bg-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.bg-success { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
.bg-warning { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
.bg-info { background: linear-gradient(135deg, #3c8ce7 0%, #00eaff 100%); }
.table th { border-top: none; }
.table td { vertical-align: middle; }
.badge { padding: 5px 10px; }
.display-4 { font-size: 2.5rem; }
.bg-bronze { background: #cd7f32; color: white; }
</style>

<?php
// Get the content
$content = ob_get_clean();

// Include the admin layout
require_once '../layouts/admin_layout.php';
?>