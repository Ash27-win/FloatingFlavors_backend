<?php
// get_user_details_admin.php
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
// 2. Get User ID
$target_user_id = $_GET['user_id'] ?? 0;
if ($target_user_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid User ID']);
    exit;
}
try {
    // 3. Fetch Basic User Info + Profile Details (Photo & Phone)
    // Joined with user_profiles table to get image and phone
    $sql = "
        SELECT 
            u.id, 
            u.name, 
            u.email, 
            u.role, 
            u.created_at,
            up.profile_image,
            up.phone
        FROM users u
        LEFT JOIN user_profiles up ON u.id = up.user_id
        WHERE u.id = ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$target_user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    // Fix Image URL (Absolute Path)
    if (!empty($user['profile_image'])) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        // Use Host from request or fallback to localhost
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost'; 
        // Assuming API is in /floating_flavors_api/ folder 
        $user['profile_image_full'] = $protocol . $host . '/floating_flavors_api/' . $user['profile_image'];
    } else {
        $user['profile_image_full'] = null;
    }
    // 4. Fetch Addresses (Corrected Schema)
    // We fetch custom_label to avoid "null" issues in frontend concatenation
    $addrStmt = $pdo->prepare("SELECT id, house, area, city, pincode, landmark, label, custom_label, is_default FROM user_addresses WHERE user_id = ?");
    $addrStmt->execute([$target_user_id]);
    $rawAddresses = $addrStmt->fetchAll(PDO::FETCH_ASSOC);
    // Process addresses to generic formatted string
    $addresses = [];
    foreach ($rawAddresses as $addr) {
        $addr['custom_label'] = $addr['custom_label'] ?? ''; // Ensure not null
        $addr['full_address'] = implode(', ', array_filter([$addr['house'], $addr['area'], $addr['city'], $addr['pincode']]));
        $addresses[] = $addr;
    }
    // 5. Fetch Key Stats based on Role
    $stats = [];
    if ($user['role'] === 'Delivery') {
        // DELIVERY PARTNER STATS
        // Total Deliveries & Total Earnings (Value of goods delivered)
        $statsStmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_orders, 
                COALESCE(SUM(amount), 0) as total_spent, -- Reusing key names for frontend compatibility (actually earnings/value)
                MAX(created_at) as last_order_date
            FROM orders 
            WHERE delivery_partner_id = ? 
            AND status IN ('DELIVERED', 'COMPLETED')
        ");
        $statsStmt->execute([$target_user_id]);
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
        
        // Label hint for frontend (optional, or handle in UI)
        $stats['is_delivery_stats'] = true; 
    } else {
        // CUSTOMER STATS
        // Total Orders Placed & Total Money Spent
        $statsStmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_orders, 
                COALESCE(SUM(amount), 0) as total_spent,
                MAX(created_at) as last_order_date
            FROM orders 
            WHERE user_id = ?
        ");
        $statsStmt->execute([$target_user_id]);
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
        $stats['is_delivery_stats'] = false;
    }
    echo json_encode([
        'success' => true,
        'user' => $user,
        'addresses' => $addresses,
        'stats' => $stats
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>