<?php
// get_orders_server.php
require_once "middleware.php";

if (($GLOBALS['AUTH_ROLE'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access only']);
    exit;
}

try {
    // Read query params
    $query = isset($_GET['query']) ? trim($_GET['query']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    if ($limit <= 0) $limit = 20;
    if ($offset < 0) $offset = 0;

    // Build WHERE clauses
    $wheres = [];
    $params = [];

    if ($status !== '') {
        // normalize some values
        $statusLower = strtolower($status);
        if ($statusLower === 'done') $statusLower = 'completed';
        $wheres[] = "LOWER(o.status) = :status";
        $params[':status'] = $statusLower;
    }

    if ($query !== '') {
        // We'll search in order id, customer_name, and item names
        // Use LIKE for items by joining subquery
        $wheres[] = "(CAST(o.id AS CHAR) LIKE :q OR LOWER(o.customer_name) LIKE :q OR EXISTS(
            SELECT 1 FROM order_items oi WHERE oi.order_id = o.id AND LOWER(oi.name) LIKE :q
        ))";
        $params[':q'] = '%' . strtolower($query) . '%';
    }

    $whereSql = '';
    if (!empty($wheres)) {
        $whereSql = 'WHERE ' . implode(' AND ', $wheres);
    }

    // First: total count for given filters (for paging)
    $countSql = "SELECT COUNT(*) as cnt FROM orders o $whereSql";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // Then: get paged items
    // We fetch orders and items in two queries for clarity
    $sql = "SELECT o.id, o.customer_name, o.status, o.distance, o.amount, o.created_at
            FROM orders o
            $whereSql
            ORDER BY o.id DESC
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);

    // Bind dynamic params
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $now = new DateTime('now', new DateTimeZone('UTC'));
    $result = [];

    foreach ($orders as $row) {
        // fetch items for each order
        $itemStmt = $pdo->prepare("SELECT name, qty FROM order_items WHERE order_id = :oid");
        $itemStmt->execute([':oid' => $row['id']]);
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        $itemList = array_map(function($i) {
            return ['name' => $i['name'], 'qty' => (int)$i['qty']];
        }, $items);

        // created_at ISO + time_ago
        $createdAt = new DateTime($row['created_at'], new DateTimeZone('UTC'));
        $createdIso = $createdAt->format(DateTime::ATOM);
        $diff = $now->getTimestamp() - $createdAt->getTimestamp();
        if ($diff < 60) $timeAgo = "just now";
        elseif ($diff < 3600) $timeAgo = floor($diff/60) . " mins ago";
        elseif ($diff < 86400) $timeAgo = floor($diff/3600) . " hrs ago";
        else $timeAgo = floor($diff/86400) . " days ago";

        $result[] = [
            'id' => (string)$row['id'],
            'customer_name' => $row['customer_name'],
            'items' => $itemList,
            'status' => $row['status'],
            'created_at' => $createdIso,
            'time_ago' => $timeAgo,
            'distance' => $row['distance'],
            'amount' => $row['amount']
        ];
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'message' => 'Orders fetched',
        'data' => $result,
        'meta' => [
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()]);
}
