<?php
// get_driver_earnings.php
require_once "middleware.php"; // Token Auth

header("Content-Type: application/json");

// 1. Auth Check
$user_id = $GLOBALS['AUTH_USER_ID'] ?? 0;
$role = $GLOBALS['AUTH_ROLE'] ?? '';

// Allow Admin to view any driver, or Driver to view self.
$target_driver_id = isset($_GET['driver_id']) ? (int)$_GET['driver_id'] : $user_id;

if ($role === 'Delivery') {
    // Driver can only see own earnings
    if ($target_driver_id !== $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
} elseif ($role !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // 2. Calculate Earnings
    // Logic: Sum of order amounts for orders delivered by this partner.
    // (In reality, driver earnings might be a % of this, but here we show 'Total Value Handled' or assume 100% for MVP)
    // Let's assume for now we just show "Value Delivered".
    
    $sql = "
        SELECT 
            COUNT(*) as total_deliveries, 
            SUM(amount) as total_earnings 
        FROM orders 
        WHERE delivery_partner_id = ? 
        AND status IN ('DELIVERED', 'COMPLETED')
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$target_driver_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'driver_id' => $target_driver_id,
        'total_deliveries' => (int)($stats['total_deliveries'] ?? 0),
        'total_earnings' => (float)($stats['total_earnings'] ?? 0)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB Error', 'error' => $e->getMessage()]);
}
?>
