<?php
require_once 'config.php';
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['refresh_token'])) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Missing refresh_token']);
    exit;
}
$rt = $input['refresh_token'];
try {
    $del = $pdo->prepare("DELETE FROM refresh_tokens WHERE token = :t");
    $del->execute([':t'=>$rt]);
    echo json_encode(['success'=>true,'message'=>'Logged out']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Server error','error'=>$e->getMessage()]);
}
