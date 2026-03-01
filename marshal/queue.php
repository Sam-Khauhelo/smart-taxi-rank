<?php
// marshal/queue.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('marshal');

// Set page title
$page_title = 'Queue Management';

// Start output buffering
ob_start();

// Get all active routes
try {
    $routes_sql = "SELECT id, route_name, fare_amount FROM routes WHERE is_active = 1 ORDER BY route_name";
    $routes = $pdo->query($routes_sql)->fetchAll();
    
    // Get queue status for each route
    $queue_stats = [];
    foreach ($routes as $route) {
        $stats_sql = "SELECT 
                        COUNT(*) as total_waiting,
                        SUM(CASE WHEN status = 'loading' THEN 1 ELSE 0 END) as loading,
                        MIN(CASE WHEN status = 'waiting' THEN position END) as next_position
                      FROM queue 
                      WHERE route_id = ? AND status IN ('waiting', 'loading')";
        $stats_stmt = $pdo->prepare($stats_sql);
        $stats_stmt->execute([$route['id']]);
        $queue_stats[$route['id']] = $stats_stmt->fetch();
    }
    
    // Get taxis not in queue (available to join)
    $available_taxis_sql = "SELECT t.id, t.registration_number, 
                                   u.full_name as driver_name,
                                   r.route_name, r.id as route_id
                            FROM taxis t
                            LEFT JOIN drivers d ON t.id = d.taxi_id
                            LEFT JOIN users u ON d.user_id = u.id
                            LEFT JOIN routes r ON t.route_id = r.id
                            WHERE t.status = 'active' 
                            AND t.id NOT IN (SELECT taxi_id FROM queue WHERE status IN ('waiting', 'loading'))
                            ORDER BY t.registration_number";
    $available_taxis = $pdo->query($available_taxis_sql)->fetchAll();
    
} catch (PDOException $e) {
    error_log("Queue page error: " . $e->getMessage());
    $routes = [];
    $queue_stats = [];
    $available_taxis = [];
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-list-ol"></i> Queue Management</h2>
        <div>
            <button class="btn btn-success" onclick="refreshQueue()">
                <i class="bi bi-arrow-repeat"></i> Refresh Queue
            </button>
            <button class="btn btn-primary" onclick="showAddToQueue()">
                <i class="bi bi-plus-circle"></i> Add Taxi to Queue
            </button>
        </div>
    </div>

    <!-- Queue Overview Cards -->
    <div class="row g-4 mb-4">
        <?php foreach ($routes as $route): 
            $stats = $queue_stats[$route['id']] ?? ['total_waiting' => 0, 'loading' => 0, 'next_position' => null];
        ?>
        <div class="col-md-3">
            <div class="card route-card" data-route-id="<?= $route['id'] ?>" onclick="scrollToRoute(<?= $route['id'] ?>)">
                <div class="card-body">
                    <h5 class="card-title"><?= htmlspecialchars($route['route_name']) ?></h5>
                    <div class="d-flex justify-content-between mt-3">
                        <div class="text-center">
                            <h3 class="mb-0 <?= $stats['loading'] > 0 ? 'text-warning' : 'text-muted' ?>">
                                <?= $stats['loading'] > 0 ? '🚕' : '⏸️' ?>
                            </h3>
                            <small>Loading</small>
                        </div>
                        <div class="text-center">
                            <h3 class="mb-0 text-primary"><?= $stats['total_waiting'] ?></h3>
                            <small>Waiting</small>
                        </div>
                        <div class="text-center">
                            <h3 class="mb-0 text-success">#<?= $stats['next_position'] ?? '-' ?></h3>
                            <small>Next Up</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Route Queue Tabs -->
    <ul class="nav nav-tabs mb-4" id="routeTabs" role="tablist">
        <?php foreach ($routes as $index => $route): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $index === 0 ? 'active' : '' ?>" 
                    id="route-<?= $route['id'] ?>-tab" 
                    data-bs-toggle="tab" 
                    data-bs-target="#route-<?= $route['id'] ?>" 
                    type="button" 
                    role="tab">
                <?= htmlspecialchars($route['route_name']) ?>
                <span class="badge bg-primary ms-2 queue-count" data-route="<?= $route['id'] ?>">
                    <?= $queue_stats[$route['id']]['total_waiting'] ?? 0 ?>
                </span>
            </button>
        </li>
        <?php endforeach; ?>
    </ul>

    <!-- Route Queue Content -->
    <div class="tab-content" id="routeTabsContent">
        <?php foreach ($routes as $index => $route): ?>
        <div class="tab-pane fade <?= $index === 0 ? 'show active' : '' ?>" 
             id="route-<?= $route['id'] ?>" 
             role="tabpanel"
             data-route-id="<?= $route['id'] ?>">
            
            <!-- Current Loading Taxi -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card border-warning">
                        <div class="card-header bg-warning text-white">
                            <h5 class="mb-0"><i class="bi bi-taxi-front"></i> CURRENTLY LOADING</h5>
                        </div>
                        <div class="card-body" id="loading-<?= $route['id'] ?>">
                            <?php
                            // Get loading taxi for this route
                            $loading_sql = "SELECT q.*, t.registration_number, u.full_name as driver_name
                                          FROM queue q
                                          JOIN taxis t ON q.taxi_id = t.id
                                          LEFT JOIN drivers d ON t.id = d.taxi_id
                                          LEFT JOIN users u ON d.user_id = u.id
                                          WHERE q.route_id = ? AND q.status = 'loading'
                                          LIMIT 1";
                            $loading_stmt = $pdo->prepare($loading_sql);
                            $loading_stmt->execute([$route['id']]);
                            $loading_taxi = $loading_stmt->fetch();
                            
                            if ($loading_taxi):
                            ?>
                            <div class="queue-card loading-card">
                                <div class="row align-items-center">
                                    <div class="col-md-1">
                                        <span class="badge bg-warning text-dark fs-6">LOADING</span>
                                    </div>
                                    <div class="col-md-2">
                                        <strong><?= htmlspecialchars($loading_taxi['registration_number']) ?></strong>
                                    </div>
                                    <div class="col-md-3">
                                        Driver: <?= htmlspecialchars($loading_taxi['driver_name'] ?? 'Unknown') ?>
                                    </div>
                                    <div class="col-md-3">
                                        <span class="text-muted">Position: #<?= $loading_taxi['position'] ?></span>
                                    </div>
                                    <div class="col-md-3">
                                        <button class="btn btn-sm btn-success" onclick="completeLoading(<?= $route['id'] ?>)">
                                            <i class="bi bi-check-circle"></i> Complete Loading
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="text-center text-muted py-3">
                                <i class="bi bi-arrow-repeat"></i> No taxi loading at the moment
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Control Buttons -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <button class="btn btn-success w-100 big-button" 
                            onclick="callNextTaxi(<?= $route['id'] ?>)">
                        <i class="bi bi-arrow-right-circle"></i> CALL NEXT
                    </button>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100 big-button" 
                            onclick="confirmDeparture(<?= $route['id'] ?>)">
                        <i class="bi bi-check-circle"></i> CONFIRM DEPART
                    </button>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-info w-100 big-button" 
                            onclick="showPassengerModal(<?= $route['id'] ?>)">
                        <i class="bi bi-people"></i> PASSENGER COUNT
                    </button>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-warning w-100 big-button" 
                            onclick="reorderQueue(<?= $route['id'] ?>)">
                        <i class="bi bi-arrow-up-down"></i> REORDER QUEUE
                    </button>
                </div>
            </div>

            <!-- Queue List -->
            <div class="card">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-list"></i> WAITING QUEUE</h5>
                    <span class="badge bg-info" id="queue-count-<?= $route['id'] ?>">
                        <?= $queue_stats[$route['id']]['total_waiting'] ?? 0 ?> waiting
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="queue-list" id="queue-<?= $route['id'] ?>" style="max-height: 500px; overflow-y: auto;">
                        <?php
                        // Get waiting queue for this route
                        $queue_sql = "SELECT q.*, t.registration_number, u.full_name as driver_name
                                    FROM queue q
                                    JOIN taxis t ON q.taxi_id = t.id
                                    LEFT JOIN drivers d ON t.id = d.taxi_id
                                    LEFT JOIN users u ON d.user_id = u.id
                                    WHERE q.route_id = ? AND q.status = 'waiting'
                                    ORDER BY q.position";
                        $queue_stmt = $pdo->prepare($queue_sql);
                        $queue_stmt->execute([$route['id']]);
                        $queue = $queue_stmt->fetchAll();
                        
                        if (empty($queue)):
                        ?>
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                            <h5>Queue is empty</h5>
                            <p>Click "Add to Queue" to add taxis</p>
                        </div>
                        <?php else: ?>
                            <?php foreach ($queue as $taxi): ?>
                            <div class="queue-item p-3 border-bottom" data-queue-id="<?= $taxi['id'] ?>" data-position="<?= $taxi['position'] ?>">
                                <div class="row align-items-center">
                                    <div class="col-md-1">
                                        <span class="position-badge badge bg-primary fs-5">#<?= $taxi['position'] ?></span>
                                    </div>
                                    <div class="col-md-2">
                                        <strong><?= htmlspecialchars($taxi['registration_number']) ?></strong>
                                    </div>
                                    <div class="col-md-3">
                                        <i class="bi bi-person"></i> <?= htmlspecialchars($taxi['driver_name'] ?? 'No driver') ?>
                                    </div>
                                    <div class="col-md-2">
                                        <span class="badge bg-secondary">Waiting</span>
                                    </div>
                                    <div class="col-md-2">
                                        <small class="text-muted"><?= date('H:i', strtotime($taxi['entered_queue_at'])) ?></small>
                                    </div>
                                    <div class="col-md-2">
                                        <button class="btn btn-sm btn-danger" onclick="removeFromQueue(<?= $taxi['id'] ?>, <?= $route['id'] ?>)">
                                            <i class="bi bi-x-circle"></i> Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Add to Queue Modal -->
<div class="modal fade" id="addToQueueModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add Taxi to Queue</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addToQueueForm">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="route_select" class="form-label">Select Route *</label>
                            <select class="form-select" id="route_select" name="route_id" required>
                                <option value="">Choose route...</option>
                                <?php foreach ($routes as $route): ?>
                                    <option value="<?= $route['id'] ?>"><?= htmlspecialchars($route['route_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="taxi_select" class="form-label">Select Taxi *</label>
                            <select class="form-select" id="taxi_select" name="taxi_id" required>
                                <option value="">Choose taxi...</option>
                                <?php foreach ($available_taxis as $taxi): ?>
                                    <option value="<?= $taxi['id'] ?>" data-route="<?= $taxi['route_id'] ?>">
                                        <?= htmlspecialchars($taxi['registration_number']) ?> 
                                        (<?= htmlspecialchars($taxi['driver_name'] ?? 'No driver') ?>)
                                        <?= $taxi['route_name'] ? ' - ' . $taxi['route_name'] : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="position" class="form-label">Position (Optional)</label>
                            <input type="number" class="form-control" id="position" name="position" min="1" placeholder="Auto-assign if empty">
                            <small class="text-muted">Leave blank to add at the end</small>
                        </div>
                    </div>
                    
                    <div class="alert alert-info" id="selectedTaxiInfo" style="display: none;">
                        <i class="bi bi-info-circle"></i> <span id="taxiInfoText"></span>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="addToQueue()">Add to Queue</button>
            </div>
        </div>
    </div>
</div>

<!-- Passenger Count Modal -->
<div class="modal fade" id="passengerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-people"></i> Enter Passenger Count</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="passengerRouteId">
                <div class="mb-3">
                    <label for="passengerCount" class="form-label">Number of Passengers</label>
                    <input type="number" class="form-control form-control-lg" id="passengerCount" min="1" max="30" value="15">
                </div>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> Maximum capacity: 30 passengers
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info" onclick="submitPassengerCount()">Save & Continue</button>
            </div>
        </div>
    </div>
</div>

<!-- Reorder Queue Modal -->
<div class="modal fade" id="reorderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-arrow-up-down"></i> Reorder Queue</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Drag and drop to reorder the queue. Click Save when done.</p>
                <ul class="list-group" id="reorderList"></ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="saveReorder()">Save New Order</button>
            </div>
        </div>
    </div>
</div>

<style>
.big-button {
    padding: 15px;
    font-size: 18px;
    font-weight: 600;
    border-radius: 10px;
    transition: transform 0.2s;
}
.big-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.queue-card {
    background: #f8f9fa;
    border-left: 4px solid #0d6efd;
    margin: 5px;
    padding: 15px;
    border-radius: 5px;
    transition: background 0.2s;
}
.queue-card:hover {
    background: #e9ecef;
}
.loading-card {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
}
.queue-item {
    transition: background 0.2s;
}
.queue-item:hover {
    background: #f8f9fa;
}
.position-badge {
    min-width: 50px;
    display: inline-block;
    text-align: center;
}
.route-card {
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    border: 2px solid transparent;
}
.route-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border-color: #007bff;
}
.nav-tabs .nav-link {
    font-weight: 600;
    color: #495057;
}
.nav-tabs .nav-link.active {
    color: #007bff;
    border-bottom: 3px solid #007bff;
}
</style>

<script>
// Global variables
let currentRouteId = null;
let reorderData = [];

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Auto-refresh queue every 30 seconds
    setInterval(refreshQueue, 30000);
    
    // Update taxi info when selecting
    document.getElementById('taxi_select')?.addEventListener('change', function() {
        const taxiId = this.value;
        if (!taxiId) {
            document.getElementById('selectedTaxiInfo').style.display = 'none';
            return;
        }
        
        const option = this.options[this.selectedIndex];
        const infoText = `Selected: ${option.text}`;
        document.getElementById('taxiInfoText').textContent = infoText;
        document.getElementById('selectedTaxiInfo').style.display = 'block';
    });
    
    // Filter taxis by route
    document.getElementById('route_select')?.addEventListener('change', function() {
        const routeId = this.value;
        const taxiSelect = document.getElementById('taxi_select');
        const options = taxiSelect.options;
        
        for (let i = 0; i < options.length; i++) {
            const option = options[i];
            if (option.value === '') continue;
            
            const taxiRoute = option.getAttribute('data-route');
            if (!routeId || taxiRoute == routeId) {
                option.style.display = '';
            } else {
                option.style.display = 'none';
            }
        }
    });
});

// Refresh queue
function refreshQueue() {
    <?php foreach ($routes as $route): ?>
    loadQueue(<?= $route['id'] ?>);
    <?php endforeach; ?>
}

// Load queue for specific route
function loadQueue(routeId) {
    fetch(`../api/get_queue.php?route_id=${routeId}`)
        .then(response => response.json())
        .then(data => {
            updateQueueDisplay(routeId, data);
        });
}

// Update queue display
function updateQueueDisplay(routeId, data) {
    const queueDiv = document.getElementById(`queue-${routeId}`);
    const loadingDiv = document.getElementById(`loading-${routeId}`);
    const queueCount = document.getElementById(`queue-count-${routeId}`);
    const tabBadge = document.querySelector(`#route-${routeId}-tab .queue-count`);
    
    // Update queue count
    if (queueCount) queueCount.textContent = data.queue.length;
    if (tabBadge) tabBadge.textContent = data.queue.length;
    
    // Update loading section
    if (data.loading) {
        loadingDiv.innerHTML = `
            <div class="queue-card loading-card">
                <div class="row align-items-center">
                    <div class="col-md-1">
                        <span class="badge bg-warning text-dark fs-6">LOADING</span>
                    </div>
                    <div class="col-md-2">
                        <strong>${data.loading.registration}</strong>
                    </div>
                    <div class="col-md-3">
                        Driver: ${data.loading.driver_name || 'Unknown'}
                    </div>
                    <div class="col-md-3">
                        <span class="text-muted">Position: #${data.loading.position}</span>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-sm btn-success" onclick="completeLoading(${routeId})">
                            <i class="bi bi-check-circle"></i> Complete Loading
                        </button>
                    </div>
                </div>
            </div>
        `;
    } else {
        loadingDiv.innerHTML = '<div class="text-center text-muted py-3"><i class="bi bi-arrow-repeat"></i> No taxi loading at the moment</div>';
    }
    
    // Update queue list
    if (data.queue.length === 0) {
        queueDiv.innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                <h5>Queue is empty</h5>
                <p>Click "Add to Queue" to add taxis</p>
            </div>
        `;
    } else {
        let html = '';
        data.queue.forEach(taxi => {
            html += `
                <div class="queue-item p-3 border-bottom" data-queue-id="${taxi.id}" data-position="${taxi.position}">
                    <div class="row align-items-center">
                        <div class="col-md-1">
                            <span class="position-badge badge bg-primary fs-5">#${taxi.position}</span>
                        </div>
                        <div class="col-md-2">
                            <strong>${taxi.registration}</strong>
                        </div>
                        <div class="col-md-3">
                            <i class="bi bi-person"></i> ${taxi.driver_name || 'No driver'}
                        </div>
                        <div class="col-md-2">
                            <span class="badge bg-secondary">Waiting</span>
                        </div>
                        <div class="col-md-2">
                            <small class="text-muted">${taxi.entered_time || ''}</small>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-sm btn-danger" onclick="removeFromQueue(${taxi.id}, ${routeId})">
                                <i class="bi bi-x-circle"></i> Remove
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
        queueDiv.innerHTML = html;
    }
}

// Show add to queue modal
function showAddToQueue() {
    new bootstrap.Modal(document.getElementById('addToQueueModal')).show();
}

// Add taxi to queue
function addToQueue() {
    const routeId = document.getElementById('route_select').value;
    const taxiId = document.getElementById('taxi_select').value;
    const position = document.getElementById('position').value;
    
    if (!routeId || !taxiId) {
        alert('Please select both route and taxi');
        return;
    }
    
    fetch('../api/add_to_queue.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            route_id: routeId,
            taxi_id: taxiId,
            position: position || null
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Taxi added to queue successfully');
            bootstrap.Modal.getInstance(document.getElementById('addToQueueModal')).hide();
            loadQueue(routeId);
            document.getElementById('addToQueueForm').reset();
        } else {
            alert('❌ Error: ' + data.message);
        }
    });
}

// Call next taxi
function callNextTaxi(routeId) {
    if (!confirm('Call the next taxi to load?')) return;
    
    fetch('../api/call_next_taxi.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ route_id: routeId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`✅ Next taxi called: ${data.taxi_registration}`);
            loadQueue(routeId);
        } else {
            if (data.message !== 'No taxis in queue') {
                alert('❌ Error: ' + data.message);
            }
        }
    });
}

// Complete loading (move from loading to ready for departure)
function completeLoading(routeId) {
    if (!confirm('Mark this taxi as ready for departure?')) return;
    
    fetch('../api/complete_loading.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ route_id: routeId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Taxi ready for departure');
            loadQueue(routeId);
        } else {
            alert('❌ Error: ' + data.message);
        }
    });
}

// Show passenger modal
function showPassengerModal(routeId) {
    currentRouteId = routeId;
    new bootstrap.Modal(document.getElementById('passengerModal')).show();
}

// Submit passenger count
function submitPassengerCount() {
    const count = document.getElementById('passengerCount').value;
    
    if (!count || count < 1) {
        alert('Please enter valid passenger count');
        return;
    }
    
    fetch('../api/save_passengers.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            route_id: currentRouteId,
            passenger_count: count
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`✅ Passenger count saved: ${count} passengers`);
            bootstrap.Modal.getInstance(document.getElementById('passengerModal')).hide();
            confirmDeparture(currentRouteId);
        } else {
            alert('❌ Error: ' + data.message);
        }
    });
}

// Confirm departure
function confirmDeparture(routeId) {
    if (!confirm('Confirm taxi departure? This will remove it from queue and log the trip.')) return;
    
    fetch('../api/depart_taxi.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ route_id: routeId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`✅ Trip logged successfully! Fare: R ${data.total_fare}`);
            loadQueue(routeId);
        } else {
            alert('❌ Error: ' + data.message);
        }
    });
}

// Remove from queue
function removeFromQueue(queueId, routeId) {
    if (!confirm('Remove this taxi from queue?')) return;
    
    fetch('../api/remove_from_queue.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ queue_id: queueId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Taxi removed from queue');
            loadQueue(routeId);
        } else {
            alert('❌ Error: ' + data.message);
        }
    });
}

// Reorder queue
function reorderQueue(routeId) {
    currentRouteId = routeId;
    
    // Get current queue
    fetch(`../api/get_queue.php?route_id=${routeId}`)
        .then(response => response.json())
        .then(data => {
            if (data.queue.length === 0) {
                alert('Queue is empty');
                return;
            }
            
            // Build reorder list
            let html = '';
            data.queue.forEach((taxi, index) => {
                html += `
                    <li class="list-group-item" data-id="${taxi.id}">
                        <i class="bi bi-grip-vertical me-2"></i>
                        <strong>#${index + 1}</strong> - ${taxi.registration} (${taxi.driver_name || 'No driver'})
                    </li>
                `;
            });
            
            document.getElementById('reorderList').innerHTML = html;
            
            // Initialize sortable
            new Sortable(document.getElementById('reorderList'), {
                animation: 150,
                handle: '.bi-grip-vertical'
            });
            
            new bootstrap.Modal(document.getElementById('reorderModal')).show();
        });
}

// Save reorder
function saveReorder() {
    const items = document.querySelectorAll('#reorderList .list-group-item');
    const newOrder = [];
    
    items.forEach((item, index) => {
        newOrder.push({
            id: item.dataset.id,
            position: index + 1
        });
    });
    
    fetch('../api/reorder_queue.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            route_id: currentRouteId,
            order: newOrder
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Queue reordered successfully');
            bootstrap.Modal.getInstance(document.getElementById('reorderModal')).hide();
            loadQueue(currentRouteId);
        } else {
            alert('❌ Error: ' + data.message);
        }
    });
}

// Scroll to route
function scrollToRoute(routeId) {
    const tab = document.getElementById(`route-${routeId}-tab`);
    if (tab) {
        tab.click();
        tab.scrollIntoView({ behavior: 'smooth' });
    }
}
</script>

<!-- Include Sortable library -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<?php
// Get the content
$content = ob_get_clean();

// Include the marshal layout
require_once '../layouts/marshal_layout.php';
?>