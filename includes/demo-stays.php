<?php
// DEMO MODE ONLY (config: FLIGHT_DATA = 'demo').
// Makes up hotel results in the format Duffel Stays returns (docs/API_NOTES.md → Stays),
// so the hotel pages can be shown before Duffel switches on Stays for the account.
// Hotel names and prices are invented and clearly fictional; photos are our own.
// There are deliberately NO review scores or counts in demo data.

function demo_stays($method, $path, $body)
{
    $dir = __DIR__ . '/../storage/cache/';

    if ($method == 'POST' && $path == '/stays/search') {
        $results = demo_stays_results($body);
        foreach ($results as $r) {
            file_put_contents($dir . 'demo-stay-' . $r['id'] . '.json', json_encode($r));
        }
        return ['ok' => true, 'error_code' => '', 'error' => '', 'data' => ['results' => $results, 'created_at' => gmdate('Y-m-d\TH:i:s\Z')]];
    }

    if ($method == 'POST' && preg_match('#^/stays/search_results/(srr_demo[a-z0-9]+)/actions/fetch_all_rates$#', $path, $m)) {
        $file = $dir . 'demo-stay-' . $m[1] . '.json';
        if (!file_exists($file)) {
            return ['ok' => false, 'data' => null, 'error_code' => 'not_found', 'error' => 'Search result expired'];
        }
        $result = json_decode(file_get_contents($file), true);
        $result['accommodation']['rooms'] = demo_stays_rooms($result);
        file_put_contents($file, json_encode($result));
        return ['ok' => true, 'error_code' => '', 'error' => '', 'data' => $result];
    }

    if ($method == 'POST' && $path == '/stays/quotes') {
        // Find the rate in the saved results
        foreach (glob($dir . 'demo-stay-srr_demo*.json') as $file) {
            $result = json_decode(file_get_contents($file), true);
            foreach ($result['accommodation']['rooms'] as $room) {
                foreach ($room['rates'] as $rate) {
                    if ($rate['id'] == $body['rate_id']) {
                        $quote = [
                            'id' => 'quo_demo' . substr(md5($rate['id'] . microtime()), 0, 16),
                            'total_amount' => $rate['total_amount'], 'total_currency' => $rate['total_currency'],
                            'check_in_date' => $result['check_in_date'], 'check_out_date' => $result['check_out_date'],
                            'rooms' => $result['rooms'], 'guests' => $result['guests'],
                            'accommodation' => array_merge($result['accommodation'], ['rooms' => [array_merge($room, ['rates' => [$rate]])]]),
                        ];
                        file_put_contents($dir . 'demo-quote-' . $quote['id'] . '.json', json_encode($quote));
                        return ['ok' => true, 'error_code' => '', 'error' => '', 'data' => $quote];
                    }
                }
            }
        }
        return ['ok' => false, 'data' => null, 'error_code' => 'rate_unavailable', 'error' => 'This rate is no longer available.'];
    }

    if ($method == 'POST' && $path == '/stays/bookings') {
        if (!file_exists($dir . 'demo-quote-' . basename($body['quote_id']) . '.json')) {
            return ['ok' => false, 'data' => null, 'error_code' => 'quote_not_found', 'error' => 'Quote not found'];
        }
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $ref = '';
        for ($i = 0; $i < 8; $i++) $ref .= $chars[random_int(0, 31)];
        return ['ok' => true, 'error_code' => '', 'error' => '', 'data' => [
            'id' => 'bok_demo' . bin2hex(random_bytes(8)), 'reference' => $ref, 'status' => 'confirmed', 'confirmed_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ]];
    }

    return ['ok' => false, 'data' => null, 'error_code' => 'not_in_demo', 'error' => 'Not available in demo mode.'];
}

function demo_stays_results($body)
{
    mt_srand(crc32(json_encode($body)));
    $lat = $body['location']['geographic_coordinates']['latitude'];
    $lng = $body['location']['geographic_coordinates']['longitude'];
    $nights = max(1, (strtotime($body['check_out_date']) - strtotime($body['check_in_date'])) / 86400);
    $place = db_one("SELECT city_name, country_code FROM airports ORDER BY POW(latitude - ?, 2) + POW(longitude - ?, 2) LIMIT 1", [$lat, $lng]);
    $city = $place ? $place['city_name'] : 'City';

    // Fictional hotel names
    $names = ['Harbourline Suites', 'The Cotton Tree Hotel', 'Palmgrove Residences', 'Kora House', 'Saffron Court Hotel', 'Lagoon Edge Apartments', 'The Meridian Walk', 'Ironwood Lodge'];
    $amenities = [['type' => 'wifi', 'description' => 'Free Wi-Fi'], ['type' => 'pool', 'description' => 'Pool'], ['type' => 'gym', 'description' => 'Gym'],
        ['type' => 'parking', 'description' => 'Parking'], ['type' => 'restaurant', 'description' => 'Restaurant'], ['type' => '24_hour_front_desk', 'description' => '24-hour front desk']];
    $results = [];
    foreach ($names as $n => $name) {
        $stars = [3, 4, 4, 5, 3, 4, 5, 3][$n];
        $per_night_usd = (35 + $stars * 28) * (0.85 + mt_rand(0, 40) / 100);
        $total = round($per_night_usd * $nights * $body['rooms'], 2);
        $id = 'srr_demo' . substr(md5(json_encode($body) . $name), 0, 16);
        $results[] = [
            'id' => $id,
            'expires_at' => gmdate('Y-m-d\TH:i:s\Z', time() + 30 * 60),
            'check_in_date' => $body['check_in_date'], 'check_out_date' => $body['check_out_date'],
            'rooms' => $body['rooms'], 'guests' => $body['guests'],
            'cheapest_rate_total_amount' => number_format($total, 2, '.', ''), 'cheapest_rate_currency' => 'USD',
            'accommodation' => [
                'id' => 'acc_demo' . substr(md5($name . $city), 0, 12),
                'name' => $name . ' (demo)',
                'rating' => $stars,
                // No review score or count in demo mode: we never show made-up reviews
                'photos' => [['url' => 'assets/img/' . ($n % 2 ? 'hotel-room' : 'hotel-pool') . '-800.webp']],
                'description' => 'A fictional hotel used to demonstrate the booking flow.',
                'location' => [
                    'geographic_coordinates' => ['latitude' => $lat + (mt_rand(-80, 80) / 1000), 'longitude' => $lng + (mt_rand(-80, 80) / 1000)],
                    'address' => ['line_one' => (mt_rand(2, 120)) . ' ' . ['Marina Road', 'Harbour Street', 'Garden Avenue', 'Central Boulevard', 'Airport Road', 'Ring Road', 'Station Road', 'Park Lane'][$n], 'city_name' => $city, 'country_code' => $place ? $place['country_code'] : ''],
                ],
                'amenities' => array_slice($amenities, $n % 3, 3 + $stars % 3),
                'rooms' => [],
            ],
        ];
    }
    return $results;
}

// Rooms and rates for one hotel (what fetch_all_rates returns)
function demo_stays_rooms($result)
{
    mt_srand(crc32($result['id']));
    $base = (float) $result['cheapest_rate_total_amount'];
    $rooms = [];
    foreach ([['Standard Room', 1.0, 'double'], ['Deluxe Room', 1.35, 'king'], ['Junior Suite', 1.9, 'king']] as $k => $room) {
        $rates = [];
        foreach ([['room_only', 1.0, false], ['breakfast', 1.12, true]] as $j => $rate) {
            $amount = round($base * $room[1] * $rate[1], 2);
            $refundable = $rate[2] || $k == 2;
            $rates[] = [
                'id' => 'rat_demo' . substr(md5($result['id'] . $k . $j), 0, 16),
                'name' => $refundable ? 'Flexible rate' : 'Saver rate',
                'total_amount' => number_format($amount, 2, '.', ''), 'total_currency' => 'USD',
                'board_type' => $rate[0],
                'payment_type' => 'pay_now',
                'available_payment_methods' => [['balance']],
                'quantity_available' => mt_rand(1, 6),
                'cancellation_timeline' => $refundable
                    ? [['refund_amount' => number_format($amount, 2, '.', ''), 'currency' => 'USD', 'before' => date('Y-m-d\T12:00:00\Z', strtotime($result['check_in_date'] . ' -3 days'))]]
                    : [],
            ];
        }
        $rooms[] = ['name' => $room[0], 'beds' => [['type' => $room[2], 'count' => 1]], 'photos' => [], 'rates' => $rates];
    }
    return $rooms;
}
