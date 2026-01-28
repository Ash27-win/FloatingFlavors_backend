<?php
require_once "config.php";

$user_id = $_POST['user_id'] ?? null;
$enabled = $_POST['enabled'] ?? null;

if ($user_id === null || $enabled === null) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid request"
    ]);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE users
    SET notifications_enabled = ?
    WHERE id = ?
");

$success = $stmt->execute([$enabled, $user_id]);

echo json_encode([
    "success" => $success
]);
