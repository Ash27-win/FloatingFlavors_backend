<?php
require_once "config.php";

$cart_item_id = $_POST['cart_item_id'] ?? null;

if (!$cart_item_id) {
    echo json_encode(["success"=>false,"message"=>"Missing id"]);
    exit;
}

$pdo->prepare("DELETE FROM cart_items WHERE id=?")->execute([$cart_item_id]);

echo json_encode(["success"=>true,"message"=>"Item removed"]);
