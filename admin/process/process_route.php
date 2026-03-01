<?php
// admin/process/process_route.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireRole('admin');

// Handle different actions
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'add':
        // Handle add route
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo "<script>
                alert('Invalid request method!');
                window.location.href = '../manage_routes.php';
            </script>";
            exit();
        }
        
        $route_name = trim($_POST['route_name'] ?? '');
        $fare_amount = $_POST['fare_amount'] ?? 0;
        $association_levy = $_POST['association_levy'] ?? 0;
        $estimated_duration_minutes = $_POST['estimated_duration_minutes'] ?? null;
        $is_active = $_POST['is_active'] ?? 1;
        
        // Validation
        $errors = [];
        if (empty($route_name)) $errors[] = "Route name is required";
        if (empty($fare_amount) || $fare_amount <= 0) $errors[] = "Valid fare amount is required";
        
        if (!empty($errors)) {
            $error_msg = implode("\\n", $errors);
            echo "<script>
                alert('Validation Errors:\\n{$error_msg}');
                window.location.href = '../manage_routes.php';
            </script>";
            exit();
        }
        
        try {
            // Check if route already exists
            $check_sql = "SELECT id FROM routes WHERE route_name = :route_name";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([':route_name' => $route_name]);
            
            if ($check_stmt->rowCount() > 0) {
                echo "<script>
                    alert('Error: Route name already exists!');
                    window.location.href = '../manage_routes.php';
                </script>";
                exit();
            }
            
            // Insert new route
            $sql = "INSERT INTO routes (route_name, fare_amount, association_levy, estimated_duration_minutes, is_active) 
                    VALUES (:route_name, :fare_amount, :association_levy, :duration, :is_active)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':route_name' => $route_name,
                ':fare_amount' => $fare_amount,
                ':association_levy' => $association_levy,
                ':duration' => $estimated_duration_minutes,
                ':is_active' => $is_active
            ]);
            
            echo "<script>
                alert('✅ Success!\\n\\nRoute \"{$route_name}\" added successfully!');
                window.location.href = '../manage_routes.php';
            </script>";
            
        } catch (PDOException $e) {
            error_log("Add route error: " . $e->getMessage());
            echo "<script>
                alert('❌ Error adding route: " . addslashes($e->getMessage()) . "');
                window.location.href = '../manage_routes.php';
            </script>";
        }
        break;
        
    case 'edit':
        // Handle edit route
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo "<script>
                alert('Invalid request method!');
                window.location.href = '../manage_routes.php';
            </script>";
            exit();
        }
        
        $route_id = $_POST['route_id'] ?? 0;
        $route_name = trim($_POST['route_name'] ?? '');
        $fare_amount = $_POST['fare_amount'] ?? 0;
        $association_levy = $_POST['association_levy'] ?? 0;
        $estimated_duration_minutes = $_POST['estimated_duration_minutes'] ?? null;
        $is_active = $_POST['is_active'] ?? 1;
        
        // Validation
        $errors = [];
        if (empty($route_id)) $errors[] = "Route ID is missing";
        if (empty($route_name)) $errors[] = "Route name is required";
        if (empty($fare_amount) || $fare_amount <= 0) $errors[] = "Valid fare amount is required";
        
        if (!empty($errors)) {
            $error_msg = implode("\\n", $errors);
            echo "<script>
                alert('Validation Errors:\\n{$error_msg}');
                window.location.href = '../manage_routes.php';
            </script>";
            exit();
        }
        
        try {
            // Check if route name exists for other routes
            $check_sql = "SELECT id FROM routes WHERE route_name = :route_name AND id != :id";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([
                ':route_name' => $route_name,
                ':id' => $route_id
            ]);
            
            if ($check_stmt->rowCount() > 0) {
                echo "<script>
                    alert('Error: Route name already exists!');
                    window.location.href = '../manage_routes.php';
                </script>";
                exit();
            }
            
            // Update route
            $sql = "UPDATE routes 
                    SET route_name = :route_name, 
                        fare_amount = :fare_amount, 
                        association_levy = :association_levy,
                        estimated_duration_minutes = :duration, 
                        is_active = :is_active 
                    WHERE id = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':route_name' => $route_name,
                ':fare_amount' => $fare_amount,
                ':association_levy' => $association_levy,
                ':duration' => $estimated_duration_minutes,
                ':is_active' => $is_active,
                ':id' => $route_id
            ]);
            
            echo "<script>
                alert('✅ Success!\\n\\nRoute \"{$route_name}\" updated successfully!');
                window.location.href = '../manage_routes.php';
            </script>";
            
        } catch (PDOException $e) {
            error_log("Edit route error: " . $e->getMessage());
            echo "<script>
                alert('❌ Error updating route: " . addslashes($e->getMessage()) . "');
                window.location.href = '../manage_routes.php';
            </script>";
        }
        break;
        
    case 'delete':
        // Handle delete route
        $route_id = $_GET['id'] ?? 0;
        
        if (empty($route_id)) {
            echo "<script>
                alert('Error: Route ID missing!');
                window.location.href = '../manage_routes.php';
            </script>";
            exit();
        }
        
        try {
            // Check if route is being used by taxis
            $check_sql = "SELECT COUNT(*) as count FROM taxis WHERE route_id = :id";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([':id' => $route_id]);
            $result = $check_stmt->fetch();
            
            if ($result['count'] > 0) {
                echo "<script>
                    alert('❌ Cannot delete route! It is assigned to {$result['count']} taxi(s).\\n\\nDeactivate the route instead.');
                    window.location.href = '../manage_routes.php';
                </script>";
                exit();
            }
            
            // Delete route
            $sql = "DELETE FROM routes WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $route_id]);
            
            echo "<script>
                alert('✅ Route deleted successfully!');
                window.location.href = '../manage_routes.php';
            </script>";
            
        } catch (PDOException $e) {
            error_log("Delete route error: " . $e->getMessage());
            echo "<script>
                alert('❌ Error deleting route: " . addslashes($e->getMessage()) . "');
                window.location.href = '../manage_routes.php';
            </script>";
        }
        break;
        
    case 'toggle':
        // Handle toggle route status
        $route_id = $_GET['id'] ?? 0;
        $status = $_GET['status'] ?? 0;
        
        if (empty($route_id)) {
            echo "<script>
                alert('Error: Route ID missing!');
                window.location.href = '../manage_routes.php';
            </script>";
            exit();
        }
        
        try {
            $sql = "UPDATE routes SET is_active = :status WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':status' => $status,
                ':id' => $route_id
            ]);
            
            $action = $status ? 'activated' : 'deactivated';
            echo "<script>
                alert('✅ Route {$action} successfully!');
                window.location.href = '../manage_routes.php';
            </script>";
            
        } catch (PDOException $e) {
            error_log("Toggle route error: " . $e->getMessage());
            echo "<script>
                alert('❌ Error toggling route status: " . addslashes($e->getMessage()) . "');
                window.location.href = '../manage_routes.php';
            </script>";
        }
        break;
        
    default:
        echo "<script>
            alert('Invalid action!');
            window.location.href = '../manage_routes.php';
        </script>";
        break;
}
?>