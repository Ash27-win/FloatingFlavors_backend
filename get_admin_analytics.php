<?php
// get_admin_analytics.php
require_once "middleware.php"; // Admin Token Auth

header("Content-Type: application/json");

// 1. Verify Admin
$admin_id = $GLOBALS['AUTH_USER_ID'] ?? 0;
$auth_role = $GLOBALS['AUTH_ROLE'] ?? '';

if ($admin_id <= 0 || $auth_role !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access only']);
    exit;
}

try {
    // 2. Total Revenue (Sum of all completed/delivered orders)
    $revStmt = $pdo->query("SELECT SUM(amount) FROM orders WHERE status IN ('DELIVERED', 'COMPLETED')");
    $totalRevenue = $revStmt->fetchColumn() ?: 0;

    // 3. Total Orders
    $ordStmt = $pdo->query("SELECT COUNT(*) FROM orders");
    $totalOrders = $ordStmt->fetchColumn() ?: 0;

    // 4. Active Orders (All except final states)
    $actStmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status NOT IN ('DELIVERED', 'COMPLETED', 'CANCELLED', 'REJECTED')");
    $activeOrders = $actStmt->fetchColumn() ?: 0;

    // 5. Total Users
    $usrStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'User'");
    $totalUsers = $usrStmt->fetchColumn() ?: 0;
    
    // 6. Low Stock Items (Threshold < 5)
    $stkStmt = $pdo->query("SELECT COUNT(*) FROM menu_items WHERE stock < 5 AND is_available = 1");
    $lowStockCount = $stkStmt->fetchColumn() ?: 0;

    // 7. 🔥 Recent Activity (Top 5 Orders) - NEW Improvement
    $recentStmt = $pdo->query("
        SELECT id, customer_name, amount, status, created_at 
        FROM orders 
        ORDER BY id DESC 
        LIMIT 5
    ");
    $recentOrders = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'total_revenue' => (float)$totalRevenue,
            'total_orders' => (int)$totalOrders,
            'active_orders' => (int)$activeOrders,
            'total_users' => (int)$totalUsers,
            'low_stock_count' => (int)$lowStockCount,
            'recent_orders' => $recentOrders // New Field
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB Error', 'error' => $e->getMessage()]);
}
?>
