<?php
require 'includes/init.php';

// Our service fee, written out the way it's set in the admin settings
$fee_type = setting('markup_type', 'flat_booking');
$fee_value = setting('markup_value', 2000);
if ($fee_type == 'percent') {
    $fee_text = $fee_value . '% of the fare';
} elseif ($fee_type == 'flat_passenger') {
    $fee_text = naira($fee_value * 100) . ' per traveller';
} else {
    $fee_text = naira($fee_value * 100) . ' per booking';
}

// Popular routes, two weeks ahead and back a week later
$depart = date('Y-m-d', strtotime('+14 days'));
$return = date('Y-m-d', strtotime('+21 days'));
$routes = [
    'Local flights' => [['LOS', 'Lagos', 'ABV', 'Abuja'], ['LOS', 'Lagos', 'PHC', 'Port Harcourt'], ['ABV', 'Abuja', 'KAN', 'Kano'], ['LOS', 'Lagos', 'ENU', 'Enugu'], ['LOS', 'Lagos', 'QUO', 'Uyo'], ['ABV', 'Abuja', 'PHC', 'Port Harcourt']],
    'International flights' => [['LOS', 'Lagos', 'LHR', 'London'], ['LOS', 'Lagos', 'DXB', 'Dubai'], ['LOS', 'Lagos', 'ACC', 'Accra'], ['ABV', 'Abuja', 'LHR', 'London'], ['LOS', 'Lagos', 'JFK', 'New York'], ['LOS', 'Lagos', 'YYZ', 'Toronto']],
];

$title = 'Cheap local and international flights';
$description = 'Book local flights within Nigeria with Air Peace, Ibom Air, Arik and more, or fly abroad with over 300 airlines. Compare fares and pay in Naira.';
$page = 'flights';
include 'includes/header.php';
?>

<section class="relative isolate overflow-hidden bg-ink-900 text-paper-50">
  <?= photo('plane-wing', 'View of an aircraft wing from the window', 'absolute inset-0 -z-20 hero-photo h-full w-full object-cover', false, [800, 1600]) ?>
  <div class="absolute inset-0 -z-10 bg-[linear-gradient(180deg,rgba(10,20,32,0.6)_0%,rgba(10,20,32,0.5)_40%,#0a1420_100%)]"></div>
  <div class="container-page pt-10 pb-12 sm:pt-20 sm:pb-20">
    <p class="eyebrow text-saffron-300">Flights · 300+ airlines</p>
    <h1 class="mt-3 text-5xl font-extrabold leading-[0.95] tracking-[-0.035em] sm:text-7xl">Flights</h1>
    <p class="mt-3 max-w-xl text-ink-100 sm:text-lg">Local flights across Nigeria and international flights worldwide. Compare airlines, choose your fare and get your e-ticket by email.</p>
    <div class="mt-8 sm:mt-12">
      <?php $tab = 'flights'; include 'includes/search-box.php'; ?>
    </div>
  </div>
</section>

<section class="container-page pt-10 sm:pt-14 grid gap-8 sm:grid-cols-2">
  <?php foreach ($routes as $heading => $list): ?>
    <div>
      <h2 class="text-xl sm:text-2xl font-bold"><?= e($heading) ?></h2>
      <ul class="mt-4 divide-y divide-paper-200 rounded-lg ring-1 ring-paper-200">
        <?php foreach ($list as $r): ?>
          <li>
            <a class="flex items-center justify-between gap-4 px-4 py-3 transition hover:bg-paper-100" href="flight-results.php?trip=return&amp;from[]=<?= $r[0] ?>&amp;to[]=<?= $r[2] ?>&amp;depart[]=<?= $depart ?>&amp;return=<?= $return ?>&amp;adults=1&amp;cabin=economy">
              <span class="font-semibold"><?= e($r[1]) ?> → <?= e($r[3]) ?></span>
              <span class="flex items-center gap-2 font-mono text-xs text-ink-600"><?= $r[0] ?>–<?= $r[2] ?> <?= icon('arrow-right', 'size-4') ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endforeach; ?>
</section>

<section class="container-page py-10 sm:py-14 grid gap-10 lg:grid-cols-2">
  <div>
    <h2 class="text-xl sm:text-2xl font-bold">How booking works</h2>
    <ol class="mt-4 space-y-4">
      <li class="flex gap-4">
        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-ink-900 font-semibold text-paper-50">1</span>
        <p><b>Search and compare.</b> <span class="text-ink-700">Filter by stops, airline and departure time. Every price you see is the full price in Naira.</span></p>
      </li>
      <li class="flex gap-4">
        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-ink-900 font-semibold text-paper-50">2</span>
        <p><b>Enter traveller details.</b> <span class="text-ink-700">Names exactly as on each traveller's ID, or passport for international trips. We check the fare again with the airline before you pay.</span></p>
      </li>
      <li class="flex gap-4">
        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-ink-900 font-semibold text-paper-50">3</span>
        <p><b>Pay securely with Paystack.</b> <span class="text-ink-700">Card, bank transfer or USSD. Your ticket is issued straight after payment and emailed to you with your booking reference.</span></p>
      </li>
    </ol>
  </div>

  <div class="rounded-lg bg-paper-100 p-5 sm:p-6">
    <h2 class="text-lg font-bold">Good to know</h2>
    <dl class="mt-3 space-y-4 text-sm">
      <div>
        <dt class="font-semibold">Our service fee</dt>
        <dd class="mt-0.5 text-ink-700">We add <b class="num"><?= e($fee_text) ?></b> to the airline's fare. It's already included in every price we show, so the price you see is the price you pay.</dd>
      </div>
      <div>
        <dt class="font-semibold">Fares in foreign currency</dt>
        <dd class="mt-0.5 text-ink-700">Airlines often price in dollars, pounds or euros. We convert to Naira at our published daily rate, and the Naira total is fixed once you pay.</dd>
      </div>
      <div>
        <dt class="font-semibold">Baggage and changes</dt>
        <dd class="mt-0.5 text-ink-700">Each fare shows the airline's baggage allowance and change and refund rules before you book.</dd>
      </div>
      <div>
        <dt class="font-semibold">Need help?</dt>
        <dd class="mt-0.5 text-ink-700">For group bookings or special assistance, <a class="link" href="contact.php">send us a message</a>.</dd>
      </div>
    </dl>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
