<?php
require_once "config.php";
$tables = ['fcm_tokens', 'notifications'];
foreach ($tables as $t) {
    $stmt=$pdo->query("SHOW TABLES LIKE '$t'");
    echo "$t: " . ($stmt->rowCount()>0 ? 'EXISTS' : 'MISSING') . "\n";
}
