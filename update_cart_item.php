<?php
require_once "config.php";

$cart_item_id = $_POST['cart_item_id'] ?? null;
$action = $_POST['action'] ?? null;

if (!$cart_item_id || !$action) {
    echo json_encode(["success"=>false,"message"=>"Missing params"]);
    exit;
}

if ($action === "increase") {
    $pdo->prepare(
        "UPDATE cart_items SET quantity = quantity + 1 WHERE id=?"
    )->execute([$cart_item_id]);
}

if ($action === "decrease") {
    $pdo->prepare(
        "UPDATE cart_items SET quantity = quantity - 1 WHERE id=? AND quantity > 1"
    )->execute([$cart_item_id]);
}

echo json_encode(["success"=>true]);
