<?php
require_once "config.php";
header("Content-Type: application/json");

$orderId = $_POST['order_id'] ?? null;
$deliveryId = $_POST['delivery_partner_id'] ?? null;

if (!$orderId || !$deliveryId) {
    echo json_encode(["success"=>false,"message"=>"Missing params"]);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE orders
    SET status = 'OUT_FOR_DELIVERY',
        delivery_partner_id = :dp
    WHERE id = :id
      AND (delivery_partner_id IS NULL OR delivery_partner_id = 0)
      AND status = 'CONFIRMED'
");

$stmt->execute([
    ':dp' => $deliveryId,
    ':id' => $orderId
]);


if ($stmt->rowCount() === 0) {
    echo json_encode(["success"=>false,"message"=>"Order already taken"]);
    exit;
}

    // ... (Existing WS logic) ...

    /* ================= 🔔 NOTIFICATION TRIGGER (NEW) ================= */
    require_once "send_notification_helper.php";
    
    // Notify User
    $uStmt = $pdo->prepare("SELECT user_id FROM orders WHERE id = ?");
    $uStmt->execute([$orderId]);
    $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);

    if ($uRow) {
        sendNotification(
            $uRow['user_id'], 
            'User', 
            "Delivery Assigned 🚚",
            "A delivery partner has accepted your order #$orderId and is on the way to the restaurant.",
            "DELIVERY_ASSIGNED",
            $orderId,
            ['screen' => 'OrderTrackingScreen'] // Open map directly
        );
    }
    /* ================================================================= */

    echo json_encode(["success"=>true, "message"=>"Order accepted"]);
