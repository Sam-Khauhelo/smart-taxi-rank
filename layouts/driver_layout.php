<?php
// layouts/driver_layout.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is driver
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    header('Location: ../index.php');
    exit();
}

// Get driver info
$driver_name = $_SESSION['full_name'] ?? 'Driver';
$taxi_reg = $_SESSION['taxi_reg'] ?? 'No Taxi';
$driver_id = $_SESSION['driver_id'] ?? 0;

$current_page = basename($_SERVER['PHP_SELF']);

// Get profile image
try {
    require_once __DIR__ . '/../config/db.php';
    $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $profile_image = $user['profile_image'] ?? null;
} catch (Exception $e) {
    $profile_image = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Driver Portal - Smart Taxi Rank</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- PWA Support -->
    <link rel="manifest" href="../driver/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Taxi Driver">
    <meta name="format-detection" content="telephone=no">
    <meta name="theme-color" content="#1e88e5">
    <meta name="mobile-web-app-capable" content="yes">
    
    <!-- iOS Icons -->
    <link rel="apple-touch-icon" href="../assets/img/icons/icon-152x152.png">
    <link rel="apple-touch-icon" sizes="152x152" href="../assets/img/icons/icon-152x152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/img/icons/icon-192x192.png">
    <link rel="apple-touch-icon" sizes="167x167" href="../assets/img/icons/icon-152x152.png">
    
    <style>
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
        }
        .wrapper {
            display: flex;
            flex: 1;
        }
        .sidebar {
            min-width: 240px;
            max-width: 240px;
            background: #0b2b40;
            color: #fff;
            transition: all 0.3s;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar .nav-link {
            color: #b0e0e6;
            padding: 12px 20px;
            transition: 0.3s;
            font-size: 15px;
        }
        .sidebar .nav-link:hover {
            background: #1e4a62;
            color: #fff;
        }
        .sidebar .nav-link.active {
            background: #1e88e5;
            color: #fff;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 22px;
        }
        .sidebar .driver-info {
            background: #05222f;
            padding: 20px 15px;
            text-align: center;
            border-bottom: 3px solid #1e88e5;
        }
        .sidebar .driver-info img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 3px solid #1e88e5;
            object-fit: cover;
            margin-bottom: 10px;
        }
        .sidebar .driver-info .taxi-badge {
            background: #1e88e5;
            color: #fff;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            display: inline-block;
            margin-top: 10px;
        }
        .sidebar .queue-position {
            background: #ffc107;
            color: #000;
            padding: 12px;
            margin: 15px;
            border-radius: 10px;
            text-align: center;
            font-weight: bold;
            transition: background 0.3s;
        }
        .sidebar .queue-position .number {
            font-size: 32px;
            display: block;
            line-height: 1;
        }
        .content {
            flex: 1;
            padding: 20px;
            background: #eef2f7;
        }
        .navbar-top {
            background: #fff;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .footer {
            background: #fff;
            padding: 15px;
            text-align: center;
            border-top: 1px solid #dee2e6;
            margin-top: auto;
        }
        .today-stats {
            background: #1e88e5;
            color: #fff;
            padding: 8px 15px;
            border-radius: 25px;
            margin-right: 15px;
            font-weight: 500;
        }
        
        /* Install button */
        #installApp {
            display: none;
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #1e88e5;
            color: white;
            border: none;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            cursor: pointer;
            animation: pulse 2s infinite;
            align-items: center;
            justify-content: center;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        /* Offline indicator */
        #offlineIndicator {
            display: none;
            position: fixed;
            bottom: 20px;
            left: 20px;
            background: #ffc107;
            color: #000;
            padding: 8px 15px;
            border-radius: 50px;
            z-index: 1000;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        /* Update notification */
        .update-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            z-index: 2000;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            animation: slideIn 0.3s;
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
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
            .today-stats {
                font-size: 0.8rem;
                padding: 5px 8px;
            }
        }
    </style>
</head>
<body>
    <!-- Install App Button -->
    <button id="installApp" onclick="installApp()">
        <i class="bi bi-download fs-4"></i>
    </button>
    
    <!-- Offline Indicator -->
    <div id="offlineIndicator">
        <i class="bi bi-wifi-off"></i> Offline Mode
    </div>

    <div class="wrapper">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="driver-info">
                <img src="<?= $profile_image ? '../assets/img/' . $profile_image : '../assets/img/driver-avatar.png' ?>" 
                     alt="Driver" 
                     onerror="this.src='https://via.placeholder.com/80/0b2b40/1e88e5?text=Driver'">
                <h5 class="mt-2"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Driver') ?></h5>
                <div class="taxi-badge">
                    <i class="bi bi-taxi-front"></i> <?= htmlspecialchars($_SESSION['taxi_reg'] ?? 'No Taxi') ?>
                </div>
            </div>

            <!-- Live Queue Position -->
            <div class="queue-position" id="queuePosition">
                <div>Your Position</div>
                <span class="number" id="positionNumber">-</span>
                <small id="queueStatus">Checking...</small>
            </div>
            
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'portal.php' ? 'active' : '' ?>" href="portal.php">
                        <i class="bi bi-house-door"></i> Home
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'my_trips.php' ? 'active' : '' ?>" href="my_trips.php">
                        <i class="bi bi-list-check"></i> My Trips
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'my_earnings.php' ? 'active' : '' ?>" href="my_earnings.php">
                        <i class="bi bi-cash-stack"></i> My Earnings
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link" href="#" onclick="shareApp()">
                        <i class="bi bi-share"></i> Share App
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link" href="#" onclick="checkForUpdates()">
                        <i class="bi bi-arrow-repeat"></i> Check Updates
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
                    <h4 class="mb-0"><?= $page_title ?? 'Driver Portal' ?></h4>
                    <small class="text-muted"><?= date('l, d F Y') ?></small>
                </div>
                <div class="d-flex align-items-center">
                    <div class="today-stats">
                        <i class="bi bi-truck"></i> <span id="todayTrips">0</span> trips
                        <span class="mx-2">|</span>
                        <i class="bi bi-cash"></i> R<span id="todayEarnings">0.00</span>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle btn-sm" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person"></i> Menu
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="#" onclick="clearCache()"><i class="bi bi-trash"></i> Clear Cache</a></li>
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
                            <p class="mb-0">&copy; <?= date('Y') ?> Smart Taxi Rank. Developed by <strong>Khauhelo Sam</strong></p>
                        </div>
                        <div class="col-md-6 text-end">
                            <p class="mb-0">v2.0.0 | <span id="lastUpdated">Just now</span></p>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // ============================================
        // PWA SERVICE WORKER REGISTRATION
        // ============================================
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('../driver/sw.js')
                    .then(function(reg) {
                        console.log('✅ Service Worker registered successfully');
                        
                        // Check for updates
                        reg.addEventListener('updatefound', function() {
                            const newWorker = reg.installing;
                            console.log('New version downloading...');
                            
                            newWorker.addEventListener('statechange', function() {
                                if (newWorker.state === 'installed') {
                                    if (navigator.serviceWorker.controller) {
                                        console.log('New version ready');
                                        showUpdateNotification();
                                    }
                                }
                            });
                        });
                    })
                    .catch(function(err) {
                        console.log('❌ Service Worker registration failed:', err);
                    });
            });
        }

        // ============================================
        // APP INSTALLATION
        // ============================================
        let deferredPrompt;
        
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            
            // Show install button
            const installBtn = document.getElementById('installApp');
            installBtn.style.display = 'flex';
        });

        function installApp() {
            if (!deferredPrompt) {
                alert('App already installed or not available on this browser');
                return;
            }
            
            deferredPrompt.prompt();
            
            deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    console.log('User installed the app');
                    
                    // Track install
                    fetch('../api/track_install.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            driver_id: <?= $driver_id ?>,
                            platform: 'pwa'
                        })
                    });
                }
                deferredPrompt = null;
                document.getElementById('installApp').style.display = 'none';
            });
        }

        // ============================================
        // OFFLINE/ONLINE DETECTION
        // ============================================
        function updateOnlineStatus() {
            const indicator = document.getElementById('offlineIndicator');
            if (navigator.onLine) {
                indicator.style.display = 'none';
                console.log('Online');
            } else {
                indicator.style.display = 'block';
                console.log('Offline');
                
                // Load cached data
                loadCachedStats();
            }
        }

        window.addEventListener('online', updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);
        updateOnlineStatus();

        // ============================================
        // DRIVER STATS UPDATES
        // ============================================
        function updateDriverStats() {
            if (!navigator.onLine) {
                loadCachedStats();
                return;
            }
            
            fetch('../api/get_driver_stats.php')
                .then(response => response.json())
                .then(data => {
                    // Update queue position
                    if (data.queue_position) {
                        document.getElementById('positionNumber').textContent = '#' + data.queue_position;
                        document.getElementById('queueStatus').textContent = data.queue_status || 'Waiting';
                        
                        // Change color if loading
                        if (data.queue_status === 'loading') {
                            document.getElementById('queuePosition').style.background = '#ff9800';
                        } else {
                            document.getElementById('queuePosition').style.background = '#ffc107';
                        }
                        
                        // Save to cache
                        localStorage.setItem('queuePosition', data.queue_position);
                        localStorage.setItem('queueStatus', data.queue_status);
                    } else {
                        document.getElementById('positionNumber').textContent = '-';
                        document.getElementById('queueStatus').textContent = 'Not in queue';
                        localStorage.removeItem('queuePosition');
                    }
                    
                    // Update today's stats
                    document.getElementById('todayTrips').textContent = data.today_trips || 0;
                    document.getElementById('todayEarnings').textContent = (data.today_earnings || 0).toFixed(2);
                    
                    // Save to cache
                    localStorage.setItem('todayTrips', data.today_trips || 0);
                    localStorage.setItem('todayEarnings', (data.today_earnings || 0).toFixed(2));
                    
                    // Update last updated time
                    const now = new Date();
                    document.getElementById('lastUpdated').textContent = now.toLocaleTimeString();
                })
                .catch(error => {
                    console.log('Fetch error, using cached data');
                    loadCachedStats();
                });
        }
        
        function loadCachedStats() {
            const queuePos = localStorage.getItem('queuePosition');
            const queueStat = localStorage.getItem('queueStatus');
            const todayTrips = localStorage.getItem('todayTrips');
            const todayEarnings = localStorage.getItem('todayEarnings');
            
            if (queuePos) {
                document.getElementById('positionNumber').textContent = '#' + queuePos;
                document.getElementById('queueStatus').textContent = queueStat || 'Waiting';
            }
            
            document.getElementById('todayTrips').textContent = todayTrips || '0';
            document.getElementById('todayEarnings').textContent = todayEarnings || '0.00';
            document.getElementById('lastUpdated').textContent = 'Offline mode';
        }

        // ============================================
        // SHARE APP
        // ============================================
        function shareApp() {
            if (navigator.share) {
                navigator.share({
                    title: 'Smart Taxi Driver',
                    text: 'Join the Smart Taxi Rank system',
                    url: window.location.origin + '/driver/portal.php'
                }).catch(console.error);
            } else {
                // Fallback
                prompt('Copy this link to share:', window.location.origin + '/driver/portal.php');
            }
        }

        // ============================================
        // UPDATE NOTIFICATION
        // ============================================
        function showUpdateNotification() {
            const notification = document.createElement('div');
            notification.className = 'update-notification';
            notification.innerHTML = `
                <strong>New version available!</strong>
                <button onclick="window.location.reload()" class="btn btn-sm btn-light ms-3">
                    Refresh
                </button>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => notification.remove(), 10000);
        }

        // ============================================
        // CHECK FOR UPDATES
        // ============================================
        function checkForUpdates() {
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.ready.then(reg => {
                    reg.update();
                    alert('Checking for updates...');
                });
            }
        }

        // ============================================
        // CLEAR CACHE
        // ============================================
        function clearCache() {
            if (confirm('Clear cached data? This may log you out.')) {
                localStorage.clear();
                sessionStorage.clear();
                
                if ('serviceWorker' in navigator) {
                    caches.keys().then(names => {
                        names.forEach(name => caches.delete(name));
                    });
                }
                
                window.location.reload();
            }
        }

        // ============================================
        // PUSH NOTIFICATIONS PERMISSION
        // ============================================
        function requestNotificationPermission() {
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission().then(permission => {
                    if (permission === 'granted') {
                        console.log('Notification permission granted');
                        
                        // Subscribe to push
                        subscribeToPush();
                    }
                });
            }
        }

        async function subscribeToPush() {
            try {
                const registration = await navigator.serviceWorker.ready;
                
                // Get VAPID public key from server
                const response = await fetch('../api/get_vapid_key.php');
                const { publicKey } = await response.json();
                
                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: publicKey
                });
                
                // Send to server
                await fetch('../api/save_push_subscription.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        driver_id: <?= $driver_id ?>,
                        subscription: subscription
                    })
                });
                
            } catch (error) {
                console.log('Push subscription failed:', error);
            }
        }

        // ============================================
        // INITIALIZE
        // ============================================
        // Request notification permission after 3 seconds
        setTimeout(requestNotificationPermission, 3000);
        
        // Update stats immediately and every 15 seconds
        updateDriverStats();
        setInterval(updateDriverStats, 15000);
    </script>
</body>
</html>