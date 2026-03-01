<?php
// admin/process/process_settings.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireRole('admin');

header('Content-Type: application/json');

// Handle both JSON and form POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if it's JSON or form data
    if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';
    } else {
        $data = $_POST;
        $action = $data['action'] ?? '';
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$response = ['success' => false, 'message' => 'Invalid action'];

try {
    switch ($action) {
        // Original settings category save
        case 'save_category':
            $category = $data['category'] ?? '';
            $updated_by = $data['updated_by'] ?? $_SESSION['full_name'];
            
            unset($data['action'], $data['category'], $data['updated_by']);
            
            $pdo->beginTransaction();
            
            foreach ($data as $key => $value) {
                $sql = "UPDATE system_settings SET setting_value = :value, updated_by = :updated_by 
                        WHERE setting_key = :key";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':value' => $value,
                    ':updated_by' => $updated_by,
                    ':key' => $key
                ]);
            }
            
            $pdo->commit();
            $response = ['success' => true, 'message' => 'Settings saved successfully'];
            break;
        
        // Original reset defaults
        case 'reset_defaults':
            $updated_by = $data['updated_by'] ?? $_SESSION['full_name'];
            
            $defaults = [
                'site_name' => 'Smart Taxi Rank',
                'association_name' => 'Taxi Association',
                'association_phone' => '0800 123 456',
                'association_email' => 'info@taxiassociation.co.za',
                'association_address' => '123 Rank Street, Durban',
                'default_levy_percentage' => '10',
                'vat_percentage' => '15',
                'enable_vat' => '0',
                'queue_timeout_minutes' => '30',
                'max_passengers_per_taxi' => '15',
                'enable_driver_commission' => '1',
                'default_commission_rate' => '20',
                'enable_notifications' => '1',
                'enable_sms_alerts' => '0',
                'sms_provider' => 'clickatell',
                'sms_api_key' => '',
                'currency_symbol' => 'R',
                'date_format' => 'Y-m-d',
                'time_format' => 'H:i',
                'session_timeout_minutes' => '120',
                'max_login_attempts' => '5',
                'lockout_duration_minutes' => '30',
                'enable_maintenance_mode' => '0',
                'maintenance_message' => 'System under maintenance. Please try again later.',
                'backup_frequency' => 'daily',
                'auto_backup' => '1',
                'allow_owner_registration' => '0',
                'require_approval' => '1',
                'items_per_page' => '50',
                'enable_debug_mode' => '0',
                'log_level' => 'error'
            ];
            
            $pdo->beginTransaction();
            
            foreach ($defaults as $key => $value) {
                $sql = "UPDATE system_settings SET setting_value = :value, updated_by = :updated_by 
                        WHERE setting_key = :key";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':value' => $value,
                    ':updated_by' => $updated_by,
                    ':key' => $key
                ]);
            }
            
            $pdo->commit();
            $response = ['success' => true, 'message' => 'Settings reset to defaults'];
            break;
        
        // Company Key Actions
        case 'add_company_key':
            $auth_code = trim($data['auth_code'] ?? '');
            $password = $data['password'] ?? '';
            $created_by = $_SESSION['full_name'];
            
            if (empty($auth_code) || empty($password)) {
                $response = ['success' => false, 'message' => 'Authorization code and password are required'];
                break;
            }
            
            // Check if code exists
            $check = $pdo->prepare("SELECT id FROM company_keys WHERE authorization_Code = ?");
            $check->execute([$auth_code]);
            
            if ($check->rowCount() > 0) {
                $response = ['success' => false, 'message' => 'Authorization code already exists'];
                break;
            }
            
            $sql = "INSERT INTO company_keys (authorization_Code, password, created_at, created_by, updated_at, updated_by) 
                    VALUES (?, ?, CURDATE(), ?, CURDATE(), ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$auth_code, $password, $created_by, $created_by]);
            
            $response = ['success' => true, 'message' => 'Company key added successfully'];
            break;
        
        case 'edit_company_key':
            $id = $data['key_id'] ?? 0;
            $auth_code = trim($data['auth_code'] ?? '');
            $password = $data['password'] ?? '';
            $updated_by = $_SESSION['full_name'];
            
            if (empty($id) || empty($auth_code)) {
                $response = ['success' => false, 'message' => 'Invalid data'];
                break;
            }
            
            // Check if code exists for other keys
            $check = $pdo->prepare("SELECT id FROM company_keys WHERE authorization_Code = ? AND id != ?");
            $check->execute([$auth_code, $id]);
            
            if ($check->rowCount() > 0) {
                $response = ['success' => false, 'message' => 'Authorization code already exists for another key'];
                break;
            }
            
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
            
            $response = ['success' => true, 'message' => 'Company key updated successfully'];
            break;
        
        case 'delete_company_key':
            $id = $data['key_id'] ?? 0;
            
            if (empty($id)) {
                $response = ['success' => false, 'message' => 'Invalid key ID'];
                break;
            }
            
            // Check if key is being used (optional - you might want to check failed_attempts or users)
            // For now, just delete
            $sql = "DELETE FROM company_keys WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            
            $response = ['success' => true, 'message' => 'Company key deleted successfully'];
            break;
        
        case 'get_company_keys':
            $keys = $pdo->query("SELECT * FROM company_keys ORDER BY created_at DESC")->fetchAll();
            $response = ['success' => true, 'data' => $keys];
            break;
        
        case 'verify_company_key':
            $auth_code = $data['auth_code'] ?? '';
            $password = $data['password'] ?? '';
            
            if (empty($auth_code) || empty($password)) {
                $response = ['success' => false, 'message' => 'Authorization code and password required'];
                break;
            }
            
            $sql = "SELECT * FROM company_keys WHERE authorization_Code = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$auth_code]);
            $key = $stmt->fetch();
            
            if ($key && $key['password'] === $password) {
                $response = ['success' => true, 'message' => 'Key verified successfully'];
            } else {
                $response = ['success' => false, 'message' => 'Invalid authorization code or password'];
            }
            break;
        
        default:
            $response = ['success' => false, 'message' => 'Unknown action'];
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Process settings error: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
}

echo json_encode($response);
?>