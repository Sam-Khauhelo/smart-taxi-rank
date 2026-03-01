<?php
// layouts/admin_layout.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Include database connection
require_once __DIR__ . '/../config/db.php';

// Get current page for active menu
$current_page = basename($_SERVER['PHP_SELF']);

// Get user's profile picture from database
try {
    $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $profile_image = $user['profile_image'] ?? null;
} catch (PDOException $e) {
    error_log("Error fetching profile image: " . $e->getMessage());
    $profile_image = null;
}

// Get real notifications from database
try {
    // Get recent registrations (last 24 hours)
    $recent_users = $pdo->query("
        SELECT 'registration' as type, full_name, role, created_at, 
               CONCAT('New ', role, ' registered: ', full_name) as message
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY created_at DESC
        LIMIT 3
    ")->fetchAll();

    // Get recent trips (last hour)
    $recent_trips = $pdo->query("
        SELECT 'trip' as type, t.id, tx.registration_number, r.route_name, 
               t.departed_at, 
               CONCAT('Trip completed: ', tx.registration_number, ' - ', r.route_name) as message
        FROM trips t
        JOIN taxis tx ON t.taxi_id = tx.id
        JOIN routes r ON t.route_id = r.id
        WHERE t.departed_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ORDER BY t.departed_at DESC
        LIMIT 3
    ")->fetchAll();

    // Get fare change notifications
    $fare_changes = $pdo->query("
        SELECT 'fare_change' as type, f.*, r.route_name, f.changed_at as time,
               CONCAT('Fare changed for ', r.route_name, ': R', f.old_fare, ' → R', f.new_fare) as message
        FROM fare_change_log f
        JOIN routes r ON f.route_id = r.id
        WHERE f.changed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY f.changed_at DESC
        LIMIT 3
    ")->fetchAll();

    // Get queue updates
    $queue_count = $pdo->query("
        SELECT COUNT(*) as waiting 
        FROM queue 
        WHERE status = 'waiting'
    ")->fetch()['waiting'];
    
    $queue_loading = $pdo->query("
        SELECT COUNT(*) as loading 
        FROM queue 
        WHERE status = 'loading'
    ")->fetch()['loading'];

    // Get failed registration attempts (last hour)
    $failed_attempts = $pdo->query("
        SELECT COUNT(*) as attempts 
        FROM failed_registration_attempts 
        WHERE attempt_time >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ")->fetch()['attempts'];

    // Get rank events today
    $events_today = $pdo->query("
        SELECT COUNT(*) as total FROM special_events 
        WHERE event_date = CURDATE() AND status = 'upcoming'
    ")->fetch()['total'] ?? 0;

    // Combine all notifications
    $notifications = [];
    
    // Add queue notification if there are waiting taxis
    if ($queue_count > 0) {
        $notifications[] = [
            'type' => 'queue',
            'message' => "Queue update: {$queue_count} taxi(s) waiting, {$queue_loading} loading",
            'time' => date('Y-m-d H:i:s'),
            'icon' => 'list-ol',
            'color' => 'info'
        ];
    }
    
    // Add failed attempts notification if any
    if ($failed_attempts > 0) {
        $notifications[] = [
            'type' => 'security',
            'message' => "⚠️ {$failed_attempts} failed registration attempt(s) in the last hour",
            'time' => date('Y-m-d H:i:s'),
            'icon' => 'shield-exclamation',
            'color' => 'danger'
        ];
    }
    
    // Add events notification
    if ($events_today > 0) {
        $notifications[] = [
            'type' => 'event',
            'message' => "📅 {$events_today} special event(s) happening today",
            'time' => date('Y-m-d H:i:s'),
            'icon' => 'calendar-event',
            'color' => 'warning'
        ];
    }
    
    // Add recent registrations
    foreach ($recent_users as $user) {
        $notifications[] = [
            'type' => 'registration',
            'message' => $user['message'],
            'time' => $user['created_at'],
            'icon' => 'person-plus',
            'color' => 'success'
        ];
    }
    
    // Add recent trips
    foreach ($recent_trips as $trip) {
        $notifications[] = [
            'type' => 'trip',
            'message' => $trip['message'],
            'time' => $trip['departed_at'],
            'icon' => 'taxi',
            'color' => 'primary'
        ];
    }
    
    // Add fare changes
    foreach ($fare_changes as $fare) {
        $notifications[] = [
            'type' => 'fare_change',
            'message' => $fare['message'],
            'time' => $fare['time'],
            'icon' => 'tag',
            'color' => 'warning'
        ];
    }
    
    // Sort notifications by time (newest first)
    usort($notifications, function($a, $b) {
        return strtotime($b['time']) - strtotime($a['time']);
    });
    
    // Limit to 5 notifications
    $notifications = array_slice($notifications, 0, 5);
    
    $notification_count = count($notifications);
    
} catch (PDOException $e) {
    error_log("Notifications error: " . $e->getMessage());
    $notifications = [];
    $notification_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Smart Taxi Rank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .wrapper {
            display: flex;
            flex: 1;
        }
        .sidebar {
            min-width: 260px;
            max-width: 260px;
            background: #1a1a2e;
            color: #fff;
            transition: all 0.3s;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        .sidebar .nav-link {
            color: #b0b0b0;
            padding: 12px 20px;
            transition: 0.3s;
            border-left: 3px solid transparent;
        }
        .sidebar .nav-link:hover {
            background: #2a2a3e;
            color: #fff;
            border-left-color: #ffc107;
        }
        .sidebar .nav-link.active {
            background: #2a2a3e;
            color: #fff;
            border-left-color: #007bff;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            font-size: 1.1rem;
        }
        .sidebar .nav-link.text-danger {
            color: #ff6b6b !important;
        }
        .sidebar .nav-link.text-danger:hover {
            background: #3a2a3e;
            color: #ff8b8b !important;
        }
        .content {
            flex: 1;
            padding: 20px;
            background: #f8f9fa;
        }
        .navbar-top {
            background: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 10px;
        }
        .footer {
            background: #fff;
            padding: 15px;
            text-align: center;
            border-top: 1px solid #dee2e6;
            margin-top: auto;
            border-radius: 10px 10px 0 0;
        }
        .user-info {
            background: linear-gradient(135deg, #2a2a3e, #1a1a2e);
            padding: 25px 20px;
            text-align: center;
            border-bottom: 2px solid #ffc107;
        }
        .user-info img {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            margin-bottom: 10px;
            border: 3px solid #ffc107;
            padding: 3px;
            background: #fff;
            object-fit: cover;
        }
        .user-info h5 {
            color: #fff;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .user-info p {
            color: #ffc107;
            margin-bottom: 0;
            font-size: 14px;
        }
        
        /* Notification styles */
        .notification-item {
            min-width: 320px;
            padding: 12px 15px;
            transition: background 0.2s;
            border-bottom: 1px solid #f0f0f0;
        }
        .notification-item:hover {
            background: #f8f9fa;
        }
        .notification-time {
            font-size: 0.75rem;
            color: #6c757d;
        }
        .dropdown-menu {
            max-height: 450px;
            overflow-y: auto;
            padding: 0;
            border: none;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .dropdown-header {
            background: #f8f9fa;
            padding: 12px 15px;
            font-weight: 600;
            border-bottom: 1px solid #dee2e6;
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            font-size: 0.6rem;
            padding: 0.25rem 0.4rem;
            animation: pulse 2s infinite;
        }
        .btn-light {
            position: relative;
            background: #f8f9fa;
            border: none;
        }
        .btn-light:hover {
            background: #e9ecef;
        }
        
        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
            }
        }
        
        .sidebar-heading {
            color: #ffc107;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 15px 20px 5px;
            margin: 0;
        }
        
        .badge-count {
            float: right;
            background: #007bff;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.7rem;
        }
        
        /* Profile image in top navbar */
        .profile-img-small {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #ffc107;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                min-width: 100%;
                max-width: 100%;
                height: auto;
                position: relative;
            }
            .wrapper {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="user-info">
                <!-- Dynamic profile image from database -->
                <img src="<?= $profile_image ? '../assets/img/' . $profile_image : '../assets/img/admin-avatar.png' ?>" 
                     alt="Admin" 
                     onerror="this.src='https://via.placeholder.com/90/1a1a2e/ffc107?text=Admin'">
                <h5><?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?></h5>
                <p><i class="bi bi-shield-lock"></i> Administrator</p>
            </div>
            
            <div class="sidebar-heading">MAIN NAVIGATION</div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'register_user.php' ? 'active' : '' ?>" href="register_user.php">
                        <i class="bi bi-person-plus"></i> Register User
                    </a>
                </li>
            </ul>
            
            <div class="sidebar-heading mt-3">MANAGEMENT</div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'manage_owners.php' ? 'active' : '' ?>" href="manage_owners.php">
                        <i class="bi bi-briefcase"></i> Manage Owners
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'manage_drivers.php' ? 'active' : '' ?>" href="manage_drivers.php">
                        <i class="bi bi-person-badge"></i> Manage Drivers
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= strpos($current_page, 'manage_taxis') !== false ? 'active' : '' ?>" href="manage_taxis.php">
                        <i class="bi bi-truck"></i> Manage Taxis
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'manage_routes.php' ? 'active' : '' ?>" href="manage_routes.php">
                        <i class="bi bi-signpost"></i> Manage Routes
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'ranks.php' ? 'active' : '' ?>" href="ranks.php">
                        <i class="bi bi-pin-map"></i> Manage Ranks
                        <?php if (($events_today ?? 0) > 0): ?>
                            <span class="badge-count"><?= $events_today ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
            
            <div class="sidebar-heading mt-3">ANALYTICS</div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'reports.php' ? 'active' : '' ?>" href="reports.php">
                        <i class="bi bi-graph-up"></i> Reports
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'route_optimization.php' ? 'active' : '' ?>" href="route_optimization.php">
                        <i class="bi bi-bar-chart"></i> Route Optimization
                    </a>
                </li>
            </ul>
            
            <div class="sidebar-heading mt-3">SYSTEM</div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'failed_attempts.php' ? 'active' : '' ?>" href="failed_attempts.php">
                        <i class="bi bi-shield-exclamation"></i> Security Log
                        <?php if (($failed_attempts ?? 0) > 0): ?>
                            <span class="badge-count"><?= $failed_attempts ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'notifications.php' ? 'active' : '' ?>" href="notifications.php">
                        <i class="bi bi-bell"></i> Notifications
                        <?php if ($notification_count > 0): ?>
                            <span class="badge-count"><?= $notification_count ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'settings.php' ? 'active' : '' ?>" href="settings.php">
                        <i class="bi bi-gear"></i> Settings
                    </a>
                </li>
            </ul>
            
            <div class="sidebar-heading mt-3">ACCOUNT</div>
            <ul class="nav flex-column mb-4">
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'profile.php' ? 'active' : '' ?>" href="profile.php">
                        <i class="bi bi-person"></i> My Profile
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link text-danger" href="../logout.php">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Main Content Area -->
        <div class="content">
            <!-- Header/Navbar -->
            <div class="navbar-top d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-0"><?= $page_title ?? 'Dashboard' ?></h4>
                    <small class="text-muted"><?= date('l, d F Y') ?></small>
                </div>
                <div class="d-flex align-items-center">
                    <!-- Notifications Dropdown -->
                    <div class="dropdown me-3">
                        <button class="btn btn-light position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell"></i>
                            <?php if ($notification_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge">
                                    <?= $notification_count > 9 ? '9+' : $notification_count ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">
                                <i class="bi bi-bell"></i> Notifications 
                                <span class="badge bg-secondary ms-2"><?= $notification_count ?> new</span>
                            </h6></li>
                            
                            <?php if (empty($notifications)): ?>
                                <li><span class="dropdown-item-text text-muted text-center py-4">
                                    <i class="bi bi-check-circle fs-4 d-block mb-2"></i>
                                    No new notifications
                                </span></li>
                            <?php else: ?>
                                <?php foreach ($notifications as $note): ?>
                                    <li>
                                        <a class="dropdown-item notification-item" href="notifications.php">
                                            <div class="d-flex align-items-center">
                                                <div class="me-3">
                                                    <span class="badge bg-<?= $note['color'] ?> p-2 rounded-circle">
                                                        <i class="bi bi-<?= $note['icon'] ?>"></i>
                                                    </span>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="d-flex justify-content-between">
                                                        <small class="fw-bold"><?= htmlspecialchars($note['message']) ?></small>
                                                    </div>
                                                    <small class="notification-time">
                                                        <i class="bi bi-clock"></i> 
                                                        <?php 
                                                        $time = strtotime($note['time']);
                                                        $now = time();
                                                        $diff = $now - $time;
                                                        
                                                        if ($diff < 60) {
                                                            echo 'Just now';
                                                        } elseif ($diff < 3600) {
                                                            echo floor($diff / 60) . ' minutes ago';
                                                        } elseif ($diff < 86400) {
                                                            echo floor($diff / 3600) . ' hours ago';
                                                        } else {
                                                            echo date('d M H:i', $time);
                                                        }
                                                        ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <li><hr class="dropdown-divider my-1"></li>
                            <li>
                                <a class="dropdown-item text-center text-primary py-2" href="notifications.php">
                                    <i class="bi bi-eye"></i> View All Notifications
                                </a>
                            </li>
                        </ul>
                    </div>
                    
                    <!-- User Dropdown with Dynamic Profile Image -->
                    <div class="dropdown">
                        <button class="btn btn-light dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown">
                            <img src="<?= $profile_image ? '../assets/img/' . $profile_image : '../assets/img/admin-avatar.png' ?>" 
                                 alt="Profile" 
                                 class="profile-img-small me-2"
                                 onerror="this.src='https://via.placeholder.com/35/1a1a2e/ffc107?text=U'">
                            <span><?= htmlspecialchars($_SESSION['full_name']) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear"></i> Settings</a></li>
                            <li><a class="dropdown-item" href="notifications.php"><i class="bi bi-bell"></i> Notifications</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Page Content (Dynamic) -->
            <div class="page-content">
                <?= $content ?? '' ?>
            </div>

            <!-- Footer -->
            <footer class="footer mt-4">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-6 text-start">
                            <p class="mb-0">
                                <i class="bi bi-c-circle"></i> <?= date('Y') ?> Smart Taxi Rank System. 
                                <span class="text-muted">Developed by <strong>Khauhelo Sam</strong>. All rights reserved.</span>
                            </p>
                        </div>
                        <div class="col-md-6 text-end">
                            <p class="mb-0">
                                <span class="badge bg-primary me-2">v2.0.0</span>
                                <a href="#" class="text-decoration-none me-2">Privacy</a>
                                <a href="#" class="text-decoration-none">Terms</a>
                            </p>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>