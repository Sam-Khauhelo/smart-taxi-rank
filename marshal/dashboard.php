<?php
// marshal/dashboard.php
// Set page title
$page_title = 'Marshal Dashboard';

// Start output buffering
ob_start();

require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('marshal');

// Get active routes for queue display
try {
    $routes = $pdo->query("SELECT id, route_name FROM routes WHERE is_active = 1 ORDER BY route_name")->fetchAll();
    
    // Get active trips count (taxis on the road)
    $active_trips_count = $pdo->query("
        SELECT COUNT(*) as count 
        FROM trips 
        WHERE trip_status = 'departed'
    ")->fetch()['count'];
    
    // Get active trips with details for the table
    $active_trips = $pdo->query("
        SELECT t.*, tx.registration_number, u.full_name as driver_name,
               r.route_name, r.fare_amount,
               TIMESTAMPDIFF(MINUTE, t.departed_at, NOW()) as minutes_on_road,
               TIMESTAMPDIFF(MINUTE, t.departed_at, NOW()) as duration
        FROM trips t
        JOIN taxis tx ON t.taxi_id = tx.id
        JOIN drivers d ON t.driver_id = d.id
        JOIN users u ON d.user_id = u.id
        JOIN routes r ON t.route_id = r.id
        WHERE t.trip_status = 'departed'
        ORDER BY t.departed_at
    ")->fetchAll();
    
    // Get estimated arrival times based on route duration
    // You would need to add estimated_duration to routes table
    // For now, using a simple 30-minute estimate
    $routes_with_duration = $pdo->query("
        SELECT id, 
               CASE 
                   WHEN route_name LIKE '%Umlazi%' THEN 25
                   WHEN route_name LIKE '%KwaMashu%' THEN 30
                   WHEN route_name LIKE '%Pinetown%' THEN 35
                   ELSE 30
               END as est_duration
        FROM routes WHERE is_active = 1
    ")->fetchAll();
    
    $route_durations = [];
    foreach ($routes_with_duration as $r) {
        $route_durations[$r['id']] = $r['est_duration'];
    }
    
} catch (PDOException $e) {
    error_log("Error fetching data: " . $e->getMessage());
    $routes = [];
    $active_trips_count = 0;
    $active_trips = [];
    $route_durations = [];
}
?>

<!-- Quick Stats Row - Added Active Trips -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <h5 class="card-title">Taxis Waiting</h5>
                <h2 id="totalWaiting">0</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <h5 class="card-title">Trips Today</h5>
                <h2 id="tripsToday">0</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <h5 class="card-title">Loading Now</h5>
                <h2 id="loadingNow">0</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <h5 class="card-title">Passengers Today</h5>
                <h2 id="passengersToday">0</h2>
            </div>
        </div>
    </div>
</div>

<!-- Second Row - Active Trips on Road -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card border-warning">
            <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-truck"></i> 🚕 ACTIVE TRIPS ON THE ROAD (<?= $active_trips_count ?>)</h5>
                <a href="active_trips.php" class="btn btn-sm btn-dark">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($active_trips)): ?>
                    <p class="text-muted text-center py-3">No active trips at the moment</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Taxi</th>
                                    <th>Driver</th>
                                    <th>Route</th>
                                    <th>Departed</th>
                                    <th>Time on Road</th>
                                    <th>Est. Arrival</th>
                                    <th>Passengers</th>
                                    <th>Fare</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($active_trips as $trip): 
                                    $est_duration = $route_durations[$trip['route_id']] ?? 30;
                                    $est_arrival = date('H:i', strtotime($trip['departed_at'] . " + $est_duration minutes"));
                                    $time_remaining = $est_duration - $trip['minutes_on_road'];
                                    $status_class = $time_remaining < 5 ? 'success' : ($time_remaining < 15 ? 'warning' : 'primary');
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($trip['registration_number']) ?></strong></td>
                                    <td><?= htmlspecialchars($trip['driver_name']) ?></td>
                                    <td><?= htmlspecialchars($trip['route_name']) ?></td>
                                    <td><?= date('H:i', strtotime($trip['departed_at'])) ?></td>
                                    <td>
                                        <span class="badge bg-info"><?= $trip['minutes_on_road'] ?> min</span>
                                    </td>
                                    <td>
                                        <?= $est_arrival ?>
                                        <?php if ($time_remaining > 0): ?>
                                            <small class="text-muted">(in <?= $time_remaining ?> min)</small>
                                        <?php else: ?>
                                            <small class="text-danger">(overdue)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $trip['passenger_count'] ?></td>
                                    <td>R <?= number_format($trip['total_fare'], 2) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $status_class ?>">
                                            <?= $time_remaining > 0 ? 'On Time' : 'Delayed' ?>
                                        </span>
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
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Route Tabs -->
<ul class="nav nav-tabs" id="routeTabs" role="tablist">
    <?php foreach ($routes as $index => $route): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $index === 0 ? 'active' : '' ?>" 
                    id="route-<?= $route['id'] ?>-tab" 
                    data-bs-toggle="tab" 
                    data-bs-target="#route-<?= $route['id'] ?>" 
                    type="button" 
                    role="tab">
                <?= htmlspecialchars($route['route_name']) ?>
            </button>
        </li>
    <?php endforeach; ?>
</ul>

<!-- Route Content -->
<div class="tab-content mt-3" id="routeTabsContent">
    <?php foreach ($routes as $index => $route): ?>
        <div class="tab-pane fade <?= $index === 0 ? 'show active' : '' ?>" 
             id="route-<?= $route['id'] ?>" 
             role="tabpanel">
            
            <!-- Current Loading Taxi -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card border-warning">
                        <div class="card-header bg-warning text-white">
                            <h5 class="mb-0"><i class="bi bi-taxi-front"></i> CURRENTLY LOADING</h5>
                        </div>
                        <div class="card-body" id="loading-<?= $route['id'] ?>">
                            <div class="text-center text-muted py-3">
                                <i class="bi bi-arrow-repeat"></i> No taxi loading at the moment
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Control Buttons -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <button class="btn btn-success w-100 big-button" 
                            onclick="loadNextTaxi(<?= $route['id'] ?>)">
                        <i class="bi bi-arrow-right-circle"></i> LOAD NEXT
                    </button>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100 big-button" 
                            onclick="confirmDeparture(<?= $route['id'] ?>)">
                        <i class="bi bi-check-circle"></i> DEPART
                    </button>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-info w-100 big-button" 
                            onclick="capturePassengers(<?= $route['id'] ?>)">
                        <i class="bi bi-people"></i> PASSENGERS
                    </button>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-secondary w-100 big-button" 
                            onclick="window.location.href='active_trips.php'">
                        <i class="bi bi-truck"></i> ON ROAD
                    </button>
                </div>
            </div>

            <!-- Queue List -->
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">QUEUE - Waiting Taxis</h5>
                </div>
                <div class="card-body" id="queue-<?= $route['id'] ?>">
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-arrow-repeat"></i> Loading queue...
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Passenger Count Modal -->
<div class="modal fade" id="passengerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Enter Passenger Count</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="passengerForm">
                    <input type="hidden" id="modalRouteId">
                    <div class="mb-3">
                        <label for="passengerCount" class="form-label">Number of Passengers</label>
                        <input type="number" class="form-control form-control-lg" id="passengerCount" min="1" max="30" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitPassengerCount()">Save</button>
            </div>
        </div>
    </div>
</div>

<style>
    .big-button {
        padding: 15px;
        font-size: 18px;
        margin: 5px 0;
        font-weight: 600;
    }
    .queue-card {
        background: #f8f9fa;
        border-left: 4px solid #0d6efd;
        margin-bottom: 10px;
        padding: 15px;
        border-radius: 5px;
        transition: transform 0.2s;
    }
    .queue-card:hover {
        transform: translateX(5px);
        background: #e9ecef;
    }
    .loading-card {
        background: #fff3cd;
        border-left: 4px solid #ffc107;
    }
    .queue-position {
        font-size: 24px;
        font-weight: bold;
        color: #0d6efd;
    }
    .border-warning {
        border-left: 5px solid #ffc107 !important;
    }
</style>

<script>
    // Refresh queue every 10 seconds
    setInterval(updateAllQueues, 10000);
    
    function updateAllQueues() {
        <?php foreach ($routes as $route): ?>
            updateQueue(<?= $route['id'] ?>);
        <?php endforeach; ?>
        updateStats();
        // Also refresh active trips via AJAX
        refreshActiveTrips();
    }
    
    function updateQueue(routeId) {
        fetch(`../api/get_queue.php?route_id=${routeId}`)
            .then(response => response.json())
            .then(data => {
                displayQueue(routeId, data);
            });
    }
    
    function displayQueue(routeId, data) {
        const queueDiv = document.getElementById(`queue-${routeId}`);
        const loadingDiv = document.getElementById(`loading-${routeId}`);
        
        let html = '';
        
        if (data.queue.length === 0) {
            html = '<div class="text-center text-muted py-5"><i class="bi bi-inbox"></i> Queue is empty</div>';
        } else {
            data.queue.forEach((taxi, index) => {
                html += `
                    <div class="queue-card">
                        <div class="row align-items-center">
                            <div class="col-md-1">
                                <span class="queue-position">#${taxi.position}</span>
                            </div>
                            <div class="col-md-3">
                                <strong>${taxi.registration}</strong>
                            </div>
                            <div class="col-md-4">
                                Driver: ${taxi.driver_name || 'No driver'}
                            </div>
                            <div class="col-md-2">
                                <span class="badge bg-secondary">${taxi.status}</span>
                            </div>
                            <div class="col-md-2">
                                <small>${taxi.entered_time || ''}</small>
                            </div>
                        </div>
                    </div>
                `;
            });
        }
        
        queueDiv.innerHTML = html;
        
        // Update loading taxi
        if (data.loading) {
            loadingDiv.innerHTML = `
                <div class="queue-card loading-card">
                    <div class="row align-items-center">
                        <div class="col-md-2">
                            <span class="badge bg-warning text-dark">LOADING</span>
                        </div>
                        <div class="col-md-3">
                            <strong>${data.loading.registration}</strong>
                        </div>
                        <div class="col-md-4">
                            Driver: ${data.loading.driver_name}
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-sm btn-success" onclick="confirmDepartureForLoading(${routeId})">
                                <i class="bi bi-check"></i> Depart Now
                            </button>
                        </div>
                    </div>
                </div>
            `;
        } else {
            loadingDiv.innerHTML = '<div class="text-center text-muted py-3"><i class="bi bi-arrow-repeat"></i> No taxi loading at the moment</div>';
        }
    }
    
    function refreshActiveTrips() {
        fetch('../api/get_active_trips.php')
            .then(response => response.json())
            .then(data => {
                // You could update the active trips table here without reloading
                console.log('Active trips updated:', data);
            });
    }
    
    function loadNextTaxi(routeId) {
        if (!confirm('Load next taxi?')) return;
        
        fetch('../api/load_taxi.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({route_id: routeId})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateQueue(routeId);
                updateStats();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
    
    function confirmDeparture(routeId) {
        if (!confirm('Confirm taxi departure?')) return;
        
        fetch('../api/depart_taxi.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({route_id: routeId})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ Taxi departed. Trip #' + data.trip_id + ' created.');
                updateQueue(routeId);
                updateStats();
                // Reload page to show new active trip
                setTimeout(() => window.location.reload(), 1000);
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
    
    function confirmDepartureForLoading(routeId) {
        confirmDeparture(routeId);
    }
    
    function forceArrival(tripId, taxiReg) {
        if (confirm(`Mark taxi ${taxiReg} as arrived at destination?`)) {
            fetch('../api/arrive_taxi.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({trip_id: tripId})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Taxi arrived. Duration: ' + data.trip.duration_minutes + ' minutes');
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
    }
    
    function capturePassengers(routeId) {
        document.getElementById('modalRouteId').value = routeId;
        document.getElementById('passengerCount').value = '';
        new bootstrap.Modal(document.getElementById('passengerModal')).show();
    }
    
    function submitPassengerCount() {
        const routeId = document.getElementById('modalRouteId').value;
        const count = document.getElementById('passengerCount').value;
        
        if (!count || count < 1) {
            alert('Please enter valid passenger count');
            return;
        }
        
        fetch('../api/save_passengers.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                route_id: routeId,
                passenger_count: count
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('passengerModal')).hide();
                alert('✅ Passenger count saved: ' + count);
                updateQueue(routeId);
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
    
    function updateStats() {
        fetch('../api/get_stats.php')
            .then(response => response.json())
            .then(data => {
                document.getElementById('totalWaiting').textContent = data.waiting;
                document.getElementById('tripsToday').textContent = data.trips;
                document.getElementById('loadingNow').textContent = data.loading;
                document.getElementById('passengersToday').textContent = data.passengers;
            });
    }
    
    // Initial load
    setTimeout(updateAllQueues, 1000);
</script>

<?php
// Get the content
$content = ob_get_clean();

// Include the marshal layout
require_once '../layouts/marshal_layout.php';
?>