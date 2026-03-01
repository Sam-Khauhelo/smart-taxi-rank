<?php
// owner/process/process_driver.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireRole('owner');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'add':
        // Handle add driver
        $full_name = trim($_POST['full_name'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $id_number = trim($_POST['id_number'] ?? '');
        $license_expiry = $_POST['license_expiry'] ?? '';
        $taxi_id = $_POST['taxi_id'] ?? null;
        $employment_type = $_POST['employment_type'] ?? '';
        $payment_rate = $_POST['payment_rate'] ?? 0;
        $owner_id = $_POST['owner_id'] ?? $_SESSION['owner_id'];
        
        // Validation
        $errors = [];
        if (empty($full_name)) $errors[] = "Full name is required";
        if (empty($phone_number)) $errors[] = "Phone number is required";
        if (empty($email)) $errors[] = "Email address is required";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
        if (empty($password)) $errors[] = "Password is required";
        if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
        if (empty($id_number)) $errors[] = "ID number is required";
        if (empty($license_expiry)) $errors[] = "License expiry date is required";
        if (empty($employment_type)) $errors[] = "Employment type is required";
        if (empty($payment_rate) || $payment_rate <= 0) $errors[] = "Valid payment rate is required";
        
        if (!empty($errors)) {
            $error_msg = implode("\\n", $errors);
            echo "<script>
                alert('Validation Errors:\\n{$error_msg}');
                window.location.href = '../my_drivers.php';
            </script>";
            exit();
        }
        
        try {
            // Check if email already exists
            $check_sql = "SELECT id FROM users WHERE username = :email";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([':email' => $email]);
            
            if ($check_stmt->rowCount() > 0) {
                echo "<script>
                    alert('Email address already exists!');
                    window.location.href = '../my_drivers.php';
                </script>";
                exit();
            }
            
            // Check if taxi is already assigned
            if (!empty($taxi_id)) {
                $check_taxi_sql = "SELECT id FROM drivers WHERE taxi_id = :taxi_id";
                $check_taxi_stmt = $pdo->prepare($check_taxi_sql);
                $check_taxi_stmt->execute([':taxi_id' => $taxi_id]);
                
                if ($check_taxi_stmt->rowCount() > 0) {
                    echo "<script>
                        alert('This taxi is already assigned to another driver!');
                        window.location.href = '../my_drivers.php';
                    </script>";
                    exit();
                }
            }
            
            // Start transaction
            $pdo->beginTransaction();
            
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert into users table
            $user_sql = "INSERT INTO users (username, password, full_name, phone_number, role, is_active, created_at) 
                         VALUES (:email, :password, :full_name, :phone, 'driver', 1, NOW())";
            $user_stmt = $pdo->prepare($user_sql);
            $user_stmt->execute([
                ':email' => $email,
                ':password' => $hashed_password,
                ':full_name' => $full_name,
                ':phone' => $phone_number
            ]);
            
            $user_id = $pdo->lastInsertId();
            
            // Insert into drivers table
            $driver_sql = "INSERT INTO drivers (user_id, owner_id, taxi_id, id_number, license_expiry_date, 
                           employment_type, payment_rate, added_by, added_at) 
                           VALUES (:user_id, :owner_id, :taxi_id, :id_number, :license_expiry, 
                           :employment_type, :payment_rate, :added_by, NOW())";
            $driver_stmt = $pdo->prepare($driver_sql);
            $driver_stmt->execute([
                ':user_id' => $user_id,
                ':owner_id' => $owner_id,
                ':taxi_id' => $taxi_id,
                ':id_number' => $id_number,
                ':license_expiry' => $license_expiry,
                ':employment_type' => $employment_type,
                ':payment_rate' => $payment_rate,
                ':added_by' => $_SESSION['full_name']
            ]);
            
            $pdo->commit();
            
            echo "<script>
                alert('✅ Driver added successfully!\\n\\nUsername: {$email}\\nPassword: {$password}');
                window.location.href = '../my_drivers.php';
            </script>";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Add driver error: " . $e->getMessage());
            echo "<script>
                alert('❌ Error adding driver: " . addslashes($e->getMessage()) . "');
                window.location.href = '../my_drivers.php';
            </script>";
        }
        break;
        
    case 'edit':
        // Handle edit driver
        $driver_id = $_POST['driver_id'] ?? 0;
        $user_id = $_POST['user_id'] ?? 0;
        $full_name = trim($_POST['full_name'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? '');
        $password = $_POST['password'] ?? '';
        $id_number = trim($_POST['id_number'] ?? '');
        $license_expiry = $_POST['license_expiry'] ?? '';
        $taxi_id = $_POST['taxi_id'] ?? null;
        $employment_type = $_POST['employment_type'] ?? '';
        $payment_rate = $_POST['payment_rate'] ?? 0;
        $is_active = $_POST['is_active'] ?? 1;
        
        // Validation
        $errors = [];
        if (empty($driver_id)) $errors[] = "Driver ID is missing";
        if (empty($user_id)) $errors[] = "User ID is missing";
        if (empty($full_name)) $errors[] = "Full name is required";
        if (empty($phone_number)) $errors[] = "Phone number is required";
        if (empty($id_number)) $errors[] = "ID number is required";
        if (empty($license_expiry)) $errors[] = "License expiry date is required";
        if (empty($employment_type)) $errors[] = "Employment type is required";
        if (empty($payment_rate) || $payment_rate <= 0) $errors[] = "Valid payment rate is required";
        
        if (!empty($errors)) {
            $error_msg = implode("\\n", $errors);
            echo "<script>
                alert('Validation Errors:\\n{$error_msg}');
                window.location.href = '../my_drivers.php';
            </script>";
            exit();
        }
        
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Update users table
            if (!empty($password)) {
                if (strlen($password) < 6) {
                    throw new Exception("Password must be at least 6 characters");
                }
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $user_sql = "UPDATE users SET full_name = :full_name, phone_number = :phone, 
                            password = :password, is_active = :is_active WHERE id = :id";
                $user_stmt = $pdo->prepare($user_sql);
                $user_stmt->execute([
                    ':full_name' => $full_name,
                    ':phone' => $phone_number,
                    ':password' => $hashed_password,
                    ':is_active' => $is_active,
                    ':id' => $user_id
                ]);
            } else {
                $user_sql = "UPDATE users SET full_name = :full_name, phone_number = :phone, 
                            is_active = :is_active WHERE id = :id";
                $user_stmt = $pdo->prepare($user_sql);
                $user_stmt->execute([
                    ':full_name' => $full_name,
                    ':phone' => $phone_number,
                    ':is_active' => $is_active,
                    ':id' => $user_id
                ]);
            }
            
            // Check if taxi is being changed
            $old_taxi_sql = "SELECT taxi_id FROM drivers WHERE id = :id";
            $old_taxi_stmt = $pdo->prepare($old_taxi_sql);
            $old_taxi_stmt->execute([':id' => $driver_id]);
            $old_taxi = $old_taxi_stmt->fetch();
            
            // Update drivers table
            $driver_sql = "UPDATE drivers SET id_number = :id_number, license_expiry_date = :license_expiry,
                          taxi_id = :taxi_id, employment_type = :employment_type, payment_rate = :payment_rate
                          WHERE id = :id";
            $driver_stmt = $pdo->prepare($driver_sql);
            $driver_stmt->execute([
                ':id_number' => $id_number,
                ':license_expiry' => $license_expiry,
                ':taxi_id' => $taxi_id,
                ':employment_type' => $employment_type,
                ':payment_rate' => $payment_rate,
                ':id' => $driver_id
            ]);
            
            $pdo->commit();
            
            echo "<script>
                alert('✅ Driver updated successfully!');
                window.location.href = '../my_drivers.php';
            </script>";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Edit driver error: " . $e->getMessage());
            echo "<script>
                alert('❌ Error updating driver: " . addslashes($e->getMessage()) . "');
                window.location.href = '../my_drivers.php';
            </script>";
        }
        break;
        
    case 'assign_taxi':
        // Handle assign taxi to driver
        $driver_id = $_POST['driver_id'] ?? 0;
        $taxi_id = $_POST['taxi_id'] ?? 0;
        
        if (empty($driver_id) || empty($taxi_id)) {
            echo "<script>
                alert('Driver and taxi are required!');
                window.location.href = '../my_drivers.php';
            </script>";
            exit();
        }
        
        try {
            // Check if taxi is already assigned
            $check_sql = "SELECT id FROM drivers WHERE taxi_id = :taxi_id AND id != :driver_id";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([
                ':taxi_id' => $taxi_id,
                ':driver_id' => $driver_id
            ]);
            
            if ($check_stmt->rowCount() > 0) {
                echo "<script>
                    alert('This taxi is already assigned to another driver!');
                    window.location.href = '../my_drivers.php';
                </script>";
                exit();
            }
            
            // Update driver
            $sql = "UPDATE drivers SET taxi_id = :taxi_id WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':taxi_id' => $taxi_id,
                ':id' => $driver_id
            ]);
            
            echo "<script>
                alert('✅ Taxi assigned successfully!');
                window.location.href = '../my_drivers.php';
            </script>";
            
        } catch (PDOException $e) {
            error_log("Assign taxi error: " . $e->getMessage());
            echo "<script>
                alert('❌ Error assigning taxi: " . addslashes($e->getMessage()) . "');
                window.location.href = '../my_drivers.php';
            </script>";
        }
        break;
        
    case 'unassign_taxi':
        // Handle unassign taxi
        $driver_id = $_GET['driver_id'] ?? 0;
        $taxi_id = $_GET['taxi_id'] ?? 0;
        
        if (empty($driver_id)) {
            echo "<script>
                alert('Driver ID missing!');
                window.location.href = '../my_drivers.php';
            </script>";
            exit();
        }
        
        try {
            $sql = "UPDATE drivers SET taxi_id = NULL WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $driver_id]);
            
            echo "<script>
                alert('✅ Driver unassigned from taxi successfully!');
                window.location.href = '../my_drivers.php';
            </script>";
            
        } catch (PDOException $e) {
            error_log("Unassign taxi error: " . $e->getMessage());
            echo "<script>
                alert('❌ Error unassigning taxi: " . addslashes($e->getMessage()) . "');
                window.location.href = '../my_drivers.php';
            </script>";
        }
        break;
        
    case 'toggle':
        // Handle toggle driver status
        $driver_id = $_GET['id'] ?? 0;
        $status = $_GET['status'] ?? 0;
        
        if (empty($driver_id)) {
            echo "<script>
                alert('Driver ID missing!');
                window.location.href = '../my_drivers.php';
            </script>";
            exit();
        }
        
        try {
            // Get user_id
            $user_sql = "SELECT user_id FROM drivers WHERE id = :id";
            $user_stmt = $pdo->prepare($user_sql);
            $user_stmt->execute([':id' => $driver_id]);
            $driver = $user_stmt->fetch();
            
            if (!$driver) {
                echo "<script>
                    alert('Driver not found!');
                    window.location.href = '../my_drivers.php';
                </script>";
                exit();
            }
            
            // Update user status
            $sql = "UPDATE users SET is_active = :status WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':status' => $status,
                ':id' => $driver['user_id']
            ]);
            
            $action = $status ? 'activated' : 'deactivated';
            echo "<script>
                alert('✅ Driver {$action} successfully!');
                window.location.href = '../my_drivers.php';
            </script>";
            
        } catch (PDOException $e) {
            error_log("Toggle driver error: " . $e->getMessage());
            echo "<script>
                alert('❌ Error toggling driver status: " . addslashes($e->getMessage()) . "');
                window.location.href = '../my_drivers.php';
            </script>";
        }
        break;
        
    default:
        echo "<script>
            alert('Invalid action!');
            window.location.href = '../my_drivers.php';
        </script>";
        break;
}
?>