<?php
require_once "config.php";
$stmt = $pdo->query("DESCRIBE orders");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($cols as $c) {
    echo "{$c['Field']} ({$c['Type']})\n";
}
