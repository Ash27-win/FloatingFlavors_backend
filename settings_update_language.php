<?php
require_once "config.php";

$user_id = $_POST['user_id'] ?? null;
$language = $_POST['language'] ?? null;

if (!$user_id || !$language) {
    echo json_encode([
        "success" => false,
        "message" => "Missing parameters"
    ]);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE users
    SET language = ?
    WHERE id = ?
");

$success = $stmt->execute([$language, $user_id]);

echo json_encode([
    "success" => $success
]);
