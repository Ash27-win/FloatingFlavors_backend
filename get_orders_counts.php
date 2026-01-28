<?php
// get_orders_counts.php
require_once "config.php";

try {
    // Count pending
    $stmt = $pdo->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN LOWER(status) = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN LOWER(status) = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN LOWER(status) IN ('completed','done') THEN 1 ELSE 0 END) as completed
      FROM orders
    ");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'message' => 'counts fetched',
        'data' => [
            'total' => (int)$row['total'],
            'pending' => (int)$row['pending'],
            'active' => (int)$row['active'],
            'completed' => (int)$row['completed']
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()]);
}
