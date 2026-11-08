<?php
// The search box, designed like a boarding pass, with Flights / Hotels / Visa / Tours tabs.
// Set $tab before including it: 'flights' (default), 'hotels', 'visa' or 'tours'.
// Each tab is a normal form. assets/js/search.js adds tab switching, airport suggestions,
// the swap button, extra multi-city flights and the travellers picker.

if (!isset($tab)) $tab = 'flights';

$today = date('Y-m-d');
$last_day = date('Y-m-d', strtotime('+330 days'));   // airlines sell up to about 11 months ahead

// Fill the form with the previous search, if there was one
$trip = in_array(get('trip'), ['one_way', 'return', 'multi']) ? get('trip') : 'return';
$from = isset($_GET['from']) ? (array) $_GET['from'] : [];
$to = isset($_GET['to']) ? (array) $_GET['to'] : [];
$depart = isset($_GET['depart']) ? (array) $_GET['depart'] : [];
$return = valid_date(get('return')) ? get('return') : date('Y-m-d', strtotime('+21 days'));
$adults = max(1, min(9, (int) (get('adults') ?: 1)));
$children = max(0, min(8, (int) get('children')));
$infants = max(0, min($adults, (int) get('infants')));
$cabins = ['economy' => 'Economy', 'premium_economy' => 'Premium economy', 'business' => 'Business', 'first' => 'First'];
$cabin = isset($cabins[get('cabin')]) ? get('cabin') : 'economy';

// City names for airport codes in the previous search, e.g. LOS -> "Lagos (LOS)"
$airport_names = [];
foreach (array_merge($from, $to) as $code) {
    if (is_string($code) && preg_match('/^[A-Z]{3}$/', $code)) {
        $a = db_one("SELECT city_name FROM airports WHERE iata_code = ?", [$code]);
        $airport_names[$code] = ($a ? $a['city_name'] : $code) . " ($code)";
    }
}

$visa_countries = db_all("SELECT slug, country_name FROM visa_destinations WHERE is_active = 1 ORDER BY sort_order");
$tour_places = db_all("SELECT DISTINCT destination FROM tour_packages WHERE is_published = 1 ORDER BY destination");

$tabs = ['flights' => 'Flights', 'hotels' => 'Hotels', 'visa' => 'Visa', 'tours' => 'Tours'];

// Shared classes: each field sits in a "cell" like the boxes on a boarding pass
$cell = 'relative rounded-lg bg-paper-50 px-3.5 pt-2.5 pb-2 ring-1 ring-ink-200 transition hover:ring-ink-400 focus-within:ring-2 focus-within:ring-saffron-500';
$cell_label = 'block font-mono text-[10px] font-semibold uppercase tracking-[0.18em] text-ink-600';
$cell_input = 'mt-0.5 block w-full border-0 bg-transparent p-0 text-base sm:text-lg font-semibold text-ink-900 placeholder:font-normal placeholder:text-ink-500 focus:outline-none focus:ring-0';
$list_class = 'airport-list absolute left-0 top-full z-40 mt-2 w-full min-w-80 overflow-hidden rounded-xl bg-white shadow-2xl ring-1 ring-ink-900/10';
$stub = 'relative flex flex-col justify-between gap-5 p-5 sm:p-6 lg:w-[17rem]';
$stub_line = '<div class="perforation-x absolute inset-x-5 top-0 h-px lg:hidden" aria-hidden="true"></div><div class="perforation absolute inset-y-5 left-0 hidden w-px lg:block" aria-hidden="true"></div>';
$barcode = '<div class="hidden lg:block" aria-hidden="true"><div class="barcode h-10 text-ink-900"></div><p class="mt-1.5 font-mono text-[10px] tracking-[0.3em] text-ink-500">MTT · ' . strtoupper(date('dMy')) . '</p></div>';
$button = 'btn btn-primary w-full py-4 text-base shadow-[0_8px_20px_-8px_rgba(227,154,38,0.8)]';
?>
<div id="search-box" class="relative text-ink-900">
  <!-- The white ticket shape with its notches (drawn behind the form) -->
  <div class="pointer-events-none absolute inset-0 drop-shadow-[0_30px_50px_rgba(5,12,22,0.45)]" aria-hidden="true">
    <div class="ticket h-full w-full bg-white"></div>
  </div>

  <div class="relative">
    <!-- Tabs -->
    <div role="tablist" class="flex gap-1 overflow-x-auto border-b border-paper-200 px-3 pt-3 sm:px-5">
      <?php foreach ($tabs as $key => $label): ?>
        <a href="<?= $key ?>.php" role="tab" data-tab="<?= $key ?>" aria-selected="<?= $tab == $key ? 'true' : 'false' ?>"
           class="relative shrink-0 rounded-t-md px-4 py-3 text-sm font-semibold text-ink-600 transition hover:text-ink-900 aria-selected:text-ink-950 after:absolute after:inset-x-3 after:-bottom-px after:h-[3px] after:rounded-full after:bg-saffron-500 after:opacity-0 aria-selected:after:opacity-100">
          <?= $label ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- ===================== Flights ===================== -->
    <form id="panel-flights" action="flight-results.php" method="get" class="lg:flex" <?= $tab == 'flights' ? '' : 'hidden' ?>>
      <div class="min-w-0 flex-1 p-4 sm:p-6">
        <div class="inline-flex rounded-full bg-paper-100 p-1 text-sm font-semibold">
          <?php foreach (['return' => 'Return', 'one_way' => 'One way', 'multi' => 'Multi-city'] as $value => $label): ?>
            <label class="relative">
              <input type="radio" name="trip" value="<?= $value ?>" <?= $trip == $value ? 'checked' : '' ?> class="peer sr-only">
              <span class="block rounded-full px-3.5 py-1.5 text-ink-600 transition peer-checked:bg-ink-900 peer-checked:text-paper-50 peer-focus-visible:outline peer-focus-visible:outline-3 peer-focus-visible:outline-saffron-500"><?= $label ?></span>
            </label>
          <?php endforeach; ?>
        </div>

        <?php
        // Up to 4 flights. Only the first shows unless "Multi-city" is chosen.
        for ($i = 0; $i < 4; $i++):
            $from_code = isset($from[$i]) ? $from[$i] : '';
            $to_code = isset($to[$i]) ? $to[$i] : '';
            $date = isset($depart[$i]) && valid_date($depart[$i]) ? $depart[$i] : date('Y-m-d', strtotime('+' . (14 + $i * 4) . ' days'));
            $show = $i == 0 || ($trip == 'multi' && ($i == 1 || $from_code != ''));
            $off = $show ? '' : 'disabled';   // disabled fields aren't sent with the form
        ?>
        <div class="flight-leg mt-4 grid grid-cols-2 gap-2.5 lg:grid-cols-[1fr_1fr_10rem_10rem]" <?= $show ? '' : 'hidden' ?>>
          <?php if ($i > 0): ?>
            <p class="col-span-2 lg:col-span-4 eyebrow text-saffron-700">Flight <?= $i + 1 ?></p>
          <?php endif; ?>

          <div class="<?= $cell ?> col-span-2 sm:col-span-1">
            <label class="<?= $cell_label ?>" for="from-<?= $i ?>">From</label>
            <input id="from-<?= $i ?>" class="airport-input <?= $cell_input ?>" type="text" placeholder="City or airport" autocomplete="off" required
                   value="<?= e(isset($airport_names[$from_code]) ? $airport_names[$from_code] : '') ?>" <?= $off ?>>
            <input type="hidden" name="from[]" value="<?= e($from_code) ?>" <?= $off ?>>
            <ul class="<?= $list_class ?>" hidden></ul>
            <button type="button" class="swap absolute -bottom-4 right-6 z-10 flex size-9 items-center justify-center rounded-full bg-white text-ink-800 shadow-md ring-1 ring-ink-200 transition hover:rotate-180 hover:bg-saffron-300 sm:-right-[1.4rem] sm:bottom-auto sm:top-1/2 sm:-translate-y-1/2" aria-label="Swap from and to">
              <?= icon('swap', 'size-4') ?>
            </button>
          </div>

          <div class="<?= $cell ?> col-span-2 sm:col-span-1 sm:pl-6">
            <label class="<?= $cell_label ?>" for="to-<?= $i ?>">To</label>
            <input id="to-<?= $i ?>" class="airport-input <?= $cell_input ?>" type="text" placeholder="City or airport" autocomplete="off" required
                   value="<?= e(isset($airport_names[$to_code]) ? $airport_names[$to_code] : '') ?>" <?= $off ?>>
            <input type="hidden" name="to[]" value="<?= e($to_code) ?>" <?= $off ?>>
            <ul class="<?= $list_class ?>" hidden></ul>
          </div>

          <div class="<?= $cell ?>">
            <label class="<?= $cell_label ?>" for="depart-<?= $i ?>"><?= $i == 0 ? 'Depart' : 'Date' ?></label>
            <input id="depart-<?= $i ?>" class="<?= $cell_input ?> num" type="date" name="depart[]" min="<?= $today ?>" max="<?= $last_day ?>" value="<?= e($date) ?>" required <?= $off ?>>
          </div>

          <?php if ($i == 0): ?>
          <div id="return-box" class="<?= $cell ?>" <?= $trip == 'return' ? '' : 'hidden' ?>>
            <label class="<?= $cell_label ?>" for="return">Return</label>
            <input id="return" class="<?= $cell_input ?> num" type="date" name="return" min="<?= $today ?>" max="<?= $last_day ?>" value="<?= e($return) ?>" <?= $trip == 'return' ? 'required' : 'disabled' ?>>
          </div>
          <?php endif; ?>
        </div>
        <?php endfor; ?>

        <p class="mt-3" id="add-flight-box" <?= $trip == 'multi' ? '' : 'hidden' ?>>
          <button type="button" id="add-flight" class="link text-sm py-1">+ Add another flight</button>
        </p>

        <!-- Quick picks: tapping one fills in From and To -->
        <div class="mt-5 flex flex-wrap items-center gap-2" id="quick-picks">
          <span class="font-mono text-[10px] font-semibold uppercase tracking-[0.18em] text-ink-500">Try</span>
          <?php foreach ([['LOS', 'Lagos', 'ABV', 'Abuja'], ['LOS', 'Lagos', 'PHC', 'Port Harcourt'], ['ABV', 'Abuja', 'KAN', 'Kano'], ['LOS', 'Lagos', 'LHR', 'London'], ['LOS', 'Lagos', 'DXB', 'Dubai'], ['LOS', 'Lagos', 'ACC', 'Accra']] as $q): ?>
            <button type="button" class="quick-pick rounded-full bg-paper-100 px-3 py-1.5 text-xs font-semibold text-ink-800 ring-1 ring-paper-200 transition hover:bg-ink-900 hover:text-paper-50"
                    data-from="<?= $q[0] ?>" data-from-city="<?= $q[1] ?>" data-to="<?= $q[2] ?>" data-to-city="<?= $q[3] ?>">
              <?= $q[1] ?> → <?= $q[3] ?>
            </button>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Ticket stub -->
      <div class="<?= $stub ?>">
        <?= $stub_line ?>
        <details class="relative" id="travellers">
          <summary class="list-none <?= $cell ?>">
            <span class="<?= $cell_label ?>">Travellers · Cabin</span>
            <span class="mt-0.5 flex items-center justify-between font-semibold">
              <span id="travellers-text"><?= plural($adults + $children + $infants, 'traveller') ?>, <?= $cabins[$cabin] ?></span>
              <span class="text-ink-500" aria-hidden="true">▾</span>
            </span>
          </summary>
          <div class="absolute right-0 top-full z-40 mt-2 w-full min-w-72 space-y-3 rounded-xl bg-white p-4 shadow-2xl ring-1 ring-ink-900/10">
            <?php
            $people = [
                ['adults', 'Adults', '12 years and over', $adults, 1, 9],
                ['children', 'Children', '2 to 11 years', $children, 0, 8],
                ['infants', 'Infants', "Under 2, on an adult's lap", $infants, 0, 9],
            ];
            foreach ($people as $p): ?>
            <div class="flex items-center justify-between gap-3">
              <label for="<?= $p[0] ?>" class="text-sm"><b><?= $p[1] ?></b><br><span class="text-ink-600"><?= $p[2] ?></span></label>
              <div class="flex items-center gap-1">
                <button type="button" class="step size-11 rounded-full ring-1 ring-ink-300 text-lg font-semibold hover:bg-ink-100" data-add="-1" aria-label="Fewer <?= strtolower($p[1]) ?>">−</button>
                <input id="<?= $p[0] ?>" name="<?= $p[0] ?>" type="number" min="<?= $p[4] ?>" max="<?= $p[5] ?>" value="<?= $p[3] ?>"
                       class="w-10 border-0 bg-transparent text-center font-semibold num [appearance:textfield]">
                <button type="button" class="step size-11 rounded-full ring-1 ring-ink-300 text-lg font-semibold hover:bg-ink-100" data-add="1" aria-label="More <?= strtolower($p[1]) ?>">+</button>
              </div>
            </div>
            <?php endforeach; ?>
            <div>
              <label class="field-label" for="cabin">Cabin</label>
              <select id="cabin" name="cabin" class="field">
                <?php foreach ($cabins as $value => $label): ?>
                  <option value="<?= $value ?>" <?= $cabin == $value ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <p class="text-xs text-ink-600">Each infant travels on an adult's lap, so you can't have more infants than adults.</p>
          </div>
        </details>
        <button type="submit" class="<?= $button ?>">Search flights <?= icon('arrow-right', 'size-5') ?></button>
        <?= $barcode ?>
      </div>
    </form>

    <!-- ===================== Hotels ===================== -->
    <form id="panel-hotels" action="hotel-results.php" method="get" class="lg:flex" <?= $tab == 'hotels' ? '' : 'hidden' ?>>
      <div class="min-w-0 flex-1 p-4 sm:p-6">
        <div class="grid grid-cols-2 gap-2.5 lg:grid-cols-[1fr_10rem_10rem]">
          <div class="<?= $cell ?> col-span-2 lg:col-span-1">
            <label class="<?= $cell_label ?>" for="hotel-city">Where are you staying?</label>
            <input id="hotel-city" class="airport-input <?= $cell_input ?>" type="text" placeholder="City, e.g. Dubai or Abuja" autocomplete="off" required>
            <input type="hidden" name="near">
            <ul class="<?= $list_class ?>" hidden></ul>
          </div>
          <div class="<?= $cell ?>">
            <label class="<?= $cell_label ?>" for="check-in">Check-in</label>
            <input id="check-in" class="<?= $cell_input ?> num" type="date" name="check_in" min="<?= $today ?>" max="<?= $last_day ?>" value="<?= date('Y-m-d', strtotime('+14 days')) ?>" required>
          </div>
          <div class="<?= $cell ?>">
            <label class="<?= $cell_label ?>" for="check-out">Check-out</label>
            <input id="check-out" class="<?= $cell_input ?> num" type="date" name="check_out" min="<?= $today ?>" max="<?= $last_day ?>" value="<?= date('Y-m-d', strtotime('+17 days')) ?>" required>
          </div>
        </div>
        <div class="mt-2.5 grid grid-cols-3 gap-2.5 sm:max-w-md">
          <div class="<?= $cell ?>"><label class="<?= $cell_label ?>" for="rooms">Rooms</label><input id="rooms" class="<?= $cell_input ?> num" type="number" name="rooms" min="1" max="8" value="1"></div>
          <div class="<?= $cell ?>"><label class="<?= $cell_label ?>" for="hotel-adults">Adults</label><input id="hotel-adults" class="<?= $cell_input ?> num" type="number" name="adults" min="1" max="16" value="2"></div>
          <div class="<?= $cell ?>"><label class="<?= $cell_label ?>" for="hotel-children">Children</label><input id="hotel-children" class="<?= $cell_input ?> num" type="number" name="children" min="0" max="8" value="0"></div>
        </div>
      </div>
      <div class="<?= $stub ?>">
        <?= $stub_line ?>
        <p class="text-sm text-ink-700">The full price for your stay, in Naira, before you pay.</p>
        <button type="submit" class="<?= $button ?>">Search hotels <?= icon('arrow-right', 'size-5') ?></button>
        <?= $barcode ?>
      </div>
    </form>

    <!-- ===================== Visa ===================== -->
    <form id="panel-visa" action="visa.php" method="get" class="lg:flex" <?= $tab == 'visa' ? '' : 'hidden' ?>>
      <div class="min-w-0 flex-1 p-4 sm:p-6">
        <p class="text-sm text-ink-700">Tell us where you're going. We'll show you the documents you need and help you apply.</p>
        <div class="<?= $cell ?> mt-3">
          <label class="<?= $cell_label ?>" for="visa-country">Destination country</label>
          <select id="visa-country" name="country" class="<?= $cell_input ?>" required>
            <option value="">Choose a country</option>
            <?php foreach ($visa_countries as $c): ?>
              <option value="<?= e($c['slug']) ?>"><?= e($c['country_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <p class="mt-3 text-sm"><a class="link" href="visa-track.php">Already applied? Track your application</a></p>
      </div>
      <div class="<?= $stub ?>">
        <?= $stub_line ?>
        <p class="text-sm text-ink-700">Document check, forms, appointment and interview preparation.</p>
        <button type="submit" class="<?= $button ?>">See requirements <?= icon('arrow-right', 'size-5') ?></button>
        <?= $barcode ?>
      </div>
    </form>

    <!-- ===================== Tours ===================== -->
    <form id="panel-tours" action="tours.php" method="get" class="lg:flex" <?= $tab == 'tours' ? '' : 'hidden' ?>>
      <div class="min-w-0 flex-1 p-4 sm:p-6">
        <p class="text-sm text-ink-700">Holidays we've planned end to end: flights, hotel, transfers and a local guide.</p>
        <div class="mt-3 grid gap-2.5 sm:grid-cols-2">
          <div class="<?= $cell ?>">
            <label class="<?= $cell_label ?>" for="tour-place">Destination</label>
            <select id="tour-place" name="destination" class="<?= $cell_input ?>">
              <option value="">Anywhere</option>
              <?php foreach ($tour_places as $t): ?>
                <option value="<?= e($t['destination']) ?>"><?= e($t['destination']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="<?= $cell ?>">
            <label class="<?= $cell_label ?>" for="tour-month">Travelling in</label>
            <select id="tour-month" name="month" class="<?= $cell_input ?>">
              <option value="">Any month</option>
              <?php for ($m = 0; $m < 12; $m++): $month = strtotime(date('Y-m-01') . " +$m months"); ?>
                <option value="<?= date('Y-m', $month) ?>"><?= date('F Y', $month) ?></option>
              <?php endfor; ?>
            </select>
          </div>
        </div>
      </div>
      <div class="<?= $stub ?>">
        <?= $stub_line ?>
        <p class="text-sm text-ink-700">Set dates, small groups, and a deposit secures your place.</p>
        <button type="submit" class="<?= $button ?>">Find holidays <?= icon('arrow-right', 'size-5') ?></button>
        <?= $barcode ?>
      </div>
    </form>
  </div>
</div>
<script src="assets/js/search.js" defer></script>
