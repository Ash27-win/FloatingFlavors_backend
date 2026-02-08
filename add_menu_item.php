<?php
require_once "config.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success"=>false,"message"=>"Method not allowed"]);
    exit;
}

// auto-detect base URL used by the incoming request
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST']; // includes port if any
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$baseUrl = rtrim($protocol . "://" . $host . $scriptDir, '/') . '/';

// make uploads/menu directory
$uploadsDir = __DIR__ . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "menu" . DIRECTORY_SEPARATOR;
if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);

// fields
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$price = $_POST['price'] ?? null;
$category = trim($_POST['category'] ?? '');
$isAvailable = isset($_POST['is_available']) ? (int)$_POST['is_available'] : 1;

if ($name === '' || $price === null || !is_numeric($price)) {
    echo json_encode(["success"=>false,"message"=>"Name and numeric price required"]);
    exit;
}
$price = number_format((float)$price, 2, '.', '');

// default stored relative path (empty if no upload)
$relativePath = "";

// stock (optional, default 50)
$stock = isset($_POST['stock']) ? (int)$_POST['stock'] : 50;

// handle file if provided under "image"
if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['image'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(["success"=>false,"message"=>"File upload error"]);
        exit;
    }

    $allowed = ['image/jpeg','image/png','image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed)) {
        echo json_encode(["success"=>false,"message"=>"Only JPG/PNG/WEBP allowed"]);
        exit;
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $originalSafe = preg_replace("/[^a-zA-Z0-9-_]/", "_", pathinfo($file['name'], PATHINFO_FILENAME));
    $rand = random_int(100000000, 999999999);
    $finalFileName = $rand . "_" . $originalSafe . "." . $ext;

    $destination = $uploadsDir . $finalFileName;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        echo json_encode(["success"=>false,"message"=>"Failed to save uploaded file"]);
        exit;
    }

    // store relative path in DB
    $relativePath = "uploads/menu/" . $finalFileName;
}

try {
    $stmt = $pdo->prepare("INSERT INTO menu_items (name, description, price, category, image_url, is_available, stock) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $description, $price, $category, $relativePath, $isAvailable, $stock]);

    // return image_full for convenience
    $imageFull = $relativePath !== "" ? ($baseUrl . $relativePath) : "";

    echo json_encode([
        "success" => true,
        "message" => "Menu item added",
        "image_relative" => $relativePath,
        "image_full" => $imageFull
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success"=>false,"message"=>"DB insert failed: ".$e->getMessage()]);
}
