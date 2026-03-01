<?php
// admin/manage_taxis.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('admin');

// Set page title
$page_title = 'Manage Taxis';

// Start output buffering
ob_start();

// Get admin's full name for added_by
$admin_name = $_SESSION['full_name'];

// Get all taxis with their details
try {
    $taxis_sql = "SELECT t.*, 
                  o.id as owner_id, u.full_name as owner_name,
                  r.route_name, r.fare_amount, r.association_levy,
                  d.id as driver_id, du.full_name as driver_name,
                  (SELECT COUNT(*) FROM trips WHERE taxi_id = t.id AND DATE(departed_at) = CURDATE()) as today_trips,
                  (SELECT SUM(total_fare) FROM trips WHERE taxi_id = t.id AND DATE(departed_at) = CURDATE()) as today_revenue,
                  (SELECT SUM(total_fare - association_levy) FROM trips WHERE taxi_id = t.id) as total_earnings,
                  (SELECT COUNT(*) FROM trips WHERE taxi_id = t.id) as total_trips
                  FROM taxis t
                  LEFT JOIN owners o ON t.owner_id = o.id
                  LEFT JOIN users u ON o.user_id = u.id
                  LEFT JOIN routes r ON t.route_id = r.id
                  LEFT JOIN drivers d ON t.id = d.taxi_id
                  LEFT JOIN users du ON d.user_id = du.id
                  ORDER BY t.created_at DESC";
    
    $taxis = $pdo->query($taxis_sql)->fetchAll();
    
    // Get all owners for dropdown
    $owners_sql = "SELECT o.id, u.full_name FROM owners o 
                   JOIN users u ON o.user_id = u.id 
                   WHERE u.is_active = 1 
                   ORDER BY u.full_name";
    $owners = $pdo->query($owners_sql)->fetchAll();
    
    // Get all routes for dropdown
    $routes_sql = "SELECT id, route_name, fare_amount FROM routes WHERE is_active = 1 ORDER BY route_name";
    $routes = $pdo->query($routes_sql)->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error fetching taxis: " . $e->getMessage());
    $taxis = [];
    $owners = [];
    $routes = [];
}
?>

<!-- Add New Taxi Button -->
<div class="mb-3">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaxiModal">
        <i class="bi bi-plus-circle"></i> Add New Taxi
    </button>
</div>

<!-- Taxis Table -->
<div class="card">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0"><i class="bi bi-truck"></i> All Taxis</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Registration</th>
                        <th>Owner</th>
                        <th>Driver</th>
                        <th>Route</th>
                        <th>Status</th>
                        <th>Today Trips</th>
                        <th>Today Revenue</th>
                        <th>Total Trips</th>
                        <th>Total Earnings</th>
                        <th>Added By</th>
                        <th>Added Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($taxis)): ?>
                        <tr>
                            <td colspan="13" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                                No taxis found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($taxis as $taxi): ?>
                        <tr>
                            <td><?= $taxi['id'] ?></td>
                            <td><strong><?= htmlspecialchars($taxi['registration_number']) ?></strong></td>
                            <td><?= htmlspecialchars($taxi['owner_name'] ?? 'N/A') ?></td>
                            <td>
                                <?php if ($taxi['driver_name']): ?>
                                    <span class="badge bg-info"><?= htmlspecialchars($taxi['driver_name']) ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">No Driver</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($taxi['route_name'] ?? 'Not assigned') ?></td>
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
                            <td><span class="badge bg-primary"><?= $taxi['today_trips'] ?? 0 ?></span></td>
                            <td>R <?= number_format($taxi['today_revenue'] ?? 0, 2) ?></td>
                            <td><?= $taxi['total_trips'] ?? 0 ?></td>
                            <td>R <?= number_format($taxi['total_earnings'] ?? 0, 2) ?></td>
                            <td><small class="text-muted"><?= htmlspecialchars($taxi['added_by'] ?? 'System') ?></small></td>
                            <td><small><?= $taxi['added_at'] ? date('d/m/Y', strtotime($taxi['added_at'])) : 'N/A' ?></small></td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="editTaxi(<?= $taxi['id'] ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteTaxi(<?= $taxi['id'] ?>)">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <button class="btn btn-sm btn-info" onclick="viewTaxiDetails(<?= $taxi['id'] ?>)">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                        Status
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="#" onclick="updateStatus(<?= $taxi['id'] ?>, 'active')">Active</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="updateStatus(<?= $taxi['id'] ?>, 'on_trip')">On Trip</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="updateStatus(<?= $taxi['id'] ?>, 'off_rank')">Off Rank</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="updateStatus(<?= $taxi['id'] ?>, 'maintenance')">Maintenance</a></li>
                                    </ul>
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

<!-- Add Taxi Modal -->
<div class="modal fade" id="addTaxiModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add New Taxi</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="process/process_taxi.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="added_by" value="<?= htmlspecialchars($admin_name) ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="registration_number" class="form-label">Registration Number *</label>
                            <input type="text" class="form-control" id="registration_number" name="registration_number" 
                                   required placeholder="e.g., ZN 123-456">
                            <small class="text-muted">Format: Province Code + Numbers (e.g., ZN 123-456)</small>
                        </div>
                        <div class="col-md-6">
                            <label for="owner_id" class="form-label">Select Owner *</label>
                            <select class="form-select" id="owner_id" name="owner_id" required>
                                <option value="">Select Owner</option>
                                <?php foreach ($owners as $owner): ?>
                                    <option value="<?= $owner['id'] ?>"><?= htmlspecialchars($owner['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
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
                        <div class="col-md-6">
                            <label for="status" class="form-label">Initial Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active">Active</option>
                                <option value="off_rank">Off Rank</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> This taxi will be registered by <strong><?= htmlspecialchars($admin_name) ?></strong>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> After adding the taxi, you can assign a driver from the "Manage Drivers" section.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Taxi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Taxi Modal -->
<div class="modal fade" id="editTaxiModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Taxi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="process/process_taxi.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="taxi_id" id="edit_taxi_id">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_registration_number" class="form-label">Registration Number *</label>
                            <input type="text" class="form-control" id="edit_registration_number" name="registration_number" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_owner_id" class="form-label">Select Owner *</label>
                            <select class="form-select" id="edit_owner_id" name="owner_id" required>
                                <option value="">Select Owner</option>
                                <?php foreach ($owners as $owner): ?>
                                    <option value="<?= $owner['id'] ?>"><?= htmlspecialchars($owner['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_route_id" class="form-label">Assign Route</label>
                            <select class="form-select" id="edit_route_id" name="route_id">
                                <option value="">No Route</option>
                                <?php foreach ($routes as $route): ?>
                                    <option value="<?= $route['id'] ?>">
                                        <?= htmlspecialchars($route['route_name']) ?> (R <?= number_format($route['fare_amount'], 2) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-select" id="edit_status" name="status">
                                <option value="active">Active</option>
                                <option value="on_trip">On Trip</option>
                                <option value="off_rank">Off Rank</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Last updated by: <span id="edit_added_by"></span>
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

<!-- View Taxi Details Modal -->
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
                                <th>Owner:</th>
                                <td id="view_owner"></td>
                            </tr>
                            <tr>
                                <th>Current Driver:</th>
                                <td id="view_driver"></td>
                            </tr>
                            <tr>
                                <th>Route:</th>
                                <td id="view_route"></td>
                            </tr>
                            <tr>
                                <th>Fare Amount:</th>
                                <td id="view_fare"></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th>Status:</th>
                                <td id="view_status"></td>
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
                
                <div class="row mt-3">
                    <div class="col-md-12">
                        <table class="table table-sm">
                            <tr>
                                <th>Added By:</th>
                                <td id="view_added_by"></td>
                                <th>Added Date:</th>
                                <td id="view_added_at"></td>
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

<script>
// Edit taxi function
function editTaxi(id) {
    fetch(`get_taxi.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('edit_taxi_id').value = data.id;
            document.getElementById('edit_registration_number').value = data.registration_number;
            document.getElementById('edit_owner_id').value = data.owner_id;
            document.getElementById('edit_route_id').value = data.route_id || '';
            document.getElementById('edit_status').value = data.status;
            document.getElementById('edit_added_by').textContent = data.added_by || 'System';
            
            new bootstrap.Modal(document.getElementById('editTaxiModal')).show();
        });
}

// View taxi details
function viewTaxiDetails(id) {
    fetch(`get_taxi.php?id=${id}&details=1`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('view_registration').textContent = data.registration_number;
            document.getElementById('view_owner').textContent = data.owner_name;
            document.getElementById('view_driver').textContent = data.driver_name || 'No driver assigned';
            document.getElementById('view_route').textContent = data.route_name || 'Not assigned';
            document.getElementById('view_fare').textContent = data.fare_amount ? 'R ' + parseFloat(data.fare_amount).toFixed(2) : 'N/A';
            
            let statusBadge = '';
            switch(data.status) {
                case 'active': statusBadge = '<span class="badge bg-success">Active</span>'; break;
                case 'on_trip': statusBadge = '<span class="badge bg-warning">On Trip</span>'; break;
                case 'off_rank': statusBadge = '<span class="badge bg-secondary">Off Rank</span>'; break;
                case 'maintenance': statusBadge = '<span class="badge bg-danger">Maintenance</span>'; break;
                default: statusBadge = '<span class="badge bg-dark">Unknown</span>';
            }
            document.getElementById('view_status').innerHTML = statusBadge;
            
            document.getElementById('view_today_trips').textContent = data.today_trips || 0;
            document.getElementById('view_today_revenue').textContent = 'R ' + (parseFloat(data.today_revenue) || 0).toFixed(2);
            document.getElementById('view_total_trips').textContent = data.total_trips || 0;
            document.getElementById('view_total_earnings').textContent = 'R ' + (parseFloat(data.total_earnings) || 0).toFixed(2);
            
            document.getElementById('view_added_by').textContent = data.added_by || 'System';
            document.getElementById('view_added_at').textContent = data.added_at ? new Date(data.added_at).toLocaleString() : 'N/A';
            
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
                    </tr>`;
                });
            } else {
                tripsHtml = '<tr><td colspan="6" class="text-center">No trips found</td></tr>';
            }
            document.querySelector('#view_trips_table tbody').innerHTML = tripsHtml;
            
            new bootstrap.Modal(document.getElementById('viewTaxiModal')).show();
        });
}

// Delete taxi function
function deleteTaxi(id) {
    if (confirm('⚠️ Are you sure you want to delete this taxi?\n\nThis action cannot be undone!')) {
        window.location.href = `process/process_taxi.php?action=delete&id=${id}`;
    }
}

// Update status function
function updateStatus(id, status) {
    if (confirm(`Change taxi status to ${status}?`)) {
        window.location.href = `process/process_taxi.php?action=status&id=${id}&status=${status}`;
    }
}
</script>

<?php
// Get the content
$content = ob_get_clean();

// Include the admin layout
require_once '../layouts/admin_layout.php';
?>