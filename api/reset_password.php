<?php
header("Content-Type: application/json");
session_start(); // Critical to read session
require_once __DIR__ . "/../config.php";
$data = json_decode(file_get_contents("php://input"), true);
$email    = trim($data['email'] ?? '');
$password = $data['password'] ?? '';
$confirm  = $data['confirm_password'] ?? '';
// 1. Session Security Check
if (!isset($_SESSION['reset_email']) || $_SESSION['reset_email'] !== $email) {
    echo json_encode(["success" => false, "message" => "Unauthorized. Please verify OTP first."]);
    exit;
}
// 2. Validate Password Match
if ($password !== $confirm) {
    echo json_encode(["success" => false, "message" => "Passwords mismatch"]);
    exit;
}
// 3. Validate Length (Min 8)
if (strlen($password) < 8) {
    echo json_encode(["success" => false, "message" => "Password must be at least 8 characters"]);
    exit;
}
// 4. Validate Symbol (Matches Figma Requirement)
if (!preg_match('/[\W_]/', $password)) {
    echo json_encode(["success" => false, "message" => "Password must include a symbol (@#$%)"]);
    exit;
}
// 5. Update Password
$hash = password_hash($password, PASSWORD_BCRYPT);
$stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
$stmt->execute([$hash, $email]);
// 6. Clear Session
unset($_SESSION['reset_email']);
echo json_encode(["success" => true, "message" => "Password updated successfully"]);
?>