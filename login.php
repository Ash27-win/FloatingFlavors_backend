<?php
// login.php
require_once "config.php";

use Firebase\JWT\JWT;

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

$email = trim($input["email"] ?? "");
$password = trim($input["password"] ?? "");
$role = trim($input["role"] ?? "");

if ($email === "" || $password === "" || $role === "") {
    echo json_encode(["success" => false, "message" => "Please fill all fields and select role"]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, name, email, password_hash, role, loyalty_points FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(["success" => false, "message" => "User not found"]);
        exit;
    }

    if (!password_verify($password, $user["password_hash"])) {
        echo json_encode(["success" => false, "message" => "Incorrect password"]);
        exit;
    }

    if ($user["role"] !== $role) {
        echo json_encode([
            "success" => false,
            "message" => "Role mismatch. You are registered as " . $user["role"]
        ]);
        exit;
    }

    // Create access token (JWT)
    $now = time();
    $payload = [
        "iss" => "floating_flavors_api",
        "iat" => $now,
        "exp" => $now + ACCESS_TOKEN_EXP,
        "sub" => (int)$user["id"],
        "role" => $user["role"]
    ];

    if (!defined('JWT_SECRET') || empty(JWT_SECRET)) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Server JWT secret not configured."]);
        exit;
    }

    $accessToken = JWT::encode($payload, JWT_SECRET, 'HS256');

    // Create refresh token (opaque string), store in DB (never expires unless removed)
    $refreshToken = bin2hex(random_bytes(64));
    $deviceInfo = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $ins = $pdo->prepare("INSERT INTO refresh_tokens (user_id, token, device_info) VALUES (:uid, :token, :device)");
    $ins->execute([
        ':uid' => $user['id'],
        ':token' => $refreshToken,
        ':device' => $deviceInfo
    ]);

    // Return response similar to previous shape but with tokens included in data
    echo json_encode([
        "success" => true,
        "message" => "Login successful",
        "data" => [
            "user" => [
                "id" => (int)$user["id"],
                "name" => $user["name"],
                "email" => $user["email"],
                "role" => $user["role"],
                "loyalty_points" => (int)($user['loyalty_points'] ?? 0)
            ],
            "access_token" => $accessToken,
            "refresh_token" => $refreshToken,
            "access_expires_in" => ACCESS_TOKEN_EXP
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Server error", "error" => $e->getMessage()]);
}
