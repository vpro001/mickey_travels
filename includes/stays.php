<?php
// Hotels through Duffel Stays. The calls are written up in docs/API_NOTES.md → Stays:
//   1. POST /stays/search                                       → hotels near a point, with their cheapest price
//   2. POST /stays/search_results/{id}/actions/fetch_all_rates  → every room and rate for one hotel
//   3. POST /stays/quotes {rate_id}                             → a confirmed price for one rate
//   4. POST /stays/bookings {quote_id, guests, email, phone}    → the booking (paid from our Duffel balance)
// Duffel has no geocoder: we search around the coordinates of the city's airport (airports table).

require_once __DIR__ . '/duffel.php';
require_once __DIR__ . '/mail.php';

// Coordinates Duffel's docs give for finding its test hotels (test mode only)
define('DUFFEL_TEST_HOTELS_LAT', -24.38);
define('DUFFEL_TEST_HOTELS_LNG', -128.32);

function stays_search($lat, $lng, $radius_km, $check_in, $check_out, $rooms, $adults, $children)
{
    $guests = [];
    for ($i = 0; $i < $adults; $i++) $guests[] = ['type' => 'adult'];
    for ($i = 0; $i < $children; $i++) $guests[] = ['type' => 'child', 'age' => 8];
    return duffel('POST', '/stays/search', [
        'check_in_date' => $check_in,
        'check_out_date' => $check_out,
        'rooms' => $rooms,
        'guests' => $guests,
        'location' => ['radius' => $radius_km, 'geographic_coordinates' => ['latitude' => (float) $lat, 'longitude' => (float) $lng]],
    ], 45);
}

function stays_rates($search_result_id)
{
    return duffel('POST', '/stays/search_results/' . rawurlencode($search_result_id) . '/actions/fetch_all_rates', null, 45);
}

function stays_quote($rate_id)
{
    return duffel('POST', '/stays/quotes', ['rate_id' => $rate_id], 45);
}

// Price for the customer, using the hotel fee settings
function hotel_price($amount, $currency, $rooms)
{
    $type = setting('hotel_markup_type', 'flat_booking');
    $value = (float) setting('hotel_markup_value', 2000);
    return price_in_naira($amount, $currency, $rooms, $type == 'percent' ? 'percent' : 'flat_booking', $value);
}

// "breakfast" → "Breakfast included" etc.
function board_name($board)
{
    $names = ['room_only' => 'Room only', 'breakfast' => 'Breakfast included', 'half_board' => 'Breakfast and dinner', 'full_board' => 'All meals', 'all_inclusive' => 'All inclusive'];
    return isset($names[$board]) ? $names[$board] : ucfirst(str_replace('_', ' ', (string) $board));
}

// When can the guest cancel for free? From the rate's cancellation_timeline.
function cancellation_text($timeline, $total)
{
    if (!$timeline) {
        return 'Non-refundable';
    }
    foreach ($timeline as $step) {
        if ((float) $step['refund_amount'] >= (float) $total) {
            return 'Free cancellation until ' . date('j M Y, H:i', strtotime($step['before']));
        }
    }
    return 'Partly refundable if cancelled early';
}


// ------------------------------------------------------------ status, payment, booking

function hotel_status_rules()
{
    return [
        'paid'                   => ['pending_payment', 'payment_failed', 'expired'],
        'payment_failed'         => ['pending_payment'],
        'expired'                => ['pending_payment', 'payment_failed'],
        'booking'                => ['paid', 'needs_attention'],
        'confirmed'              => ['booking', 'needs_attention'],
        'needs_attention'        => ['paid', 'booking'],
        'cancellation_requested' => ['confirmed'],
        'cancelled'              => ['confirmed', 'cancellation_requested', 'needs_attention'],
        'refunded'               => ['cancelled', 'needs_attention'],
    ];
}

// Same rules as flight_status(): one UPDATE, only from allowed statuses, logged.
function hotel_status($booking_id, $new_status, $reason = null)
{
    $rules = hotel_status_rules();
    if (!isset($rules[$new_status])) return false;
    $from = $rules[$new_status];
    $marks = implode(',', array_fill(0, count($from), '?'));
    $stmt = db_query("UPDATE hotel_bookings SET status = ?, status_reason = ? WHERE id = ? AND status IN ($marks)", array_merge([$new_status, $reason, $booking_id], $from));
    $changed = mysqli_stmt_affected_rows($stmt) == 1;
    if ($changed) {
        db_insert('audit_log', ['user_id' => isset($_SESSION['staff']['id']) ? $_SESSION['staff']['id'] : null, 'action' => 'status:' . $new_status,
            'entity_type' => 'hotel_booking', 'entity_id' => $booking_id, 'details' => json_encode(['reason' => $reason]),
            'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'cli']);
    }
    return $changed;
}

// Create the hotel booking with Duffel. Only the process that moves "paid → booking" does it.
// Never retried automatically (Duffel: after a 500 the outcome is unknown; contact support).
function book_hotel($booking_id)
{
    if (!hotel_status($booking_id, 'booking', 'Creating the hotel booking')) {
        return;
    }
    set_time_limit(120);
    $b = db_one("SELECT * FROM hotel_bookings WHERE id = ?", [$booking_id]);
    $guests = json_decode($b['guests_json'], true);
    $body = [
        'quote_id' => $b['quote_id'],
        'guests' => $guests['names'],
        'email' => $b['contact_email'],
        'phone_number' => $b['contact_phone'],
        'metadata' => ['booking_ref' => $b['booking_ref']],
    ];
    if (!empty($guests['requests'])) {
        $body['accommodation_special_requests'] = $guests['requests'];
    }
    $r = duffel('POST', '/stays/bookings', $body, 90);   // no "payment": the docs say leave it out to pay from our balance

    if (!$r['ok'] || empty($r['data']['reference']) || $r['data']['status'] != 'confirmed') {
        $reason = in_array($r['error_code'], ['connection_failed', 'unknown'])
            ? 'The hotel did not give a clear answer. Check the Duffel dashboard BEFORE trying again.'
            : 'The hotel booking failed (' . $r['error_code'] . ': ' . $r['error'] . '). The customer has paid: rebook or refund.';
        hotel_status($booking_id, 'needs_attention', substr($reason, 0, 255));
        admin_alert('ACTION NEEDED: hotel booking ' . $b['booking_ref'], $reason . "\n\nCustomer: " . $b['contact_email'] . ', ' . $b['contact_phone'] . "\nPaid: " . naira($b['total_kobo']));
        return;
    }
    db_query("UPDATE hotel_bookings SET duffel_booking_id = ?, hotel_reference = ?, confirmed_at = NOW() WHERE id = ?",
        [$r['data']['id'], $r['data']['reference'], $booking_id]);
    hotel_status($booking_id, 'confirmed', 'Hotel confirmation ' . $r['data']['reference']);
    send_hotel_email($booking_id);
}

function send_hotel_email($booking_id)
{
    $b = db_one("SELECT * FROM hotel_bookings WHERE id = ?", [$booking_id]);
    $hotel = json_decode($b['accommodation_json'], true);
    $link = SITE_URL . '/hotel-booking.php?ref=' . urlencode($b['booking_ref']) . '&email=' . urlencode($b['contact_email']);
    $html = '<div style="font-family:Arial,sans-serif;color:#0f1f33;max-width:600px">'
        . '<h1 style="font-size:22px">Your hotel is booked</h1>'
        . '<p><b>' . e($hotel['name']) . '</b><br>' . e($hotel['address']) . '</p>'
        . '<p>Check-in ' . nice_date($b['check_in_date']) . ' · Check-out ' . nice_date($b['check_out_date']) . ' · ' . plural($b['rooms'], 'room') . '</p>'
        . '<p>Hotel confirmation number: <b style="font-size:18px;letter-spacing:1px">' . e($b['hotel_reference']) . '</b><br>Our reference: ' . e($b['booking_ref']) . '</p>'
        . '<p>Show the hotel confirmation number at check-in.</p>'
        . '<p>Total paid: <b>' . naira($b['total_kobo']) . '</b></p>'
        . '<p><a href="' . e($link) . '">View your booking</a></p><p>' . e(SITE_NAME) . '</p></div>';
    send_mail($b['contact_email'], 'Hotel booked: ' . $hotel['name'] . ' (' . $b['booking_ref'] . ')', $html);
}
