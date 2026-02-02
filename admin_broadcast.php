<?php
// admin_broadcast.php
require_once "config.php";
require_once "send_notification_helper.php";
require_once "utils.php"; // for getBearerToken()

header('Content-Type: application/json');

// 1. Authenticate Admin
$token = getBearerToken();
if (!$token) {
    http_response_code(401);
    echo json_encode(['success'=>false, 'message'=>'Unauthorized']);
    exit;
}
// Validate Token Role = Admin
try {
    $decoded = decode_jwt($token);
    if ($decoded['role'] !== 'Admin') {
        throw new Exception("Not an admin");
    }
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['success'=>false, 'message'=>'Forbidden']);
    exit;
}

// 2. Get Input
$input = json_decode(file_get_contents('php://input'), true);
$title = $input['title'] ?? 'Special Offer! 🎉';
$body = $input['body'] ?? 'Check out our latest menu additions.';
$targetRole = $input['target_role'] ?? 'User'; // User, Delivery

if (empty($title) || empty($body)) {
    echo json_encode(['success'=>false, 'message'=>'Title and body required']);
    exit;
}

// 3. Fetch All Users of Target Role
// We need to send to each one.
try {
    // Ideally, for mass broadcast, we use FCM Topics (e.g. subscribe everyone to "promotions").
    // But since our helper uses specific tokens, we fetch all IDs.
    // Optimization: In production, use "Topics". For now, Loop IDs.
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE role = ?");
    $stmt->execute([$targetRole]);
    $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $count = 0;
    foreach ($users as $uid) {
        sendNotification(
            $uid,
            $targetRole,
            $title,
            $body,
            "BROADCAST",
            0,
            ['screen' => 'Home']
        );
        $count++;
    }

    echo json_encode(['success'=>true, 'message'=>"Broadcast sent to $count $targetRole(s)"]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'message'=>'Broadcast failed', 'error'=>$e->getMessage()]);
}
