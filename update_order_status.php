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