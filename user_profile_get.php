<?php
require_once "config.php";

$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(["success" => false, "message" => "User ID required"]);
    exit;
}

/* WIFI SAFE BASE URL */
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$baseUrl = rtrim($protocol . "://" . $host . $scriptDir, '/') . '/';

/* USER + PROFILE */
$stmt = $pdo->prepare("
    SELECT 
        u.name,
        p.phone,
        p.alt_phone,
        p.profile_image
    FROM users u
    LEFT JOIN user_profiles p ON u.id = p.user_id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

/* ADDRESS (DEFAULT ONLY) */
$stmtAddr = $pdo->prepare("
    SELECT pincode, city, house, area, landmark
    FROM user_addresses
    WHERE user_id = ? AND is_default = 1
    LIMIT 1
");
$stmtAddr->execute([$user_id]);
$address = $stmtAddr->fetch(PDO::FETCH_ASSOC);

/* IMAGE FULL PATH */
$imageFull = "";
if (!empty($user['profile_image'])) {
    $imageFull = $baseUrl . ltrim($user['profile_image'], '/');
}

echo json_encode([
    "success" => true,
    "data" => [
        "name" => $user['name'] ?? "",
        "phone" => $user['phone'] ?? "",
        "alt_phone" => $user['alt_phone'] ?? "",
        "profile_image" => $imageFull,
        "address" => $address ?: null
    ]
]);
