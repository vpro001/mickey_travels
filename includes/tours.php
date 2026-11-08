<?php
// Tour bookings: deposits, seats and emails.

require_once __DIR__ . '/mail.php';

// Called inside confirm_payment()'s transaction once a tour payment is verified.
function tour_paid($booking_id, $payment)
{
    $b = db_one("SELECT * FROM tour_bookings WHERE id = ? FOR UPDATE", [$booking_id]);
    $first_payment = $b['amount_paid_kobo'] == 0;
    $paid = $b['amount_paid_kobo'] + $payment['amount_kobo'];
    $status = $paid >= $b['total_kobo'] ? 'paid' : 'deposit_paid';

    db_query("UPDATE tour_bookings SET amount_paid_kobo = ?, status = ? WHERE id = ?", [$paid, $status, $booking_id]);

    // Reserve the seats on the first payment, but never more seats than the departure has
    if ($first_payment) {
        $stmt = db_query("UPDATE tour_departures SET seats_booked = seats_booked + ? WHERE id = ? AND seats_booked + ? <= seats_total",
            [(int) $b['travellers'], $b['departure_id'], (int) $b['travellers']]);
        if (mysqli_stmt_affected_rows($stmt) != 1) {
            admin_alert('ACTION NEEDED: tour departure full', 'Tour booking ' . $b['booking_ref'] . ' has paid but the departure has no seats left. Contact the customer.');
        }
    }
    db_insert('audit_log', ['action' => 'status:' . $status, 'entity_type' => 'tour_booking', 'entity_id' => $booking_id,
        'details' => json_encode(['paid_kobo' => $payment['amount_kobo']]), 'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'cli']);
}

function send_tour_email($booking_id)
{
    $b = db_one("SELECT b.*, p.title, d.start_date, d.end_date FROM tour_bookings b JOIN tour_packages p ON p.id = b.package_id JOIN tour_departures d ON d.id = b.departure_id WHERE b.id = ?", [$booking_id]);
    $balance = $b['total_kobo'] - $b['amount_paid_kobo'];
    $link = SITE_URL . '/tour-booking.php?ref=' . urlencode($b['booking_ref']) . '&email=' . urlencode($b['email']);
    $html = '<div style="font-family:Arial,sans-serif;color:#0f1f33;max-width:600px">'
        . '<h1 style="font-size:22px">' . ($balance > 0 ? 'Your place is reserved' : 'Your holiday is paid for') . '</h1>'
        . '<p><b>' . e($b['title']) . '</b><br>' . nice_date($b['start_date']) . ' to ' . nice_date($b['end_date']) . ' · ' . plural($b['travellers'], 'traveller') . '</p>'
        . '<p>Reference: <b>' . e($b['booking_ref']) . '</b><br>Paid so far: ' . naira($b['amount_paid_kobo']) . ' of ' . naira($b['total_kobo']) . '</p>'
        . ($balance > 0 ? '<p>Balance to pay: <b>' . naira($balance) . '</b>. We will remind you before it is due.</p>' : '')
        . '<p>Our team will contact you with flight and hotel details.</p>'
        . '<p><a href="' . e($link) . '">View your booking</a></p><p>' . e(SITE_NAME) . '</p></div>';
    send_mail($b['email'], 'Tour booking ' . $b['booking_ref'] . ': ' . $b['title'], $html);
}
