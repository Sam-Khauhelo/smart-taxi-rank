<?php
// admin/process/process_user.php
require_once '../../config/db.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<script>
        alert('Invalid request method!');
        window.location.href = '../register_user.php';
    </script>";
    exit();
}

// Get form data
$full_name = trim($_POST['full_name'] ?? '');
$phone_number = trim($_POST['phone_number'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? '';
$company_key = $_POST['company_key'] ?? ''; // The company key password entered by user

// Get user IP and user agent
$ip_address = $_SERVER['REMOTE_ADDR'];
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Basic validation
$errors = [];

if (empty($full_name)) $errors[] = "Full name is required";
if (empty($phone_number)) $errors[] = "Phone number is required";
if (empty($username)) $errors[] = "Username is required";
if (empty($password)) $errors[] = "Password is required";
if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
if (empty($role)) $errors[] = "Role is required";
if (empty($company_key)) $errors[] = "Company key password is required";

// If there are errors, show them in JavaScript dialog
if (!empty($errors)) {
    $error_message = implode("\\n", $errors);
    echo "<script>
        alert('❌ Validation Errors:\\n{$error_message}');
        window.location.href = '../register_user.php';
    </script>";
    exit();
}

try {
    // Get all company key passwords
    $key_sql = "SELECT password FROM company_keys";
    $key_stmt = $pdo->prepare($key_sql);
    $key_stmt->execute();
    $keys = $key_stmt->fetchAll();
    
    $valid_key = false;
    
    // Check if entered password matches any stored password
    foreach ($keys as $key) {
        if ($company_key === $key['password']) {
            $valid_key = true;
            break;
        }
    }
    
    if (!$valid_key) {
        // Log the failed attempt with all details
        $log_sql = "INSERT INTO failed_registration_attempts 
                    (full_name, phone_number, username, role, company_key_entered, ip_address, user_agent, attempt_time) 
                    VALUES (:full_name, :phone, :username, :role, :company_key, :ip, :ua, NOW())";
        
        $log_stmt = $pdo->prepare($log_sql);
        $log_stmt->execute([
            ':full_name' => $full_name,
            ':phone' => $phone_number,
            ':username' => $username,
            ':role' => $role,
            ':company_key' => $company_key,
            ':ip' => $ip_address,
            ':ua' => $user_agent
        ]);
        
        // Log to error log as well
        error_log("FAILED REGISTRATION ATTEMPT - IP: $ip_address, Username: $username, Role: $role, Key: $company_key");
        
        echo "<script>
            alert('❌ Authorization Failed!\\n\\nThe company key password you entered is incorrect.\\n\\nThis attempt has been logged.');
            window.location.href = '../register_user.php';
        </script>";
        exit();
    }
    
    // Check if username or phone already exists
    $check_sql = "SELECT id FROM users WHERE username = :username OR phone_number = :phone";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([
        ':username' => $username,
        ':phone' => $phone_number
    ]);
    
    if ($check_stmt->rowCount() > 0) {
        echo "<script>
            alert('❌ Error: Username or phone number already exists!');
            window.location.href = '../register_user.php';
        </script>";
        exit();
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Hash password for the new user
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert into users table
    $insert_sql = "INSERT INTO users (username, password, full_name, phone_number, role, is_active, created_at) 
                   VALUES (:username, :password, :full_name, :phone, :role, 1, NOW())";
    $insert_stmt = $pdo->prepare($insert_sql);
    $insert_stmt->execute([
        ':username' => $username,
        ':password' => $hashed_password,
        ':full_name' => $full_name,
        ':phone' => $phone_number,
        ':role' => $role
    ]);
    
    $user_id = $pdo->lastInsertId();
    
    // Handle role-specific data
    if ($role === 'owner') {
        $id_number = trim($_POST['id_number'] ?? '');
        $bank_name = trim($_POST['bank_name'] ?? '');
        $account_number = trim($_POST['account_number'] ?? '');
        $branch_code = trim($_POST['branch_code'] ?? '');
        
        if (empty($id_number)) {
            throw new Exception("ID number is required for owners");
        }
        
        $owner_sql = "INSERT INTO owners (user_id, id_number, bank_name, account_number, branch_code) 
                      VALUES (:user_id, :id_number, :bank_name, :account_number, :branch_code)";
        $owner_stmt = $pdo->prepare($owner_sql);
        $owner_stmt->execute([
            ':user_id' => $user_id,
            ':id_number' => $id_number,
            ':bank_name' => $bank_name,
            ':account_number' => $account_number,
            ':branch_code' => $branch_code
        ]);
        
        $message = "Owner registered successfully!";
        
    } elseif ($role === 'driver') {
        $driver_id_number = trim($_POST['driver_id_number'] ?? '');
        $license_expiry = $_POST['license_expiry'] ?? '';
        $owner_id = $_POST['owner_id'] ?? '';
        $employment_type = $_POST['employment_type'] ?? '';
        $payment_rate = $_POST['payment_rate'] ?? 0;
        
        // Validate driver fields
        if (empty($driver_id_number)) throw new Exception("ID number is required for drivers");
        if (empty($license_expiry)) throw new Exception("License expiry date is required");
        if (empty($owner_id)) throw new Exception("Please select an owner");
        if (empty($employment_type)) throw new Exception("Employment type is required");
        if (empty($payment_rate)) throw new Exception("Payment rate is required");
        
        $driver_sql = "INSERT INTO drivers (user_id, owner_id, id_number, license_expiry_date, employment_type, payment_rate) 
                       VALUES (:user_id, :owner_id, :id_number, :license_expiry, :employment_type, :payment_rate)";
        $driver_stmt = $pdo->prepare($driver_sql);
        $driver_stmt->execute([
            ':user_id' => $user_id,
            ':owner_id' => $owner_id,
            ':id_number' => $driver_id_number,
            ':license_expiry' => $license_expiry,
            ':employment_type' => $employment_type,
            ':payment_rate' => $payment_rate
        ]);
        
        $message = "Driver registered successfully!";
        
    } elseif ($role === 'marshal') {
        $station_point = trim($_POST['station_point'] ?? 'Main Rank');
        
        // Create marshals table if not exists
        $create_table = "CREATE TABLE IF NOT EXISTS marshals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNIQUE NOT NULL,
            station_point VARCHAR(100),
            shift ENUM('morning', 'afternoon', 'night') DEFAULT 'morning',
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        $pdo->exec($create_table);
        
        $shift = $_POST['shift'] ?? 'morning';
        
        $marshal_sql = "INSERT INTO marshals (user_id, station_point, shift) 
                        VALUES (:user_id, :station_point, :shift)";
        $marshal_stmt = $pdo->prepare($marshal_sql);
        $marshal_stmt->execute([
            ':user_id' => $user_id,
            ':station_point' => $station_point,
            ':shift' => $shift
        ]);
        
        $message = "Marshal registered successfully!";
        
    } else {
        // Admin registration
        $message = "Admin user registered successfully!";
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Show success message
    echo "<script>
        alert('✅ Registration Successful!\\n\\n{$message}\\n\\nUsername: {$username}');
        window.location.href = '../../index.php';
    </script>";
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Registration error: " . $e->getMessage());
    
    echo "<script>
        alert('❌ Registration Failed!\\n\\n" . addslashes($e->getMessage()) . "');
        window.location.href = '../register_user.php';
    </script>";
}
?>