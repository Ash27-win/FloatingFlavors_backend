<?php
require_once "config.php";

$user_id = $_POST['user_id'] ?? null;

if (!$user_id) {
    echo json_encode([
        "success" => false,
        "message" => "User ID required"
    ]);
    exit;
}

$stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
$success = $stmt->execute([$user_id]);

echo json_encode([
    "success" => $success
]);
