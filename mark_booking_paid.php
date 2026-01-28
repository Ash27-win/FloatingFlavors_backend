<?php
require "config.php";

$booking_id = $_POST['booking_id'];
$txn_id = $_POST['txn_id'];
$method = $_POST['method'];

$stmt = $pdo->prepare("
  INSERT INTO payment_transactions
  (booking_id, transaction_id, payment_method, payment_status)
  VALUES (?, ?, ?, 'PAID')
");

$stmt->execute([$booking_id, $txn_id, $method]);

echo json_encode(["success" => true]);
