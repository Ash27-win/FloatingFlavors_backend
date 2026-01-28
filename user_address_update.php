<?php
require_once "config.php";
require_once "geocode_address.php";

header("Content-Type: application/json");

$address_id = $_POST['address_id'] ?? null;
$user_id    = $_POST['user_id'] ?? null;
$label      = $_POST['label'] ?? '';
$house      = $_POST['house'] ?? '';
$area       = $_POST['area'] ?? '';
$city       = $_POST['city'] ?? '';
$pincode    = $_POST['pincode'] ?? '';
$landmark   = $_POST['landmark'] ?? null;
$is_default = $_POST['is_default'] ?? 0;

if (!$address_id || !$user_id) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid request"
    ]);
    exit;
}

/* 🔥 STEP 1: FULL ADDRESS */
$fullAddress = "$house, $area, $city - $pincode";

/* 🔥 STEP 2: SMART GEOCODE */
$geo = smartGeocode($house, $area, $city, $pincode);
// Ensure we don't overwrite valid coords with null unless necessary, 
// but here we trust smartGeocode to find the best match.
$stmt = $pdo->prepare("
    UPDATE user_addresses
    SET label = ?, house = ?, area = ?, city = ?, pincode = ?, landmark = ?,
        latitude = ?, longitude = ?, is_default = ?
    WHERE id = ? AND user_id = ?
");

$stmt->execute([
    $label,
    $house,
    $area,
    $city,
    $pincode,
    $landmark,
    $geo['lat'] ?? null,
    $geo['lng'] ?? null,
    $is_default,
    $address_id,
    $user_id
]);

echo json_encode([
    "success" => true,
    "message" => "Address updated with location",
    "lat" => $geo['lat'] ?? null,
    "lng" => $geo['lng'] ?? null
]);
exit;
