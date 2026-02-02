<?php
// test_fcm_auth.php
require_once "config.php";
require_once "GoogleAccessToken.php";

header('Content-Type: text/plain');

echo "1. Checking Configuration...\n";
if (defined('GOOGLE_APPLICATION_CREDENTIALS')) {
    echo "   [OK] Credential Path: " . GOOGLE_APPLICATION_CREDENTIALS . "\n";
} else {
    echo "   [FAIL] GOOGLE_APPLICATION_CREDENTIALS not defined!\n";
    exit;
}

if (file_exists(GOOGLE_APPLICATION_CREDENTIALS)) {
    echo "   [OK] JSON File exists.\n";
} else {
    echo "   [FAIL] JSON File NOT found!\n";
    exit;
}

echo "\n2. Attempting to Authenticate with Google...\n";
try {
    $token = GoogleAccessToken::getToken(GOOGLE_APPLICATION_CREDENTIALS);
    echo "   [SUCCESS] OAuth2 Token Generated!\n";
    echo "   Token Start: " . substr($token, 0, 20) . "...\n";
    echo "   (This proves your JSON Key is valid and talking to Google)\n";
} catch (Exception $e) {
    echo "   [ERROR] Authentication Failed:\n";
    echo "   " . $e->getMessage() . "\n";
}
