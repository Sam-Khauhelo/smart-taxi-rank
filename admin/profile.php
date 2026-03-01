<?php
// admin/profile.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('admin');

// Set page title
$page_title = 'My Profile';

// Start output buffering
ob_start();

$user_id = $_SESSION['user_id'];

// Get user current data
try {
    $sql = "SELECT u.*, 
                   o.id as owner_id, o.id_number as owner_id_number, o.bank_name, o.account_number, o.branch_code,
                   d.id as driver_id, d.license_expiry_date, d.employment_type, d.payment_rate,
                   t.registration_number
            FROM users u
            LEFT JOIN owners o ON u.id = o.user_id
            LEFT JOIN drivers d ON u.id = d.user_id
            LEFT JOIN taxis t ON d.taxi_id = t.id
            WHERE u.id = :id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo "<script>
            alert('User not found!');
            window.location.href = 'dashboard.php';
        </script>";
        exit();
    }
    
} catch (PDOException $e) {
    error_log("Profile error: " . $e->getMessage());
    echo "<script>
        alert('Error loading profile!');
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
            <button class="btn btn-secondary" onclick="changePassword()">
                <i class="bi bi-key"></i> Change Password
            </button>
        </div>
    </div>

    <!-- Profile Info Card -->
    <div class="row">
        <div class="col-md-4">
            <!-- Profile Picture Card -->
            <div class="card mb-4">
                <div class="card-body text-center">
                    <div class="position-relative d-inline-block">
                        <img src="<?= '../assets/img/' . ($user['profile_image'] ?? 'default-avatar.png') ?>" 
                             alt="Profile" 
                             class="rounded-circle img-thumbnail" 
                             style="width: 150px; height: 150px; object-fit: cover;"
                             id="profileImage"
                             onerror="this.src='https://via.placeholder.com/150'">
                        <button class="btn btn-sm btn-primary position-absolute bottom-0 end-0 rounded-circle" 
                                onclick="uploadImage()"
                                style="width: 40px; height: 40px;">
                            <i class="bi bi-camera"></i>
                        </button>
                    </div>
                    <h4 class="mt-3"><?= htmlspecialchars($user['full_name']) ?></h4>
                    <p class="text-muted">
                        <i class="bi bi-shield"></i> <?= ucfirst($user['role']) ?>
                        <?php if ($user['role'] == 'driver'): ?>
                            <br><small>Taxi: <?= htmlspecialchars($user['registration_number'] ?? 'Not assigned') ?></small>
                        <?php endif; ?>
                    </p>
                    <div class="d-flex justify-content-center">
                        <span class="badge bg-<?= $user['is_active'] ? 'success' : 'danger' ?>">
                            <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Account Info Card -->
            <div class="card mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> Account Info</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>Email (Username):</strong></td>
                            <td>
                                <i class="bi bi-envelope"></i> <?= htmlspecialchars($user['username']) ?>
                                <span class="badge bg-info">Login Email</span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Member Since:</strong></td>
                            <td><?= date('d M Y', strtotime($user['created_at'])) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Last Login:</strong></td>
                            <td><?= $user['last_login'] ? date('d M Y H:i', strtotime($user['last_login'])) : 'Never' ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <!-- Personal Information Card -->
            <div class="card mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-person"></i> Personal Information</h5>
                </div>
                <div class="card-body">
                    <table class="table">
                        <tr>
                            <th style="width: 200px;">Full Name:</th>
                            <td><?= htmlspecialchars($user['full_name']) ?></td>
                        </tr>
                        <tr>
                            <th>Email Address:</th>
                            <td>
                                <strong><?= htmlspecialchars($user['username']) ?></strong>
                                <small class="text-muted">(used for login)</small>
                            </td>
                        </tr>
                        <tr>
                            <th>Phone Number:</th>
                            <td><?= htmlspecialchars($user['phone_number'] ?? 'Not provided') ?></td>
                        </tr>
                        <?php if ($user['role'] == 'owner'): ?>
                        <tr>
                            <th>ID Number:</th>
                            <td><?= htmlspecialchars($user['owner_id_number'] ?? 'Not provided') ?></td>
                        </tr>
                        <tr>
                            <th>Bank Name:</th>
                            <td><?= htmlspecialchars($user['bank_name'] ?? 'Not provided') ?></td>
                        </tr>
                        <tr>
                            <th>Account Number:</th>
                            <td><?= htmlspecialchars($user['account_number'] ?? 'Not provided') ?></td>
                        </tr>
                        <tr>
                            <th>Branch Code:</th>
                            <td><?= htmlspecialchars($user['branch_code'] ?? 'Not provided') ?></td>
                        </tr>
                        <?php elseif ($user['role'] == 'driver'): ?>
                        <tr>
                            <th>ID Number:</th>
                            <td><?= htmlspecialchars($user['id_number'] ?? 'Not provided') ?></td>
                        </tr>
                        <tr>
                            <th>License Expiry:</th>
                            <td>
                                <?php if ($user['license_expiry_date']): ?>
                                    <?php 
                                    $expiry = strtotime($user['license_expiry_date']);
                                    $now = time();
                                    $days_left = ceil(($expiry - $now) / (60 * 60 * 24));
                                    
                                    if ($expiry < $now):
                                    ?>
                                        <span class="badge bg-danger">Expired on <?= date('d M Y', $expiry) ?></span>
                                    <?php elseif ($days_left <= 30): ?>
                                        <span class="badge bg-warning"><?= $days_left ?> days left (<?= date('d M Y', $expiry) ?>)</span>
                                    <?php else: ?>
                                        <span class="badge bg-success"><?= date('d M Y', $expiry) ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    Not provided
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Employment Type:</th>
                            <td><?= ucfirst($user['employment_type'] ?? 'Not specified') ?></td>
                        </tr>
                        <tr>
                            <th>Payment Rate:</th>
                            <td>
                                <?php if ($user['employment_type'] == 'commission'): ?>
                                    <?= $user['payment_rate'] ?>% commission
                                <?php elseif ($user['employment_type'] == 'wage'): ?>
                                    R <?= number_format($user['payment_rate'] ?? 0, 2) ?> per day
                                <?php elseif ($user['employment_type'] == 'rental'): ?>
                                    R <?= number_format($user['payment_rate'] ?? 0, 2) ?> per day (rental)
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <!-- Recent Activity Card -->
            <div class="card mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Activity</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get recent activity based on role
                    if ($user['role'] == 'admin') {
                        $activity_sql = "SELECT 'user_registered' as action, created_at as date, 
                                         CONCAT('Registered user: ', full_name) as description 
                                         FROM users 
                                         ORDER BY created_at DESC LIMIT 5";
                        $stmt = $pdo->query($activity_sql);
                        $activities = $stmt->fetchAll();
                    } elseif ($user['role'] == 'driver' && $user['driver_id']) {
                        $activity_sql = "SELECT 'trip' as action, departed_at as date, 
                                         CONCAT('Trip on ', route_name, ' with ', passenger_count, ' passengers') as description 
                                         FROM trips t
                                         JOIN routes r ON t.route_id = r.id
                                         WHERE driver_id = :driver_id 
                                         ORDER BY departed_at DESC LIMIT 5";
                        $stmt = $pdo->prepare($activity_sql);
                        $stmt->execute([':driver_id' => $user['driver_id']]);
                        $activities = $stmt->fetchAll();
                    } elseif ($user['role'] == 'owner' && $user['owner_id']) {
                        $activity_sql = "SELECT 'settlement' as action, created_at as date, 
                                         CONCAT('Settlement of R', amount, ' for period ', period_start, ' to ', period_end) as description 
                                         FROM owner_settlements 
                                         WHERE owner_id = :owner_id 
                                         ORDER BY created_at DESC LIMIT 5";
                        $stmt = $pdo->prepare($activity_sql);
                        $stmt->execute([':owner_id' => $user['owner_id']]);
                        $activities = $stmt->fetchAll();
                    } else {
                        $activities = [];
                    }
                    
                    if (empty($activities)): ?>
                        <p class="text-muted text-center py-3">No recent activity</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($activities as $activity): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="bi bi-<?= $activity['action'] == 'trip' ? 'taxi' : 'activity' ?> me-2"></i>
                                        <?= htmlspecialchars($activity['description']) ?>
                                    </div>
                                    <small class="text-muted"><?= date('d M H:i', strtotime($activity['date'])) ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Profile</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="process/process_profile.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_profile">
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                    
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" 
                               value="<?= htmlspecialchars($user['full_name']) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address (Username) *</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?= htmlspecialchars($user['username']) ?>" required>
                        <small class="text-muted">This will be your login email</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone_number" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                               value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>">
                    </div>
                    
                    <?php if ($user['role'] == 'owner'): ?>
                    <hr>
                    <h6>Owner Information</h6>
                    <div class="mb-3">
                        <label for="owner_id_number" class="form-label">ID Number</label>
                        <input type="text" class="form-control" id="owner_id_number" name="owner_id_number" 
                               value="<?= htmlspecialchars($user['owner_id_number'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="bank_name" class="form-label">Bank Name</label>
                        <input type="text" class="form-control" id="bank_name" name="bank_name" 
                               value="<?= htmlspecialchars($user['bank_name'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="account_number" class="form-label">Account Number</label>
                        <input type="text" class="form-control" id="account_number" name="account_number" 
                               value="<?= htmlspecialchars($user['account_number'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="branch_code" class="form-label">Branch Code</label>
                        <input type="text" class="form-control" id="branch_code" name="branch_code" 
                               value="<?= htmlspecialchars($user['branch_code'] ?? '') ?>">
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-key"></i> Change Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                    <button type="submit" class="btn btn-warning">Change Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Upload Image Modal -->
<div class="modal fade" id="uploadImageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
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
                             style="max-width: 150px; max-height: 150px; display: none;">
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
.table td, .table th {
    padding: 12px;
    vertical-align: middle;
}
.card {
    border: none;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    margin-bottom: 20px;
}
.card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.list-group-item {
    border: none;
    border-bottom: 1px solid #f0f0f0;
}
.list-group-item:last-child {
    border-bottom: none;
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
            alert('File size must be less than 2MB');
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
</script>

<?php
// Get the content
$content = ob_get_clean();

// Include the admin layout
require_once '../layouts/admin_layout.php';
?>