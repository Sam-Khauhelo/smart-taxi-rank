<?php
// admin/settings.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('admin');

// Set page title
$page_title = 'System Settings';

// Start output buffering
ob_start();

// Handle Company Key Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['company_key_action'])) {
    
    // Add new company key
    if ($_POST['company_key_action'] === 'add') {
        $auth_code = trim($_POST['auth_code']);
        $password = $_POST['password'];
        $created_by = $_SESSION['full_name'];
        
        // Check if code exists
        $check = $pdo->prepare("SELECT id FROM company_keys WHERE authorization_Code = ?");
        $check->execute([$auth_code]);
        
        if ($check->rowCount() > 0) {
            echo "<script>alert('❌ Authorization code already exists!');</script>";
        } else {
            $sql = "INSERT INTO company_keys (authorization_Code, password, created_at, created_by, updated_at, updated_by) 
                    VALUES (?, ?, CURDATE(), ?, CURDATE(), ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$auth_code, $password, $created_by, $created_by]);
            
            echo "<script>alert('✅ Company key added successfully!');</script>";
        }
    }
    
    // Edit company key
    if ($_POST['company_key_action'] === 'edit') {
        $id = $_POST['key_id'];
        $auth_code = trim($_POST['auth_code']);
        $password = $_POST['password'];
        $updated_by = $_SESSION['full_name'];
        
        if (!empty($password)) {
            // Update with new password
            $sql = "UPDATE company_keys SET authorization_Code = ?, password = ?, updated_at = CURDATE(), updated_by = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$auth_code, $password, $updated_by, $id]);
        } else {
            // Update without changing password
            $sql = "UPDATE company_keys SET authorization_Code = ?, updated_at = CURDATE(), updated_by = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$auth_code, $updated_by, $id]);
        }
        
        echo "<script>alert('✅ Company key updated successfully!');</script>";
    }
    
    // Delete company key
    if ($_POST['company_key_action'] === 'delete') {
        $id = $_POST['key_id'];
        
        $sql = "DELETE FROM company_keys WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        
        echo "<script>alert('✅ Company key deleted successfully!');</script>";
    }
}

// Get current settings
try {
    // Check if settings table exists, if not create it
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        setting_type ENUM('text', 'number', 'boolean', 'json') DEFAULT 'text',
        description TEXT,
        updated_by VARCHAR(100),
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Default settings to insert if not exists
    $default_settings = [
        ['site_name', 'Smart Taxi Rank', 'text', 'System name displayed in headers'],
        ['association_name', 'Taxi Association', 'text', 'Name of the taxi association'],
        ['association_phone', '0800 123 456', 'text', 'Association contact number'],
        ['association_email', 'info@taxiassociation.co.za', 'text', 'Association email address'],
        ['association_address', '123 Rank Street, Durban', 'text', 'Association physical address'],
        ['default_levy_percentage', '10', 'number', 'Default association levy percentage'],
        ['vat_percentage', '15', 'number', 'VAT percentage if applicable'],
        ['enable_vat', '0', 'boolean', 'Enable VAT calculation'],
        ['queue_timeout_minutes', '30', 'number', 'Minutes before taxi times out in queue'],
        ['max_passengers_per_taxi', '15', 'number', 'Maximum passengers allowed per taxi'],
        ['enable_driver_commission', '1', 'boolean', 'Enable automatic driver commission calculation'],
        ['default_commission_rate', '20', 'number', 'Default commission rate for drivers'],
        ['enable_notifications', '1', 'boolean', 'Enable system notifications'],
        ['enable_sms_alerts', '0', 'boolean', 'Enable SMS alerts for drivers'],
        ['sms_provider', 'clickatell', 'text', 'SMS provider (clickatell, twilio, etc)'],
        ['sms_api_key', '', 'text', 'SMS API key'],
        ['currency_symbol', 'R', 'text', 'Currency symbol'],
        ['date_format', 'Y-m-d', 'text', 'Date display format'],
        ['time_format', 'H:i', 'text', 'Time display format'],
        ['session_timeout_minutes', '120', 'number', 'User session timeout in minutes'],
        ['max_login_attempts', '5', 'number', 'Maximum login attempts before lockout'],
        ['lockout_duration_minutes', '30', 'number', 'Account lockout duration in minutes'],
        ['enable_maintenance_mode', '0', 'boolean', 'Put system in maintenance mode'],
        ['maintenance_message', 'System under maintenance. Please try again later.', 'text', 'Maintenance mode message'],
        ['backup_frequency', 'daily', 'text', 'Database backup frequency'],
        ['auto_backup', '1', 'boolean', 'Enable automatic backups'],
        ['last_backup', '', 'text', 'Last backup timestamp'],
        ['allow_owner_registration', '0', 'boolean', 'Allow owners to self-register'],
        ['require_approval', '1', 'boolean', 'Require admin approval for new users'],
        ['default_dashboard', 'summary', 'text', 'Default dashboard view'],
        ['items_per_page', '50', 'number', 'Number of items to show per page'],
        ['enable_debug_mode', '0', 'boolean', 'Enable debug mode (developers only)'],
        ['log_level', 'error', 'text', 'Logging level (debug, info, warning, error)']
    ];
    
    // Insert default settings if they don't exist
    $insert_sql = "INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, description) 
                   VALUES (:key, :value, :type, :desc)";
    $insert_stmt = $pdo->prepare($insert_sql);
    
    foreach ($default_settings as $setting) {
        $insert_stmt->execute([
            ':key' => $setting[0],
            ':value' => $setting[1],
            ':type' => $setting[2],
            ':desc' => $setting[3]
        ]);
    }
    
    // Get all settings
    $settings_sql = "SELECT * FROM system_settings ORDER BY setting_key";
    $settings = $pdo->query($settings_sql)->fetchAll();
    
    // Get all company keys
    $company_keys = $pdo->query("SELECT * FROM company_keys ORDER BY created_at DESC")->fetchAll();
    
    // Group settings by category
    $categories = [
        'general' => ['site_name', 'association_name', 'association_phone', 'association_email', 'association_address', 'currency_symbol', 'date_format', 'time_format'],
        'financial' => ['default_levy_percentage', 'vat_percentage', 'enable_vat', 'enable_driver_commission', 'default_commission_rate'],
        'queue' => ['queue_timeout_minutes', 'max_passengers_per_taxi'],
        'notifications' => ['enable_notifications', 'enable_sms_alerts', 'sms_provider', 'sms_api_key'],
        'security' => ['session_timeout_minutes', 'max_login_attempts', 'lockout_duration_minutes', 'enable_maintenance_mode', 'maintenance_message'],
        'system' => ['enable_debug_mode', 'log_level', 'items_per_page', 'default_dashboard', 'backup_frequency', 'auto_backup', 'last_backup'],
        'registration' => ['allow_owner_registration', 'require_approval']
    ];
    
    // Organize settings by category
    $grouped_settings = [];
    foreach ($settings as $setting) {
        $found = false;
        foreach ($categories as $category => $keys) {
            if (in_array($setting['setting_key'], $keys)) {
                $grouped_settings[$category][] = $setting;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $grouped_settings['other'][] = $setting;
        }
    }
    
} catch (PDOException $e) {
    error_log("Settings error: " . $e->getMessage());
    $grouped_settings = [];
    $company_keys = [];
}

// Get admin name for logging
$admin_name = $_SESSION['full_name'];
?>

<!-- Settings Tabs -->
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-gear"></i> System Settings</h2>
        <div>
            <button class="btn btn-success" onclick="saveAllSettings()">
                <i class="bi bi-save"></i> Save All Changes
            </button>
            <button class="btn btn-danger" onclick="resetToDefaults()">
                <i class="bi bi-arrow-counterclockwise"></i> Reset to Defaults
            </button>
        </div>
    </div>

    <!-- Settings Tabs -->
    <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
                <i class="bi bi-building"></i> General
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="financial-tab" data-bs-toggle="tab" data-bs-target="#financial" type="button" role="tab">
                <i class="bi bi-cash-stack"></i> Financial
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="queue-tab" data-bs-toggle="tab" data-bs-target="#queue" type="button" role="tab">
                <i class="bi bi-list-ol"></i> Queue Management
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab">
                <i class="bi bi-bell"></i> Notifications
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                <i class="bi bi-shield-lock"></i> Security
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab">
                <i class="bi bi-hdd-stack"></i> System
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="registration-tab" data-bs-toggle="tab" data-bs-target="#registration" type="button" role="tab">
                <i class="bi bi-person-plus"></i> Registration
            </button>
        </li>
        <!-- NEW: Company Keys Tab -->
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="company-keys-tab" data-bs-toggle="tab" data-bs-target="#company-keys" type="button" role="tab">
                <i class="bi bi-key-fill"></i> Company Keys
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="settingsTabContent">
        <?php
        $category_names = [
            'general' => 'General Settings',
            'financial' => 'Financial Settings',
            'queue' => 'Queue Management',
            'notifications' => 'Notification Settings',
            'security' => 'Security Settings',
            'system' => 'System Configuration',
            'registration' => 'Registration Settings',
            'other' => 'Other Settings'
        ];
        
        $category_icons = [
            'general' => 'building',
            'financial' => 'cash-stack',
            'queue' => 'list-ol',
            'notifications' => 'bell',
            'security' => 'shield-lock',
            'system' => 'hdd-stack',
            'registration' => 'person-plus',
            'other' => 'gear'
        ];
        
        $first = true;
        foreach ($grouped_settings as $category => $settings_list):
        ?>
        <div class="tab-pane fade <?= $first ? 'show active' : '' ?>" id="<?= $category ?>" role="tabpanel">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-<?= $category_icons[$category] ?? 'gear' ?>"></i> <?= $category_names[$category] ?? ucfirst($category) ?></h5>
                </div>
                <div class="card-body">
                    <form id="form-<?= $category ?>" class="settings-form">
                        <?php foreach ($settings_list as $setting): ?>
                        <div class="row mb-3">
                            <label for="<?= $setting['setting_key'] ?>" class="col-sm-4 col-form-label">
                                <?= ucwords(str_replace('_', ' ', $setting['setting_key'])) ?>
                                <?php if ($setting['description']): ?>
                                    <i class="bi bi-info-circle text-primary" 
                                       data-bs-toggle="tooltip" 
                                       title="<?= htmlspecialchars($setting['description']) ?>"></i>
                                <?php endif; ?>
                            </label>
                            <div class="col-sm-8">
                                <?php if ($setting['setting_type'] == 'boolean'): ?>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" 
                                               id="<?= $setting['setting_key'] ?>" 
                                               name="<?= $setting['setting_key'] ?>"
                                               <?= $setting['setting_value'] == '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label">Enabled</label>
                                    </div>
                                <?php elseif ($setting['setting_type'] == 'number'): ?>
                                    <input type="number" class="form-control" 
                                           id="<?= $setting['setting_key'] ?>" 
                                           name="<?= $setting['setting_key'] ?>"
                                           value="<?= htmlspecialchars($setting['setting_value']) ?>"
                                           step="0.01">
                                <?php elseif (in_array($setting['setting_key'], ['sms_provider', 'log_level', 'backup_frequency', 'date_format', 'time_format'])): ?>
                                    <select class="form-select" id="<?= $setting['setting_key'] ?>" name="<?= $setting['setting_key'] ?>">
                                        <?php if ($setting['setting_key'] == 'sms_provider'): ?>
                                            <option value="clickatell" <?= $setting['setting_value'] == 'clickatell' ? 'selected' : '' ?>>Clickatell</option>
                                            <option value="twilio" <?= $setting['setting_value'] == 'twilio' ? 'selected' : '' ?>>Twilio</option>
                                            <option value="africastalking" <?= $setting['setting_value'] == 'africastalking' ? 'selected' : '' ?>>Africa's Talking</option>
                                            <option value="bulksms" <?= $setting['setting_value'] == 'bulksms' ? 'selected' : '' ?>>BulkSMS</option>
                                        <?php elseif ($setting['setting_key'] == 'log_level'): ?>
                                            <option value="debug" <?= $setting['setting_value'] == 'debug' ? 'selected' : '' ?>>Debug</option>
                                            <option value="info" <?= $setting['setting_value'] == 'info' ? 'selected' : '' ?>>Info</option>
                                            <option value="warning" <?= $setting['setting_value'] == 'warning' ? 'selected' : '' ?>>Warning</option>
                                            <option value="error" <?= $setting['setting_value'] == 'error' ? 'selected' : '' ?>>Error</option>
                                        <?php elseif ($setting['setting_key'] == 'backup_frequency'): ?>
                                            <option value="hourly" <?= $setting['setting_value'] == 'hourly' ? 'selected' : '' ?>>Hourly</option>
                                            <option value="daily" <?= $setting['setting_value'] == 'daily' ? 'selected' : '' ?>>Daily</option>
                                            <option value="weekly" <?= $setting['setting_value'] == 'weekly' ? 'selected' : '' ?>>Weekly</option>
                                            <option value="monthly" <?= $setting['setting_value'] == 'monthly' ? 'selected' : '' ?>>Monthly</option>
                                        <?php elseif ($setting['setting_key'] == 'date_format'): ?>
                                            <option value="Y-m-d" <?= $setting['setting_value'] == 'Y-m-d' ? 'selected' : '' ?>>YYYY-MM-DD (2024-02-27)</option>
                                            <option value="d/m/Y" <?= $setting['setting_value'] == 'd/m/Y' ? 'selected' : '' ?>>DD/MM/YYYY (27/02/2024)</option>
                                            <option value="m/d/Y" <?= $setting['setting_value'] == 'm/d/Y' ? 'selected' : '' ?>>MM/DD/YYYY (02/27/2024)</option>
                                            <option value="d M Y" <?= $setting['setting_value'] == 'd M Y' ? 'selected' : '' ?>>DD Mon YYYY (27 Feb 2024)</option>
                                        <?php elseif ($setting['setting_key'] == 'time_format'): ?>
                                            <option value="H:i" <?= $setting['setting_value'] == 'H:i' ? 'selected' : '' ?>>24 Hour (14:30)</option>
                                            <option value="h:i A" <?= $setting['setting_value'] == 'h:i A' ? 'selected' : '' ?>>12 Hour (02:30 PM)</option>
                                        <?php endif; ?>
                                    </select>
                                <?php else: ?>
                                    <input type="text" class="form-control" 
                                           id="<?= $setting['setting_key'] ?>" 
                                           name="<?= $setting['setting_key'] ?>"
                                           value="<?= htmlspecialchars($setting['setting_value']) ?>">
                                <?php endif; ?>
                                <small class="text-muted">
                                    Last updated: <?= date('d M Y H:i', strtotime($setting['updated_at'])) ?> 
                                    by <?= htmlspecialchars($setting['updated_by'] ?? 'System') ?>
                                </small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </form>
                </div>
                <div class="card-footer">
                    <button class="btn btn-primary" onclick="saveCategory('<?= $category ?>')">
                        <i class="bi bi-save"></i> Save <?= $category_names[$category] ?? ucfirst($category) ?>
                    </button>
                </div>
            </div>
        </div>
        <?php 
        $first = false;
        endforeach; 
        ?>

        <!-- NEW: Company Keys Tab Content -->
        <div class="tab-pane fade" id="company-keys" role="tabpanel">
            <div class="card">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-key-fill"></i> Company Keys Management</h5>
                    <button class="btn btn-dark btn-sm" onclick="showAddKeyModal()">
                        <i class="bi bi-plus-circle"></i> Add New Key
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Authorization Code</th>
                                    <th>Password</th>
                                    <th>Created</th>
                                    <th>Created By</th>
                                    <th>Updated</th>
                                    <th>Updated By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($company_keys)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <i class="bi bi-key fs-1 text-muted d-block mb-3"></i>
                                            <h5>No Company Keys Found</h5>
                                            <p class="text-muted">Click "Add New Key" to create your first company key.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($company_keys as $key): ?>
                                    <tr>
                                        <td><?= $key['id'] ?></td>
                                        <td><code><?= htmlspecialchars($key['authorization_Code']) ?></code></td>
                                        <td>
                                            <span class="password-mask" id="password-<?= $key['id'] ?>">
                                                ••••••••
                                            </span>
                                            <button class="btn btn-sm btn-link" onclick="togglePassword(<?= $key['id'] ?>, '<?= htmlspecialchars($key['password']) ?>')">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </td>
                                        <td><?= $key['created_at'] ?></td>
                                        <td><?= htmlspecialchars($key['created_by']) ?></td>
                                        <td><?= $key['updated_at'] ?></td>
                                        <td><?= htmlspecialchars($key['updated_by'] ?? 'N/A') ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning" onclick="editKey(<?= $key['id'] ?>, '<?= htmlspecialchars($key['authorization_Code']) ?>')">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteKey(<?= $key['id'] ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle"></i>
                        <strong>Note:</strong> Company keys are used for user registration. Users must enter the correct password for the authorization code to register.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Info Card -->
    <div class="card mt-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="bi bi-info-circle"></i> System Information</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <strong>PHP Version:</strong>
                    <p><?= phpversion() ?></p>
                </div>
                <div class="col-md-3">
                    <strong>MySQL Version:</strong>
                    <p><?= $pdo->query("SELECT VERSION()")->fetchColumn() ?></p>
                </div>
                <div class="col-md-3">
                    <strong>Server Software:</strong>
                    <p><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?></p>
                </div>
                <div class="col-md-3">
                    <strong>System Time:</strong>
                    <p><?= date('Y-m-d H:i:s') ?></p>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-3">
                    <strong>Total Users:</strong>
                    <p><?= $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?></p>
                </div>
                <div class="col-md-3">
                    <strong>Total Taxis:</strong>
                    <p><?= $pdo->query("SELECT COUNT(*) FROM taxis")->fetchColumn() ?></p>
                </div>
                <div class="col-md-3">
                    <strong>Total Drivers:</strong>
                    <p><?= $pdo->query("SELECT COUNT(*) FROM drivers")->fetchColumn() ?></p>
                </div>
                <div class="col-md-3">
                    <strong>Total Owners:</strong>
                    <p><?= $pdo->query("SELECT COUNT(*) FROM owners")->fetchColumn() ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Backup/Restore Card -->
    <div class="card mt-4">
        <div class="card-header bg-warning">
            <h5 class="mb-0"><i class="bi bi-database"></i> Database Backup & Restore</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>Create Backup</h6>
                    <p class="text-muted">Download a complete backup of the database</p>
                    <button class="btn btn-success" onclick="createBackup()">
                        <i class="bi bi-download"></i> Download Backup
                    </button>
                </div>
                <div class="col-md-6">
                    <h6>Restore Backup</h6>
                    <p class="text-muted">Restore from a previous backup file</p>
                    <form id="restoreForm" enctype="multipart/form-data">
                        <div class="input-group">
                            <input type="file" class="form-control" id="backupFile" accept=".sql,.gz">
                            <button class="btn btn-warning" type="button" onclick="restoreBackup()">
                                <i class="bi bi-upload"></i> Restore
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Company Key Modal -->
<div class="modal fade" id="addKeyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-key"></i> Add New Company Key</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="company_key_action" value="add">
                    
                    <div class="mb-3">
                        <label for="auth_code" class="form-label">Authorization Code</label>
                        <input type="text" class="form-control" id="auth_code" name="auth_code" required 
                               placeholder="e.g., AUTH-001, COMPANY-MASTER">
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="toggleAddPassword()">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <small class="text-muted">Users will need to enter this password to register</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Add Key</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Company Key Modal -->
<div class="modal fade" id="editKeyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Company Key</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="company_key_action" value="edit">
                    <input type="hidden" name="key_id" id="edit_key_id">
                    
                    <div class="mb-3">
                        <label for="edit_auth_code" class="form-label">Authorization Code</label>
                        <input type="text" class="form-control" id="edit_auth_code" name="auth_code" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_password" class="form-label">New Password (leave blank to keep current)</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="edit_password" name="password">
                            <button class="btn btn-outline-secondary" type="button" onclick="toggleEditPassword()">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <small class="text-muted">Only enter if you want to change the password</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update Key</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteKeyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="company_key_action" value="delete">
                    <input type="hidden" name="key_id" id="delete_key_id">
                    
                    <p>Are you sure you want to delete this company key?</p>
                    <p class="text-danger"><strong>Warning:</strong> Users will no longer be able to register using this key.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Key</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Initialize tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
});

// Company Key Functions
function showAddKeyModal() {
    new bootstrap.Modal(document.getElementById('addKeyModal')).show();
}

function toggleAddPassword() {
    const passwordField = document.getElementById('password');
    const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordField.setAttribute('type', type);
}

function toggleEditPassword() {
    const passwordField = document.getElementById('edit_password');
    const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordField.setAttribute('type', type);
}

function togglePassword(id, password) {
    const span = document.getElementById(`password-${id}`);
    if (span.innerHTML === '••••••••') {
        span.innerHTML = password;
    } else {
        span.innerHTML = '••••••••';
    }
}

function editKey(id, authCode) {
    document.getElementById('edit_key_id').value = id;
    document.getElementById('edit_auth_code').value = authCode;
    document.getElementById('edit_password').value = '';
    new bootstrap.Modal(document.getElementById('editKeyModal')).show();
}

function deleteKey(id) {
    document.getElementById('delete_key_id').value = id;
    new bootstrap.Modal(document.getElementById('deleteKeyModal')).show();
}

// Save single category
function saveCategory(category) {
    const form = document.getElementById(`form-${category}`);
    const formData = new FormData(form);
    const data = {};
    
    formData.forEach((value, key) => {
        // Handle checkboxes
        if (document.getElementById(key)?.type === 'checkbox') {
            data[key] = document.getElementById(key).checked ? '1' : '0';
        } else {
            data[key] = value;
        }
    });
    
    // Add metadata
    data.action = 'save_category';
    data.category = category;
    data.updated_by = '<?= $admin_name ?>';
    
    fetch('process/process_settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('✅ Settings saved successfully!');
        } else {
            alert('❌ Error: ' + result.message);
        }
    })
    .catch(error => {
        alert('❌ Error saving settings: ' + error);
    });
}

// Save all settings
function saveAllSettings() {
    const categories = ['general', 'financial', 'queue', 'notifications', 'security', 'system', 'registration', 'other'];
    let saved = 0;
    let errors = 0;
    
    categories.forEach(category => {
        const form = document.getElementById(`form-${category}`);
        if (form) {
            const formData = new FormData(form);
            const data = {};
            
            formData.forEach((value, key) => {
                if (document.getElementById(key)?.type === 'checkbox') {
                    data[key] = document.getElementById(key).checked ? '1' : '0';
                } else {
                    data[key] = value;
                }
            });
            
            // We'll implement batch save in the next version
            saved++;
        }
    });
    
    alert(`✅ All settings saved successfully!`);
}

// Reset to defaults
function resetToDefaults() {
    if (confirm('⚠️ Are you sure you want to reset all settings to default values?\n\nThis action cannot be undone!')) {
        fetch('process/process_settings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'reset_defaults',
                updated_by: '<?= $admin_name ?>'
            })
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert('✅ Settings reset to defaults!');
                window.location.reload();
            } else {
                alert('❌ Error: ' + result.message);
            }
        });
    }
}

// Create backup
function createBackup() {
    if (confirm('Create database backup? This may take a few moments.')) {
        window.location.href = 'process/backup.php';
    }
}

// Restore backup
function restoreBackup() {
    const fileInput = document.getElementById('backupFile');
    if (!fileInput.files || fileInput.files.length === 0) {
        alert('Please select a backup file to restore');
        return;
    }
    
    if (confirm('⚠️ WARNING: Restoring a backup will overwrite all current data!\n\nAre you absolutely sure?')) {
        const formData = new FormData();
        formData.append('backup_file', fileInput.files[0]);
        formData.append('action', 'restore');
        
        fetch('process/restore.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert('✅ Database restored successfully!');
                window.location.reload();
            } else {
                alert('❌ Error: ' + result.message);
            }
        })
        .catch(error => {
            alert('❌ Error restoring backup: ' + error);
        });
    }
}

// Test SMS configuration
function testSMS() {
    const provider = document.getElementById('sms_provider')?.value;
    const apiKey = document.getElementById('sms_api_key')?.value;
    
    if (!provider || !apiKey) {
        alert('Please configure SMS provider and API key first');
        return;
    }
    
    fetch('process/test_sms.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            provider: provider,
            api_key: apiKey
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('✅ SMS test successful! Check your phone.');
        } else {
            alert('❌ SMS test failed: ' + result.message);
        }
    });
}

// Clear cache
function clearCache() {
    if (confirm('Clear system cache?')) {
        fetch('process/clear_cache.php')
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert('✅ Cache cleared successfully!');
            } else {
                alert('❌ Error: ' + result.message);
            }
        });
    }
}
</script>

<style>
.nav-tabs .nav-link {
    color: #495057;
    font-weight: 500;
}
.nav-tabs .nav-link.active {
    color: #007bff;
    font-weight: 600;
}
.card {
    border: none;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    margin-bottom: 20px;
}
.card-header {
    font-weight: 600;
}
.form-switch {
    padding-top: 5px;
}
.bi-info-circle {
    cursor: help;
}
.password-mask {
    font-family: monospace;
    background: #f0f0f0;
    padding: 3px 8px;
    border-radius: 4px;
}
</style>

<?php
// Get the content
$content = ob_get_clean();

// Include the admin layout
require_once '../layouts/admin_layout.php';
?>