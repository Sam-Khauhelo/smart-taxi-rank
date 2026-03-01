<?php
// admin/notifications.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('admin');

$page_title = 'All Notifications';
ob_start();

// Handle mark as read
if (isset($_POST['mark_read'])) {
    $id = $_POST['notification_id'];
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?")->execute([$id]);
    echo "<script>window.location.href = 'notifications.php';</script>";
    exit();
}

// Handle mark all as read
if (isset($_POST['mark_all_read'])) {
    $pdo->query("UPDATE notifications SET is_read = 1 WHERE user_id IS NULL OR user_id IN (SELECT id FROM users)");
    echo "<script>window.location.href = 'notifications.php';</script>";
    exit();
}

// Handle delete notification
if (isset($_POST['delete'])) {
    $id = $_POST['notification_id'];
    $pdo->prepare("DELETE FROM notifications WHERE id = ?")->execute([$id]);
    echo "<script>window.location.href = 'notifications.php';</script>";
    exit();
}

try {
    // Get fare change notifications
    $fare_changes = $pdo->query("
        SELECT 'fare_change' as type, f.*, r.route_name,
               f.changed_at as time,
               CONCAT('Fare changed for ', r.route_name, ': R', f.old_fare, ' → R', f.new_fare) as message,
               'tag' as icon, 'warning' as color,
               f.id as notification_id
        FROM fare_change_log f
        JOIN routes r ON f.route_id = r.id
        WHERE f.changed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY f.changed_at DESC
    ")->fetchAll();
    
    // Get all registrations (last 7 days)
    $registrations = $pdo->query("
        SELECT 'registration' as type, id as notification_id, full_name, role, created_at as time,
               CONCAT('New ', role, ' registered: ', full_name) as message,
               'person-plus' as icon, 'success' as color,
               0 as is_read
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ")->fetchAll();
    
    // Get all trips (last 7 days)
    $trips = $pdo->query("
        SELECT 'trip' as type, t.id as notification_id, tx.registration_number, r.route_name, 
               t.departed_at as time,
               CONCAT('Trip completed: ', tx.registration_number, ' - ', r.route_name) as message,
               'taxi' as icon, 'primary' as color,
               0 as is_read
        FROM trips t
        JOIN taxis tx ON t.taxi_id = tx.id
        JOIN routes r ON t.route_id = r.id
        WHERE t.departed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ")->fetchAll();
    
    // Get failed attempts (last 7 days)
    $failed = $pdo->query("
        SELECT 'failed' as type, id as notification_id, full_name, username, attempt_time as time,
               CONCAT('⚠️ Failed registration attempt', IF(full_name IS NOT NULL, CONCAT(' for ', full_name), '')) as message,
               'shield-exclamation' as icon, 'danger' as color,
               0 as is_read
        FROM failed_registration_attempts 
        WHERE attempt_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ")->fetchAll();
    
    // Get system notifications from notifications table
    $system_notifications = $pdo->query("
        SELECT 'system' as type, id as notification_id, message, created_at as time,
               'bell' as icon, 
               CASE 
                   WHEN type = 'fare_change' THEN 'warning'
                   WHEN type = 'new_user' THEN 'success'
                   WHEN type = 'trip' THEN 'primary'
                   ELSE 'info'
               END as color,
               is_read
        FROM notifications 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ")->fetchAll();
    
    // Combine all notifications
    $all_notifications = array_merge(
        $registrations, 
        $trips, 
        $failed, 
        $fare_changes,
        $system_notifications
    );
    
    // Sort by time (newest first)
    usort($all_notifications, function($a, $b) {
        return strtotime($b['time']) - strtotime($a['time']);
    });
    
    // Get unread count
    $unread_count = count(array_filter($all_notifications, function($n) {
        return !isset($n['is_read']) || $n['is_read'] == 0;
    }));
    
} catch (PDOException $e) {
    error_log("Notifications error: " . $e->getMessage());
    $all_notifications = [];
    $unread_count = 0;
}
?>

<style>
.notification-item {
    transition: background-color 0.3s;
    border-left: 4px solid transparent;
    position: relative;
}
.notification-item.unread {
    background-color: #f0f7ff;
    border-left-color: #007bff;
}
.notification-item:hover {
    background-color: #f8f9fa;
}
.notification-actions {
    opacity: 0;
    transition: opacity 0.3s;
}
.notification-item:hover .notification-actions {
    opacity: 1;
}
.notification-badge {
    position: absolute;
    top: 10px;
    right: 10px;
}
.time-badge {
    font-size: 0.75rem;
    background: #e9ecef;
    padding: 2px 8px;
    border-radius: 12px;
}
.notification-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}
</style>

<div class="container-fluid">
    <!-- Header with actions -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>
            <i class="bi bi-bell"></i> All Notifications
            <?php if ($unread_count > 0): ?>
                <span class="badge bg-danger ms-2"><?= $unread_count ?> unread</span>
            <?php endif; ?>
        </h2>
        <div>
            <?php if ($unread_count > 0): ?>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="mark_all_read" class="btn btn-primary" 
                            onclick="return confirm('Mark all notifications as read?')">
                        <i class="bi bi-check-all"></i> Mark All Read
                    </button>
                </form>
            <?php endif; ?>
            <button class="btn btn-success" onclick="exportNotifications()">
                <i class="bi bi-download"></i> Export
            </button>
            <button class="btn btn-secondary" onclick="window.location.reload()">
                <i class="bi bi-arrow-repeat"></i> Refresh
            </button>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Total Notifications</h6>
                    <h3><?= count($all_notifications) ?></h3>
                    <small>Last 7 days</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Registrations</h6>
                    <h3><?= count($registrations) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Trips</h6>
                    <h3><?= count($trips) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Failed Attempts</h6>
                    <h3><?= count($failed) ?></h3>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter Tabs -->
    <ul class="nav nav-tabs mb-3" id="notificationTabs">
        <li class="nav-item">
            <a class="nav-link active" data-filter="all" href="#">All</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-filter="unread" href="#">Unread</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-filter="registration" href="#">Registrations</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-filter="trip" href="#">Trips</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-filter="fare_change" href="#">Fare Changes</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-filter="failed" href="#">Failed Attempts</a>
        </li>
    </ul>
    
    <!-- Notifications List -->
    <div class="card">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Notification History</h5>
            <div>
                <input type="text" class="form-control form-control-sm" placeholder="Search notifications..." 
                       id="searchInput" style="width: 250px;">
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($all_notifications)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-check-circle fs-1 text-success d-block mb-3"></i>
                    <h5>No notifications</h5>
                    <p class="text-muted">There are no notifications for the last 7 days.</p>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush" id="notificationList">
                    <?php foreach ($all_notifications as $index => $note): 
                        $is_unread = !isset($note['is_read']) || $note['is_read'] == 0;
                        $notification_id = $note['notification_id'] ?? $index;
                    ?>
                        <div class="list-group-item list-group-item-action notification-item <?= $is_unread ? 'unread' : '' ?>" 
                             data-type="<?= $note['type'] ?>"
                             data-message="<?= strtolower(htmlspecialchars($note['message'])) ?>">
                            
                            <div class="d-flex align-items-start">
                                <!-- Icon -->
                                <div class="me-3">
                                    <div class="notification-icon bg-<?= $note['color'] ?> text-white">
                                        <i class="bi bi-<?= $note['icon'] ?>"></i>
                                    </div>
                                </div>
                                
                                <!-- Content -->
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?= htmlspecialchars($note['message']) ?></h6>
                                            <div class="d-flex gap-2 align-items-center">
                                                <small class="text-muted">
                                                    <i class="bi bi-calendar"></i> 
                                                    <?= date('d M Y H:i', strtotime($note['time'])) ?>
                                                </small>
                                                <span class="time-badge">
                                                    <?php 
                                                    $time = strtotime($note['time']);
                                                    $now = time();
                                                    $diff = $now - $time;
                                                    
                                                    if ($diff < 60) {
                                                        echo 'Just now';
                                                    } elseif ($diff < 3600) {
                                                        echo floor($diff / 60) . ' min ago';
                                                    } elseif ($diff < 86400) {
                                                        echo floor($diff / 3600) . ' hours ago';
                                                    } else {
                                                        echo floor($diff / 86400) . ' days ago';
                                                    }
                                                    ?>
                                                </span>
                                                <?php if ($note['type'] == 'fare_change'): ?>
                                                    <span class="badge bg-warning">Fare Change</span>
                                                <?php elseif ($note['type'] == 'failed'): ?>
                                                    <span class="badge bg-danger">Security Alert</span>
                                                <?php elseif ($note['type'] == 'registration'): ?>
                                                    <span class="badge bg-success">New User</span>
                                                <?php elseif ($note['type'] == 'trip'): ?>
                                                    <span class="badge bg-primary">Trip</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Actions -->
                                        <div class="notification-actions">
                                            <?php if ($is_unread): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="notification_id" value="<?= $notification_id ?>">
                                                    <button type="submit" name="mark_read" class="btn btn-sm btn-outline-primary" 
                                                            title="Mark as read">
                                                        <i class="bi bi-check-circle"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('Delete this notification?')">
                                                <input type="hidden" name="notification_id" value="<?= $notification_id ?>">
                                                <button type="submit" name="delete" class="btn btn-sm btn-outline-danger" 
                                                        title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($note['type'] == 'fare_change'): ?>
                                <div class="mt-2 ms-5 ps-2 border-start">
                                    <small class="text-muted">
                                                        <i class="bi bi-arrow-right"></i> 
                                        Changed by: <?= htmlspecialchars($note['changed_by'] ?? 'System') ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer text-muted">
            <i class="bi bi-info-circle"></i> Showing <?= count($all_notifications) ?> notifications
        </div>
    </div>
</div>

<script>
// Filter functionality
document.querySelectorAll('[data-filter]').forEach(tab => {
    tab.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Update active tab
        document.querySelectorAll('[data-filter]').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        
        const filter = this.dataset.filter;
        const items = document.querySelectorAll('.notification-item');
        
        items.forEach(item => {
            if (filter === 'all') {
                item.style.display = '';
            } else if (filter === 'unread') {
                item.style.display = item.classList.contains('unread') ? '' : 'none';
            } else {
                item.style.display = item.dataset.type === filter ? '' : 'none';
            }
        });
    });
});

// Search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    const search = this.value.toLowerCase();
    const items = document.querySelectorAll('.notification-item');
    
    items.forEach(item => {
        const message = item.dataset.message;
        if (message.includes(search)) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
});

// Export notifications
function exportNotifications() {
    // Create CSV content
    let csv = "Type,Message,Date,Time\n";
    
    <?php foreach ($all_notifications as $note): ?>
        csv += "<?= $note['type'] ?>,<?= str_replace(',', ' ', addslashes($note['message'])) ?>,<?= date('Y-m-d', strtotime($note['time'])) ?>,<?= date('H:i:s', strtotime($note['time'])) ?>\n";
    <?php endforeach; ?>
    
    // Download CSV
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'notifications_<?= date('Y-m-d') ?>.csv';
    a.click();
}

// Auto-refresh every 60 seconds
setTimeout(() => {
    window.location.reload();
}, 60000);
</script>

<?php
$content = ob_get_clean();
require_once '../layouts/admin_layout.php';
?>