<?php
// get_user_home.php
require_once 'config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Ensure PDO
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'error',
        'message' => 'No PDO connection found. Ensure config.php creates $pdo as PDO instance.',
        'data' => null
    ]);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function json_response($ok, $data = null, $message = '') {
    echo json_encode([
        'status' => $ok ? 'success' : 'error',
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

try {
    // Check Authorization header for Bearer token
    $authUserId = null;
    $headers = function_exists('apache_request_headers') ? apache_request_headers() : null;
    $authHeader = null;

    if ($headers) {
        foreach ($headers as $k => $v) {
            if (strtolower($k) === 'authorization') { $authHeader = $v; break; }
        }
    }
    if (!$authHeader && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    }

    if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $m)) {
        $token = $m[1];

        if (!defined('JWT_SECRET') || empty(JWT_SECRET)) {
            http_response_code(500);
            json_response(false, null, 'Server JWT secret not configured.');
        }

        try {
            $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
            if (isset($decoded->sub)) {
                $authUserId = intval($decoded->sub);
            } else {
                http_response_code(401);
                json_response(false, null, 'Invalid token payload (no subject).');
            }
        } catch (Exception $e) {
            http_response_code(401);
            json_response(false, null, 'Invalid or expired token: ' . $e->getMessage());
        }
    }

    // Fallback to query param user_id for legacy
    $user_id_param = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $user_id = $authUserId ?? ($user_id_param > 0 ? $user_id_param : 0);

    if ($user_id <= 0) {
        http_response_code(400);
        json_response(false, null, 'Missing user context. Provide Authorization Bearer token or ?user_id=.');
    }

    // Fetch user
    $stmt = $pdo->prepare("SELECT id, name, email, loyalty_points FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$user) {
        http_response_code(404);
        json_response(false, null, 'User not found.');
    }

    // Total completed orders
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS cnt
        FROM orders
        WHERE user_id = :uid
          AND status = 'completed'
    ");
    $stmt->execute([':uid' => $user_id]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalOrders = intval($r['cnt'] ?? 0);

    // Loyalty points rule (20 per completed order)
    $loyaltyPoints = $totalOrders * 20;

    // Persist loyalty_points if DB_NAME is available and column exists
    $shouldPersist = false;
    if (defined('DB_NAME') && DB_NAME) {
        $colStmt = $pdo->prepare("
            SELECT COUNT(*) AS cnt
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = :db
              AND TABLE_NAME = 'users'
              AND COLUMN_NAME = 'loyalty_points'
        ");
        $colStmt->execute([':db' => DB_NAME]);
        $colRes = $colStmt->fetch(PDO::FETCH_ASSOC);
        $exists = intval($colRes['cnt'] ?? 0) > 0;
        if ($exists) $shouldPersist = true;
    }

    if ($shouldPersist) {
        $updateStmt = $pdo->prepare("UPDATE users SET loyalty_points = :points WHERE id = :uid");
        $updateStmt->execute([
            ':points' => $loyaltyPoints,
            ':uid' => $user_id
        ]);
        $user['loyalty_points'] = $loyaltyPoints;
    } else {
        $user['loyalty_points'] = intval($user['loyalty_points'] ?? $loyaltyPoints);
    }

    // Featured items
    $featured = [];
    $sqlFeatured = "
        SELECT id, name, description, price, category, image_url, is_available, created_at
        FROM menu_items
        WHERE is_available = 1
        ORDER BY created_at DESC
        LIMIT 4
    ";
    $stmt = $pdo->query($sqlFeatured);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        if (isset($row['price'])) $row['price'] = (string)$row['price'];
        $featured[] = $row;
    }

    $quick_actions = [
        ['id' => 'browse_menu', 'title' => 'Browse Menu', 'icon' => 'menu'],
        ['id' => 'booking_catering', 'title' => 'Booking Catering', 'icon' => 'calendar']
    ];

    $banner = [
        'title' => 'Limited Time Offer!',
        'subtitle' => 'Get 20% off on catering orders above ₹5000',
        'cta' => 'Book Now'
    ];

    $data = [
    "userStats" => [
        "userId" => intval($user['id']),
        "userName" => $user['name'],
        "totalOrders" => $totalOrders,
        "loyaltyPoints" => $loyaltyPoints
    ],

    // EXACT key expected by UserHomeScreen
    "featured" => $featured,

    // EXACT key expected by UserHomeScreen
    "offer" => [
        "title" => $banner['title'],
        "subtitle" => $banner['subtitle']
    ]
];


    json_response(true, $data);

} catch (PDOException $e) {
    http_response_code(500);
    json_response(false, null, "Database error: " . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    json_response(false, null, "Server error: " . $e->getMessage());
}
