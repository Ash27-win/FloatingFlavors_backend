<?php
// change_admin_password.php
require_once "config.php";

header('Content-Type: application/json; charset=utf-8');

// Only POST allowed
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}

// Read JSON body
$payload = json_decode(file_get_contents("php://input"), true);
if (!$payload) {
    echo json_encode(["success" => false, "message" => "Invalid JSON"]);
    exit;
}

// Inputs (trim to avoid hidden spaces)
$admin_id = isset($payload['admin_id']) ? (int)$payload['admin_id'] : 0;
$old_password = isset($payload['old_password']) ? trim($payload['old_password']) : '';
$new_password = isset($payload['new_password']) ? trim($payload['new_password']) : '';

if ($admin_id <= 0 || $old_password === '' || $new_password === '') {
    echo json_encode(["success" => false, "message" => "admin_id, old_password, new_password required"]);
    exit;
}

// Minimal new password rule
if (strlen($new_password) < 6) {
    echo json_encode(["success" => false, "message" => "New password must be at least 6 characters"]);
    exit;
}

try {
    // Fetch current stored password
    $stmt = $pdo->prepare("SELECT password_hash FROM admins WHERE id = ? LIMIT 1");
    $stmt->execute([$admin_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "DB error", "error" => $e->getMessage()]);
    exit;
}

if (!$row) {
    echo json_encode(["success" => false, "message" => "Admin not found"]);
    exit;
}

$stored = $row['password_hash'] ?? '';

// If stored hash is empty or looks like a placeholder, tell operator
if (empty($stored) || strpos($stored, 'REPLACE_WITH_HASH') === 0) {
    echo json_encode(["success" => false, "message" => "Password not set correctly for this account. Please reset via admin."]);
    exit;
}

// Normalize for checking
$matched = false;
$need_migrate_to_bcrypt = false;

// Case A: bcrypt (recommended)
if (strlen($stored) > 3 && (strpos($stored, '$2y$') === 0 || strpos($stored, '$2a$') === 0 || strpos($stored, '$argon2') === 0)) {
    if (password_verify($old_password, $stored)) {
        $matched = true;
        // Optionally rehash if algorithm parameters changed / needs upgrade
        if (password_needs_rehash($stored, PASSWORD_BCRYPT)) {
            $need_migrate_to_bcrypt = true;
        }
    }
}
// Case B: md5 legacy
elseif (strlen($stored) === 32 && ctype_xdigit($stored)) {
    if (hash('md5', $old_password) === $stored) {
        $matched = true;
        $need_migrate_to_bcrypt = true; // migrate to bcrypt immediately
    }
}
// Case C: plain text legacy (not recommended)
else {
    if ($old_password === $stored) {
        $matched = true;
        $need_migrate_to_bcrypt = true; // migrate to bcrypt immediately
    }
}

if (!$matched) {
    // no match
    echo json_encode(["success" => false, "message" => "Old password incorrect"]);
    exit;
}

// If matched and migration is needed, re-hash to bcrypt now
if ($need_migrate_to_bcrypt) {
    try {
        $newHashForOld = password_hash($old_password, PASSWORD_BCRYPT);
        $u = $pdo->prepare("UPDATE admins SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $u->execute([$newHashForOld, $admin_id]);
        // update stored variable so future verify uses new hash if needed
        $stored = $newHashForOld;
    } catch (Exception $e) {
        // non-fatal — we can still proceed to update to new password below
    }
}

// Hash the new password and update
$newHash = password_hash($new_password, PASSWORD_BCRYPT);

try {
    $update = $pdo->prepare("UPDATE admins SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $update->execute([$newHash, $admin_id]);
    echo json_encode(["success" => true, "message" => "Password updated successfully"]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "DB update failed", "error" => $e->getMessage()]);
    exit;
}
