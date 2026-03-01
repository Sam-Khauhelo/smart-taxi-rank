<?php
// process_login.php
session_start();
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    $_SESSION['login_error'] = "Please enter username and password";
    header('Location: index.php');
    exit();
}

try {
    // Get user from database
    $sql = "SELECT u.*, 
            d.id as driver_id, d.license_expiry_date,
            o.id as owner_id,
            t.id as taxi_id, t.registration_number
            FROM users u
            LEFT JOIN drivers d ON u.id = d.user_id
            LEFT JOIN owners o ON u.id = o.user_id
            LEFT JOIN taxis t ON d.taxi_id = t.id
            WHERE u.username = :username AND u.is_active = 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':username' => $username]);
    
    if ($stmt->rowCount() === 1) {
        $user = $stmt->fetch();
        
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['phone'] = $user['phone_number'];
            
            // Set role-specific session variables
            if ($user['role'] == 'driver') {
                $_SESSION['driver_id'] = $user['driver_id'];
                $_SESSION['taxi_id'] = $user['taxi_id'];
                $_SESSION['taxi_reg'] = $user['registration_number'];
            } elseif ($user['role'] == 'owner') {
                $_SESSION['owner_id'] = $user['owner_id'];
            }
            
            // Update last login
            $update_sql = "UPDATE users SET last_login = NOW() WHERE id = :id";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([':id' => $user['id']]);
            
            // Redirect based on role
            switch($user['role']) {
                case 'admin':
                    header('Location: admin/dashboard.php');
                    break;
                case 'marshal':
                    header('Location: marshal/dashboard.php');
                    break;
                case 'owner':
                    header('Location: owner/dashboard.php');
                    break;
                case 'driver':
                    header('Location: driver/portal.php');
                    break;
                default:
                    header('Location: logout.php');
            }
            exit();
        }
    }
    
    // If we get here, login failed
    $_SESSION['login_error'] = "Invalid username or password";
    header('Location: index.php');
    
} catch (PDOException $e) {
    error_log("Login error: " . $e->getMessage());
    $_SESSION['login_error'] = "System error. Please try again later.";
    header('Location: index.php');
}
?>