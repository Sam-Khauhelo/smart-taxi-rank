<?php
// owner/process/process_profile.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireRole('owner');

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'update_profile':
        // Handle profile update
        $user_id = $_POST['user_id'] ?? $_SESSION['user_id'];
        $owner_id = $_POST['owner_id'] ?? $_SESSION['owner_id'];
        $full_name = trim($_POST['full_name'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $id_number = trim($_POST['id_number'] ?? '');
        $bank_name = trim($_POST['bank_name'] ?? '');
        $account_number = trim($_POST['account_number'] ?? '');
        $branch_code = trim($_POST['branch_code'] ?? '');
        
        // Validation
        if (empty($full_name)) {
            echo "<script>
                alert('❌ Full name is required!');
                window.location.href = '../profile.php';
            </script>";
            exit();
        }
        
        if (empty($email)) {
            echo "<script>
                alert('❌ Email address is required!');
                window.location.href = '../profile.php';
            </script>";
            exit();
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo "<script>
                alert('❌ Please enter a valid email address!');
                window.location.href = '../profile.php';
            </script>";
            exit();
        }
        
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Check if email already exists for another user
            $check_sql = "SELECT id FROM users WHERE username = :email AND id != :id";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([
                ':email' => $email,
                ':id' => $user_id
            ]);
            
            if ($check_stmt->rowCount() > 0) {
                echo "<script>
                    alert('❌ This email is already in use by another account!');
                    window.location.href = '../profile.php';
                </script>";
                exit();
            }
            
            // Update users table
            $user_sql = "UPDATE users SET full_name = :full_name, username = :email, phone_number = :phone 
                        WHERE id = :id";
            $user_stmt = $pdo->prepare($user_sql);
            $user_stmt->execute([
                ':full_name' => $full_name,
                ':email' => $email,
                ':phone' => $phone_number,
                ':id' => $user_id
            ]);
            
            // Update session
            $_SESSION['full_name'] = $full_name;
            $_SESSION['username'] = $email;
            
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
            
            $message = "✅ Profile updated successfully!";
            if (!empty($bank_name) && !empty($account_number)) {
                $message .= "\\n\\nBanking details have been saved.";
            }
            
            echo "<script>
                alert('{$message}');
                window.location.href = '../profile.php';
            </script>";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Profile update error: " . $e->getMessage());
            echo "<script>
                alert('❌ Error updating profile: " . addslashes($e->getMessage()) . "');
                window.location.href = '../profile.php';
            </script>";
        }
        break;
        
    case 'change_password':
        // Handle password change
        $user_id = $_POST['user_id'] ?? $_SESSION['user_id'];
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            echo "<script>
                alert('❌ All password fields are required!');
                window.location.href = '../profile.php';
            </script>";
            exit();
        }
        
        if ($new_password !== $confirm_password) {
            echo "<script>
                alert('❌ New passwords do not match!');
                window.location.href = '../profile.php';
            </script>";
            exit();
        }
        
        if (strlen($new_password) < 6) {
            echo "<script>
                alert('❌ New password must be at least 6 characters long!');
                window.location.href = '../profile.php';
            </script>";
            exit();
        }
        
        try {
            // Verify current password
            $sql = "SELECT password FROM users WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $user_id]);
            $user = $stmt->fetch();
            
            if (!password_verify($current_password, $user['password'])) {
                echo "<script>
                    alert('❌ Current password is incorrect!');
                    window.location.href = '../profile.php';
                </script>";
                exit();
            }
            
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $sql = "UPDATE users SET password = :password WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':password' => $hashed_password,
                ':id' => $user_id
            ]);
            
            echo "<script>
                alert('✅ Password changed successfully!\\n\\nPlease use your new password next login.');
                window.location.href = '../profile.php';
            </script>";
            
        } catch (Exception $e) {
            error_log("Password change error: " . $e->getMessage());
            echo "<script>
                alert('❌ Error changing password: " . addslashes($e->getMessage()) . "');
                window.location.href = '../profile.php';
            </script>";
        }
        break;
        
    case 'upload_image':
        // Handle image upload
        $user_id = $_POST['user_id'] ?? $_SESSION['user_id'];
        
        if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
            echo "<script>
                alert('❌ Please select an image to upload!');
                window.location.href = '../profile.php';
            </script>";
            exit();
        }
        
        $file = $_FILES['profile_image'];
        
        // Validate file size (2MB max)
        if ($file['size'] > 2 * 1024 * 1024) {
            echo "<script>
                alert('❌ File size must be less than 2MB!');
                window.location.href = '../profile.php';
            </script>";
            exit();
        }
        
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            echo "<script>
                alert('❌ Only JPG, PNG and GIF images are allowed!');
                window.location.href = '../profile.php';
            </script>";
            exit();
        }
        
        try {
            // Create upload directory if not exists
            $upload_dir = '../../assets/img/profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'owner_' . $user_id . '_' . time() . '.' . $extension;
            $filepath = $upload_dir . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Update database
                $sql = "UPDATE users SET profile_image = :image WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':image' => 'profiles/' . $filename,
                    ':id' => $user_id
                ]);
                
                echo "<script>
                    alert('✅ Profile image uploaded successfully!');
                    window.location.href = '../profile.php';
                </script>";
            } else {
                throw new Exception('Failed to move uploaded file');
            }
            
        } catch (Exception $e) {
            error_log("Image upload error: " . $e->getMessage());
            echo "<script>
                alert('❌ Error uploading image: " . addslashes($e->getMessage()) . "');
                window.location.href = '../profile.php';
            </script>";
        }
        break;
        
    default:
        echo "<script>
            alert('❌ Invalid action!');
            window.location.href = '../profile.php';
        </script>";
        break;
}
?>