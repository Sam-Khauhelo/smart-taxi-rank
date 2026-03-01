<?php
// admin/process/process_driver.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireRole('admin');

// Get admin's name for added_by
$admin_name = $_SESSION['full_name'];

// Handle different actions
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'add':
        // Handle add driver
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo "<script>
                alert('Invalid request method!');
                window.location.href = '../manage_drivers.php';
            </script>";
            exit();
        }
        
        $full_name = trim($_POST['full_name'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $driver_id_number = trim($_POST['driver_id_number'] ?? '');
        $license_expiry = $_POST['license_expiry'] ?? '';
        $owner_id = $_POST['owner_id'] ?? '';
        $taxi_id = $_POST['taxi_id'] ?? null;
        $employment_type = $_POST['employment_type'] ?? '';
        $payment_rate = $_POST['payment_rate'] ?? 0;
        $is_active = $_POST['is_active'] ?? 1;
        $added_by = $_POST['added_by'] ?? $admin_name;
        
        // Validation
        $errors = [];
        if (empty($full_name)) $errors[] = "Full name is required";
        if (empty($phone_number)) $errors[] = "Phone number is required";
        if (empty($username)) $errors[] = "Username is required";
        if (empty($password)) $errors[] = "Password is required";
        if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
        if (empty($driver_id_number)) $errors[] = "ID number is required";
        if (empty($license_expiry)) $errors[] = "License expiry date is required";
        if (empty($owner_id)) $errors[] = "Please select an owner";
        if (empty($employment_type)) $errors[] = "Employment type is required";
        if (empty($payment_rate) || $payment_rate <= 0) $errors[] = "Valid payment rate is required";
        
        if (!empty($errors)) {
            $error_msg = implode("\\n", $errors);
            echo "<script>
                alert('Validation Errors:\\n{$error_msg}');
                window.location.href = '../manage_drivers.php';
            </script>";
            exit();
        }
        
        try {
            // Check if username or phone already exists
            $check_sql = "SELECT id FROM users WHERE username = :username OR phone_number = :phone";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([
                ':username' => $username,
                ':phone' => $phone_number
            ]);
            
            if ($check_stmt->rowCount() > 0) {
                echo "<script>
                    alert('Error: Username or phone number already exists!');
                    window.location.href = '../manage_drivers.php';
                </script>";
                exit();
            }
            
            // Check if taxi is already assigned to another driver
            if (!empty($taxi_id)) {
                $check_taxi_sql = "SELECT id FROM drivers WHERE taxi_id = :taxi_id";
                $check_taxi_stmt = $pdo->prepare($check_taxi_sql);
                $check_taxi_stmt->execute([':taxi_id' => $taxi_id]);
                
                if ($check_taxi_stmt->rowCount() > 0) {
                    echo "<script>
                        alert('Error: This taxi is already assigned to another driver!');
                        window.location.href = '../manage_drivers.php';
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
                         VALUES (:username, :password, :full_name, :phone, 'driver', :is_active, NOW())";
            $user_stmt = $pdo->prepare($user_sql);
            $user_stmt->execute([
                ':username' => $username,
                ':password' => $hashed_password,
                ':full_name' => $full_name,
                ':phone' => $phone_number,
                ':is_active' => $is_active
            ]);
            
            $user_id = $pdo->lastInsertId();
            
            // Insert into drivers table with added_by
            $driver_sql = "INSERT INTO drivers (user_id, owner_id, taxi_id, id_number, license_expiry_date, 
                           employment_type, payment_rate, added_by, added_at) 
                           VALUES (:user_id, :owner_id, :taxi_id, :id_number, :license_expiry, 
                           :employment_type, :payment_rate, :added_by, NOW())";
            $driver_stmt = $pdo->prepare($driver_sql);
            $driver_stmt->execute([
                ':user_id' => $user_id,
                ':owner_id' => $owner_id,
                ':taxi_id' => empty($taxi_id) ? null : $taxi_id,
                ':id_number' => $driver_id_number,
                ':license_expiry' => $license_expiry,
                ':employment_type' => $employment_type,
                ':payment_rate' => $payment_rate,
                ':added_by' => $added_by
            ]);
            
            // If taxi assigned, update taxi status
            if (!empty($taxi_id)) {
                $update_taxi_sql = "UPDATE taxis SET status = 'active' WHERE id = :id";
                $update_taxi_stmt = $pdo->prepare($update_taxi_sql);
                $update_taxi_stmt->execute([':id' => $taxi_id]);
            }
            
            $pdo->commit();
            
            echo "<script>
                alert('✅ Success!\\n\\nDriver \"{$full_name}\" registered successfully!\\nUsername: {$username}\\nAdded by: {$added_by}');
                window.location.href = '../manage_drivers.php';
            </script>";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Add driver error: " . $e->getMessage());
            echo "<script>
                alert('❌ Error adding driver: " . addslashes($e->getMessage()) . "');
                window.location.href = '../manage_drivers.php';
            </script>";
        }
        break;
        
    case 'edit':
        // Handle edit driver
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo "<script>
                alert('Invalid request method!');
                window.location.href = '../manage_drivers.php';
            </script>";
            exit();
        }
        
        $driver_id = $_POST['driver_id'] ?? 0;
        $user_id = $_POST['user_id'] ?? 0;
        $full_name = trim($_POST['full_name'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? '');
        $password = $_POST['password'] ?? '';
        $driver_id_number = trim($_POST['driver_id_number'] ?? '');
        $license_expiry = $_POST['license_expiry'] ?? '';
        $owner_id = $_POST['owner_id'] ?? '';
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
        if (empty($driver_id_number)) $errors[] = "ID number is required";
        if (empty($license_expiry)) $errors[] = "License expiry date is required";
        if (empty($owner_id)) $errors[] = "Please select an owner";
        if (empty($employment_type)) $errors[] = "Employment type is required";
        if (empty($payment_rate) || $payment_rate <= 0) $errors[] = "Valid payment rate is required";
        
        if (!empty($errors)) {
            $error_msg = implode("\\n", $errors);
            echo "<script>
                alert('Validation Errors:\\n{$error_msg}');
                window.location.href = '../manage_drivers.php';
            </script>";
            exit();
        }
        
        try {
            // Check if taxi is already assigned to another driver (excluding current)
            if (!empty($taxi_id)) {
                $check_taxi_sql = "SELECT id FROM drivers WHERE taxi_id = :taxi_id AND id != :driver_id";
                $check_taxi_stmt = $pdo->prepare($check_taxi_sql);
                $check_taxi_stmt->execute([
                    ':taxi_id' => $taxi_id,
                    ':driver_id' => $driver_id
                ]);
                
                if ($check_taxi_stmt->rowCount() > 0) {
                    echo "<script>
                        alert('Error: This taxi is already assigned to another driver!');
                        window.location.href = '../manage_drivers.php';
                    </script>";
                    exit();
                }
            }
            
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
            
            // Get current taxi assignment
            $old_taxi_sql = "SELECT taxi_id FROM drivers WHERE id = :id";
            $old_taxi_stmt = $pdo->prepare($old_taxi_sql);
            $old_taxi_stmt->execute([':id' => $driver_id]);
            $old_taxi = $old_taxi_stmt->fetch();
            
            // Update drivers table
            $driver_sql = "UPDATE drivers SET owner_id = :owner_id, taxi_id = :taxi_id, 
                          id_number = :id_number, license_expiry_date = :license_expiry,
                          employment_type = :employment_type, payment_rate = :payment_rate
                          WHERE id = :id";
            $driver_stmt = $pdo->prepare($driver_sql);
            $driver_stmt->execute([
                ':owner_id' => $owner_id,
                ':taxi_id' => empty($taxi_id) ? null : $taxi_id,
                ':id_number' => $driver_id_number,
                ':license_expiry' => $license_expiry,
                ':employment_type' => $employment_type,
                ':payment_rate' => $payment_rate,
                ':id' => $driver_id
            ]);
            
            // Update taxi statuses
            if (!empty($old_taxi['taxi_id']) && $old_taxi['taxi_id'] != $taxi_id) {
                // Old taxi is now free
                $update_old_sql = "UPDATE taxis SET status = 'active' WHERE id = :id";
                $update_old_stmt = $pdo->prepare($update_old_sql);
                $update_old_stmt->execute([':id' => $old_taxi['taxi_id']]);
            }
            
            if (!empty($taxi_id) && $taxi_id != $old_taxi['taxi_id']) {
                // New taxi is now assigned
                $update_new_sql = "UPDATE taxis SET status = 'active' WHERE id = :id";
                $update_new_stmt = $pdo->prepare($update_new_sql);
                $update_new_stmt->execute([':id' => $taxi_id]);
            }
            
            $pdo->commit();
            
            echo "<script>
                alert('✅ Success!\\n\\nDriver \"{$full_name}\" updated successfully!');
                window.location.href = '../manage_drivers.php';
            </script>";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Edit driver error: " . $e->getMessage());
            echo "<script>
                alert('❌ Error updating driver: " . addslashes($e->getMessage()) . "');
                window.location.href = '../manage_drivers.php';
            </script>";
        }
        break;
        
    case 'delete':
        // Handle delete driver
        $driver_id = $_GET['id'] ?? 0;
        
        if (empty($driver_id)) {
            echo "<script>
                alert('Error: Driver ID missing!');
                window.location.href = '../manage_drivers.php';
            </script>";
            exit();
        }
        
        try {
            // Get user_id and taxi_id before deleting
            $info_sql = "SELECT user_id, taxi_id FROM drivers WHERE id = :id";
            $info_stmt = $pdo->prepare($info_sql);
            $info_stmt->execute([':id' => $driver_id]);
            $driver = $info_stmt->fetch();
            
            if (!$driver) {
                echo "<script>
                    alert('Error: Driver not found!');
                    window.location.href = '../manage_drivers.php';
                </script>";
                exit();
            }
            
            // Start transaction
            $pdo->beginTransaction();
            
            // Free up taxi if assigned
            if (!empty($driver['taxi_id'])) {
                $update_taxi_sql = "UPDATE taxis SET status = 'active' WHERE id = :id";
                $update_taxi_stmt = $pdo->prepare($update_taxi_sql);
                $update_taxi_stmt->execute([':id' => $driver['taxi_id']]);
            }
            
            // Delete from drivers table
            $delete_driver_sql = "DELETE FROM drivers WHERE id = :id";
            $delete_driver_stmt = $pdo->prepare($delete_driver_sql);
            $delete_driver_stmt->execute([':id' => $driver_id]);
            
            // Delete from users table
            $delete_user_sql = "DELETE FROM users WHERE id = :id";
            $delete_user_stmt = $pdo->prepare($delete_user_sql);
            $delete_user_stmt->execute([':id' => $driver['user_id']]);
            
            $pdo->commit();
            
            echo "<script>
                alert('✅ Driver deleted successfully!');
                window.location.href = '../manage_drivers.php';
            </script>";
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Delete driver error: " . $e->getMessage());
            echo "<script>
                alert('❌ Error deleting driver: " . addslashes($e->getMessage()) . "');
                window.location.href = '../manage_drivers.php';
            </script>";
        }
        break;
        
    case 'toggle':
        // Handle toggle driver status
        $driver_id = $_GET['id'] ?? 0;
        $status = $_GET['status'] ?? 0;
        
        if (empty($driver_id)) {
            echo "<script>
                alert('Error: Driver ID missing!');
                window.location.href = '../manage_drivers.php';
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
                    alert('Error: Driver not found!');
                    window.location.href = '../manage_drivers.php';
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
                window.location.href = '../manage_drivers.php';
            </script>";
            
        } catch (PDOException $e) {
            error_log("Toggle driver error: " . $e->getMessage());
            echo "<script>
                alert('❌ Error toggling driver status: " . addslashes($e->getMessage()) . "');
                window.location.href = '../manage_drivers.php';
            </script>";
        }
        break;
        
    default:
        echo "<script>
            alert('Invalid action!');
            window.location.href = '../manage_drivers.php';
        </script>";
        break;
}
?>