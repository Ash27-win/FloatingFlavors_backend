<?php
require_once __DIR__ . "/../config.php";

$data = json_decode(file_get_contents("php://input"), true);
$email = $data['email'] ?? '';
$otp   = $data['otp'] ?? '';

$stmt = $pdo->prepare(
    "SELECT id FROM password_reset_otp
     WHERE email = ? AND otp = ?
     AND is_used = 0 AND expires_at >= NOW()"
);
$stmt->execute([$email, $otp]);

$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo json_encode(["success" => false, "message" => "Invalid or expired OTP"]);
    exit;
}

$id = $row['id'];

// Invalidate other OTPs
$pdo->prepare(
    "UPDATE password_reset_otp SET is_used = 1 WHERE email = ?"
)->execute([$email]);

echo json_encode(["success" => true, "message" => "OTP verified"]);
