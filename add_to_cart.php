<?php
require_once "config.php";

$user_id = $_POST['user_id'] ?? null;
$menu_item_id = $_POST['menu_item_id'] ?? null;
$price = $_POST['price'] ?? null;

if (!$user_id || !$menu_item_id || !$price) {
    echo json_encode(["success"=>false,"message"=>"Missing params"]);
    exit;
}

/* 1. Find or create cart */
$stmt = $pdo->prepare("SELECT id FROM cart WHERE user_id=?");
$stmt->execute([$user_id]);
$cart = $stmt->fetch();

if (!$cart) {
    $pdo->prepare("INSERT INTO cart (user_id) VALUES (?)")->execute([$user_id]);
    $cart_id = $pdo->lastInsertId();
} else {
    $cart_id = $cart['id'];
}

/* 2. Check Stock & Add */
$stockStmt = $pdo->prepare("SELECT stock, is_available FROM menu_items WHERE id = ?");
$stockStmt->execute([$menu_item_id]);
$menuItem = $stockStmt->fetch();

if (!$menuItem || $menuItem['is_available'] == 0 || $menuItem['stock'] <= 0) {
    echo json_encode(["success"=>false, "message"=>"Item out of stock!"]);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT id, quantity FROM cart_items WHERE cart_id=? AND menu_item_id=?"
);
$stmt->execute([$cart_id, $menu_item_id]);
$item = $stmt->fetch();

if ($item) {
    if ($item['quantity'] >= $menuItem['stock']) {
        echo json_encode(["success"=>false, "message"=>"Maximum stock reached!"]);
        exit;
    }
    $pdo->prepare(
        "UPDATE cart_items SET quantity = quantity + 1 WHERE id=?"
    )->execute([$item['id']]);
} else {
    $pdo->prepare(
        "INSERT INTO cart_items (cart_id, menu_item_id, quantity, price)
         VALUES (?,?,1,?)"
    )->execute([$cart_id, $menu_item_id, $price]);
}

echo json_encode(["success"=>true,"message"=>"Item added"]);
