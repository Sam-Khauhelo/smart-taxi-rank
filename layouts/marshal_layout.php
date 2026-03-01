<?php
// layouts/marshal_layout.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is marshal
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'marshal') {
    header('Location: ../index.php');
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marshal Panel - Smart Taxi Rank</title>
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
            min-width: 250px;
            max-width: 250px;
            background: #1e3c72;
            color: #fff;
            transition: all 0.3s;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar .nav-link {
            color: #fff;
            padding: 15px 20px;
            transition: 0.3s;
            font-size: 16px;
        }
        .sidebar .nav-link:hover {
            background: #2a4b8c;
            color: #fff;
        }
        .sidebar .nav-link.active {
            background: #ffc107;
            color: #000;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
        }
        .sidebar .rank-info {
            background: #0a2647;
            padding: 20px;
            text-align: center;
            border-bottom: 2px solid #ffc107;
        }
        .sidebar .rank-info h4 {
            color: #ffc107;
            margin-bottom: 5px;
        }
        .sidebar .rank-info p {
            color: #fff;
            margin-bottom: 0;
        }
        .content {
            flex: 1;
            padding: 20px;
            background: #f0f2f5;
        }
        .navbar-top {
            background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
        }
        .queue-badge {
            background: #ffc107;
            color: #000;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 10px;
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
            <div class="rank-info">
                <h4><i class="bi bi-shield"></i> MARSHAL</h4>
                <p><?= htmlspecialchars($_SESSION['full_name'] ?? 'Marshal') ?></p>
                <small>Main Rank • Zone A</small>
            </div>
            
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                        <span class="queue-badge" id="queueCount">0</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'queue.php' ? 'active' : '' ?>" href="queue.php">
                        <i class="bi bi-list-ol"></i> Full Queue
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'trips.php' ? 'active' : '' ?>" href="trips.php">
                        <i class="bi bi-clock-history"></i> Today's Trips
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'history.php' ? 'active' : '' ?>" href="history.php">
                        <i class="bi bi-calendar"></i> Trip History
                    </a>
                </li>
                
                <li class="nav-item mt-4">
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
                    <h4 class="mb-0"><?= $page_title ?? 'Marshal Dashboard' ?></h4>
                    <small class="text-muted"><?= date('l, d F Y') ?></small>
                </div>
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <span class="badge bg-success p-2">
                            <i class="bi bi-wifi"></i> Online
                        </span>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['full_name']) ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person"></i> Profile</a></li>
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
                            <p class="mb-0">&copy; <?= date('Y') ?> Smart Taxi Rank. Developed by <strong>Khauhelo Sam</strong>. All rights reserved.</p>
                        </div>
                        <div class="col-md-6 text-end">
                            <p class="mb-0">Last sync: <span id="lastSync">Just now</span></p>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update queue count every 10 seconds
        function updateQueueCount() {
            fetch('../api/get_queue_count.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('queueCount').textContent = data.count;
                });
        }
        
        // Update last sync time
        function updateLastSync() {
            const now = new Date();
            document.getElementById('lastSync').textContent = now.toLocaleTimeString();
        }
        
        setInterval(() => {
            updateQueueCount();
            updateLastSync();
        }, 10000);
        
        updateQueueCount();
    </script>
</body>
</html>