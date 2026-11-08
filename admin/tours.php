<?php
require '../includes/init.php';
require '../includes/admin.php';

$tours = db_all("SELECT p.*,
    (SELECT COUNT(*) FROM tour_departures d WHERE d.package_id = p.id AND d.start_date >= CURDATE() AND d.status = 'open') AS upcoming,
    (SELECT COUNT(*) FROM tour_bookings b WHERE b.package_id = p.id AND b.amount_paid_kobo > 0) AS booked
    FROM tour_packages p ORDER BY p.sort_order, p.title");

$title = 'Tour packages';
$section = 'tours';
include '../includes/admin-header.php';
?>
<div class="flex flex-wrap items-center justify-between gap-3">
  <h1 class="text-3xl font-extrabold">Tour packages</h1>
  <a href="tour-edit.php" class="btn btn-dark">+ New package</a>
</div>
<div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
  <?php foreach ($tours as $t):
      $img = '';
      if ($t['cover_image']) {
          $img = file_exists(__DIR__ . '/../assets/img/' . $t['cover_image'] . '-480.webp') ? $t['cover_image'] . '-480.webp' : $t['cover_image'] . '-480.jpg';
      }
  ?>
    <a href="tour-edit.php?id=<?= $t['id'] ?>" class="group overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-ink-900/5 hover:shadow-md">
      <div class="relative aspect-[16/9] bg-ink-800">
        <?php if ($img): ?><img src="../assets/img/<?= e($img) ?>" alt="" class="h-full w-full object-cover"><?php endif; ?>
        <span class="absolute left-3 top-3"><?= $t['is_published'] ? '<span class="badge bg-ok-100 text-ok-700">Published</span>' : '<span class="badge bg-ink-100 text-ink-700">Draft</span>' ?></span>
      </div>
      <div class="p-4">
        <p class="font-display text-lg font-bold group-hover:underline"><?= e($t['title']) ?></p>
        <p class="text-sm text-ink-600"><?= e($t['destination']) ?> · <?= plural($t['duration_nights'], 'night') ?> · <?= naira($t['price_kobo']) ?></p>
        <p class="mt-2 text-xs text-ink-600"><?= plural($t['upcoming'], 'upcoming date') ?> · <?= plural($t['booked'], 'paid booking') ?></p>
      </div>
    </a>
  <?php endforeach; ?>
</div>
<?php include '../includes/admin-footer.php'; ?>
