<?php
// get_orders.php
require_once 'config.php'; // your config.php sets $pdo and headers

try {
    // In get_orders.php, modify the SELECT query:
$stmt = $pdo->prepare("SELECT id, customer_name, status, time_ago, distance, amount, created_at, delivery_partner_id FROM orders ORDER BY id DESC");
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];

    $now = new DateTime('now', new DateTimeZone('UTC'));

    foreach ($orders as $row) {
        // items for this order
        $itemStmt = $pdo->prepare("SELECT name, qty FROM order_items WHERE order_id = :oid");
        $itemStmt->execute(['oid' => $row['id']]);
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        $normalizedItems = array_map(function($it) {
            return [
                'name' => $it['name'],
                'qty' => (int)$it['qty']
            ];
        }, $items);

        // created_at -> ISO8601 in UTC
        $createdAtSql = $row['created_at']; // e.g. "2025-12-08 13:05:00"
        $createdIso = null;
        $timeAgo = $row['time_ago']; // default from DB if present

        if ($createdAtSql) {
            // Convert DB timestamp to DateTime, assume DB stores UTC or server local (adjust if needed)
            $dt = new DateTime($createdAtSql, new DateTimeZone('UTC'));
            $createdIso = $dt->format(DATE_ATOM); // ISO 8601 with timezone

            // compute time_ago on server (fallback)
            $diffSeconds = $now->getTimestamp() - $dt->getTimestamp();
            if ($diffSeconds < 60) {
                $timeAgo = 'just now';
            } elseif ($diffSeconds < 3600) {
                $m = (int)floor($diffSeconds / 60);
                $timeAgo = $m . ' min' . ($m > 1 ? 's' : '') . ' ago';
            } elseif ($diffSeconds < 86400) {
                $h = (int)floor($diffSeconds / 3600);
                $timeAgo = $h . ' hr' . ($h > 1 ? 's' : '') . ' ago';
            } elseif ($diffSeconds < 604800) {
                $d = (int)floor($diffSeconds / 86400);
                $timeAgo = $d . ' day' . ($d > 1 ? 's' : '') . ' ago';
            } else {
                $timeAgo = $dt->format('d M, Y');
            }
        }

        $result[] = [
            'id' => (string)$row['id'],
            'customer_name' => $row['customer_name'],
            'items' => $normalizedItems,
            'status' => $row['status'],
            'created_at' => $createdIso,
            'time_ago' => $timeAgo,
            'distance' => $row['distance'],
            'amount' => $row['amount'],
            'delivery_partner_id' => $row['delivery_partner_id'] // Add this line
        ];
    }

    echo json_encode([
        'success' => true,
        'message' => 'Orders fetched',
        'data' => $result
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
