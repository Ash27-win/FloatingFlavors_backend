<?php
// ADMIN SHOULD SEE BOOKINGS
require_once "config.php";

$stmt = $pdo->prepare("
    SELECT 
        booking_id AS id,
        user_id,
        booking_type,
        event_name,
        company_name,
        people_count,
        employee_count,
        status,
        created_at
    FROM event_bookings
    ORDER BY booking_id DESC
");

$stmt->execute();

echo json_encode([
    "success" => true,
    "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
]);
