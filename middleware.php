<?php
require 'config.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$headers = function_exists('apache_request_headers') ? apache_request_headers() : null;
$auth = null;
if ($headers && isset($headers['Authorization'])) $auth = $headers['Authorization'];
if (!$auth && isset($_SERVER['HTTP_AUTHORIZATION'])) $auth = $_SERVER['HTTP_AUTHORIZATION'];

if (!$auth || !preg_match('/Bearer\s(\S+)/', $auth, $m)) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Missing token']);
    exit;
}

$token = $m[1];

try {
    $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
    // expose user id and role to downstream code
    $GLOBALS['AUTH_USER_ID'] = $decoded->sub ?? null;
    $GLOBALS['AUTH_ROLE'] = $decoded->role ?? null;
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Invalid token','error'=>$e->getMessage()]);
    exit;
}
