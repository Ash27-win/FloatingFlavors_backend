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

/* EVENT */
$eventStmt = $pdo->prepare("
    SELECT booking_id, booking_type, event_type, event_name,
           people_count, event_date, event_time, status
    FROM event_bookings
    WHERE booking_id = ?
");
$eventStmt->execute([$bookingId]);
$event = $eventStmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    echo json_encode([
        "success" => false,
        "message" => "Booking not found"
    ]);
    exit;
}

/* ITEMS */
$itemStmt = $pdo->prepare("
    SELECT 
        m.name,
        b.quantity,
        b.price_snapshot,
        (b.quantity * b.price_snapshot) AS total
    FROM booking_menu_items b
    JOIN menu_items m ON m.id = b.menu_item_id
    WHERE b.booking_id = ?
");
$itemStmt->execute([$bookingId]);
$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

$total = array_sum(array_column($items, 'total'));

echo json_encode([
    "success" => true,
    "event" => $event,
    "items" => $items,
    "total_amount" => $total
]);
