<?php
// update_admin_profile.php
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
    // 🔍 1. Find the REAL Admin ID in `admins` table using Auth Email
    // Fetch Email from Users table using Auth ID
    $userStmt = $pdo->prepare("SELECT email, name FROM users WHERE id = :uid");
    $userStmt->execute([':uid' => $admin_id]);
    $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$userRow) {
        echo json_encode(['success'=>false,'message'=>'User not found']);
        exit;
    }
    
    $emailVal = $userRow['email'];
    $defaultName = $userRow['name'];

    // Check if profile exists in `admins`
    $profStmt = $pdo->prepare("SELECT id FROM admins WHERE email = :email");
    $profStmt->execute([':email' => $emailVal]);
    $profRow = $profStmt->fetch(PDO::FETCH_ASSOC);

    if (!$profRow) {
        // If profile doesn't exist, create it now!
        $ins = $pdo->prepare("INSERT INTO admins (full_name, email, role, business_name, created_at, updated_at) VALUES (:name, :email, 'Admin', 'My Cloud Kitchen', NOW(), NOW())");
        $ins->execute([':name' => $defaultName, ':email' => $emailVal]);
        $targetProfileId = $pdo->lastInsertId();
    } else {
        $targetProfileId = $profRow['id'];
    }

    // 🚀 2. Now Update the Correct Profile ID
    $fields = [];
    $params = [':id' => $targetProfileId];

    if ($full_name !== null) { $fields[] = "full_name = :full_name"; $params[':full_name'] = $full_name; }
    // Email update blocked to prevent mismatch (kept consistent with Auth)
    if ($phone !== null) { $fields[] = "phone = :phone"; $params[':phone'] = $phone; }
    if ($business_name !== null) { $fields[] = "business_name = :business_name"; $params[':business_name'] = $business_name; }
    if ($address !== null) { $fields[] = "address = :address"; $params[':address'] = $address; }

    if (empty($fields)) {
        echo json_encode(['success'=>true,'message'=>'No changes detected']);
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
?>
