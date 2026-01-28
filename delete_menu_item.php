<?php
header("Content-Type: application/json");
require "config.php"; // provides $pdo

// read id from POST form-data
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($id <= 0) {
    echo json_encode(["success" => false, "message" => "Missing or invalid 'id'"]);
    exit;
}

try {
    // 1) Fetch current image path (if any)
    $stmt = $pdo->prepare("SELECT image_url FROM menu_items WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $oldImage = $row['image_url'] ?? null;

    // 2) Delete DB row
    $del = $pdo->prepare("DELETE FROM menu_items WHERE id = ?");
    $del->execute([$id]);

    // 3) If row existed and had image, remove file (silently ignore errors)
    if ($oldImage) {
        $oldFile = __DIR__ . DIRECTORY_SEPARATOR . $oldImage;
        if (file_exists($oldFile) && is_file($oldFile)) {
            @unlink($oldFile);
        }
    }

    echo json_encode(["success" => true, "message" => "Item deleted successfully"]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "DB error", "error" => $e->getMessage()]);
}
?>
