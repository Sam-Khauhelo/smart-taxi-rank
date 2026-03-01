<?php
// admin/process/process_owner.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireRole('admin');

// Handle different actions
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'add':
        // Handle add owner
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo "<script>
                alert('Invalid request method!');
                window.location.href = '../manage_owners.php';
            </script>";
            exit();
        }
        
        $full_name = trim($_POST['full_name'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $id_number = trim($_POST['id_number'] ?? '');
        $bank_name = trim($_POST['bank_name'] ?? '');
        $account_number = trim($_POST['account_number'] ?? '');
        $branch_code = trim($_POST['branch_code'] ?? '');
        $is_active = $_POST['is_active'] ?? 1;
        
        // Validation
        $errors = [];
        if (empty($full_name)) $errors[] = "Full name is required";
        if (empty($phone_number)) $errors[] = "Phone number is required";
        if (empty($username)) $errors[] = "Username is required";
        if (empty($password)) $errors[] = "Password is required";
        if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
        if (empty($id_number)) $errors[] = "ID number is required";
        
        if (!empty($errors)) {
            $error_msg = implode("\\n", $errors);
            echo "<script>
                alert('Validation Errors:\\n{$error_msg}');
                window.location.href = '../manage_owners.php';
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
                    window.location.href = '../manage_owners.php';
                </script>";
                exit();
            }
            
            // Start transaction
            $pdo->beginTransaction();
            
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert into users table
            $user_sql = "INSERT INTO users (username, password, full_name, phone_number, role, is_active, created_at) 
                         VALUES (:username, :password, :full_name, :phone, 'owner', :is_active, NOW())";
            $user_stmt = $pdo->prepare($user_sql);
            $user_stmt->execute([
                ':username' => $username,
                ':password' => $hashed_password,
                ':full_name' => $full_name,
                ':phone' => $phone_number,
                ':is_active' => $is_active
            ]);
            
            $user_id = $pdo->lastInsertId();
            
            // Insert into owners table
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
            
            $pdo->commit();
            
            echo "<script>
                alert('✅ Success!\\n\\nOwner \"{$full_name}\" registered successfully!\\nUsername: {$username}');
                window.location.href = '../manage_owners.php';
            </script>";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Add owner error: " . $e->getMessage());
            echo "<script>
                alert('❌ Error adding owner: " . addslashes($e->getMessage()) . "');
                window.location.href = '../manage_owners.php';
            </script>";
        }
        break;
        
    case 'edit':
        // Handle edit owner
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo "<script>
                alert('Invalid request method!');
                window.location.href = '../manage_owners.php';
            </script>";
            exit();
        }
        
        $owner_id = $_POST['owner_id'] ?? 0;
        $user_id = $_POST['user_id'] ?? 0;
        $full_name = trim($_POST['full_name'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? '');
        $password = $_POST['password'] ?? '';
        $id_number = trim($_POST['id_number'] ?? '');
        $bank_name = trim($_POST['bank_name'] ?? '');
        $account_number = trim($_POST['account_number'] ?? '');
        $branch_code = trim($_POST['branch_code'] ?? '');
        $is_active = $_POST['is_active'] ?? 1;
        
        // Validation
        $errors = [];
        if (empty($owner_id)) $errors[] = "Owner ID is missing";
        if (empty($user_id)) $errors[] = "User ID is missing";
        if (empty($full_name)) $errors[] = "Full name is required";
        if (empty($phone_number)) $errors[] = "Phone number is required";
        if (empty($id_number)) $errors[] = "ID number is required";
        
        if (!empty($errors)) {
            $error_msg = implode("\\n", $errors);
            echo "<script>
                alert('Validation Errors:\\n{$error_msg}');
                window.location.href = '../manage_owners.php';
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
            
            // Update owners table
            $owner_sql = "UPDATE owners SET id_number = :id_number, bank_name = :bank_name,
                         account_number = :account_number, branch_code = :branch_code
                         WHERE id = :id";
            $owner_stmt = $pdo->prepare($owner_sql);
            $owner_stmt->execute([
                ':id_number' => $id_number,
                ':bank_name' => $bank_name,
                ':account_number' => $account_number,
                ':branch_code' => $branch_code,
                ':id' => $owner_id
            ]);
            
            $pdo->commit();
            
            echo "<script>
                alert('✅ Success!\\n\\nOwner \"{$full_name}\" updated successfully!');
                window.location.href = '../manage_owners.php';
            </script>";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Edit owner error: " . $e->getMessage());
            echo "<script>
                alert('❌ Error updating owner: " . addslashes($e->getMessage()) . "');
                window.location.href = '../manage_owners.php';
            </script>";
        }
        break;
        
    case 'delete':
        // Handle delete owner
        $owner_id = $_GET['id'] ?? 0;
        
        if (empty($owner_id)) {
            echo "<script>
                alert('Error: Owner ID missing!');
                window.location.href = '../manage_owners.php';
            </script>";
            exit();
        }
        
        try {
            // Get user_id before deleting
            $user_sql = "SELECT user_id FROM owners WHERE id = :id";
            $user_stmt = $pdo->prepare($user_sql);
            $user_stmt->execute([':id' => $owner_id]);
            $owner = $user_stmt->fetch();
            
            if (!$owner) {
                echo "<script>
                    alert('Error: Owner not found!');
                    window.location.href = '../manage_owners.php';
                </script>";
                exit();
            }
            
            // Start transaction
            $pdo->beginTransaction();
            
            // Delete from owners table (foreign key will cascade)
            $delete_owner_sql = "DELETE FROM owners WHERE id = :id";
            $delete_owner_stmt = $pdo->prepare($delete_owner_sql);
            $delete_owner_stmt->execute([':id' => $owner_id]);
            
            // Delete from users table
            $delete_user_sql = "DELETE FROM users WHERE id = :id";
            $delete_user_stmt = $pdo->prepare($delete_user_sql);
            $delete_user_stmt->execute([':id' => $owner['user_id']]);
            
            $pdo->commit();
            
            echo "<script>
                alert('✅ Owner deleted successfully!');
                window.location.href = '../manage_owners.php';
            </script>";
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Delete owner error: " . $e->getMessage());
            
            // Check if foreign key constraint failed
            if ($e->errorInfo[1] == 1451) {
                echo "<script>
                    alert('❌ Cannot delete owner because they have taxis or drivers.\\n\\nPlease delete all taxis and drivers first.');
                    window.location.href = '../manage_owners.php';
                </script>";
            } else {
                echo "<script>
                    alert('❌ Error deleting owner: " . addslashes($e->getMessage()) . "');
                    window.location.href = '../manage_owners.php';
                </script>";
            }
        }
        break;
        
    case 'toggle':
        // Handle toggle owner status
        $owner_id = $_GET['id'] ?? 0;
        $status = $_GET['status'] ?? 0;
        
        if (empty($owner_id)) {
            echo "<script>
                alert('Error: Owner ID missing!');
                window.location.href = '../manage_owners.php';
            </script>";
            exit();
        }
        
        try {
            // Get user_id
            $user_sql = "SELECT user_id FROM owners WHERE id = :id";
            $user_stmt = $pdo->prepare($user_sql);
            $user_stmt->execute([':id' => $owner_id]);
            $owner = $user_stmt->fetch();
            
            if (!$owner) {
                echo "<script>
                    alert('Error: Owner not found!');
                    window.location.href = '../manage_owners.php';
                </script>";
                exit();
            }
            
            // Update user status
            $sql = "UPDATE users SET is_active = :status WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':status' => $status,
                ':id' => $owner['user_id']
            ]);
            
            $action = $status ? 'activated' : 'deactivated';
            echo "<script>
                alert('✅ Owner {$action} successfully!');
                window.location.href = '../manage_owners.php';
            </script>";
            
        } catch (PDOException $e) {
            error_log("Toggle owner error: " . $e->getMessage());
            echo "<script>
                alert('❌ Error toggling owner status: " . addslashes($e->getMessage()) . "');
                window.location.href = '../manage_owners.php';
            </script>";
        }
        break;
        
    default:
        echo "<script>
            alert('Invalid action!');
            window.location.href = '../manage_owners.php';
        </script>";
        break;
}
?>