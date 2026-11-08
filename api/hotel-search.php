<?php
// Runs a hotel search and sends back the result cards as HTML. Called by hotel-results.php.

require '../includes/init.php';
require '../includes/stays.php';
require '../includes/hotel-search.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
session_write_close();

// At most 20 searches a minute from one address
if (!rate_limit('hotel-search:' . $_SERVER['REMOTE_ADDR'], 20, 60)) {
    echo json_encode(['ok' => false, 'message' => 'You have searched a lot in the last minute. Please wait a moment and try again.']);
    exit;
}

$search = read_hotel_search($_GET);
if ($search['error']) {
    echo json_encode(['ok' => false, 'message' => $search['error']]);
    exit;
}

// Same search within 15 minutes → reuse the results
$cache_file = __DIR__ . '/../storage/cache/hotels-' . md5(json_encode([$search, FLIGHT_DATA])) . '.json';
$results = null;
if (file_exists($cache_file) && filemtime($cache_file) > time() - 15 * 60) {
    $results = json_decode(file_get_contents($cache_file), true);
}
if ($results === null) {
    $r = stays_search($search['lat'], $search['lng'], $search['radius'], $search['check_in'], $search['check_out'], $search['rooms'], $search['adults'], $search['children']);
    if (!$r['ok']) {
        write_log('search', 'Hotel search failed', ['code' => $r['error_code'], 'error' => $r['error']]);
        echo json_encode(['ok' => false, 'message' => $r['error_code'] == 'not_configured'
            ? "Hotel search isn't switched on yet. Please contact us and we'll find a hotel for you."
            : "We couldn't get hotels for this search right now. Please try again, or contact us."]);
        exit;
    }
    $results = $r['data']['results'];
    file_put_contents($cache_file, json_encode($results));
}

// Price each hotel; drop any we can't price in naira
$priced = [];
foreach ($results as $h) {
    $price = hotel_price($h['cheapest_rate_total_amount'], $h['cheapest_rate_currency'], $search['rooms']);
    if ($price) $priced[] = [$h, $price];
}
usort($priced, function ($a, $b) { return $a[1]['total_kobo'] - $b[1]['total_kobo']; });

if (!$priced) {
    echo json_encode(['ok' => true, 'count' => 0, 'message' => 'No hotels found for these dates. Try different dates or a nearby city.']);
    exit;
}

ob_start();
foreach ($priced as $n => $row) {
    list($h, $price) = $row;
    include '../includes/hotel-card.php';
}
$prices = array_map(function ($row) { return $row[1]['total_kobo']; }, $priced);
echo json_encode(['ok' => true, 'count' => count($priced), 'html' => ob_get_clean(), 'min_price' => min($prices), 'max_price' => max($prices)]);
