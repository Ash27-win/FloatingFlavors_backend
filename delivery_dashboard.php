<?php
require_once "config.php";
header("Content-Type: application/json");

$deliveryPartnerId = $_GET['delivery_partner_id'] ?? null;

if (!$deliveryPartnerId) {
    echo json_encode(["success"=>false,"message"=>"delivery_partner_id required"]);
    exit;
}

/* ================= ACTIVE ORDER ================= */
/* Only orders already accepted by this delivery partner */
$activeStmt = $pdo->prepare("
    SELECT o.*, p.phone AS customer_phone
    FROM orders o
    JOIN user_profiles p ON p.user_id = o.user_id
    WHERE o.delivery_partner_id = ?
      AND o.status = 'OUT_FOR_DELIVERY'
    LIMIT 1
");
$activeStmt->execute([$deliveryPartnerId]);
$active = $activeStmt->fetch(PDO::FETCH_ASSOC);

/* ================= UPCOMING ORDERS ================= */
/* Orders assigned to THIS delivery partner but not yet started */
/* ================= UPCOMING ORDERS (ASSIGNED + UNASSIGNED POOL) ================= */
/* Orders assigned to THIS delivery partner OR unassigned (pool) */
$upcomingStmt = $pdo->prepare("
    SELECT o.*, p.phone AS customer_phone
    FROM orders o
    JOIN user_profiles p ON p.user_id = o.user_id
    WHERE (o.delivery_partner_id = ? OR o.delivery_partner_id IS NULL)
      AND o.status = 'CONFIRMED'
    ORDER BY o.created_at ASC
");
$upcomingStmt->execute([$deliveryPartnerId]);
$upcoming = $upcomingStmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= DELIVERY PARTNER NAME ================= */
$userStmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$userStmt->execute([$deliveryPartnerId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    "success" => true,
    "delivery_partner_name" => $user['name'] ?? '',
    "active_order" => $active ?: null,
    "upcoming_orders" => $upcoming
]);
