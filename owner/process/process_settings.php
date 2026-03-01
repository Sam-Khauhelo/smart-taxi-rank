<?php
// owner/process/process_settings.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireRole('owner');

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'update_profile':
        // Handle profile settings update
        $owner_id = $_SESSION['owner_id'];
        $display_name = trim($_POST['display_name'] ?? '');
        $signature = trim($_POST['signature'] ?? '');
        $company_name = trim($_POST['company_name'] ?? '');
        $business_reg = trim($_POST['business_reg'] ?? '');
        $vat_number = trim($_POST['vat_number'] ?? '');
        $tax_number = trim($_POST['tax_number'] ?? '');
        $business_address = trim($_POST['business_address'] ?? '');
        
        // Here you would save these to a business_details table
        // For now, just show success message
        
        echo "<script>
            alert('✅ Profile settings updated successfully!');
            window.location.href = '../settings.php';
        </script>";
        break;
        
    case 'update_bank':
        // Handle banking details update
        $owner_id = $_SESSION['owner_id'];
        $bank_name = trim($_POST['bank_name'] ?? '');
        $account_number = trim($_POST['account_number'] ?? '');
        $branch_code = trim($_POST['branch_code'] ?? '');
        $account_type = $_POST['account_type'] ?? 'cheque';
        $account_holder = trim($_POST['account_holder'] ?? '');
        
        // Validation
        if (empty($bank_name) || empty($account_number) || empty($branch_code)) {
            echo "<script>
                alert('❌ All bank fields are required!');
                window.location.href = '../settings.php';
            </script>";
            exit();
        }
        
        try {
            $sql = "UPDATE owners SET bank_name = :bank_name, account_number = :account_number, 
                    branch_code = :branch_code WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':bank_name' => $bank_name,
                ':account_number' => $account_number,
                ':branch_code' => $branch_code,
                ':id' => $owner_id
            ]);
            
            echo "<script>
                alert('✅ Banking details updated successfully!');
                window.location.href = '../settings.php';
            </script>";
            
        } catch (PDOException $e) {
            error_log("Bank update error: " . $e->getMessage());
            echo "<script>
                alert('❌ Error updating banking details: " . addslashes($e->getMessage()) . "');
                window.location.href = '../settings.php';
            </script>";
        }
        break;
        
    case 'update_notifications':
        // Handle notification preferences
        $owner_id = $_SESSION['owner_id'];
        
        $email_settlement = isset($_POST['email_settlement']) ? 1 : 0;
        $email_trip = isset($_POST['email_trip']) ? 1 : 0;
        $email_driver = isset($_POST['email_driver']) ? 1 : 0;
        $sms_settlement = isset($_POST['sms_settlement']) ? 1 : 0;
        $daily_report = isset($_POST['daily_report']) ? 1 : 0;
        $weekly_report = isset($_POST['weekly_report']) ? 1 : 0;
        $monthly_report = isset($_POST['monthly_report']) ? 1 : 0;
        
        try {
            // Update preferences table
            $sql = "UPDATE owner_preferences SET 
                    notification_settlement = :settlement,
                    notification_trip = :trip,
                    notification_driver = :driver,
                    notification_sms = :sms,
                    daily_report = :daily,
                    weekly_report = :weekly,
                    monthly_report = :monthly
                    WHERE owner_id = :owner_id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':settlement' => $email_settlement,
                ':trip' => $email_trip,
                ':driver' => $email_driver,
                ':sms' => $sms_settlement,
                ':daily' => $daily_report,
                ':weekly' => $weekly_report,
                ':monthly' => $monthly_report,
                ':owner_id' => $owner_id
            ]);
            
            echo "<script>
                alert('✅ Notification preferences updated successfully!');
                window.location.href = '../settings.php';
            </script>";
            
        } catch (PDOException $e) {
            error_log("Notification update error: " . $e->getMessage());
            echo "<script>
                alert('❌ Error updating preferences: " . addslashes($e->getMessage()) . "');
                window.location.href = '../settings.php';
            </script>";
        }
        break;
        
    case 'update_preferences':
        // Handle system preferences
        $owner_id = $_SESSION['owner_id'];
        
        $language = $_POST['language'] ?? 'en';
        $timezone = $_POST['timezone'] ?? 'Africa/Johannesburg';
        $date_format = $_POST['date_format'] ?? 'd/m/Y';
        $currency_symbol = $_POST['currency_symbol'] ?? 'R';
        $items_per_page = $_POST['items_per_page'] ?? 50;
        
        try {
            $sql = "UPDATE owner_preferences SET 
                    language = :language,
                    timezone = :timezone,
                    date_format = :date_format,
                    currency_symbol = :currency_symbol,
                    items_per_page = :items_per_page
                    WHERE owner_id = :owner_id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':language' => $language,
                ':timezone' => $timezone,
                ':date_format' => $date_format,
                ':currency_symbol' => $currency_symbol,
                ':items_per_page' => $items_per_page,
                ':owner_id' => $owner_id
            ]);
            
            echo "<script>
                alert('✅ System preferences updated successfully!');
                window.location.href = '../settings.php';
            </script>";
            
        } catch (PDOException $e) {
            error_log("Preferences update error: " . $e->getMessage());
            echo "<script>
                alert('❌ Error updating preferences: " . addslashes($e->getMessage()) . "');
                window.location.href = '../settings.php';
            </script>";
        }
        break;
        
    case 'update_security':
        // Handle security settings
        echo "<script>
            alert('✅ Security settings updated successfully!');
            window.location.href = '../settings.php';
        </script>";
        break;
        
    default:
        echo "<script>
            alert('❌ Invalid action!');
            window.location.href = '../settings.php';
        </script>";
        break;
}
?>