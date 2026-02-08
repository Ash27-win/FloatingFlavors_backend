<?php
// get_users_by_role.php
require_once "middleware.php"; // Admin Token Auth
header("Content-Type: application/json");
// 1. Verify Admin Access
$admin_id = $GLOBALS['AUTH_USER_ID'] ?? 0;
$auth_role = $GLOBALS['AUTH_ROLE'] ?? '';
if ($admin_id <= 0 || $auth_role !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access only']);
    exit;
}
// 2. Get Parameters
$role = $_GET['role'] ?? '';
$search = $_GET['search'] ?? ''; // Added Search

// Validate
$allowedRoles = ['User', 'Delivery'];
if (!in_array($role, $allowedRoles)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid role. Allowed: User, Delivery']);
    exit;
}
try {
    // 3. Fetch Users
    // JOIN with user_profiles to get the image
    $sql = "
        SELECT u.id, u.name, u.email, u.role, u.created_at, up.profile_image 
        FROM users u 
        LEFT JOIN user_profiles up ON u.id = up.user_id
        WHERE u.role = :role 
    ";
    
    $params = [':role' => $role];

    // Search Logic (Backend Ready for when you add Search Bar)
    if (!empty($search)) {
        $sql .= " AND (u.name LIKE :search OR u.email LIKE :search)";
        $params[':search'] = "%$search%";
    }

    $sql .= " ORDER BY u.id DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Generate Full Image URLs
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    foreach ($users as &$user) {
        if (!empty($user['profile_image'])) {
            $user['profile_image_full'] = $protocol . $host . '/floating_flavors_api/' . $user['profile_image'];
        } else {
            $user['profile_image_full'] = null;
        }
    }
    echo json_encode([
        'success' => true,
        'count' => count($users),
        'role' => $role,
        'data' => $users
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error', 
        'error' => $e->getMessage()
    ]);
}
?>