<?php
// admin/process/process_taxi.php
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
        // Handle add taxi
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo "<script>
                alert('Invalid request method!');
                window.location.href = '../manage_taxis.php';
            </script>";
            exit();
        }
        
        $registration_number = trim($_POST['registration_number'] ?? '');
        $owner_id = $_POST['owner_id'] ?? '';
        $route_id = $_POST['route_id'] ?? null;
        $status = $_POST['status'] ?? 'active';
        $added_by = $_POST['added_by'] ?? $admin_name;
        
        // Validation
        $errors = [];
        if (empty($registration_number)) {
            $errors[] = "Registration number is required";
        }
        if (empty($owner_id)) {
            $errors[] = "Please select an owner";
        }
        
        // Validate registration number format (simple check)
        if (!empty($registration_number) && !preg_match('/^[A-Z]{2,3}\s?[\d-]+\s?[\d]*$/', $registration_number)) {
            $errors[] = "Registration number format is invalid. Use format like 'ZN 123-456'";
        }
        
        if (!empty($errors)) {
            $error_msg = implode("\\n", $errors);
            echo "<script>
                alert('Validation Errors:\\n{$error_msg}');
                window.location.href = '../manage_taxis.php';
            </script>";
            exit();
        }
        
        try {
            // Check if registration number already exists
            $check_sql = "SELECT id FROM taxis WHERE registration_number = :reg";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([':reg' => $registration_number]);
            
            if ($check_stmt->rowCount() > 0) {
                echo "<script>
                    alert('Error: Registration number already exists!');
                    window.location.href = '../manage_taxis.php';
                </script>";
                exit();
            }
            
            // Insert into taxis table with added_by
            $sql = "INSERT INTO taxis (registration_number, owner_id, route_id, status, added_by, added_at) 
                    VALUES (:reg, :owner_id, :route_id, :status, :added_by, NOW())";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':reg' => $registration_number,
                ':owner_id' => $owner_id,
                ':route_id' => empty($route_id) ? null : $route_id,
                ':status' => $status,
                ':added_by' => $added_by
            ]);
            
            echo "<script>
                alert('✅ Success!\\n\\nTaxi \"{$registration_number}\" added successfully!\\nAdded by: {$added_by}');
                window.location.href = '../manage_taxis.php';
            </script>";
            
        } catch (PDOException $e) {
            error_log("Add taxi error: " . $e->getMessage());
            echo "<script>
                alert('❌ Error adding taxi: " . addslashes($e->getMessage()) . "');
                window.location.href = '../manage_taxis.php';
            </script>";
        }
        break;
        
    case 'edit':
        // Handle edit taxi
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo "<script>
                alert('Invalid request method!');
                window.location.href = '../manage_taxis.php';
            </script>";
            exit();
        }
        
        $taxi_id = $_POST['taxi_id'] ?? 0;
        $registration_number = trim($_POST['registration_number'] ?? '');
        $owner_id = $_POST['owner_id'] ?? '';
        $route_id = $_POST['route_id'] ?? null;
        $status = $_POST['status'] ?? 'active';
        
        // Validation
        $errors = [];
        if (empty($taxi_id)) $errors[] = "Taxi ID is missing";
        if (empty($registration_number)) $errors[] = "Registration number is required";
        if (empty($owner_id)) $errors[] = "Please select an owner";
        
        if (!empty($errors)) {
            $error_msg = implode("\\n", $errors);
            echo "<script>
                alert('Validation Errors:\\n{$error_msg}');
                window.location.href = '../manage_taxis.php';
            </script>";
            exit();
        }
        
        try {
            // Check if registration number exists for other taxis
            $check_sql = "SELECT id FROM taxis WHERE registration_number = :reg AND id != :id";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([
                ':reg' => $registration_number,
                ':id' => $taxi_id
            ]);
            
            if ($check_stmt->rowCount() > 0) {
                echo "<script>
                    alert('Error: Registration number already exists for another taxi!');
                    window.location.href = '../manage_taxis.php';
                </script>";
                exit();
            }
            
            // Update taxi
            $sql = "UPDATE taxis SET registration_number = :reg, owner_id = :owner_id, 
                    route_id = :route_id, status = :status WHERE id = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':reg' => $registration_number,
                ':owner_id' => $owner_id,
                ':route_id' => empty($route_id) ? null : $route_id,
                ':status' => $status,
                ':id' => $taxi_id
            ]);
            
            echo "<script>
                alert('✅ Success!\\n\\nTaxi \"{$registration_number}\" updated successfully!');
                window.location.href = '../manage_taxis.php';
            </script>";
            
        } catch (PDOException $e) {
            error_log("Edit taxi error: " . $e->getMessage());
            echo "<script>
                alert('❌ Error updating taxi: " . addslashes($e->getMessage()) . "');
                window.location.href = '../manage_taxis.php';
            </script>";
        }
        break;
        
    case 'delete':
        // Handle delete taxi
        $taxi_id = $_GET['id'] ?? 0;
        
        if (empty($taxi_id)) {
            echo "<script>
                alert('Error: Taxi ID missing!');
                window.location.href = '../manage_taxis.php';
            </script>";
            exit();
        }
        
        try {
            // Check if taxi has trips
            $check_trips_sql = "SELECT COUNT(*) as count FROM trips WHERE taxi_id = :id";
            $check_trips_stmt = $pdo->prepare($check_trips_sql);
            $check_trips_stmt->execute([':id' => $taxi_id]);
            $trips = $check_trips_stmt->fetch();
            
            if ($trips['count'] > 0) {
                echo "<script>
                    alert('❌ Cannot delete taxi! It has {$trips['count']} trip(s) recorded.\\n\\nYou can change its status to \"Off Rank\" instead.');
                    window.location.href = '../manage_taxis.php';
                </script>";
                exit();
            }
            
            // Check if taxi is assigned to a driver
            $check_driver_sql = "SELECT COUNT(*) as count FROM drivers WHERE taxi_id = :id";
            $check_driver_stmt = $pdo->prepare($check_driver_sql);
            $check_driver_stmt->execute([':id' => $taxi_id]);
            $drivers = $check_driver_stmt->fetch();
            
            if ($drivers['count'] > 0) {
                echo "<script>
                    alert('❌ Cannot delete taxi! It is assigned to a driver.\\n\\nPlease unassign the driver first.');
                    window.location.href = '../manage_taxis.php';
                </script>";
                exit();
            }
            
            // Check if taxi is in queue
            $check_queue_sql = "SELECT COUNT(*) as count FROM queue WHERE taxi_id = :id";
            $check_queue_stmt = $pdo->prepare($check_queue_sql);
            $check_queue_stmt->execute([':id' => $taxi_id]);
            $queue = $check_queue_stmt->fetch();
            
            if ($queue['count'] > 0) {
                echo "<script>
                    alert('❌ Cannot delete taxi! It is currently in the queue.\\n\\nPlease remove it from queue first.');
                    window.location.href = '../manage_taxis.php';
                </script>";
                exit();
            }
            
            // Delete taxi
            $sql = "DELETE FROM taxis WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $taxi_id]);
            
            echo "<script>
                alert('✅ Taxi deleted successfully!');
                window.location.href = '../manage_taxis.php';
            </script>";
            
        } catch (PDOException $e) {
            error_log("Delete taxi error: " . $e->getMessage());
            echo "<script>
                alert('❌ Error deleting taxi: " . addslashes($e->getMessage()) . "');
                window.location.href = '../manage_taxis.php';
            </script>";
        }
        break;
        
    case 'status':
        // Handle status update
        $taxi_id = $_GET['id'] ?? 0;
        $status = $_GET['status'] ?? '';
        
        $valid_statuses = ['active', 'on_trip', 'off_rank', 'maintenance'];
        
        if (empty($taxi_id) || !in_array($status, $valid_statuses)) {
            echo "<script>
                alert('Error: Invalid parameters!');
                window.location.href = '../manage_taxis.php';
            </script>";
            exit();
        }
        
        try {
            $sql = "UPDATE taxis SET status = :status WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':status' => $status,
                ':id' => $taxi_id
            ]);
            
            echo "<script>
                alert('✅ Taxi status updated to \"{$status}\" successfully!');
                window.location.href = '../manage_taxis.php';
            </script>";
            
        } catch (PDOException $e) {
            error_log("Status update error: " . $e->getMessage());
            echo "<script>
                alert('❌ Error updating status: " . addslashes($e->getMessage()) . "');
                window.location.href = '../manage_taxis.php';
            </script>";
        }
        break;
        
    default:
        echo "<script>
            alert('Invalid action!');
            window.location.href = '../manage_taxis.php';
        </script>";
        break;
}
?>