<?php
// owner/my_drivers.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('owner');

// Set page title
$page_title = 'My Drivers';

// Start output buffering
ob_start();

$owner_id = $_SESSION['owner_id'];

try {
    // Get owner's drivers with details
    $drivers_sql = "SELECT d.*, 
                   u.full_name, u.phone_number, u.username, u.email, u.is_active, u.created_at,
                   t.id as taxi_id, t.registration_number, t.status as taxi_status,
                   r.route_name,
                   (SELECT COUNT(*) FROM trips WHERE driver_id = d.id AND DATE(departed_at) = CURDATE()) as today_trips,
                   (SELECT SUM(total_fare) FROM trips WHERE driver_id = d.id AND DATE(departed_at) = CURDATE()) as today_revenue,
                   (SELECT COUNT(*) FROM trips WHERE driver_id = d.id) as total_trips,
                   (SELECT SUM(total_fare) FROM trips WHERE driver_id = d.id) as total_revenue,
                   (SELECT SUM(owner_payout) FROM trips WHERE driver_id = d.id) as total_earnings
                  FROM drivers d
                  JOIN users u ON d.user_id = u.id
                  LEFT JOIN taxis t ON d.taxi_id = t.id
                  LEFT JOIN routes r ON t.route_id = r.id
                  WHERE d.owner_id = ?
                  ORDER BY u.full_name";
    
    $stmt = $pdo->prepare($drivers_sql);
    $stmt->execute([$owner_id]);
    $drivers = $stmt->fetchAll();
    
    // Get owner's taxis for assignment (only unassigned or currently assigned)
    $taxis_sql = "SELECT t.*, r.route_name,
                  d.id as current_driver_id, u.full_name as current_driver
                  FROM taxis t
                  LEFT JOIN routes r ON t.route_id = r.id
                  LEFT JOIN drivers d ON t.id = d.taxi_id
                  LEFT JOIN users u ON d.user_id = u.id
                  WHERE t.owner_id = ? AND (t.status = 'active' OR t.status = 'on_trip')
                  ORDER BY t.registration_number";
    $stmt = $pdo->prepare($taxis_sql);
    $stmt->execute([$owner_id]);
    $available_taxis = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("My drivers error: " . $e->getMessage());
    $drivers = [];
    $available_taxis = [];
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-people"></i> My Drivers</h2>
        <div>
            <button class="btn btn-primary" onclick="addDriver()">
                <i class="bi bi-person-plus"></i> Add New Driver
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
                    <h6 class="card-title text-white-50">Total Drivers</h6>
                    <h3 class="mb-0"><?= count($drivers) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h6 class="card-title text-white-50">Active Drivers</h6>
                    <h3 class="mb-0">
                        <?= count(array_filter($drivers, function($d) { return $d['is_active'] == 1; })) ?>
                    </h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <h6 class="card-title text-white-50">Assigned to Taxi</h6>
                    <h3 class="mb-0">
                        <?= count(array_filter($drivers, function($d) { return !empty($d['taxi_id']); })) ?>
                    </h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <h6 class="card-title text-white-50">Today's Revenue</h6>
                    <h3 class="mb-0">R <?= number_format(array_sum(array_column($drivers, 'today_revenue')), 2) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Drivers Table -->
    <div class="card">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-list"></i> My Drivers</h5>
            <div class="d-flex">
                <select class="form-select form-select-sm me-2" style="width: auto;" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="assigned">Assigned to Taxi</option>
                    <option value="unassigned">Unassigned</option>
                </select>
                <input type="text" class="form-control form-control-sm" placeholder="Search drivers..." id="searchInput" style="width: 250px;">
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="driversTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Driver Name</th>
                            <th>Contact</th>
                            <th>Assigned Taxi</th>
                            <th>License Expiry</th>
                            <th>Employment</th>
                            <th>Rate</th>
                            <th>Today's Trips</th>
                            <th>Today's Revenue</th>
                            <th>Total Trips</th>
                            <th>Total Earnings</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($drivers)): ?>
                            <tr>
                                <td colspan="13" class="text-center py-5">
                                    <i class="bi bi-people fs-1 text-muted d-block mb-3"></i>
                                    <h5 class="text-muted">No Drivers Found</h5>
                                    <p class="text-muted">You haven't registered any drivers yet.</p>
                                    <button class="btn btn-primary" onclick="addDriver()">
                                        <i class="bi bi-person-plus"></i> Add Your First Driver
                                    </button>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($drivers as $index => $driver): ?>
                            <tr data-status="<?= $driver['is_active'] ? 'active' : 'inactive' ?>" 
                                data-assigned="<?= !empty($driver['taxi_id']) ? 'assigned' : 'unassigned' ?>">
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($driver['full_name']) ?></strong>
                                    <br>
                                    <small class="text-muted">ID: <?= $driver['id'] ?></small>
                                </td>
                                <td>
                                    <i class="bi bi-telephone"></i> <?= htmlspecialchars($driver['phone_number'] ?? 'N/A') ?>
                                    <br>
                                    <small class="text-muted"><?= htmlspecialchars($driver['email'] ?? '') ?></small>
                                </td>
                                <td>
                                    <?php if ($driver['registration_number']): ?>
                                        <span class="badge bg-info"><?= htmlspecialchars($driver['registration_number']) ?></span>
                                        <br>
                                        <small><?= htmlspecialchars($driver['route_name'] ?? 'No route') ?></small>
                                        <br>
                                        <small class="badge bg-<?= $driver['taxi_status'] == 'active' ? 'success' : 'warning' ?>">
                                            <?= ucfirst($driver['taxi_status']) ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $expiry = strtotime($driver['license_expiry_date']);
                                    $now = time();
                                    $days_left = ceil(($expiry - $now) / (60 * 60 * 24));
                                    
                                    if ($expiry < $now):
                                    ?>
                                        <span class="badge bg-danger">Expired</span>
                                        <br>
                                        <small class="text-danger"><?= date('d/m/Y', $expiry) ?></small>
                                    <?php elseif ($days_left <= 30): ?>
                                        <span class="badge bg-warning"><?= $days_left ?> days</span>
                                        <br>
                                        <small><?= date('d/m/Y', $expiry) ?></small>
                                    <?php else: ?>
                                        <span class="badge bg-success">Valid</span>
                                        <br>
                                        <small><?= date('d/m/Y', $expiry) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= ucfirst($driver['employment_type'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if ($driver['employment_type'] == 'commission'): ?>
                                        <strong><?= $driver['payment_rate'] ?>%</strong>
                                    <?php else: ?>
                                        <strong>R <?= number_format($driver['payment_rate'] ?? 0, 2) ?></strong>
                                        <br><small>per day</small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary"><?= $driver['today_trips'] ?? 0 ?></span>
                                </td>
                                <td>R <?= number_format($driver['today_revenue'] ?? 0, 2) ?></td>
                                <td class="text-center"><?= $driver['total_trips'] ?? 0 ?></td>
                                <td><strong>R <?= number_format($driver['total_earnings'] ?? 0, 2) ?></strong></td>
                                <td>
                                    <?php if ($driver['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-info" onclick="viewDriver(<?= $driver['id'] ?>)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-warning" onclick="editDriver(<?= $driver['id'] ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php if (!$driver['taxi_id']): ?>
                                        <button class="btn btn-sm btn-success" onclick="assignTaxi(<?= $driver['id'] ?>)">
                                            <i class="bi bi-truck"></i>
                                        </button>
                                        <?php else: ?>
                                        <button class="btn btn-sm btn-danger" onclick="unassignTaxi(<?= $driver['id'] ?>, <?= $driver['taxi_id'] ?>)">
                                            <i class="bi bi-truck-flatbed"></i>
                                        </button>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-primary" onclick="viewDriverTrips(<?= $driver['id'] ?>)">
                                            <i class="bi bi-clock-history"></i>
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

<!-- Add Driver Modal -->
<div class="modal fade" id="addDriverModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-person-plus"></i> Add New Driver</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="process/process_driver.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="owner_id" value="<?= $owner_id ?>">
                    
                    <h6 class="mb-3">Personal Information</h6>
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
                            <label for="email" class="form-label">Email Address (Username) *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                            <small class="text-muted">Will be used for login</small>
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                    </div>
                    
                    <h6 class="mb-3 mt-4">Driver Details</h6>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="id_number" class="form-label">ID Number *</label>
                            <input type="text" class="form-control" id="id_number" name="id_number" required>
                        </div>
                        <div class="col-md-4">
                            <label for="license_expiry" class="form-label">License Expiry Date *</label>
                            <input type="date" class="form-control" id="license_expiry" name="license_expiry" required>
                        </div>
                        <div class="col-md-4">
                            <label for="taxi_id" class="form-label">Assign Taxi (Optional)</label>
                            <select class="form-select" id="taxi_id" name="taxi_id">
                                <option value="">No Taxi (Assign Later)</option>
                                <?php foreach ($available_taxis as $taxi): ?>
                                    <option value="<?= $taxi['id'] ?>" <?= $taxi['current_driver_id'] ? 'disabled' : '' ?>>
                                        <?= htmlspecialchars($taxi['registration_number']) ?> 
                                        (<?= htmlspecialchars($taxi['route_name'] ?? 'No Route') ?>)
                                        <?= $taxi['current_driver'] ? ' - Currently with ' . $taxi['current_driver'] : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
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
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> The driver will receive login credentials via email after registration.
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
                            <label for="edit_email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="edit_email" readonly class="bg-light">
                            <small class="text-muted">Email cannot be changed</small>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_password" class="form-label">New Password (leave blank to keep current)</label>
                            <input type="password" class="form-control" id="edit_password" name="password">
                            <small class="text-muted">Minimum 6 characters if changing</small>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_id_number" class="form-label">ID Number *</label>
                            <input type="text" class="form-control" id="edit_id_number" name="id_number" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_license_expiry" class="form-label">License Expiry *</label>
                            <input type="date" class="form-control" id="edit_license_expiry" name="license_expiry" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_taxi_id" class="form-label">Assign Taxi</label>
                            <select class="form-select" id="edit_taxi_id" name="taxi_id">
                                <option value="">No Taxi</option>
                                <?php foreach ($available_taxis as $taxi): ?>
                                    <option value="<?= $taxi['id'] ?>">
                                        <?= htmlspecialchars($taxi['registration_number']) ?>
                                        (<?= htmlspecialchars($taxi['route_name'] ?? 'No Route') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
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

<!-- View Driver Modal -->
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
                                <th>Email:</th>
                                <td id="view_email"></td>
                            </tr>
                            <tr>
                                <th>ID Number:</th>
                                <td id="view_id_number"></td>
                            </tr>
                            <tr>
                                <th>License Expiry:</th>
                                <td id="view_license"></td>
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
                                <th>Taxi</th>
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

<!-- Assign Taxi Modal -->
<div class="modal fade" id="assignTaxiModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-truck"></i> Assign Taxi to Driver</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="process/process_driver.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="assign_taxi">
                    <input type="hidden" name="driver_id" id="assign_driver_id">
                    
                    <div class="mb-3">
                        <label for="assign_taxi_id" class="form-label">Select Taxi</label>
                        <select class="form-select" id="assign_taxi_id" name="taxi_id" required>
                            <option value="">Choose a taxi...</option>
                            <?php foreach ($available_taxis as $taxi): ?>
                                <option value="<?= $taxi['id'] ?>" <?= $taxi['current_driver_id'] ? 'disabled' : '' ?>>
                                    <?= htmlspecialchars($taxi['registration_number']) ?> 
                                    (<?= htmlspecialchars($taxi['route_name'] ?? 'No Route') ?>)
                                    <?= $taxi['current_driver'] ? ' - Currently with ' . $taxi['current_driver'] : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Assign Taxi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Driver Trips Modal -->
<div class="modal fade" id="driverTripsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-clock-history"></i> Driver Trip History</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6 id="driver_trips_info" class="mb-3"></h6>
                <div class="table-responsive">
                    <table class="table table-sm" id="driver_trips_table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Taxi</th>
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
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}
</style>

<script>
// Update rate help text based on employment type
document.getElementById('employment_type')?.addEventListener('change', function() {
    const help = document.getElementById('rate_help');
    if (this.value === 'commission') {
        help.textContent = 'Enter percentage (e.g., 20 for 20%)';
    } else {
        help.textContent = 'Enter amount in Rands per day';
    }
});

// Search and filter functionality
document.getElementById('searchInput')?.addEventListener('keyup', filterTable);
document.getElementById('statusFilter')?.addEventListener('change', filterTable);

function filterTable() {
    const searchText = document.getElementById('searchInput')?.value.toLowerCase() || '';
    const statusFilter = document.getElementById('statusFilter')?.value || '';
    const table = document.getElementById('driversTable');
    const rows = table.getElementsByTagName('tr');
    
    for (let i = 1; i < rows.length; i++) {
        const row = rows[i];
        const name = row.cells[1]?.textContent.toLowerCase() || '';
        const phone = row.cells[2]?.textContent.toLowerCase() || '';
        const taxi = row.cells[3]?.textContent.toLowerCase() || '';
        const rowStatus = row.dataset.status || '';
        const rowAssigned = row.dataset.assigned || '';
        
        const matchesSearch = name.includes(searchText) || phone.includes(searchText) || taxi.includes(searchText);
        let matchesFilter = true;
        
        if (statusFilter === 'active') {
            matchesFilter = rowStatus === 'active';
        } else if (statusFilter === 'inactive') {
            matchesFilter = rowStatus === 'inactive';
        } else if (statusFilter === 'assigned') {
            matchesFilter = rowAssigned === 'assigned';
        } else if (statusFilter === 'unassigned') {
            matchesFilter = rowAssigned === 'unassigned';
        }
        
        if (matchesSearch && matchesFilter) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    }
}

// Add driver function
function addDriver() {
    new bootstrap.Modal(document.getElementById('addDriverModal')).show();
}

// View driver details
function viewDriver(id) {
    fetch(`get_driver_details.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('view_full_name').textContent = data.full_name;
            document.getElementById('view_phone').textContent = data.phone_number;
            document.getElementById('view_email').textContent = data.email || data.username;
            document.getElementById('view_id_number').textContent = data.id_number;
            document.getElementById('view_license').textContent = new Date(data.license_expiry_date).toLocaleDateString();
            document.getElementById('view_taxi').textContent = data.registration_number || 'Not assigned';
            document.getElementById('view_route').textContent = data.route_name || 'N/A';
            document.getElementById('view_employment').textContent = data.employment_type.charAt(0).toUpperCase() + data.employment_type.slice(1);
            
            let rateText = data.employment_type === 'commission' ? 
                data.payment_rate + '%' : 
                'R ' + parseFloat(data.payment_rate).toFixed(2) + '/day';
            document.getElementById('view_rate').textContent = rateText;
            
            document.getElementById('view_total_trips').textContent = data.total_trips || 0;
            document.getElementById('view_total_earnings').textContent = 'R ' + (parseFloat(data.total_earnings) || 0).toFixed(2);
            
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
                        <td>${trip.registration_number}</td>
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
            
            new bootstrap.Modal(document.getElementById('viewDriverModal')).show();
        });
}

// Edit driver function
function editDriver(id) {
    fetch(`get_driver_details.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('edit_driver_id').value = data.driver_id;
            document.getElementById('edit_user_id').value = data.user_id;
            document.getElementById('edit_full_name').value = data.full_name;
            document.getElementById('edit_phone_number').value = data.phone_number;
            document.getElementById('edit_email').value = data.username;
            document.getElementById('edit_id_number').value = data.id_number;
            document.getElementById('edit_license_expiry').value = data.license_expiry_date;
            document.getElementById('edit_taxi_id').value = data.taxi_id || '';
            document.getElementById('edit_employment_type').value = data.employment_type;
            document.getElementById('edit_payment_rate').value = data.payment_rate;
            document.getElementById('edit_is_active').value = data.is_active;
            
            new bootstrap.Modal(document.getElementById('editDriverModal')).show();
        });
}

// Assign taxi function
function assignTaxi(driverId) {
    document.getElementById('assign_driver_id').value = driverId;
    new bootstrap.Modal(document.getElementById('assignTaxiModal')).show();
}

// Unassign taxi function
function unassignTaxi(driverId, taxiId) {
    if (confirm('Remove this driver from their current taxi?')) {
        window.location.href = `process/process_driver.php?action=unassign_taxi&driver_id=${driverId}&taxi_id=${taxiId}`;
    }
}

// View driver trips
function viewDriverTrips(driverId) {
    fetch(`get_driver_trips.php?id=${driverId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('driver_trips_info').textContent = `Trip History for Driver: ${data.driver_name}`;
            
            let tripsHtml = '';
            if (data.trips && data.trips.length > 0) {
                data.trips.forEach(trip => {
                    tripsHtml += `<tr>
                        <td>${new Date(trip.departed_at).toLocaleString()}</td>
                        <td>${trip.registration_number}</td>
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
            document.querySelector('#driver_trips_table tbody').innerHTML = tripsHtml;
            
            new bootstrap.Modal(document.getElementById('driverTripsModal')).show();
        });
}

// Toggle status function
function toggleStatus(id, status) {
    const action = status ? 'activate' : 'deactivate';
    if (confirm(`Are you sure you want to ${action} this driver?`)) {
        window.location.href = `process/process_driver.php?action=toggle&id=${id}&status=${status}`;
    }
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