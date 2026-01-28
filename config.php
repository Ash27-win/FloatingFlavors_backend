<?php
// config.php

$HOST = "localhost";
$DB_NAME = "floating_flavors_db";
$USERNAME = "root";
$PASSWORD = "";

if (!defined('DB_NAME')) define('DB_NAME', $DB_NAME);

try {
    $pdo = new PDO(
        "mysql:host=$HOST;dbname=$DB_NAME;charset=utf8mb4",
        $USERNAME,
        $PASSWORD,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed"
    ]);
    exit;
}

/* ================= COMPOSER AUTOLOAD (EXISTING) ================= */
$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Composer autoload not found"
    ]);
    exit;
}
require_once $autoload;

/* ================= JWT CONFIG (EXISTING) ================= */
if (!defined('JWT_SECRET')) {
    define(
        'JWT_SECRET',
        '3807c08757e189a1783be9891663470a0de314cebfb1a74dd8dbf4253e76131c74cdb9afe5680bf27bae24fa89eedb11e2a330747bca925bc96a4a1fcdb045e2'
    );
}

if (!defined('ACCESS_TOKEN_EXP')) define('ACCESS_TOKEN_EXP', 60 * 60 * 24 * 30);

/* ================= 🔥 FIX START ================= */
/* ✅ DEFINE GRAPH HOPPER KEY (THIS WAS MISSING) */
if (!defined('GRAPHHOPPER_API_KEY')) {
    define('GRAPHHOPPER_API_KEY', '4ad1c669-4572-4d1b-90cc-9e55fb21dc3f');
}
/* ================= 🔥 FIX END ================= */

if (!headers_sent()) {
    header("Content-Type: application/json; charset=utf-8");
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
}

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}
