<?php
require_once "config.php";
header("Content-Type: application/json");

/* ================= INPUT ================= */
$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    echo json_encode([
        "success" => false,
        "message" => "user_id missing"
    ]);
    exit;
}

/* ================= FETCH ADDRESSES ================= */
$stmt = $pdo->prepare("
    SELECT
        id,
        label,
        house,
        area,
        city,
        pincode,
        landmark,
        latitude,
        longitude,
        is_default
    FROM user_addresses
    WHERE user_id = ?
    ORDER BY is_default DESC, id DESC
");

$stmt->execute([$user_id]);
$addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= RESPONSE ================= */
echo json_encode([
    "success" => true,
    "data" => $addresses
]);
