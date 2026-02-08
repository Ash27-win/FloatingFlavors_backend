<?php
require_once 'config.php';

try {
    echo "--- Applying Schema Update needed for Low Stock Logic ---\n";
    
    // 1. Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM menu_items LIKE 'stock'");
    $exists = $stmt->fetch();
    
    if ($exists) {
        echo "✅ Column 'stock' already exists in 'menu_items'. Skipping add.\n";
    } else {
        // 2. Add Column
        $sql = "ALTER TABLE menu_items ADD COLUMN stock INT DEFAULT 50";
        $pdo->exec($sql);
        echo "✅ SUCCESS: Added 'stock' column with default value 50.\n";
    }

} catch (PDOException $e) {
    echo "❌ DB Error: " . $e->getMessage() . "\n";
}
?>
