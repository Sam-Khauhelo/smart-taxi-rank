<?php
// admin/manage_drivers.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('admin');

// Set page title
$page_title = 'Manage Drivers';

// Start output buffering
ob_start();

// Get admin's full name for added_by
$admin_name = $_SESSION['full_name'];

// Get all drivers with their details
try {
    $drivers_sql = "SELECT d.*, u.full_name, u.phone_number, u.username, u.is_active, u.created_at,
                   o.id as owner_id, ou.full_name as owner_name,
                   t.id as taxi_id, t.registration_number, r.route_name,
                   (SELECT COUNT(*) FROM trips WHERE driver_id = d.id AND DATE(departed_at) = CURDATE()) as today_trips,
                   (SELECT SUM(total_fare) FROM trips WHERE driver_id = d.id AND DATE(departed_at) = CURDATE()) as today_earnings
                   FROM drivers d
                   JOIN users u ON d.user_id = u.id
                   LEFT JOIN owners o ON d.owner_id = o.id
                   LEFT JOIN users ou ON o.user_id = ou.id
                   LEFT JOIN taxis t ON d.taxi_id = t.id
                   LEFT JOIN routes r ON t.route_id = r.id
                   ORDER BY u.full_name";
    
    $drivers = $pdo->query($drivers_sql)->fetchAll();
    
    // Get all owners for dropdown
    $owners_sql = "SELECT o.id, u.full_name FROM owners o JOIN users u ON o.user_id = u.id WHERE u.is_active = 1 ORDER BY u.full_name";
    $owners = $pdo->query($owners_sql)->fetchAll();
    
    // Get all taxis for dropdown (only unassigned or currently assigned to this driver)
    $taxis_sql = "SELECT t.*, r.route_name FROM taxis t 
                  LEFT JOIN routes r ON t.route_id = r.id 
                  WHERE t.status != 'off_rank' 
                  ORDER BY t.registration_number";
    $taxis = $pdo->query($taxis_sql)->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error fetching drivers: " . $e->getMessage());
    $drivers = [];
    $owners = [];
    $taxis = [];
}
?>

<!-- Add New Driver Button -->
<div class="mb-3">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDriverModal">
        <i class="bi bi-plus-circle"></i> Add New Driver
    </button>
</div>

<!-- Drivers Table -->
<div class="card">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0"><i class="bi bi-person-badge"></i> All Drivers</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Owner</th>
                        <th>Assigned Taxi</th>
                        <th>Route</th>
                        <th>License Expiry</th>
                        <th>Employment</th>
                        <th>Rate</th>
                        <th>Today's Trips</th>
                        <th>Today's Earnings</th>
                        <th>Added By</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($drivers)): ?>
                        <tr>
                            <td colspan="14" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                                No drivers found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($drivers as $driver): ?>
                        <tr>
                            <td><?= $driver['id'] ?></td>
                            <td><strong><?= htmlspecialchars($driver['full_name']) ?></strong></td>
                            <td><?= htmlspecialchars($driver['phone_number']) ?></td>
                            <td><?= htmlspecialchars($driver['owner_name'] ?? 'N/A') ?></td>
                            <td>
                                <?php if ($driver['registration_number']): ?>
                                    <span class="badge bg-info"><?= htmlspecialchars($driver['registration_number']) ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($driver['route_name'] ?? 'N/A') ?></td>
                            <td>
                                <?php 
                                $expiry = strtotime($driver['license_expiry_date']);
                                $now = time();
                                $days_left = ceil(($expiry - $now) / (60 * 60 * 24));
                                
                                if ($expiry < $now):
                                ?>
                                    <span class="badge bg-danger">Expired</span>
                                <?php elseif ($days_left <= 30): ?>
                                    <span class="badge bg-warning"><?= $days_left ?> days left</span>
                                <?php else: ?>
                                    <span class="badge bg-success"><?= date('d M Y', $expiry) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= ucfirst($driver['employment_type'] ?? 'N/A') ?></td>
                            <td>
                                <?php if ($driver['employment_type'] == 'commission'): ?>
                                    <?= $driver['payment_rate'] ?>%
                                <?php else: ?>
                                    R <?= number_format($driver['payment_rate'] ?? 0, 2) ?>/day
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-primary"><?= $driver['today_trips'] ?? 0 ?></span></td>
                            <td>R <?= number_format($driver['today_earnings'] ?? 0, 2) ?></td>
                            <td><small class="text-muted"><?= htmlspecialchars($driver['added_by'] ?? 'System') ?></small></td>
                            <td>
                                <?php if ($driver['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="editDriver(<?= $driver['id'] ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteDriver(<?= $driver['id'] ?>)">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <button class="btn btn-sm btn-info" onclick="viewDriverDetails(<?= $driver['id'] ?>)">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <?php if ($driver['is_active']): ?>
                                    <button class="btn btn-sm btn-secondary" onclick="toggleStatus(<?= $driver['id'] ?>, 0)">
                                        <i class="bi bi-eye-slash"></i>
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-success" onclick="toggleStatus(<?= $driver['id'] ?>, 1)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Driver Modal -->
<div class="modal fade" id="addDriverModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add New Driver</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="process/process_driver.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="added_by" value="<?= htmlspecialchars($admin_name) ?>">
                    
                    <h6 class="mb-3">User Account Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="full_name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="phone_number" class="form-label">Phone Number *</label>
                            <input type="tel" class="form-control" id="phone_number" name="phone_number" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="username" class="form-label">Username *</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                    </div>
                    
                    <h6 class="mb-3 mt-4">Driver Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="driver_id_number" class="form-label">ID Number *</label>
                            <input type="text" class="form-control" id="driver_id_number" name="driver_id_number" required>
                        </div>
                        <div class="col-md-4">
                            <label for="license_expiry" class="form-label">License Expiry Date *</label>
                            <input type="date" class="form-control" id="license_expiry" name="license_expiry" required>
                        </div>
                        <div class="col-md-4">
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
                        <div class="col-md-4">
                            <label for="taxi_id" class="form-label">Assign Taxi</label>
                            <select class="form-select" id="taxi_id" name="taxi_id">
                                <option value="">No Taxi (Assign Later)</option>
                                <?php foreach ($taxis as $taxi): ?>
                                    <option value="<?= $taxi['id'] ?>">
                                        <?= htmlspecialchars($taxi['registration_number']) ?> 
                                        (<?= htmlspecialchars($taxi['route_name'] ?? 'No Route') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="employment_type" class="form-label">Employment Type *</label>
                            <select class="form-select" id="employment_type" name="employment_type" required>
                                <option value="">Select Type</option>
                                <option value="commission">Commission (%)</option>
                                <option value="wage">Daily Wage (R)</option>
                                <option value="rental">Rental (R/day)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="payment_rate" class="form-label">Payment Rate *</label>
                            <input type="number" step="0.01" class="form-control" id="payment_rate" name="payment_rate" required>
                            <small class="text-muted" id="rate_help">Percentage or amount per day</small>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="is_active" class="form-label">Status</label>
                            <select class="form-select" id="is_active" name="is_active">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> This driver will be registered by <strong><?= htmlspecialchars($admin_name) ?></strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Driver</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Driver Modal -->
<div class="modal fade" id="editDriverModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Driver</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="process/process_driver.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="driver_id" id="edit_driver_id">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <h6 class="mb-3">User Account Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_full_name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="edit_full_name" name="full_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_phone_number" class="form-label">Phone Number *</label>
                            <input type="tel" class="form-control" id="edit_phone_number" name="phone_number" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="edit_username" readonly class="bg-light">
                            <small class="text-muted">Username cannot be changed</small>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_password" class="form-label">New Password (leave blank to keep current)</label>
                            <input type="password" class="form-control" id="edit_password" name="password">
                            <small class="text-muted">Minimum 6 characters if changing</small>
                        </div>
                    </div>
                    
                    <h6 class="mb-3 mt-4">Driver Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_driver_id_number" class="form-label">ID Number *</label>
                            <input type="text" class="form-control" id="edit_driver_id_number" name="driver_id_number" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_license_expiry" class="form-label">License Expiry Date *</label>
                            <input type="date" class="form-control" id="edit_license_expiry" name="license_expiry" required>
                        </div>
                        <div class="col-md-4">
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
                        <div class="col-md-4">
                            <label for="edit_taxi_id" class="form-label">Assign Taxi</label>
                            <select class="form-select" id="edit_taxi_id" name="taxi_id">
                                <option value="">No Taxi (Unassigned)</option>
                                <?php foreach ($taxis as $taxi): ?>
                                    <option value="<?= $taxi['id'] ?>">
                                        <?= htmlspecialchars($taxi['registration_number']) ?> 
                                        (<?= htmlspecialchars($taxi['route_name'] ?? 'No Route') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_employment_type" class="form-label">Employment Type *</label>
                            <select class="form-select" id="edit_employment_type" name="employment_type" required>
                                <option value="commission">Commission (%)</option>
                                <option value="wage">Daily Wage (R)</option>
                                <option value="rental">Rental (R/day)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_payment_rate" class="form-label">Payment Rate *</label>
                            <input type="number" step="0.01" class="form-control" id="edit_payment_rate" name="payment_rate" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_is_active" class="form-label">Status</label>
                            <select class="form-select" id="edit_is_active" name="is_active">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update Driver</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Driver Details Modal -->
<div class="modal fade" id="viewDriverModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-eye"></i> Driver Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th>Name:</th>
                                <td id="view_full_name"></td>
                            </tr>
                            <tr>
                                <th>Phone:</th>
                                <td id="view_phone"></td>
                            </tr>
                            <tr>
                                <th>Username:</th>
                                <td id="view_username"></td>
                            </tr>
                            <tr>
                                <th>ID Number:</th>
                                <td id="view_id_number"></td>
                            </tr>
                            <tr>
                                <th>License Expiry:</th>
                                <td id="view_license_expiry"></td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td id="view_status"></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th>Owner:</th>
                                <td id="view_owner"></td>
                            </tr>
                            <tr>
                                <th>Assigned Taxi:</th>
                                <td id="view_taxi"></td>
                            </tr>
                            <tr>
                                <th>Route:</th>
                                <td id="view_route"></td>
                            </tr>
                            <tr>
                                <th>Employment Type:</th>
                                <td id="view_employment"></td>
                            </tr>
                            <tr>
                                <th>Payment Rate:</th>
                                <td id="view_rate"></td>
                            </tr>
                            <tr>
                                <th>Added By:</th>
                                <td id="view_added_by"></td>
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
// Update rate help text based on employment type
document.getElementById('employment_type').addEventListener('change', function() {
    const help = document.getElementById('rate_help');
    if (this.value === 'commission') {
        help.textContent = 'Enter percentage (e.g., 20 for 20%)';
    } else {
        help.textContent = 'Enter amount in Rands per day';
    }
});

// Edit driver function
function editDriver(id) {
    fetch(`get_driver.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('edit_driver_id').value = data.driver_id;
            document.getElementById('edit_user_id').value = data.user_id;
            document.getElementById('edit_full_name').value = data.full_name;
            document.getElementById('edit_phone_number').value = data.phone_number;
            document.getElementById('edit_username').value = data.username;
            document.getElementById('edit_driver_id_number').value = data.id_number;
            document.getElementById('edit_license_expiry').value = data.license_expiry_date;
            document.getElementById('edit_owner_id').value = data.owner_id;
            document.getElementById('edit_taxi_id').value = data.taxi_id || '';
            document.getElementById('edit_employment_type').value = data.employment_type;
            document.getElementById('edit_payment_rate').value = data.payment_rate;
            document.getElementById('edit_is_active').value = data.is_active;
            
            new bootstrap.Modal(document.getElementById('editDriverModal')).show();
        });
}

// View driver details
function viewDriverDetails(id) {
    fetch(`get_driver.php?id=${id}&details=1`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('view_full_name').textContent = data.full_name;
            document.getElementById('view_phone').textContent = data.phone_number;
            document.getElementById('view_username').textContent = data.username;
            document.getElementById('view_id_number').textContent = data.id_number;
            document.getElementById('view_license_expiry').textContent = new Date(data.license_expiry_date).toLocaleDateString();
            document.getElementById('view_owner').textContent = data.owner_name;
            document.getElementById('view_taxi').textContent = data.registration_number || 'Not assigned';
            document.getElementById('view_route').textContent = data.route_name || 'N/A';
            document.getElementById('view_employment').textContent = data.employment_type.charAt(0).toUpperCase() + data.employment_type.slice(1);
            
            let rateText = data.employment_type === 'commission' ? 
                data.payment_rate + '%' : 
                'R ' + parseFloat(data.payment_rate).toFixed(2) + '/day';
            document.getElementById('view_rate').textContent = rateText;
            
            document.getElementById('view_added_by').textContent = data.added_by || 'System';
            
            let statusBadge = data.is_active ? 
                '<span class="badge bg-success">Active</span>' : 
                '<span class="badge bg-danger">Inactive</span>';
            document.getElementById('view_status').innerHTML = statusBadge;
            
            // Populate trips table
            let tripsHtml = '';
            if (data.trips && data.trips.length > 0) {
                data.trips.forEach(trip => {
                    tripsHtml += `<tr>
                        <td>${new Date(trip.departed_at).toLocaleString()}</td>
                        <td>${trip.route_name}</td>
                        <td>${trip.passenger_count}</td>
                        <td>R ${parseFloat(trip.total_fare).toFixed(2)}</td>
                        <td>R ${parseFloat(trip.association_levy).toFixed(2)}</td>
                    </tr>`;
                });
            } else {
                tripsHtml = '<tr><td colspan="5" class="text-center">No trips found</td></tr>';
            }
            document.querySelector('#view_trips_table tbody').innerHTML = tripsHtml;
            
            new bootstrap.Modal(document.getElementById('viewDriverModal')).show();
        });
}

// Delete driver function
function deleteDriver(id) {
    if (confirm('⚠️ Are you sure you want to delete this driver?\n\nThis action cannot be undone!')) {
        window.location.href = `process/process_driver.php?action=delete&id=${id}`;
    }
}

// Toggle status function
function toggleStatus(id, status) {
    const action = status ? 'activate' : 'deactivate';
    if (confirm(`Are you sure you want to ${action} this driver?`)) {
        window.location.href = `process/process_driver.php?action=toggle&id=${id}&status=${status}`;
    }
}
</script>

<?php
// Get the content
$content = ob_get_clean();

// Include the admin layout
require_once '../layouts/admin_layout.php';
?>