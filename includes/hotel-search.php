<?php
// Reads and checks a hotel search from the URL:
//   ?near=LOS&check_in=2026-12-01&check_out=2026-12-04&rooms=1&adults=2&children=0
// "near" is an airport code from the search box; we search around that airport's coordinates.
// near=TEST searches Duffel's test hotels (only with a duffel_test_ token, see docs/API_NOTES.md).
// Returns ['error' => '...'] or the cleaned search with lat/lng.

function read_hotel_search($q)
{
    $near = isset($q['near']) && is_string($q['near']) ? strtoupper(trim($q['near'])) : '';
    $check_in = isset($q['check_in']) && is_string($q['check_in']) ? $q['check_in'] : '';
    $check_out = isset($q['check_out']) && is_string($q['check_out']) ? $q['check_out'] : '';
    $rooms = isset($q['rooms']) ? (int) $q['rooms'] : 1;
    $adults = isset($q['adults']) ? (int) $q['adults'] : 2;
    $children = isset($q['children']) ? (int) $q['children'] : 0;

    if ($near == 'TEST' && FLIGHT_DATA == 'live' && strpos(DUFFEL_TOKEN, 'duffel_test_') === 0) {
        $place = ['city' => 'Duffel test hotels', 'lat' => DUFFEL_TEST_HOTELS_LAT, 'lng' => DUFFEL_TEST_HOTELS_LNG, 'radius' => 2];
    } else {
        $a = preg_match('/^[A-Z]{3}$/', $near) ? db_one("SELECT city_name, latitude, longitude FROM airports WHERE iata_code = ?", [$near]) : null;
        if (!$a) {
            return ['error' => 'Please choose a city from the list.'];
        }
        // Airports are usually outside the city centre, so search a wide circle around them
        $place = ['city' => $a['city_name'], 'lat' => $a['latitude'], 'lng' => $a['longitude'], 'radius' => 25];
    }

    $today = date('Y-m-d');
    if (!valid_date($check_in) || $check_in < $today || $check_in > date('Y-m-d', strtotime('+330 days'))) {
        return ['error' => 'Please choose a check-in date from today up to 330 days ahead.'];
    }
    if (!valid_date($check_out) || $check_out <= $check_in) {
        return ['error' => 'The check-out date must be after check-in.'];
    }
    $nights = (int) round((strtotime($check_out) - strtotime($check_in)) / 86400);
    if ($nights > 30) {
        return ['error' => 'For stays longer than 30 nights, please contact us.'];
    }
    if ($rooms < 1 || $rooms > 8 || $adults < $rooms || $adults > 16 || $children < 0 || $children > 8) {
        return ['error' => 'Check the rooms and guests: at least one adult per room, up to 8 rooms.'];
    }

    return ['error' => '', 'near' => $near, 'city' => $place['city'], 'lat' => $place['lat'], 'lng' => $place['lng'], 'radius' => $place['radius'],
        'check_in' => $check_in, 'check_out' => $check_out, 'nights' => $nights, 'rooms' => $rooms, 'adults' => $adults, 'children' => $children];
}

// The search as a URL query, to carry it from page to page
function hotel_search_query($s)
{
    return http_build_query(['near' => $s['near'], 'check_in' => $s['check_in'], 'check_out' => $s['check_out'],
        'rooms' => $s['rooms'], 'adults' => $s['adults'], 'children' => $s['children']]);
}
