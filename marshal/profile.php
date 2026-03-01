<?php
// marshal/profile.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('marshal');

// Set page title
$page_title = 'My Profile';

// Start output buffering
ob_start();

$user_id = $_SESSION['user_id'];

// Get user current data with marshal details
try {
    // Check if marshals table exists and get marshal details
    $marshal_sql = "SELECT m.* FROM marshals m WHERE m.user_id = ?";
    $marshal_stmt = $pdo->prepare($marshal_sql);
    $marshal_stmt->execute([$user_id]);
    $marshal = $marshal_stmt->fetch();
    
    // Get user data
    $sql = "SELECT u.* 
            FROM users u
            WHERE u.id = :id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo "<script>
            alert('Profile not found!');
            window.location.href = 'dashboard.php';
        </script>";
        exit();
    }
    
    // Get today's stats
    $today_sql = "SELECT 
                    COUNT(*) as total_trips,
                    COALESCE(SUM(passenger_count), 0) as total_passengers,
                    COALESCE(SUM(total_fare), 0) as total_revenue
                  FROM trips 
                  WHERE DATE(departed_at) = CURDATE()";
    $today_stmt = $pdo->query($today_sql);
    $today_stats = $today_stmt->fetch();
    
    // Get queue stats
    $queue_sql = "SELECT 
                    COUNT(*) as total_waiting,
                    SUM(CASE WHEN status = 'loading' THEN 1 ELSE 0 END) as loading_count
                  FROM queue 
                  WHERE status IN ('waiting', 'loading')";
    $queue_stmt = $pdo->query($queue_sql);
    $queue_stats = $queue_stmt->fetch();
    
    // Get recent activity
    $activity_sql = "SELECT 
                        'trip' as type,
                        departed_at as date,
                        CONCAT('Trip completed: ', tx.registration_number, ' - ', r.route_name, ' (', t.passenger_count, ' pax)') as description
                     FROM trips t
                     JOIN taxis tx ON t.taxi_id = tx.id
                     JOIN routes r ON t.route_id = r.id
                     ORDER BY t.departed_at DESC
                     LIMIT 10";
    $activity_stmt = $pdo->query($activity_sql);
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

    <!-- Main Profile Row -->
    <div class="row">
        <!-- Left Column - Profile Picture & Quick Info -->
        <div class="col-md-4">
            <!-- Profile Picture Card -->
            <div class="card mb-4">
                <div class="card-body text-center">
                    <div class="position-relative d-inline-block">
                        <img src="<?= !empty($user['profile_image']) ? '../assets/img/' . $user['profile_image'] : 'https://via.placeholder.com/150' ?>" 
                             alt="Profile" 
                             class="rounded-circle img-thumbnail" 
                             style="width: 150px; height: 150px; object-fit: cover; border: 3px solid #ffc107;"
                             id="profileImage"
                             onerror="this.src='https://via.placeholder.com/150'">
                        <button class="btn btn-sm btn-primary position-absolute bottom-0 end-0 rounded-circle" 
                                onclick="uploadImage()"
                                style="width: 40px; height: 40px; background: #ffc107; border-color: #ffc107;">
                            <i class="bi bi-camera"></i>
                        </button>
                    </div>
                    <h3 class="mt-3"><?= htmlspecialchars($user['full_name']) ?></h3>
                    <p class="text-muted">
                        <i class="bi bi-shield"></i> Rank Marshal
                        <?php if ($marshal && !empty($marshal['station_point'])): ?>
                            <br><small class="text-muted">Station: <?= htmlspecialchars($marshal['station_point']) ?></small>
                        <?php endif; ?>
                    </p>
                    
                    <!-- Status Badge -->
                    <div class="mb-3">
                        <?php if ($user['is_active']): ?>
                            <span class="badge bg-success fs-6">
                                <i class="bi bi-check-circle"></i> Active
                            </span>
                        <?php else: ?>
                            <span class="badge bg-danger fs-6">
                                <i class="bi bi-x-circle"></i> Inactive
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Quick Stats -->
                    <div class="row mt-3">
                        <div class="col-4">
                            <div class="border-end">
                                <h5 class="mb-0"><?= $queue_stats['total_waiting'] ?? 0 ?></h5>
                                <small class="text-muted">Waiting</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border-end">
                                <h5 class="mb-0"><?= $queue_stats['loading_count'] ?? 0 ?></h5>
                                <small class="text-muted">Loading</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div>
                                <h5 class="mb-0"><?= $today_stats['total_trips'] ?? 0 ?></h5>
                                <small class="text-muted">Today</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Info Card -->
            <div class="card mb-4">
                <div class="card-header" style="background: #1e3c72; color: white;">
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
                            <td><strong>Role:</strong></td>
                            <td>
                                <span class="badge bg-warning">Marshal</span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Today's Performance Card -->
            <div class="card mb-4">
                <div class="card-header" style="background: #28a745; color: white;">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> Today's Performance</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Trips Managed:</span>
                        <strong><?= $today_stats['total_trips'] ?? 0 ?></strong>
                    </div>
                    <div class="progress mb-3" style="height: 10px;">
                        <div class="progress-bar bg-success" role="progressbar" 
                             style="width: <?= min(100, ($today_stats['total_trips'] ?? 0) * 10) ?>%"></div>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span>Passengers:</span>
                        <strong><?= number_format($today_stats['total_passengers'] ?? 0) ?></strong>
                    </div>
                    <div class="progress mb-3" style="height: 10px;">
                        <div class="progress-bar bg-info" role="progressbar" 
                             style="width: <?= min(100, ($today_stats['total_passengers'] ?? 0) / 10) ?>%"></div>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span>Revenue Processed:</span>
                        <strong>R <?= number_format($today_stats['total_revenue'] ?? 0, 2) ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Personal Details & Activity -->
        <div class="col-md-8">
            <!-- Personal Information Card -->
            <div class="card mb-4">
                <div class="card-header" style="background: #1e3c72; color: white;">
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
                                    <th>Phone Number:</th>
                                    <td>
                                        <?php if ($user['phone_number']): ?>
                                            <i class="bi bi-telephone"></i> <?= htmlspecialchars($user['phone_number']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not provided</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Email:</th>
                                    <td><i class="bi bi-envelope"></i> <?= htmlspecialchars($user['username']) ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table">
                                <tr>
                                    <th style="width: 120px;">Station Point:</th>
                                    <td>
                                        <?php if ($marshal && !empty($marshal['station_point'])): ?>
                                            <span class="badge bg-info"><?= htmlspecialchars($marshal['station_point']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Main Rank</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Shift:</th>
                                    <td>
                                        <?php if ($marshal && !empty($marshal['shift'])): ?>
                                            <span class="badge bg-<?= $marshal['shift'] == 'morning' ? 'warning' : ($marshal['shift'] == 'afternoon' ? 'primary' : 'dark') ?>">
                                                <?= ucfirst($marshal['shift']) ?> Shift
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Not set</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Work Schedule Card -->
            <div class="card mb-4">
                <div class="card-header" style="background: #17a2b8; color: white;">
                    <h5 class="mb-0"><i class="bi bi-calendar-check"></i> Work Schedule</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 text-center mb-3">
                            <div class="border rounded p-2 <?= date('N') == 1 ? 'bg-warning' : '' ?>">
                                <strong>Mon</strong>
                                <br>
                                <small>06:00 - 14:00</small>
                            </div>
                        </div>
                        <div class="col-md-3 text-center mb-3">
                            <div class="border rounded p-2 <?= date('N') == 2 ? 'bg-warning' : '' ?>">
                                <strong>Tue</strong>
                                <br>
                                <small>06:00 - 14:00</small>
                            </div>
                        </div>
                        <div class="col-md-3 text-center mb-3">
                            <div class="border rounded p-2 <?= date('N') == 3 ? 'bg-warning' : '' ?>">
                                <strong>Wed</strong>
                                <br>
                                <small>06:00 - 14:00</small>
                            </div>
                        </div>
                        <div class="col-md-3 text-center mb-3">
                            <div class="border rounded p-2 <?= date('N') == 4 ? 'bg-warning' : '' ?>">
                                <strong>Thu</strong>
                                <br>
                                <small>06:00 - 14:00</small>
                            </div>
                        </div>
                        <div class="col-md-3 text-center mb-3">
                            <div class="border rounded p-2 <?= date('N') == 5 ? 'bg-warning' : '' ?>">
                                <strong>Fri</strong>
                                <br>
                                <small>06:00 - 14:00</small>
                            </div>
                        </div>
                        <div class="col-md-3 text-center mb-3">
                            <div class="border rounded p-2 bg-secondary text-white">
                                <strong>Sat</strong>
                                <br>
                                <small>Off</small>
                            </div>
                        </div>
                        <div class="col-md-3 text-center mb-3">
                            <div class="border rounded p-2 bg-secondary text-white">
                                <strong>Sun</strong>
                                <br>
                                <small>Off</small>
                            </div>
                        </div>
                        <div class="col-md-3 text-center mb-3">
                            <div class="border rounded p-2 bg-success text-white">
                                <strong>Today</strong>
                                <br>
                                <small>On Duty</small>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info mt-2 mb-0">
                        <i class="bi bi-info-circle"></i> Current shift: <strong>Morning (06:00 - 14:00)</strong>
                    </div>
                </div>
            </div>

            <!-- Queue Management Stats -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header" style="background: #fd7e14; color: white;">
                            <h5 class="mb-0"><i class="bi bi-list-ol"></i> Queue Statistics</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="queueChart" height="150"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header" style="background: #6f42c1; color: white;">
                            <h5 class="mb-0"><i class="bi bi-clock"></i> Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="queue.php" class="btn btn-warning btn-lg">
                                    <i class="bi bi-list-ol"></i> Manage Queue
                                </a>
                                <a href="trips.php" class="btn btn-info btn-lg">
                                    <i class="bi bi-clock-history"></i> View Today's Trips
                                </a>
                                <a href="history.php" class="btn btn-secondary btn-lg">
                                    <i class="bi bi-calendar-range"></i> Trip History
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity Card -->
            <div class="card">
                <div class="card-header bg-dark text-white">
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
                                        <span class="badge bg-primary p-2"><i class="bi bi-taxi-front"></i></span>
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
        </div>
    </div>
</div>

<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: #1e3c72; color: white;">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Profile</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="process/process_profile.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_profile">
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                    
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
                            <label for="station_point" class="form-label">Station Point</label>
                            <input type="text" class="form-control" id="station_point" name="station_point" 
                                   value="<?= htmlspecialchars($marshal['station_point'] ?? 'Main Rank') ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="shift" class="form-label">Preferred Shift</label>
                            <select class="form-select" id="shift" name="shift">
                                <option value="morning" <?= ($marshal['shift'] ?? '') == 'morning' ? 'selected' : '' ?>>Morning (06:00 - 14:00)</option>
                                <option value="afternoon" <?= ($marshal['shift'] ?? '') == 'afternoon' ? 'selected' : '' ?>>Afternoon (14:00 - 22:00)</option>
                                <option value="night" <?= ($marshal['shift'] ?? '') == 'night' ? 'selected' : '' ?>>Night (22:00 - 06:00)</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: #1e3c72; border-color: #1e3c72;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: #dc3545; color: white;">
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
            <div class="modal-header" style="background: #17a2b8; color: white;">
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
                             style="max-width: 150px; max-height: 150px; display: none; border: 3px solid #ffc107;">
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

// Queue Chart
const queueCtx = document.getElementById('queueChart')?.getContext('2d');
if (queueCtx) {
    new Chart(queueCtx, {
        type: 'doughnut',
        data: {
            labels: ['Waiting', 'Loading', 'Available'],
            datasets: [{
                data: [
                    <?= $queue_stats['total_waiting'] ?? 0 ?>,
                    <?= $queue_stats['loading_count'] ?? 0 ?>,
                    20 // Example available spots
                ],
                backgroundColor: [
                    'rgba(255, 193, 7, 0.8)',
                    'rgba(23, 162, 184, 0.8)',
                    'rgba(40, 167, 69, 0.8)'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}
</script>

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
    font-size: 12px;
    padding: 6px 10px;
}
.border {
    transition: transform 0.2s;
}
.border:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.progress {
    border-radius: 10px;
}
.timeline {
    max-height: 300px;
    overflow-y: auto;
}
</style>

<?php
// Get the content
$content = ob_get_clean();

// Include the marshal layout
require_once '../layouts/marshal_layout.php';
?>