<?php
// get_live_location.php
require 'config.php';
header("Content-Type: application/json");

// Prevent Caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache"); 
header("Expires: 0");

$order_id = $_GET['order_id'] ?? null;

if (!$order_id) {
    echo json_encode(["success" => false, "message" => "Missing order_id"]);
    exit;
}

try {
    // Fetch directly from orders table (Optimized)
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
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(["success" => false, "message" => "Order not found"]);
        exit;
    }

    // A) Live Driver Location
    if (!empty($order['current_latitude']) && !empty($order['current_longitude']) && (float)$order['current_latitude'] != 0.0) {
        echo json_encode([
            "success" => true,
            "source" => "live_driver",
            "location" => [
                "latitude" => (float)$order['current_latitude'],
                "longitude" => (float)$order['current_longitude'],
                "updated_at" => $order['updated_at']
            ]
        ]);
        exit;
    }

    // B) Restaurant Fallback
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

    // C) Waiting
    echo json_encode([
        "success" => false, 
        "message" => "Location waiting for update..."
    ]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "DB Error: " . $e->getMessage()]);
}
?>
