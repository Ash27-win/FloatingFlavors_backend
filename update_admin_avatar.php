<?php
// update_admin_avatar.php
require_once "config.php";
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Method not allowed']);
    exit;
}

// ensure uploads dir
$uploadsDir = __DIR__ . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "admin_avatars" . DIRECTORY_SEPARATOR;
if (!is_dir($uploadsDir)) {
    if (!mkdir($uploadsDir, 0755, true)) {
        echo json_encode(['success'=>false,'message'=>'Failed to create upload folder']);
        exit;
    }
}

// admin_id (as form field or part)
$admin_id = isset($_POST['admin_id']) ? (int)$_POST['admin_id'] : 0;
if ($admin_id <= 0) {
    // maybe client sent JSON body—try to parse raw input (but for multipart we expect form)
    echo json_encode(['success'=>false,'message'=>'admin_id required']);
    exit;
}

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] === UPLOAD_ERR_NO_FILE) {
    echo json_encode(['success'=>false,'message'=>'avatar file required']);
    exit;
}

$file = $_FILES['avatar'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success'=>false,'message'=>'File upload error code '.$file['error']]);
    exit;
}

// safe mime detection
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

// map mime -> extension
$map = [
    'image/jpeg' => 'jpg',
    'image/pjpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif'
];

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$ext = strtolower($ext);
if (empty($ext) || strlen($ext) > 5) {
    // fallback to mime
    $ext = isset($map[$mime]) ? $map[$mime] : 'jpg';
}

// sanitized name
$baseName = preg_replace("/[^a-zA-Z0-9-_]/", "_", pathinfo($file['name'], PATHINFO_FILENAME));
$rand = random_int(10000000, 99999999);
$finalFile = $rand . "_" . $baseName . "." . $ext;
$dest = $uploadsDir . $finalFile;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    // try changing permissions if possible
    @chmod($uploadsDir, 0755);
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        echo json_encode(['success'=>false,'message'=>'Failed to save file', 'error'=>error_get_last()]);
        exit;
    }
}

// update DB relative path
$relative = "uploads/admin_avatars/" . $finalFile;
try {
    $u = $pdo->prepare("UPDATE admins SET avatar_url = :path, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
    $u->execute([':path' => $relative, ':id' => $admin_id]);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>'DB error','error'=>$e->getMessage()]);
    exit;
}

// build public URL (auto-detect)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$publicUrl = rtrim($protocol . '://' . $host . $scriptDir, '/') . '/' . $relative;

echo json_encode(['success'=>true,'message'=>'Avatar updated','image_relative' => $relative, 'image_full' => $publicUrl]);
