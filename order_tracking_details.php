<?php
require_once "config.php";
header("Content-Type: application/json");

/* ================= INPUT ================= */
$order_id = $_GET['order_id'] ?? null;
$type     = $_GET['type'] ?? null;

if (!$order_id || !$type) {
    echo json_encode([
        "success" => false,
        "message" => "Missing order_id or type"
    ]);
    exit;
}

/* ================= COMMON UI STEPS ================= */
$uiSteps = ["CONFIRMED", "PREPARING", "OUT_FOR_DELIVERY", "DELIVERED"];

/* ============================================================
   INDIVIDUAL ORDER TRACKING
============================================================ */
if ($type === "INDIVIDUAL") {

    $sql = "
        SELECT 
            o.id,
            o.status,
            o.created_at,
            CONCAT(
                ua.house, ', ',
                ua.area, ', ',
                ua.city, ' - ',
                ua.pincode
            ) AS full_address,
            ua.landmark,
            ua.latitude,
            ua.longitude,
            dp.name AS delivery_person_name,
            dp.vehicle_number
        FROM orders o
        LEFT JOIN user_addresses ua 
            ON ua.id = o.user_address_id
        LEFT JOIN delivery_person dp 
            ON dp.order_id = o.id
        WHERE o.id = :order_id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['order_id' => $order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode([
            "success" => false,
            "message" => "Order not found"
        ]);
        exit;
    }

    /* -------- STATUS MAP -------- */
    $dbStatus = strtoupper($order['status']);

$statusMap = [
    "PENDING"           => "CONFIRMED",
    "CONFIRMED"         => "PREPARING",
    "PREPARING"         => "PREPARING",
    "OUT_FOR_DELIVERY"  => "OUT_FOR_DELIVERY",
    "DELIVERED"         => "DELIVERED",
    "COMPLETED"         => "DELIVERED"
];

$currentUiStatus = $statusMap[$dbStatus] ?? "CONFIRMED";


    /* -------- TIMELINE -------- */
    $statusTimes = [
        "CONFIRMED" => $order['created_at'],
        "PREPARING" => date("Y-m-d H:i:s", strtotime($order['created_at'] . " +15 minutes")),
        "OUT_FOR_DELIVERY" =>
            in_array($currentUiStatus, ["OUT_FOR_DELIVERY", "DELIVERED"])
                ? date("Y-m-d H:i:s", strtotime($order['created_at'] . " +30 minutes"))
                : null,
        "DELIVERED" =>
            $currentUiStatus === "DELIVERED"
                ? date("Y-m-d H:i:s", strtotime($order['created_at'] . " +45 minutes"))
                : null
    ];

    $timeline = [];
    foreach ($uiSteps as $step) {
        $timeline[] = [
            "status" => $step,
            "time"   => $statusTimes[$step]
        ];
    }

    /* -------- LIVE LOCATION -------- */
    $locStmt = $pdo->prepare("
        SELECT latitude, longitude 
        FROM order_live_location 
        WHERE order_id = :order_id 
        LIMIT 1
    ");
    $locStmt->execute(['order_id' => $order_id]);
    $location = $locStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "orderNumber" => "ORD" . str_pad($order_id, 4, "0", STR_PAD_LEFT),
        "orderType" => "INDIVIDUAL",

        "eventInfo" => [
            "title"  => "Food Order",
            "date"   => date("Y-m-d", strtotime($order['created_at'])),
            "people" => 1
        ],

        "statusTimeline" => $timeline,
        "currentStatus"  => $currentUiStatus,

        "deliveryPerson" => [
            "name"    => $order['delivery_person_name'] ?? "Delivery Partner",
            "vehicle" => $order['vehicle_number'] ?? "Vehicle not assigned"
        ],

        "deliveryLocation" => $location ? [
            "latitude"  => (float)$location['latitude'],
            "longitude" => (float)$location['longitude']
        ] : null,

        "deliveryAddress" => [
            "line1"     => $order['full_address'],
            "city"      => "",
            "pincode"   => "",
            "note"      => $order['landmark'],
            "latitude"  => (float)$order['latitude'],
            "longitude" => (float)$order['longitude']
        ]
    ]);
    exit;
}

/* ============================================================
   EVENT / COMPANY TRACKING
============================================================ */
if ($type === "EVENT" || $type === "COMPANY") {

    $stmt = $pdo->prepare("
        SELECT *,
        CONCAT(delivery_address, ', ', delivery_city, ' - ', delivery_pincode) AS full_address
        FROM event_bookings
        WHERE booking_id = :booking_id
        AND booking_type = :booking_type
        LIMIT 1
    ");

    $stmt->execute([
        'booking_id'   => $order_id,
        'booking_type' => $type
    ]);

    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        echo json_encode([
            "success" => false,
            "message" => "Booking not found"
        ]);
        exit;
    }

    $dbStatus = strtoupper($event['status']);

    $dbStatus = strtoupper($order['status']);

$statusMap = [
    "PENDING"           => "CONFIRMED",
    "CONFIRMED"         => "PREPARING",
    "PREPARING"         => "PREPARING",
    "OUT_FOR_DELIVERY"  => "OUT_FOR_DELIVERY",
    "DELIVERED"         => "DELIVERED",
    "COMPLETED"         => "DELIVERED"
];

$currentUiStatus = $statusMap[$dbStatus] ?? "CONFIRMED";


    $statusTimes = [
        "CONFIRMED" => $event['created_at'],
        "PREPARING" => date("Y-m-d H:i:s", strtotime($event['created_at'] . " +15 minutes")),
        "OUT_FOR_DELIVERY" =>
            in_array($currentUiStatus, ["OUT_FOR_DELIVERY", "DELIVERED"])
                ? date("Y-m-d H:i:s", strtotime($event['created_at'] . " +30 minutes"))
                : null,
        "DELIVERED" =>
            $currentUiStatus === "DELIVERED"
                ? date("Y-m-d H:i:s", strtotime($event['created_at'] . " +45 minutes"))
                : null
    ];

    $timeline = [];
    foreach ($uiSteps as $step) {
        $timeline[] = [
            "status" => $step,
            "time"   => $statusTimes[$step]
        ];
    }

    /* -------- LIVE LOCATION -------- */
    $locStmt = $pdo->prepare("
        SELECT latitude, longitude 
        FROM order_live_location 
        WHERE order_id = :order_id 
        LIMIT 1
    ");
    $locStmt->execute(['order_id' => $order_id]);
    $location = $locStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "orderNumber" => "EVT" . str_pad($order_id, 4, "0", STR_PAD_LEFT),
        "orderType" => $type,

        "eventInfo" => [
            "title"  => $event['event_name'] ?? $event['company_name'] ?? "Booking",
            "date"   => $event['event_date'],
            "people" => $event['people_count'] ?? 0
        ],

        "statusTimeline" => $timeline,
        "currentStatus"  => $currentUiStatus,

        "deliveryPerson" => [
            "name"    => "Delivery Team",
            "vehicle" => "Assigned Vehicle"
        ],

        "deliveryLocation" => $location ? [
            "latitude"  => (float)$location['latitude'],
            "longitude" => (float)$location['longitude']
        ] : null,

        "deliveryAddress" => [
            "line1" => $event['full_address'] ?? "Address not available",
            "city"  => "",
            "pincode" => "",
            "note"  => $event['notes'] ?? ""
        ]
    ]);
    exit;
}

/* ================= FALLBACK ================= */
echo json_encode([
    "success" => false,
    "message" => "Invalid type"
]);
