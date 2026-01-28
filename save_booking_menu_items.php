<?php
require_once "config.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success"=>false,"message"=>"Method not allowed"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

$bookingId = (int)($input['booking_id'] ?? 0);
$items = $input['items'] ?? [];

if ($bookingId <= 0 || empty($items)) {
    echo json_encode(["success"=>false,"message"=>"Invalid input"]);
    exit;
}

$pdo->beginTransaction();

try {
    // Clear previous selection
    $pdo->prepare(
        "DELETE FROM booking_menu_items WHERE booking_id = ?"
    )->execute([$bookingId]);

    // Insert new selection
    $stmt = $pdo->prepare("
        INSERT INTO booking_menu_items
        (booking_id, menu_item_id, quantity, price_snapshot)
        VALUES (?, ?, ?, ?)
    ");

    foreach ($items as $item) {
        $stmt->execute([
            $bookingId,
            $item['menu_item_id'],
            $item['quantity'],
            $item['price']
        ]);
    }

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "Booking menu saved"
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
