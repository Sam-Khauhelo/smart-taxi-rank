<?php
// admin/manage_routes.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('admin');

// Set page title
$page_title = 'Manage Routes';

// Start output buffering
ob_start();

// Get all routes
try {
    $routes = $pdo->query("SELECT * FROM routes ORDER BY route_name")->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching routes: " . $e->getMessage());
    $routes = [];
}
?>

<!-- Add New Route Button -->
<div class="mb-3">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRouteModal">
        <i class="bi bi-plus-circle"></i> Add New Route
    </button>
</div>

<!-- Routes Table -->
<div class="card">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0"><i class="bi bi-signpost"></i> All Routes</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Route Name</th>
                        <th>Fare Amount</th>
                        <th>Association Levy</th>
                        <th>Duration (min)</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($routes)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                                No routes found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($routes as $route): ?>
                        <tr>
                            <td><?= $route['id'] ?></td>
                            <td><strong><?= htmlspecialchars($route['route_name']) ?></strong></td>
                            <td>R <?= number_format($route['fare_amount'], 2) ?></td>
                            <td>R <?= number_format($route['association_levy'] ?? 0, 2) ?></td>
                            <td><?= $route['estimated_duration_minutes'] ?? 'N/A' ?></td>
                            <td>
                                <?php if ($route['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="editRoute(<?= $route['id'] ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteRoute(<?= $route['id'] ?>)">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php if ($route['is_active']): ?>
                                    <button class="btn btn-sm btn-secondary" onclick="toggleStatus(<?= $route['id'] ?>, 0)">
                                        <i class="bi bi-eye-slash"></i>
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-success" onclick="toggleStatus(<?= $route['id'] ?>, 1)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Route Modal -->
<div class="modal fade" id="addRouteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add New Route</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="process/process_route.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="route_name" class="form-label">Route Name *</label>
                        <input type="text" class="form-control" id="route_name" name="route_name" required 
                               placeholder="e.g., Durban - Umlazi">
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="fare_amount" class="form-label">Fare Amount (R) *</label>
                            <input type="number" step="0.01" class="form-control" id="fare_amount" name="fare_amount" required 
                                   placeholder="25.00">
                        </div>
                        <div class="col-md-6">
                            <label for="association_levy" class="form-label">Association Levy (R)</label>
                            <input type="number" step="0.01" class="form-control" id="association_levy" name="association_levy" 
                                   placeholder="5.00" value="0.00">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="estimated_duration_minutes" class="form-label">Duration (minutes)</label>
                            <input type="number" class="form-control" id="estimated_duration_minutes" name="estimated_duration_minutes" 
                                   placeholder="45">
                        </div>
                        <div class="col-md-6">
                            <label for="is_active" class="form-label">Status</label>
                            <select class="form-select" id="is_active" name="is_active">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Route</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Route Modal -->
<div class="modal fade" id="editRouteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Route</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="process/process_route.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="route_id" id="edit_route_id">
                    
                    <div class="mb-3">
                        <label for="edit_route_name" class="form-label">Route Name *</label>
                        <input type="text" class="form-control" id="edit_route_name" name="route_name" required>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_fare_amount" class="form-label">Fare Amount (R) *</label>
                            <input type="number" step="0.01" class="form-control" id="edit_fare_amount" name="fare_amount" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_association_levy" class="form-label">Association Levy (R)</label>
                            <input type="number" step="0.01" class="form-control" id="edit_association_levy" name="association_levy">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_duration" class="form-label">Duration (minutes)</label>
                            <input type="number" class="form-control" id="edit_duration" name="estimated_duration_minutes">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_is_active" class="form-label">Status</label>
                            <select class="form-select" id="edit_is_active" name="is_active">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update Route</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Edit route function
function editRoute(id) {
    // Fetch route details
    fetch(`get_route.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('edit_route_id').value = data.id;
            document.getElementById('edit_route_name').value = data.route_name;
            document.getElementById('edit_fare_amount').value = data.fare_amount;
            document.getElementById('edit_association_levy').value = data.association_levy || 0;
            document.getElementById('edit_duration').value = data.estimated_duration_minutes || '';
            document.getElementById('edit_is_active').value = data.is_active;
            
            new bootstrap.Modal(document.getElementById('editRouteModal')).show();
        });
}

// Delete route function
function deleteRoute(id) {
    if (confirm('Are you sure you want to delete this route? This action cannot be undone.')) {
        window.location.href = `process/process_route.php?action=delete&id=${id}`;
    }
}

// Toggle status function
function toggleStatus(id, status) {
    const action = status ? 'activate' : 'deactivate';
    if (confirm(`Are you sure you want to ${action} this route?`)) {
        window.location.href = `process/process_route.php?action=toggle&id=${id}&status=${status}`;
    }
}
</script>

<?php
// Get the content
$content = ob_get_clean();

// Include the admin layout
require_once '../layouts/admin_layout.php';
?>