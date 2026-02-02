<?php
// get_admin_settings.php
require_once "middleware.php"; // Token Auth

$admin_id = $GLOBALS['AUTH_USER_ID'] ?? 0;
$role = $GLOBALS['AUTH_ROLE'] ?? '';

if ($admin_id <= 0 || $role !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Admin access only']);
    exit;
}

    // 1. Fetch Core User Details (Source of Truth for Auth)
    // We use the ID from the token ($admin_id) to get the REAL user who logged in.
    $userStmt = $pdo->prepare("SELECT name, email FROM users WHERE id = :uid");
    $userStmt->execute([':uid' => $admin_id]);
    $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$userRow) {
        http_response_code(404);
        echo json_encode(['success'=>false,'message'=>'User account not found']);
        exit;
    }

    $email = $userRow['email'];
    $fullName = $userRow['name'];

    // 2. Fetch Extended Admin Profile (Business Info) by EMAIL
    // We match by EMAIL because users.id and admins.id might not be synchronized.
    $adminStmt = $pdo->prepare("SELECT * FROM admins WHERE email = :email LIMIT 1");
    $adminStmt->execute([':email' => $email]);
    $adminRow = $adminStmt->fetch(PDO::FETCH_ASSOC);

    // Default values if no profile exists in 'admins' table yet
    $businessName = "My Cloud Kitchen";
    $address = "";
    $phone = "";
    $avatarUrl = null;
    $adminTableId = 0;

    if ($adminRow) {
        $adminTableId = $adminRow['id'];
        $businessName = $adminRow['business_name'];
        $address = $adminRow['address'];
        $phone = $adminRow['phone'];
        $avatarUrl = $adminRow['avatar_url'];
        // Ideally prefer the name in admins table if set, otherwise keep users table name
        if (!empty($adminRow['full_name'])) {
            $fullName = $adminRow['full_name'];
        }
    }

    // 3. Fetch Notification Prefs (using admins.id if we found one, else user_id fallback?)
    // Actually prefs are usually linked to the admin_id (which in this context was ambiguous).
    // Let's assume matches the ID in 'admins' table if it exists, otherwise 0.
    $prefs = ['new_order_alerts'=>0,'low_stock_alerts'=>0,'ai_insights'=>0,'customer_feedback'=>0];
    
    if ($adminTableId > 0) {
        $prefsStmt = $pdo->prepare("SELECT new_order_alerts, low_stock_alerts, ai_insights, customer_feedback FROM admin_notification_prefs WHERE admin_id = :id");
        $prefsStmt->execute([':id' => $adminTableId]);
        $fetched = $prefsStmt->fetch(PDO::FETCH_ASSOC);
        if ($fetched) $prefs = $fetched;
    }

    $data = [
        'admin_id' => (int)$admin_id, // Keep consistency with Token ID
        'full_name' => $fullName,
        'email' => $email,
        'phone' => $phone,
        'business_name' => $businessName,
        'address' => $address,
        'avatar_url' => $avatarUrl,
        'new_order_alerts' => (bool)$prefs['new_order_alerts'],
        'low_stock_alerts' => (bool)$prefs['low_stock_alerts'],
        'ai_insights' => (bool)$prefs['ai_insights'],
        'customer_feedback' => (bool)$prefs['customer_feedback'],
        'updated_at' => date('Y-m-d H:i:s')
    ];

    echo json_encode(['success'=>true,'message'=>'Admin settings fetched','data'=>$data]);

    // End of script, no catch block needed as we removed the try

