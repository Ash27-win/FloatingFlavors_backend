<?php
require_once "middleware.php";

try {
    $adminId = $GLOBALS['AUTH_USER_ID'] ?? 0;
    if ($adminId <= 0 || ($GLOBALS['AUTH_ROLE'] ?? '') !== 'Admin') {
        http_response_code(403);
        echo json_encode(['success'=>false,'message'=>'Admin access only']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT new_order_alerts, low_stock_alerts, ai_insights, customer_feedback, updated_at FROM admin_notification_prefs WHERE admin_id = :id");
    $stmt->execute([':id'=>$adminId]);
    $prefs = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$prefs) {
        $prefs = ['new_order_alerts'=>1,'low_stock_alerts'=>1,'ai_insights'=>1,'customer_feedback'=>1,'updated_at'=>null];
    }

    // cast toggles to ints
    $prefs_cast = [
        'new_order_alerts' => (int)$prefs['new_order_alerts'],
        'low_stock_alerts' => (int)$prefs['low_stock_alerts'],
        'ai_insights' => (int)$prefs['ai_insights'],
        'customer_feedback' => (int)$prefs['customer_feedback'],
        'updated_at' => $prefs['updated_at']
    ];

    echo json_encode(['success'=>true,'message'=>'Prefs fetched','data'=>$prefs_cast]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Server error','error'=>$e->getMessage()]);
}
