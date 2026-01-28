<?php
header("Content-Type: application/json");
require_once __DIR__ . "/config.php";

try {

    $uid = (int)$_POST['user_id'];
    $name = trim($_POST['name']);
    $email = $_POST['email'] ?? null;
    $phone = $_POST['phone'];
    $alt = $_POST['alt_phone'] ?? null;

    $pincode = $_POST['pincode'];
    $city = $_POST['city'];
    $house = $_POST['house'];
    $area = $_POST['area'];
    $landmark = $_POST['landmark'] ?? null;

    /* ---------- IMAGE ---------- */
    $imgPath = null;
    if (!empty($_FILES['profile_image']['name'])) {
        $dir = __DIR__ . "/uploads/profile/";
        if (!is_dir($dir)) mkdir($dir, 0777, true);

        $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $file = "profile_{$uid}_" . time() . "." . $ext;

        if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], $dir . $file)) {
            throw new Exception("Image upload failed");
        }

        $imgPath = "uploads/profile/" . $file;
    }

    /* ---------- USERS (NAME) ---------- */
    $stmt = $pdo->prepare("UPDATE users SET name=?, email=? WHERE id=?");
    $stmt->execute([$name, $email, $uid]);

    /* ---------- PROFILE ---------- */
    if ($imgPath) {
        $stmt = $pdo->prepare(
            "UPDATE user_profiles SET phone=?, alt_phone=?, profile_image=? WHERE user_id=?"
        );
        $stmt->execute([$phone, $alt, $imgPath, $uid]);
    } else {
        $stmt = $pdo->prepare(
            "UPDATE user_profiles SET phone=?, alt_phone=? WHERE user_id=?"
        );
        $stmt->execute([$phone, $alt, $uid]);
    }

    /* ---------- ADDRESS ---------- */
    $stmt = $pdo->prepare(
        "UPDATE user_addresses
         SET pincode=?, city=?, house=?, area=?, landmark=?
         WHERE user_id=? AND is_default=1"
    );
    $stmt->execute([$pincode, $city, $house, $area, $landmark, $uid]);

    echo json_encode([
        "success" => true,
        "message" => "Profile updated successfully",
        "profile_image" => $imgPath
    ]);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
    exit;
}