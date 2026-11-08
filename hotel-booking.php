<?php
require 'includes/init.php';
require 'includes/booking.php';
require 'includes/stays.php';

// A hotel booking's status: hotel-booking.php?ref=MTH-XXXXXXXX (this browser, or with ?email=)
$ref = strtoupper(get('ref'));
$b = preg_match('/^MTH-[A-Z0-9]{8}$/', $ref) ? db_one("SELECT * FROM hotel_bookings WHERE booking_ref = ?", [$ref]) : null;
$mine = $b && (in_array($ref, isset($_SESSION['my_hotels']) ? $_SESSION['my_hotels'] : []) || (get('email') != '' && strcasecmp(get('email'), $b['contact_email']) == 0));
if (!$mine) {
    flash('Enter your booking reference and email to see your booking.', 'error');
    redirect('manage-booking.php');
}
$_SESSION['my_hotels'][] = $ref;

// Pay again after a failed payment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && post('action') == 'pay' && paystack_ready()) {
    csrf_check();
    if (in_array($b['status'], ['pending_payment', 'payment_failed'])) {
        $reference = $ref . '-' . bin2hex(random_bytes(3));
        db_insert('payments', ['reference' => $reference, 'booking_type' => 'hotel', 'booking_id' => $b['id'], 'amount_kobo' => $b['total_kobo'], 'currency' => 'NGN', 'email' => $b['contact_email']]);
        db_query("UPDATE hotel_bookings SET status = 'pending_payment' WHERE id = ? AND status = 'payment_failed'", [$b['id']]);
        $pay = paystack_initialize($b['contact_email'], (int) $b['total_kobo'], $reference, SITE_URL . '/payment-callback.php', ['booking_ref' => $ref, 'booking_type' => 'hotel']);
        if ($pay['ok']) {
            db_query("UPDATE payments SET authorization_url = ? WHERE reference = ?", [$pay['data']['authorization_url'], $reference]);
            header('Location: ' . $pay['data']['authorization_url']);
            exit;
        }
        flash("We couldn't open the payment page. Please try again in a moment.", 'error');
    }
    redirect('hotel-booking.php?ref=' . urlencode($ref));
}

// Still waiting? Ask Paystack once more in case the return page and webhook both missed it.
if (in_array($b['status'], ['pending_payment', 'payment_failed']) && paystack_ready()) {
    $last = db_one("SELECT reference FROM payments WHERE booking_type = 'hotel' AND booking_id = ? AND status = 'initialized' AND created_at < NOW() - INTERVAL 30 SECOND ORDER BY id DESC LIMIT 1", [$b['id']]);
    if ($last) {
        $verify = paystack_verify($last['reference']);
        if ($verify['ok'] && in_array($verify['data']['status'], ['success', 'failed', 'abandoned'])) {
            confirm_payment($last['reference'], $verify['data']);
            redirect('hotel-booking.php?ref=' . urlencode($ref));
        }
    }
}

$hotel = json_decode($b['accommodation_json'], true);
$guests = json_decode($b['guests_json'], true);
$messages = [
    'pending_payment' => ['Waiting for payment', 'Your room is not booked until payment is complete.', 'bg-warn-100 text-warn-700'],
    'payment_failed'  => ["Payment didn't go through", 'No money was taken. You can try again below.', 'bg-bad-100 text-bad-700'],
    'expired'         => ['This booking has expired', 'Please search again for today\'s prices.', 'bg-bad-100 text-bad-700'],
    'paid'            => ['Payment received', 'We are booking your room with the hotel now. This page updates by itself.', 'bg-warn-100 text-warn-700'],
    'booking'         => ['Booking your room', 'Payment received. The hotel is confirming your room now.', 'bg-warn-100 text-warn-700'],
    'confirmed'       => ["You're booked", 'The hotel has confirmed your room. We have emailed the details to ' . $b['contact_email'] . '.', 'bg-ok-100 text-ok-700'],
    'needs_attention' => ['Payment received. Our team is finishing your booking', "Your money is safe: one of our staff will contact you to complete it. Quote reference $ref.", 'bg-warn-100 text-warn-700'],
    'cancellation_requested' => ['Cancellation requested', 'Our team is processing your request.', 'bg-warn-100 text-warn-700'],
    'cancelled'       => ['Booking cancelled', 'This booking has been cancelled.', 'bg-bad-100 text-bad-700'],
    'refunded'        => ['Refunded', 'This booking was cancelled and refunded.', 'bg-bad-100 text-bad-700'],
];
$m = $messages[$b['status']];
$working = in_array($b['status'], ['paid', 'booking']);
$nights = (int) round((strtotime($b['check_out_date']) - strtotime($b['check_in_date'])) / 86400);

$title = 'Hotel booking ' . $ref;
include 'includes/header.php';
?>
<?php if ($working): ?><meta http-equiv="refresh" content="8"><?php endif; ?>
<section class="container-page py-10 sm:py-14">
  <div class="mx-auto max-w-3xl space-y-6">
    <div class="rounded-2xl p-6 <?= $m[2] ?> print:hidden" role="status">
      <p class="font-display text-2xl font-bold"><?= e($m[0]) ?></p>
      <p class="mt-1"><?= e($m[1]) ?></p>
      <?php if (in_array($b['status'], ['pending_payment', 'payment_failed']) && paystack_ready()): ?>
        <form method="post" class="mt-4"><?= csrf_field() ?><input type="hidden" name="action" value="pay"><button class="btn btn-dark px-6 py-3"><?= icon('card', 'size-5') ?> Pay <?= naira($b['total_kobo']) ?> now</button></form>
      <?php endif; ?>
    </div>

    <article class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-ink-900/5">
      <div class="relative h-52 bg-ink-800">
        <?php if ($hotel['photo']): ?><img src="<?= e($hotel['photo']) ?>" alt="" class="h-full w-full object-cover"><?php endif; ?>
        <div class="absolute inset-0 bg-gradient-to-t from-ink-950/90 to-transparent"></div>
        <div class="absolute bottom-0 p-6 text-paper-50">
          <p class="text-saffron-300"><?= str_repeat('★', (int) $hotel['rating']) ?></p>
          <p class="font-display text-3xl font-extrabold"><?= e($hotel['name']) ?></p>
          <p class="text-sm text-ink-200"><?= e($hotel['address']) ?></p>
        </div>
      </div>
      <dl class="grid gap-5 p-6 sm:grid-cols-3">
        <div><dt class="font-mono text-[11px] uppercase tracking-[0.16em] text-ink-600">Hotel confirmation</dt><dd class="mt-1 font-mono text-xl font-bold tracking-wider"><?= e($b['hotel_reference'] ?: '—') ?></dd></div>
        <div><dt class="font-mono text-[11px] uppercase tracking-[0.16em] text-ink-600">Our reference</dt><dd class="mt-1 font-mono text-lg font-semibold"><?= e($ref) ?></dd></div>
        <div><dt class="font-mono text-[11px] uppercase tracking-[0.16em] text-ink-600">Paid</dt><dd class="mt-1 font-semibold num"><?= in_array($b['status'], ['pending_payment', 'payment_failed', 'expired']) ? '—' : naira($b['total_kobo']) ?></dd></div>
        <div><dt class="font-mono text-[11px] uppercase tracking-[0.16em] text-ink-600">Check-in</dt><dd class="mt-1 num"><?= date('D j M Y', strtotime($b['check_in_date'])) ?></dd></div>
        <div><dt class="font-mono text-[11px] uppercase tracking-[0.16em] text-ink-600">Check-out</dt><dd class="mt-1 num"><?= date('D j M Y', strtotime($b['check_out_date'])) ?></dd></div>
        <div><dt class="font-mono text-[11px] uppercase tracking-[0.16em] text-ink-600">Stay</dt><dd class="mt-1"><?= plural($nights, 'night') ?>, <?= plural($b['rooms'], 'room') ?></dd></div>
        <div class="sm:col-span-3"><dt class="font-mono text-[11px] uppercase tracking-[0.16em] text-ink-600">Room</dt><dd class="mt-1"><?= e($hotel['room']) ?> · <?= e(board_name($hotel['board'])) ?> · <?= e($hotel['cancellation']) ?></dd></div>
        <div class="sm:col-span-3"><dt class="font-mono text-[11px] uppercase tracking-[0.16em] text-ink-600">Guests</dt>
          <dd class="mt-1"><?= e(implode(', ', array_map(function ($g) { return $g['given_name'] . ' ' . strtoupper($g['family_name']); }, $guests['names']))) ?></dd></div>
      </dl>
      <?php if ($b['status'] == 'confirmed'): ?>
        <div class="border-t border-paper-200 px-6 py-4 print:hidden"><button onclick="window.print()" class="btn btn-dark"><?= icon('document', 'size-5') ?> Print confirmation</button></div>
      <?php endif; ?>
    </article>
    <p class="text-sm text-ink-700 print:hidden">Questions about your stay? <a class="link" href="contact.php">Contact us</a> and quote <?= e($ref) ?>.</p>
  </div>
</section>
<?php include 'includes/footer.php'; ?>
