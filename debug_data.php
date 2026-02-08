<?php
require_once "config.php";
header("Content-Type: text/plain");

$userId = 1;

echo "--- USER PROFILES ---\n";
$stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE user_id = ?");
$stmt->execute([$userId]);
$profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($profiles, JSON_PRETTY_PRINT) . "\n";

echo "--- USER ADDRESSES ---\n";
$stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ?");
$stmt->execute([$userId]);
$addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($addresses, JSON_PRETTY_PRINT) . "\n";

echo "--- USERS ---\n";
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
echo json_encode($user, JSON_PRETTY_PRINT) . "\n";
?>
