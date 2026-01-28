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
    echo json_encode([
        "success" => true,
        "order_id" => $order_id
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
