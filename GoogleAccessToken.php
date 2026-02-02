<?php
// GoogleAccessToken.php

class GoogleAccessToken {
    private static $tokenUrl = 'https://oauth2.googleapis.com/token';
    private static $scope = 'https://www.googleapis.com/auth/firebase.messaging';

    /**
     * Generate an OAuth2 Access Token using the Service Account JSON
     */
    public static function getToken($jsonPath) {
        if (!file_exists($jsonPath)) {
            throw new Exception("Service Account JSON not found at: $jsonPath");
        }

        $authConfig = json_decode(file_get_contents($jsonPath), true);
        if (!$authConfig) {
            throw new Exception("Invalid JSON in Service Account file");
        }

        $now = time();
        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $payload = json_encode([
            'iss' => $authConfig['client_email'],
            'scope' => self::$scope,
            'aud' => self::$tokenUrl,
            'iat' => $now,
            'exp' => $now + 3600 // 1 hour expiration
        ]);

        $base64Header = self::base64UrlEncode($header);
        $base64Payload = self::base64UrlEncode($payload);
        
        $signatureInput = $base64Header . "." . $base64Payload;
        $signature = '';

        $privateKey = $authConfig['private_key'];
        if (!openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new Exception("Failed to sign JWT");
        }

        $jwt = $signatureInput . "." . self::base64UrlEncode($signature);

        // POST to Google to get Access Token
        $postData = http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::$tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception("CURL Error getting OAuth token: " . curl_error($ch));
        }
        curl_close($ch);

        $json = json_decode($response, true);
        if (isset($json['access_token'])) {
            return $json['access_token'];
        } else {
            throw new Exception("Failed to get access token: " . ($json['error_description'] ?? json_encode($json)));
        }
    }

    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
