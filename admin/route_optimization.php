<?php
// admin/route_optimization.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('admin');

$page_title = 'Route Optimization';
ob_start();

// Get today's date
$today = date('Y-m-d');

// Get all routes with performance data
$routes = $pdo->query("
    SELECT r.*, 
           COUNT(t.id) as total_trips,
           COALESCE(SUM(t.passenger_count), 0) as total_passengers,
           COALESCE(SUM(t.total_fare), 0) as total_revenue,
           COALESCE(AVG(t.passenger_count), 0) as avg_passengers
    FROM routes r
    LEFT JOIN trips t ON r.id = t.route_id AND DATE(t.departed_at) = CURDATE()
    GROUP BY r.id
    ORDER BY total_trips DESC
")->fetchAll();

// Get peak hours data
$peak_hours = $pdo->query("
    SELECT 
        HOUR(departed_at) as hour,
        COUNT(*) as trips,
        AVG(passenger_count) as avg_passengers
    FROM trips
    WHERE departed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY HOUR(departed_at)
    ORDER BY trips DESC
    LIMIT 5
")->fetchAll();
?>

<div class="container-fluid">
    <h2 class="mb-4"><i class="bi bi-graph-up"></i> Route Optimization</h2>
    
    <!-- Peak Hours Card -->
    <div class="card mb-4">
        <div class="card-header bg-warning">
            <h5 class="mb-0"><i class="bi bi-clock"></i> Peak Hours (Last 30 Days)</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($peak_hours as $peak): ?>
                <div class="col-md-2">
                    <div class="text-center border p-3">
                        <h3><?= $peak['hour'] ?>:00 - <?= $peak['hour']+1 ?>:00</h3>
                        <p class="mb-0"><?= $peak['trips'] ?> trips</p>
                        <small>Avg <?= round($peak['avg_passengers']) ?> passengers</small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-3 alert alert-info">
                <i class="bi bi-lightbulb"></i>
                <strong>Suggestion:</strong> Add <?= count($peak_hours) ?> additional taxis during these peak hours.
            </div>
        </div>
    </div>
    
    <!-- Route Performance Table -->
    <div class="card mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="bi bi-signpost"></i> Route Performance Today</h5>
        </div>
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Route</th>
                        <th>Fare</th>
                        <th>Trips Today</th>
                        <th>Passengers</th>
                        <th>Revenue</th>
                        <th>Avg Passengers</th>
                        <th>Performance</th>
                        <th>Recommendation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($routes as $route): 
                        $performance = 'average';
                        $color = 'warning';
                        
                        if ($route['total_trips'] > 20) {
                            $performance = 'Excellent';
                            $color = 'success';
                        } elseif ($route['total_trips'] > 10) {
                            $performance = 'Good';
                            $color = 'primary';
                        } elseif ($route['total_trips'] == 0) {
                            $performance = 'Poor';
                            $color = 'danger';
                        }
                        
                        // Generate recommendation
                        $recommendation = '';
                        if ($route['total_trips'] > 20) {
                            $recommendation = 'Route performing well. Consider adding more taxis.';
                        } elseif ($route['total_trips'] < 5 && $route['total_trips'] > 0) {
                            $recommendation = 'Low usage. Consider reducing frequency or reviewing fare.';
                        } elseif ($route['avg_passengers'] < 10) {
                            $recommendation = 'Low passenger count. Consider marketing this route.';
                        } else {
                            $recommendation = 'Normal operation. Monitor regularly.';
                        }
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($route['route_name']) ?></strong></td>
                        <td>R <?= number_format($route['fare_amount'], 2) ?></td>
                        <td><?= $route['total_trips'] ?></td>
                        <td><?= $route['total_passengers'] ?></td>
                        <td>R <?= number_format($route['total_revenue'], 2) ?></td>
                        <td><?= round($route['avg_passengers'], 1) ?></td>
                        <td><span class="badge bg-<?= $color ?>"><?= $performance ?></span></td>
                        <td><small><?= $recommendation ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Profitability Analysis -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-cash"></i> Most Profitable Routes</h5>
                </div>
                <div class="card-body">
                    <canvas id="profitChart" height="300"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-people"></i> Passenger Predictions</h5>
                </div>
                <div class="card-body">
                    <canvas id="predictionChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Fare Adjustment Recommendations -->
    <div class="card mt-4">
        <div class="card-header bg-warning">
            <h5 class="mb-0"><i class="bi bi-tag"></i> Fare Adjustment Recommendations</h5>
        </div>
        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>Route</th>
                        <th>Current Fare</th>
                        <th>Recommended Fare</th>
                        <th>Reason</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Calculate fare recommendations based on performance
                    foreach ($routes as $route):
                        $current = $route['fare_amount'];
                        $recommended = $current;
                        $reason = '';
                        
                        if ($route['total_trips'] > 20 && $route['avg_passengers'] > 15) {
                            // High demand - can increase fare
                            $recommended = $current * 1.1;
                            $reason = 'High demand route. 10% increase recommended.';
                        } elseif ($route['total_trips'] < 5 && $route['total_trips'] > 0) {
                            // Low demand - consider decrease
                            $recommended = $current * 0.9;
                            $reason = 'Low demand. 10% decrease to attract more passengers.';
                        } elseif ($route['total_trips'] == 0) {
                            $reason = 'No trips today. Review route viability.';
                        }
                        
                        if ($reason):
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($route['route_name']) ?></td>
                        <td>R <?= number_format($current, 2) ?></td>
                        <td><strong>R <?= number_format($recommended, 2) ?></strong></td>
                        <td><small><?= $reason ?></small></td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="applyFare(<?= $route['id'] ?>, <?= $recommended ?>)">
                                Apply Recommendation
                            </button>
                        </td>
                    </tr>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Profitability Chart
const profitCtx = document.getElementById('profitChart').getContext('2d');
new Chart(profitCtx, {
    type: 'bar',
    data: {
        labels: [<?php 
            $top = array_slice($routes, 0, 5);
            foreach($top as $r) echo "'" . addslashes($r['route_name']) . "',";
        ?>],
        datasets: [{
            label: 'Revenue (R)',
            data: [<?php foreach($top as $r) echo $r['total_revenue'] . ","; ?>],
            backgroundColor: 'rgba(40, 167, 69, 0.7)'
        }]
    }
});

// Prediction Chart
const predCtx = document.getElementById('predictionChart').getContext('2d');
new Chart(predCtx, {
    type: 'line',
    data: {
        labels: ['6AM', '8AM', '10AM', '12PM', '2PM', '4PM', '6PM', '8PM'],
        datasets: [{
            label: 'Predicted Passengers',
            data: [45, 82, 67, 55, 48, 72, 89, 43],
            borderColor: 'rgb(23, 162, 184)',
            tension: 0.4
        }]
    }
});

function applyFare(routeId, newFare) {
    if (confirm(`Change fare to R${newFare.toFixed(2)}?`)) {
        window.location.href = `update_fare.php?id=${routeId}&fare=${newFare}`;
    }
}
</script>

<?php
$content = ob_get_clean();
require_once '../layouts/admin_layout.php';
?>