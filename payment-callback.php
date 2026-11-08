<?php
require 'includes/init.php';
require 'includes/booking.php';

// Paystack sends the customer back here after paying: payment-callback.php?reference=...
// We never trust the browser: we ask Paystack directly what happened, then show the booking.
// (The webhook in webhooks/paystack.php does the same thing in the background, in case the
// customer closes the page before getting back here.)

$reference = get('reference') ?: get('trxref');
if (!preg_match('/^[A-Za-z0-9.=-]{6,100}$/', $reference)) {
    redirect('index.php');
}

$payment = db_one("SELECT * FROM payments WHERE reference = ?", [$reference]);
if (!$payment) {
    redirect('index.php');
}
// The booking reference is the start of the payment reference (MTF-XXXXXXXX-abc123)
$booking_ref = substr($reference, 0, 12);

session_write_close();   // issuing a ticket can take a while; don't block the visitor's other tabs

$verify = paystack_verify($reference);
if ($verify['ok']) {
    confirm_payment($reference, $verify['data']);
} else {
    // Paystack couldn't be reached. The webhook will still confirm the payment; the booking page explains.
    write_log('payments', 'Verify failed on callback', ['reference' => $reference, 'message' => $verify['message']]);
}

session_start();
if ($payment['booking_type'] == 'hotel') {
    $_SESSION['my_hotels'][] = $booking_ref;
    redirect('hotel-booking.php?ref=' . urlencode($booking_ref));
}
if ($payment['booking_type'] == 'tour') {
    $_SESSION['my_tours'][] = $booking_ref;
    redirect('tour-booking.php?ref=' . urlencode($booking_ref));
}
$_SESSION['my_bookings'][] = $booking_ref;
redirect('booking.php?ref=' . urlencode($booking_ref));
