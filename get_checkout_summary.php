<?php
require_once "config.php";
header("Content-Type: application/json");

$user_id = $_GET['user_id'] ?? 0;

if ($user_id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid user id"
    ]);
    exit;
}

try {

    // 1️⃣ Get user's cart id
    $cartStmt = $pdo->prepare("SELECT id FROM cart WHERE user_id = ?");
    $cartStmt->execute([$user_id]);
    $cart = $cartStmt->fetch(PDO::FETCH_ASSOC);

    if (!$cart) {
        echo json_encode([
            "success" => true,
            "data" => [
                "items" => [],
                "total" => 0
            ]
        ]);
        exit;
    }

    $cart_id = $cart['id'];

    // 2️⃣ Fetch cart items
    $stmt = $pdo->prepare("
        SELECT 
            ci.id AS cart_item_id,
            ci.menu_item_id,
            m.name,
            ci.quantity,
            ci.price,
            (ci.quantity * ci.price) AS subtotal
        FROM cart_items ci
        JOIN menu_items m ON m.id = ci.menu_item_id
        WHERE ci.cart_id = ?
    ");

    $stmt->execute([$cart_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = 0;
    foreach ($items as $item) {
        $total += $item['subtotal'];
    }

    echo json_encode([
        "success" => true,
        "data" => [
            "items" => $items,
            "total" => $total
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
