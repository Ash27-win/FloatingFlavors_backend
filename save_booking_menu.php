<?php
require_once "config.php";

$data = json_decode(file_get_contents("php://input"), true);

if (
    !isset($data['booking_id']) ||
    !isset($data['items']) ||
    !is_array($data['items'])
) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => "Invalid request payload"
    ]);
    exit;
}

$bookingId = (int)$data['booking_id'];
$items = $data['items'];

try {
    $pdo->beginTransaction();

    // ❌ DO NOT DELETE (important for persistence)
    // DELETE removed so selections persist correctly

    $stmt = $pdo->prepare("
        INSERT INTO booking_menu_items
        (booking_id, menu_item_id, quantity, price_snapshot)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            quantity = VALUES(quantity),
            price_snapshot = VALUES(price_snapshot)
    ");

    foreach ($items as $item) {

    // 🔴 IF QUANTITY = 0 → DELETE THAT ITEM
    if ((int)$item['quantity'] === 0) {
        $del = $pdo->prepare("
            DELETE FROM booking_menu_items
            WHERE booking_id = ? AND menu_item_id = ?
        ");
        $del->execute([
            $bookingId,
            (int)$item['menu_item_id']
        ]);
        continue;
    }

    // 🟢 ELSE → INSERT / UPDATE
    $stmt->execute([
        $bookingId,
        (int)$item['menu_item_id'],
        (int)$item['quantity'],
        (float)$item['price']
    ]);
}


    $pdo->commit();

    echo json_encode([
        "status" => true,
        "message" => "Booking menu saved successfully"
    ]);

} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Failed to save booking menu",
        "error" => $e->getMessage()
    ]);
}
