<?php
header("Content-Type: application/json");
session_start(); // <--- CRITICAL: Start Session
require_once __DIR__ . "/../config.php";
$data = json_decode(file_get_contents("php://input"), true);
$email = trim($data['email'] ?? '');
$otp   = trim($data['otp'] ?? '');
// Check OTP in DB using MySQL NOW() for consistency
$stmt = $pdo->prepare("SELECT id FROM password_reset_otp WHERE email = ? AND otp = ? AND is_used = 0 AND expires_at >= NOW()");
$stmt->execute([$email, $otp]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo json_encode(["success" => false, "message" => "Invalid or expired OTP"]);
    exit;
}
// Mark OTP as used
$pdo->prepare("UPDATE password_reset_otp SET is_used = 1 WHERE id = ?")->execute([$row['id']]);
// Save Verified State to Session
$_SESSION['reset_email'] = $email;
echo json_encode(["success" => true, "message" => "OTP verified"]);
?>