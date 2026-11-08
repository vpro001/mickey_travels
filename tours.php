<?php
require 'includes/init.php';

// All published holiday packages, with optional filters ?destination= and ?month=YYYY-MM
$destination = get('destination');
$month = preg_match('/^\d{4}-\d{2}$/', get('month')) ? get('month') : '';

$sql = "SELECT p.*,
               MIN(CASE WHEN d.start_date >= CURDATE() AND d.status = 'open' AND d.seats_booked < d.seats_total THEN d.start_date END) AS next_date,
               SUM(CASE WHEN d.start_date >= CURDATE() AND d.status = 'open' THEN d.seats_total - d.seats_booked ELSE 0 END) AS seats_left
        FROM tour_packages p
        LEFT JOIN tour_departures d ON d.package_id = p.id
        WHERE p.is_published = 1";
$params = [];
if ($destination != '') {
    $sql .= " AND p.destination = ?";
    $params[] = $destination;
}
if ($month != '') {
    $sql .= " AND EXISTS (SELECT 1 FROM tour_departures x WHERE x.package_id = p.id AND x.status = 'open' AND DATE_FORMAT(x.start_date, '%Y-%m') = ?)";
    $params[] = $month;
}
$sql .= " GROUP BY p.id ORDER BY p.sort_order, p.title";
$tours = db_all($sql, $params);
$places = db_all("SELECT DISTINCT destination FROM tour_packages WHERE is_published = 1 ORDER BY destination");

$title = 'Tours and holidays';
$page = 'tours';
include 'includes/header.php';
?>
<section class="relative isolate overflow-hidden bg-ink-950 text-paper-50">
  <?= photo('cape-town', 'Table Mountain seen from the beach', 'hero-photo absolute inset-0 -z-20 h-full w-full object-cover', false, [480, 800]) ?>
  <div class="absolute inset-0 -z-10 bg-gradient-to-r from-ink-950 via-ink-950/80 to-ink-950/20"></div>
  <div class="container-page py-16 sm:py-24">
    <p class="eyebrow text-saffron-300">Tours and holidays</p>
    <h1 class="mt-3 max-w-3xl text-5xl font-extrabold leading-[0.95] tracking-[-0.035em] sm:text-7xl">Holidays we've already planned.</h1>
    <p class="mt-4 max-w-xl text-lg text-ink-100">Flights, hotel, transfers and a local guide, with set dates and small groups. A deposit secures your place.</p>

    <form method="get" class="mt-8 flex max-w-2xl flex-col gap-2 rounded-2xl bg-white p-2 sm:flex-row">
      <select name="destination" class="flex-1 rounded-xl border-0 bg-paper-50 px-4 py-3 text-ink-900 focus:ring-2 focus:ring-saffron-500" aria-label="Destination">
        <option value="">Anywhere</option>
        <?php foreach ($places as $pl): ?><option <?= $destination == $pl['destination'] ? 'selected' : '' ?>><?= e($pl['destination']) ?></option><?php endforeach; ?>
      </select>
      <select name="month" class="flex-1 rounded-xl border-0 bg-paper-50 px-4 py-3 text-ink-900 focus:ring-2 focus:ring-saffron-500" aria-label="Month">
        <option value="">Any month</option>
        <?php for ($m = 0; $m < 12; $m++): $ts = strtotime(date('Y-m-01') . " +$m months"); ?>
          <option value="<?= date('Y-m', $ts) ?>" <?= $month == date('Y-m', $ts) ? 'selected' : '' ?>><?= date('F Y', $ts) ?></option>
        <?php endfor; ?>
      </select>
      <button class="btn btn-primary px-6 py-3">Show holidays</button>
    </form>
  </div>
</section>

<section class="container-page py-12 sm:py-16">
  <?php if (!$tours): ?>
    <div class="rounded-2xl bg-white p-10 text-center ring-1 ring-paper-200">
      <p class="font-display text-2xl font-bold">No holidays match that yet</p>
      <p class="mt-2 text-ink-700">Try another month, or tell us where you'd like to go and we'll plan it for you.</p>
      <div class="mt-5 flex justify-center gap-3"><a class="btn btn-ghost" href="tours.php">See all holidays</a><a class="btn btn-dark" href="contact.php">Plan a custom trip</a></div>
    </div>
  <?php else: ?>
    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
      <?php foreach ($tours as $n => $t): ?>
        <a href="tour.php?slug=<?= e($t['slug']) ?>" style="--delay: <?= ($n % 3) * 80 ?>ms" class="reveal group flex flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-ink-900/5 transition hover:-translate-y-1 hover:shadow-xl">
          <div class="relative aspect-[4/3] overflow-hidden bg-ink-800">
            <?php if ($t['cover_image']): ?><?= photo($t['cover_image'], $t['destination'], 'h-full w-full object-cover transition duration-700 group-hover:scale-105', true, [480, 800], '(min-width: 1024px) 33vw, 100vw') ?><?php endif; ?>
            <span class="absolute left-4 top-4 rounded-full bg-ink-950/80 px-3 py-1 font-mono text-[11px] font-semibold uppercase tracking-wider text-paper-50"><?= plural($t['duration_days'], 'day') ?> · <?= plural($t['duration_nights'], 'night') ?></span>
          </div>
          <div class="flex flex-1 flex-col p-5">
            <p class="font-mono text-[11px] font-semibold uppercase tracking-[0.18em] text-saffron-700"><?= e($t['destination']) ?></p>
            <h2 class="mt-1 text-2xl font-extrabold leading-tight"><?= e($t['title']) ?></h2>
            <p class="mt-2 flex-1 text-sm text-ink-700"><?= e($t['summary']) ?></p>
            <div class="mt-5 flex items-end justify-between gap-3 border-t border-paper-200 pt-4">
              <div>
                <p class="font-display text-2xl font-bold num"><?= naira($t['price_kobo']) ?></p>
                <p class="text-xs text-ink-600"><?= e($t['price_basis']) ?></p>
              </div>
              <p class="text-right text-xs text-ink-600">
                <?php if ($t['next_date']): ?>Next departure<br><b class="text-ink-900 num"><?= nice_date($t['next_date']) ?></b><?php else: ?>Dates coming soon<?php endif; ?>
              </p>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php include 'includes/footer.php'; ?>
