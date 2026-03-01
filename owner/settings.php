<?php
// owner/settings.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('owner');

// Set page title
$page_title = 'Settings';

// Start output buffering
ob_start();

$owner_id = $_SESSION['owner_id'];
$user_id = $_SESSION['user_id'];

try {
    // Get owner's current settings/preferences
    // First, check if owner_preferences table exists, if not create it
    $pdo->exec("CREATE TABLE IF NOT EXISTS owner_preferences (
        id INT AUTO_INCREMENT PRIMARY KEY,
        owner_id INT UNIQUE NOT NULL,
        notification_email TINYINT(1) DEFAULT 1,
        notification_sms TINYINT(1) DEFAULT 0,
        notification_settlement TINYINT(1) DEFAULT 1,
        notification_trip TINYINT(1) DEFAULT 1,
        notification_driver TINYINT(1) DEFAULT 1,
        daily_report TINYINT(1) DEFAULT 1,
        weekly_report TINYINT(1) DEFAULT 1,
        monthly_report TINYINT(1) DEFAULT 1,
        language VARCHAR(10) DEFAULT 'en',
        timezone VARCHAR(50) DEFAULT 'Africa/Johannesburg',
        date_format VARCHAR(20) DEFAULT 'd/m/Y',
        currency_symbol VARCHAR(10) DEFAULT 'R',
        items_per_page INT DEFAULT 50,
        dashboard_widgets TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (owner_id) REFERENCES owners(id) ON DELETE CASCADE
    )");
    
    // Get or create owner preferences
    $pref_sql = "SELECT * FROM owner_preferences WHERE owner_id = ?";
    $pref_stmt = $pdo->prepare($pref_sql);
    $pref_stmt->execute([$owner_id]);
    $preferences = $pref_stmt->fetch();
    
    if (!$preferences) {
        // Create default preferences
        $insert_sql = "INSERT INTO owner_preferences (owner_id) VALUES (?)";
        $insert_stmt = $pdo->prepare($insert_sql);
        $insert_stmt->execute([$owner_id]);
        
        // Fetch newly created preferences
        $pref_stmt->execute([$owner_id]);
        $preferences = $pref_stmt->fetch();
    }
    
    // Get owner's banking details
    $bank_sql = "SELECT bank_name, account_number, branch_code FROM owners WHERE id = ?";
    $bank_stmt = $pdo->prepare($bank_sql);
    $bank_stmt->execute([$owner_id]);
    $bank_details = $bank_stmt->fetch();
    
    // Get notification history
    $notifications_sql = "SELECT * FROM owner_notifications 
                          WHERE owner_id = ? 
                          ORDER BY created_at DESC 
                          LIMIT 20";
    $notifications_stmt = $pdo->prepare($notifications_sql);
    $notifications_stmt->execute([$owner_id]);
    $notifications = $notifications_stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Settings error: " . $e->getMessage());
    $preferences = [];
    $bank_details = [];
    $notifications = [];
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-gear"></i> Settings</h2>
        <div>
            <button class="btn btn-primary" onclick="saveAllSettings()">
                <i class="bi bi-save"></i> Save All Changes
            </button>
        </div>
    </div>

    <!-- Settings Tabs -->
    <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab">
                <i class="bi bi-person"></i> Profile Settings
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab">
                <i class="bi bi-bell"></i> Notifications
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="preferences-tab" data-bs-toggle="tab" data-bs-target="#preferences" type="button" role="tab">
                <i class="bi bi-sliders"></i> Preferences
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                <i class="bi bi-shield-lock"></i> Security
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="api-tab" data-bs-toggle="tab" data-bs-target="#api" type="button" role="tab">
                <i class="bi bi-code"></i> API Access
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="settingsTabContent">
        <!-- Profile Settings Tab -->
        <div class="tab-pane fade show active" id="profile" role="tabpanel">
            <div class="card">
                <div class="card-header" style="background: #f39c12; color: white;">
                    <h5 class="mb-0"><i class="bi bi-person"></i> Profile Settings</h5>
                </div>
                <div class="card-body">
                    <form id="profileForm" action="process/process_settings.php" method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="display_name" class="form-label">Display Name</label>
                                <input type="text" class="form-control" id="display_name" name="display_name" 
                                       value="<?= htmlspecialchars($_SESSION['full_name']) ?>">
                                <small class="text-muted">How your name appears in the system</small>
                            </div>
                            <div class="col-md-6">
                                <label for="signature" class="form-label">Digital Signature</label>
                                <input type="text" class="form-control" id="signature" name="signature" 
                                       placeholder="e.g., Thabo M.">
                                <small class="text-muted">Used for approvals and statements</small>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="company_name" class="form-label">Company/Business Name</label>
                                <input type="text" class="form-control" id="company_name" name="company_name" 
                                       placeholder="Your taxi business name">
                            </div>
                            <div class="col-md-6">
                                <label for="business_reg" class="form-label">Business Registration Number</label>
                                <input type="text" class="form-control" id="business_reg" name="business_reg">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="vat_number" class="form-label">VAT Number (if applicable)</label>
                                <input type="text" class="form-control" id="vat_number" name="vat_number">
                            </div>
                            <div class="col-md-6">
                                <label for="tax_number" class="form-label">Tax Reference Number</label>
                                <input type="text" class="form-control" id="tax_number" name="tax_number">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="business_address" class="form-label">Business Address</label>
                            <textarea class="form-control" id="business_address" name="business_address" rows="2"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="background: #f39c12; border-color: #f39c12;">
                            <i class="bi bi-save"></i> Update Profile
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Banking Details Card -->
            <div class="card mt-4">
                <div class="card-header" style="background: #27ae60; color: white;">
                    <h5 class="mb-0"><i class="bi bi-bank"></i> Banking Details</h5>
                </div>
                <div class="card-body">
                    <form id="bankForm" action="process/process_settings.php" method="POST">
                        <input type="hidden" name="action" value="update_bank">
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="bank_name" class="form-label">Bank Name *</label>
                                <input type="text" class="form-control" id="bank_name" name="bank_name" 
                                       value="<?= htmlspecialchars($bank_details['bank_name'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="account_number" class="form-label">Account Number *</label>
                                <input type="text" class="form-control" id="account_number" name="account_number" 
                                       value="<?= htmlspecialchars($bank_details['account_number'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="branch_code" class="form-label">Branch Code *</label>
                                <input type="text" class="form-control" id="branch_code" name="branch_code" 
                                       value="<?= htmlspecialchars($bank_details['branch_code'] ?? '') ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="account_type" class="form-label">Account Type</label>
                                <select class="form-select" id="account_type" name="account_type">
                                    <option value="cheque">Cheque Account</option>
                                    <option value="savings">Savings Account</option>
                                    <option value="transmission">Transmission Account</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="account_holder" class="form-label">Account Holder Name</label>
                                <input type="text" class="form-control" id="account_holder" name="account_holder" 
                                       value="<?= htmlspecialchars($_SESSION['full_name']) ?>">
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Your banking details are encrypted and used only for settlements.
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-save"></i> Update Banking Details
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Notifications Tab -->
        <div class="tab-pane fade" id="notifications" role="tabpanel">
            <div class="card">
                <div class="card-header" style="background: #3498db; color: white;">
                    <h5 class="mb-0"><i class="bi bi-bell"></i> Notification Preferences</h5>
                </div>
                <div class="card-body">
                    <form id="notificationsForm" action="process/process_settings.php" method="POST">
                        <input type="hidden" name="action" value="update_notifications">
                        
                        <h6 class="mb-3">Email Notifications</h6>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="email_settlement" 
                                           name="email_settlement" <?= ($preferences['notification_settlement'] ?? 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="email_settlement">Settlement updates</label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="email_trip" 
                                           name="email_trip" <?= ($preferences['notification_trip'] ?? 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="email_trip">Daily trip summary</label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="email_driver" 
                                           name="email_driver" <?= ($preferences['notification_driver'] ?? 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="email_driver">Driver updates</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="email_promotional" 
                                           name="email_promotional">
                                    <label class="form-check-label" for="email_promotional">Promotional emails</label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="email_newsletter" 
                                           name="email_newsletter">
                                    <label class="form-check-label" for="email_newsletter">Newsletter</label>
                                </div>
                            </div>
                        </div>
                        
                        <h6 class="mb-3 mt-4">SMS Notifications</h6>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="sms_settlement" 
                                           name="sms_settlement" <?= ($preferences['notification_sms'] ?? 0) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="sms_settlement">Settlement alerts (SMS)</label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="sms_daily" 
                                           name="sms_daily">
                                    <label class="form-check-label" for="sms_daily">Daily earnings summary (SMS)</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="sms_phone" class="form-label">SMS Phone Number</label>
                                <input type="tel" class="form-control" id="sms_phone" name="sms_phone" 
                                       value="<?= htmlspecialchars($_SESSION['phone'] ?? '') ?>">
                                <small class="text-muted">Separate multiple numbers with commas</small>
                            </div>
                        </div>
                        
                        <h6 class="mb-3 mt-4">Report Frequency</h6>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="daily_report" 
                                           name="daily_report" <?= ($preferences['daily_report'] ?? 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="daily_report">Daily Report</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="weekly_report" 
                                           name="weekly_report" <?= ($preferences['weekly_report'] ?? 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="weekly_report">Weekly Report</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="monthly_report" 
                                           name="monthly_report" <?= ($preferences['monthly_report'] ?? 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="monthly_report">Monthly Report</label>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="background: #3498db; border-color: #3498db;">
                            <i class="bi bi-save"></i> Save Notification Settings
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Recent Notifications Card -->
            <div class="card mt-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Notifications</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($notifications)): ?>
                        <p class="text-muted text-center py-3">No recent notifications</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($notifications as $note): ?>
                                <div class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?= htmlspecialchars($note['title']) ?></h6>
                                        <small class="text-muted"><?= date('d M H:i', strtotime($note['created_at'])) ?></small>
                                    </div>
                                    <p class="mb-1"><?= htmlspecialchars($note['message']) ?></p>
                                    <small class="text-<?= $note['read_status'] ? 'muted' : 'primary' ?>">
                                        <?= $note['read_status'] ? 'Read' : 'New' ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Preferences Tab -->
        <div class="tab-pane fade" id="preferences" role="tabpanel">
            <div class="card">
                <div class="card-header" style="background: #9b59b6; color: white;">
                    <h5 class="mb-0"><i class="bi bi-sliders"></i> System Preferences</h5>
                </div>
                <div class="card-body">
                    <form id="preferencesForm" action="process/process_settings.php" method="POST">
                        <input type="hidden" name="action" value="update_preferences">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="language" class="form-label">Language</label>
                                <select class="form-select" id="language" name="language">
                                    <option value="en" <?= ($preferences['language'] ?? 'en') == 'en' ? 'selected' : '' ?>>English</option>
                                    <option value="zu" <?= ($preferences['language'] ?? '') == 'zu' ? 'selected' : '' ?>>isiZulu</option>
                                    <option value="xh" <?= ($preferences['language'] ?? '') == 'xh' ? 'selected' : '' ?>>isiXhosa</option>
                                    <option value="af" <?= ($preferences['language'] ?? '') == 'af' ? 'selected' : '' ?>>Afrikaans</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="timezone" class="form-label">Timezone</label>
                                <select class="form-select" id="timezone" name="timezone">
                                    <option value="Africa/Johannesburg" <?= ($preferences['timezone'] ?? 'Africa/Johannesburg') == 'Africa/Johannesburg' ? 'selected' : '' ?>>South Africa (UTC+2)</option>
                                    <option value="Africa/Cairo">Egypt (UTC+2)</option>
                                    <option value="Africa/Lagos">Nigeria (UTC+1)</option>
                                    <option value="Africa/Nairobi">Kenya (UTC+3)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="date_format" class="form-label">Date Format</label>
                                <select class="form-select" id="date_format" name="date_format">
                                    <option value="d/m/Y" <?= ($preferences['date_format'] ?? 'd/m/Y') == 'd/m/Y' ? 'selected' : '' ?>>DD/MM/YYYY (27/02/2024)</option>
                                    <option value="Y-m-d" <?= ($preferences['date_format'] ?? '') == 'Y-m-d' ? 'selected' : '' ?>>YYYY-MM-DD (2024-02-27)</option>
                                    <option value="m/d/Y" <?= ($preferences['date_format'] ?? '') == 'm/d/Y' ? 'selected' : '' ?>>MM/DD/YYYY (02/27/2024)</option>
                                    <option value="d M Y" <?= ($preferences['date_format'] ?? '') == 'd M Y' ? 'selected' : '' ?>>DD Mon YYYY (27 Feb 2024)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="currency_symbol" class="form-label">Currency Symbol</label>
                                <select class="form-select" id="currency_symbol" name="currency_symbol">
                                    <option value="R" <?= ($preferences['currency_symbol'] ?? 'R') == 'R' ? 'selected' : '' ?>>Rand (R)</option>
                                    <option value="$" <?= ($preferences['currency_symbol'] ?? '') == '$' ? 'selected' : '' ?>>Dollar ($)</option>
                                    <option value="€" <?= ($preferences['currency_symbol'] ?? '') == '€' ? 'selected' : '' ?>>Euro (€)</option>
                                    <option value="£" <?= ($preferences['currency_symbol'] ?? '') == '£' ? 'selected' : '' ?>>Pound (£)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="items_per_page" class="form-label">Items Per Page</label>
                                <select class="form-select" id="items_per_page" name="items_per_page">
                                    <option value="25" <?= ($preferences['items_per_page'] ?? 50) == 25 ? 'selected' : '' ?>>25</option>
                                    <option value="50" <?= ($preferences['items_per_page'] ?? 50) == 50 ? 'selected' : '' ?>>50</option>
                                    <option value="100" <?= ($preferences['items_per_page'] ?? 50) == 100 ? 'selected' : '' ?>>100</option>
                                    <option value="200" <?= ($preferences['items_per_page'] ?? 50) == 200 ? 'selected' : '' ?>>200</option>
                                </select>
                            </div>
                        </div>
                        
                        <h6 class="mb-3">Dashboard Widgets</h6>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="widget_earnings" checked>
                                    <label class="form-check-label" for="widget_earnings">Earnings Summary</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="widget_taxis" checked>
                                    <label class="form-check-label" for="widget_taxis">Taxis Overview</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="widget_drivers" checked>
                                    <label class="form-check-label" for="widget_drivers">Drivers Overview</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="widget_trips" checked>
                                    <label class="form-check-label" for="widget_trips">Recent Trips</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="widget_settlements" checked>
                                    <label class="form-check-label" for="widget_settlements">Settlements</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="widget_chart" checked>
                                    <label class="form-check-label" for="widget_chart">Earnings Chart</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="widget_alerts" checked>
                                    <label class="form-check-label" for="widget_alerts">Alerts & Warnings</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="widget_weather">
                                    <label class="form-check-label" for="widget_weather">Weather Info</label>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="background: #9b59b6; border-color: #9b59b6;">
                            <i class="bi bi-save"></i> Save Preferences
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Security Tab -->
        <div class="tab-pane fade" id="security" role="tabpanel">
            <div class="card">
                <div class="card-header" style="background: #e74c3c; color: white;">
                    <h5 class="mb-0"><i class="bi bi-shield-lock"></i> Security Settings</h5>
                </div>
                <div class="card-body">
                    <form id="securityForm" action="process/process_settings.php" method="POST">
                        <input type="hidden" name="action" value="update_security">
                        
                        <h6 class="mb-3">Two-Factor Authentication</h6>
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="two_factor" name="two_factor">
                                    <label class="form-check-label" for="two_factor">Enable Two-Factor Authentication</label>
                                </div>
                                <small class="text-muted">Add an extra layer of security to your account</small>
                            </div>
                        </div>
                        
                        <h6 class="mb-3 mt-4">Login Alerts</h6>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="alert_new_device" checked>
                                    <label class="form-check-label" for="alert_new_device">Alert on new device login</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="alert_failed_login" checked>
                                    <label class="form-check-label" for="alert_failed_login">Alert on failed login attempts</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="alert_password_change" checked>
                                    <label class="form-check-label" for="alert_password_change">Alert on password change</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="alert_settlement" checked>
                                    <label class="form-check-label" for="alert_settlement">Alert on settlement requests</label>
                                </div>
                            </div>
                        </div>
                        
                        <h6 class="mb-3 mt-4">Session Management</h6>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="session_timeout" class="form-label">Session Timeout (minutes)</label>
                                <input type="number" class="form-control" id="session_timeout" value="30" min="5" max="120">
                            </div>
                            <div class="col-md-6">
                                <label for="login_attempts" class="form-label">Max Login Attempts</label>
                                <input type="number" class="form-control" id="login_attempts" value="5" min="3" max="10">
                            </div>
                        </div>
                        
                        <h6 class="mb-3 mt-4">Active Sessions</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Device</th>
                                        <th>IP Address</th>
                                        <th>Last Active</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><i class="bi bi-laptop"></i> Chrome on Windows</td>
                                        <td>192.168.1.100</td>
                                        <td>Now</td>
                                        <td><span class="badge bg-success">Current</span></td>
                                    </tr>
                                    <tr>
                                        <td><i class="bi bi-phone"></i> Safari on iPhone</td>
                                        <td>192.168.1.105</td>
                                        <td>2 hours ago</td>
                                        <td><button class="btn btn-sm btn-danger">Terminate</button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-save"></i> Save Security Settings
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Recent Activity Card -->
            <div class="card mt-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Security Activity</h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <div class="d-flex mb-3">
                            <div class="me-3">
                                <span class="badge bg-success p-2"><i class="bi bi-check"></i></span>
                            </div>
                            <div>
                                <p class="mb-0">Successful login from Chrome on Windows</p>
                                <small class="text-muted">Just now</small>
                            </div>
                        </div>
                        <div class="d-flex mb-3">
                            <div class="me-3">
                                <span class="badge bg-warning p-2"><i class="bi bi-exclamation"></i></span>
                            </div>
                            <div>
                                <p class="mb-0">Failed login attempt from unknown device</p>
                                <small class="text-muted">2 hours ago</small>
                            </div>
                        </div>
                        <div class="d-flex mb-3">
                            <div class="me-3">
                                <span class="badge bg-info p-2"><i class="bi bi-key"></i></span>
                            </div>
                            <div>
                                <p class="mb-0">Password changed successfully</p>
                                <small class="text-muted">3 days ago</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- API Access Tab -->
        <div class="tab-pane fade" id="api" role="tabpanel">
            <div class="card">
                <div class="card-header" style="background: #1abc9c; color: white;">
                    <h5 class="mb-0"><i class="bi bi-code"></i> API Access</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> API access allows you to connect your own applications to your taxi data.
                    </div>
                    
                    <h6 class="mb-3">Your API Keys</h6>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>API Key</th>
                                    <th>Created</th>
                                    <th>Last Used</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Default Key</td>
                                    <td>
                                        <code>sk_live_••••••••••••••••</code>
                                        <button class="btn btn-sm btn-link" onclick="showFullKey()">Show</button>
                                    </td>
                                    <td>27 Feb 2024</td>
                                    <td>Today</td>
                                    <td><span class="badge bg-success">Active</span></td>
                                    <td>
                                        <button class="btn btn-sm btn-danger">Revoke</button>
                                        <button class="btn btn-sm btn-warning">Regenerate</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <button class="btn btn-success mt-3" onclick="generateNewKey()">
                        <i class="bi bi-plus-circle"></i> Generate New API Key
                    </button>
                    
                    <hr class="my-4">
                    
                    <h6 class="mb-3">API Usage Statistics</h6>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="border p-3 text-center">
                                <h3 class="mb-0">1,234</h3>
                                <small class="text-muted">Today's Calls</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border p-3 text-center">
                                <h3 class="mb-0">45.2k</h3>
                                <small class="text-muted">This Month</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border p-3 text-center">
                                <h3 class="mb-0">98.5%</h3>
                                <small class="text-muted">Success Rate</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border p-3 text-center">
                                <h3 class="mb-0">234ms</h3>
                                <small class="text-muted">Avg Response</small>
                            </div>
                        </div>
                    </div>
                    
                    <h6 class="mb-3 mt-4">API Documentation</h6>
                    <p>Access our API documentation to integrate with your systems:</p>
                    <a href="#" class="btn btn-outline-primary">
                        <i class="bi bi-file-text"></i> View API Documentation
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Success/Error Message Modal (hidden by default) -->
<div class="modal fade" id="messageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" id="messageModalHeader">
                <h5 class="modal-title" id="messageModalTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="messageModalBody">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    border: none;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    margin-bottom: 20px;
    border-radius: 10px;
    overflow: hidden;
}
.card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.card-header {
    font-weight: 600;
    border-bottom: none;
}
.nav-tabs .nav-link {
    color: #495057;
    font-weight: 500;
}
.nav-tabs .nav-link.active {
    color: #f39c12;
    font-weight: 600;
    border-bottom: 2px solid #f39c12;
}
.form-switch {
    padding-left: 2.5em;
}
.form-switch .form-check-input {
    width: 2em;
    margin-left: -2.5em;
}
.timeline {
    max-height: 300px;
    overflow-y: auto;
}
.border {
    transition: transform 0.2s;
}
.border:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
</style>

<script>
// Save all settings
function saveAllSettings() {
    // Collect all form data
    const forms = ['profileForm', 'bankForm', 'notificationsForm', 'preferencesForm', 'securityForm'];
    let successCount = 0;
    
    forms.forEach(formId => {
        const form = document.getElementById(formId);
        if (form) {
            // In a real implementation, you'd submit each form via AJAX
            // For now, we'll just show a message
            successCount++;
        }
    });
    
    showMessage('success', 'Settings Saved', `All settings have been saved successfully.`);
}

// Show message modal
function showMessage(type, title, message) {
    const modal = new bootstrap.Modal(document.getElementById('messageModal'));
    const header = document.getElementById('messageModalHeader');
    const modalTitle = document.getElementById('messageModalTitle');
    const modalBody = document.getElementById('messageModalBody');
    
    header.className = 'modal-header';
    if (type === 'success') {
        header.classList.add('bg-success', 'text-white');
    } else if (type === 'error') {
        header.classList.add('bg-danger', 'text-white');
    } else if (type === 'warning') {
        header.classList.add('bg-warning');
    } else if (type === 'info') {
        header.classList.add('bg-info', 'text-white');
    }
    
    modalTitle.textContent = title;
    modalBody.innerHTML = `<p class="mb-0">${message}</p>`;
    
    modal.show();
}

// Generate new API key
function generateNewKey() {
    if (confirm('Generate a new API key? The old key will stop working immediately.')) {
        showMessage('success', 'API Key Generated', 'Your new API key has been generated. Please save it securely.');
    }
}

// Show full API key
function showFullKey() {
    alert('sk_live_abc123xyz789def456ghi789jkl');
}

// Preview image before upload (if needed)
document.getElementById('profile_image')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        if (file.size > 2 * 1024 * 1024) {
            alert('❌ File size must be less than 2MB');
            this.value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('imagePreview');
            if (preview) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
        }
        reader.readAsDataURL(file);
    }
});

// Auto-save hint
let autoSaveTimer;
document.querySelectorAll('input, select, textarea').forEach(element => {
    element.addEventListener('change', () => {
        clearTimeout(autoSaveTimer);
        autoSaveTimer = setTimeout(() => {
            // Show auto-save indicator (optional)
            const indicator = document.getElementById('autoSaveIndicator');
            if (indicator) {
                indicator.style.display = 'block';
                setTimeout(() => {
                    indicator.style.display = 'none';
                }, 2000);
            }
        }, 2000);
    });
});
</script>

<?php
// Get the content
$content = ob_get_clean();

// Include the owner layout
require_once '../layouts/owner_layout.php';
?>