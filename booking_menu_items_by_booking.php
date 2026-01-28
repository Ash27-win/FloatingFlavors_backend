<?php
require_once "config.php";

$bookingId = (int)($_GET['booking_id'] ?? 0);

if ($bookingId <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid booking id"
    ]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT 
        menu_item_id,
        quantity
    FROM booking_menu_items
    WHERE booking_id = ?
");

$stmt->execute([$bookingId]);

echo json_encode([
    "success" => true,
    "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
]);
