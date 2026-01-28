<?php
require_once "config.php";

/* ---------------- METHOD CHECK ---------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Method not allowed"
    ]);
    exit;
}

/* ---------------- INPUT ---------------- */
$input = json_decode(file_get_contents("php://input"), true);

$dietary  = $input['dietary']  ?? [];
$cuisines = $input['cuisines'] ?? [];

/* ---------------- BASE URL (IMAGE FIX) ---------------- */
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host     = $_SERVER['HTTP_HOST'];
$baseUrl  = $protocol . "://" . $host . "/floating_flavors_api/";

/* ---------------- SQL ---------------- */
$sql = "
SELECT 
    m.id,
    m.name,
    m.description,
    m.price,
    m.category,
    m.image_url
FROM menu_items m
LEFT JOIN menu_filters f ON f.menu_item_id = m.id
WHERE m.is_available = 1
";

$params = [];

/* ---------------- DIETARY FILTER ---------------- */
if (!empty($dietary)) {
    $conds = [];
    foreach ($dietary as $i => $d) {
        $key = ":diet$i";
        $conds[] = "f.dietary_tags LIKE $key";
        $params[$key] = "%" . trim($d) . "%";
    }
    $sql .= " AND (" . implode(" OR ", $conds) . ")";
}

/* ---------------- CUISINE FILTER ---------------- */
if (!empty($cuisines)) {
    $conds = [];
    foreach ($cuisines as $i => $c) {
        $key = ":cui$i";
        $conds[] = "f.cuisine_tags LIKE $key";
        $params[$key] = "%" . trim($c) . "%";
    }
    $sql .= " AND (" . implode(" OR ", $conds) . ")";
}

/* ---------------- EXECUTE ---------------- */
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------------- ALIGN OUTPUT (IMPORTANT PART) ---------------- */
foreach ($data as &$item) {

    $img = trim($item['image_url'] ?? '');

    if ($img === '' || $img === '0') {
        $item['image_full'] = "";
    }
    elseif (preg_match('#^https?://#i', $img)) {
        // already absolute
        $item['image_full'] = $img;
    }
    else {
        // relative path → full URL
        $item['image_full'] = $baseUrl . ltrim($img, '/');
    }

    // remove raw column (Android does not use it)
    unset($item['image_url']);
}
unset($item);

/* ---------------- RESPONSE ---------------- */
echo json_encode([
    "success" => true,
    "count"   => count($data),
    "data"    => $data
]);
