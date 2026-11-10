<?php
require 'includes/init.php';

// Search links go two weeks ahead, returning a week later
$route_depart = date('Y-m-d', strtotime('+14 days'));
$route_return = date('Y-m-d', strtotime('+21 days'));

function route_link($from, $to)
{
    global $route_depart, $route_return;
    return "flight-results.php?trip=return&from[]=$from&to[]=$to&depart[]=$route_depart&return=$route_return&adults=1&cabin=economy";
}

// Straight-line distance between two airports in km, worked out from their coordinates
function airport_km($a, $b)
{
    $p = db_one("SELECT latitude, longitude FROM airports WHERE iata_code = ?", [$a]);
    $q = db_one("SELECT latitude, longitude FROM airports WHERE iata_code = ?", [$b]);
    $lat1 = deg2rad($p['latitude']);
    $lat2 = deg2rad($q['latitude']);
    $h = sin(($lat2 - $lat1) / 2) ** 2 + cos($lat1) * cos($lat2) * sin(deg2rad($q['longitude'] - $p['longitude']) / 2) ** 2;
    return round(6371 * 2 * asin(sqrt($h)));
}

// Departures board, local routes first
$board = [
    'Within Nigeria' => [['ABV', 'Abuja'], ['PHC', 'Port Harcourt'], ['KAN', 'Kano'], ['ENU', 'Enugu']],
    'International' => [['LHR', 'London'], ['DXB', 'Dubai'], ['ACC', 'Accra'], ['JFK', 'New York']],
];

// Destination features (photos in assets/img/)
$destinations = [
    ['London', 'United Kingdom', 'LHR', 'london', 'Tower Bridge in London at sunset', 'Family visits, study and the West End.'],
    ['Dubai', 'United Arab Emirates', 'DXB', 'dubai', 'Burj Khalifa and the Dubai skyline', 'Shopping, sunshine and an evening in the desert.'],
    ['Accra', 'Ghana', 'ACC', 'accra', 'Kwame Nkrumah Memorial Park in Accra', 'An hour away. No visa needed for Nigerians.'],
    ['New York', 'United States', 'JFK', 'new-york', 'Manhattan skyline across the Hudson River', 'Business, family and the city that never stops.'],
    ['Toronto', 'Canada', 'YYZ', 'toronto', 'Toronto skyline and the CN Tower', 'Visiting family, school and new beginnings.'],
];

$visas = db_all("SELECT * FROM visa_destinations WHERE is_active = 1 ORDER BY sort_order LIMIT 6");

$tours = db_all("
    SELECT p.*, MIN(d.start_date) AS next_date
    FROM tour_packages p
    LEFT JOIN tour_departures d ON d.package_id = p.id AND d.status = 'open' AND d.start_date >= CURDATE()
    WHERE p.is_published = 1 AND p.is_featured = 1
    GROUP BY p.id
    ORDER BY p.sort_order
    LIMIT 3
");

$whatsapp = preg_replace('/\D/', '', setting('contact_whatsapp'));

$title = '';
$page = 'home';
include 'includes/header.php';
?>

<!-- =============================== HERO =============================== -->
<section class="relative isolate overflow-hidden bg-ink-950 text-paper-50">
  <div class="absolute inset-0 -z-20 overflow-hidden">
    <?= photo('lagos', 'Aerial view of the Lagos lagoon, a bridge and the city skyline', 'hero-photo h-full w-full object-cover', false, [800, 1600]) ?>
  </div>
  <div class="absolute inset-0 -z-10 bg-[linear-gradient(180deg,rgba(10,20,32,0.55)_0%,rgba(10,20,32,0.35)_35%,rgba(10,20,32,0.92)_78%,#0a1420_100%)]"></div>
  <div class="absolute inset-0 -z-10 bg-ink-950/45 sm:hidden"></div>

  <div class="container-page pt-10 pb-12 sm:pt-20 sm:pb-20 lg:pt-28">
    <div class="flex flex-wrap items-end justify-between gap-8">
      <div class="max-w-4xl">
        <p class="eyebrow text-saffron-300">Port Harcourt · 4.82° N, 7.03° E</p>
        <h1 class="mt-4 text-[2.6rem] font-extrabold leading-[0.95] tracking-[-0.035em] sm:text-7xl lg:text-[6.5rem]">
          Fly anywhere.<br><span class="text-saffron-400">Pay in Naira.</span>
        </h1>
        <p class="mt-5 max-w-xl text-base text-ink-100 sm:text-xl sm:leading-relaxed">Flights from over 300 airlines, hotels, visas and holidays, booked in one place by a Port Harcourt travel agency that picks up the phone.</p>
      </div>
      <ul class="hidden space-y-3 border-l border-white/20 pl-5 font-mono text-xs uppercase tracking-[0.16em] text-ink-200 lg:block">
        <li><span class="text-saffron-300">01</span> · 300+ airlines</li>
        <li><span class="text-saffron-300">02</span> · Card, transfer or USSD</li>
        <li><span class="text-saffron-300">03</span> · E-ticket by email</li>
        <li><span class="text-saffron-300">04</span> · People on WhatsApp</li>
      </ul>
    </div>

    <div class="mt-8 sm:mt-12">
      <?php $tab = 'flights'; include 'includes/search-box.php'; ?>
    </div>
  </div>
</section>

<!-- =============================== DEPARTURES BOARD =============================== -->
<section class="bg-ink-950 pb-16 text-paper-50 sm:pb-24">
  <div class="container-page">
    <div id="departures" class="reveal overflow-hidden rounded-2xl bg-[#0d1a2b] ring-1 ring-white/10">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-5 py-4 sm:px-7">
        <div class="flex items-center gap-3">
          <span class="flex size-9 items-center justify-center rounded-lg bg-saffron-400 text-ink-950"><?= icon('plane', 'size-5') ?></span>
          <div>
            <h2 class="font-mono text-sm font-semibold uppercase tracking-[0.25em] text-saffron-300">Departures</h2>
            <p class="text-xs text-ink-300">Popular routes from Lagos, local and international. Tap one for today's fares.</p>
          </div>
        </div>
        <p class="font-mono text-xs uppercase tracking-[0.2em] text-ink-300">Nigeria time <span id="lagos-time" class="ml-1 text-lg font-semibold text-paper-50 num"><?= date('H:i') ?></span></p>
      </div>

      <div class="hidden grid-cols-[1fr_6rem_9rem_9rem] gap-4 px-7 pt-4 font-mono text-[11px] uppercase tracking-[0.2em] text-ink-400 md:grid">
        <span>Destination</span><span>Code</span><span>Distance</span><span class="text-right">Remarks</span>
      </div>
      <ul class="divide-y divide-white/5 px-2 pb-2 sm:px-3">
        <?php foreach ($board as $group => $rows): ?>
          <li class="px-3 pt-5 pb-2 font-mono text-[11px] uppercase tracking-[0.2em] text-ink-400 sm:px-4"><?= e($group) ?></li>
        <?php foreach ($rows as $row): ?>
          <li>
            <a href="<?= e(route_link('LOS', $row[0])) ?>" class="group grid grid-cols-[1fr_auto] items-center gap-4 rounded-lg px-3 py-3 font-mono text-base font-semibold uppercase text-saffron-300 transition hover:bg-white/5 sm:px-4 md:grid-cols-[1fr_6rem_9rem_9rem] md:text-lg">
              <span data-flap class="truncate"><?= e($row[1]) ?></span>
              <span data-flap class="hidden md:block text-paper-50"><?= $row[0] ?></span>
              <span data-flap class="hidden md:block text-ink-200 num"><?= number_format(airport_km('LOS', $row[0])) ?> KM</span>
              <span class="flex items-center justify-end gap-2 text-xs tracking-[0.15em] text-paper-50">
                <span class="hidden sm:inline">See fares</span>
                <span class="flex size-8 items-center justify-center rounded-full bg-white/10 transition group-hover:translate-x-1 group-hover:bg-saffron-400 group-hover:text-ink-950"><?= icon('arrow-right', 'size-4') ?></span>
              </span>
            </a>
          </li>
        <?php endforeach; ?>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</section>

<!-- =============================== DESTINATIONS =============================== -->
<section class="container-page py-16 sm:py-24">
  <div class="reveal grid gap-6 lg:grid-cols-[1fr_auto] lg:items-end">
    <div>
      <p class="eyebrow text-saffron-700">Where Nigerians are flying</p>
      <h2 class="mt-3 max-w-2xl text-4xl font-extrabold leading-[1] sm:text-6xl">Five cities worth the flight.</h2>
    </div>
    <p class="max-w-sm text-ink-700">Return trips with one adult in economy. Tap a city and we'll show you today's fares.</p>
  </div>

  <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-12">
    <?php foreach ($destinations as $n => $d):
        $span = ['lg:col-span-7 lg:h-[34rem]', 'lg:col-span-5 lg:h-[34rem]', 'lg:col-span-4 lg:h-[26rem]', 'lg:col-span-4 lg:h-[26rem]', 'lg:col-span-4 lg:h-[26rem]'][$n];
    ?>
      <a href="<?= e(route_link('LOS', $d[2])) ?>" style="--delay: <?= $n * 80 ?>ms"
         class="reveal group relative isolate flex h-80 overflow-hidden rounded-2xl bg-ink-800 <?= $n == 0 ? 'sm:col-span-2' : '' ?> <?= $span ?>">
        <?= photo($d[3], $d[4], 'absolute inset-0 -z-20 h-full w-full object-cover transition duration-700 ease-out group-hover:scale-105', true, [480, 800], $n < 2 ? '(min-width: 1024px) 55vw, 100vw' : '(min-width: 1024px) 33vw, 50vw') ?>
        <div class="absolute inset-0 -z-10 bg-gradient-to-t from-ink-950/95 via-ink-950/25 to-ink-950/10"></div>
        <span class="absolute left-5 top-5 font-mono text-xs font-semibold tracking-[0.2em] text-paper-50/80">0<?= $n + 1 ?></span>
        <span class="absolute right-5 top-5 rounded-full bg-ink-950/60 px-3 py-1 font-mono text-[11px] font-semibold tracking-[0.15em] text-paper-50 backdrop-blur-sm">LOS → <?= $d[2] ?></span>
        <div class="mt-auto w-full p-5 sm:p-7 text-paper-50">
          <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-saffron-300"><?= e($d[1]) ?></p>
          <p class="mt-1 font-display font-extrabold leading-[0.95] tracking-[-0.03em] <?= $n < 2 ? 'text-5xl sm:text-6xl' : 'text-4xl' ?>"><?= e($d[0]) ?></p>
          <p class="mt-3 max-w-sm text-sm text-ink-100"><?= e($d[5]) ?></p>
          <p class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-saffron-300">See fares <?= icon('arrow-right', 'size-4 transition group-hover:translate-x-1') ?></p>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<!-- =============================== VISA =============================== -->
<section class="relative overflow-hidden bg-paper-100">
  <div class="container-page grid gap-12 py-16 sm:py-24 lg:grid-cols-[1fr_1.1fr] lg:items-center">
    <!-- Photo with passport stamps -->
    <div class="reveal relative mx-auto w-full max-w-lg lg:mx-0">
      <div class="rotate-[-2deg] overflow-hidden rounded-2xl shadow-2xl ring-8 ring-white">
        <?= photo('passport', 'A passport, camera and map laid out on a table', 'aspect-[4/5] w-full object-cover', true, [480, 800], '(min-width: 1024px) 40vw, 100vw') ?>
      </div>
      <svg class="absolute -right-4 -top-6 size-36 rotate-[14deg] text-bad-700 sm:-right-8 sm:size-44" viewBox="0 0 200 200" aria-hidden="true">
        <defs><path id="stamp-ring" d="M100,100 m-72,0 a72,72 0 1,1 144,0 a72,72 0 1,1 -144,0"/></defs>
        <circle cx="100" cy="100" r="92" fill="none" stroke="currentColor" stroke-width="5"/>
        <circle cx="100" cy="100" r="58" fill="none" stroke="currentColor" stroke-width="2"/>
        <text font-family="IBM Plex Mono, monospace" font-size="15" font-weight="600" letter-spacing="4" fill="currentColor"><textPath href="#stamp-ring">MICKEY'S VISA DESK · LAGOS · NIGERIA ·</textPath></text>
        <text x="100" y="96" text-anchor="middle" font-family="IBM Plex Mono, monospace" font-size="20" font-weight="600" fill="currentColor">CHECKED</text>
        <text x="100" y="118" text-anchor="middle" font-family="IBM Plex Mono, monospace" font-size="12" fill="currentColor"><?= strtoupper(date('d M Y')) ?></text>
      </svg>
      <div class="absolute -bottom-6 left-4 right-4 rounded-xl bg-ink-950 p-4 text-paper-50 shadow-2xl sm:left-auto sm:right-[-1.5rem] sm:w-72">
        <p class="font-mono text-[10px] uppercase tracking-[0.2em] text-ink-300">Track your application</p>
        <p class="mt-1 font-mono text-lg font-semibold tracking-wider text-saffron-300">MTV-XXXXXXXX</p>
        <p class="mt-1 text-xs text-ink-200">Every step shows online with your reference number and email.</p>
      </div>
    </div>

    <div class="reveal">
      <p class="eyebrow text-saffron-700">Visa services</p>
      <h2 class="mt-3 text-4xl font-extrabold leading-[1] sm:text-5xl">Get the application right the first time.</h2>
      <p class="mt-4 max-w-xl text-lg text-ink-700 leading-relaxed">A refused visa costs time and money. We check every document before the embassy sees it.</p>

      <ol class="mt-8 grid gap-x-6 gap-y-5 sm:grid-cols-2">
        <?php foreach ([
            ['Check', 'We tell you exactly what your destination asks for.'],
            ['Prepare', 'We review your documents and fill the forms with you.'],
            ['Appointment', 'We book it and prepare you for the interview.'],
            ['Track', 'Follow every step online until you have your decision.'],
        ] as $k => $step): ?>
          <li class="border-t-2 border-ink-900 pt-3">
            <p class="font-mono text-xs font-semibold text-saffron-700">0<?= $k + 1 ?></p>
            <p class="mt-1 font-display text-xl font-bold"><?= $step[0] ?></p>
            <p class="mt-1 text-sm text-ink-700"><?= $step[1] ?></p>
          </li>
        <?php endforeach; ?>
      </ol>

      <div class="mt-8 flex flex-wrap gap-2">
        <?php foreach ($visas as $visa): ?>
          <a href="visa.php?country=<?= e($visa['slug']) ?>" class="rounded-full bg-white px-4 py-2 text-sm font-semibold ring-1 ring-paper-300 transition hover:bg-ink-900 hover:text-paper-50 hover:ring-ink-900">
            <?= e($visa['country_name']) ?> <span class="font-normal opacity-70 num">· from <?= naira($visa['service_fee_kobo']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
      <p class="mt-3 text-xs text-ink-600">The embassy makes the final decision. Our fee is for our service and doesn't include the embassy's visa fee.</p>
      <div class="mt-6 flex flex-wrap gap-3">
        <a class="btn btn-dark px-5 py-3" href="visa.php">Start a visa application</a>
        <a class="btn btn-ghost px-5 py-3" href="visa-track.php">Track an application</a>
      </div>
    </div>
  </div>
</section>

<!-- =============================== TOURS =============================== -->
<section class="container-page py-16 sm:py-24">
  <div class="reveal flex flex-wrap items-end justify-between gap-4">
    <div>
      <p class="eyebrow text-saffron-700">Tours and holidays</p>
      <h2 class="mt-3 max-w-2xl text-4xl font-extrabold leading-[1] sm:text-6xl">Holidays we've already planned.</h2>
    </div>
    <a class="btn btn-ghost px-5 py-3" href="tours.php">See all holidays <?= icon('arrow-right', 'size-4') ?></a>
  </div>

  <div class="mt-10 grid gap-5 lg:grid-cols-2">
    <?php foreach ($tours as $n => $tour): $big = $n == 0; ?>
      <a href="tour.php?slug=<?= e($tour['slug']) ?>" style="--delay: <?= $n * 100 ?>ms"
         class="reveal group relative isolate flex overflow-hidden rounded-2xl bg-ink-900 text-paper-50 <?= $big ? 'min-h-[30rem] lg:row-span-2' : 'min-h-[18rem]' ?>">
        <?php if ($tour['cover_image']): ?>
          <?= photo($tour['cover_image'], $tour['destination'], 'absolute inset-0 -z-20 h-full w-full object-cover transition duration-700 group-hover:scale-105', true, [480, 800], '(min-width: 1024px) 50vw, 100vw') ?>
        <?php endif; ?>
        <div class="absolute inset-0 -z-10 bg-gradient-to-t from-ink-950 via-ink-950/40 to-transparent"></div>
        <div class="mt-auto flex w-full flex-wrap items-end justify-between gap-4 p-6 sm:p-8">
          <div class="max-w-md">
            <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-saffron-300"><?= e($tour['destination']) ?> · <?= plural($tour['duration_nights'], 'night') ?></p>
            <h3 class="mt-2 font-extrabold leading-[1] <?= $big ? 'text-4xl sm:text-5xl' : 'text-3xl' ?>"><?= e($tour['title']) ?></h3>
            <?php if ($big): ?><p class="mt-3 text-ink-100"><?= e($tour['summary']) ?></p><?php endif; ?>
            <?php if ($tour['next_date']): ?>
              <p class="mt-3 text-xs text-ink-200">Next departure <b class="text-paper-50 num"><?= nice_date($tour['next_date']) ?></b></p>
            <?php endif; ?>
          </div>
          <div class="rounded-xl bg-paper-50 px-4 py-3 text-ink-950">
            <p class="font-display text-2xl font-bold leading-none num"><?= naira($tour['price_kobo']) ?></p>
            <p class="mt-1 text-[11px] text-ink-600"><?= e($tour['price_basis']) ?></p>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<!-- =============================== TALK TO US =============================== -->
<section class="container-page">
  <div class="reveal relative isolate overflow-hidden rounded-3xl bg-ink-950 text-paper-50">
    <?= photo('lekki-bridge', 'The Lekki-Ikoyi Link Bridge in Lagos', 'absolute inset-0 -z-20 h-full w-full object-cover object-[50%_30%]', true, [480, 800], '100vw') ?>
    <div class="absolute inset-0 -z-10 bg-gradient-to-r from-ink-950 via-ink-950/85 to-ink-950/30"></div>
    <div class="max-w-2xl p-8 sm:p-14 lg:p-20">
      <p class="eyebrow text-saffron-300">Real people, in Port Harcourt</p>
      <h2 class="mt-3 text-4xl font-extrabold leading-[1] sm:text-6xl">Rather talk it through?</h2>
      <p class="mt-4 text-lg text-ink-100 leading-relaxed">Group bookings, special assistance, a trip with several stops, or you just want a second opinion. Send us a message and one of our staff will get back to you.</p>
      <div class="mt-8 flex flex-wrap gap-3">
        <?php if ($whatsapp): ?>
          <a class="btn btn-primary px-6 py-3.5 text-base" href="https://wa.me/<?= $whatsapp ?>" target="_blank" rel="noopener"><?= icon('chat') ?> Chat on WhatsApp</a>
        <?php endif; ?>
        <a class="btn btn-primary px-6 py-3.5 text-base" href="contact.php">Send us a message</a>
        <a class="btn px-6 py-3.5 text-base ring-1 ring-inset ring-white/30 hover:bg-white/10" href="about.php">About us</a>
      </div>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
