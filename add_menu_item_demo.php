<?php
// add_menu_item_upload.php
// Place in: C:\xampp\htdocs\floating_flavors_api\add_menu_item_upload.php

require_once "config.php";

// Only accept POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}

// -------------------------
// CHANGED: Build base URL dynamically so backend returns FULL image URLs.
// This avoids hardcoding and works with localhost, dev tunnels, public hostnames, etc.
// Example result: "https://wv1qhk7m-80.inc1.devtunnels.ms/floating_flavors_api/"
// -------------------------
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST']; // includes host and port if present
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$baseUrl = rtrim($protocol . "://" . $host . $scriptDir, '/') . '/'; // CHANGED

// Ensure uploads directory exists
$uploadsDir = __DIR__ . DIRECTORY_SEPARATOR . "uploads";
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

// Fields come in $_POST; file comes in $_FILES['image']
$name = isset($_POST['name']) ? trim($_POST['name']) : "";
$description = isset($_POST['description']) ? trim($_POST['description']) : "";
$price = isset($_POST['price']) ? $_POST['price'] : null;
$category = isset($_POST['category']) ? trim($_POST['category']) : "";
$isAvailable = isset($_POST['isAvailable']) ? (int)$_POST['isAvailable'] : 1;

// Basic validation
if ($name === "" || $price === null || !is_numeric($price)) {
    echo json_encode(["success" => false, "message" => "Name and numeric price are required"]);
    exit;
}

$price = number_format((float)$price, 2, '.', '');

// Handle file upload (optional)
$imageUrl = ""; // will store full URL (CHANGED)
if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['image'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(["success" => false, "message" => "File upload error", "error_code" => $file['error']]);
        exit;
    }

    // Basic validations: file type and size (e.g., max 5MB)
    $allowedMime = ['image/jpeg','image/png','image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowedMime)) {
        echo json_encode(["success" => false, "message" => "Only JPG/PNG/WEBP images are allowed"]);
        exit;
    }
    $maxBytes = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxBytes) {
        echo json_encode(["success" => false, "message" => "Image exceeds maximum size of 5MB"]);
        exit;
    }

    // Create unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safeName = preg_replace("/[^a-zA-Z0-9-_\.]/", "_", pathinfo($file['name'], PATHINFO_FILENAME));
    $newFileName = time() . "_" . bin2hex(random_bytes(4)) . "_" . $safeName . "." . $ext;
    $destination = $uploadsDir . DIRECTORY_SEPARATOR . $newFileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        echo json_encode(["success" => false, "message" => "Failed to move uploaded file"]);
        exit;
    }

    // -------------------------
    // CHANGED: Save full URL into DB so UI receives complete URL.
    // (Previously we saved "uploads/xxx.jpg". Now we save full $baseUrl + "uploads/xxx.jpg")
    // -------------------------
    $imageUrl = $baseUrl . "uploads/" . $newFileName; // CHANGED
}

// Insert into DB
try {
    $stmt = $pdo->prepare("
        INSERT INTO menu_items (name, description, price, category, image_url, is_available)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$name, $description, $price, $category, $imageUrl, $isAvailable]);
    echo json_encode([
        "success" => true,
        "message" => "Menu item added successfully",
        "image_url" => $imageUrl // helpful feedback in response
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Failed to add menu item", "error" => $e->getMessage()]);
}
