<?php
// admin/fare_history.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('admin');

$page_title = 'Fare Change History';
ob_start();

// Get fare change history
$history = $pdo->query("
    SELECT f.*, r.route_name 
    FROM fare_change_log f
    JOIN routes r ON f.route_id = r.id
    ORDER BY f.changed_at DESC
    LIMIT 50
")->fetchAll();
?>

<div class="container-fluid">
    <h2 class="mb-4"><i class="bi bi-clock-history"></i> Fare Change History</h2>
    
    <div class="card">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Recent Fare Changes</h5>
        </div>
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Route</th>
                        <th>Old Fare</th>
                        <th>New Fare</th>
                        <th>Change</th>
                        <th>Changed By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                <h5>No fare changes recorded</h5>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($history as $change): 
                            $difference = $change['new_fare'] - $change['old_fare'];
                            $percent = ($difference / $change['old_fare']) * 100;
                        ?>
                        <tr>
                            <td><?= date('d M Y H:i', strtotime($change['changed_at'])) ?></td>
                            <td><strong><?= htmlspecialchars($change['route_name']) ?></strong></td>
                            <td>R <?= number_format($change['old_fare'], 2) ?></td>
                            <td>R <?= number_format($change['new_fare'], 2) ?></td>
                            <td>
                                <span class="badge bg-<?= $difference > 0 ? 'success' : 'danger' ?>">
                                    <?= $difference > 0 ? '+' : '' ?><?= number_format($difference, 2) ?> 
                                    (<?= number_format($percent, 1) ?>%)
                                </span>
                            </td>
                            <td><?= htmlspecialchars($change['changed_by']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../layouts/admin_layout.php';
?>