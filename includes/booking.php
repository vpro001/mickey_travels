<?php
// Flight bookings: status changes, confirming payment, and issuing the ticket.
// The allowed status changes are listed in docs/STATE_MACHINES.md.

require_once __DIR__ . '/duffel.php';
require_once __DIR__ . '/paystack.php';
require_once __DIR__ . '/mail.php';

// For each new status, the statuses a booking is allowed to come from
function flight_status_rules()
{
    return [
        'paid'                   => ['pending_payment', 'payment_failed', 'expired'],
        'payment_failed'         => ['pending_payment'],
        'expired'                => ['pending_payment', 'payment_failed'],
        'ticketing'              => ['paid', 'needs_attention'],
        'confirmed'              => ['ticketing', 'needs_attention'],
        'needs_attention'        => ['paid', 'ticketing'],
        'cancellation_requested' => ['confirmed'],
        'cancelled'              => ['confirmed', 'cancellation_requested', 'needs_attention'],
        'refunded'               => ['cancelled', 'needs_attention'],
    ];
}

// Change a booking's status, but only if that change is allowed from its current status.
// Done in a single UPDATE, so if two processes try at once only one succeeds.
// Returns true if the status was changed.
function flight_status($booking_id, $new_status, $reason = null)
{
    $rules = flight_status_rules();
    if (!isset($rules[$new_status])) {
        return false;
    }
    $from = $rules[$new_status];
    $marks = implode(',', array_fill(0, count($from), '?'));
    $stmt = db_query(
        "UPDATE flight_bookings SET status = ?, status_reason = ? WHERE id = ? AND status IN ($marks)",
        array_merge([$new_status, $reason, $booking_id], $from)
    );
    $changed = mysqli_stmt_affected_rows($stmt) == 1;
    if ($changed) {
        db_insert('audit_log', [
            'user_id' => isset($_SESSION['staff']['id']) ? $_SESSION['staff']['id'] : null,
            'action' => 'status:' . $new_status,
            'entity_type' => 'flight_booking',
            'entity_id' => $booking_id,
            'details' => json_encode(['reason' => $reason]),
            'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'cli',
        ]);
    }
    return $changed;
}

// Record a verified Paystack payment, then issue the ticket.
// Called by both payment-callback.php and the webhook, which can arrive in any order and
// more than once. The payment row is locked and "fulfilled_at" is set only once, so the
// ticket can never be issued twice.
// $verified is the 'data' part of Paystack's verify response.
function confirm_payment($reference, $verified)
{
    db_begin();
    $payment = db_one("SELECT * FROM payments WHERE reference = ? FOR UPDATE", [$reference]);
    if (!$payment) {
        db_rollback();
        write_log('payments', 'Unknown payment reference', ['reference' => $reference]);
        return null;
    }
    $booking_id = $payment['booking_id'];

    if ($payment['fulfilled_at']) {   // already handled (webhook and callback both came)
        db_commit();
        return $booking_id;
    }

    $status = isset($verified['status']) ? $verified['status'] : '';
    if ($status != 'success') {
        // Not paid (yet). Only "failed" and "abandoned" are final.
        if ($status == 'failed' || $status == 'abandoned') {
            db_query("UPDATE payments SET status = ?, verify_response = ? WHERE id = ?", [$status, json_encode($verified), $payment['id']]);
            if ($payment['booking_type'] == 'tour') {
                db_query("UPDATE tour_bookings SET status = 'payment_failed' WHERE id = ? AND status = 'pending_payment'", [$booking_id]);
            } elseif ($payment['booking_type'] == 'hotel') {
                require_once __DIR__ . '/stays.php';
                hotel_status($booking_id, 'payment_failed', 'Paystack: ' . $status);
            } else {
                flight_status($booking_id, 'payment_failed', 'Paystack: ' . $status);
            }
        }
        db_commit();
        return $booking_id;
    }

    db_query(
        "UPDATE payments SET status = 'success', paid_at = NOW(), fulfilled_at = NOW(), provider_txn_id = ?, channel = ?, verify_response = ? WHERE id = ?",
        [(string) $verified['id'], (string) $verified['channel'], json_encode($verified), $payment['id']]
    );
    $amount_ok = (int) $verified['amount'] === (int) $payment['amount_kobo'] && $verified['currency'] === $payment['currency'];

    // Tours: record the money and reserve the seats (includes/tours.php)
    if ($payment['booking_type'] == 'tour') {
        require_once __DIR__ . '/tours.php';
        if (!$amount_ok) {
            db_commit();
            admin_alert('ACTION NEEDED: tour payment ' . $reference, 'The amount paid (' . $verified['amount'] . ' ' . $verified['currency'] . ') does not match the expected ' . naira($payment['amount_kobo']) . '. Check it before confirming the tour booking.');
            return $booking_id;
        }
        tour_paid($booking_id, $payment);
        db_commit();
        send_tour_email($booking_id);
        return $booking_id;
    }

    // Hotels: same checks as flights, then create the hotel booking
    if ($payment['booking_type'] == 'hotel') {
        require_once __DIR__ . '/stays.php';
        hotel_status($booking_id, 'paid', 'Payment received by ' . $verified['channel']);
        if (!$amount_ok) {
            hotel_status($booking_id, 'needs_attention', 'Paid amount ' . $verified['amount'] . ' ' . $verified['currency'] . ' does not match the expected ' . $payment['amount_kobo']);
            db_commit();
            admin_alert('ACTION NEEDED: hotel payment ' . $reference, 'The amount paid does not match the booking. Check it before booking the hotel.');
            return $booking_id;
        }
        db_commit();
        book_hotel($booking_id);
        return $booking_id;
    }

    // Flights: the amount and currency must be exactly what we asked for
    if (!$amount_ok) {
        flight_status($booking_id, 'paid', 'Payment received');
        flight_status($booking_id, 'needs_attention', 'Paid amount ' . $verified['amount'] . ' ' . $verified['currency'] . ' does not match the expected ' . $payment['amount_kobo']);
        db_commit();
        alert_admin($booking_id, 'Payment amount does not match the booking. Do not issue the ticket until this is checked.');
        return $booking_id;
    }

    flight_status($booking_id, 'paid', 'Payment received by ' . $verified['channel']);
    db_commit();

    issue_ticket($booking_id);
    return $booking_id;
}

// Create the airline order with Duffel and save the ticket details.
// Only one process can move a booking from "paid" to "ticketing", so this runs once per booking.
function issue_ticket($booking_id)
{
    if (!flight_status($booking_id, 'ticketing', 'Creating the airline order')) {
        return;   // someone else is already doing it, or it's done
    }
    set_time_limit(200);   // creating an order can take up to 2 minutes (Duffel docs)

    $booking = db_one("SELECT * FROM flight_bookings WHERE id = ?", [$booking_id]);

    // 1. Check the offer again: the amount we pay Duffel must match its latest price
    $offer = duffel('GET', '/air/offers/' . rawurlencode($booking['offer_id']));
    if (!$offer['ok']) {
        needs_attention($booking_id, "The fare is no longer available ({$offer['error_code']}). The customer has paid: rebook or refund.");
        return;
    }
    $new_amount = $offer['data']['total_amount'];
    $currency = $offer['data']['total_currency'];
    if ($currency != $booking['supplier_currency'] || (float) $new_amount > (float) $booking['supplier_amount']) {
        needs_attention($booking_id, "The airline price went up after payment, from {$booking['supplier_amount']} to $new_amount $currency. Contact the customer.");
        return;
    }

    // 2. Create the order, paid from our Duffel balance. Never retried automatically:
    //    if the outcome is unclear, a person must check the Duffel dashboard first.
    $order = duffel('POST', '/air/orders', [
        'type'            => 'instant',
        'selected_offers' => [$booking['offer_id']],
        'payments'        => [['type' => 'balance', 'currency' => $currency, 'amount' => $new_amount]],
        'passengers'      => json_decode($booking['passengers_json'], true),
        'metadata'        => ['booking_ref' => $booking['booking_ref']],
    ], 130);

    if (!$order['ok'] || empty($order['data']['booking_reference'])) {
        $unclear = in_array($order['error_code'], ['connection_failed', 'unknown', 'airline_unknown', 'airline_internal']);
        needs_attention($booking_id, ($unclear
            ? 'The airline did not give a clear answer. Check the Duffel dashboard BEFORE trying again, the order may exist. '
            : 'The airline order failed. ') . "({$order['error_code']}: {$order['error']})");
        return;
    }

    // 3. Save the airline booking reference and the e-ticket numbers
    $data = $order['data'];
    $documents = isset($data['documents']) ? $data['documents'] : [];
    db_query(
        "UPDATE flight_bookings SET duffel_order_id = ?, pnr = ?, documents_json = ?, confirmed_at = NOW() WHERE id = ?",
        [$data['id'], $data['booking_reference'], json_encode($documents), $booking_id]
    );
    foreach ($documents as $doc) {
        foreach ($doc['passenger_ids'] as $passenger_id) {
            db_query("UPDATE flight_passengers SET ticket_number = ? WHERE booking_id = ? AND duffel_passenger_id = ?", [$doc['unique_identifier'], $booking_id, $passenger_id]);
        }
    }
    flight_status($booking_id, 'confirmed', 'Ticketed: ' . $data['booking_reference']);
    send_booking_email($booking_id);
}

function needs_attention($booking_id, $reason)
{
    flight_status($booking_id, 'needs_attention', substr($reason, 0, 255));
    write_log('bookings', 'Needs attention', ['booking' => $booking_id, 'reason' => $reason]);
    alert_admin($booking_id, $reason);
}

function alert_admin($booking_id, $reason)
{
    $b = db_one("SELECT booking_ref, contact_email, contact_phone, total_kobo FROM flight_bookings WHERE id = ?", [$booking_id]);
    admin_alert('ACTION NEEDED: booking ' . $b['booking_ref'],
        'Booking ' . $b['booking_ref'] . " needs a person to look at it.\n\n" . $reason
        . "\n\nCustomer: " . $b['contact_email'] . ', ' . $b['contact_phone'] . "\nPaid: " . naira($b['total_kobo']));
}

// Email the office (ADMIN_EMAIL). Without one set, the alert is only written to the log.
function admin_alert($subject, $text)
{
    write_log('alerts', $subject, ['text' => $text]);
    if (ADMIN_EMAIL != '') {
        send_mail(ADMIN_EMAIL, $subject, '<div style="font-family:Arial,sans-serif">' . nl2br(e($text)) . '</div>');
    }
}

// Confirmation email with the airline booking reference and e-ticket numbers
function send_booking_email($booking_id)
{
    $b = db_one("SELECT * FROM flight_bookings WHERE id = ?", [$booking_id]);
    $passengers = db_all("SELECT * FROM flight_passengers WHERE booking_id = ? ORDER BY id", [$booking_id]);
    $trip = json_decode($b['itinerary_json'], true);
    $link = SITE_URL . '/booking.php?ref=' . urlencode($b['booking_ref']) . '&email=' . urlencode($b['contact_email']);

    $html = '<div style="font-family:Arial,sans-serif;color:#0f1f33;max-width:600px">'
        . '<h1 style="font-size:22px">Your flight is booked</h1>'
        . '<p>Airline booking reference (PNR): <b style="font-size:20px;letter-spacing:2px">' . e($b['pnr']) . '</b><br>'
        . 'Our reference: ' . e($b['booking_ref']) . '</p>';
    foreach ($trip as $s) {
        $html .= '<p><b>' . e($s['from_city']) . ' (' . e($s['from']) . ') to ' . e($s['to_city']) . ' (' . e($s['to']) . ')</b><br>'
            . date('D j M Y, H:i', strtotime($s['depart'])) . ' → ' . date('H:i', strtotime($s['arrive']))
            . ($s['stops'] ? ' · ' . plural($s['stops'], 'stop') : ' · Direct') . '</p>';
    }
    $html .= '<table style="border-collapse:collapse" cellpadding="6"><tr><th align="left">Traveller</th><th align="left">E-ticket</th></tr>';
    foreach ($passengers as $p) {
        $html .= '<tr><td>' . e(strtoupper($p['family_name']) . ' / ' . $p['given_name']) . '</td><td>' . e($p['ticket_number'] ?: 'Being issued') . '</td></tr>';
    }
    $html .= '</table>'
        . '<p>Total paid: <b>' . naira($b['total_kobo']) . '</b></p>'
        . '<p><a href="' . e($link) . '">View or print your itinerary</a></p>'
        . '<p>Please arrive at the airport at least 1 hour before local flights and 3 hours before international flights. Bring a valid ID for local flights; for international trips, check the entry and visa rules for your destination before you travel.</p>'
        . '<p>' . e(SITE_NAME) . '</p></div>';

    send_mail($b['contact_email'], 'Booking confirmed: ' . $b['pnr'] . ' (' . $b['booking_ref'] . ')', $html);
}
