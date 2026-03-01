<?php
// owner/process/process_settlement.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireRole('owner');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'request':
        // Handle settlement request
        $owner_id = $_SESSION['owner_id'];
        $period_start = $_POST['period_start'] ?? '';
        $period_end = $_POST['period_end'] ?? '';
        
        if (empty($period_start) || empty($period_end)) {
            echo "<script>
                alert('Please select period dates!');
                window.location.href = '../settlements.php';
            </script>";
            exit();
        }
        
        try {
            // Calculate total earnings for the period
            $calc_sql = "SELECT COALESCE(SUM(owner_payout), 0) as total
                        FROM trips t
                        JOIN taxis tx ON t.taxi_id = tx.id
                        WHERE tx.owner_id = :owner_id 
                        AND DATE(t.departed_at) BETWEEN :start AND :end";
            
            $calc_stmt = $pdo->prepare($calc_sql);
            $calc_stmt->execute([
                ':owner_id' => $owner_id,
                ':start' => $period_start,
                ':end' => $period_end
            ]);
            $total = $calc_stmt->fetch()['total'];
            
            if ($total <= 0) {
                echo "<script>
                    alert('No earnings found for this period!');
                    window.location.href = '../settlements.php';
                </script>";
                exit();
            }
            
            // Check if settlement already exists for this period
            $check_sql = "SELECT id FROM owner_settlements 
                         WHERE owner_id = :owner_id 
                         AND period_start = :start 
                         AND period_end = :end";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([
                ':owner_id' => $owner_id,
                ':start' => $period_start,
                ':end' => $period_end
            ]);
            
            if ($check_stmt->rowCount() > 0) {
                echo "<script>
                    alert('A settlement request for this period already exists!');
                    window.location.href = '../settlements.php';
                </script>";
                exit();
            }
            
            // Create settlement request
            $sql = "INSERT INTO owner_settlements (owner_id, amount, period_start, period_end, status, created_at) 
                    VALUES (:owner_id, :amount, :start, :end, 'pending', NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':owner_id' => $owner_id,
                ':amount' => $total,
                ':start' => $period_start,
                ':end' => $period_end
            ]);
            
            echo "<script>
                alert('✅ Settlement request submitted successfully!\\n\\nAmount: R " . number_format($total, 2) . "');
                window.location.href = '../settlements.php';
            </script>";
            
        } catch (PDOException $e) {
            error_log("Settlement request error: " . $e->getMessage());
            echo "<script>
                alert('❌ Error submitting request: " . addslashes($e->getMessage()) . "');
                window.location.href = '../settlements.php';
            </script>";
        }
        break;
        
    case 'update_bank':
        // Handle bank details update
        $owner_id = $_SESSION['owner_id'];
        $bank_name = trim($_POST['bank_name'] ?? '');
        $account_number = trim($_POST['account_number'] ?? '');
        $branch_code = trim($_POST['branch_code'] ?? '');
        
        if (empty($bank_name) || empty($account_number) || empty($branch_code)) {
            echo "<script>
                alert('All bank fields are required!');
                window.location.href = '../settlements.php';
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
                window.location.href = '../settlements.php';
            </script>";
            
        } catch (PDOException $e) {
            error_log("Bank update error: " . $e->getMessage());
            echo "<script>
                alert('❌ Error updating bank details: " . addslashes($e->getMessage()) . "');
                window.location.href = '../settlements.php';
            </script>";
        }
        break;
        
    case 'cancel':
        // Handle cancel settlement request
        $settlement_id = $_GET['id'] ?? 0;
        $owner_id = $_SESSION['owner_id'];
        
        if (empty($settlement_id)) {
            echo "<script>
                alert('Settlement ID missing!');
                window.location.href = '../settlements.php';
            </script>";
            exit();
        }
        
        try {
            // Verify ownership and pending status
            $check_sql = "SELECT id FROM owner_settlements 
                         WHERE id = :id AND owner_id = :owner_id AND status = 'pending'";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([
                ':id' => $settlement_id,
                ':owner_id' => $owner_id
            ]);
            
            if ($check_stmt->rowCount() == 0) {
                echo "<script>
                    alert('Settlement not found or cannot be cancelled!');
                    window.location.href = '../settlements.php';
                </script>";
                exit();
            }
            
            // Delete the settlement request
            $sql = "DELETE FROM owner_settlements WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $settlement_id]);
            
            echo "<script>
                alert('✅ Settlement request cancelled successfully!');
                window.location.href = '../settlements.php';
            </script>";
            
        } catch (PDOException $e) {
            error_log("Cancel settlement error: " . $e->getMessage());
            echo "<script>
                alert('❌ Error cancelling request: " . addslashes($e->getMessage()) . "');
                window.location.href = '../settlements.php';
            </script>";
        }
        break;
        
    default:
        echo "<script>
            alert('Invalid action!');
            window.location.href = '../settlements.php';
        </script>";
        break;
}
?>