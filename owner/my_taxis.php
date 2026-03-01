<?php
// owner/my_taxis.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('owner');

// Set page title
$page_title = 'My Taxis';

// Start output buffering
ob_start();

$owner_id = $_SESSION['owner_id'];

try {
    // Get owner's taxis with details
    $taxis_sql = "SELECT t.*, 
                  r.route_name, r.fare_amount,
                  d.id as driver_id, u.full_name as driver_name, u.phone_number as driver_phone,
                  (SELECT COUNT(*) FROM trips WHERE taxi_id = t.id AND DATE(departed_at) = CURDATE()) as today_trips,
                  (SELECT SUM(total_fare) FROM trips WHERE taxi_id = t.id AND DATE(departed_at) = CURDATE()) as today_revenue,
                  (SELECT COUNT(*) FROM trips WHERE taxi_id = t.id) as total_trips,
                  (SELECT SUM(total_fare - association_levy) FROM trips WHERE taxi_id = t.id) as total_earnings
                  FROM taxis t
                  LEFT JOIN routes r ON t.route_id = r.id
                  LEFT JOIN drivers d ON t.id = d.taxi_id
                  LEFT JOIN users u ON d.user_id = u.id
                  WHERE t.owner_id = ?
                  ORDER BY t.created_at DESC";
    
    $stmt = $pdo->prepare($taxis_sql);
    $stmt->execute([$owner_id]);
    $taxis = $stmt->fetchAll();
    
    // Get available routes for assigning
    $routes_sql = "SELECT id, route_name, fare_amount FROM routes WHERE is_active = 1 ORDER BY route_name";
    $routes = $pdo->query($routes_sql)->fetchAll();
    
    // Get available drivers (unassigned or assigned to owner's taxis)
    $drivers_sql = "SELECT d.id, u.full_name, u.phone_number, d.taxi_id, t.registration_number
                    FROM drivers d
                    JOIN users u ON d.user_id = u.id
                    LEFT JOIN taxis t ON d.taxi_id = t.id
                    WHERE d.owner_id = ? AND (d.taxi_id IS NULL OR d.taxi_id IN (SELECT id FROM taxis WHERE owner_id = ?))
                    ORDER BY u.full_name";
    $stmt = $pdo->prepare($drivers_sql);
    $stmt->execute([$owner_id, $owner_id]);
    $available_drivers = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("My taxis error: " . $e->getMessage());
    $taxis = [];
    $routes = [];
    $available_drivers = [];
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-truck"></i> My Taxis</h2>
        <div>
            <button class="btn btn-primary" onclick="addTaxi()">
                <i class="bi bi-plus-circle"></i> Register New Taxi
            </button>
            <button class="btn btn-success" onclick="refreshStats()">
                <i class="bi bi-arrow-repeat"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <h6 class="card-title text-white-50">Total Taxis</h6>
                    <h3 class="mb-0"><?= count($taxis) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h6 class="card-title text-white-50">Active Taxis</h6>
                    <h3 class="mb-0">
                        <?= count(array_filter($taxis, function($t) { return $t['status'] == 'active'; })) ?>
                    </h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <h6 class="card-title text-white-50">On Trip</h6>
                    <h3 class="mb-0">
                        <?= count(array_filter($taxis, function($t) { return $t['status'] == 'on_trip'; })) ?>
                    </h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <h6 class="card-title text-white-50">Today's Revenue</h6>
                    <h3 class="mb-0">R <?= number_format(array_sum(array_column($taxis, 'today_revenue')), 2) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Taxis Table -->
    <div class="card">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-list"></i> My Taxi Fleet</h5>
            <div>
                <input type="text" class="form-control form-control-sm" placeholder="Search taxis..." id="searchInput">
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="taxisTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Registration</th>
                            <th>Route</th>
                            <th>Driver</th>
                            <th>Status</th>
                            <th>Today Trips</th>
                            <th>Today Revenue</th>
                            <th>Total Trips</th>
                            <th>Total Earnings</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($taxis)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-5">
                                    <i class="bi bi-truck fs-1 text-muted d-block mb-3"></i>
                                    <h5 class="text-muted">No Taxis Found</h5>
                                    <p class="text-muted">You haven't registered any taxis yet.</p>
                                    <button class="btn btn-primary" onclick="addTaxi()">
                                        <i class="bi bi-plus-circle"></i> Register Your First Taxi
                                    </button>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($taxis as $index => $taxi): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($taxi['registration_number']) ?></strong>
                                    <br>
                                    <small class="text-muted">ID: <?= $taxi['id'] ?></small>
                                </td>
                                <td>
                                    <?php if ($taxi['route_name']): ?>
                                        <span class="badge bg-info"><?= htmlspecialchars($taxi['route_name']) ?></span>
                                        <br>
                                        <small>R <?= number_format($taxi['fare_amount'] ?? 0, 2) ?></small>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($taxi['driver_name']): ?>
                                        <i class="bi bi-person"></i> <?= htmlspecialchars($taxi['driver_name']) ?>
                                        <br>
                                        <small class="text-muted"><?= htmlspecialchars($taxi['driver_phone'] ?? '') ?></small>
                                    <?php else: ?>
                                        <span class="badge bg-warning">No driver</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'active' => 'success',
                                        'on_trip' => 'warning',
                                        'off_rank' => 'secondary',
                                        'maintenance' => 'danger'
                                    ];
                                    $color = $status_colors[$taxi['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $color ?>"><?= ucfirst($taxi['status']) ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary"><?= $taxi['today_trips'] ?? 0 ?></span>
                                </td>
                                <td>R <?= number_format($taxi['today_revenue'] ?? 0, 2) ?></td>
                                <td class="text-center"><?= $taxi['total_trips'] ?? 0 ?></td>
                                <td><strong>R <?= number_format($taxi['total_earnings'] ?? 0, 2) ?></strong></td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-info" onclick="viewTaxi(<?= $taxi['id'] ?>)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-warning" onclick="editTaxi(<?= $taxi['id'] ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-success" onclick="assignDriver(<?= $taxi['id'] ?>)">
                                            <i class="bi bi-person-plus"></i>
                                        </button>
                                        <button class="btn btn-sm btn-primary" onclick="viewTrips(<?= $taxi['id'] ?>)">
                                            <i class="bi bi-clock-history"></i>
                                        </button>
                                    </div>
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

<!-- Add Taxi Modal -->
<div class="modal fade" id="addTaxiModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Register New Taxi</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="process/process_taxi.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="owner_id" value="<?= $owner_id ?>">
                    
                    <div class="mb-3">
                        <label for="registration_number" class="form-label">Registration Number *</label>
                        <input type="text" class="form-control" id="registration_number" name="registration_number" 
                               required placeholder="e.g., ZN 123-456">
                        <small class="text-muted">Format: Province Code + Numbers (e.g., ZN 123-456, CA 123456)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="route_id" class="form-label">Assign Route</label>
                        <select class="form-select" id="route_id" name="route_id">
                            <option value="">No Route (Assign Later)</option>
                            <?php foreach ($routes as $route): ?>
                                <option value="<?= $route['id'] ?>">
                                    <?= htmlspecialchars($route['route_name']) ?> (R <?= number_format($route['fare_amount'], 2) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Initial Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active">Active - Ready for Queue</option>
                            <option value="off_rank">Off Rank - Not Available</option>
                            <option value="maintenance">Maintenance - Under Repair</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> After adding the taxi, you can assign a driver from your available drivers.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Register Taxi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Taxi Modal -->
<div class="modal fade" id="viewTaxiModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-eye"></i> Taxi Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th>Registration:</th>
                                <td id="view_registration"></td>
                            </tr>
                            <tr>
                                <th>Route:</th>
                                <td id="view_route"></td>
                            </tr>
                            <tr>
                                <th>Fare Amount:</th>
                                <td id="view_fare"></td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td id="view_status"></td>
                            </tr>
                            <tr>
                                <th>Added Date:</th>
                                <td id="view_added"></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th>Driver:</th>
                                <td id="view_driver"></td>
                            </tr>
                            <tr>
                                <th>Driver Phone:</th>
                                <td id="view_driver_phone"></td>
                            </tr>
                            <tr>
                                <th>Today's Trips:</th>
                                <td id="view_today_trips"></td>
                            </tr>
                            <tr>
                                <th>Today's Revenue:</th>
                                <td id="view_today_revenue"></td>
                            </tr>
                            <tr>
                                <th>Total Trips:</th>
                                <td id="view_total_trips"></td>
                            </tr>
                            <tr>
                                <th>Total Earnings:</th>
                                <td id="view_total_earnings"></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <h6 class="mt-3">Recent Trips</h6>
                <div class="table-responsive">
                    <table class="table table-sm" id="view_trips_table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Driver</th>
                                <th>Route</th>
                                <th>Passengers</th>
                                <th>Fare</th>
                                <th>Levy</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Taxi Modal -->
<div class="modal fade" id="editTaxiModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Taxi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="process/process_taxi.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="taxi_id" id="edit_taxi_id">
                    
                    <div class="mb-3">
                        <label for="edit_registration" class="form-label">Registration Number *</label>
                        <input type="text" class="form-control" id="edit_registration" name="registration_number" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_route" class="form-label">Route</label>
                        <select class="form-select" id="edit_route" name="route_id">
                            <option value="">No Route</option>
                            <?php foreach ($routes as $route): ?>
                                <option value="<?= $route['id'] ?>">
                                    <?= htmlspecialchars($route['route_name']) ?> (R <?= number_format($route['fare_amount'], 2) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_status" class="form-label">Status</label>
                        <select class="form-select" id="edit_status" name="status">
                            <option value="active">Active</option>
                            <option value="on_trip">On Trip</option>
                            <option value="off_rank">Off Rank</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update Taxi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Driver Modal -->
<div class="modal fade" id="assignDriverModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-person-plus"></i> Assign Driver</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="process/process_taxi.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="assign_driver">
                    <input type="hidden" name="taxi_id" id="assign_taxi_id">
                    
                    <div class="mb-3">
                        <label for="driver_id" class="form-label">Select Driver</label>
                        <select class="form-select" id="driver_id" name="driver_id" required>
                            <option value="">Choose a driver...</option>
                            <?php foreach ($available_drivers as $driver): ?>
                                <option value="<?= $driver['id'] ?>">
                                    <?= htmlspecialchars($driver['full_name']) ?> 
                                    <?= $driver['taxi_id'] ? '(Currently assigned to ' . $driver['registration_number'] . ')' : '(Available)' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> If the driver is already assigned to another taxi, they will be moved to this taxi.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Assign Driver</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Trips Modal -->
<div class="modal fade" id="tripsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-clock-history"></i> Trip History</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6 id="trips_taxi_info" class="mb-3"></h6>
                <div class="table-responsive">
                    <table class="table table-sm" id="trips_table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Driver</th>
                                <th>Route</th>
                                <th>Passengers</th>
                                <th>Fare</th>
                                <th>Levy</th>
                                <th>Payout</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
.table td {
    vertical-align: middle;
}
.card {
    border: none;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    margin-bottom: 20px;
}
.badge {
    font-size: 11px;
    padding: 5px 8px;
}
.btn-group .btn {
    margin-right: 2px;
}
</style>

<script>
// Search functionality
document.getElementById('searchInput')?.addEventListener('keyup', function() {
    const searchText = this.value.toLowerCase();
    const table = document.getElementById('taxisTable');
    const rows = table.getElementsByTagName('tr');
    
    for (let i = 1; i < rows.length; i++) {
        const row = rows[i];
        const registration = row.cells[1]?.textContent.toLowerCase() || '';
        const driver = row.cells[3]?.textContent.toLowerCase() || '';
        const route = row.cells[2]?.textContent.toLowerCase() || '';
        
        if (registration.includes(searchText) || driver.includes(searchText) || route.includes(searchText)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    }
});

// Add taxi function
function addTaxi() {
    new bootstrap.Modal(document.getElementById('addTaxiModal')).show();
}

// View taxi details
function viewTaxi(id) {
    fetch(`get_taxi_details.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('view_registration').textContent = data.registration_number;
            document.getElementById('view_route').textContent = data.route_name || 'Not assigned';
            document.getElementById('view_fare').textContent = data.fare_amount ? 'R ' + parseFloat(data.fare_amount).toFixed(2) : 'N/A';
            
            let statusBadge = '';
            switch(data.status) {
                case 'active': statusBadge = '<span class="badge bg-success">Active</span>'; break;
                case 'on_trip': statusBadge = '<span class="badge bg-warning">On Trip</span>'; break;
                case 'off_rank': statusBadge = '<span class="badge bg-secondary">Off Rank</span>'; break;
                case 'maintenance': statusBadge = '<span class="badge bg-danger">Maintenance</span>'; break;
            }
            document.getElementById('view_status').innerHTML = statusBadge;
            
            document.getElementById('view_added').textContent = new Date(data.created_at).toLocaleDateString();
            document.getElementById('view_driver').textContent = data.driver_name || 'No driver assigned';
            document.getElementById('view_driver_phone').textContent = data.driver_phone || 'N/A';
            document.getElementById('view_today_trips').textContent = data.today_trips || 0;
            document.getElementById('view_today_revenue').textContent = 'R ' + (parseFloat(data.today_revenue) || 0).toFixed(2);
            document.getElementById('view_total_trips').textContent = data.total_trips || 0;
            document.getElementById('view_total_earnings').textContent = 'R ' + (parseFloat(data.total_earnings) || 0).toFixed(2);
            
            // Populate trips table
            let tripsHtml = '';
            if (data.trips && data.trips.length > 0) {
                data.trips.forEach(trip => {
                    tripsHtml += `<tr>
                        <td>${new Date(trip.departed_at).toLocaleString()}</td>
                        <td>${trip.driver_name}</td>
                        <td>${trip.route_name}</td>
                        <td>${trip.passenger_count}</td>
                        <td>R ${parseFloat(trip.total_fare).toFixed(2)}</td>
                        <td>R ${parseFloat(trip.association_levy).toFixed(2)}</td>
                        <td>R ${parseFloat(trip.owner_payout).toFixed(2)}</td>
                    </tr>`;
                });
            } else {
                tripsHtml = '<tr><td colspan="7" class="text-center">No trips found</td></tr>';
            }
            document.querySelector('#view_trips_table tbody').innerHTML = tripsHtml;
            
            new bootstrap.Modal(document.getElementById('viewTaxiModal')).show();
        });
}

// Edit taxi function
function editTaxi(id) {
    fetch(`get_taxi_details.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('edit_taxi_id').value = data.id;
            document.getElementById('edit_registration').value = data.registration_number;
            document.getElementById('edit_route').value = data.route_id || '';
            document.getElementById('edit_status').value = data.status;
            
            new bootstrap.Modal(document.getElementById('editTaxiModal')).show();
        });
}

// Assign driver function
function assignDriver(taxiId) {
    document.getElementById('assign_taxi_id').value = taxiId;
    new bootstrap.Modal(document.getElementById('assignDriverModal')).show();
}

// View trips function
function viewTrips(taxiId) {
    fetch(`get_taxi_trips.php?id=${taxiId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('trips_taxi_info').textContent = `Trip History for Taxi: ${data.registration}`;
            
            let tripsHtml = '';
            if (data.trips && data.trips.length > 0) {
                data.trips.forEach(trip => {
                    tripsHtml += `<tr>
                        <td>${new Date(trip.departed_at).toLocaleString()}</td>
                        <td>${trip.driver_name}</td>
                        <td>${trip.route_name}</td>
                        <td>${trip.passenger_count}</td>
                        <td>R ${parseFloat(trip.total_fare).toFixed(2)}</td>
                        <td>R ${parseFloat(trip.association_levy).toFixed(2)}</td>
                        <td>R ${parseFloat(trip.owner_payout).toFixed(2)}</td>
                    </tr>`;
                });
            } else {
                tripsHtml = '<tr><td colspan="7" class="text-center">No trips found</td></tr>';
            }
            document.querySelector('#trips_table tbody').innerHTML = tripsHtml;
            
            new bootstrap.Modal(document.getElementById('tripsModal')).show();
        });
}

// Refresh stats
function refreshStats() {
    window.location.reload();
}
</script>

<?php
// Get the content
$content = ob_get_clean();

// Include the owner layout
require_once '../layouts/owner_layout.php';
?>