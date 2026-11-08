<?php
// Runs a flight search and sends back the result cards as HTML, plus what the filters need.
// Called by assets/js/results.js from flight-results.php, with the same ?trip=...&from[]=... query.

require '../includes/init.php';
require '../includes/duffel.php';
require '../includes/flight-search.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
session_write_close();   // the search can take a while; don't lock the visitor's other pages meanwhile

// At most 20 searches a minute from one address (each search costs us an airline request)
if (!rate_limit('flight-search:' . $_SERVER['REMOTE_ADDR'], 20, 60)) {
    echo json_encode(['ok' => false, 'message' => 'You have searched a lot in the last minute. Please wait a moment and try again.']);
    exit;
}

$search = read_flight_search($_GET);
if ($search['error']) {
    echo json_encode(['ok' => false, 'message' => $search['error']]);
    exit;
}

// Re-use results for the same search for up to 15 minutes (but never after the prices expire)
$key = md5(json_encode([$search, FLIGHT_DATA]));
$cache_file = __DIR__ . '/../storage/cache/search-' . $key . '.json';
$offers = null;
if (file_exists($cache_file) && filemtime($cache_file) > time() - 15 * 60) {
    $cached = json_decode(file_get_contents($cache_file), true);
    if ($cached && strtotime($cached['expires_at']) > time() + 120) {
        $offers = $cached['offers'];
    }
}

if ($offers === null) {
    $result = duffel_search_flights($search['slices'], $search['adults'], $search['children'], $search['infants'], $search['cabin']);

    if (!$result['ok']) {
        write_log('search', 'Flight search failed', ['code' => $result['error_code'], 'error' => $result['error']]);
        $messages = [
            'not_configured'    => 'Flight search isn\'t switched on yet. Please contact us and we\'ll find fares for you.',
            'rate_limit_exceeded' => 'Lots of people are searching right now. Please try again in a minute.',
            'connection_failed' => 'We couldn\'t reach the airlines just now. Please check your connection and try again.',
        ];
        $message = isset($messages[$result['error_code']]) ? $messages[$result['error_code']] : 'We couldn\'t get fares for this search right now. Please try again, or contact us and we\'ll help.';
        echo json_encode(['ok' => false, 'message' => $message]);
        exit;
    }

    $offers = [];
    foreach ($result['data']['offers'] as $raw) {
        $offer = simplify_offer($raw);
        if ($offer) {
            $offers[] = $offer;
        }
    }
    usort($offers, function ($a, $b) { return $a['price']['total_kobo'] - $b['price']['total_kobo']; });

    $expires_at = $offers ? min(array_column($offers, 'expires_at')) : gmdate('Y-m-d\TH:i:s\Z', time() + 600);
    file_put_contents($cache_file, json_encode(['expires_at' => $expires_at, 'offers' => $offers]));
}

if (!$offers) {
    echo json_encode(['ok' => true, 'count' => 0, 'html' => '', 'message' => 'No flights found for these dates. Try different dates or a nearby airport.']);
    exit;
}

// Build the cards
ob_start();
foreach ($offers as $n => $offer) {
    include '../includes/flight-card.php';
}
$html = ob_get_clean();

// Airlines and cheapest price for each, for the airline filter
$airlines = [];
foreach ($offers as $offer) {
    $code = $offer['airline_code'];
    if (!isset($airlines[$code]) || $offer['price']['total_kobo'] < $airlines[$code]['price']) {
        $airlines[$code] = ['code' => $code, 'name' => $offer['airline'], 'price' => $offer['price']['total_kobo']];
    }
}
usort($airlines, function ($a, $b) { return $a['price'] - $b['price']; });
foreach ($airlines as $i => $a) {
    $airlines[$i]['price_text'] = naira($a['price']);
}

$prices = array_map(function ($o) { return $o['price']['total_kobo']; }, $offers);

echo json_encode([
    'ok'         => true,
    'count'      => count($offers),
    'html'       => $html,
    'airlines'   => array_values($airlines),
    'min_price'  => min($prices),
    'max_price'  => max($prices),
    'expires_at' => min(array_column($offers, 'expires_at')),
]);
