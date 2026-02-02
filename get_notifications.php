<?php
// get_notifications.php
require_once "config.php";
require_once "utils.php"; // Assuming authenticate() is here or similar

header('Content-Type: application/json; charset=utf-8');

// 1. Authenticate User
$access_token = getBearerToken();
if (!$access_token) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Token missing']);
    exit;
}

try {
    $decoded = decode_jwt($access_token);
    $user_id = $decoded['sub'];
    $role = $decoded['role']; // Important: Notifications are role-specific
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Invalid token']);
    exit;
}

// 2. Pagination
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

try {
    // 3. Fetch Notifications for this User & Role
    $stmt = $pdo->prepare("
        SELECT id, title, body, type, reference_id, is_read, created_at 
        FROM notifications 
        WHERE recipient_id = :uid AND recipient_role = :role 
        ORDER BY created_at DESC 
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':role', $role, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Get Unread Count (Global for this user/role)
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM notifications 
        WHERE recipient_id = :uid AND recipient_role = :role AND is_read = 0
    ");
    $countStmt->execute([':uid' => $user_id, ':role' => $role]);
    $unreadCount = (int)$countStmt->fetchColumn();

    // 5. Pretty print time ago (optional but nice)
    foreach ($notifications as &$notif) {
        $notif['time_ago'] = time_elapsed_string($notif['created_at']);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Notifications fetched',
        'data' => $notifications,
        'unread_count' => $unreadCount
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'DB error','error'=>$e->getMessage()]);
}
