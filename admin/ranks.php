<?php
// admin/ranks.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('admin');

$page_title = 'Rank Management';
ob_start();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_rank'])) {
        $rank_name = $_POST['rank_name'];
        $location = $_POST['location'];
        $capacity = $_POST['capacity'];
        $contact = $_POST['contact_number'];
        
        $sql = "INSERT INTO ranks (rank_name, location, capacity, contact_number) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$rank_name, $location, $capacity, $contact]);
        
        echo "<script>alert('✅ Rank added successfully!');</script>";
    }
}

// Get all ranks
$ranks = $pdo->query("SELECT * FROM ranks ORDER BY rank_name")->fetchAll();
?>

<div class="container-fluid">
    <h2 class="mb-4"><i class="bi bi-pin-map"></i> Rank Management</h2>
    
    <!-- Add Rank Button -->
    <button class="btn btn-primary mb-4" data-bs-toggle="modal" data-bs-target="#addRankModal">
        <i class="bi bi-plus-circle"></i> Add New Rank
    </button>
    
    <!-- Ranks Overview -->
    <div class="row">
        <?php foreach ($ranks as $rank): ?>
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><?= htmlspecialchars($rank['rank_name']) ?></h5>
                </div>
                <div class="card-body">
                    <p><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($rank['location']) ?></p>
                    <p><i class="bi bi-people"></i> Capacity: <?= $rank['capacity'] ?> taxis</p>
                    <p><i class="bi bi-telephone"></i> <?= htmlspecialchars($rank['contact_number']) ?></p>
                    
                    <?php
                    // Get current queue status
                    $queue = $pdo->prepare("SELECT COUNT(*) as waiting FROM queue WHERE status='waiting'");
                    $queue->execute();
                    $waiting = $queue->fetch()['waiting'];
                    
                    // Get today's marshals
                    $marshals = $pdo->prepare("
                        SELECT COUNT(*) as count FROM marshal_shifts 
                        WHERE rank_id = ? AND shift_date = CURDATE()
                    ");
                    $marshals->execute([$rank['id']]);
                    $marshal_count = $marshals->fetch()['count'];
                    ?>
                    
                    <div class="mt-3">
                        <div class="d-flex justify-content-between">
                            <span>Current Queue:</span>
                            <span class="badge bg-info"><?= $waiting ?> taxis</span>
                        </div>
                        <div class="d-flex justify-content-between mt-2">
                            <span>Marshals Today:</span>
                            <span class="badge bg-success"><?= $marshal_count ?> on duty</span>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <a href="rank_details.php?id=<?= $rank['id'] ?>" class="btn btn-sm btn-primary">
                            <i class="bi bi-eye"></i> View Details
                        </a>
                        <button class="btn btn-sm btn-warning" onclick="editRank(<?= $rank['id'] ?>)">
                            <i class="bi bi-pencil"></i> Edit
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Marshal Shifts Today -->
    <div class="card mt-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="bi bi-clock"></i> Today's Marshal Shifts</h5>
        </div>
        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>Marshal</th>
                        <th>Rank</th>
                        <th>Shift Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $shifts = $pdo->query("
                        SELECT ms.*, u.full_name, r.rank_name 
                        FROM marshal_shifts ms
                        JOIN marshals m ON ms.marshal_id = m.id
                        JOIN users u ON m.user_id = u.id
                        JOIN ranks r ON ms.rank_id = r.id
                        WHERE ms.shift_date = CURDATE()
                        ORDER BY ms.shift_start
                    ")->fetchAll();
                    
                    foreach ($shifts as $shift):
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($shift['full_name']) ?></td>
                        <td><?= htmlspecialchars($shift['rank_name']) ?></td>
                        <td><?= $shift['shift_start'] ?> - <?= $shift['shift_end'] ?></td>
                        <td>
                            <span class="badge bg-<?= $shift['status'] == 'active' ? 'success' : 
                                ($shift['status'] == 'completed' ? 'secondary' : 'warning') ?>">
                                <?= ucfirst($shift['status']) ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-success" onclick="markActive(<?= $shift['id'] ?>)">
                                <i class="bi bi-play"></i>
                            </button>
                            <button class="btn btn-sm btn-secondary" onclick="markComplete(<?= $shift['id'] ?>)">
                                <i class="bi bi-check"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Rank Modal -->
<div class="modal fade" id="addRankModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Add New Rank</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Rank Name</label>
                        <input type="text" name="rank_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Location</label>
                        <input type="text" name="location" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Capacity (taxis)</label>
                        <input type="number" name="capacity" class="form-control" value="50" required>
                    </div>
                    <div class="mb-3">
                        <label>Contact Number</label>
                        <input type="text" name="contact_number" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_rank" class="btn btn-primary">Add Rank</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editRank(id) {
    // Load rank data and show edit modal
    fetch(`get_rank.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            // Populate and show edit modal
            alert('Edit functionality coming soon');
        });
}

function markActive(id) {
    if (confirm('Mark this shift as active?')) {
        window.location.href = `update_shift.php?id=${id}&status=active`;
    }
}

function markComplete(id) {
    if (confirm('Mark this shift as completed?')) {
        window.location.href = `update_shift.php?id=${id}&status=completed`;
    }
}
</script>

<?php
$content = ob_get_clean();
require_once '../layouts/admin_layout.php';
?>