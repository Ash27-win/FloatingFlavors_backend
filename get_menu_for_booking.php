<?php
require_once "config.php";

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Method not allowed"
    ]);
    exit;
}

/**
 * Build base URL dynamically
 * Works for:
 * - localhost
 * - LAN IP
 * - Same WiFi
 * - Mobile hotspot
 */
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$baseUrl = rtrim($protocol . "://" . $host . $scriptDir, '/') . '/';

try {
    $stmt = $pdo->query("
        SELECT
            id,
            name,
            description,
            price,
            category,
            image_url
        FROM menu_items
        WHERE is_available = 1
        ORDER BY category, name
    ");

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as &$item) {
        $img = trim($item['image_url'] ?? '');

        if ($img === '') {
            $item['image_full'] = "";
            continue;
        }

        // Case 1: absolute URL
        if (preg_match('#^https?://#i', $img)) {

            // Replace localhost / 127.0.0.1 with current host
            if (
                strpos($img, '://localhost') !== false ||
                strpos($img, '://127.0.0.1') !== false
            ) {
                $parsed = parse_url($img);
                $path = isset($parsed['path']) ? ltrim($parsed['path'], '/') : '';
                $item['image_full'] = $baseUrl . $path;
            } else {
                // Already valid public/LAN URL
                $item['image_full'] = $img;
            }
            continue;
        }

        // Case 2: relative path
        $item['image_full'] = $baseUrl . ltrim($img, '/\\');
    }
    unset($item);

    echo json_encode([
        "success" => true,
        "data" => $items
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "DB error: " . $e->getMessage()
    ]);
}
