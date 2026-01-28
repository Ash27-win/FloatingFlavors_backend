<?php
// get_order_detail.php
require_once "config.php";

try {
    // GET param ?id=123 or body JSON with {"id":123}
    $id = $_GET['id'] ?? null;
    if (!$id) {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;
    }

    if (!$id) {
        http_response_code(400);
        echo json_encode(['success'=>false, 'message'=>'id required']);
        exit;
    }

    // fetch order
    $stmt = $pdo->prepare("
    SELECT 
        o.id,
        o.customer_name,
        o.status,
        o.time_ago,
        o.distance,
        o.amount,
        o.created_at,
        o.delivery_partner_id,
        CONCAT(
            u.house, ', ',
            u.area, ', ',
            u.city, ' - ',
            u.pincode,
            IF(u.landmark IS NOT NULL, CONCAT(' (', u.landmark, ')'), '')
        ) AS delivery_address
    FROM orders o
    JOIN user_addresses u 
        ON u.id = o.user_address_id
    WHERE o.id = :id
    LIMIT 1
");


    $stmt->execute([':id' => intval($id)]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        echo json_encode(['success'=>false, 'message'=>'Order not found']);
        exit;
    }

    // fetch items
    $itemStmt = $pdo->prepare("SELECT name, qty FROM order_items WHERE order_id = :oid");
    $itemStmt->execute([':oid' => intval($id)]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    // normalize
    $itemList = array_map(function($it){ return ['name'=>$it['name'],'qty'=> (int)$it['qty']]; }, $items);

    // created_at ISO and time_ago
    $now = new DateTime('now', new DateTimeZone('UTC'));
    $createdAt = new DateTime($order['created_at'], new DateTimeZone('UTC'));
    $createdIso = $createdAt->format(DateTime::ATOM);
    $diff = $now->getTimestamp() - $createdAt->getTimestamp();
    if ($diff < 60) $timeAgo = "just now";
    elseif ($diff < 3600) $timeAgo = floor($diff/60) . " mins ago";
    elseif ($diff < 86400) $timeAgo = floor($diff/3600) . " hrs ago";
    else $timeAgo = floor($diff/86400) . " days ago";

    $resp = [
    'success' => true,
    'message' => 'Order fetched',
    'data' => [
        'id' => (string)$order['id'],
        'customer_name' => $order['customer_name'],
        'items' => $itemList,
        'status' => $order['status'],
        'created_at' => $createdIso,
        'time_ago' => $timeAgo,
        'distance' => $order['distance'],
        'amount' => $order['amount'],
        'delivery_partner_id' => $order['delivery_partner_id'] ? (string)$order['delivery_partner_id'] : null,
        'delivery_address' => $order['delivery_address']
    ]
];

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($resp, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Server error','error'=>$e->getMessage()]);
}
