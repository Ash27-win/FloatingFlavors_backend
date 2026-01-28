<?php
require_once "config.php";
header("Content-Type: application/json");

$input = json_decode(file_get_contents("php://input"), true);

$deliveryPartnerId = $input['delivery_partner_id'] ?? null;

if (!$deliveryPartnerId) {
    echo json_encode([
        "success" => false,
        "message" => "Delivery partner ID missing"
    ]);
    exit;
}

// OPTIONAL (recommended):
// 1. Remove FCM token
// 2. Mark user offline
// 3. Invalidate refresh token

$stmt = $conn->prepare("
    UPDATE delivery_partners
    SET is_online = 0, last_logout = NOW()
    WHERE id = ?
");
$stmt->bind_param("i", $deliveryPartnerId);
$stmt->execute();

echo json_encode([
    "success" => true,
    "message" => "Delivery partner logged out successfully"
]);
