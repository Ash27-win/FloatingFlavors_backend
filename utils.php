<?php
// utils.php

require_once 'config.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function getBearerToken() {
    $headers = function_exists('apache_request_headers') ? apache_request_headers() : null;
    $auth = null;
    if ($headers) {
        foreach ($headers as $k => $v) {
            if (strtolower($k) === 'authorization') { $auth = $v; break; }
        }
    }
    if (!$auth && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $auth = $_SERVER['HTTP_AUTHORIZATION'];
    }
    
    if ($auth && preg_match('/Bearer\s(\S+)/', $auth, $m)) {
        return $m[1];
    }
    return null;
}

function decode_jwt($token) {
    if (!defined('JWT_SECRET')) {
        throw new Exception("JWT_SECRET not defined");
    }
    // Returns array
    $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
    return (array) $decoded;
}

function time_elapsed_string($datetime, $full = false) {
    if ($datetime == '0000-00-00 00:00:00') return "Just now";
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
