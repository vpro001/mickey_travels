<?php
require 'includes/init.php';
require 'includes/booking.php';
require 'includes/tours.php';

// A tour booking's status: tour-booking.php?ref=MTT-XXXXXXXX (this browser, or with ?email=)
$ref = strtoupper(get('ref'));
$b = preg_match('/^MTT-[A-Z0-9]{8}$/', $ref) ? db_one(
    "SELECT b.*, p.title, p.slug, p.destination, p.cover_image, d.start_date, d.end_date
     FROM tour_bookings b JOIN tour_packages p ON p.id = b.package_id JOIN tour_departures d ON d.id = b.departure_id
     WHERE b.booking_ref = ?", [$ref]) : null;
$mine = $b && (in_array($ref, isset($_SESSION['my_tours']) ? $_SESSION['my_tours'] : []) || (get('email') != '' && strcasecmp(get('email'), $b['email']) == 0));
if (!$mine) {
    flash('Enter your booking reference and email to see your booking.', 'error');
    redirect('manage-booking.php');
}
$_SESSION['my_tours'][] = $ref;
$balance = $b['total_kobo'] - $b['amount_paid_kobo'];

// Pay now (first payment again after a failure, or the balance after a deposit)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && post('action') == 'pay' && paystack_ready()) {
    csrf_check();
    if (in_array($b['status'], ['pending_payment', 'payment_failed', 'deposit_paid']) && $balance > 0) {
        $first = $b['amount_paid_kobo'] == 0;
        $due = $first && $b['payment_option'] == 'deposit'
            ? (int) round($b['total_kobo'] * db_one("SELECT deposit_pct FROM tour_packages WHERE id = ?", [$b['package_id']])['deposit_pct'] / 100)
            : $balance;
        $reference = $ref . '-' . bin2hex(random_bytes(3));
        db_insert('payments', ['reference' => $reference, 'booking_type' => 'tour', 'booking_id' => $b['id'], 'purpose' => $first ? ($b['payment_option'] == 'deposit' ? 'deposit' : 'full') : 'balance',
            'amount_kobo' => $due, 'currency' => 'NGN', 'email' => $b['email']]);
        db_query("UPDATE tour_bookings SET status = 'pending_payment' WHERE id = ? AND status = 'payment_failed'", [$b['id']]);
        $pay = paystack_initialize($b['email'], $due, $reference, SITE_URL . '/payment-callback.php', ['booking_ref' => $ref, 'booking_type' => 'tour']);
        if ($pay['ok']) {
            db_query("UPDATE payments SET authorization_url = ? WHERE reference = ?", [$pay['data']['authorization_url'], $reference]);
            header('Location: ' . $pay['data']['authorization_url']);
            exit;
        }
        flash("We couldn't open the payment page. Please try again in a moment.", 'error');
    }
    redirect('tour-booking.php?ref=' . urlencode($ref));
}

// Waiting for payment? Ask Paystack once more in case the return page and webhook both missed it.
if (in_array($b['status'], ['pending_payment', 'payment_failed', 'deposit_paid']) && paystack_ready()) {
    $last = db_one("SELECT reference FROM payments WHERE booking_type = 'tour' AND booking_id = ? AND status = 'initialized' AND created_at < NOW() - INTERVAL 30 SECOND ORDER BY id DESC LIMIT 1", [$b['id']]);
    if ($last) {
        $verify = paystack_verify($last['reference']);
        if ($verify['ok'] && in_array($verify['data']['status'], ['success', 'failed', 'abandoned'])) {
            confirm_payment($last['reference'], $verify['data']);
            redirect('tour-booking.php?ref=' . urlencode($ref));
        }
    }
}

$messages = [
    'pending_payment' => ['Waiting for payment', 'Your places are not reserved until the first payment is complete.', 'bg-warn-100 text-warn-700'],
    'payment_failed'  => ["Payment didn't go through", 'No money was taken. You can try again below.', 'bg-bad-100 text-bad-700'],
    'deposit_paid'    => ['Your place is reserved', 'Deposit received. Pay the balance any time before the trip.', 'bg-ok-100 text-ok-700'],
    'paid'            => ['Your holiday is paid for', 'Our team will contact you with flight and hotel details.', 'bg-ok-100 text-ok-700'],
    'confirmed'       => ['Confirmed', 'Everything is booked. We have sent you the details.', 'bg-ok-100 text-ok-700'],
    'cancellation_requested' => ['Cancellation requested', 'Our team is processing your request.', 'bg-warn-100 text-warn-700'],
    'cancelled'       => ['Cancelled', 'This booking has been cancelled.', 'bg-bad-100 text-bad-700'],
    'refunded'        => ['Refunded', 'This booking was cancelled and refunded.', 'bg-bad-100 text-bad-700'],
];
$m = $messages[$b['status']];
$can_pay = in_array($b['status'], ['pending_payment', 'payment_failed', 'deposit_paid']) && $balance > 0 && strtotime($b['start_date']) > time();

$title = 'Tour booking ' . $ref;
include 'includes/header.php';
?>
<section class="container-page py-10 sm:py-14">
  <div class="mx-auto max-w-3xl space-y-6">
    <div class="rounded-2xl p-6 <?= $m[2] ?>" role="status">
      <p class="font-display text-2xl font-bold"><?= e($m[0]) ?></p>
      <p class="mt-1"><?= e($m[1]) ?></p>
      <?php if ($can_pay && paystack_ready()): ?>
        <form method="post" class="mt-4"><?= csrf_field() ?><input type="hidden" name="action" value="pay">
          <button class="btn btn-dark px-6 py-3"><?= icon('card', 'size-5') ?> <?= $b['amount_paid_kobo'] > 0 ? 'Pay the balance of ' . naira($balance) : 'Pay now' ?></button>
        </form>
      <?php endif; ?>
    </div>

    <article class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-ink-900/5">
      <div class="relative h-48 bg-ink-800">
        <?php if ($b['cover_image']): ?><?= photo($b['cover_image'], $b['destination'], 'h-full w-full object-cover', false, [480, 800], '768px') ?><?php endif; ?>
        <div class="absolute inset-0 bg-gradient-to-t from-ink-950/90 to-transparent"></div>
        <div class="absolute bottom-0 p-6 text-paper-50">
          <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-saffron-300"><?= e($b['destination']) ?></p>
          <p class="font-display text-3xl font-extrabold"><?= e($b['title']) ?></p>
        </div>
      </div>
      <dl class="grid gap-4 p-6 sm:grid-cols-2">
        <div><dt class="font-mono text-[11px] uppercase tracking-[0.16em] text-ink-600">Reference</dt><dd class="mt-1 font-mono text-lg font-semibold"><?= e($ref) ?></dd></div>
        <div><dt class="font-mono text-[11px] uppercase tracking-[0.16em] text-ink-600">Dates</dt><dd class="mt-1 font-semibold num"><?= nice_date($b['start_date']) ?> – <?= nice_date($b['end_date']) ?></dd></div>
        <div><dt class="font-mono text-[11px] uppercase tracking-[0.16em] text-ink-600">Lead traveller</dt><dd class="mt-1"><?= e($b['lead_name']) ?> · <?= plural($b['travellers'], 'traveller') ?></dd></div>
        <div><dt class="font-mono text-[11px] uppercase tracking-[0.16em] text-ink-600">Paid</dt><dd class="mt-1 num"><b><?= naira($b['amount_paid_kobo']) ?></b> of <?= naira($b['total_kobo']) ?></dd></div>
      </dl>
    </article>
    <p class="text-sm text-ink-700">Questions about your trip? <a class="link" href="contact.php">Contact us</a> and quote <?= e($ref) ?>.</p>
  </div>
</section>
<?php include 'includes/footer.php'; ?>
