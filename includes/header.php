<?php
// Site header. Set these before including it:
//   $title = 'Contact us';   page title
//   $page  = 'flights';      highlights that menu item (flights, hotels, visa, tours)
if (!isset($title)) $title = '';
if (!isset($page)) $page = '';
if (!isset($description)) $description = "Mickey Travels & Tours, Port Harcourt: flights, hotels, visas, student visas, medical tourism and holidays. Your window to the world.";

$menu = ['flights.php' => 'Flights', 'hotels.php' => 'Hotels', 'visa.php' => 'Visa', 'tours.php' => 'Tours'];
?>
<!doctype html>
<html lang="en-NG">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $title ? e($title) . ' · ' . SITE_NAME : SITE_NAME . ' — Flights, hotels, visas and tours' ?></title>
<meta name="description" content="<?= e($description) ?>">
<meta name="theme-color" content="#0a1420">
<link rel="icon" type="image/png" href="assets/img/logo-96.png">
<link rel="apple-touch-icon" href="assets/img/logo-192.png">
<?php
// Search engines and link previews (WhatsApp, Facebook, X). Private pages set $noindex = true.
$canonical = SITE_URL . '/' . basename(strtok($_SERVER['REQUEST_URI'], '?'));
$private = ['booking.php', 'hotel-booking.php', 'tour-booking.php', 'account.php', 'login.php', 'register.php', 'forgot-password.php', 'reset-password.php', 'flight-book.php', 'hotel-book.php', 'visa-track.php', 'manage-booking.php', 'flight-results.php', 'hotel-results.php'];
if (in_array(basename($_SERVER['SCRIPT_NAME']), $private)) $noindex = true;
?>
<?php if (!empty($noindex)): ?><meta name="robots" content="noindex"><?php else: ?><link rel="canonical" href="<?= e($canonical . (get('slug') ? '?slug=' . urlencode(get('slug')) : (get('country') ? '?country=' . urlencode(get('country')) : ''))) ?>"><?php endif; ?>
<meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
<meta property="og:title" content="<?= e($title ?: SITE_NAME) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:image" content="<?= e(SITE_URL) ?>/assets/img/lagos-1600.webp">
<meta property="og:type" content="website">
<meta name="twitter:card" content="summary_large_image">
<?php include __DIR__ . '/analytics.php'; ?>
<?php $base = ''; include __DIR__ . '/head-assets.php'; ?>
</head>
<body class="min-h-dvh flex flex-col">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[60] btn btn-primary">Skip to content</a>

<?php if (FLIGHT_DATA === 'demo'): ?>
<div class="print:hidden bg-saffron-300 text-ink-950 text-center font-mono text-[11px] font-semibold uppercase tracking-wider py-1">Demo data · flight and hotel results are samples, not live fares</div>
<?php endif; ?>

<header class="print:hidden sticky top-0 z-50 border-b border-white/10 bg-ink-950/95 text-paper-50 backdrop-blur-sm">
  <div class="container-page flex h-16 items-center justify-between gap-4">
    <a href="index.php" class="group flex items-center gap-2.5">
      <img src="assets/img/logo-96.png" alt="" width="40" height="40" class="size-10 rounded-full transition group-hover:rotate-[-6deg]">
      <span class="leading-none">
        <span class="block font-display text-lg font-bold tracking-tight">Mickey Travels</span>
        <span class="block font-mono text-[10px] font-semibold uppercase tracking-[0.22em] text-saffron-300">&amp; Tours</span>
      </span>
    </a>

    <nav class="hidden md:flex items-center gap-1 text-sm font-medium">
      <?php foreach ($menu as $link => $label): $on = $page . '.php' == $link; ?>
        <a href="<?= $link ?>" class="relative rounded px-3 py-2 transition hover:text-saffron-300 <?= $on ? 'text-saffron-300' : 'text-paper-100' ?>">
          <?= $label ?>
          <?php if ($on): ?><span class="absolute inset-x-3 -bottom-[13px] h-0.5 rounded bg-saffron-400"></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
      <a href="manage-booking.php" class="ml-3 rounded-md px-3.5 py-2 ring-1 ring-white/20 transition hover:bg-white/10">Manage booking</a>
      <a href="<?= empty($_SESSION['user']) ? 'login.php' : 'account.php' ?>" class="ml-1 rounded-md px-3 py-2 transition hover:text-saffron-300 <?= $page == 'account' ? 'text-saffron-300' : 'text-paper-100' ?>"><?= empty($_SESSION['user']) ? 'Sign in' : 'My account' ?></a>
    </nav>

    <details class="md:hidden relative">
      <summary class="list-none rounded-md px-3 py-2 ring-1 ring-white/20 text-sm font-medium">Menu</summary>
      <nav class="absolute right-0 z-40 mt-3 w-60 rounded-xl bg-ink-900 p-2 shadow-2xl ring-1 ring-white/10">
        <?php foreach ($menu as $link => $label): ?>
          <a href="<?= $link ?>" class="block rounded-md px-3 py-3 hover:bg-ink-800"><?= $label ?></a>
        <?php endforeach; ?>
        <a href="manage-booking.php" class="block rounded-md px-3 py-3 hover:bg-ink-800">Manage booking</a>
        <a href="<?= empty($_SESSION['user']) ? 'login.php' : 'account.php' ?>" class="block rounded-md px-3 py-3 hover:bg-ink-800"><?= empty($_SESSION['user']) ? 'Sign in' : 'My account' ?></a>
      </nav>
    </details>
  </div>
</header>

<?php if (isset($_SESSION['flash'])): ?>
  <div class="container-page mt-4">
    <p class="rounded-md px-4 py-3 text-sm font-medium <?= $_SESSION['flash']['type'] == 'error' ? 'bg-bad-100 text-bad-700' : 'bg-ok-100 text-ok-700' ?>">
      <?= e($_SESSION['flash']['message']) ?>
    </p>
  </div>
  <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<main id="main" class="flex-1">
