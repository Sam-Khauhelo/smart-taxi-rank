<?php
// owner/process/process_taxi.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireRole('owner');

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'add':
        // Handle add taxi
        $registration_number = trim($_POST['registration_number'] ?? '');
        $owner_id = $_POST['owner_id'] ?? $_SESSION['owner_id'];
        $route_id = $_POST['route_id'] ?? null;
        $status = $_POST['status'] ?? 'active';
        
        // Validation
        if (empty($registration_number)) {
            echo "<script>
                alert('Registration number is required!');
                window.location.href = '../my_taxis.php';
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
                    alert('Registration number already exists!');
                    window.location.href = '../my_taxis.php';
                </script>";
                exit();
            }
            
            // Insert taxi
            $sql = "INSERT INTO taxis (registration_number, owner_id, route_id, status, added_by, added_at) 
                    VALUES (:reg, :owner_id, :route_id, :status, :added_by, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':reg' => $registration_number,
                ':owner_id' => $owner_id,
                ':route_id' => $route_id,
                ':status' => $status,
                ':added_by' => $_SESSION['full_name']
            ]);
            
            echo "<script>
                alert('✅ Taxi registered successfully!');
                window.location.href = '../my_taxis.php';
            </script>";
            
        } catch (PDOException $e) {
            error_log("Add taxi error: " . $e->getMessage());
            echo "<script>
                alert('❌ Error registering taxi: " . addslashes($e->getMessage()) . "');
                window.location.href = '../my_taxis.php';
            </script>";
        }
        break;
        
    case 'edit':
        // Handle edit taxi
        $taxi_id = $_POST['taxi_id'] ?? 0;
        $registration_number = trim($_POST['registration_number'] ?? '');
        $route_id = $_POST['route_id'] ?? null;
        $status = $_POST['status'] ?? 'active';
        
        if (empty($taxi_id)) {
            echo "<script>
                alert('Taxi ID missing!');
                window.location.href = '../my_taxis.php';
            </script>";
            exit();
        }
        
        if (empty($registration_number)) {
            echo "<script>
                alert('Registration number is required!');
                window.location.href = '../my_taxis.php';
            </script>";
            exit();
        }
        
        try {
            // Check if registration exists for other taxis
            $check_sql = "SELECT id FROM taxis WHERE registration_number = :reg AND id != :id";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([
                ':reg' => $registration_number,
                ':id' => $taxi_id
            ]);
            
            if ($check_stmt->rowCount() > 0) {
                echo "<script>
                    alert('Registration number already exists for another taxi!');
                    window.location.href = '../my_taxis.php';
                </script>";
                exit();
            }
            
            // Update taxi
            $sql = "UPDATE taxis SET registration_number = :reg, route_id = :route_id, status = :status 
                    WHERE id = :id AND owner_id = :owner_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':reg' => $registration_number,
                ':route_id' => $route_id,
                ':status' => $status,
                ':id' => $taxi_id,
                ':owner_id' => $_SESSION['owner_id']
            ]);
            
            echo "<script>
                alert('✅ Taxi updated successfully!');
                window.location.href = '../my_taxis.php';
            </script>";
            
        } catch (PDOException $e) {
            error_log("Edit taxi error: " . $e->getMessage());
            echo "<script>
                alert('❌ Error updating taxi: " . addslashes($e->getMessage()) . "');
                window.location.href = '../my_taxis.php';
            </script>";
        }
        break;
        
    case 'assign_driver':
        // Handle assign driver
        $taxi_id = $_POST['taxi_id'] ?? 0;
        $driver_id = $_POST['driver_id'] ?? 0;
        
        if (empty($taxi_id) || empty($driver_id)) {
            echo "<script>
                alert('Taxi and driver are required!');
                window.location.href = '../my_taxis.php';
            </script>";
            exit();
        }
        
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Get current driver assigned to this taxi (if any)
            $current_sql = "SELECT id FROM drivers WHERE taxi_id = :taxi_id";
            $current_stmt = $pdo->prepare($current_sql);
            $current_stmt->execute([':taxi_id' => $taxi_id]);
            $current_driver = $current_stmt->fetch();
            
            if ($current_driver) {
                // Unassign current driver
                $unassign_sql = "UPDATE drivers SET taxi_id = NULL WHERE id = :id";
                $unassign_stmt = $pdo->prepare($unassign_sql);
                $unassign_stmt->execute([':id' => $current_driver['id']]);
            }
            
            // Check if driver is already assigned to another taxi
            $check_sql = "SELECT taxi_id FROM drivers WHERE id = :id";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([':id' => $driver_id]);
            $driver = $check_stmt->fetch();
            
            if ($driver['taxi_id']) {
                // Unassign from old taxi
                $unassign_old_sql = "UPDATE drivers SET taxi_id = NULL WHERE id = :id";
                $unassign_old_stmt = $pdo->prepare($unassign_old_sql);
                $unassign_old_stmt->execute([':id' => $driver_id]);
            }
            
            // Assign new driver
            $assign_sql = "UPDATE drivers SET taxi_id = :taxi_id WHERE id = :id";
            $assign_stmt = $pdo->prepare($assign_sql);
            $assign_stmt->execute([
                ':taxi_id' => $taxi_id,
                ':id' => $driver_id
            ]);
            
            $pdo->commit();
            
            echo "<script>
                alert('✅ Driver assigned successfully!');
                window.location.href = '../my_taxis.php';
            </script>";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Assign driver error: " . $e->getMessage());
            echo "<script>
                alert('❌ Error assigning driver: " . addslashes($e->getMessage()) . "');
                window.location.href = '../my_taxis.php';
            </script>";
        }
        break;
        
    default:
        echo "<script>
            alert('Invalid action!');
            window.location.href = '../my_taxis.php';
        </script>";
        break;
}
?>