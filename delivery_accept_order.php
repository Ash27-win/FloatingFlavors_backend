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
    SET status = 'OUT_FOR_DELIVERY'
    WHERE id = :id
      AND delivery_partner_id = :dp
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

echo json_encode(["success"=>true,"message"=>"Order accepted"]);
