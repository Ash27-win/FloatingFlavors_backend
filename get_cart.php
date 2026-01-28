<?php
require_once "config.php";

$user_id = $_GET['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(["success"=>false,"message"=>"User required"]);
    exit;
}

/* Get cart */
$stmt = $pdo->prepare("SELECT id FROM cart WHERE user_id=?");
$stmt->execute([$user_id]);
$cart = $stmt->fetch();

if (!$cart) {
    echo json_encode(["success"=>true,"items"=>[], "total"=>0]);
    exit;
}

/* Get items */
$stmt = $pdo->prepare("
    SELECT 
        ci.id AS cart_item_id,
        ci.menu_item_id,        
        m.name,
        m.image_url,
        ci.quantity,
        ci.price,
        (ci.quantity * ci.price) AS subtotal
    FROM cart_items ci
    JOIN menu_items m ON m.id = ci.menu_item_id
    WHERE ci.cart_id = ?
");
$stmt->execute([$cart['id']]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = array_sum(array_column($items, 'subtotal'));

echo json_encode([
    "success" => true,
    "items" => $items,
    "total" => $total
]);
