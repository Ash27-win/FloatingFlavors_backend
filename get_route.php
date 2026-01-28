<?php
require_once "config.php";
header("Content-Type: application/json");

/* ================= INPUT ================= */
$startLat = $_GET['start_lat'] ?? null;
$startLng = $_GET['start_lng'] ?? null;
$endLat   = $_GET['end_lat'] ?? null;
$endLng   = $_GET['end_lng'] ?? null;

if (!$startLat || !$startLng || !$endLat || !$endLng) {
    echo json_encode([
        "success" => false,
        "message" => "Missing coordinates"
    ]);
    exit;
}

/* ================= 🔥 FIX START ================= */
// ✅ SAFETY CHECK (PREVENT FATAL ERROR)
if (!defined('GRAPHHOPPER_API_KEY') || empty(GRAPHHOPPER_API_KEY)) {
    echo json_encode([
        "success" => false,
        "message" => "GraphHopper API key not configured"
    ]);
    exit;
}
/* ================= 🔥 FIX END ================= */

$url = "https://graphhopper.com/api/1/route"
    . "?point=$startLat,$startLng"
    . "&point=$endLat,$endLng"
    . "&vehicle=car"
    . "&locale=en"
    . "&calc_points=true"
    . "&points_encoded=false"
    . "&instructions=true"
    . "&key=" . GRAPHHOPPER_API_KEY;

$resp = file_get_contents($url);
if ($resp === false) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to contact GraphHopper"
    ]);
    exit;
}

$data = json_decode($resp, true);
if (!isset($data['paths'][0])) {
    echo json_encode([
        "success" => false,
        "message" => "No route found"
    ]);
    exit;
}

/* ================= ORIGINAL RESPONSE STRUCTURE ================= */
echo json_encode([
    "paths" => [
        [
            "distance" => $data['paths'][0]['distance'],
            "time" => $data['paths'][0]['time'],
            "points" => $data['paths'][0]['points'],
            "instructions" => $data['paths'][0]['instructions']
        ]
    ]
]);
