<?php
// admin/failed_attempts.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('admin');

$page_title = 'Failed Registration Attempts';
ob_start();

// Get all failed attempts
$sql = "SELECT * FROM failed_registration_attempts ORDER BY attempt_time DESC LIMIT 100";
$attempts = $pdo->query($sql)->fetchAll();
?>

<div class="container-fluid">
    <h2 class="mb-4"><i class="bi bi-shield-exclamation"></i> Failed Registration Attempts</h2>
    
    <div class="card">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Last 100 Failed Attempts</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Key Entered</th>
                            <th>IP Address</th>
                            <th>User Agent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($attempts)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="bi bi-check-circle text-success fs-1 d-block mb-3"></i>
                                    <h5>No failed attempts recorded</h5>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($attempts as $attempt): ?>
                            <tr>
                                <td><?= date('Y-m-d H:i:s', strtotime($attempt['attempt_time'])) ?></td>
                                <td><?= htmlspecialchars($attempt['full_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($attempt['phone_number'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($attempt['username'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($attempt['role'] ?? 'N/A') ?></span>
                                </td>
                                <td>
                                    <code><?= htmlspecialchars($attempt['company_key_entered'] ?? 'N/A') ?></code>
                                </td>
                                <td><?= htmlspecialchars($attempt['ip_address'] ?? 'N/A') ?></td>
                                <td>
                                    <small class="text-muted" title="<?= htmlspecialchars($attempt['user_agent'] ?? '') ?>">
                                        <?= substr(htmlspecialchars($attempt['user_agent'] ?? ''), 0, 50) ?>...
                                    </small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../layouts/admin_layout.php';
?>