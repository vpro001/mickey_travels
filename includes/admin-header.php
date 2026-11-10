<?php
// Top of every admin page. Set $title and $section ('dashboard', 'flights', 'tours-bookings', 'visas', 'tours', 'enquiries', 'settings').
if (!isset($title)) $title = 'Admin';
if (!isset($section)) $section = '';

$admin_menu = [
    'dashboard'      => ['index.php', 'Dashboard'],
    'flights'        => ['bookings.php', 'Flight bookings'],
    'hotels'         => ['hotel-bookings.php', 'Hotel bookings'],
    'tours-bookings' => ['tour-bookings.php', 'Tour bookings'],
    'visas'          => ['visas.php', 'Visa applications'],
    'visa-destinations' => ['visa-destinations.php', 'Visa destinations'],
    'tours'          => ['tours.php', 'Tour packages'],
    'enquiries'      => ['enquiries.php', 'Enquiries'],
    'settings'       => ['settings.php', 'Fees, rates & contact'],
];
// Counters next to menu items that need action
$counts = [
    'flights'   => db_one("SELECT COUNT(*) n FROM flight_bookings WHERE status = 'needs_attention'")['n'],
    'hotels'    => db_one("SELECT COUNT(*) n FROM hotel_bookings WHERE status = 'needs_attention'")['n'],
    'visas'     => db_one("SELECT COUNT(*) n FROM visa_applications WHERE status IN ('submitted', 'under_review')")['n'],
    'enquiries' => db_one("SELECT COUNT(*) n FROM enquiries WHERE status = 'new'")['n'],
];
?>
<!doctype html>
<html lang="en-NG">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($title) ?> · Admin · <?= e(SITE_NAME) ?></title>
<?php $base = '../'; include __DIR__ . '/head-assets.php'; ?>
</head>
<body class="min-h-dvh bg-paper-100 lg:flex">
<?php if (!empty($_SESSION['staff'])): ?>
<aside class="bg-ink-950 text-ink-200 lg:sticky lg:top-0 lg:h-dvh lg:w-64 lg:shrink-0">
  <div class="flex items-center justify-between gap-3 px-5 py-4 lg:block">
    <a href="index.php" class="flex items-center gap-2.5">
      <span class="flex size-8 items-center justify-center rounded-md bg-saffron-400 font-display text-lg font-extrabold text-ink-950">M</span>
      <span class="leading-none"><span class="block font-display font-bold text-paper-50">Mickey Travels</span><span class="font-mono text-[10px] uppercase tracking-[0.2em] text-saffron-300">Back office</span></span>
    </a>
    <details class="lg:hidden"><summary class="list-none rounded-md px-3 py-2 text-sm ring-1 ring-white/20">Menu</summary>
      <nav class="absolute left-0 right-0 z-40 mt-3 space-y-1 bg-ink-950 p-3">
        <?php foreach ($admin_menu as $key => $m): ?><a href="<?= $m[0] ?>" class="block rounded-md px-3 py-2.5 hover:bg-white/10"><?= $m[1] ?></a><?php endforeach; ?>
        <form method="post" action="logout.php"><?= csrf_field() ?><button class="block w-full rounded-md px-3 py-2.5 text-left hover:bg-white/10">Log out</button></form>
      </nav>
    </details>
  </div>
  <nav class="hidden space-y-0.5 px-3 lg:block">
    <?php foreach ($admin_menu as $key => $m): $on = $section == $key; ?>
      <a href="<?= $m[0] ?>" class="flex items-center justify-between rounded-md px-3 py-2.5 text-sm font-medium transition <?= $on ? 'bg-white/10 text-paper-50' : 'hover:bg-white/5 hover:text-paper-50' ?>">
        <?= $m[1] ?>
        <?php if (!empty($counts[$key])): ?><span class="rounded-full bg-saffron-400 px-2 py-0.5 text-[11px] font-bold text-ink-950"><?= $counts[$key] ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="hidden border-t border-white/10 px-5 py-4 text-sm lg:absolute lg:bottom-0 lg:block lg:w-64">
    <p class="text-paper-50"><?= e($_SESSION['staff']['name']) ?></p>
    <p class="text-xs capitalize text-ink-400"><?= e($_SESSION['staff']['role']) ?></p>
    <div class="mt-3 flex gap-4 text-xs"><a class="hover:text-saffron-300" href="../index.php" target="_blank">View site</a><form method="post" action="logout.php" class="inline"><?= csrf_field() ?><button class="hover:text-saffron-300">Log out</button></form></div>
  </div>
</aside>
<?php endif; ?>
<main class="min-w-0 flex-1 p-4 sm:p-8">
<?php if (isset($_SESSION['flash'])): ?>
  <p class="mb-6 rounded-lg px-4 py-3 text-sm font-medium <?= $_SESSION['flash']['type'] == 'error' ? 'bg-bad-100 text-bad-700' : 'bg-ok-100 text-ok-700' ?>"><?= e($_SESSION['flash']['message']) ?></p>
  <?php unset($_SESSION['flash']); ?>
<?php endif; ?>
