<?php
// mark_notification_read.php
require_once "config.php";
require_once "utils.php"; 

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$access_token = getBearerToken();
if (!$access_token) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Token missing']);
    exit;
}

try {
    $decoded = decode_jwt($access_token);
    $user_id = $decoded['sub'];
    $role = $decoded['role'];
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Invalid token']);
    exit;
}

// Optional: Specific Notification ID (If null, mark ALL as read)
$notif_id = isset($input['notification_id']) ? (int)$input['notification_id'] : null;

try {
    if ($notif_id) {
        // Mark Single
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :nid AND recipient_id = :uid AND recipient_role = :role");
        $stmt->execute([':nid' => $notif_id, ':uid' => $user_id, ':role' => $role]);
        $msg = "Notification marked read";
    } else {
        // Mark All
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE recipient_id = :uid AND recipient_role = :role AND is_read = 0");
        $stmt->execute([':uid' => $user_id, ':role' => $role]);
        $msg = "All notifications marked read";
    }

    echo json_encode(['success'=>true, 'message'=>$msg]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'DB error','error'=>$e->getMessage()]);
}
