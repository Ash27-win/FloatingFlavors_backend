<?php
require_once "config.php";
header("Content-Type: application/json");

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (empty($data)) {
        $data = $_POST;
    }

    $orderId = $data['order_id'] ?? null;
    $statusRaw = $data['status'] ?? null;
    $deliveryPartnerId = $data['delivery_partner_id'] ?? null;
    $rejectReason = $data['reject_reason'] ?? null;

    if (!$orderId || !$statusRaw) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "order_id and status are required"
        ]);
        exit;
    }

    // ✅ Normalize
    $status = strtoupper(trim($statusRaw));

    // ✅ Allowed statuses
    $allowed = [
        'PENDING',
        'CONFIRMED',
        'PREPARING',
        'OUT_FOR_DELIVERY',
        'DELIVERED',
        'COMPLETED',
        'REJECTED',
        'CANCELLED'
    ];

    if (!in_array($status, $allowed, true)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Invalid status: $status"
        ]);
        exit;
    }

    // Normalize delivery partner
    if ($deliveryPartnerId === '' || $deliveryPartnerId === 0) {
        $deliveryPartnerId = null;
    }

    // ✅ Check if order exists
    $checkStmt = $pdo->prepare("SELECT id FROM orders WHERE id = ?");
    $checkStmt->execute([$orderId]);
    if ($checkStmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Order not found"
        ]);
        exit;
    }

    /* ================= 🔄 STOCK RESTORATION LOGIC ================= */
    // If order is Rejected/Cancelled, we must add the items back to stock.
    if ($status === 'REJECTED' || $status === 'CANCELLED') {
        // 1. Get items in this order
        $itemsStmt = $pdo->prepare("SELECT name, menu_item_id, qty FROM order_items WHERE order_id = ?");
        $itemsStmt->execute([$orderId]);
        $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Restore stock
        $restoreStmt = $pdo->prepare("UPDATE menu_items SET stock = stock + ? WHERE id = ?");
        $availStmt = $pdo->prepare("UPDATE menu_items SET is_available = 1 WHERE id = ? AND stock > 0");

        foreach ($orderItems as $item) {
             $restoreStmt->execute([$item['qty'], $item['menu_item_id']]);
             // If item was auto-disabled (stock=0), re-enable it
             $availStmt->execute([$item['menu_item_id']]);
        }
    }
    /* ============================================================== */

    // ✅ FIXED: Use correct column name 'delivery_partner_id'
    if ($status === 'REJECTED') {
        // For rejected orders
        $stmt = $pdo->prepare(
            "UPDATE orders 
             SET status = :status, 
                 reject_reason = :reject_reason,
                 delivery_partner_id = :dp  -- ✅ FIXED TYPO
             WHERE id = :id"
        );
        $stmt->execute([
            ':status' => $status,
            ':reject_reason' => $rejectReason,
            ':dp' => $deliveryPartnerId,
            ':id' => (int)$orderId
        ]);
    } elseif ($deliveryPartnerId !== null) {
        // For accepted orders with delivery partner
        $stmt = $pdo->prepare(
            "UPDATE orders 
             SET status = :status, 
                 delivery_partner_id = :dp  -- ✅ FIXED TYPO
             WHERE id = :id"
        );
        $stmt->execute([
            ':status' => $status,
            ':dp' => (int)$deliveryPartnerId,
            ':id' => (int)$orderId
        ]);
    } else {
        // For other status updates
        $stmt = $pdo->prepare(
            "UPDATE orders 
             SET status = :status 
             WHERE id = :id"
        );
        $stmt->execute([
            ':status' => $status,
            ':id' => (int)$orderId
        ]);
    }

    // ✅ Get updated order details
    $fetchStmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $fetchStmt->execute([$orderId]);
    $updatedOrder = $fetchStmt->fetch(PDO::FETCH_ASSOC);

    // ✅ Success message
    $message = match ($status) {
        'OUT_FOR_DELIVERY' => 'Order accepted and assigned to delivery partner',
        'REJECTED' => 'Order rejected successfully',
        'CANCELLED' => 'Order cancelled successfully',
        'COMPLETED', 'DELIVERED' => 'Order marked as delivered',
        default => 'Order status updated successfully'
    };

    /* ================= 🔔 NOTIFICATION TRIGGER (NEW) ================= */
    require_once "send_notification_helper.php";
    
    $notifTitle = "Order Update";
    $notifBody = "Your order #$orderId is now $status";
    $screen = "UserOrderDetails";

    if ($status === 'CONFIRMED') {
        // 1. Notify User (Standard flow below)
        $notifTitle = "Order Accepted 👨‍🍳";
        $notifBody = "The kitchen has accepted your order #$orderId. Cooking started!";
        $screen = "OrderTrackingScreen"; 

        // 2. Broadcast to Delivery Partners (New Flow)
        try {
            // Fetch all unique delivery partner IDs
            $delStmt = $pdo->prepare("SELECT DISTINCT user_id FROM fcm_tokens WHERE role = 'Delivery'");
            $delStmt->execute();
            $delRows = $delStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($delRows as $dRow) {
                sendNotification(
                    $dRow['user_id'], 
                    'Delivery', 
                    "New Order Available 📦",
                    "Order #$orderId is ready for pickup. Accept it now!",
                    "NEW_ORDER",
                    $orderId, 
                    ['screen' => 'DeliveryDashboard'] 
                );
            }
        } catch (Exception $e) {
            error_log("Delivery Broadcast Error: " . $e->getMessage());
        }

    } elseif ($status === 'OUT_FOR_DELIVERY') {
        $notifTitle = "Order On The Way 🚚";
        $notifBody = "Your food is out for delivery! Track it live.";
        $screen = "OrderTrackingScreen";
    } elseif ($status === 'DELIVERED') {
        $notifTitle = "Order Delivered ✅";
        $notifBody = "Enjoy your meal! Please rate us.";
    } elseif ($status === 'REJECTED') {
        $notifTitle = "Order Rejected ⚠️";
        $reason = $rejectReason ?: "Unfortunately, we cannot fulfill this order.";
        $notifBody = "Reason: $reason";
        $screen = "UserOrderDetails";
        
    } elseif ($status === 'CANCELLED') {
        $notifTitle = "Order Cancelled ❌";
        $notifBody = "Your order #$orderId has been cancelled.";
    }

    // Fetch user_id for this order to notify them
    $uStmt = $pdo->prepare("SELECT user_id FROM orders WHERE id = ?");
    $uStmt->execute([$orderId]);
    $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);

    if ($uRow) {
        sendNotification(
            $uRow['user_id'], 
            'User', 
            $notifTitle,
            $notifBody,
            "ORDER_" . strtoupper($status),
            $orderId,
            ['screen' => $screen]
        );
    }
    /* ================================================================= */

    echo json_encode([
        "success" => true,
        "order_id" => (int)$orderId,
        "status" => $status,
        "delivery_partner_id" => $deliveryPartnerId,
        "reject_reason" => $rejectReason,
        "message" => $message,
        "order" => $updatedOrder
    ]);

} catch (Exception $e) {
    error_log("Update order error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Server error: " . $e->getMessage()
    ]);
}