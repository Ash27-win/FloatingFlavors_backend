<?php
//ADMIN ACCEPT / REJECT BOOKING
require_once "config.php";

$bookingId = $_POST['booking_id'] ?? null;
$status = $_POST['status'] ?? null;

if (!$bookingId || !$status) {
    echo json_encode(["success"=>false,"message"=>"Missing params"]);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE event_bookings 
    SET status = :status 
    WHERE booking_id = :id
");

$stmt->execute([
    ":status" => $status,
    ":id" => $bookingId
]);

echo json_encode([
    "success" => true,
    "booking_id" => $bookingId,
    "status" => $status
]);
