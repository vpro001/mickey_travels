<?php
require 'includes/init.php';
require 'includes/booking.php';

// A flight booking's status and itinerary: booking.php?ref=MTF-XXXXXXXX
// Visible to the browser that made the booking, or with ?email= matching the booking's email
// (that's the link in the confirmation email).

$ref = strtoupper(get('ref'));
$booking = preg_match('/^MTF-[A-Z0-9]{8}$/', $ref) ? db_one("SELECT * FROM flight_bookings WHERE booking_ref = ?", [$ref]) : null;
$mine = $booking && (
    in_array($ref, isset($_SESSION['my_bookings']) ? $_SESSION['my_bookings'] : [])
    || (get('email') != '' && strcasecmp(get('email'), $booking['contact_email']) == 0)
);
if (!$mine) {
    // Same answer whether the booking exists or not, so references can't be guessed
    flash('Enter your booking reference and email to see your booking.', 'error');
    redirect('manage-booking.php');
}
$_SESSION['my_bookings'][] = $ref;

// "Pay now" again after a failed or abandoned payment (only while the airline still holds the price)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && post('action') == 'pay') {
    csrf_check();
    if (in_array($booking['status'], ['pending_payment', 'payment_failed']) && strtotime($booking['offer_expires_at']) > time() && paystack_ready()) {
        $reference = $booking['booking_ref'] . '-' . bin2hex(random_bytes(3));
        db_insert('payments', [
            'reference' => $reference, 'provider' => 'paystack', 'booking_type' => 'flight', 'booking_id' => $booking['id'],
            'amount_kobo' => $booking['total_kobo'], 'currency' => 'NGN', 'email' => $booking['contact_email'], 'status' => 'initialized',
        ]);
        db_query("UPDATE flight_bookings SET status = 'pending_payment' WHERE id = ? AND status = 'payment_failed'", [$booking['id']]);
        $pay = paystack_initialize($booking['contact_email'], (int) $booking['total_kobo'], $reference, SITE_URL . '/payment-callback.php', ['booking_ref' => $ref, 'booking_type' => 'flight']);
        if ($pay['ok']) {
            db_query("UPDATE payments SET authorization_url = ? WHERE reference = ?", [$pay['data']['authorization_url'], $reference]);
            header('Location: ' . $pay['data']['authorization_url']);
            exit;
        }
        flash("We couldn't open the payment page. Please try again in a moment.", 'error');
    }
    redirect('booking.php?ref=' . urlencode($ref));
}

// Still waiting for payment? Ask Paystack once more, in case both the return page and the
// webhook missed it (for example if Paystack couldn't be reached at the time).
if (in_array($booking['status'], ['pending_payment', 'payment_failed']) && paystack_ready()) {
    $last = db_one("SELECT reference FROM payments WHERE booking_type = 'flight' AND booking_id = ? AND status = 'initialized' AND created_at < NOW() - INTERVAL 30 SECOND ORDER BY id DESC LIMIT 1", [$booking['id']]);
    if ($last) {
        $verify = paystack_verify($last['reference']);
        if ($verify['ok'] && in_array($verify['data']['status'], ['success', 'failed', 'abandoned'])) {
            session_write_close();
            confirm_payment($last['reference'], $verify['data']);
            session_start();
            $booking = db_one("SELECT * FROM flight_bookings WHERE id = ?", [$booking['id']]);
        }
    }
}

// Mark unpaid bookings as expired once the airline's price hold has passed
if ($booking['status'] == 'pending_payment' && strtotime($booking['offer_expires_at']) < time()) {
    flight_status($booking['id'], 'expired', 'Price hold ended before payment');
    $booking = db_one("SELECT * FROM flight_bookings WHERE id = ?", [$booking['id']]);
}

$passengers = db_all("SELECT * FROM flight_passengers WHERE booking_id = ? ORDER BY id", [$booking['id']]);
$trip = json_decode($booking['itinerary_json'], true);
$offer = json_decode($booking['offer_snapshot'], true);
$status = $booking['status'];
$can_pay = in_array($status, ['pending_payment', 'payment_failed']) && strtotime($booking['offer_expires_at']) > time();

// What to tell the customer for each status
$messages = [
    'pending_payment' => ['Waiting for payment', 'Your seats are not booked until payment is complete.', 'warn'],
    'payment_failed'  => ["Payment didn't go through", 'No money was taken for this booking. You can try again below.', 'bad'],
    'expired'         => ['This booking has expired', "The airline's price hold ended before payment was made. Please search again for today's fares.", 'bad'],
    'paid'            => ['Payment received', "We're asking the airline to issue your ticket. This page will update by itself.", 'warn'],
    'ticketing'       => ['Issuing your ticket', "Payment received. The airline is issuing your ticket now. This page will update by itself.", 'warn'],
    'confirmed'       => ["You're booked", 'Your tickets are issued. We have emailed your itinerary to ' . $booking['contact_email'] . '.', 'ok'],
    'needs_attention' => ['Payment received. Our team is finishing your booking', "The airline needed a moment longer than usual. Your money is safe: one of our staff will contact you to complete the booking. Quote reference $ref.", 'warn'],
    'cancellation_requested' => ['Cancellation requested', 'Our team is processing your request.', 'warn'],
    'cancelled'       => ['Booking cancelled', 'This booking has been cancelled.', 'bad'],
    'refunded'        => ['Refunded', 'This booking was cancelled and refunded.', 'bad'],
];
$m = $messages[$status];
$tone = ['ok' => 'bg-ok-100 text-ok-700', 'warn' => 'bg-warn-100 text-warn-700', 'bad' => 'bg-bad-100 text-bad-700'][$m[2]];
$working = in_array($status, ['paid', 'ticketing']);

$title = 'Booking ' . $ref;
include 'includes/header.php';
?>
<?php if ($working): ?><meta http-equiv="refresh" content="8"><?php endif; ?>

<section class="bg-ink-950 text-paper-50 print:hidden">
  <div class="container-page py-6">
    <ol class="flex flex-wrap items-center gap-x-3 gap-y-2 font-mono text-xs font-semibold uppercase tracking-[0.15em]">
      <li class="flex items-center gap-2 text-ink-300"><span class="flex size-6 items-center justify-center rounded-full bg-ok-700 text-white"><?= icon('check', 'size-3.5') ?></span> Flight</li>
      <li class="h-px w-6 bg-ink-600"></li>
      <li class="flex items-center gap-2 text-ink-300"><span class="flex size-6 items-center justify-center rounded-full bg-ok-700 text-white"><?= icon('check', 'size-3.5') ?></span> Travellers</li>
      <li class="h-px w-6 bg-ink-600"></li>
      <?php $paid = !in_array($status, ['pending_payment', 'payment_failed', 'expired']); ?>
      <li class="flex items-center gap-2 <?= $paid ? 'text-ink-300' : 'text-saffron-300' ?>"><span class="flex size-6 items-center justify-center rounded-full <?= $paid ? 'bg-ok-700 text-white' : 'bg-saffron-400 text-ink-950' ?>"><?= $paid ? icon('check', 'size-3.5') : '3' ?></span> Payment</li>
      <li class="h-px w-6 bg-ink-600"></li>
      <li class="flex items-center gap-2 <?= $status == 'confirmed' ? 'text-ok-100' : ($paid ? 'text-saffron-300' : 'text-ink-300') ?>"><span class="flex size-6 items-center justify-center rounded-full <?= $status == 'confirmed' ? 'bg-ok-700 text-white' : 'ring-1 ring-ink-500' ?>"><?= $status == 'confirmed' ? icon('check', 'size-3.5') : '4' ?></span> Ticket</li>
    </ol>
  </div>
</section>

<section class="container-page py-8 sm:py-12">
  <div class="mx-auto max-w-4xl">
    <div class="rounded-2xl p-5 sm:p-6 <?= $tone ?> print:hidden" role="status">
      <p class="font-display text-2xl font-bold"><?= e($m[0]) ?></p>
      <p class="mt-1"><?= e($m[1]) ?></p>
      <?php if ($can_pay): ?>
        <form method="post" class="mt-4">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="pay">
          <button class="btn btn-dark px-6 py-3"><?= icon('card', 'size-5') ?> Pay <?= naira($booking['total_kobo']) ?> now</button>
        </form>
      <?php elseif ($status == 'expired'): ?>
        <a class="btn btn-dark mt-4 px-6 py-3" href="flights.php">Search again</a>
      <?php elseif ($working): ?>
        <p class="mt-3 flex items-center gap-2 text-sm"><span class="size-3 animate-ping rounded-full bg-current"></span> Checking again in a few seconds…</p>
      <?php endif; ?>
    </div>

    <!-- The itinerary, printable -->
    <article class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-ink-900/5 print:shadow-none print:ring-0">
      <header class="flex flex-wrap items-start justify-between gap-6 bg-ink-950 p-6 text-paper-50 sm:p-8 print:bg-white print:text-ink-950">
        <div>
          <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-saffron-300 print:text-ink-600">Itinerary · <?= e(SITE_NAME) ?></p>
          <p class="mt-2 font-display text-3xl font-extrabold"><?= e($trip[0]['from_city']) ?> → <?= e($trip[0]['to_city']) ?></p>
          <p class="mt-1 text-sm text-ink-300 print:text-ink-700"><?= e($offer['owner']['name']) ?> · <?= plural(count($passengers), 'traveller') ?></p>
        </div>
        <div class="grid grid-cols-2 gap-6 text-right">
          <div>
            <p class="font-mono text-[10px] uppercase tracking-[0.2em] text-ink-300 print:text-ink-600">Airline reference</p>
            <p class="mt-1 font-mono text-2xl font-semibold tracking-[0.2em] <?= $booking['pnr'] ? 'text-saffron-300 print:text-ink-950' : 'text-ink-500' ?>"><?= e($booking['pnr'] ?: '——————') ?></p>
          </div>
          <div>
            <p class="font-mono text-[10px] uppercase tracking-[0.2em] text-ink-300 print:text-ink-600">Our reference</p>
            <p class="mt-1 font-mono text-lg font-semibold tracking-wider"><?= e($ref) ?></p>
          </div>
        </div>
      </header>

      <div class="divide-y divide-dashed divide-paper-300">
        <?php foreach ($trip as $s): ?>
          <div class="grid gap-4 p-6 sm:grid-cols-[1fr_auto_1fr] sm:items-center sm:p-8">
            <div>
              <p class="font-mono text-[11px] uppercase tracking-[0.16em] text-ink-600"><?= date('D j M Y', strtotime($s['depart'])) ?></p>
              <p class="mt-1 font-display text-4xl font-bold num"><?= substr($s['depart'], 11, 5) ?></p>
              <p class="font-semibold"><?= e($s['from_city']) ?> <span class="font-mono text-ink-600">(<?= e($s['from']) ?>)</span></p>
            </div>
            <div class="text-center text-sm text-ink-600">
              <?= icon('plane', 'mx-auto size-5 text-saffron-600') ?>
              <p class="mt-1 num"><?= minutes_text($s['minutes']) ?></p>
              <p><?= $s['stops'] ? plural($s['stops'], 'stop') . ' · ' . e(implode(', ', $s['via'])) : 'Direct' ?></p>
            </div>
            <div class="sm:text-right">
              <p class="font-mono text-[11px] uppercase tracking-[0.16em] text-ink-600"><?= date('D j M Y', strtotime($s['arrive'])) ?></p>
              <p class="mt-1 font-display text-4xl font-bold num"><?= substr($s['arrive'], 11, 5) ?></p>
              <p class="font-semibold"><?= e($s['to_city']) ?> <span class="font-mono text-ink-600">(<?= e($s['to']) ?>)</span></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="border-t border-paper-200 p-6 sm:p-8">
        <p class="font-mono text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-600">Travellers</p>
        <table class="mt-3 w-full text-left text-sm">
          <thead class="text-ink-600"><tr><th class="py-2 font-medium">Name</th><th class="py-2 font-medium">Type</th><th class="py-2 text-right font-medium">E-ticket number</th></tr></thead>
          <tbody class="divide-y divide-paper-200">
            <?php foreach ($passengers as $p): ?>
              <tr>
                <td class="py-2.5 font-semibold"><?= e(strtoupper($p['family_name'])) ?> / <?= e(strtoupper($p['given_name'])) ?> <?= e(strtoupper($p['title'])) ?></td>
                <td class="py-2.5 text-ink-700"><?= ['adult' => 'Adult', 'child' => 'Child', 'infant_without_seat' => 'Infant'][$p['type']] ?></td>
                <td class="py-2.5 text-right font-mono num"><?= e($p['ticket_number'] ?: '—') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="flex flex-wrap items-end justify-between gap-6 border-t border-paper-200 bg-paper-50 p-6 sm:p-8">
        <dl class="space-y-1 text-sm">
          <div class="flex gap-6"><dt class="w-40 text-ink-700">Airline fare and taxes</dt><dd class="num"><?= naira($booking['base_kobo']) ?></dd></div>
          <div class="flex gap-6"><dt class="w-40 text-ink-700">Our service fee</dt><dd class="num"><?= naira($booking['markup_kobo']) ?></dd></div>
          <div class="flex gap-6 text-base font-bold"><dt class="w-40"><?= $paid ? 'Total paid' : 'Total' ?></dt><dd class="num"><?= naira($booking['total_kobo']) ?></dd></div>
        </dl>
        <div class="flex gap-3 print:hidden">
          <?php if ($status == 'confirmed'): ?>
            <button onclick="window.print()" class="btn btn-dark px-5 py-3"><?= icon('document', 'size-5') ?> Print itinerary</button>
          <?php endif; ?>
          <a href="contact.php" class="btn btn-ghost px-5 py-3">Need help?</a>
        </div>
      </div>
    </article>
    <p class="mt-4 text-xs text-ink-600">Arrive at the airport at least 1 hour before local flights and 3 hours before international flights. Bring a valid ID (passport, national ID card or driver's licence) for local flights; for international trips, check the passport, visa and health rules for every country on your trip.</p>
  </div>
</section>
<?php include 'includes/footer.php'; ?>
