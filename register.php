<?php
// register.php
require_once "config.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

$name = trim($input["name"] ?? "");
$email = trim($input["email"] ?? "");
$password = trim($input["password"] ?? "");
$confirmPassword = trim($input["confirmPassword"] ?? "");
$role = trim($input["role"] ?? "");

if ($name === "" || $email === "" || $password === "" || $confirmPassword === "" || $role === "") {
    echo json_encode(["success" => false, "message" => "Please fill all fields"]);
    exit;
}

if (!in_array($role, ["User", "Admin", "Delivery"])) {
    echo json_encode(["success" => false, "message" => "Invalid role"]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["success" => false, "message" => "Invalid email"]);
    exit;
}

if ($password !== $confirmPassword) {
    echo json_encode(["success" => false, "message" => "Passwords do not match"]);
    exit;
}

// Hash password (bcrypt)
$passwordHash = password_hash($password, PASSWORD_BCRYPT);

// Check if email already exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);

if ($stmt->fetch()) {
    echo json_encode(["success" => false, "message" => "Email already registered"]);
    exit;
}

// Insert user
$stmt = $pdo->prepare("
    INSERT INTO users (name, email, password_hash, role)
    VALUES (?, ?, ?, ?)
");

try {
    $stmt->execute([$name, $email, $passwordHash, $role]);
    $user_id = $pdo->lastInsertId(); // Capture ID

    // === 🔔 WELCOME NOTIFICATION (NEW) ===
    require_once "send_notification_helper.php";
    sendNotification(
        $user_id,
        $role,
        "Welcome to Floating Flavors! 👋",
        "Thanks for joining us, $name! Explore our menu and order your first meal.",
        "WELCOME_MSG",
        $user_id, // Reference to self
        ['screen' => 'Home']
    );
    // =====================================

    echo json_encode(["success" => true, "message" => "Registration successful"]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Registration failed",
        "error" => $e->getMessage()
    ]);
}
