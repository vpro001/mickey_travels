<?php
require '../includes/init.php';
require '../includes/admin.php';

$today = db_one("SELECT
    (SELECT COUNT(*) FROM flight_bookings WHERE DATE(created_at) = CURDATE() AND status NOT IN ('pending_payment', 'expired', 'payment_failed')) AS flights,
    (SELECT COUNT(*) FROM tour_bookings WHERE DATE(created_at) = CURDATE() AND amount_paid_kobo > 0) AS tours,
    (SELECT COALESCE(SUM(amount_kobo), 0) FROM payments WHERE status = 'success' AND DATE(paid_at) = CURDATE()) AS revenue_today,
    (SELECT COALESCE(SUM(amount_kobo), 0) FROM payments WHERE status = 'success' AND paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS revenue_month,
    (SELECT COALESCE(SUM(markup_kobo), 0) FROM flight_bookings WHERE status = 'confirmed' AND confirmed_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS fees_month,
    (SELECT COUNT(*) FROM visa_applications WHERE status IN ('submitted', 'under_review')) AS visas_waiting,
    (SELECT COUNT(*) FROM flight_bookings WHERE status = 'needs_attention') AS attention,
    (SELECT COUNT(*) FROM hotel_bookings WHERE status = 'needs_attention') AS hotel_attention,
    (SELECT COUNT(*) FROM enquiries WHERE status = 'new') AS enquiries");

$flights = db_all("SELECT * FROM flight_bookings WHERE status <> 'expired' ORDER BY id DESC LIMIT 8");
$visas = db_all("SELECT a.*, d.country_name FROM visa_applications a JOIN visa_destinations d ON d.id = a.destination_id ORDER BY a.id DESC LIMIT 6");
$tours = db_all("SELECT b.*, p.title FROM tour_bookings b JOIN tour_packages p ON p.id = b.package_id ORDER BY b.id DESC LIMIT 5");
$enquiries = db_all("SELECT * FROM enquiries ORDER BY id DESC LIMIT 5");

$title = 'Dashboard';
$section = 'dashboard';
include '../includes/admin-header.php';
?>
<div class="flex flex-wrap items-end justify-between gap-3">
  <div>
    <p class="eyebrow text-saffron-700"><?= date('l j F Y') ?></p>
    <h1 class="mt-1 text-3xl font-extrabold">Good <?= date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening') ?>, <?= e(explode(' ', $_SESSION['staff']['name'])[0]) ?></h1>
  </div>
  <?php if (FLIGHT_DATA == 'demo'): ?><span class="badge bg-saffron-300 text-ink-950">Flights in demo mode</span><?php endif; ?>
</div>

<?php if ($today['hotel_attention'] > 0): ?>
  <a href="hotel-bookings.php" class="mt-6 flex items-center justify-between gap-4 rounded-xl bg-bad-100 p-4 text-bad-700 ring-1 ring-bad-700/20">
    <span><b><?= plural($today['hotel_attention'], 'paid hotel booking') ?> need<?= $today['hotel_attention'] == 1 ? 's' : '' ?> a person.</b> The customer has paid but the hotel is not booked yet.</span>
    <span class="font-semibold">Open →</span>
  </a>
<?php endif; ?>
<?php if ($today['attention'] > 0): ?>
  <a href="bookings.php?status=needs_attention" class="mt-6 flex items-center justify-between gap-4 rounded-xl bg-bad-100 p-4 text-bad-700 ring-1 ring-bad-700/20">
    <span><b><?= plural($today['attention'], 'paid booking') ?> need<?= $today['attention'] == 1 ? 's' : '' ?> a person.</b> The customer has paid but the ticket was not issued.</span>
    <span class="font-semibold">Open →</span>
  </a>
<?php endif; ?>

<div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
  <?php foreach ([
      ['Paid today', naira($today['revenue_today']), plural($today['flights'], 'flight') . ' · ' . plural($today['tours'], 'tour') . ' booked today'],
      ['Paid this month', naira($today['revenue_month']), 'All payments received through Paystack'],
      ['Flight fees this month', naira($today['fees_month']), 'Our service fee on ticketed flights'],
      ['Waiting for us', $today['visas_waiting'] + $today['enquiries'], plural($today['visas_waiting'], 'visa application') . ' · ' . plural($today['enquiries'], 'new enquiry', 'new enquiries')],
  ] as $card): ?>
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-ink-900/5">
      <p class="font-mono text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-600"><?= $card[0] ?></p>
      <p class="mt-2 font-display text-3xl font-bold num"><?= $card[1] ?></p>
      <p class="mt-1 text-xs text-ink-600"><?= $card[2] ?></p>
    </div>
  <?php endforeach; ?>
</div>

<div class="mt-8 grid gap-6 xl:grid-cols-[1.4fr_1fr]">
  <section class="rounded-2xl bg-white shadow-sm ring-1 ring-ink-900/5">
    <div class="flex items-center justify-between border-b border-paper-200 px-5 py-4"><h2 class="font-display text-lg font-bold">Latest flight bookings</h2><a class="text-sm link" href="bookings.php">All</a></div>
    <div class="overflow-x-auto">
      <table class="w-full text-left text-sm">
        <thead class="text-xs text-ink-600"><tr><th class="px-5 py-2 font-medium">Reference</th><th class="px-3 py-2 font-medium">Customer</th><th class="px-3 py-2 font-medium">Status</th><th class="px-5 py-2 text-right font-medium">Total</th></tr></thead>
        <tbody class="divide-y divide-paper-200">
          <?php foreach ($flights as $b): ?>
            <tr class="hover:bg-paper-50">
              <td class="px-5 py-3"><a class="font-mono font-semibold hover:underline" href="booking.php?id=<?= $b['id'] ?>"><?= e($b['booking_ref']) ?></a><br><span class="text-xs text-ink-600"><?= date('j M, H:i', strtotime($b['created_at'])) ?></span></td>
              <td class="px-3 py-3"><?= e($b['contact_email']) ?></td>
              <td class="px-3 py-3"><?= status_badge($b['status']) ?></td>
              <td class="px-5 py-3 text-right num"><?= naira($b['total_kobo']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$flights): ?><tr><td colspan="4" class="px-5 py-8 text-center text-ink-600">No flight bookings yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <div class="space-y-6">
    <section class="rounded-2xl bg-white shadow-sm ring-1 ring-ink-900/5">
      <div class="flex items-center justify-between border-b border-paper-200 px-5 py-4"><h2 class="font-display text-lg font-bold">Visa applications</h2><a class="text-sm link" href="visas.php">All</a></div>
      <ul class="divide-y divide-paper-200 text-sm">
        <?php foreach ($visas as $v): ?>
          <li><a href="visa.php?id=<?= $v['id'] ?>" class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-paper-50"><span><b><?= e($v['full_name']) ?></b><br><span class="text-xs text-ink-600"><?= e($v['country_name']) ?> · <?= e($v['reference']) ?></span></span><?= status_badge($v['status']) ?></a></li>
        <?php endforeach; ?>
        <?php if (!$visas): ?><li class="px-5 py-6 text-center text-ink-600">No applications yet.</li><?php endif; ?>
      </ul>
    </section>
    <section class="rounded-2xl bg-white shadow-sm ring-1 ring-ink-900/5">
      <div class="flex items-center justify-between border-b border-paper-200 px-5 py-4"><h2 class="font-display text-lg font-bold">Tour bookings</h2><a class="text-sm link" href="tour-bookings.php">All</a></div>
      <ul class="divide-y divide-paper-200 text-sm">
        <?php foreach ($tours as $t): ?>
          <li class="flex items-center justify-between gap-3 px-5 py-3"><span><b><?= e($t['lead_name']) ?></b><br><span class="text-xs text-ink-600"><?= e($t['title']) ?> · <?= e($t['booking_ref']) ?></span></span><?= status_badge($t['status']) ?></li>
        <?php endforeach; ?>
        <?php if (!$tours): ?><li class="px-5 py-6 text-center text-ink-600">No tour bookings yet.</li><?php endif; ?>
      </ul>
    </section>
    <section class="rounded-2xl bg-white shadow-sm ring-1 ring-ink-900/5">
      <div class="flex items-center justify-between border-b border-paper-200 px-5 py-4"><h2 class="font-display text-lg font-bold">Enquiries</h2><a class="text-sm link" href="enquiries.php">All</a></div>
      <ul class="divide-y divide-paper-200 text-sm">
        <?php foreach ($enquiries as $q): ?>
          <li class="flex items-center justify-between gap-3 px-5 py-3"><span><b><?= e($q['name']) ?></b><br><span class="text-xs text-ink-600"><?= e($q['subject']) ?></span></span><?= status_badge($q['status']) ?></li>
        <?php endforeach; ?>
        <?php if (!$enquiries): ?><li class="px-5 py-6 text-center text-ink-600">No enquiries yet.</li><?php endif; ?>
      </ul>
    </section>
  </div>
</div>
<?php include '../includes/admin-footer.php'; ?>
