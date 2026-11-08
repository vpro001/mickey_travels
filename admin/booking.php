<?php
require '../includes/init.php';
require '../includes/admin.php';
require '../includes/booking.php';

$id = (int) get('id');
$b = db_one("SELECT * FROM flight_bookings WHERE id = ?", [$id]);
if (!$b) {
    redirect('bookings.php');
}

// Staff actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_check();
    $action = post('action');
    $note = post('note');

    if ($action == 'ticketed_by_hand') {
        // Staff issued the ticket outside the site (e.g. on the Duffel dashboard)
        $pnr = strtoupper(post('pnr'));
        if (!preg_match('/^[A-Z0-9]{5,8}$/', $pnr)) {
            flash('Enter the airline booking reference (5–8 letters and numbers).', 'error');
        } elseif (flight_status($id, 'confirmed', 'Ticketed by staff: ' . $pnr . ($note ? ". $note" : ''))) {
            db_query("UPDATE flight_bookings SET pnr = ?, confirmed_at = NOW() WHERE id = ?", [$pnr, $id]);
            send_booking_email($id);
            flash('Marked as ticketed. The customer has been emailed.');
        } else {
            flash('That change is not allowed from the current status.', 'error');
        }
    } elseif ($action == 'retry') {
        issue_ticket($id);
        $now = db_one("SELECT status, status_reason FROM flight_bookings WHERE id = ?", [$id]);
        flash($now['status'] == 'confirmed' ? 'Ticket issued.' : 'Still not ticketed: ' . $now['status_reason'], $now['status'] == 'confirmed' ? 'success' : 'error');
    } elseif (in_array($action, ['cancelled', 'refunded', 'cancellation_requested'])) {
        $changed = flight_status($id, $action, 'Staff: ' . ($note ?: $action));
        flash($changed ? 'Status updated.' : 'That change is not allowed from the current status.', $changed ? 'success' : 'error');
    } elseif ($action == 'resend' && $b['status'] == 'confirmed') {
        send_booking_email($id);
        flash('Confirmation email sent again.');
    }
    redirect('booking.php?id=' . $id);
}

$passengers = db_all("SELECT * FROM flight_passengers WHERE booking_id = ? ORDER BY id", [$id]);
$payments = db_all("SELECT * FROM payments WHERE booking_type = 'flight' AND booking_id = ? ORDER BY id", [$id]);
$history = db_all("SELECT a.*, u.full_name FROM audit_log a LEFT JOIN users u ON u.id = a.user_id WHERE a.entity_type = 'flight_booking' AND a.entity_id = ? ORDER BY a.id", [$id]);
$trip = json_decode($b['itinerary_json'], true);
$offer = json_decode($b['offer_snapshot'], true);
$allowed = [];
foreach (flight_status_rules() as $to => $from) {
    if (in_array($b['status'], $from)) $allowed[] = $to;
}

$title = $b['booking_ref'];
$section = 'flights';
include '../includes/admin-header.php';
$small = 'font-mono text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-600';
?>
<a href="bookings.php" class="text-sm text-ink-600 hover:text-ink-900">← Flight bookings</a>
<div class="mt-2 flex flex-wrap items-center gap-3">
  <h1 class="font-mono text-3xl font-bold"><?= e($b['booking_ref']) ?></h1>
  <?= status_badge($b['status']) ?>
</div>
<?php if ($b['status_reason']): ?><p class="mt-2 max-w-3xl text-sm text-ink-700"><?= e($b['status_reason']) ?></p><?php endif; ?>

<div class="mt-6 grid gap-6 xl:grid-cols-[1.5fr_1fr]">
  <div class="space-y-6">
    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5">
      <div class="flex flex-wrap justify-between gap-4">
        <div><p class="<?= $small ?>">Airline</p><p class="mt-1 font-semibold"><?= e($offer['owner']['name']) ?></p></div>
        <div><p class="<?= $small ?>">PNR</p><p class="mt-1 font-mono text-xl font-bold tracking-widest"><?= e($b['pnr'] ?: '—') ?></p></div>
        <div><p class="<?= $small ?>">Duffel order</p><p class="mt-1 font-mono text-xs"><?= e($b['duffel_order_id'] ?: '—') ?></p></div>
        <div><p class="<?= $small ?>">Offer</p><p class="mt-1 font-mono text-xs"><?= e($b['offer_id']) ?></p></div>
      </div>
      <div class="mt-5 divide-y divide-paper-200 border-t border-paper-200">
        <?php foreach ($trip as $s): ?>
          <div class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
            <span class="font-mono font-semibold"><?= e($s['from']) ?> → <?= e($s['to']) ?></span>
            <span class="num"><?= date('D j M Y H:i', strtotime($s['depart'])) ?> → <?= date('H:i', strtotime($s['arrive'])) ?></span>
            <span class="text-ink-600"><?= $s['stops'] ? plural($s['stops'], 'stop') . ' via ' . e(implode(', ', $s['via'])) : 'Direct' ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5">
      <h2 class="font-display text-lg font-bold">Travellers</h2>
      <div class="mt-3 overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="text-xs text-ink-600"><tr><th class="py-2 font-medium">Name</th><th class="py-2 font-medium">Type</th><th class="py-2 font-medium">Born</th><th class="py-2 font-medium">Passport</th><th class="py-2 font-medium">E-ticket</th></tr></thead>
          <tbody class="divide-y divide-paper-200">
            <?php foreach ($passengers as $p): ?>
              <tr>
                <td class="py-2.5 font-semibold"><?= e(strtoupper($p['family_name']) . ' / ' . $p['given_name'] . ' ' . strtoupper($p['title'])) ?></td>
                <td class="py-2.5"><?= e(str_replace('_', ' ', $p['type'])) ?></td>
                <td class="py-2.5 num"><?= nice_date($p['born_on']) ?></td>
                <td class="py-2.5 font-mono text-xs"><?= $p['passport_number'] ? e($p['passport_number'] . ' · ' . $p['passport_country'] . ' · exp ' . $p['passport_expires_on']) : '—' ?></td>
                <td class="py-2.5 font-mono"><?= e($p['ticket_number'] ?: '—') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="mt-3 text-sm text-ink-700">Contact: <?= e($b['contact_email']) ?> · <?= e($b['contact_phone']) ?></p>
    </section>

    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5">
      <h2 class="font-display text-lg font-bold">History</h2>
      <ol class="mt-3 space-y-2 text-sm">
        <li class="text-ink-600 num"><?= date('j M Y H:i', strtotime($b['created_at'])) ?> · Booking created, waiting for payment</li>
        <?php foreach ($history as $h): $d = json_decode((string) $h['details'], true); ?>
          <li class="num"><span class="text-ink-600"><?= date('j M Y H:i', strtotime($h['created_at'])) ?></span> · <b><?= e(str_replace(['status:', '_'], ['', ' '], $h['action'])) ?></b><?= !empty($d['reason']) ? ' — ' . e($d['reason']) : '' ?> <span class="text-ink-500">(<?= e($h['full_name'] ?: 'system') ?>)</span></li>
        <?php endforeach; ?>
      </ol>
    </section>
  </div>

  <div class="space-y-6">
    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5">
      <h2 class="font-display text-lg font-bold">Money</h2>
      <dl class="mt-3 space-y-1.5 text-sm">
        <div class="flex justify-between"><dt class="text-ink-600">Airline price</dt><dd class="num"><?= e($b['supplier_currency'] . ' ' . number_format($b['supplier_amount'], 2)) ?></dd></div>
        <div class="flex justify-between"><dt class="text-ink-600">Rate used (incl. <?= (float) $b['fx_buffer_pct'] ?>% buffer)</dt><dd class="num">₦<?= number_format($b['fx_rate'], 2) ?></dd></div>
        <div class="flex justify-between"><dt class="text-ink-600">Airline price in naira</dt><dd class="num"><?= naira($b['base_kobo']) ?></dd></div>
        <div class="flex justify-between"><dt class="text-ink-600">Our fee (<?= e(str_replace('_', ' ', $b['markup_type'])) ?>)</dt><dd class="num"><?= naira($b['markup_kobo']) ?></dd></div>
        <div class="flex justify-between border-t border-paper-200 pt-1.5 font-bold"><dt>Customer total</dt><dd class="num"><?= naira($b['total_kobo']) ?></dd></div>
      </dl>
      <h3 class="mt-5 <?= $small ?>">Payments</h3>
      <ul class="mt-2 space-y-2 text-sm">
        <?php foreach ($payments as $p): ?>
          <li class="flex items-center justify-between gap-2"><span class="font-mono text-xs"><?= e($p['reference']) ?><br><span class="text-ink-500"><?= $p['paid_at'] ? 'Paid ' . date('j M H:i', strtotime($p['paid_at'])) . ' · ' . e($p['channel']) : date('j M H:i', strtotime($p['created_at'])) ?></span></span><?= status_badge($p['status'] == 'initialized' ? 'pending' : $p['status']) ?></li>
        <?php endforeach; ?>
      </ul>
    </section>

    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5">
      <h2 class="font-display text-lg font-bold">Actions</h2>
      <?php if ($b['status'] == 'needs_attention'): ?>
        <form method="post" class="mt-4 space-y-3 rounded-xl bg-paper-100 p-4">
          <?= csrf_field() ?><input type="hidden" name="action" value="ticketed_by_hand">
          <p class="text-sm font-semibold">Ticketed it yourself?</p>
          <input name="pnr" placeholder="Airline reference (PNR)" class="field mt-0 font-mono uppercase">
          <input name="note" placeholder="Note (optional)" class="field mt-0">
          <button class="btn btn-dark w-full">Mark as ticketed and email the customer</button>
        </form>
        <form method="post" class="mt-3" onsubmit="return confirm('Have you checked the Duffel dashboard? If an order already exists, trying again can book twice.')">
          <?= csrf_field() ?><input type="hidden" name="action" value="retry">
          <button class="btn btn-ghost w-full">Try the airline order again</button>
          <p class="mt-1 text-xs text-ink-600">Only after checking the Duffel dashboard that no order exists.</p>
        </form>
      <?php endif; ?>
      <?php if ($b['status'] == 'confirmed'): ?>
        <form method="post" class="mt-4"><?= csrf_field() ?><input type="hidden" name="action" value="resend"><button class="btn btn-ghost w-full">Resend confirmation email</button></form>
      <?php endif; ?>
      <?php $other = array_intersect($allowed, ['cancellation_requested', 'cancelled', 'refunded']); if ($other): ?>
        <form method="post" class="mt-4 space-y-2" onsubmit="return confirm('Change this booking\'s status?')">
          <?= csrf_field() ?>
          <select name="action" class="field mt-0"><?php foreach ($other as $o): ?><option value="<?= $o ?>"><?= ucfirst(str_replace('_', ' ', $o)) ?></option><?php endforeach; ?></select>
          <input name="note" placeholder="Reason" class="field mt-0">
          <button class="btn btn-ghost w-full">Update status</button>
        </form>
      <?php endif; ?>
      <?php if (!$allowed && $b['status'] != 'confirmed'): ?><p class="mt-3 text-sm text-ink-600">Nothing to do for this status.</p><?php endif; ?>
    </section>
  </div>
</div>
<?php include '../includes/admin-footer.php'; ?>
