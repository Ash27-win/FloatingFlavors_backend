<?php
require_once 'middleware.php'; // Auth required

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$fcmToken = trim($input['fcm_token'] ?? '');
$deviceInfo = trim($input['device_info'] ?? 'Unknown');

if (empty($fcmToken)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'fcm_token required']);
    exit;
}

$userId = $GLOBALS['AUTH_USER_ID'];
$role = $GLOBALS['AUTH_ROLE'] ?? 'User'; // Default to User if role missing

try {
    // Check if duplicate token exists for this user/role
    $check = $pdo->prepare("SELECT id FROM fcm_tokens WHERE user_id = ? AND role = ? AND token = ? LIMIT 1");
    $check->execute([$userId, $role, $fcmToken]);
    
    if ($check->rowCount() > 0) {
        // Just update timestamp
        $update = $pdo->prepare("UPDATE fcm_tokens SET last_updated = NOW(), device_info = ? WHERE user_id = ? AND role = ? AND token = ?");
        $update->execute([$deviceInfo, $userId, $role, $fcmToken]);
    } else {
        // Insert new token
        $insert = $pdo->prepare("INSERT INTO fcm_tokens (user_id, role, token, device_info) VALUES (?, ?, ?, ?)");
        $insert->execute([$userId, $role, $fcmToken, $deviceInfo]);
    }

    echo json_encode(['success' => true, 'message' => 'Token updated']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB Error', 'error' => $e->getMessage()]);
}
