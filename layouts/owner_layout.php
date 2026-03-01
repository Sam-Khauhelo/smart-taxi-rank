<?php
// layouts/owner_layout.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is owner
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
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
    <title>Owner Panel - Smart Taxi Rank</title>
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
            background: linear-gradient(135deg, #2c3e50 0%, #1a252f 100%);
            color: #fff;
            transition: all 0.3s;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 12px 20px;
            transition: 0.3s;
            border-left: 3px solid transparent;
        }
        .sidebar .nav-link:hover {
            background: #34495e;
            border-left-color: #f39c12;
            color: #fff;
        }
        .sidebar .nav-link.active {
            background: #34495e;
            border-left-color: #f39c12;
            color: #fff;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 22px;
            color: #f39c12;
        }
        .sidebar .owner-profile {
            background: #1a252f;
            padding: 25px 20px;
            text-align: center;
            border-bottom: 2px solid #f39c12;
        }
        .sidebar .owner-profile .avatar {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            margin-bottom: 15px;
            border: 3px solid #f39c12;
            padding: 3px;
            background: #fff;
        }
        .sidebar .owner-profile h4 {
            color: #fff;
            margin-bottom: 5px;
            font-size: 18px;
        }
        .sidebar .owner-profile p {
            color: #f39c12;
            margin-bottom: 0;
            font-size: 14px;
        }
        .sidebar .fleet-stats {
            padding: 15px;
            background: #1f2d3a;
            margin: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .sidebar .fleet-stats .stat {
            display: inline-block;
            width: 45%;
        }
        .sidebar .fleet-stats .stat .value {
            font-size: 20px;
            font-weight: bold;
            color: #f39c12;
        }
        .sidebar .fleet-stats .stat .label {
            font-size: 12px;
            color: #95a5a6;
        }
        .content {
            flex: 1;
            padding: 20px;
            background: #f5f7fa;
        }
        .navbar-top {
            background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 15px 25px;
            margin-bottom: 25px;
            border-radius: 12px;
        }
        .footer {
            background: #fff;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #e9ecef;
            margin-top: auto;
            border-radius: 12px 12px 0 0;
        }
        .balance-card {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: #fff;
            padding: 10px 20px;
            border-radius: 10px;
            margin-right: 15px;
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
            <div class="owner-profile">
                <img src="../assets/img/owner-avatar.png" alt="Owner" class="avatar" onerror="this.src='https://via.placeholder.com/90'">
                <h4><?= htmlspecialchars($_SESSION['full_name'] ?? 'Owner') ?></h4>
                <p><i class="bi bi-star-fill"></i> Premium Owner</p>
            </div>

            <div class="fleet-stats">
                <div class="stat">
                    <div class="value" id="sidebarTaxis">0</div>
                    <div class="label">Taxis</div>
                </div>
                <div class="stat">
                    <div class="value" id="sidebarDrivers">0</div>
                    <div class="label">Drivers</div>
                </div>
            </div>
            
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'my_taxis.php' ? 'active' : '' ?>" href="my_taxis.php">
                        <i class="bi bi-truck"></i> My Taxis
                        <span class="badge bg-warning text-dark float-end" id="taxiCount">0</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'my_drivers.php' ? 'active' : '' ?>" href="my_drivers.php">
                        <i class="bi bi-people"></i> My Drivers
                        <span class="badge bg-info float-end" id="driverCount">0</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'earnings.php' ? 'active' : '' ?>" href="earnings.php">
                        <i class="bi bi-graph-up"></i> Earnings
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'settlements.php' ? 'active' : '' ?>" href="settlements.php">
                        <i class="bi bi-cash-stack"></i> Settlements
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
                    <h4 class="mb-1"><?= $page_title ?? 'Owner Dashboard' ?></h4>
                    <small class="text-muted"><?= date('l, d F Y') ?></small>
                </div>
                <div class="d-flex align-items-center">
                    <div class="balance-card">
                        <small>Today's Earnings</small>
                        <h5 class="mb-0" id="todayEarnings">R 0.00</h5>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['full_name']) ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear"></i> Settings</a></li>
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
                            <p class="mb-0">Need help? <a href="#">Contact Support</a></p>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Load owner stats
        function loadOwnerStats() {
            fetch('../api/get_owner_stats.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('sidebarTaxis').textContent = data.taxis;
                    document.getElementById('sidebarDrivers').textContent = data.drivers;
                    document.getElementById('taxiCount').textContent = data.taxis;
                    document.getElementById('driverCount').textContent = data.drivers;
                    document.getElementById('todayEarnings').textContent = 'R ' + data.today_earnings.toFixed(2);
                });
        }
        
        loadOwnerStats();
        setInterval(loadOwnerStats, 30000);
    </script>
</body>
</html>