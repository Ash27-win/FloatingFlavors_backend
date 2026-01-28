<?php
// update_admin_settings.php
require_once "config.php";
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Method not allowed']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload) {
    echo json_encode(['success'=>false,'message'=>'Invalid JSON']);
    exit;
}

$admin_id = isset($payload['admin_id']) ? (int)$payload['admin_id'] : 0;
if ($admin_id <= 0) {
    echo json_encode(['success'=>false,'message'=>'admin_id required']);
    exit;
}

// pull fields safely (allow empty strings)
$full_name = isset($payload['full_name']) ? trim($payload['full_name']) : null;
$email = isset($payload['email']) ? trim($payload['email']) : null;
$phone = isset($payload['phone']) ? trim($payload['phone']) : null;
$business_name = isset($payload['business_name']) ? trim($payload['business_name']) : null;
$address = isset($payload['address']) ? trim($payload['address']) : null;

try {
    // Build dynamic update so we only update provided fields (prevents overwriting with NULL)
    $fields = [];
    $params = [':id' => $admin_id];

    if ($full_name !== null) { $fields[] = "full_name = :full_name"; $params[':full_name'] = $full_name; }
    if ($email !== null) { $fields[] = "email = :email"; $params[':email'] = $email; }
    if ($phone !== null) { $fields[] = "phone = :phone"; $params[':phone'] = $phone; }
    if ($business_name !== null) { $fields[] = "business_name = :business_name"; $params[':business_name'] = $business_name; }
    if ($address !== null) { $fields[] = "address = :address"; $params[':address'] = $address; }

    if (empty($fields)) {
        echo json_encode(['success'=>false,'message'=>'No fields to update']);
        exit;
    }

    $sql = "UPDATE admins SET " . implode(", ", $fields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success'=>true,'message'=>'Profile updated']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'DB error','error'=>$e->getMessage()]);
}
