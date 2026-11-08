<?php
require 'includes/init.php';

$title = 'Hotels';
$description = 'Find and reserve hotels in Nigeria and abroad, with the total price in Naira before you pay.';
$page = 'hotels';
include 'includes/header.php';
?>

<section class="relative isolate overflow-hidden bg-ink-900 text-paper-50">
  <?= photo('hotel-pool', 'Hotel pool with palm trees at sunset', 'absolute inset-0 -z-20 hero-photo h-full w-full object-cover', false, [800, 1600]) ?>
  <div class="absolute inset-0 -z-10 bg-[linear-gradient(180deg,rgba(10,20,32,0.6)_0%,rgba(10,20,32,0.5)_40%,#0a1420_100%)]"></div>
  <div class="container-page pt-10 pb-12 sm:pt-20 sm:pb-20">
    <p class="eyebrow text-saffron-300">Hotels · Nigeria and abroad</p>
    <h1 class="mt-3 text-5xl font-extrabold leading-[0.95] tracking-[-0.035em] sm:text-7xl">Hotels</h1>
    <p class="mt-3 max-w-xl text-ink-100 sm:text-lg">Search by city, compare rooms and see the full price for your stay in Naira.</p>
    <div class="mt-8 sm:mt-12">
      <?php $tab = 'hotels'; include 'includes/search-box.php'; ?>
    </div>
  </div>
</section>

<section class="container-page py-10 sm:py-14 max-w-3xl">
  <h2 class="text-xl sm:text-2xl font-bold">Before you book</h2>
  <ul class="mt-4 space-y-3 text-ink-700">
    <li class="flex gap-3"><?= icon('check', 'size-5 shrink-0 text-ok-700 mt-0.5') ?> Every room shows its cancellation rules and what's included (breakfast, room only) before you pay.</li>
    <li class="flex gap-3"><?= icon('check', 'size-5 shrink-0 text-ok-700 mt-0.5') ?> The price shown is the total for your whole stay, including our service fee.</li>
    <li class="flex gap-3"><?= icon('check', 'size-5 shrink-0 text-ok-700 mt-0.5') ?> You'll get the hotel's own confirmation number by email to show at check-in.</li>
  </ul>
  <p class="mt-6 text-sm text-ink-600">Booking a hotel with your flight? <a class="link" href="contact.php">Ask us</a> and we'll put the whole trip together.</p>
</section>

<?php if (FLIGHT_DATA == 'live' && strpos(DUFFEL_TOKEN, 'duffel_test_') === 0): ?>
<!-- Only with a Duffel TEST token: test mode returns Duffel's test hotels, found at special coordinates -->
<section class="container-page pb-10">
  <div class="rounded-2xl bg-warn-100 p-5 text-warn-700">
    <p class="font-semibold">Duffel test mode</p>
    <p class="mt-1 text-sm">Real cities return no hotels in test mode. <a class="underline" href="hotel-results.php?near=TEST&check_in=<?= date('Y-m-d', strtotime('+30 days')) ?>&check_out=<?= date('Y-m-d', strtotime('+32 days')) ?>&rooms=1&adults=2">Show Duffel's test hotels</a>.</p>
  </div>
</section>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
