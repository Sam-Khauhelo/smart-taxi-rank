<?php
// driver/portal.php
// Set page title
$page_title = 'Driver Portal';

// Start output buffering
ob_start();

require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('driver');

$driver_id = $_SESSION['driver_id'];
$taxi_id = $_SESSION['taxi_id'];

try {
    // Get driver's queue position
    $queue_sql = "SELECT q.position, q.status, r.route_name 
                  FROM queue q
                  JOIN routes r ON q.route_id = r.id
                  WHERE q.taxi_id = ? AND q.status IN ('waiting', 'loading')
                  ORDER BY q.position LIMIT 1";
    $stmt = $pdo->prepare($queue_sql);
    $stmt->execute([$taxi_id]);
    $queue = $stmt->fetch();
    
    // Check if driver has an active trip (departed but not arrived)
    $active_trip_sql = "SELECT t.*, r.route_name, r.fare_amount,
                               TIMESTAMPDIFF(MINUTE, t.departed_at, NOW()) as minutes_since_departure
                        FROM trips t
                        JOIN routes r ON t.route_id = r.id
                        WHERE t.driver_id = ? AND t.trip_status = 'departed'
                        ORDER BY t.departed_at DESC
                        LIMIT 1";
    $active_trip_stmt = $pdo->prepare($active_trip_sql);
    $active_trip_stmt->execute([$driver_id]);
    $active_trip = $active_trip_stmt->fetch();
    
    // Get today's trips
    $trips_sql = "SELECT COUNT(*) as trip_count, 
                  SUM(passenger_count) as total_passengers,
                  SUM(total_fare) as total_fare,
                  SUM(association_levy) as total_levy,
                  SUM(total_fare - association_levy) as owner_amount
                  FROM trips 
                  WHERE driver_id = ? AND DATE(departed_at) = CURDATE()";
    $stmt = $pdo->prepare($trips_sql);
    $stmt->execute([$driver_id]);
    $today = $stmt->fetch();
    
    // Get recent trips (excluding active trip if any)
    $recent_sql = "SELECT t.*, r.route_name, 
                   TIME(t.departed_at) as time
                   FROM trips t
                   JOIN routes r ON t.route_id = r.id
                   WHERE t.driver_id = ? AND t.trip_status = 'arrived'
                   ORDER BY t.departed_at DESC
                   LIMIT 10";
    $stmt = $pdo->prepare($recent_sql);
    $stmt->execute([$driver_id]);
    $recent_trips = $stmt->fetchAll();
    
    // Get driver's payment info
    $driver_sql = "SELECT d.*, u.full_name,
                   o.user_id as owner_user_id,
                   ou.full_name as owner_name
                   FROM drivers d
                   JOIN users u ON d.user_id = u.id
                   JOIN owners o ON d.owner_id = o.id
                   JOIN users ou ON o.user_id = ou.id
                   WHERE d.id = ?";
    $stmt = $pdo->prepare($driver_sql);
    $stmt->execute([$driver_id]);
    $driver_info = $stmt->fetch();
    
} catch (PDOException $e) {
    error_log("Driver portal error: " . $e->getMessage());
    $today = ['trip_count' => 0, 'total_passengers' => 0, 'total_fare' => 0];
    $recent_trips = [];
    $driver_info = [];
    $active_trip = null;
}
?>

<!-- ACTIVE TRIP BANNER - Show if driver is on the road -->
<?php if ($active_trip): ?>
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card border-warning">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="bi bi-truck"></i> 🚕 YOU ARE ON AN ACTIVE TRIP</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Route:</strong>
                        <p class="fs-5"><?= htmlspecialchars($active_trip['route_name']) ?></p>
                    </div>
                    <div class="col-md-3">
                        <strong>Departed:</strong>
                        <p class="fs-5"><?= date('H:i', strtotime($active_trip['departed_at'])) ?></p>
                    </div>
                    <div class="col-md-3">
                        <strong>Passengers:</strong>
                        <p class="fs-5"><?= $active_trip['passenger_count'] ?></p>
                    </div>
                    <div class="col-md-3">
                        <strong>Time on Road:</strong>
                        <p class="fs-5" id="tripTimer"><?= $active_trip['minutes_since_departure'] ?> min</p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <strong>Total Fare:</strong>
                        <p class="fs-4">R <?= number_format($active_trip['total_fare'], 2) ?></p>
                    </div>
                    <div class="col-md-6">
                        <strong>Your Earnings (est):</strong>
                        <p class="fs-4 text-success">
                            <?php 
                            if ($driver_info['employment_type'] == 'commission') {
                                $earnings = $active_trip['total_fare'] * ($driver_info['payment_rate'] / 100);
                                echo "R " . number_format($earnings, 2) . " (" . $driver_info['payment_rate'] . "%)";
                            } else {
                                echo "Based on daily rate";
                            }
                            ?>
                        </p>
                    </div>
                </div>
                <button class="btn btn-success btn-lg w-100 mt-3" onclick="confirmArrival(<?= $active_trip['id'] ?>)">
                    <i class="bi bi-check-circle"></i> ✅ I HAVE ARRIVED AT DESTINATION
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Timer for active trip
function updateTimer() {
    const departed = new Date('<?= $active_trip['departed_at'] ?>').getTime();
    const now = new Date().getTime();
    const minutes = Math.floor((now - departed) / (1000 * 60));
    document.getElementById('tripTimer').textContent = minutes + ' min';
}

// Update timer every minute
setInterval(updateTimer, 60000);

// Confirm arrival function
function confirmArrival(tripId) {
    if (confirm('Have you arrived at your destination and dropped off all passengers?')) {
        // Show loading state
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Confirming...';
        btn.disabled = true;
        
        fetch('../api/driver_arrive.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({trip_id: tripId})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ ' + data.message);
                window.location.reload();
            } else {
                alert('❌ ' + data.message);
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        })
        .catch(error => {
            alert('❌ Error connecting to server');
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }
}
</script>
<?php endif; ?>

<!-- Queue Position Banner - Only show if NOT on active trip -->
<?php if (!$active_trip): ?>
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card <?= isset($queue['status']) && $queue['status'] == 'loading' ? 'bg-warning' : 'bg-primary' ?> text-white">
            <div class="card-body text-center py-4">
                <?php if (isset($queue['position'])): ?>
                    <h3>Your Position in Queue</h3>
                    <div class="queue-number">#<?= $queue['position'] ?></div>
                    <p class="mb-0">Route: <?= htmlspecialchars($queue['route_name']) ?> | Status: <?= ucfirst($queue['status']) ?></p>
                <?php else: ?>
                    <h3>Not in Queue</h3>
                    <p class="mb-0">See the marshal to join the queue</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Today's Stats -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <i class="bi bi-clock-history fs-1 text-primary"></i>
            <div class="stat-value"><?= $today['trip_count'] ?? 0 ?></div>
            <div class="text-muted">Trips Today</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <i class="bi bi-people fs-1 text-success"></i>
            <div class="stat-value"><?= $today['total_passengers'] ?? 0 ?></div>
            <div class="text-muted">Passengers</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <i class="bi bi-cash-stack fs-1 text-warning"></i>
            <div class="stat-value">R <?= number_format($today['total_fare'] ?? 0, 2) ?></div>
            <div class="text-muted">Total Fare</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <i class="bi bi-piggy-bank fs-1 text-info"></i>
            <div class="stat-value">R <?= number_format(($today['total_fare'] ?? 0) - ($today['total_levy'] ?? 0), 2) ?></div>
            <div class="text-muted">Owner Amount</div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Trips -->
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-clock"></i> Recent Trips</h5>
                <?php if ($active_trip): ?>
                    <span class="badge bg-warning">Currently on trip</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($recent_trips) && !$active_trip): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-1"></i>
                        <p>No trips recorded yet today</p>
                    </div>
                <?php elseif (empty($recent_trips) && $active_trip): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-truck fs-1"></i>
                        <p>Your first trip today is in progress</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Route</th>
                                    <th>Passengers</th>
                                    <th>Fare</th>
                                    <th>Levy</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_trips as $trip): ?>
                                    <tr>
                                        <td><?= date('H:i', strtotime($trip['departed_at'])) ?></td>
                                        <td><?= htmlspecialchars($trip['route_name']) ?></td>
                                        <td><?= $trip['passenger_count'] ?></td>
                                        <td>R <?= number_format($trip['total_fare'], 2) ?></td>
                                        <td>R <?= number_format($trip['association_levy'], 2) ?></td>
                                        <td><span class="badge bg-success">Completed</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Driver Info -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> My Details</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Owner:</span>
                        <strong><?= htmlspecialchars($driver_info['owner_name'] ?? 'N/A') ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Employment:</span>
                        <strong><?= ucfirst($driver_info['employment_type'] ?? 'N/A') ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Payment Rate:</span>
                        <strong>
                            <?php if (($driver_info['employment_type'] ?? '') == 'commission'): ?>
                                <?= $driver_info['payment_rate'] ?>%
                            <?php else: ?>
                                R <?= number_format($driver_info['payment_rate'] ?? 0, 2) ?>/day
                            <?php endif; ?>
                        </strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>License Expiry:</span>
                        <strong class="<?= (isset($driver_info['license_expiry_date']) && strtotime($driver_info['license_expiry_date']) < time()) ? 'text-danger' : 'text-success' ?>">
                            <?= isset($driver_info['license_expiry_date']) ? date('d M Y', strtotime($driver_info['license_expiry_date'])) : 'N/A' ?>
                        </strong>
                    </li>
                    <?php if ($active_trip): ?>
                    <li class="list-group-item d-flex justify-content-between bg-light">
                        <span>Current Trip:</span>
                        <strong class="text-warning">Active</strong>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-lightning"></i> Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="my_trips.php" class="btn btn-outline-primary">
                        <i class="bi bi-list"></i> View All Trips
                    </a>
                    <a href="my_earnings.php" class="btn btn-outline-success">
                        <i class="bi bi-cash"></i> Earnings Details
                    </a>
                    <?php if (!$active_trip): ?>
                    <button class="btn btn-outline-info" onclick="window.location.reload()">
                        <i class="bi bi-arrow-repeat"></i> Refresh Queue
                    </button>
                    <?php else: ?>
                    <button class="btn btn-outline-warning" onclick="confirmArrival(<?= $active_trip['id'] ?>)">
                        <i class="bi bi-check-circle"></i> Mark Arrival
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .queue-number {
        font-size: 72px;
        font-weight: bold;
        color: #0d6efd;
        line-height: 1;
    }
    .stat-card {
        text-align: center;
        padding: 20px;
        border-radius: 10px;
        background: #f8f9fa;
        margin-bottom: 20px;
        transition: transform 0.2s;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .stat-value {
        font-size: 32px;
        font-weight: bold;
        color: #0d6efd;
    }
    .border-warning {
        border-left: 5px solid #ffc107 !important;
    }
</style>

<!-- Auto-refresh queue position every 30 seconds (only if not on active trip) -->
<?php if (!$active_trip): ?>
<script>
    setTimeout(function() {
        window.location.reload();
    }, 30000);
</script>
<?php endif; ?>

<?php
// Get the content
$content = ob_get_clean();

// Include the driver layout
require_once '../layouts/driver_layout.php';
?>