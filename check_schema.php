<?php
require_once 'config.php';
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM menu_items");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns: " . implode(", ", $columns) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
