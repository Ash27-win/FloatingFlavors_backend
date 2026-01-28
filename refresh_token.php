<?php
require_once 'config.php';
use Firebase\JWT\JWT;

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['refresh_token'])) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Missing refresh_token']);
    exit;
}
$rt = $input['refresh_token'];

try {
    $stmt = $pdo->prepare("SELECT user_id FROM refresh_tokens WHERE token = :t LIMIT 1");
    $stmt->execute([':t'=>$rt]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(401);
        echo json_encode(['success'=>false,'message'=>'Invalid refresh token']);
        exit;
    }
    $userId = (int)$row['user_id'];
    $now = time();
    $payload = [
        'iss' => 'floating_flavors_api',
        'iat' => $now,
        'exp' => $now + ACCESS_TOKEN_EXP,
        'sub' => $userId
    ];
    $newAccess = JWT::encode($payload, JWT_SECRET, 'HS256');
    echo json_encode(['success'=>true,'access_token'=>$newAccess,'access_expires_in'=>ACCESS_TOKEN_EXP]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Server error','error'=>$e->getMessage()]);
}
