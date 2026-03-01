<?php
// admin/manage_owners.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('admin');

// Set page title
$page_title = 'Manage Owners';

// Start output buffering
ob_start();

// Get all owners with their stats
try {
    $owners_sql = "SELECT o.*, u.full_name, u.phone_number, u.username, u.is_active, u.created_at,
                  (SELECT COUNT(*) FROM taxis WHERE owner_id = o.id) as total_taxis,
                  (SELECT COUNT(*) FROM drivers WHERE owner_id = o.id) as total_drivers,
                  (SELECT SUM(total_fare - association_levy) FROM trips t 
                   JOIN taxis tx ON t.taxi_id = tx.id 
                   WHERE tx.owner_id = o.id AND DATE(t.departed_at) = CURDATE()) as today_earnings,
                  (SELECT SUM(total_fare - association_levy) FROM trips t 
                   JOIN taxis tx ON t.taxi_id = tx.id 
                   WHERE tx.owner_id = o.id) as total_earnings
                  FROM owners o
                  JOIN users u ON o.user_id = u.id
                  ORDER BY u.full_name";
    
    $owners = $pdo->query($owners_sql)->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error fetching owners: " . $e->getMessage());
    $owners = [];
}
?>

<!-- Add New Owner Button -->
<div class="mb-3">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addOwnerModal">
        <i class="bi bi-plus-circle"></i> Add New Owner
    </button>
</div>

<!-- Owners Table -->
<div class="card">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0"><i class="bi bi-briefcase"></i> All Owners</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Username</th>
                        <th>ID Number</th>
                        <th>Taxis</th>
                        <th>Drivers</th>
                        <th>Today's Earnings</th>
                        <th>Total Earnings</th>
                        <th>Bank Details</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($owners)): ?>
                        <tr>
                            <td colspan="12" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                                No owners found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($owners as $owner): ?>
                        <tr>
                            <td><?= $owner['id'] ?></td>
                            <td><strong><?= htmlspecialchars($owner['full_name']) ?></strong></td>
                            <td><?= htmlspecialchars($owner['phone_number']) ?></td>
                            <td><?= htmlspecialchars($owner['username']) ?></td>
                            <td><?= htmlspecialchars($owner['id_number']) ?></td>
                            <td><span class="badge bg-info"><?= $owner['total_taxis'] ?></span></td>
                            <td><span class="badge bg-success"><?= $owner['total_drivers'] ?></span></td>
                            <td>R <?= number_format($owner['today_earnings'] ?? 0, 2) ?></td>
                            <td>R <?= number_format($owner['total_earnings'] ?? 0, 2) ?></td>
                            <td>
                                <?php if ($owner['bank_name']): ?>
                                    <small>
                                        <?= htmlspecialchars($owner['bank_name']) ?><br>
                                        <?= htmlspecialchars($owner['account_number']) ?>
                                    </small>
                                <?php else: ?>
                                    <span class="text-muted">Not provided</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($owner['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="editOwner(<?= $owner['id'] ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteOwner(<?= $owner['id'] ?>)">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <button class="btn btn-sm btn-info" onclick="viewOwnerDetails(<?= $owner['id'] ?>)">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <?php if ($owner['is_active']): ?>
                                    <button class="btn btn-sm btn-secondary" onclick="toggleStatus(<?= $owner['id'] ?>, 0)">
                                        <i class="bi bi-eye-slash"></i>
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-success" onclick="toggleStatus(<?= $owner['id'] ?>, 1)">
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

<!-- Add Owner Modal -->
<div class="modal fade" id="addOwnerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add New Owner</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="process/process_owner.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
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
                    
                    <h6 class="mb-3 mt-4">Owner Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="id_number" class="form-label">ID Number / Passport *</label>
                            <input type="text" class="form-control" id="id_number" name="id_number" required>
                        </div>
                        <div class="col-md-4">
                            <label for="bank_name" class="form-label">Bank Name</label>
                            <input type="text" class="form-control" id="bank_name" name="bank_name">
                        </div>
                        <div class="col-md-4">
                            <label for="account_number" class="form-label">Account Number</label>
                            <input type="text" class="form-control" id="account_number" name="account_number">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="branch_code" class="form-label">Branch Code</label>
                            <input type="text" class="form-control" id="branch_code" name="branch_code">
                        </div>
                        <div class="col-md-4">
                            <label for="is_active" class="form-label">Status</label>
                            <select class="form-select" id="is_active" name="is_active">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Owner</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Owner Modal -->
<div class="modal fade" id="editOwnerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Owner</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="process/process_owner.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="owner_id" id="edit_owner_id">
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
                            <input type="text" class="form-control" id="edit_username" name="username" readonly class="bg-light">
                            <small class="text-muted">Username cannot be changed</small>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_password" class="form-label">New Password (leave blank to keep current)</label>
                            <input type="password" class="form-control" id="edit_password" name="password">
                            <small class="text-muted">Minimum 6 characters if changing</small>
                        </div>
                    </div>
                    
                    <h6 class="mb-3 mt-4">Owner Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_id_number" class="form-label">ID Number / Passport *</label>
                            <input type="text" class="form-control" id="edit_id_number" name="id_number" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_bank_name" class="form-label">Bank Name</label>
                            <input type="text" class="form-control" id="edit_bank_name" name="bank_name">
                        </div>
                        <div class="col-md-4">
                            <label for="edit_account_number" class="form-label">Account Number</label>
                            <input type="text" class="form-control" id="edit_account_number" name="account_number">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_branch_code" class="form-label">Branch Code</label>
                            <input type="text" class="form-control" id="edit_branch_code" name="branch_code">
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
                    <button type="submit" class="btn btn-warning">Update Owner</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Owner Details Modal -->
<div class="modal fade" id="viewOwnerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-eye"></i> Owner Details</h5>
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
                                <th>Status:</th>
                                <td id="view_status"></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th>Bank Name:</th>
                                <td id="view_bank_name"></td>
                            </tr>
                            <tr>
                                <th>Account Number:</th>
                                <td id="view_account_number"></td>
                            </tr>
                            <tr>
                                <th>Branch Code:</th>
                                <td id="view_branch_code"></td>
                            </tr>
                            <tr>
                                <th>Total Taxis:</th>
                                <td id="view_taxis"></td>
                            </tr>
                            <tr>
                                <th>Total Drivers:</th>
                                <td id="view_drivers"></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <h6 class="mt-3">Recent Taxis</h6>
                <div class="table-responsive">
                    <table class="table table-sm" id="view_taxis_table">
                        <thead>
                            <tr>
                                <th>Registration</th>
                                <th>Route</th>
                                <th>Driver</th>
                                <th>Status</th>
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
// Edit owner function
function editOwner(id) {
    fetch(`get_owner.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('edit_owner_id').value = data.owner_id;
            document.getElementById('edit_user_id').value = data.user_id;
            document.getElementById('edit_full_name').value = data.full_name;
            document.getElementById('edit_phone_number').value = data.phone_number;
            document.getElementById('edit_username').value = data.username;
            document.getElementById('edit_id_number').value = data.id_number;
            document.getElementById('edit_bank_name').value = data.bank_name || '';
            document.getElementById('edit_account_number').value = data.account_number || '';
            document.getElementById('edit_branch_code').value = data.branch_code || '';
            document.getElementById('edit_is_active').value = data.is_active;
            
            new bootstrap.Modal(document.getElementById('editOwnerModal')).show();
        });
}

// View owner details
function viewOwnerDetails(id) {
    fetch(`get_owner.php?id=${id}&details=1`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('view_full_name').textContent = data.full_name;
            document.getElementById('view_phone').textContent = data.phone_number;
            document.getElementById('view_username').textContent = data.username;
            document.getElementById('view_id_number').textContent = data.id_number;
            document.getElementById('view_bank_name').textContent = data.bank_name || 'Not provided';
            document.getElementById('view_account_number').textContent = data.account_number || 'Not provided';
            document.getElementById('view_branch_code').textContent = data.branch_code || 'Not provided';
            document.getElementById('view_taxis').textContent = data.total_taxis;
            document.getElementById('view_drivers').textContent = data.total_drivers;
            
            let statusBadge = data.is_active ? 
                '<span class="badge bg-success">Active</span>' : 
                '<span class="badge bg-danger">Inactive</span>';
            document.getElementById('view_status').innerHTML = statusBadge;
            
            // Populate taxis table
            let taxisHtml = '';
            if (data.taxis && data.taxis.length > 0) {
                data.taxis.forEach(taxi => {
                    taxisHtml += `<tr>
                        <td>${taxi.registration_number}</td>
                        <td>${taxi.route_name || 'Not assigned'}</td>
                        <td>${taxi.driver_name || 'No driver'}</td>
                        <td><span class="badge bg-${taxi.status === 'active' ? 'success' : 'warning'}">${taxi.status}</span></td>
                    </tr>`;
                });
            } else {
                taxisHtml = '<tr><td colspan="4" class="text-center">No taxis found</td></tr>';
            }
            document.querySelector('#view_taxis_table tbody').innerHTML = taxisHtml;
            
            new bootstrap.Modal(document.getElementById('viewOwnerModal')).show();
        });
}

// Delete owner function
function deleteOwner(id) {
    if (confirm('⚠️ WARNING: Deleting this owner will also delete all their taxis and drivers!\n\nAre you absolutely sure?')) {
        if (confirm('This action CANNOT be undone. Type "DELETE" to confirm:')) {
            window.location.href = `process/process_owner.php?action=delete&id=${id}`;
        }
    }
}

// Toggle status function
function toggleStatus(id, status) {
    const action = status ? 'activate' : 'deactivate';
    if (confirm(`Are you sure you want to ${action} this owner?`)) {
        window.location.href = `process/process_owner.php?action=toggle&id=${id}&status=${status}`;
    }
}
</script>

<?php
// Get the content
$content = ob_get_clean();

// Include the admin layout
require_once '../layouts/admin_layout.php';
?>