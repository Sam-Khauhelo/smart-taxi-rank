<?php
// admin/get_owner.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('admin');

header('Content-Type: application/json');

$owner_id = $_GET['id'] ?? 0;
$include_details = isset($_GET['details']);

if (empty($owner_id)) {
    echo json_encode(['error' => 'Owner ID required']);
    exit();
}

try {
    if ($include_details) {
        // Get owner with full details including taxis
        $sql = "SELECT o.id as owner_id, o.user_id, o.id_number, o.bank_name, o.account_number, o.branch_code,
                       u.full_name, u.phone_number, u.username, u.is_active,
                       (SELECT COUNT(*) FROM taxis WHERE owner_id = o.id) as total_taxis,
                       (SELECT COUNT(*) FROM drivers WHERE owner_id = o.id) as total_drivers
                FROM owners o
                JOIN users u ON o.user_id = u.id
                WHERE o.id = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $owner_id]);
        $owner = $stmt->fetch();
        
        if ($owner) {
            // Get owner's taxis
            $taxis_sql = "SELECT t.*, r.route_name, 
                          d.id as driver_id, u2.full_name as driver_name
                          FROM taxis t
                          LEFT JOIN routes r ON t.route_id = r.id
                          LEFT JOIN drivers d ON t.id = d.taxi_id
                          LEFT JOIN users u2 ON d.user_id = u2.id
                          WHERE t.owner_id = :id";
            $taxis_stmt = $pdo->prepare($taxis_sql);
            $taxis_stmt->execute([':id' => $owner_id]);
            $owner['taxis'] = $taxis_stmt->fetchAll();
            
            echo json_encode($owner);
        } else {
            echo json_encode(['error' => 'Owner not found']);
        }
    } else {
        // Basic owner data for editing
        $sql = "SELECT o.id as owner_id, o.user_id, o.id_number, o.bank_name, o.account_number, o.branch_code,
                       u.full_name, u.phone_number, u.username, u.is_active
                FROM owners o
                JOIN users u ON o.user_id = u.id
                WHERE o.id = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $owner_id]);
        $owner = $stmt->fetch();
        
        if ($owner) {
            echo json_encode($owner);
        } else {
            echo json_encode(['error' => 'Owner not found']);
        }
    }
    
} catch (PDOException $e) {
    error_log("Get owner error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}
?>