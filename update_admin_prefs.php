<?php
require_once "config.php";

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['admin_id'])) {
        echo json_encode(['success'=>false,'message'=>'Invalid request']);
        exit;
    }

    $id = (int)$input['admin_id'];
    $noa = isset($input['new_order_alerts']) ? (int)$input['new_order_alerts'] : 0;
    $lsa = isset($input['low_stock_alerts']) ? (int)$input['low_stock_alerts'] : 0;
    $ai = isset($input['ai_insights']) ? (int)$input['ai_insights'] : 0;
    $cf = isset($input['customer_feedback']) ? (int)$input['customer_feedback'] : 0;

    $existsStmt = $pdo->prepare("SELECT admin_id FROM admin_notification_prefs WHERE admin_id = :id");
    $existsStmt->execute([':id'=>$id]);
    if ($existsStmt->fetch()) {
        $update = $pdo->prepare("UPDATE admin_notification_prefs SET new_order_alerts=:noa, low_stock_alerts=:lsa, ai_insights=:ai, customer_feedback=:cf, updated_at = NOW() WHERE admin_id=:id");
        $update->execute([':noa'=>$noa,':lsa'=>$lsa,':ai'=>$ai,':cf'=>$cf,':id'=>$id]);
    } else {
        $insert = $pdo->prepare("INSERT INTO admin_notification_prefs (admin_id, new_order_alerts, low_stock_alerts, ai_insights, customer_feedback, updated_at) VALUES (:id,:noa,:lsa,:ai,:cf,NOW())");
        $insert->execute([':id'=>$id,':noa'=>$noa,':lsa'=>$lsa,':ai'=>$ai,':cf'=>$cf]);
    }

    echo json_encode(['success'=>true,'message'=>'Notification prefs updated']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Server error','error'=>$e->getMessage()]);
}
