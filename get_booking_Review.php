<?php
require_once "config.php";

$bookingId = (int)($_GET['booking_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT * FROM event_bookings WHERE booking_id = ?
");
$stmt->execute([$bookingId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT 
        m.name,
        b.quantity,
        (b.quantity * b.price_snapshot) AS total
    FROM booking_menu_items b
    JOIN menu_items m ON m.id = b.menu_item_id
    WHERE b.booking_id = ?
");
$stmt->execute([$bookingId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = array_sum(array_column($items, 'total'));

echo json_encode([
    "success" => true,
    "event" => $event,
    "items" => $items,
    "total_amount" => $total
]);
