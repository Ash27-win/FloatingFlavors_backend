<?php
require_once "config.php";
header("Content-Type: text/plain");

$userId = 1;

echo "--- USER PROFILES ---\n";
$stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE user_id = ?");
$stmt->execute([$userId]);
$profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($profiles, JSON_PRETTY_PRINT) . "\n";
?>
