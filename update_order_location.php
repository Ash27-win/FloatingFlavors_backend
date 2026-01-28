<?php
require_once "config.php";
header("Content-Type: application/json");
// Enable error logging
ini_set('display_errors', 1);
error_reporting(E_ALL);
// Get POST data
$order_id = $_POST['order_id'] ?? null;
$lat = $_POST['lat'] ?? null;
$lng = $_POST['lng'] ?? null;
$delivery_partner_id = $_POST['delivery_partner_id'] ?? null;
// Log received data
error_log("DEBUG update_order_location.php: Received - order_id: $order_id, lat: $lat, lng: $lng, delivery_partner_id: $delivery_partner_id");
// Validate
if (!$order_id || !$lat || !$lng) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "order_id, lat, lng required"
    ]);
    exit;
}
// Check if delivery partner exists for this order
$checkSql = "SELECT delivery_partner_id FROM orders WHERE id = :order_id";
$checkStmt = $pdo->prepare($checkSql);
$checkStmt->execute([':order_id' => $order_id]);
$order = $checkStmt->fetch();
if (!$order) {
    echo json_encode([
        "success" => false,
        "message" => "Order not found"
    ]);
    exit;
}
// If no delivery_partner_id was sent, use the one from orders table
if (!$delivery_partner_id && $order['delivery_partner_id']) {
    $delivery_partner_id = $order['delivery_partner_id'];
}
if (!$delivery_partner_id) {
    $delivery_partner_id = 1; // Default
}
try {
    // 1. Update the LIVE TRACKING table (Your existing logic)
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
    // 2. 🔥 CRITICAL FIX: Also Update the Main ORDERS Table
    // This fixes the issue where the Customer App (which reads 'orders') sees old data.
    $sqlOrders = "UPDATE orders SET current_latitude = :lat, current_longitude = :lng WHERE id = :order_id";
    $stmtOrders = $pdo->prepare($sqlOrders);
    $stmtOrders->execute([
        ':lat' => $lat,
        ':lng' => $lng,
        ':order_id' => $order_id
    ]);
    
    error_log("DEBUG: Double update successful for order_id: $order_id");
    
    echo json_encode([
        "success" => true,
        "message" => "Location updated successfully (Tables Synced)"
    ]);
    
} catch (Exception $e) {
    error_log("ERROR in update_order_location.php: " . $e->getMessage());
    
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>