<?php
require_once "config.php";
header("Content-Type: application/json");

// Read inputs
$user_id = $_POST['user_id'] ?? 0;
$payment = $_POST['payment_method'] ?? '';

if ($user_id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid user id"
    ]);
    exit;
}

try {
    $pdo->beginTransaction();

    /* -------------------------------------------------
       1️⃣ FETCH USER (REAL CUSTOMER NAME)
    ------------------------------------------------- */
    $userStmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $userStmt->execute([$user_id]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("User not found");
    }

    $customerName = $user['name'];

    /* -------------------------------------------------
       2️⃣ FETCH USER CART
    ------------------------------------------------- */
    $cartStmt = $pdo->prepare("SELECT id FROM cart WHERE user_id = ?");
    $cartStmt->execute([$user_id]);
    $cart = $cartStmt->fetch(PDO::FETCH_ASSOC);

    if (!$cart) {
        throw new Exception("Cart not found");
    }

    $cart_id = $cart['id'];

    /* -------------------------------------------------
       3️⃣ FETCH CART ITEMS
    ------------------------------------------------- */
    $itemStmt = $pdo->prepare("
        SELECT 
            ci.menu_item_id,
            m.name,
            ci.quantity,
            ci.price
        FROM cart_items ci
        JOIN menu_items m ON m.id = ci.menu_item_id
        WHERE ci.cart_id = ?
    ");
    $itemStmt->execute([$cart_id]);

    $cartItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($cartItems)) {
        throw new Exception("Cart is empty");
    }

    /* -------------------------------------------------
       4️⃣ CALCULATE TOTAL AMOUNT
    ------------------------------------------------- */
    $totalAmount = 0;
    foreach ($cartItems as $item) {
        $totalAmount += $item['quantity'] * $item['price'];
    }

    /* -------------------------------------------------
   5️⃣ INSERT INTO ORDERS TABLE (FIXED)
    ------------------------------------------------- */

    $user_address_id = $_POST['user_address_id'] ?? null;

    if (empty($user_address_id) || !is_numeric($user_address_id)) {
        throw new Exception("Address not selected or invalid");
    }

    $orderStmt = $pdo->prepare("
        INSERT INTO orders (
            customer_name,
            status,
            amount,
            user_id,
            user_address_id
            )
        VALUES (?, 'pending', ?, ?, ?)
    ");

    $orderStmt->execute([
        $customerName,
        $totalAmount,
        $user_id,
        $user_address_id
    ]);



    $order_id = $pdo->lastInsertId();

    /* -------------------------------------------------
       6️⃣ INSERT INTO ORDER_ITEMS TABLE
    ------------------------------------------------- */
    $orderItemStmt = $pdo->prepare("
        INSERT INTO order_items (order_id, name, qty)
        VALUES (?, ?, ?)
    ");

    foreach ($cartItems as $item) {
        $orderItemStmt->execute([
            $order_id,
            $item['name'],
            $item['quantity']
        ]);
    }

    /* -------------------------------------------------
       7️⃣ CLEAR USER CART
    ------------------------------------------------- */
    $clearStmt = $pdo->prepare("
        DELETE ci FROM cart_items ci
        WHERE ci.cart_id = ?
    ");
    $clearStmt->execute([$cart_id]);

    $pdo->commit();

    /* -------------------------------------------------
       8️⃣ SUCCESS RESPONSE
    ------------------------------------------------- */
    // ... (Existing WS logic) ...

    /* ================= 🔔 NOTIFICATION TRIGGER (NEW) ================= */
    // Notify ADMIN about new order
    require_once "send_notification_helper.php";
    
    // In a real app, you might have multiple admins. Here we assume ID 1 is the main admin
    // or we could loop through all admins. For MVP, we target Admin ID 1.
    $adminId = 1; 
    
    // 🔔 1. New Order Notification
    sendNotification(
        $adminId, 
        'Admin', 
        "New Order #$order_id Received! 🆕",
        "Customer $user_id has placed a new order of ₹$totalAmount.",
        "ORDER_PLACED",
        $order_id,
        ['screen' => 'AdminOrderDetails']
    );

    /* ================================================================= */
    // 🔔 2. UPDATE STOCK & Low Stock Check
    // Iterate through items to deduct stock and check availability
    
    foreach ($cartItems as $item) {
        $itemId = $item['menu_item_id'];
        $qty = $item['quantity'];
        
        // 0. CHECK STOCK VALIDATION (Server Side Safety)
        $checkLimit = $pdo->prepare("SELECT name, stock FROM menu_items WHERE id = ?");
        $checkLimit->execute([$itemId]);
        $limitRow = $checkLimit->fetch(PDO::FETCH_ASSOC);
        
        if (!$limitRow || $limitRow['stock'] < $qty) {
            throw new Exception("Insufficient stock for item: " . ($limitRow['name'] ?? 'Unknown'));
        }

        // 1. Deduct Stock
        $updateStock = $pdo->prepare("UPDATE menu_items SET stock = stock - ? WHERE id = ?");
        $updateStock->execute([$qty, $itemId]);
        
        // 2. Check New Stock Level
        $checkStock = $pdo->prepare("SELECT name, stock FROM menu_items WHERE id = ?");
        $checkStock->execute([$itemId]);
        $row = $checkStock->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $newStock = $row['stock'];
            
            // 3. Mark Unavailable if 0
            if ($newStock <= 0) {
                $pdo->prepare("UPDATE menu_items SET is_available = 0 WHERE id = ?")->execute([$itemId]);
            }
            
            // 4. Low Stock Alert (< 5)
            // (Only send if we haven't sent one recently? 
            // optimization: For now, send every time it dips below 5 to be safe).
            if ($newStock < 5) {
                sendNotification(
                    $adminId, 
                    'Admin', 
                    "Low Stock Alert: {$row['name']} ⚠️", 
                    "Only $newStock left! Re-stock immediately.", 
                    "LOW_STOCK", 
                    $itemId,
                    ['screen' => 'AdminMenu']
                );
            }
        }
    } 
    
    sendNotification(
        $adminId, 
        'Admin', 
        "New Order #$order_id Received! 🆕",
        "Customer $user_id has placed a new order of ₹$totalAmount.",
        "ORDER_PLACED",
        $order_id,
        ['screen' => 'AdminOrderDetails']
    );
    /* ================================================================= */

    echo json_encode(['success' => true, 'message' => 'Order placed successfully', 'order_id' => $order_id]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
