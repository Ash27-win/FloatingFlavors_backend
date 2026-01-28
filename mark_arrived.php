<?php
require_once "config.php";
header("Content-Type: application/json");

$orderId = $_POST['order_id'] ?? null;

if (!$orderId) {
    echo json_encode([
        "success" => false,
        "message" => "order_id required"
    ]);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE orders
    SET status = 'DELIVERED'
    WHERE id = :id
      AND status = 'OUT_FOR_DELIVERY'
");

$stmt->execute([':id' => $orderId]);

if ($stmt->rowCount() === 0) {
    echo json_encode([
        "success" => false,
        "message" => "Order not in delivery state"
    ]);
    exit;
}

echo json_encode([
    "success" => true,
    "message" => "Order marked as delivered"
]);
