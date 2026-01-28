<?php
require 'config.php';
header("Content-Type: application/json");
// 🔥 Prevent Caching (Crucial for Real-Time Tracking)
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
$order_id = $_GET['order_id'] ?? null;
if (!$order_id) {
    echo json_encode(["success" => false, "message" => "Missing order_id"]);
    exit;
}
try {
    // 1. Try to get LIVE Driver Location directly from the ORDER table 
    // (We added current_latitude/current_longitude to 'orders' exactly for this reason)
    $stmt = $pdo->prepare("
        SELECT 
            o.current_latitude, 
            o.current_longitude, 
            o.updated_at,
            o.status,
            r.latitude as rest_lat, 
            r.longitude as rest_lng
        FROM orders o
        LEFT JOIN restaurants r ON o.restaurant_id = r.id
        WHERE o.id = :order_id
        LIMIT 1
    ");
    $stmt->execute(['order_id' => $order_id]);
    $order = $stmt->fetch();
    if (!$order) {
        echo json_encode(["success" => false, "message" => "Order not found"]);
        exit;
    }
    // A) Check if we have a LIVE Driver Location
    // We check if it's not null and not 0.0
    if (!empty($order['current_latitude']) && !empty($order['current_longitude']) && (float)$order['current_latitude'] != 0.0) {
        echo json_encode([
            "success" => true,
            "source" => "live_driver", // Debug flag
            "location" => [
                "latitude" => (float)$order['current_latitude'],
                "longitude" => (float)$order['current_longitude'],
                "updated_at" => $order['updated_at']
            ]
        ]);
        exit;
    }
    // B) Fallback: If Driver hasn't moved yet, show RESTAURANT Location
    // (Your existing logic, tailored for accuracy)
    if (!empty($order['rest_lat']) && !empty($order['rest_lng'])) {
        echo json_encode([
            "success" => true,
            "source" => "restaurant_fallback",
            "location" => [
                "latitude" => (float)$order['rest_lat'],
                "longitude" => (float)$order['rest_lng'],
                "updated_at" => date('Y-m-d H:i:s')
            ]
        ]);
        exit;
    }
    // C) No Data Available
    echo json_encode([
        "success" => false, 
        "message" => "Location waiting for update..."
    ]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "DB Error: " . $e->getMessage()]);
}
?>