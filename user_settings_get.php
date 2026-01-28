<?php
require_once "config.php";

$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    echo json_encode([
        "success" => false,
        "message" => "User ID required"
    ]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.name,
        u.email,
        u.language,
        u.notifications_enabled,
        p.profile_image
    FROM users u
    LEFT JOIN user_profiles p ON p.user_id = u.id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode([
        "success" => false,
        "message" => "User not found"
    ]);
    exit;
}

echo json_encode([
    "success" => true,
    "data" => $user,
    "app" => [
        "version" => "2.4.0",
        "build" => "302"
    ]
]);
