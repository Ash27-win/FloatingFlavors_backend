<?php
require_once __DIR__ . "/../config.php";

$data = json_decode(file_get_contents("php://input"), true);
$email   = $data['email'] ?? '';
$password = $data['password'] ?? '';
$confirm  = $data['confirm_password'] ?? '';

if ($password !== $confirm) {
    echo json_encode(["success" => false, "message" => "Passwords mismatch"]);
    exit;
}

if (!preg_match('/^(?=.*[!@#$%^&*]).{8,}$/', $password)) {
    echo json_encode([
        "success" => false,
        "message" => "Min 8 chars & 1 special symbol required"
    ]);
    exit;
}

$hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
$stmt->execute([$hash, $email]);

echo json_encode(["success" => true, "message" => "Password updated"]);
