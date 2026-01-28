<?php
header("Content-Type: application/json");
require_once __DIR__ . "/config.php";

// IMPORTANT: use $pdo (not $conn)
$user_id = intval($_GET['user_id'] ?? 0);

// ---------- CURRENT PLAN ----------
$currentStmt = $pdo->prepare("
    SELECT um.*, mp.*
    FROM user_memberships um
    JOIN membership_plans mp ON mp.id = um.plan_id
    WHERE um.user_id = ? AND um.status = 'ACTIVE'
    LIMIT 1
");
$currentStmt->execute([$user_id]);
$currentPlan = $currentStmt->fetch(PDO::FETCH_ASSOC);

// 🔥 FIX HERE
if ($currentPlan === false) {
    $currentPlan = null;
}


// ---------- AVAILABLE PLANS ----------
$plansStmt = $pdo->prepare("
    SELECT * FROM membership_plans
    WHERE is_active = 1
");
$plansStmt->execute();
$plans = $plansStmt->fetchAll(PDO::FETCH_ASSOC);

// ---------- RESPONSE ----------
echo json_encode([
    "currentPlan" => $currentPlan,
    "availablePlans" => $plans
]);
