<?php
require '../includes/init.php';
require '../includes/admin.php';

// Flight bookings, filtered by status and searched by reference, PNR or email
$status = get('status');
$q = get('q');
$statuses = ['pending_payment', 'payment_failed', 'expired', 'paid', 'ticketing', 'confirmed', 'needs_attention', 'cancellation_requested', 'cancelled', 'refunded'];

$sql = "SELECT * FROM flight_bookings WHERE 1";
$params = [];
if (in_array($status, $statuses)) {
    $sql .= " AND status = ?";
    $params[] = $status;
}
if ($q != '') {
    $sql .= " AND (booking_ref = ? OR pnr = ? OR contact_email LIKE ?)";
    array_push($params, strtoupper($q), strtoupper($q), '%' . addcslashes($q, '%_\\') . '%');
}
$sql .= " ORDER BY id DESC LIMIT 200";
$bookings = db_all($sql, $params);

$title = 'Flight bookings';
$section = 'flights';
include '../includes/admin-header.php';
?>
<h1 class="text-3xl font-extrabold">Flight bookings</h1>
<form class="mt-5 flex flex-wrap gap-2">
  <input name="q" value="<?= e($q) ?>" placeholder="Reference, PNR or email" class="field mt-0 w-72">
  <select name="status" class="field mt-0 w-56">
    <option value="">All statuses</option>
    <?php foreach ($statuses as $s): ?><option value="<?= $s ?>" <?= $status == $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option><?php endforeach; ?>
  </select>
  <button class="btn btn-dark">Filter</button>
</form>

<div class="mt-5 overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-ink-900/5">
  <table class="w-full text-left text-sm">
    <thead class="border-b border-paper-200 text-xs text-ink-600">
      <tr><th class="px-5 py-3 font-medium">Reference</th><th class="px-3 py-3 font-medium">Created</th><th class="px-3 py-3 font-medium">Route</th><th class="px-3 py-3 font-medium">Customer</th><th class="px-3 py-3 font-medium">PNR</th><th class="px-3 py-3 font-medium">Status</th><th class="px-5 py-3 text-right font-medium">Total</th></tr>
    </thead>
    <tbody class="divide-y divide-paper-200">
      <?php foreach ($bookings as $b): $trip = json_decode($b['itinerary_json'], true); ?>
        <tr class="hover:bg-paper-50">
          <td class="px-5 py-3"><a class="font-mono font-semibold hover:underline" href="booking.php?id=<?= $b['id'] ?>"><?= e($b['booking_ref']) ?></a></td>
          <td class="px-3 py-3 text-ink-700 num"><?= date('j M Y H:i', strtotime($b['created_at'])) ?></td>
          <td class="px-3 py-3 font-mono"><?= e($trip[0]['from'] . ' → ' . $trip[0]['to']) ?><?= count($trip) > 1 ? ' +' . (count($trip) - 1) : '' ?></td>
          <td class="px-3 py-3"><?= e($b['contact_email']) ?></td>
          <td class="px-3 py-3 font-mono font-semibold"><?= e($b['pnr'] ?: '—') ?></td>
          <td class="px-3 py-3"><?= status_badge($b['status']) ?></td>
          <td class="px-5 py-3 text-right num"><?= naira($b['total_kobo']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$bookings): ?><tr><td colspan="7" class="px-5 py-10 text-center text-ink-600">No bookings found.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php include '../includes/admin-footer.php'; ?>
