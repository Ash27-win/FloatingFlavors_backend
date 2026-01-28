<?php
require_once "config.php";

$userId = $_GET['user_id'] ?? null;

if (!$userId) {
    echo json_encode([
        "success" => false,
        "message" => "User ID required"
    ]);
    exit;
}

// Get the latest booking (any status)
$stmt = $pdo->prepare("
    SELECT * FROM event_bookings
    WHERE user_id = ?
    ORDER BY booking_id DESC
    LIMIT 1
");
$stmt->execute([$userId]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if ($booking) {
    echo json_encode([
        "success" => true,
        "has_booking" => true,
        "data" => $booking,
        "can_create_new" => ($booking['status'] == 'CANCELLED') // Only cancelled bookings allow new ones
    ]);
} else {
    echo json_encode([
        "success" => true,
        "has_booking" => false,
        "data" => null,
        "can_create_new" => true
    ]);
}