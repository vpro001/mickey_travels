<?php
// Duffel API: flights and hotels. What each call expects is written up in docs/API_NOTES.md.
//
// Each call returns:
//   ['ok' => true/false, 'data' => ..., 'error_code' => 'offer_expired' etc., 'error' => message]
// In demo mode (FLIGHT_DATA = 'demo') the answers are made up by includes/demo-flights.php,
// in exactly the same format Duffel uses, so the rest of the site works the same either way.

require_once __DIR__ . '/http.php';
require_once __DIR__ . '/pricing.php';

function duffel($method, $path, $body = null, $timeout = 60)
{
    if (FLIGHT_DATA === 'demo') {
        require_once __DIR__ . '/demo-flights.php';
        return demo_duffel($method, $path, $body);
    }

    $headers = [
        'Authorization'   => 'Bearer ' . DUFFEL_TOKEN,
        'Accept'          => 'application/json',
        'Accept-Encoding' => 'gzip',
        'Duffel-Version'  => 'v2',
    ];
    $data = $body === null ? null : ['data' => $body];

    $r = http_request($method, 'https://api.duffel.com' . $path, $headers, $data, $timeout);

    if ($r['ok']) {
        return ['ok' => true, 'data' => $r['data']['data'], 'error_code' => '', 'error' => ''];
    }
    $first = isset($r['data']['errors'][0]) ? $r['data']['errors'][0] : ['code' => 'connection_failed', 'message' => $r['error']];
    return ['ok' => false, 'data' => null, 'error_code' => $first['code'], 'error' => $first['message']];
}


// ------------------------------------------------------------ flight search

// Search for flights. $slices = [['origin' => 'LOS', 'destination' => 'LHR', 'departure_date' => '2026-12-01'], ...]
// Children are sent as age 10 and infants as age 1, because the search box asks for
// age groups, not exact ages (Duffel takes either a type or an age for each passenger).
function duffel_search_flights($slices, $adults, $children, $infants, $cabin)
{
    $passengers = [];
    for ($i = 0; $i < $adults; $i++) $passengers[] = ['type' => 'adult'];
    for ($i = 0; $i < $children; $i++) $passengers[] = ['age' => 10];
    for ($i = 0; $i < $infants; $i++) $passengers[] = ['age' => 1];

    $body = [
        'slices'          => $slices,
        'passengers'      => $passengers,
        'cabin_class'     => $cabin,
        'max_connections' => 2,
    ];
    // supplier_timeout: how long Duffel waits for airlines (ms). The call can take that long.
    return duffel('POST', '/air/offer_requests?return_offers=true&supplier_timeout=20000', $body, 45);
}

// Turn one Duffel offer into a simpler array for the results page, with the Naira price added.
// Returns null if we can't price it (no exchange rate for its currency).
function simplify_offer($offer)
{
    $passenger_count = count($offer['passengers']);
    $price = price_in_naira($offer['total_amount'], $offer['total_currency'], $passenger_count);
    if ($price === null) {
        return null;
    }

    $slices = [];
    $total_minutes = 0;
    $max_stops = 0;
    foreach ($offer['slices'] as $slice) {
        $segments = $slice['segments'];
        $first = $segments[0];
        $last = $segments[count($segments) - 1];
        $stops = count($segments) - 1;
        $max_stops = max($max_stops, $stops);

        $minutes = duration_minutes($slice['duration']);
        $total_minutes += $minutes;

        $legs = [];
        foreach ($segments as $n => $s) {
            $legs[] = [
                'from'          => $s['origin']['iata_code'],
                'from_name'     => $s['origin']['name'],
                'from_terminal' => isset($s['origin_terminal']) ? $s['origin_terminal'] : '',
                'to'            => $s['destination']['iata_code'],
                'to_name'       => $s['destination']['name'],
                'to_terminal'   => isset($s['destination_terminal']) ? $s['destination_terminal'] : '',
                'depart'        => $s['departing_at'],
                'arrive'        => $s['arriving_at'],
                'duration'      => isset($s['duration']) ? $s['duration'] : '',
                'flight'        => $s['marketing_carrier']['iata_code'] . ' ' . $s['marketing_carrier_flight_number'],
                'operated_by'   => $s['operating_carrier']['name'],
                'aircraft'      => isset($s['aircraft']['name']) ? $s['aircraft']['name'] : '',
                'cabin'         => isset($s['passengers'][0]['cabin_class_marketing_name']) ? $s['passengers'][0]['cabin_class_marketing_name'] : '',
                // waiting time at the airport before the next flight
                'layover'       => isset($segments[$n + 1]) ? (strtotime($segments[$n + 1]['departing_at']) - strtotime($s['arriving_at'])) / 60 : 0,
            ];
        }

        $slices[] = [
            'from'     => $slice['origin']['iata_code'],
            'to'       => $slice['destination']['iata_code'],
            'from_city' => isset($slice['origin']['city_name']) ? $slice['origin']['city_name'] : $slice['origin']['name'],
            'to_city'  => isset($slice['destination']['city_name']) ? $slice['destination']['city_name'] : $slice['destination']['name'],
            'depart'   => $first['departing_at'],
            'arrive'   => $last['arriving_at'],
            'minutes'  => $minutes,
            'stops'    => $stops,
            'via'      => array_map(function ($s) { return $s['destination']['iata_code']; }, array_slice($segments, 0, -1)),
            'legs'     => $legs,
            'brand'    => isset($slice['fare_brand_name']) ? $slice['fare_brand_name'] : '',
        ];
    }

    // Baggage for the first passenger on the first flight (it's per passenger, per flight)
    $checked = 0;
    $carry_on = 0;
    $bags = isset($offer['slices'][0]['segments'][0]['passengers'][0]['baggages']) ? $offer['slices'][0]['segments'][0]['passengers'][0]['baggages'] : [];
    foreach ($bags as $bag) {
        if ($bag['type'] == 'checked') $checked += $bag['quantity'];
        if ($bag['type'] == 'carry_on') $carry_on += $bag['quantity'];
    }

    return [
        'id'           => $offer['id'],
        'airline'      => $offer['owner']['name'],
        'airline_code' => $offer['owner']['iata_code'],
        'logo'         => isset($offer['owner']['logo_symbol_url']) ? $offer['owner']['logo_symbol_url'] : '',
        'expires_at'   => $offer['expires_at'],
        'price'        => $price,
        'passengers'   => $passenger_count,
        'slices'       => $slices,
        'minutes'      => $total_minutes,
        'stops'        => $max_stops,
        'checked_bags' => $checked,
        'carry_on'     => $carry_on,
        'refund'       => isset($offer['conditions']['refund_before_departure']) ? $offer['conditions']['refund_before_departure'] : null,
        'change'       => isset($offer['conditions']['change_before_departure']) ? $offer['conditions']['change_before_departure'] : null,
        'needs_passport' => !empty($offer['passenger_identity_documents_required']),
    ];
}

// "PT7H35M" -> 455
function duration_minutes($iso)
{
    preg_match('/P(?:(\d+)D)?T?(?:(\d+)H)?(?:(\d+)M)?/', (string) $iso, $m);
    return (isset($m[1]) ? (int) $m[1] * 1440 : 0) + (isset($m[2]) ? (int) $m[2] * 60 : 0) + (isset($m[3]) ? (int) $m[3] : 0);
}

// 455 -> "7h 35m"
function minutes_text($minutes)
{
    $h = intdiv((int) $minutes, 60);
    $m = (int) $minutes % 60;
    return $h ? "{$h}h " . str_pad($m, 2, '0', STR_PAD_LEFT) . 'm' : "{$m}m";
}
