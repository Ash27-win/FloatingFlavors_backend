<?php
// test_env_setup.php
require_once 'config.php';

echo "--- Security Configuration Check ---\n";
echo "1. Checking Environment Variable 'FIREBASE_SERVICE_ACCOUNT_PATH'...\n";
$envPath = getenv('FIREBASE_SERVICE_ACCOUNT_PATH');

if ($envPath) {
    echo "   [OK] Variable set to: $envPath\n";
} else {
    echo "   [WARNING] Variable NOT set in this shell.\n";
    echo "   (This is normal if you just set it. You might need to restart XAMPP/PC)\n";
}

echo "\n2. Checking Config Constant 'GOOGLE_APPLICATION_CREDENTIALS'...\n";
if (defined('GOOGLE_APPLICATION_CREDENTIALS')) {
    echo "   [OK] Config resolved path to: " . GOOGLE_APPLICATION_CREDENTIALS . "\n";
    
    echo "\n3. Verifying File Existence...\n";
    if (file_exists(GOOGLE_APPLICATION_CREDENTIALS)) {
        echo "   [SUCCESS] ✅ File Found! Your system is Secure and Working.\n";
    } else {
        echo "   [ERROR] ❌ File NOT found at " . GOOGLE_APPLICATION_CREDENTIALS . "\n";
        echo "   -> Did you move the file to C:\\xampp\\secrets\\?\n";
        echo "   -> Or does the path in config.php fallback to the project root (where it was deleted)?\n";
    }
} else {
    echo "   [ERROR] ❌ GOOGLE_APPLICATION_CREDENTIALS is not defined.\n";
}
?>
