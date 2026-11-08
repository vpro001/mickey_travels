<?php
require '../includes/init.php';
require '../includes/admin.php';

// Tour bookings. Staff confirm them once hotels and flights are arranged, or cancel/refund.
$rules = [
    'confirmed'              => ['paid', 'deposit_paid'],
    'cancellation_requested' => ['deposit_paid', 'paid', 'confirmed'],
    'cancelled'              => ['pending_payment', 'payment_failed', 'deposit_paid', 'paid', 'confirmed', 'cancellation_requested'],
    'refunded'               => ['cancelled'],
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_check();
    $id = (int) post('id');
    $to = post('status');
    if (isset($rules[$to])) {
        $from = $rules[$to];
        $marks = implode(',', array_fill(0, count($from), '?'));
        $stmt = db_query("UPDATE tour_bookings SET status = ? WHERE id = ? AND status IN ($marks)", array_merge([$to, $id], $from));
        if (mysqli_stmt_affected_rows($stmt) == 1) {
            // Give the seats back when a booking is cancelled
            if ($to == 'cancelled') {
                $b = db_one("SELECT departure_id, travellers, amount_paid_kobo FROM tour_bookings WHERE id = ?", [$id]);
                if ($b['amount_paid_kobo'] > 0) {
                    db_query("UPDATE tour_departures SET seats_booked = GREATEST(0, seats_booked - ?) WHERE id = ?", [(int) $b['travellers'], $b['departure_id']]);
                }
            }
            db_insert('audit_log', ['user_id' => $_SESSION['staff']['id'], 'action' => 'status:' . $to, 'entity_type' => 'tour_booking', 'entity_id' => $id, 'details' => json_encode(['note' => post('note')]), 'ip' => $_SERVER['REMOTE_ADDR']]);
            flash('Status updated.');
        } else {
            flash('That change is not allowed from the current status.', 'error');
        }
    }
    redirect('tour-bookings.php');
}

$bookings = db_all("SELECT b.*, p.title, d.start_date FROM tour_bookings b JOIN tour_packages p ON p.id = b.package_id JOIN tour_departures d ON d.id = b.departure_id ORDER BY b.id DESC LIMIT 200");

$title = 'Tour bookings';
$section = 'tours-bookings';
include '../includes/admin-header.php';
?>
<h1 class="text-3xl font-extrabold">Tour bookings</h1>
<div class="mt-5 overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-ink-900/5">
  <table class="w-full text-left text-sm">
    <thead class="border-b border-paper-200 text-xs text-ink-600">
      <tr><th class="px-5 py-3 font-medium">Reference</th><th class="px-3 py-3 font-medium">Tour</th><th class="px-3 py-3 font-medium">Lead traveller</th><th class="px-3 py-3 font-medium">Paid</th><th class="px-3 py-3 font-medium">Status</th><th class="px-5 py-3 font-medium">Change</th></tr>
    </thead>
    <tbody class="divide-y divide-paper-200">
      <?php foreach ($bookings as $b):
          $next = [];
          foreach ($rules as $to => $from) if (in_array($b['status'], $from)) $next[] = $to;
      ?>
        <tr class="align-top hover:bg-paper-50">
          <td class="px-5 py-3 font-mono font-semibold"><?= e($b['booking_ref']) ?><br><span class="font-sans text-xs font-normal text-ink-600"><?= date('j M Y', strtotime($b['created_at'])) ?></span></td>
          <td class="px-3 py-3"><?= e($b['title']) ?><br><span class="text-xs text-ink-600">Departs <?= nice_date($b['start_date']) ?> · <?= plural($b['travellers'], 'traveller') ?></span></td>
          <td class="px-3 py-3"><?= e($b['lead_name']) ?><br><span class="text-xs text-ink-600"><?= e($b['email']) ?> · <?= e($b['phone']) ?></span></td>
          <td class="px-3 py-3 num"><?= naira($b['amount_paid_kobo']) ?><br><span class="text-xs text-ink-600">of <?= naira($b['total_kobo']) ?></span></td>
          <td class="px-3 py-3"><?= status_badge($b['status']) ?></td>
          <td class="px-5 py-3">
            <?php if ($next): ?>
              <form method="post" class="flex gap-2" onsubmit="return confirm('Change this booking\'s status?')">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $b['id'] ?>">
                <select name="status" class="rounded-md border-0 bg-paper-50 px-2 py-1.5 text-sm ring-1 ring-ink-300"><?php foreach ($next as $n): ?><option value="<?= $n ?>"><?= ucfirst(str_replace('_', ' ', $n)) ?></option><?php endforeach; ?></select>
                <button class="btn btn-dark px-3 py-1.5">Save</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$bookings): ?><tr><td colspan="6" class="px-5 py-10 text-center text-ink-600">No tour bookings yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php include '../includes/admin-footer.php'; ?>
