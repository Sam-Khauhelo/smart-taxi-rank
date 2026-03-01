<?php
// api/offline_queue.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('driver');

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'sync_trips':
        // Sync offline trips when back online
        $offline_trips = json_decode($_POST['trips'] ?? '[]', true);
        $synced = [];
        
        foreach ($offline_trips as $trip) {
            // Validate and save trip
            $sql = "INSERT INTO trips (driver_id, taxi_id, route_id, passenger_count, 
                                       fare_amount, total_fare, association_levy, owner_payout, 
                                       departed_at, trip_status, synced_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'arrived', NOW())";
            
            // Add to database
            // ... (implementation)
            
            $synced[] = $trip['id'];
        }
        
        echo json_encode(['success' => true, 'synced' => $synced]);
        break;
        
    case 'get_queue':
        // Get queue for offline storage
        $driver_id = $_SESSION['driver_id'];
        
        $sql = "SELECT q.*, r.route_name, t.registration_number
                FROM queue q
                JOIN taxis t ON q.taxi_id = t.id
                JOIN routes r ON q.route_id = r.id
                WHERE t.driver_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$driver_id]);
        $queue = $stmt->fetch();
        
        echo json_encode(['queue' => $queue]);
        break;
}
?>