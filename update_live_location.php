<?php
// update_live_location.php
require_once "config.php";
header("Content-Type: application/json");

// Enable error logging
ini_set('display_errors', 0); // Hide from output
error_reporting(E_ALL);

// Get POST data
$order_id = $_POST['order_id'] ?? null;
$lat = $_POST['lat'] ?? null;
$lng = $_POST['lng'] ?? null;
$delivery_partner_id = $_POST['delivery_partner_id'] ?? null;

// Validate
if (!$order_id || !$lat || !$lng) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "order_id, lat, lng required"
    ]);
    exit;
}

try {
    // 1. Check if delivery partner exists for this order if not provided
    if (!$delivery_partner_id) {
        $checkStmt = $pdo->prepare("SELECT delivery_partner_id FROM orders WHERE id = ?");
        $checkStmt->execute([$order_id]);
        $order = $checkStmt->fetch(PDO::FETCH_ASSOC);
        $delivery_partner_id = $order['delivery_partner_id'] ?? 1; // Default to 1 if missing
    }

    // 2. Update order_live_location (History/Specific Table)
    $sqlLive = "
    INSERT INTO order_live_location (order_id, delivery_partner_id, latitude, longitude)
    VALUES (:order_id, :delivery_partner_id, :lat, :lng)
    ON DUPLICATE KEY UPDATE
    latitude = VALUES(latitude),
    longitude = VALUES(longitude),
    delivery_partner_id = VALUES(delivery_partner_id),
    updated_at = CURRENT_TIMESTAMP
    ";
    
    $stmtLive = $pdo->prepare($sqlLive);
    $stmtLive->execute([
        ':order_id' => $order_id,
        ':delivery_partner_id' => $delivery_partner_id,
        ':lat' => $lat,
        ':lng' => $lng
    ]);

    // 3. Update orders Table (For Quick Access/Frontend)
    $sqlOrders = "UPDATE orders SET current_latitude = :lat, current_longitude = :lng WHERE id = :order_id";
    $stmtOrders = $pdo->prepare($sqlOrders);
    $stmtOrders->execute([
        ':lat' => $lat,
        ':lng' => $lng,
        ':order_id' => $order_id
    ]);
    
    echo json_encode([
        "success" => true,
        "message" => "Location updated successfully"
    ]);
    
} catch (Exception $e) {
    error_log("ERROR in update_live_location.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>
