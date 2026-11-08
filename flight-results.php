<?php
require 'includes/init.php';
require 'includes/flight-search.php';

// Check the search. The flights themselves are loaded by assets/js/results.js
// from api/flight-search.php, so this page shows straight away while the airlines answer.
$search = read_flight_search($_GET);

$title = 'Flight results';
$page = 'flights';

if (!$search['error']) {
    $first = $search['slices'][0];
    $from_city = db_one("SELECT city_name FROM airports WHERE iata_code = ?", [$first['origin']]);
    $to_city = db_one("SELECT city_name FROM airports WHERE iata_code = ?", [$first['destination']]);
    $from_city = $from_city ? $from_city['city_name'] : $first['origin'];
    $to_city = $to_city ? $to_city['city_name'] : $first['destination'];
    $title = "Flights from $from_city to $to_city";

    $dates = date('D j M', strtotime($first['departure_date']));
    if ($search['trip'] == 'return') {
        $dates .= ' – ' . date('D j M', strtotime($search['slices'][1]['departure_date']));
    } elseif ($search['trip'] == 'multi') {
        $dates = plural(count($search['slices']), 'flight') . ' · from ' . $dates;
    }
    $people = plural($search['adults'], 'adult');
    if ($search['children']) $people .= ', ' . plural($search['children'], 'child', 'children');
    if ($search['infants']) $people .= ', ' . plural($search['infants'], 'infant');
    $cabin_names = ['economy' => 'Economy', 'premium_economy' => 'Premium economy', 'business' => 'Business', 'first' => 'First'];
}

include 'includes/header.php';
?>

<!-- Search summary -->
<section class="relative isolate overflow-hidden bg-ink-950 text-paper-50">
  <div class="absolute inset-0 -z-10 opacity-20 [background-image:radial-gradient(circle_at_1px_1px,#afc0d1_1px,transparent_0)] [background-size:22px_22px]" aria-hidden="true"></div>
  <div class="container-page py-8 sm:py-10">
    <?php if ($search['error']): ?>
      <h1 class="text-2xl font-bold">Let's fix your search</h1>
      <p class="mt-1 text-ink-200"><?= e($search['error']) ?></p>
    <?php else: ?>
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p class="eyebrow text-saffron-300"><?= e($first['origin']) ?> <?= $search['trip'] == 'return' ? '⇄' : '→' ?> <?= e($first['destination']) ?> · <?= e($cabin_names[$search['cabin']]) ?></p>
          <h1 class="mt-2 flex flex-wrap items-center gap-x-4 text-3xl font-extrabold tracking-[-0.03em] sm:text-5xl">
            <?= e($from_city) ?> <?= icon('arrow-right', 'size-7 text-saffron-400') ?> <?= e($to_city) ?>
          </h1>
          <p class="mt-2 text-sm text-ink-200"><?= e($dates) ?> · <?= e($people) ?><?= $search['trip'] == 'return' ? ' · Return trip' : '' ?></p>
        </div>
        <button type="button" id="change-search" class="btn ring-1 ring-inset ring-ink-500 hover:bg-ink-800">Change search</button>
      </div>
    <?php endif; ?>
    <div id="search-again" class="mt-5" <?= $search['error'] ? '' : 'hidden' ?>>
      <?php $tab = 'flights'; include 'includes/search-box.php'; ?>
    </div>
  </div>
</section>

<?php if (!$search['error']): ?>
<section class="container-page py-6 sm:py-8">
  <noscript>
    <p class="mb-4 rounded-md bg-warn-100 px-4 py-3 text-sm text-warn-700">Please turn on JavaScript to see flight results, or <a class="underline" href="contact.php">contact us</a> and we'll find fares for you.</p>
  </noscript>

  <div class="grid gap-6 lg:grid-cols-[17rem_1fr]">

    <!-- ============ Filters ============ -->
    <aside id="filters" class="fixed inset-0 z-50 hidden overflow-y-auto bg-ink-950/50 lg:static lg:z-auto lg:block lg:overflow-visible lg:bg-transparent">
      <div class="ml-auto min-h-full w-full max-w-sm bg-paper-50 p-5 lg:sticky lg:top-4 lg:min-h-0 lg:max-w-none lg:rounded-xl lg:bg-white lg:p-5 lg:ring-1 lg:ring-paper-200">
        <div class="flex items-center justify-between">
          <p class="font-display text-lg font-bold">Filters</p>
          <button type="button" id="reset-filters" class="text-sm font-medium text-ink-700 underline underline-offset-4">Reset</button>
        </div>

        <fieldset class="mt-5">
          <legend class="field-label">Stops</legend>
          <div class="mt-2 space-y-1.5 text-sm">
            <label class="flex items-center gap-2"><input type="radio" name="stops" value="9" checked class="size-4 accent-ink-900"> Any number of stops</label>
            <label class="flex items-center gap-2"><input type="radio" name="stops" value="0" class="size-4 accent-ink-900"> Direct only</label>
            <label class="flex items-center gap-2"><input type="radio" name="stops" value="1" class="size-4 accent-ink-900"> 1 stop at most</label>
          </div>
        </fieldset>

        <fieldset class="mt-6">
          <legend class="field-label">Departure time</legend>
          <div class="mt-2 grid grid-cols-2 gap-2 text-sm">
            <?php foreach ([['0-6', 'Night', '00:00–05:59'], ['6-12', 'Morning', '06:00–11:59'], ['12-18', 'Afternoon', '12:00–17:59'], ['18-24', 'Evening', '18:00–23:59']] as $t): ?>
              <label class="cursor-pointer">
                <input type="checkbox" name="time" value="<?= $t[0] ?>" class="peer sr-only">
                <span class="block rounded-md px-3 py-2 ring-1 ring-ink-300 peer-checked:bg-ink-900 peer-checked:text-paper-50 peer-checked:ring-ink-900 peer-focus-visible:outline-3 peer-focus-visible:outline-saffron-500">
                  <b class="block"><?= $t[1] ?></b><span class="text-xs opacity-80 num"><?= $t[2] ?></span>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <fieldset class="mt-6">
          <legend class="field-label">Maximum price</legend>
          <input type="range" id="max-price" class="mt-3 w-full accent-ink-900" min="0" max="0" step="1">
          <p class="mt-1 text-sm font-semibold num" id="max-price-text"></p>
        </fieldset>

        <fieldset class="mt-6">
          <legend class="field-label">Airlines</legend>
          <div id="airline-filters" class="mt-2 space-y-1.5 text-sm"></div>
        </fieldset>

        <button type="button" id="close-filters" class="btn btn-dark mt-8 w-full lg:hidden">Show flights</button>
      </div>
    </aside>

    <!-- ============ Results ============ -->
    <div class="min-w-0">
      <!-- Sort -->
      <div class="grid grid-cols-3 overflow-hidden rounded-xl bg-white ring-1 ring-paper-200" role="group" aria-label="Sort flights">
        <button type="button" class="sort px-3 py-3 text-left aria-pressed:bg-ink-900 aria-pressed:text-paper-50" data-sort="price" aria-pressed="true">
          <span class="block text-sm font-semibold">Cheapest</span><span class="block text-xs opacity-80 num" id="sort-price">–</span>
        </button>
        <button type="button" class="sort border-x border-paper-200 px-3 py-3 text-left aria-pressed:bg-ink-900 aria-pressed:text-paper-50" data-sort="minutes" aria-pressed="false">
          <span class="block text-sm font-semibold">Fastest</span><span class="block text-xs opacity-80 num" id="sort-minutes">–</span>
        </button>
        <button type="button" class="sort px-3 py-3 text-left aria-pressed:bg-ink-900 aria-pressed:text-paper-50" data-sort="best" aria-pressed="false">
          <span class="block text-sm font-semibold">Best value</span><span class="block text-xs opacity-80">Price and time</span>
        </button>
      </div>

      <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-ink-700" id="result-count" aria-live="polite">Searching over 300 airlines…</p>
        <div class="flex items-center gap-3">
          <p class="hidden text-xs text-ink-600" id="price-timer"></p>
          <button type="button" id="open-filters" class="btn btn-ghost lg:hidden"><?= icon('filter', 'size-4') ?> Filters</button>
        </div>
      </div>

      <!-- Shown when the prices have expired -->
      <div id="expired" class="mt-4 hidden rounded-xl bg-warn-100 p-4 text-warn-700">
        <p class="font-semibold">These prices have expired</p>
        <p class="mt-1 text-sm">Airlines only hold prices for a short time. Search again to see today's fares.</p>
        <button type="button" onclick="location.reload()" class="btn btn-dark mt-3">Search again</button>
      </div>

      <!-- Error or no results -->
      <div id="message" class="mt-4 hidden rounded-xl bg-white p-6 text-center ring-1 ring-paper-200">
        <p class="font-display text-lg font-bold" id="message-title">No flights found</p>
        <p class="mt-1 text-ink-700" id="message-text"></p>
        <div class="mt-4 flex flex-wrap justify-center gap-3">
          <button type="button" class="btn btn-dark" onclick="document.getElementById('change-search').click(); window.scrollTo(0, 0)">Change search</button>
          <a class="btn btn-ghost" href="contact.php">Ask us to find fares</a>
        </div>
      </div>

      <!-- Flights go here. The grey cards show while the search runs. -->
      <div id="results" class="mt-4 space-y-3"
           data-url="api/flight-search.php?<?= e($_SERVER['QUERY_STRING']) ?>">
        <?php for ($i = 0; $i < 4; $i++): ?>
          <div class="skeleton card rounded-xl p-5">
            <div class="flex animate-pulse items-center gap-3"><div class="size-8 rounded-md bg-ink-100"></div><div class="h-4 w-40 rounded bg-ink-100"></div></div>
            <div class="mt-5 grid animate-pulse grid-cols-[4rem_1fr_4rem_8rem] items-center gap-4">
              <div class="h-7 rounded bg-ink-100"></div><div class="h-2 rounded bg-ink-100"></div><div class="h-7 rounded bg-ink-100"></div><div class="h-10 rounded bg-ink-100"></div>
            </div>
          </div>
        <?php endfor; ?>
      </div>
      <p id="no-match" class="mt-4 hidden rounded-xl bg-white p-6 text-center text-ink-700 ring-1 ring-paper-200">No flights match these filters. <button type="button" class="link" onclick="document.getElementById('reset-filters').click()">Reset filters</button></p>
    </div>
  </div>
</section>
<script src="assets/js/results.js" defer></script>
<?php endif; ?>

<script>
  // "Change search" opens the search box at the top
  var changeButton = document.getElementById('change-search');
  if (changeButton) {
    changeButton.addEventListener('click', function () {
      var box = document.getElementById('search-again');
      box.hidden = !box.hidden;
    });
  }
</script>

<?php include 'includes/footer.php'; ?>
