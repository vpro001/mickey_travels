<?php
require 'includes/init.php';
require 'includes/account.php';
require 'includes/booking.php';
require 'includes/stays.php';
require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_check();
    $action = post('action');

    if ($action == 'resend') {
        send_verification_email($user);
        flash('We have sent the confirmation email again.');
    }

    // Cancellation requests go to our staff. (We don't cancel with the airline or hotel automatically:
    // fare rules and refunds need a person to check them.)
    if ($action == 'cancel') {
        $type = post('type');
        $id = (int) post('id');
        $mine = my_bookings($user);
        $list = ['flight' => 'flights', 'hotel' => 'hotels', 'tour' => 'tours'];
        $booking = null;
        if (isset($list[$type])) {
            foreach ($mine[$list[$type]] as $b) {
                if ($b['id'] == $id) $booking = $b;
            }
        }
        $done = false;
        if ($booking && $type == 'flight') $done = flight_status($id, 'cancellation_requested', 'Customer asked to cancel: ' . post('reason'));
        if ($booking && $type == 'hotel') $done = hotel_status($id, 'cancellation_requested', 'Customer asked to cancel: ' . post('reason'));
        if ($booking && $type == 'tour') {
            $stmt = db_query("UPDATE tour_bookings SET status = 'cancellation_requested' WHERE id = ? AND status IN ('deposit_paid', 'paid', 'confirmed')", [$id]);
            $done = mysqli_stmt_affected_rows($stmt) == 1;
        }
        if ($done) {
            admin_alert('Cancellation request: ' . $booking['booking_ref'], $user['full_name'] . ' (' . $user['email'] . ") asked to cancel booking " . $booking['booking_ref'] . ".\n\nReason: " . (post('reason') ?: 'not given'));
            flash('We have your cancellation request. Our team will contact you about the airline or hotel rules and any refund.');
        } else {
            flash('This booking can\'t be cancelled online. Please contact us.', 'error');
        }
    }
    redirect('account.php');
}

$mine = my_bookings($user);
// Let the links below open each booking's page in this browser
foreach ($mine['flights'] as $b) $_SESSION['my_bookings'][] = $b['booking_ref'];
foreach ($mine['hotels'] as $b) $_SESSION['my_hotels'][] = $b['booking_ref'];
foreach ($mine['tours'] as $b) $_SESSION['my_tours'][] = $b['booking_ref'];
foreach ($mine['visas'] as $v) $_SESSION['visa_access'][] = $v['reference'];

$status_names = [
    'pending_payment' => 'Waiting for payment', 'payment_failed' => 'Payment failed', 'paid' => 'Paid', 'ticketing' => 'Issuing ticket', 'booking' => 'Booking',
    'confirmed' => 'Confirmed', 'needs_attention' => 'Our team is on it', 'cancellation_requested' => 'Cancellation requested', 'cancelled' => 'Cancelled',
    'refunded' => 'Refunded', 'deposit_paid' => 'Deposit paid', 'expired' => 'Expired',
    'submitted' => 'Received', 'under_review' => 'Being reviewed', 'documents_requested' => 'Documents needed', 'processing' => 'At the embassy',
    'approved' => 'Approved', 'refused' => 'Refused', 'completed' => 'Completed',
];
function pill($status, $names)
{
    $good = ['confirmed', 'paid', 'deposit_paid', 'approved', 'completed'];
    $bad = ['payment_failed', 'cancelled', 'refunded', 'refused', 'expired'];
    $class = in_array($status, $good) ? 'bg-ok-100 text-ok-700' : (in_array($status, $bad) ? 'bg-bad-100 text-bad-700' : 'bg-warn-100 text-warn-700');
    return '<span class="badge ' . $class . '">' . e(isset($names[$status]) ? $names[$status] : $status) . '</span>';
}
function cancel_form($type, $id)
{
    return '<details class="mt-3"><summary class="text-sm link">Request cancellation</summary>'
        . '<form method="post" class="mt-3 flex flex-col gap-2 sm:flex-row" onsubmit="return confirm(\'Send a cancellation request to our team?\')">' . csrf_field()
        . '<input type="hidden" name="action" value="cancel"><input type="hidden" name="type" value="' . $type . '"><input type="hidden" name="id" value="' . (int) $id . '">'
        . '<input name="reason" placeholder="Reason (optional)" class="field mt-0 flex-1"><button class="btn btn-ghost">Send request</button></form>'
        . '<p class="mt-1 text-xs text-ink-600">Airline and hotel rules decide what can be refunded. We\'ll check and contact you.</p></details>';
}

$title = 'My account';
$page = 'account';
include 'includes/header.php';
$empty = !$mine['flights'] && !$mine['hotels'] && !$mine['tours'] && !$mine['visas'];
?>
<section class="bg-ink-950 text-paper-50">
  <div class="container-page flex flex-wrap items-end justify-between gap-4 py-10 sm:py-14">
    <div>
      <p class="eyebrow text-saffron-300">My account</p>
      <h1 class="mt-2 text-4xl font-extrabold tracking-[-0.03em] sm:text-5xl">Hello, <?= e(explode(' ', $user['full_name'])[0]) ?></h1>
      <p class="mt-1 text-ink-300"><?= e($user['email']) ?></p>
    </div>
    <form method="post" action="logout.php"><?= csrf_field() ?><button class="btn ring-1 ring-inset ring-white/20 hover:bg-white/10">Sign out</button></form>
  </div>
</section>

<section class="container-page py-10 sm:py-14 space-y-10">
  <?php if ($user['email_verified_at'] === null): ?>
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-warn-100 p-5 text-warn-700">
      <p><b>Please confirm your email.</b> Until you do, we only show bookings you made while signed in.</p>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="resend"><button class="btn btn-dark">Send the email again</button></form>
    </div>
  <?php endif; ?>

  <?php if ($empty): ?>
    <div class="rounded-2xl bg-white p-10 text-center ring-1 ring-paper-200">
      <p class="font-display text-2xl font-bold">Nothing booked yet</p>
      <p class="mt-2 text-ink-700">Your flights, hotels, holidays and visa applications will show here.</p>
      <div class="mt-5 flex flex-wrap justify-center gap-3"><a class="btn btn-primary" href="flights.php">Find a flight</a><a class="btn btn-ghost" href="visa.php">Visa services</a></div>
    </div>
  <?php endif; ?>

  <?php if ($mine['flights']): ?>
    <div>
      <h2 class="text-2xl font-extrabold">Flights</h2>
      <div class="mt-4 grid gap-4 md:grid-cols-2">
        <?php foreach ($mine['flights'] as $b): $trip = json_decode($b['itinerary_json'], true); ?>
          <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-ink-900/5">
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="font-display text-xl font-bold"><?= e($trip[0]['from_city']) ?> → <?= e($trip[0]['to_city']) ?></p>
                <p class="text-sm text-ink-600 num"><?= date('D j M Y', strtotime($trip[0]['depart'])) ?><?= count($trip) > 1 ? ' · ' . plural(count($trip), 'flight') : '' ?></p>
              </div>
              <?= pill($b['status'], $status_names) ?>
            </div>
            <p class="mt-3 text-sm"><span class="font-mono"><?= e($b['booking_ref']) ?></span><?= $b['pnr'] ? ' · PNR <b class="font-mono">' . e($b['pnr']) . '</b>' : '' ?> · <?= naira($b['total_kobo']) ?></p>
            <a class="btn btn-ghost mt-4" href="booking.php?ref=<?= e($b['booking_ref']) ?>">View itinerary</a>
            <?= $b['status'] == 'confirmed' ? cancel_form('flight', $b['id']) : '' ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($mine['hotels']): ?>
    <div>
      <h2 class="text-2xl font-extrabold">Hotels</h2>
      <div class="mt-4 grid gap-4 md:grid-cols-2">
        <?php foreach ($mine['hotels'] as $b): $h = json_decode($b['accommodation_json'], true); ?>
          <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-ink-900/5">
            <div class="flex items-start justify-between gap-3">
              <div><p class="font-display text-xl font-bold"><?= e($h['name']) ?></p><p class="text-sm text-ink-600 num"><?= nice_date($b['check_in_date']) ?> – <?= nice_date($b['check_out_date']) ?></p></div>
              <?= pill($b['status'], $status_names) ?>
            </div>
            <p class="mt-3 text-sm"><span class="font-mono"><?= e($b['booking_ref']) ?></span><?= $b['hotel_reference'] ? ' · Hotel ref <b class="font-mono">' . e($b['hotel_reference']) . '</b>' : '' ?> · <?= naira($b['total_kobo']) ?></p>
            <a class="btn btn-ghost mt-4" href="hotel-booking.php?ref=<?= e($b['booking_ref']) ?>">View booking</a>
            <?= $b['status'] == 'confirmed' ? cancel_form('hotel', $b['id']) : '' ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($mine['tours']): ?>
    <div>
      <h2 class="text-2xl font-extrabold">Holidays</h2>
      <div class="mt-4 grid gap-4 md:grid-cols-2">
        <?php foreach ($mine['tours'] as $b): ?>
          <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-ink-900/5">
            <div class="flex items-start justify-between gap-3">
              <div><p class="font-display text-xl font-bold"><?= e($b['title']) ?></p><p class="text-sm text-ink-600 num">Departs <?= nice_date($b['start_date']) ?> · <?= plural($b['travellers'], 'traveller') ?></p></div>
              <?= pill($b['status'], $status_names) ?>
            </div>
            <p class="mt-3 text-sm"><span class="font-mono"><?= e($b['booking_ref']) ?></span> · paid <?= naira($b['amount_paid_kobo']) ?> of <?= naira($b['total_kobo']) ?></p>
            <a class="btn btn-ghost mt-4" href="tour-booking.php?ref=<?= e($b['booking_ref']) ?>">View booking</a>
            <?= in_array($b['status'], ['deposit_paid', 'paid', 'confirmed']) ? cancel_form('tour', $b['id']) : '' ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($mine['visas']): ?>
    <div>
      <h2 class="text-2xl font-extrabold">Visa applications</h2>
      <div class="mt-4 grid gap-4 md:grid-cols-2">
        <?php foreach ($mine['visas'] as $v): ?>
          <a href="visa-track.php?ref=<?= e($v['reference']) ?>" class="block rounded-2xl bg-white p-5 shadow-sm ring-1 ring-ink-900/5 hover:shadow-md">
            <div class="flex items-start justify-between gap-3">
              <div><p class="font-display text-xl font-bold"><?= e($v['country_name']) ?> visa</p><p class="text-sm text-ink-600">Travel <?= nice_date($v['travel_date']) ?></p></div>
              <?= pill($v['status'], $status_names) ?>
            </div>
            <p class="mt-3 font-mono text-sm"><?= e($v['reference']) ?></p>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</section>
<?php include 'includes/footer.php'; ?>
