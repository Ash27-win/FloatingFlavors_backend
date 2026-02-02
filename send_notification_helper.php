<?php
// send_notification_helper.php
require_once "config.php";

/**
 * Sends a notification via FCM and logs it to the database.
 * 
 * @param int $recipientId User ID
 * @param string $role 'User', 'Admin', 'Delivery'
 * @param string $title Notification Title
 * @param string $body Notification Body
 * @param string $type Event type (e.g. ORDER_PLACED)
 * @param int $referenceId Related ID (e.g. Order ID)
 * @param array $extraData Additional data for the app (screen, priority)
 */
function sendNotification($recipientId, $role, $title, $body, $type, $referenceId = 0, $extraData = []) {
    global $pdo;

    // 1. Log to Database (So it appears in "Notification History" screen later)
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (recipient_id, recipient_role, title, body, type, reference_id, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
        ");
        $stmt->execute([$recipientId, $role, $title, $body, $type, $referenceId]);
    } catch (Exception $e) {
        // Silent fail on log, don't stop flow
        error_log("Notification Log Error: " . $e->getMessage());
    }

    // 2. Fetch FCM Tokens for this User+Role
    try {
        $tokenStmt = $pdo->prepare("SELECT token FROM fcm_tokens WHERE user_id = ? AND role = ?");
        $tokenStmt->execute([$recipientId, $role]);
        $tokens = $tokenStmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($tokens)) {
            return; // No devices to notify
        }

        // 3. Prepare FCM HTTP v1 (OAuth2)
        require_once "GoogleAccessToken.php";
        
        try {
            if (!defined('GOOGLE_APPLICATION_CREDENTIALS')) {
                throw new Exception("Application Credentials not set in config");
            }
            // Get valid OAuth2 Token
            $oauthToken = GoogleAccessToken::getToken(GOOGLE_APPLICATION_CREDENTIALS);
        } catch (Exception $e) {
            error_log("FCM Auth Error: " . $e->getMessage());
            return;
        }

        // 4. Send to Each Token (HTTP v1 does not support multicast "registration_ids" in one call efficiently like legacy)
        // We must map it or use batch (but batch is complex).
        // For MVP, loop and send.
        
        // Read Project ID from JSON (needed for URL)
        $json = json_decode(file_get_contents(GOOGLE_APPLICATION_CREDENTIALS), true);
        $projectId = $json['project_id'];
        $url = "https://fcm.googleapis.com/v1/projects/$projectId/messages:send";
        
        $headers = [
            'Authorization: Bearer ' . $oauthToken,
            'Content-Type: application/json'
        ];

        // Merge standard data with extra data
        $dataPayload = array_merge([
            'title' => $title,
            'body' => $body,
            'type' => $type,
            'role' => $role,
            'reference_id' => (string)$referenceId, // Must be string in data
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
        ], array_map('strval', $extraData)); // Enforce string values for data

        foreach ($tokens as $deviceToken) {
            $payload = [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $body
                    ],
                    'data' => $dataPayload,
                    // Optional: Add android specific priority
                    'android' => [
                        'priority' => 'high'
                    ]
                ]
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            
            $result = curl_exec($ch);
            if ($result === FALSE) {
                error_log("FCM V1 Curl Error: " . curl_error($ch));
            } else {
                // Optional: Check if token invalid (404/400) and delete from DB
                // $resJson = json_decode($result, true);
            }
            curl_close($ch);
        }

    } catch (Exception $e) {
        error_log("FCM Send Error: " . $e->getMessage());
    }
}
