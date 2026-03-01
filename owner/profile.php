<?php
// owner/profile.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('owner');

// Set page title
$page_title = 'My Profile';

// Start output buffering
ob_start();

$user_id = $_SESSION['user_id'];
$owner_id = $_SESSION['owner_id'];

// Get user current data with owner details
try {
    $sql = "SELECT u.*, 
                   o.id as owner_id, 
                   o.id_number, 
                   o.bank_name, 
                   o.account_number, 
                   o.branch_code,
                   (SELECT COUNT(*) FROM taxis WHERE owner_id = o.id) as total_taxis,
                   (SELECT COUNT(*) FROM drivers WHERE owner_id = o.id) as total_drivers,
                   (SELECT COUNT(*) FROM trips t 
                    JOIN taxis tx ON t.taxi_id = tx.id 
                    WHERE tx.owner_id = o.id) as total_trips,
                   (SELECT COALESCE(SUM(owner_payout), 0) FROM trips t 
                    JOIN taxis tx ON t.taxi_id = tx.id 
                    WHERE tx.owner_id = o.id) as total_earnings,
                   (SELECT COUNT(*) FROM owner_settlements WHERE owner_id = o.id AND status = 'pending') as pending_settlements,
                   (SELECT COALESCE(SUM(amount), 0) FROM owner_settlements WHERE owner_id = o.id AND status = 'pending') as pending_amount
            FROM users u
            JOIN owners o ON u.id = o.user_id
            WHERE u.id = :id AND o.id = :owner_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id' => $user_id,
        ':owner_id' => $owner_id
    ]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo "<script>
            alert('Profile not found!');
            window.location.href = 'dashboard.php';
        </script>";
        exit();
    }
    
    // Get recent activity
    $activity_sql = "SELECT 
                        'settlement' as type,
                        created_at as date,
                        CONCAT('Settlement requested: R', amount, ' for period ', DATE_FORMAT(period_start, '%d/%m'), '-', DATE_FORMAT(period_end, '%d/%m')) as description,
                        status
                     FROM owner_settlements 
                     WHERE owner_id = :owner_id
                     UNION ALL
                     SELECT 
                        'trip' as type,
                        t.departed_at as date,
                        CONCAT('Trip completed: ', tx.registration_number, ' - ', r.route_name, ' (', t.passenger_count, ' pax)') as description,
                        'completed' as status
                     FROM trips t
                     JOIN taxis tx ON t.taxi_id = tx.id
                     JOIN routes r ON t.route_id = r.id
                     WHERE tx.owner_id = :owner_id
                     ORDER BY date DESC
                     LIMIT 10";
    
    $activity_stmt = $pdo->prepare($activity_sql);
    $activity_stmt->execute([':owner_id' => $owner_id]);
    $activities = $activity_stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Profile error: " . $e->getMessage());
    echo "<script>
        alert('Error loading profile: " . addslashes($e->getMessage()) . "');
        window.location.href = 'dashboard.php';
    </script>";
    exit();
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-person-circle"></i> My Profile</h2>
        <div>
            <button class="btn btn-primary" onclick="editProfile()">
                <i class="bi bi-pencil"></i> Edit Profile
            </button>
            <button class="btn btn-warning" onclick="changePassword()">
                <i class="bi bi-key"></i> Change Password
            </button>
            <button class="btn btn-info" onclick="uploadImage()">
                <i class="bi bi-camera"></i> Update Photo
            </button>
        </div>
    </div>

    <!-- Profile Overview -->
    <div class="row">
        <!-- Left Column - Profile Picture & Quick Stats -->
        <div class="col-md-4">
            <!-- Profile Picture Card -->
            <div class="card mb-4">
                <div class="card-body text-center">
                    <div class="position-relative d-inline-block">
                        <img src="<?= !empty($user['profile_image']) ? '../assets/img/' . $user['profile_image'] : 'https://via.placeholder.com/150' ?>" 
                             alt="Profile" 
                             class="rounded-circle img-thumbnail" 
                             style="width: 150px; height: 150px; object-fit: cover; border: 3px solid #f39c12;"
                             id="profileImage"
                             onerror="this.src='https://via.placeholder.com/150'">
                        <button class="btn btn-sm btn-primary position-absolute bottom-0 end-0 rounded-circle" 
                                onclick="uploadImage()"
                                style="width: 40px; height: 40px; background: #f39c12; border-color: #f39c12;">
                            <i class="bi bi-camera"></i>
                        </button>
                    </div>
                    <h3 class="mt-3"><?= htmlspecialchars($user['full_name']) ?></h3>
                    <p class="text-muted">
                        <i class="bi bi-star-fill text-warning"></i> Owner
                    </p>
                    
                    <!-- Quick Stats -->
                    <div class="row mt-3">
                        <div class="col-4">
                            <div class="border-end">
                                <h5 class="mb-0"><?= $user['total_taxis'] ?></h5>
                                <small class="text-muted">Taxis</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border-end">
                                <h5 class="mb-0"><?= $user['total_drivers'] ?></h5>
                                <small class="text-muted">Drivers</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div>
                                <h5 class="mb-0"><?= $user['total_trips'] ?></h5>
                                <small class="text-muted">Trips</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pending Settlements Alert -->
                    <?php if ($user['pending_settlements'] > 0): ?>
                        <div class="alert alert-warning mt-3 mb-0 py-2">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>R <?= number_format($user['pending_amount'], 2) ?></strong> pending
                            <br>
                            <small><?= $user['pending_settlements'] ?> settlement(s) awaiting payment</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Account Info Card -->
            <div class="card mb-4">
                <div class="card-header" style="background: #f39c12; color: white;">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> Account Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>Username/Email:</strong></td>
                            <td><i class="bi bi-envelope"></i> <?= htmlspecialchars($user['username']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Member Since:</strong></td>
                            <td><i class="bi bi-calendar"></i> <?= date('d M Y', strtotime($user['created_at'])) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Last Login:</strong></td>
                            <td>
                                <i class="bi bi-clock"></i> 
                                <?= $user['last_login'] ? date('d M Y H:i', strtotime($user['last_login'])) : 'First login' ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Status:</strong></td>
                            <td>
                                <?php if ($user['is_active']): ?>
                                    <span class="badge bg-success">Active Account</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right Column - Personal & Business Details -->
        <div class="col-md-8">
            <!-- Personal Information Card -->
            <div class="card mb-4">
                <div class="card-header" style="background: #2c3e50; color: white;">
                    <h5 class="mb-0"><i class="bi bi-person"></i> Personal Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table">
                                <tr>
                                    <th style="width: 120px;">Full Name:</th>
                                    <td><strong><?= htmlspecialchars($user['full_name']) ?></strong></td>
                                </tr>
                                <tr>
                                    <th>ID Number:</th>
                                    <td>
                                        <?php if ($user['id_number']): ?>
                                            <span class="badge bg-info"><?= htmlspecialchars($user['id_number']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Not provided</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Phone Number:</th>
                                    <td>
                                        <?php if ($user['phone_number']): ?>
                                            <i class="bi bi-telephone"></i> <?= htmlspecialchars($user['phone_number']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not provided</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table">
                                <tr>
                                    <th style="width: 120px;">Email:</th>
                                    <td><i class="bi bi-envelope"></i> <?= htmlspecialchars($user['username']) ?></td>
                                </tr>
                                <tr>
                                    <th>Total Earnings:</th>
                                    <td>
                                        <h5 class="text-success mb-0">R <?= number_format($user['total_earnings'] ?? 0, 2) ?></h5>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Account Age:</th>
                                    <td>
                                        <?php 
                                        $days = floor((time() - strtotime($user['created_at'])) / (60 * 60 * 24));
                                        echo $days . ' days';
                                        ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Banking Details Card -->
            <div class="card mb-4">
                <div class="card-header" style="background: #27ae60; color: white;">
                    <h5 class="mb-0"><i class="bi bi-bank"></i> Banking Details</h5>
                </div>
                <div class="card-body">
                    <?php if ($user['bank_name'] || $user['account_number'] || $user['branch_code']): ?>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="border p-3 rounded text-center">
                                    <i class="bi bi-building fs-1 text-primary"></i>
                                    <h6>Bank Name</h6>
                                    <p class="mb-0"><strong><?= htmlspecialchars($user['bank_name']) ?></strong></p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border p-3 rounded text-center">
                                    <i class="bi bi-credit-card fs-1 text-success"></i>
                                    <h6>Account Number</h6>
                                    <p class="mb-0"><strong><?= htmlspecialchars($user['account_number']) ?></strong></p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border p-3 rounded text-center">
                                    <i class="bi bi-upc-scan fs-1 text-info"></i>
                                    <h6>Branch Code</h6>
                                    <p class="mb-0"><strong><?= htmlspecialchars($user['branch_code']) ?></strong></p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning mb-0">
                            <i class="bi bi-exclamation-triangle"></i>
                            No banking details on file. Please update your banking information to receive settlements.
                            <button class="btn btn-sm btn-warning mt-2" onclick="editProfile()">
                                <i class="bi bi-pencil"></i> Add Banking Details
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Activity Card -->
            <div class="card mb-4">
                <div class="card-header" style="background: #3498db; color: white;">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Activity</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($activities)): ?>
                        <p class="text-muted text-center py-3">No recent activity</p>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($activities as $activity): ?>
                                <div class="d-flex align-items-start mb-3 pb-2 border-bottom">
                                    <div class="me-3">
                                        <?php if ($activity['type'] == 'settlement'): ?>
                                            <?php if ($activity['status'] == 'pending'): ?>
                                                <span class="badge bg-warning p-2"><i class="bi bi-clock"></i></span>
                                            <?php else: ?>
                                                <span class="badge bg-success p-2"><i class="bi bi-check"></i></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-info p-2"><i class="bi bi-taxi-front"></i></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <p class="mb-0"><?= htmlspecialchars($activity['description']) ?></p>
                                        <small class="text-muted">
                                            <i class="bi bi-clock"></i> <?= date('d M Y H:i', strtotime($activity['date'])) ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Business Summary Card -->
            <div class="card">
                <div class="card-header" style="background: #e74c3c; color: white;">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> Business Summary</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3 col-6 mb-3">
                            <div class="border rounded p-3">
                                <i class="bi bi-truck fs-2 text-primary"></i>
                                <h4 class="mb-0"><?= $user['total_taxis'] ?></h4>
                                <small class="text-muted">Total Taxis</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="border rounded p-3">
                                <i class="bi bi-people fs-2 text-success"></i>
                                <h4 class="mb-0"><?= $user['total_drivers'] ?></h4>
                                <small class="text-muted">Total Drivers</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="border rounded p-3">
                                <i class="bi bi-clock-history fs-2 text-warning"></i>
                                <h4 class="mb-0"><?= $user['total_trips'] ?></h4>
                                <small class="text-muted">Total Trips</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="border rounded p-3">
                                <i class="bi bi-cash-stack fs-2 text-info"></i>
                                <h4 class="mb-0">R <?= number_format($user['total_earnings'] ?? 0, 2) ?></h4>
                                <small class="text-muted">Total Earnings</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="row mt-3">
                        <div class="col-12">
                            <hr>
                            <div class="d-flex justify-content-center gap-2">
                                <a href="my_taxis.php" class="btn btn-outline-primary">
                                    <i class="bi bi-truck"></i> Manage Taxis
                                </a>
                                <a href="my_drivers.php" class="btn btn-outline-success">
                                    <i class="bi bi-people"></i> Manage Drivers
                                </a>
                                <a href="earnings.php" class="btn btn-outline-warning">
                                    <i class="bi bi-graph-up"></i> View Earnings
                                </a>
                                <a href="settlements.php" class="btn btn-outline-info">
                                    <i class="bi bi-cash-stack"></i> Settlements
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: #f39c12; color: white;">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Profile</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="process/process_profile.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_profile">
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                    <input type="hidden" name="owner_id" value="<?= $user['owner_id'] ?>">
                    
                    <h6 class="mb-3">Personal Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="full_name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                   value="<?= htmlspecialchars($user['full_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="phone_number" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                                   value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email Address (Username) *</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?= htmlspecialchars($user['username']) ?>" required>
                            <small class="text-muted">This will be your login email</small>
                        </div>
                        <div class="col-md-6">
                            <label for="id_number" class="form-label">ID Number</label>
                            <input type="text" class="form-control" id="id_number" name="id_number" 
                                   value="<?= htmlspecialchars($user['id_number'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <h6 class="mb-3 mt-4">Banking Details</h6>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="bank_name" class="form-label">Bank Name</label>
                            <input type="text" class="form-control" id="bank_name" name="bank_name" 
                                   value="<?= htmlspecialchars($user['bank_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="account_number" class="form-label">Account Number</label>
                            <input type="text" class="form-control" id="account_number" name="account_number" 
                                   value="<?= htmlspecialchars($user['account_number'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="branch_code" class="form-label">Branch Code</label>
                            <input type="text" class="form-control" id="branch_code" name="branch_code" 
                                   value="<?= htmlspecialchars($user['branch_code'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Your banking details are used for settlements.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: #f39c12; border-color: #f39c12;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: #e74c3c; color: white;">
                <h5 class="modal-title"><i class="bi bi-key"></i> Change Password</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="process/process_profile.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password *</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password *</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password *</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Change Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Upload Image Modal -->
<div class="modal fade" id="uploadImageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: #3498db; color: white;">
                <h5 class="modal-title"><i class="bi bi-camera"></i> Upload Profile Image</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="process/process_profile.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="upload_image">
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                    
                    <div class="mb-3">
                        <label for="profile_image" class="form-label">Select Image</label>
                        <input type="file" class="form-control" id="profile_image" name="profile_image" 
                               accept="image/jpeg,image/png,image/gif" required>
                        <small class="text-muted">Max size: 2MB. Allowed: JPG, PNG, GIF</small>
                    </div>
                    
                    <div class="text-center">
                        <img id="imagePreview" src="#" alt="Preview" class="img-fluid rounded-circle" 
                             style="max-width: 150px; max-height: 150px; display: none; border: 3px solid #f39c12;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">Upload Image</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.card {
    border: none;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    margin-bottom: 20px;
    border-radius: 10px;
    overflow: hidden;
}
.card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.card-header {
    font-weight: 600;
    border-bottom: none;
}
.table td, .table th {
    padding: 12px;
    vertical-align: middle;
    border-top: none;
}
.badge {
    font-size: 11px;
    padding: 5px 8px;
}
.timeline {
    max-height: 400px;
    overflow-y: auto;
}
.btn-group .btn {
    margin-right: 5px;
}
.border {
    transition: transform 0.2s;
}
.border:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
</style>

<script>
// Edit profile function
function editProfile() {
    new bootstrap.Modal(document.getElementById('editProfileModal')).show();
}

// Change password function
function changePassword() {
    new bootstrap.Modal(document.getElementById('changePasswordModal')).show();
}

// Upload image function
function uploadImage() {
    new bootstrap.Modal(document.getElementById('uploadImageModal')).show();
}

// Preview image before upload
document.getElementById('profile_image')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        if (file.size > 2 * 1024 * 1024) {
            alert('❌ File size must be less than 2MB');
            this.value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('imagePreview');
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(file);
    }
});

// Confirm before leaving if password fields don't match
document.getElementById('confirm_password')?.addEventListener('input', function() {
    const newPass = document.getElementById('new_password').value;
    const confirmPass = this.value;
    
    if (confirmPass && newPass !== confirmPass) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.transition = 'opacity 1s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 1000);
    });
}, 5000);
</script>

<?php
// Get the content
$content = ob_get_clean();

// Include the owner layout
require_once '../layouts/owner_layout.php';
?>