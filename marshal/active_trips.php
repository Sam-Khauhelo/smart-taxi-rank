<?php
// marshal/active_trips.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('marshal');

$page_title = 'Active Trips';
ob_start();

// Get all active trips (departed but not arrived)
$trips = $pdo->query("
    SELECT t.*, tx.registration_number, u.full_name as driver_name,
           r.route_name, r.fare_amount,
           TIMESTAMPDIFF(MINUTE, t.departed_at, NOW()) as minutes_since_departure,
           TIMESTAMPDIFF(MINUTE, t.departed_at, NOW()) as duration,
           CASE 
               WHEN TIMESTAMPDIFF(MINUTE, t.departed_at, NOW()) < 15 THEN 'bg-success'
               WHEN TIMESTAMPDIFF(MINUTE, t.departed_at, NOW()) < 30 THEN 'bg-warning'
               ELSE 'bg-danger'
           END as time_badge_class
    FROM trips t
    JOIN taxis tx ON t.taxi_id = tx.id
    JOIN drivers d ON t.driver_id = d.id
    JOIN users u ON d.user_id = u.id
    JOIN routes r ON t.route_id = r.id
    WHERE t.trip_status = 'departed'
    ORDER BY t.departed_at
")->fetchAll();

// Get estimated route durations (you can customize these)
$route_durations = [
    'Umlazi' => 25,
    'KwaMashu' => 30,
    'Pinetown' => 35,
    'Soweto' => 40,
    'Default' => 30
];
?>

<style>
    .active-trip-card {
        transition: transform 0.2s, box-shadow 0.2s;
        border: none;
        border-radius: 15px;
        overflow: hidden;
        margin-bottom: 20px;
    }
    .active-trip-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    .timer-badge {
        font-size: 1.2rem;
        padding: 8px 15px;
        border-radius: 50px;
        animation: pulse 2s infinite;
    }
    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.8; }
        100% { opacity: 1; }
    }
    .eta-progress {
        height: 8px;
        border-radius: 4px;
        margin-top: 10px;
    }
    .status-onroad {
        background: linear-gradient(135deg, #ffc107, #fd7e14);
        color: #000;
    }
    .header-stats {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        border-radius: 15px;
        margin-bottom: 30px;
    }
    .stat-item {
        text-align: center;
        border-right: 1px solid rgba(255,255,255,0.2);
    }
    .stat-item:last-child {
        border-right: none;
    }
    .stat-value {
        font-size: 2rem;
        font-weight: 700;
    }
    .stat-label {
        font-size: 0.9rem;
        opacity: 0.9;
    }
    .map-placeholder {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 10px;
        text-align: center;
        color: #6c757d;
        font-size: 0.9rem;
    }
</style>

<div class="container-fluid">
    <!-- Header Stats -->
    <div class="header-stats">
        <div class="row">
            <div class="col-md-3 stat-item">
                <div class="stat-value"><?= count($trips) ?></div>
                <div class="stat-label">Active Trips</div>
            </div>
            <div class="col-md-3 stat-item">
                <div class="stat-value">
                    <?php 
                    $total_passengers = array_sum(array_column($trips, 'passenger_count'));
                    echo $total_passengers;
                    ?>
                </div>
                <div class="stat-label">Passengers on Road</div>
            </div>
            <div class="col-md-3 stat-item">
                <div class="stat-value">
                    R <?= number_format(array_sum(array_column($trips, 'total_fare')), 0) ?>
                </div>
                <div class="stat-label">Revenue in Transit</div>
            </div>
            <div class="col-md-3 stat-item">
                <div class="stat-value">
                    <?php 
                    $longest_trip = !empty($trips) ? max(array_column($trips, 'minutes_since_departure')) : 0;
                    echo $longest_trip . ' min';
                    ?>
                </div>
                <div class="stat-label">Longest Trip</div>
            </div>
        </div>
    </div>

    <!-- Map View Placeholder (Optional) -->
    <div class="map-placeholder mb-4">
        <i class="bi bi-map fs-2"></i>
        <p class="mb-0">Map view coming soon - Track taxis in real time</p>
    </div>

    <!-- Active Trips Grid/Table -->
    <div class="card">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-truck"></i> Active Trips On The Road</h5>
            <span class="badge bg-warning fs-6"><?= count($trips) ?> on road</span>
        </div>
        <div class="card-body">
            <?php if (empty($trips)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-check-circle fs-1 text-success d-block mb-3"></i>
                    <h4>No Active Trips</h4>
                    <p class="text-muted">All taxis are currently at the rank.</p>
                </div>
            <?php else: ?>
                <!-- Desktop Table View -->
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Taxi</th>
                                <th>Driver</th>
                                <th>Route</th>
                                <th>Departed</th>
                                <th>Time on Road</th>
                                <th>Passengers</th>
                                <th>Fare</th>
                                <th>ETA</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trips as $trip): 
                                // Calculate ETA based on route
                                $route_key = array_key_exists($trip['route_name'], $route_durations) ? $trip['route_name'] : 'Default';
                                $est_duration = $route_durations[$route_key];
                                $time_remaining = max(0, $est_duration - $trip['minutes_since_departure']);
                                $progress = min(100, ($trip['minutes_since_departure'] / $est_duration) * 100);
                                $eta = date('H:i', strtotime("+$time_remaining minutes"));
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($trip['registration_number']) ?></strong>
                                </td>
                                <td>
                                    <i class="bi bi-person"></i> <?= htmlspecialchars($trip['driver_name']) ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?= htmlspecialchars($trip['route_name']) ?></span>
                                </td>
                                <td>
                                    <?= date('H:i', strtotime($trip['departed_at'])) ?>
                                </td>
                                <td>
                                    <span class="badge <?= $trip['time_badge_class'] ?> timer-badge">
                                        <i class="bi bi-clock"></i> <?= $trip['minutes_since_departure'] ?> min
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary"><?= $trip['passenger_count'] ?></span>
                                </td>
                                <td>
                                    <strong>R <?= number_format($trip['total_fare'], 2) ?></strong>
                                </td>
                                <td>
                                    <?= $eta ?>
                                    <div class="progress eta-progress">
                                        <div class="progress-bar bg-<?= $time_remaining < 5 ? 'success' : ($time_remaining < 15 ? 'warning' : 'primary') ?>" 
                                             style="width: <?= $progress ?>%"></div>
                                    </div>
                                    <small class="text-muted"><?= $time_remaining ?> min left</small>
                                </td>
                                <td>
                                    <span class="badge bg-warning">On Road</span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-success" onclick="forceArrival(<?= $trip['id'] ?>, '<?= $trip['registration_number'] ?>')">
                                        <i class="bi bi-check-circle"></i> Arrived
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <div class="d-md-none">
                    <?php foreach ($trips as $trip): 
                        $route_key = array_key_exists($trip['route_name'], $route_durations) ? $trip['route_name'] : 'Default';
                        $est_duration = $route_durations[$route_key];
                        $time_remaining = max(0, $est_duration - $trip['minutes_since_departure']);
                        $progress = min(100, ($trip['minutes_since_departure'] / $est_duration) * 100);
                    ?>
                        <div class="card active-trip-card">
                            <div class="card-header bg-warning text-dark">
                                <div class="d-flex justify-content-between">
                                    <h5 class="mb-0"><?= htmlspecialchars($trip['registration_number']) ?></h5>
                                    <span class="badge bg-dark"><?= $trip['minutes_since_departure'] ?> min</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row mb-2">
                                    <div class="col-4 text-muted">Driver:</div>
                                    <div class="col-8"><?= htmlspecialchars($trip['driver_name']) ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-4 text-muted">Route:</div>
                                    <div class="col-8"><?= htmlspecialchars($trip['route_name']) ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-4 text-muted">Passengers:</div>
                                    <div class="col-8"><?= $trip['passenger_count'] ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-4 text-muted">Fare:</div>
                                    <div class="col-8">R <?= number_format($trip['total_fare'], 2) ?></div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-4 text-muted">Progress:</div>
                                    <div class="col-8">
                                        <div class="progress">
                                            <div class="progress-bar bg-<?= $time_remaining < 5 ? 'success' : ($time_remaining < 15 ? 'warning' : 'primary') ?>" 
                                                 style="width: <?= $progress ?>%"></div>
                                        </div>
                                        <small><?= $time_remaining ?> min remaining</small>
                                    </div>
                                </div>
                                <button class="btn btn-success w-100" onclick="forceArrival(<?= $trip['id'] ?>, '<?= $trip['registration_number'] ?>')">
                                    <i class="bi bi-check-circle"></i> Mark Arrived
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Stats Card -->
    <?php if (!empty($trips)): ?>
    <div class="row mt-4">
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Estimated Arrivals</h6>
                    <h3>Next: <?= date('H:i', strtotime('+' . min(array_column($trips, 'time_remaining')) . ' minutes')) ?></h3>
                    <small>Soonest taxi returning</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Average Trip Time</h6>
                    <h3>
                        <?php 
                        $avg_time = !empty($trips) ? array_sum(array_column($trips, 'minutes_since_departure')) / count($trips) : 0;
                        echo round($avg_time) . ' minutes';
                        ?>
                    </h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h6 class="text-dark-50">Revenue on Road</h6>
                    <h3>R <?= number_format(array_sum(array_column($trips, 'total_fare')), 2) ?></h3>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function forceArrival(tripId, taxiReg) {
    if (confirm(`Mark taxi ${taxiReg} as arrived at destination?`)) {
        // Show loading on button
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Confirming...';
        btn.disabled = true;
        
        fetch('../api/arrive_taxi.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({trip_id: tripId})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ ' + data.message);
                location.reload();
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

// Auto-refresh every 30 seconds
setTimeout(function() {
    location.reload();
}, 30000);
</script>

<?php
$content = ob_get_clean();
require_once '../layouts/marshal_layout.php';
?>