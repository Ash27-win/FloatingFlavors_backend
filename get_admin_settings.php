<?php
// get_admin_settings.php
require_once "config.php";
header('Content-Type: application/json; charset=utf-8');

try {
    $admin_id = isset($_GET['admin_id']) ? (int)$_GET['admin_id'] : 0;
    if ($admin_id <= 0) {
        echo json_encode(['success'=>false,'message'=>'admin_id required']);
        exit;
    }

    $sql = "SELECT id AS admin_id, full_name, email, phone, business_name, address, avatar_url
            FROM admins WHERE id = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id'=>$admin_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success'=>false,'message'=>'Admin not found']);
        exit;
    }

    // fetch notification prefs (optional)
    $prefsStmt = $pdo->prepare("SELECT new_order_alerts, low_stock_alerts, ai_insights, customer_feedback FROM admin_notification_prefs WHERE admin_id = :id");
    $prefsStmt->execute([':id'=>$admin_id]);
    $prefs = $prefsStmt->fetch(PDO::FETCH_ASSOC);

    // default prefs if missing
    $prefs = $prefs ?: ['new_order_alerts'=>0,'low_stock_alerts'=>0,'ai_insights'=>0,'customer_feedback'=>0];

    $data = [
        'admin_id' => (int)$row['admin_id'],
        'full_name' => $row['full_name'],
        'email' => $row['email'],
        'phone' => $row['phone'],
        'business_name' => $row['business_name'],
        'address' => $row['address'],
        // IMPORTANT: keep avatar_url exactly as stored (could be relative)
        'avatar_url' => $row['avatar_url'],
        // return prefs as booleans (frontend expects booleans)
        'new_order_alerts' => (bool)$prefs['new_order_alerts'],
        'low_stock_alerts' => (bool)$prefs['low_stock_alerts'],
        'ai_insights' => (bool)$prefs['ai_insights'],
        'customer_feedback' => (bool)$prefs['customer_feedback'],
        'updated_at' => date('Y-m-d H:i:s')
    ];

    echo json_encode(['success'=>true,'message'=>'Admin settings fetched','data'=>$data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Server error','error'=>$e->getMessage()]);
}
