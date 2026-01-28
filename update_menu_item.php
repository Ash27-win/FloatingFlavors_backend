<?php
// update_menu_item.php  (PDO version) 
// Replaces mixed-mysqli version — expects multipart/form-data
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config.php'; // config creates $pdo

$uploadDir = __DIR__ . '/uploads/';
$menuUploadSubdir = 'menu/';           // keep menu images in uploads/menu/
$maxFileSize = 4 * 1024 * 1024;        // 4 MB
$allowedMime = ['image/jpeg', 'image/png', 'image/webp'];

// build base URL used for image_full
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$baseUrl = rtrim($protocol . '://' . $host . $scriptDir, '/') . '/';

if (!is_dir($uploadDir . $menuUploadSubdir)) {
    @mkdir($uploadDir . $menuUploadSubdir, 0755, true);
}

// helpers
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function safeFileName($name) {
    return preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
}

// read inputs (multipart/form-data)
$id = isset($_POST['id']) ? trim($_POST['id']) : null;
if (empty($id) || !is_numeric($id)) {
    jsonResponse(['success' => false, 'message' => 'Invalid or missing id'], 400);
}

$name = isset($_POST['name']) ? trim($_POST['name']) : null;
$description = isset($_POST['description']) ? trim($_POST['description']) : null;
$price = isset($_POST['price']) ? trim($_POST['price']) : null;
$category = isset($_POST['category']) ? trim($_POST['category']) : null;
$is_available = isset($_POST['is_available']) ? (int) $_POST['is_available'] : null;

try {
    // fetch existing to preserve image if not replaced
    $sel = $pdo->prepare("SELECT image_url FROM menu_items WHERE id = ?");
    $sel->execute([$id]);
    $row = $sel->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        jsonResponse(['success' => false, 'message' => 'Item not found'], 404);
    }
    $existingImageUrl = $row['image_url'] ?? '';

    // handle optional file upload
    $newRelativeImage = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $fileError = $_FILES['image']['error'];
        if ($fileError !== UPLOAD_ERR_OK) {
            jsonResponse(['success' => false, 'message' => 'File upload error: ' . $fileError], 400);
        }

        $tmpPath = $_FILES['image']['tmp_name'];
        if (!is_uploaded_file($tmpPath)) {
            jsonResponse(['success' => false, 'message' => 'Invalid uploaded file'], 400);
        }

        $fileSize = filesize($tmpPath);
        if ($fileSize > $maxFileSize) {
            jsonResponse(['success' => false, 'message' => 'File too large. Max ' . ($maxFileSize/1024/1024) . ' MB'], 400);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);
        if (!in_array($mime, $allowedMime)) {
            jsonResponse(['success' => false, 'message' => 'Invalid file type. Allowed jpeg, png, webp'], 400);
        }

        switch ($mime) {
            case 'image/jpeg': $ext = '.jpg'; break;
            case 'image/png':  $ext = '.png'; break;
            case 'image/webp': $ext = '.webp'; break;
            default: $ext = '';
        }

        $origName = pathinfo($_FILES['image']['name'], PATHINFO_FILENAME);
        $safe = preg_replace('/[^A-Za-z0-9_-]/', '_', $origName);
        $unique = time() . '_' . bin2hex(random_bytes(5));
        $fileName = safeFileName($unique . '_' . $safe . $ext);
        $dest = $uploadDir . $menuUploadSubdir . $fileName;

        if (!move_uploaded_file($tmpPath, $dest)) {
            jsonResponse(['success' => false, 'message' => 'Failed to save uploaded file'], 500);
        }
        @chmod($dest, 0644);

        $newRelativeImage = 'uploads/' . $menuUploadSubdir . $fileName;

        // delete old local image if it is inside uploads/
        if (!empty($existingImageUrl) && strpos($existingImageUrl, 'uploads/') === 0) {
            $oldFile = __DIR__ . '/' . $existingImageUrl;
            if (is_file($oldFile)) {
                @unlink($oldFile);
            }
        }
    }

    // Build dynamic update query based on provided fields
    $fields = [];
    $params = [];

    if ($name !== null) { $fields[] = 'name = ?'; $params[] = $name; }
    if ($description !== null) { $fields[] = 'description = ?'; $params[] = $description; }
    if ($price !== null) { 
        // sanitize numeric price
        if ($price === '' || !is_numeric($price)) {
            jsonResponse(['success' => false, 'message' => 'Price must be numeric'], 400);
        }
        $priceVal = number_format((float)$price, 2, '.', '');
        $fields[] = 'price = ?'; $params[] = $priceVal;
    }
    if ($category !== null) { $fields[] = 'category = ?'; $params[] = $category; }
    if ($is_available !== null) { $fields[] = 'is_available = ?'; $params[] = (int)$is_available; }
    if ($newRelativeImage !== null) { $fields[] = 'image_url = ?'; $params[] = $newRelativeImage; }

    if (empty($fields)) {
        jsonResponse(['success' => false, 'message' => 'No fields to update'], 400);
    }

    $params[] = $id;
    $sql = "UPDATE menu_items SET " . implode(', ', $fields) . " WHERE id = ?";

    $upd = $pdo->prepare($sql);
    $ok = $upd->execute($params);
    if (!$ok) {
        jsonResponse(['success' => false, 'message' => 'DB update failed'], 500);
    }

    $finalImage = $newRelativeImage !== null ? $newRelativeImage : $existingImageUrl;
    $image_full = $finalImage !== '' ? ($baseUrl . ltrim($finalImage, '/\\')) : '';

    jsonResponse([
        'success' => true,
        'message' => 'Item updated successfully',
        'image_url' => $finalImage,
        'image_full' => $image_full
    ], 200);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'DB error', 'error' => $e->getMessage()], 500);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()], 500);
}
