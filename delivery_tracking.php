<?php
require_once "config.php";
header("Content-Type: application/json");

$order_id = $_GET['order_id'] ?? null;
$type = $_GET['type'] ?? "INDIVIDUAL";

if (!$order_id) {
    echo json_encode([
        "success" => false,
        "message" => "order_id missing"
    ]);
    exit;
}

/* ================= USER ADDRESS (ONLY SOURCE) ================= */
$stmt = $pdo->prepare("
    SELECT 
        CONCAT(
            ua.house, ', ',
            ua.area, ', ',
            ua.city, ' - ',
            ua.pincode
        ) AS line1,
        ua.city,
        ua.pincode,
        ua.landmark AS note,
        ua.latitude,
        ua.longitude
    FROM orders o
    JOIN user_addresses ua 
        ON ua.id = o.user_address_id
    WHERE o.id = ?
    LIMIT 1
");
$stmt->execute([$order_id]);
$address = $stmt->fetch(PDO::FETCH_ASSOC);


/* ================= VALIDATION ================= */
if (
    !$address ||
    $address['latitude'] === null ||
    $address['longitude'] === null
) {
    echo json_encode([
        "success" => true,
        "deliveryAddress" => [
            "line1" => $address['line1'] ?? "Address not set",
            "city" => $address['city'] ?? "",
            "pincode" => $address['pincode'] ?? "",
            "note" => $address['note'] ?? "",
            "latitude" => 0,
            "longitude" => 0,
            "error" => "DELIVERY_ADDRESS_LOCATION_MISSING"
        ]
    ]);
    exit;
}

/* ================= SUCCESS ================= */
echo json_encode([
    "success" => true,
    "deliveryAddress" => [
        "line1" => $address['line1'],
        "city" => $address['city'],
        "pincode" => $address['pincode'],
        "note" => $address['note'],
        "latitude" => (float)$address['latitude'],
        "longitude" => (float)$address['longitude']
    ]
]);
