<?php
header("Content-Type: application/json");
require "config.php"; // provides $pdo

// id and is_available expected from form-data or x-www-form-urlencoded
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$is_available = isset($_POST['is_available']) ? intval($_POST['is_available']) : null;

if ($id <= 0 || ($is_available !== 0 && $is_available !== 1 && $is_available !== null)) {
    echo json_encode(["success" => false, "message" => "Missing or invalid parameters"]);
    exit;
}

// if is_available is not provided, toggle current value
try {
    if ($is_available === null) {
        // fetch current value, then toggle
        $sel = $pdo->prepare("SELECT is_available FROM menu_items WHERE id = ?");
        $sel->execute([$id]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo json_encode(["success" => false, "message" => "Item not found"]);
            exit;
        }
        $current = intval($row['is_available']);
        $is_available = ($current === 1) ? 0 : 1;
    }

    // update value
    $upd = $pdo->prepare("UPDATE menu_items SET is_available = ? WHERE id = ?");
    $upd->execute([$is_available, $id]);

    echo json_encode(["success" => true, "message" => "Availability updated", "is_available" => $is_available]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "DB error", "error" => $e->getMessage()]);
}
?>
