<?php
require_once "config.php";

$id = $_POST['address_id'];
$stmt = $pdo->prepare("DELETE FROM user_addresses WHERE id=?");
$stmt->execute([$id]);

echo json_encode(["success"=>true]);
