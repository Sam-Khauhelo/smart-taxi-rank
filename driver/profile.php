<?php
// driver/profile.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('driver');

// Set page title
$page_title = 'My Profile';

// Start output buffering
ob_start();

$user_id = $_SESSION['user_id'];
$driver_id = $_SESSION['driver_id'];

// Get user current data with driver details
try {
    $sql = "SELECT u.*, 
                   d.id as driver_id, 
                   d.id_number, 
                   d.license_expiry_date,
                   d.employment_type,
                   d.payment_rate,
                   d.added_by,
                   d.added_at,
                   o.id as owner_id,
                   ou.full_name as owner_name,
                   ou.phone_number as owner_phone,
                   t.id as taxi_id,
                   t.registration_number,
                   r.route_name,
                   (SELECT COUNT(*) FROM trips WHERE driver_id = d.id) as total_trips,
                   (SELECT COALESCE(SUM(owner_payout), 0) FROM trips WHERE driver_id = d.id) as total_owner_payout,
                   (SELECT COUNT(*) FROM trips WHERE driver_id = d.id AND DATE(departed_at) = CURDATE()) as today_trips,
                   (SELECT COALESCE(SUM(owner_payout), 0) FROM trips WHERE driver_id = d.id AND DATE(departed_at) = CURDATE()) as today_owner_payout
            FROM users u
            JOIN drivers d ON u.id = d.user_id
            LEFT JOIN owners o ON d.owner_id = o.id
            LEFT JOIN users ou ON o.user_id = ou.id
            LEFT JOIN taxis t ON d.taxi_id = t.id
            LEFT JOIN routes r ON t.route_id = r.id
            WHERE u.id = :id AND d.id = :driver_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id' => $user_id,
        ':driver_id' => $driver_id
    ]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo "<script>
            alert('Profile not found!');
            window.location.href = 'portal.php';
        </script>";
        exit();
    }
    
    // Calculate driver's actual earnings based on employment type
    $driver_earnings = 0;
    if ($user['employment_type'] == 'commission') {
        $driver_earnings = ($user['total_owner_payout'] ?? 0) * ($user['payment_rate'] / 100);
    } elseif ($user['employment_type'] == 'wage' || $user['employment_type'] == 'rental') {
        // Get days worked
        $days_sql = "SELECT COUNT(DISTINCT DATE(departed_at)) as days_worked 
                     FROM trips WHERE driver_id = ?";
        $days_stmt = $pdo->prepare($days_sql);
        $days_stmt->execute([$driver_id]);
        $days = $days_stmt->fetch();
        $driver_earnings = ($days['days_worked'] ?? 0) * ($user['payment_rate'] ?? 0);
    }
    
    // Get recent trips
    $recent_sql = "SELECT t.*, r.route_name, 
                          TIME(t.departed_at) as trip_time,
                          DATE(t.departed_at) as trip_date
                   FROM trips t
                   JOIN routes r ON t.route_id = r.id
                   WHERE t.driver_id = ?
                   ORDER BY t.departed_at DESC
                   LIMIT 5";
    $recent_stmt = $pdo->prepare($recent_sql);
    $recent_stmt->execute([$driver_id]);
    $recent_trips = $recent_stmt->fetchAll();
    
    // Get license status
    $license_expiry = strtotime($user['license_expiry_date']);
    $now = time();
    $days_to_expiry = ceil(($license_expiry - $now) / (60 * 60 * 24));
    $license_status = $license_expiry < $now ? 'expired' : ($days_to_expiry <= 30 ? 'warning' : 'valid');
    
    // Get queue position if any
    $queue_sql = "SELECT q.position, q.status, r.route_name
                  FROM queue q
                  JOIN routes r ON q.route_id = r.id
                  WHERE q.taxi_id = ? AND q.status IN ('waiting', 'loading')
                  ORDER BY q.position LIMIT 1";
    $queue_stmt = $pdo->prepare($queue_sql);
    $queue_stmt->execute([$user['taxi_id'] ?? 0]);
    $queue = $queue_stmt->fetch();
    
} catch (PDOException $e) {
    error_log("Profile error: " . $e->getMessage());
    echo "<script>
        alert('Error loading profile: " . addslashes($e->getMessage()) . "');
        window.location.href = 'portal.php';
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

    <!-- License Warning Alert -->
    <?php if ($license_status == 'expired'): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle"></i>
            <strong>License Expired!</strong> Your driver's license expired on <?= date('d M Y', $license_expiry) ?>. 
            Please update your license information immediately.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($license_status == 'warning'): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle"></i>
            <strong>License Expiring Soon!</strong> Your license will expire in <?= $days_to_expiry ?> days 
            (<?= date('d M Y', $license_expiry) ?>). Please renew before expiry.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

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
                             style="width: 150px; height: 150px; object-fit: cover; border: 3px solid #1e88e5;"
                             id="profileImage"
                             onerror="this.src='https://via.placeholder.com/150'">
                        <button class="btn btn-sm btn-primary position-absolute bottom-0 end-0 rounded-circle" 
                                onclick="uploadImage()"
                                style="width: 40px; height: 40px; background: #1e88e5; border-color: #1e88e5;">
                            <i class="bi bi-camera"></i>
                        </button>
                    </div>
                    <h3 class="mt-3"><?= htmlspecialchars($user['full_name']) ?></h3>
                    <p class="text-muted">
                        <i class="bi bi-person-badge"></i> Professional Driver
                    </p>
                    
                    <!-- License Status Badge -->
                    <div class="mb-3">
                        <?php if ($license_status == 'valid'): ?>
                            <span class="badge bg-success fs-6">
                                <i class="bi bi-check-circle"></i> License Valid
                            </span>
                        <?php elseif ($license_status == 'warning'): ?>
                            <span class="badge bg-warning fs-6">
                                <i class="bi bi-exclamation-triangle"></i> License Expiring Soon
                            </span>
                        <?php else: ?>
                            <span class="badge bg-danger fs-6">
                                <i class="bi bi-x-circle"></i> License Expired
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Queue Status -->
                    <?php if ($queue): ?>
                        <div class="alert alert-info py-2">
                            <i class="bi bi-list-ol"></i>
                            <strong>Queue Position: #<?= $queue['position'] ?></strong>
                            <br>
                            <small>Route: <?= htmlspecialchars($queue['route_name']) ?> | Status: <?= ucfirst($queue['status']) ?></small>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Quick Stats -->
                    <div class="row mt-3">
                        <div class="col-4">
                            <div class="border-end">
                                <h5 class="mb-0"><?= $user['total_trips'] ?></h5>
                                <small class="text-muted">Total Trips</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border-end">
                                <h5 class="mb-0"><?= $user['today_trips'] ?></h5>
                                <small class="text-muted">Today</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div>
                                <h5 class="mb-0">R <?= number_format($driver_earnings, 2) ?></h5>
                                <small class="text-muted">Total Earned</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Info Card -->
            <div class="card mb-4">
                <div class="card-header" style="background: #1e88e5; color: white;">
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
                        <tr>
                            <td><strong>Added By:</strong></td>
                            <td><i class="bi bi-person"></i> <?= htmlspecialchars($user['added_by'] ?? 'System') ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right Column - Personal & Employment Details -->
        <div class="col-md-8">
            <!-- Personal Information Card -->
            <div class="card mb-4">
                <div class="card-header" style="background: #0b2b40; color: white;">
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
                                <tr>
                                    <th>Email:</th>
                                    <td><i class="bi bi-envelope"></i> <?= htmlspecialchars($user['username']) ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table">
                                <tr>
                                    <th style="width: 120px;">License Number:</th>
                                    <td><?= htmlspecialchars($user['id_number'] ?? 'N/A') ?></td>
                                </tr>
                                <tr>
                                    <th>License Expiry:</th>
                                    <td>
                                        <?php if ($user['license_expiry_date']): ?>
                                            <?php if ($license_status == 'valid'): ?>
                                                <span class="text-success"><?= date('d M Y', $license_expiry) ?></span>
                                            <?php elseif ($license_status == 'warning'): ?>
                                                <span class="text-warning"><?= date('d M Y', $license_expiry) ?> (<?= $days_to_expiry ?> days)</span>
                                            <?php else: ?>
                                                <span class="text-danger"><?= date('d M Y', $license_expiry) ?> (EXPIRED)</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not provided</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Days Until Expiry:</th>
                                    <td>
                                        <?php if ($license_expiry): ?>
                                            <?php if ($license_expiry < $now): ?>
                                                <span class="badge bg-danger">Expired <?= abs($days_to_expiry) ?> days ago</span>
                                            <?php else: ?>
                                                <span class="badge bg-<?= $days_to_expiry <= 30 ? 'warning' : 'success' ?>">
                                                    <?= $days_to_expiry ?> days
                                                </span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Employment & Taxi Details Card -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header" style="background: #27ae60; color: white;">
                            <h5 class="mb-0"><i class="bi bi-briefcase"></i> Employment Details</h5>
                        </div>
                        <div class="card-body">
                            <table class="table">
                                <tr>
                                    <th>Owner:</th>
                                    <td>
                                        <strong><?= htmlspecialchars($user['owner_name'] ?? 'N/A') ?></strong>
                                        <?php if ($user['owner_phone']): ?>
                                            <br><small><i class="bi bi-telephone"></i> <?= htmlspecialchars($user['owner_phone']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Employment Type:</th>
                                    <td>
                                        <span class="badge bg-primary"><?= ucfirst($user['employment_type'] ?? 'N/A') ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Payment Rate:</th>
                                    <td>
                                        <?php if ($user['employment_type'] == 'commission'): ?>
                                            <strong><?= $user['payment_rate'] ?>%</strong> of owner payout
                                        <?php elseif ($user['employment_type'] == 'wage'): ?>
                                            <strong>R <?= number_format($user['payment_rate'] ?? 0, 2) ?></strong> per day
                                        <?php elseif ($user['employment_type'] == 'rental'): ?>
                                            <strong>R <?= number_format($user['payment_rate'] ?? 0, 2) ?></strong> per day (rental)
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Total Trips:</th>
                                    <td><?= $user['total_trips'] ?> trips</td>
                                </tr>
                                <tr>
                                    <th>Today's Trips:</th>
                                    <td><?= $user['today_trips'] ?> trips</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header" style="background: #e67e22; color: white;">
                            <h5 class="mb-0"><i class="bi bi-truck"></i> Assigned Taxi</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($user['taxi_id']): ?>
                                <div class="text-center mb-3">
                                    <i class="bi bi-taxi-front fs-1 text-primary"></i>
                                    <h4 class="mt-2"><?= htmlspecialchars($user['registration_number']) ?></h4>
                                </div>
                                <table class="table">
                                    <tr>
                                        <th>Route:</th>
                                        <td><?= htmlspecialchars($user['route_name'] ?? 'Not assigned') ?></td>
                                    </tr>
                                    <tr>
                                        <th>Status:</th>
                                        <td>
                                            <?php
                                            $taxi_status = $user['status'] ?? 'unknown';
                                            $status_colors = [
                                                'active' => 'success',
                                                'on_trip' => 'warning',
                                                'off_rank' => 'secondary',
                                                'maintenance' => 'danger'
                                            ];
                                            $color = $status_colors[$taxi_status] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?= $color ?>"><?= ucfirst($taxi_status) ?></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Today's Owner Payout:</th>
                                        <td class="fw-bold">R <?= number_format($user['today_owner_payout'] ?? 0, 2) ?></td>
                                    </tr>
                                </table>
                            <?php else: ?>
                                <div class="text-center py-3">
                                    <i class="bi bi-truck fs-1 text-muted"></i>
                                    <p class="text-muted mt-2">No taxi assigned</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Earnings Summary Card -->
            <div class="card mb-4">
                <div class="card-header" style="background: #9b59b6; color: white;">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> Earnings Summary</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="border p-3 rounded text-center">
                                <small class="text-muted">Owner Payout (Total)</small>
                                <h4 class="text-primary mb-0">R <?= number_format($user['total_owner_payout'] ?? 0, 2) ?></h4>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border p-3 rounded text-center">
                                <small class="text-muted">Your Earnings (Total)</small>
                                <h4 class="text-success mb-0">R <?= number_format($driver_earnings, 2) ?></h4>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border p-3 rounded text-center">
                                <small class="text-muted">Today's Owner Payout</small>
                                <h4 class="text-warning mb-0">R <?= number_format($user['today_owner_payout'] ?? 0, 2) ?></h4>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($user['employment_type'] == 'commission'): ?>
                        <div class="alert alert-info mt-3 mb-0">
                            <i class="bi bi-info-circle"></i>
                            You earn <strong><?= $user['payment_rate'] ?>% commission</strong> on owner payout.
                            Your total commission: <strong>R <?= number_format($driver_earnings, 2) ?></strong>
                        </div>
                    <?php elseif ($user['employment_type'] == 'wage'): ?>
                        <div class="alert alert-info mt-3 mb-0">
                            <i class="bi bi-info-circle"></i>
                            You earn a daily wage of <strong>R <?= number_format($user['payment_rate'] ?? 0, 2) ?></strong> per day worked.
                        </div>
                    <?php elseif ($user['employment_type'] == 'rental'): ?>
                        <div class="alert alert-warning mt-3 mb-0">
                            <i class="bi bi-info-circle"></i>
                            You pay a rental fee of <strong>R <?= number_format($user['payment_rate'] ?? 0, 2) ?></strong> per day.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Trips Card -->
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Trips</h5>
                    <a href="my_trips.php" class="btn btn-sm btn-light">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_trips)): ?>
                        <p class="text-muted text-center py-3">No recent trips</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Route</th>
                                        <th>Pass</th>
                                        <th>Fare</th>
                                        <th>Levy</th>
                                        <th>Payout</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_trips as $trip): ?>
                                    <tr>
                                        <td><?= date('d/m', strtotime($trip['trip_date'])) ?></td>
                                        <td><?= date('H:i', strtotime($trip['departed_at'])) ?></td>
                                        <td><?= htmlspecialchars($trip['route_name']) ?></td>
                                        <td class="text-center"><?= $trip['passenger_count'] ?></td>
                                        <td>R <?= number_format($trip['total_fare'], 2) ?></td>
                                        <td>R <?= number_format($trip['association_levy'], 2) ?></td>
                                        <td class="fw-bold">R <?= number_format($trip['owner_payout'], 2) ?></td>
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
</div>

<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: #1e88e5; color: white;">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Profile</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="process/process_profile.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_profile">
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                    <input type="hidden" name="driver_id" value="<?= $user['driver_id'] ?>">
                    
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
                            <label for="id_number" class="form-label">ID Number / License Number</label>
                            <input type="text" class="form-control" id="id_number" name="id_number" 
                                   value="<?= htmlspecialchars($user['id_number'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <h6 class="mb-3 mt-4">License Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="license_expiry" class="form-label">License Expiry Date</label>
                            <input type="date" class="form-control" id="license_expiry" name="license_expiry" 
                                   value="<?= $user['license_expiry_date'] ?>">
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> 
                        Your employment type and payment rate can only be changed by your owner.
                        If you need to update these, please contact <?= htmlspecialchars($user['owner_name'] ?? 'your owner') ?>.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: #1e88e5; border-color: #1e88e5;">Save Changes</button>
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
                             style="max-width: 150px; max-height: 150px; display: none; border: 3px solid #1e88e5;">
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
    display: flex;
    justify-content: space-between;
    align-items: center;
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
</script>

<?php
// Get the content
$content = ob_get_clean();

// Include the driver layout
require_once '../layouts/driver_layout.php';
?>