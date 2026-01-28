<?php
function geocodeAddress($address) {
    if (empty($address)) return null;
    
    $url = "https://nominatim.openstreetmap.org/search?"
        . http_build_query([
            "q" => $address,
            "format" => "json",
            "limit" => 1
        ]);

    $opts = [
        "http" => [
            "header" => "User-Agent: FloatingFlavors/1.0\r\n"
        ]
    ];

    $context = stream_context_create($opts);
    // Suppress warnings for network issues
    $response = @file_get_contents($url, false, $context);

    if ($response === false) return null;

    $data = json_decode($response, true);
    if (empty($data)) return null;

    return [
        "lat" => (float)$data[0]["lat"],
        "lng" => (float)$data[0]["lon"]
    ];
}

function smartGeocode($house, $area, $city, $pincode) {
    // 1. Try Full Address
    $full = "$house, $area, $city - $pincode";
    $res = geocodeAddress($full);
    if ($res) return $res;

    // 2. Try Area + City + Pincode (skip house)
    $fallback1 = "$area, $city, $pincode";
    $res = geocodeAddress($fallback1);
    if ($res) return $res;

    // 3. Try Area + Pincode (skip City, useful if city has typo)
    $fallback2 = "$area, $pincode";
    $res = geocodeAddress($fallback2);
    if ($res) return $res;

    // 4. Try just City + Pincode
    $fallback3 = "$city, $pincode";
    $res = geocodeAddress($fallback3);
    if ($res) return $res;

    // 5. Try just Pincode (Last resort)
    $res = geocodeAddress($pincode);
    
    return $res;
}
