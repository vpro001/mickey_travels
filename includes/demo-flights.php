<?php
// DEMO MODE ONLY (config: FLIGHT_DATA = 'demo').
// Makes up flight offers in exactly the format Duffel returns (see docs/API_NOTES.md),
// so the results and booking pages can be shown before the Duffel account is ready.
// Fares are invented. Routes use real airports and sensible connections.
// The site shows a "Demo data" banner whenever this file is in use.

function demo_duffel($method, $path, $body)
{
    if (strpos($path, '/stays/') === 0) {
        require_once __DIR__ . '/demo-stays.php';
        return demo_stays($method, $path, $body);
    }
    if ($method == 'POST' && strpos($path, '/air/offer_requests') === 0) {
        return ['ok' => true, 'data' => demo_offer_request($body), 'error_code' => '', 'error' => ''];
    }
    if ($method == 'GET' && strpos($path, '/air/offers/') === 0) {
        // Look the offer up again from the saved search (used when booking)
        $id = basename(strtok($path, '?'));
        $file = __DIR__ . '/../storage/cache/demo-offer-' . preg_replace('/[^a-z0-9_]/i', '', $id) . '.json';
        if (file_exists($file)) {
            return ['ok' => true, 'data' => json_decode(file_get_contents($file), true), 'error_code' => '', 'error' => ''];
        }
        return ['ok' => false, 'data' => null, 'error_code' => 'offer_no_longer_available', 'error' => 'This offer is no longer available.'];
    }
    if ($method == 'POST' && $path == '/air/orders') {
        // Pretend the airline issued the tickets: booking reference + one e-ticket per passenger
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $pnr = '';
        for ($i = 0; $i < 6; $i++) $pnr .= $chars[random_int(0, 31)];
        $documents = [];
        foreach ($body['passengers'] as $p) {
            $documents[] = ['type' => 'electronic_ticket', 'unique_identifier' => '0' . random_int(100000000000, 999999999999), 'passenger_ids' => [$p['id']]];
        }
        return ['ok' => true, 'error_code' => '', 'error' => '', 'data' => [
            'id' => 'ord_demo' . bin2hex(random_bytes(8)),
            'booking_reference' => $pnr,
            'documents' => $documents,
            'total_amount' => $body['payments'][0]['amount'],
            'total_currency' => $body['payments'][0]['currency'],
        ]];
    }
    if ($method == 'GET' && strpos($path, '/places/suggestions') === 0) {
        return ['ok' => true, 'data' => [], 'error_code' => '', 'error' => ''];
    }
    return ['ok' => false, 'data' => null, 'error_code' => 'not_in_demo', 'error' => 'This is not available in demo mode.'];
}

// Airlines used in the demo. type: domestic (Nigeria only), direct (listed airports), hub (connects through its hub)
function demo_airlines()
{
    return [
        ['P4', 'Air Peace', 'domestic', null, 1.00],
        ['QI', 'Ibom Air', 'domestic', null, 1.08],
        ['W3', 'Arik Air', 'domestic', null, 0.95],
        ['BA', 'British Airways', 'direct', ['LHR'], 1.25],
        ['VS', 'Virgin Atlantic', 'direct', ['LHR'], 1.18],
        ['AW', 'Africa World Airlines', 'direct', ['ACC'], 0.9],
        ['ET', 'Ethiopian Airlines', 'hub', 'ADD', 0.92],
        ['TK', 'Turkish Airlines', 'hub', 'IST', 0.98],
        ['QR', 'Qatar Airways', 'hub', 'DOH', 1.10],
        ['EK', 'Emirates', 'hub', 'DXB', 1.12],
        ['KQ', 'Kenya Airways', 'hub', 'NBO', 0.88],
        ['AT', 'Royal Air Maroc', 'hub', 'CMN', 0.86],
    ];
}

function demo_airport($code)
{
    static $cache = [];
    if (!isset($cache[$code])) {
        $a = db_one("SELECT iata_code, name, city_name, country_code, latitude, longitude FROM airports WHERE iata_code = ?", [$code]);
        $cache[$code] = $a ?: ['iata_code' => $code, 'name' => $code, 'city_name' => $code, 'country_code' => '', 'latitude' => 0, 'longitude' => 0];
    }
    return $cache[$code];
}

// Distance in km between two airports
function demo_km($a, $b)
{
    $a = demo_airport($a);
    $b = demo_airport($b);
    $lat1 = deg2rad($a['latitude']); $lat2 = deg2rad($b['latitude']);
    $dlat = $lat2 - $lat1; $dlon = deg2rad($b['longitude'] - $a['longitude']);
    $h = sin($dlat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dlon / 2) ** 2;
    return 6371 * 2 * asin(min(1, sqrt($h)));
}

// Which airports this airline would fly through for this trip, or null if it doesn't fly it
function demo_route($airline, $from, $to)
{
    list($code, $name, $type, $where) = $airline;
    $domestic = demo_airport($from)['country_code'] == 'NG' && demo_airport($to)['country_code'] == 'NG';
    if ($type == 'domestic') {
        return $domestic ? [$from, $to] : null;
    }
    if ($domestic) {
        return null;
    }
    if ($type == 'direct') {
        return in_array($to, $where) || in_array($from, $where) ? [$from, $to] : null;
    }
    // Hub airline: flies to/from its hub directly, everything else connects through it
    if ($from == $where || $to == $where) {
        return [$from, $to];
    }
    // Do not connect through a hub that is a very big detour (more than about double the distance)
    if (demo_km($from, $where) + demo_km($where, $to) > demo_km($from, $to) * 2.2 + 500) {
        return null;
    }
    return [$from, $where, $to];
}

function demo_iso_duration($minutes)
{
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return 'PT' . ($h ? $h . 'H' : '') . ($m ? str_pad($m, 2, '0', STR_PAD_LEFT) . 'M' : '');
}

function demo_place($code)
{
    $a = demo_airport($code);
    return ['type' => 'airport', 'iata_code' => $code, 'name' => $a['name'], 'city_name' => $a['city_name'], 'iata_country_code' => $a['country_code']];
}

function demo_offer_request($body)
{
    // Same search = same results, so reloading the page doesn't shuffle everything
    mt_srand(crc32(json_encode($body)));

    // Passengers, with Duffel-style ids
    $passengers = [];
    foreach ($body['passengers'] as $n => $p) {
        $type = isset($p['type']) ? $p['type'] : ($p['age'] < 2 ? 'infant_without_seat' : 'child');
        $passengers[] = ['id' => 'pas_demo' . $n, 'type' => $type, 'age' => isset($p['age']) ? $p['age'] : null];
    }

    $cabin_factor = ['economy' => 1, 'premium_economy' => 1.8, 'business' => 3.6, 'first' => 6][$body['cabin_class']];
    $cabin_name = ['economy' => 'Economy', 'premium_economy' => 'Premium Economy', 'business' => 'Business', 'first' => 'First'][$body['cabin_class']];
    $expires = gmdate('Y-m-d\TH:i:s\Z', time() + 30 * 60);   // real offers expire after about 30 minutes

    $offers = [];
    foreach (demo_airlines() as $airline) {
        // Two departure times per airline, so there's a choice of times
        foreach ([0, 1] as $option) {
            $slices = [];
            $km_total = 0;
            foreach ($body['slices'] as $s) {
                $airports = demo_route($airline, $s['origin'], $s['destination']);
                if ($airports === null) {
                    continue 3;   // this airline doesn't fly this trip
                }
                $hour = [6, 8, 10, 13, 15, 18, 21, 23][mt_rand(0, 7)];
                if ($option == 1) $hour = ($hour + 7) % 24;
                $time = strtotime($s['departure_date'] . sprintf(' %02d:%02d:00', $hour, [0, 15, 30, 45][mt_rand(0, 3)]));

                $segments = [];
                for ($i = 0; $i < count($airports) - 1; $i++) {
                    $km = demo_km($airports[$i], $airports[$i + 1]);
                    $km_total += $km;
                    $minutes = (int) (round(($km / 800 * 60 + 35) / 5) * 5);
                    // Times are local to each airport in Duffel. The demo keeps it simple and uses one clock.
                    $depart = $time;
                    $arrive = $time + $minutes * 60;
                    $segments[] = [
                        'id' => 'seg_demo' . mt_rand(100000, 999999),
                        'origin' => demo_place($airports[$i]),
                        'destination' => demo_place($airports[$i + 1]),
                        'origin_terminal' => (string) mt_rand(1, 3),
                        'destination_terminal' => (string) mt_rand(1, 5),
                        'departing_at' => date('Y-m-d\TH:i:s', $depart),
                        'arriving_at' => date('Y-m-d\TH:i:s', $arrive),
                        'duration' => demo_iso_duration($minutes),
                        'marketing_carrier' => ['iata_code' => $airline[0], 'name' => $airline[1]],
                        'marketing_carrier_flight_number' => (string) mt_rand(100, 980),
                        'operating_carrier' => ['iata_code' => $airline[0], 'name' => $airline[1]],
                        'operating_carrier_flight_number' => (string) mt_rand(100, 980),
                        'aircraft' => ['name' => $km > 3000 ? ['Boeing 787-9', 'Airbus A350-900', 'Boeing 777-300ER'][mt_rand(0, 2)] : ['Boeing 737-800', 'Airbus A320', 'Embraer 195'][mt_rand(0, 2)]],
                        'passengers' => array_map(function ($p) use ($cabin_name, $km) {
                            return [
                                'passenger_id' => $p['id'],
                                'cabin_class_marketing_name' => $cabin_name,
                                'baggages' => [
                                    ['type' => 'checked', 'quantity' => $p['type'] == 'infant_without_seat' ? 0 : ($km > 3000 ? 2 : 1)],
                                    ['type' => 'carry_on', 'quantity' => $p['type'] == 'infant_without_seat' ? 0 : 1],
                                ],
                            ];
                        }, $passengers),
                    ];
                    $time = $arrive + mt_rand(70, 260) * 60;   // time to connect
                }
                $first = $segments[0];
                $last = $segments[count($segments) - 1];
                $slices[] = [
                    'origin' => demo_place($s['origin']),
                    'destination' => demo_place($s['destination']),
                    'duration' => demo_iso_duration((strtotime($last['arriving_at']) - strtotime($first['departing_at'])) / 60),
                    'fare_brand_name' => ['Basic', 'Standard', 'Flex'][mt_rand(0, 2)],
                    'segments' => $segments,
                ];
            }

            // Price per adult in USD, from distance; children 75%, infants 10%
            $direct = count($slices[0]['segments']) == 1;
            $adult_usd = (80 + $km_total * 0.075) * $airline[4] * $cabin_factor * ($direct ? 1.12 : 1) * (0.92 + mt_rand(0, 20) / 100);
            $total_usd = 0;
            foreach ($passengers as $p) {
                $total_usd += $adult_usd * ($p['type'] == 'adult' ? 1 : ($p['type'] == 'child' ? 0.75 : 0.1));
            }
            // Nigerian airlines price in naira, British Airways in pounds, the rest in dollars
            if ($airline[2] == 'domestic') {
                $currency = 'NGN';
                $total = round($total_usd * 1550, -2);
            } elseif ($airline[0] == 'BA' || $airline[0] == 'VS') {
                $currency = 'GBP';
                $total = round($total_usd * 0.76, 2);
            } else {
                $currency = 'USD';
                $total = round($total_usd, 2);
            }

            $offer = [
                'id' => 'off_demo' . substr(md5(json_encode($body) . $airline[0] . $option), 0, 16),
                'total_amount' => number_format($total, 2, '.', ''),
                'total_currency' => $currency,
                'base_amount' => number_format($total * 0.68, 2, '.', ''),
                'tax_amount' => number_format($total * 0.32, 2, '.', ''),
                'expires_at' => $expires,
                'owner' => ['iata_code' => $airline[0], 'name' => $airline[1], 'logo_symbol_url' => null],
                'passengers' => $passengers,
                'passenger_identity_documents_required' => $airline[2] != 'domestic',
                'conditions' => [
                    'refund_before_departure' => mt_rand(0, 2) ? ['allowed' => true, 'penalty_amount' => number_format($total * 0.25, 2, '.', ''), 'penalty_currency' => $currency] : ['allowed' => false],
                    'change_before_departure' => ['allowed' => true, 'penalty_amount' => number_format($total * 0.1, 2, '.', ''), 'penalty_currency' => $currency],
                ],
                'slices' => $slices,
            ];
            // Save it so the booking page can "re-fetch" it like the real API
            file_put_contents(__DIR__ . '/../storage/cache/demo-offer-' . $offer['id'] . '.json', json_encode($offer));
            $offers[] = $offer;
        }
    }

    return ['id' => 'orq_demo' . substr(md5(json_encode($body)), 0, 12), 'offers' => $offers, 'passengers' => $passengers];
}
