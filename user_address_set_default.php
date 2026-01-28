<?php
require "config.php";

/* ---------------- INPUT ---------------- */
$user_id    = $_POST['user_id'];
$address_id = $_POST['address_id'];

/* ---------------- GEOCODE FUNCTION ---------------- */
function geocodeAddress($address) {
    $url = "https://nominatim.openstreetmap.org/search?" . http_build_query([
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
    $resp = file_get_contents($url, false, $context);

    if ($resp === false) return null;

    $data = json_decode($resp, true);
    if (empty($data)) return null;

    return [
        "lat" => (float)$data[0]['lat'],
        "lng" => (float)$data[0]['lon']
    ];
}

try {

    /* ---------------- RESET DEFAULT ---------------- */
    $pdo->prepare(
        "UPDATE user_addresses SET is_default = 0 WHERE user_id = ?"
    )->execute([$user_id]);

    /* ---------------- SET NEW DEFAULT ---------------- */
    $pdo->prepare(
        "UPDATE user_addresses SET is_default = 1 WHERE id = ? AND user_id = ?"
    )->execute([$address_id, $user_id]);

    /* ---------------- FETCH ADDRESS ---------------- */
    $stmt = $pdo->prepare("
        SELECT house, area, city, pincode, latitude, longitude
        FROM user_addresses
        WHERE id = ? AND user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$address_id, $user_id]);
    $addr = $stmt->fetch(PDO::FETCH_ASSOC);

    /* ---------------- GEOCODE IF NEEDED ---------------- */
    if (
        $addr &&
        (
            $addr['latitude'] === null ||
            $addr['longitude'] === null ||
            (float)$addr['latitude'] == 0.0 ||
            (float)$addr['longitude'] == 0.0
        )
    ) {
        $fullAddress =
            $addr['house'] . ", " .
            $addr['area'] . ", " .
            $addr['city'] . " - " .
            $addr['pincode'];

        $geo = geocodeAddress($fullAddress);

        if ($geo) {
            $pdo->prepare("
                UPDATE user_addresses
                SET latitude = ?, longitude = ?
                WHERE id = ?
            ")->execute([
                $geo['lat'],
                $geo['lng'],
                $address_id
            ]);
        }
    }

    echo json_encode([
        "status" => true,
        "message" => "Default address updated with location"
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}
