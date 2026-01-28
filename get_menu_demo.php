<?php
// get_menu.php
// Place in: C:\xampp\htdocs\floating_flavors_api\get_menu.php

require_once "config.php";

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}

// -------------------------
// CHANGED: Build base URL dynamically (same as upload file) so we can convert relative image paths
// to full URLs if some rows still have relative paths.
// -------------------------
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$baseUrl = rtrim($protocol . "://" . $host . $scriptDir, '/') . '/'; // CHANGED

try {
    $stmt = $pdo->query("
        SELECT id, name, description, price, category, image_url, is_available
        FROM menu_items
        ORDER BY id ASC
    ");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // -------------------------
    // CHANGED: Normalize image_url for each item: 
    // - if it's empty -> keep empty
    // - if it already starts with http -> leave it
    // - if it is a relative path (e.g., "uploads/xxx.jpg") -> convert to full URL
    // -------------------------
    // --- insert into get_menu.php AFTER $baseUrl is built and $items fetched ---
foreach ($items as &$item) {
    if (empty($item['image_url'])) continue;

    // If already absolute (http/https)
    if (preg_match("#^https?://#i", $item['image_url'])) {
        // If it contains localhost, replace host with current request host
        if (strpos($item['image_url'], '://localhost') !== false) {
            $parsed = parse_url($item['image_url']);
            $newPath = isset($parsed['path']) ? ltrim($parsed['path'], '/') : '';
            $item['image_url'] = $baseUrl . $newPath;
        }
        continue;
    }

    // If relative path (uploads/...), build full URL
    $item['image_url'] = $baseUrl . ltrim($item['image_url'], '/\\');
}
unset($item);


    echo json_encode(["success" => true, "message" => "Menu fetched successfully", "data" => $items]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Failed to fetch menu", "error" => $e->getMessage()]);
}
