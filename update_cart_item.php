<?php
require_once "config.php";

$cart_item_id = $_POST['cart_item_id'] ?? null;
$action = $_POST['action'] ?? null;

if (!$cart_item_id || !$action) {
    echo json_encode(["success"=>false,"message"=>"Missing params"]);
    exit;
}

if ($action === "increase") {
    // Check stock limit before increasing
    $check = $pdo->prepare("
        SELECT ci.quantity, m.stock 
        FROM cart_items ci
        JOIN menu_items m ON m.id = ci.menu_item_id
        WHERE ci.id = ?
    ");
    $check->execute([$cart_item_id]);
    $row = $check->fetch();

    if ($row && $row['quantity'] < $row['stock']) {
        $pdo->prepare(
            "UPDATE cart_items SET quantity = quantity + 1 WHERE id=?"
        )->execute([$cart_item_id]);
    } else {
        echo json_encode(["success"=>false, "message"=>"Maximum stock reached"]);
        exit;
    }
}

if ($action === "decrease") {
    $pdo->prepare(
        "UPDATE cart_items SET quantity = quantity - 1 WHERE id=? AND quantity > 1"
    )->execute([$cart_item_id]);
}

echo json_encode(["success"=>true]);
