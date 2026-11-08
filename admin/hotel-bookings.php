<?php
require '../includes/init.php';
require '../includes/admin.php';
require '../includes/booking.php';
require '../includes/stays.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_check();
    $id = (int) post('id');
    $action = post('action');
    $b = db_one("SELECT * FROM hotel_bookings WHERE id = ?", [$id]);
    if ($b && $action == 'confirmed_by_hand') {
        $hotel_ref = strtoupper(post('hotel_reference'));
        if (!preg_match('/^[A-Z0-9-]{4,40}$/', $hotel_ref)) {
            flash('Enter the hotel confirmation number.', 'error');
        } elseif (hotel_status($id, 'confirmed', 'Confirmed by staff: ' . $hotel_ref)) {
            db_query("UPDATE hotel_bookings SET hotel_reference = ?, confirmed_at = NOW() WHERE id = ?", [$hotel_ref, $id]);
            send_hotel_email($id);
            flash('Marked as confirmed. The customer has been emailed.');
        } else {
            flash('That change is not allowed from the current status.', 'error');
        }
    } elseif ($b && $action == 'retry') {
        book_hotel($id);
        $now = db_one("SELECT status, status_reason FROM hotel_bookings WHERE id = ?", [$id]);
        flash($now['status'] == 'confirmed' ? 'Hotel booked.' : 'Still not booked: ' . $now['status_reason'], $now['status'] == 'confirmed' ? 'success' : 'error');
    } elseif ($b && in_array($action, ['cancelled', 'refunded'])) {
        $ok = hotel_status($id, $action, 'Staff: ' . (post('note') ?: $action));
        flash($ok ? 'Status updated.' : 'That change is not allowed from the current status.', $ok ? 'success' : 'error');
    }
    redirect('hotel-bookings.php' . ($id ? '#b' . $id : ''));
}

$bookings = db_all("SELECT * FROM hotel_bookings ORDER BY FIELD(status, 'needs_attention') DESC, id DESC LIMIT 200");

$title = 'Hotel bookings';
$section = 'hotels';
include '../includes/admin-header.php';
?>
<h1 class="text-3xl font-extrabold">Hotel bookings</h1>
<div class="mt-5 space-y-3">
  <?php foreach ($bookings as $b): $h = json_decode($b['accommodation_json'], true); $g = json_decode($b['guests_json'], true); ?>
    <details id="b<?= $b['id'] ?>" class="rounded-2xl bg-white shadow-sm ring-1 ring-ink-900/5" <?= $b['status'] == 'needs_attention' ? 'open' : '' ?>>
      <summary class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
        <span><b class="font-mono"><?= e($b['booking_ref']) ?></b> · <?= e($h['name']) ?> <span class="text-sm text-ink-600">· <?= nice_date($b['check_in_date']) ?> – <?= nice_date($b['check_out_date']) ?> · <?= e($b['contact_email']) ?></span></span>
        <span class="flex items-center gap-3"><span class="font-semibold num"><?= naira($b['total_kobo']) ?></span><?= status_badge($b['status']) ?></span>
      </summary>
      <div class="grid gap-6 border-t border-paper-200 p-5 text-sm lg:grid-cols-2">
        <dl class="space-y-1.5">
          <div class="flex justify-between gap-3"><dt class="text-ink-600">Room</dt><dd class="text-right"><?= e($h['room']) ?> · <?= e(board_name($h['board'])) ?></dd></div>
          <div class="flex justify-between gap-3"><dt class="text-ink-600">Guests</dt><dd class="text-right"><?= e(implode(', ', array_map(function ($n) { return $n['given_name'] . ' ' . $n['family_name']; }, $g['names']))) ?></dd></div>
          <div class="flex justify-between gap-3"><dt class="text-ink-600">Phone</dt><dd><?= e($b['contact_phone']) ?></dd></div>
          <div class="flex justify-between gap-3"><dt class="text-ink-600">Hotel price</dt><dd class="num"><?= e($b['supplier_currency'] . ' ' . number_format($b['supplier_amount'], 2)) ?> × ₦<?= number_format($b['fx_rate'], 2) ?></dd></div>
          <div class="flex justify-between gap-3"><dt class="text-ink-600">Our fee</dt><dd class="num"><?= naira($b['markup_kobo']) ?></dd></div>
          <div class="flex justify-between gap-3"><dt class="text-ink-600">Hotel confirmation</dt><dd class="font-mono font-semibold"><?= e($b['hotel_reference'] ?: '—') ?></dd></div>
          <div class="flex justify-between gap-3"><dt class="text-ink-600">Duffel booking / quote</dt><dd class="font-mono text-xs"><?= e($b['duffel_booking_id'] ?: $b['quote_id']) ?></dd></div>
          <?php if ($b['status_reason']): ?><p class="rounded-lg bg-paper-100 p-3"><?= e($b['status_reason']) ?></p><?php endif; ?>
          <?php if (!empty($g['requests'])): ?><p class="rounded-lg bg-paper-100 p-3">Requests: <?= e($g['requests']) ?></p><?php endif; ?>
        </dl>
        <div class="space-y-3">
          <?php if ($b['status'] == 'needs_attention'): ?>
            <form method="post" class="space-y-2 rounded-xl bg-paper-100 p-4"><?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= $b['id'] ?>"><input type="hidden" name="action" value="confirmed_by_hand">
              <p class="font-semibold">Booked it yourself?</p>
              <input name="hotel_reference" placeholder="Hotel confirmation number" class="field mt-0 font-mono uppercase">
              <button class="btn btn-dark w-full">Mark confirmed and email the customer</button>
            </form>
            <form method="post" onsubmit="return confirm('Checked the Duffel dashboard? If a booking already exists, trying again can book twice.')"><?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= $b['id'] ?>"><input type="hidden" name="action" value="retry"><button class="btn btn-ghost w-full">Try the hotel booking again</button>
            </form>
          <?php endif; ?>
          <?php $next = array_intersect(['cancelled', 'refunded'], array_keys(array_filter(hotel_status_rules(), function ($from) use ($b) { return in_array($b['status'], $from); }))); if ($next): ?>
            <form method="post" class="flex gap-2" onsubmit="return confirm('Change this booking\'s status?')"><?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= $b['id'] ?>">
              <select name="action" class="field mt-0"><?php foreach ($next as $n): ?><option value="<?= $n ?>"><?= ucfirst($n) ?></option><?php endforeach; ?></select>
              <input name="note" placeholder="Reason" class="field mt-0"><button class="btn btn-ghost">Save</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </details>
  <?php endforeach; ?>
  <?php if (!$bookings): ?><p class="rounded-2xl bg-white p-10 text-center text-ink-600 ring-1 ring-ink-900/5">No hotel bookings yet.</p><?php endif; ?>
</div>
<?php include '../includes/admin-footer.php'; ?>
