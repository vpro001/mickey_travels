<?php
// Airport suggestions for the search box.
// api/airport-suggest.php?q=lag  ->  [{"code":"LOS","city":"Lagos","name":"Murtala Muhammed International Airport","country":"Nigeria"}]

require '../includes/init.php';
require '../includes/duffel.php';

header('Content-Type: application/json');

// Suggestions fire as people type, so the limit is generous
if (!rate_limit('suggest:' . $_SERVER['REMOTE_ADDR'], 120, 60)) {
    http_response_code(429);
    echo '[]';
    exit;
}

$q = get('q');
if (strlen($q) < 2) {
    echo '[]';
    exit;
}

function find_airports($q)
{
    // Escape % and _ so they're searched as normal characters
    $like = addcslashes($q, '%_\\') . '%';
    return db_all("
        SELECT iata_code AS code, city_name AS city, name, country_name AS country
        FROM airports
        WHERE iata_code = ? OR city_name LIKE ? OR name LIKE ? OR country_name LIKE ?
        ORDER BY iata_code = ? DESC, city_name LIKE ? DESC, popularity DESC
        LIMIT 8
    ", [strtoupper($q), $like, $like, $like, strtoupper($q), $like]);
}

$airports = find_airports($q);

// Nothing in our own list? Ask Duffel and save what it finds for next time.
if (!$airports && DUFFEL_TOKEN != '' && FLIGHT_DATA == 'live') {
    $result = duffel('GET', '/places/suggestions?query=' . urlencode($q), null, 10);
    if ($result['ok']) {
        foreach ($result['data'] as $place) {
            if ($place['type'] == 'airport') {
                db_query("INSERT IGNORE INTO airports (iata_code, name, city_name, country_code, latitude, longitude, popularity)
                          VALUES (?, ?, ?, ?, ?, ?, 5)",
                    [$place['iata_code'], $place['name'], $place['city_name'], $place['iata_country_code'], (string) $place['latitude'], (string) $place['longitude']]);
            }
        }
        $airports = find_airports($q);
    }
}

header('Cache-Control: public, max-age=86400');
echo json_encode($airports);
