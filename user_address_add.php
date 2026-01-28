<?php
require_once "config.php";
require_once "geocode_address.php";

header("Content-Type: application/json");

$user_id  = $_POST['user_id'] ?? null;
$label    = $_POST['label'] ?? "Home";
$house    = $_POST['house'] ?? '';
$area     = $_POST['area'] ?? '';
$city     = $_POST['city'] ?? '';
$pincode  = $_POST['pincode'] ?? '';
$landmark = $_POST['landmark'] ?? null;

if (!$user_id || !$house || !$area || !$city || !$pincode) {
    echo json_encode([
        "success" => false,
        "message" => "Missing address fields"
    ]);
    exit;
}

/* 🔥 STEP 1: FULL ADDRESS STRING */
$fullAddress = "$house, $area, $city - $pincode";

/* 🔥 STEP 2: SMART GEOCODE */
// Uses fallback to area/city if house number not found
$geo = smartGeocode($house, $area, $city, $pincode);

$lat = $geo['lat'] ?? null;
$lng = $geo['lng'] ?? null;

/* 🔥 STEP 3: INSERT EVEN IF LAT/LNG NULL */
$stmt = $pdo->prepare("
    INSERT INTO user_addresses
    (user_id, label, house, area, city, pincode, landmark, latitude, longitude, is_default)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
");

$stmt->execute([
    $user_id,
    $label,
    $house,
    $area,
    $city,
    $pincode,
    $landmark,
    $lat,
    $lng
]);

echo json_encode([
    "success" => true,
    "latitude" => $lat,
    "longitude" => $lng
]);

