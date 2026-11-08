<?php
require 'includes/init.php';
require 'includes/stays.php';
require 'includes/hotel-search.php';

$search = read_hotel_search($_GET);
$title = $search['error'] ? 'Hotels' : 'Hotels in ' . $search['city'];
$page = 'hotels';
include 'includes/header.php';
?>
<section class="relative isolate overflow-hidden bg-ink-950 text-paper-50">
  <div class="absolute inset-0 -z-10 opacity-20 [background-image:radial-gradient(circle_at_1px_1px,#afc0d1_1px,transparent_0)] [background-size:22px_22px]" aria-hidden="true"></div>
  <div class="container-page py-8 sm:py-10">
    <?php if ($search['error']): ?>
      <h1 class="text-3xl font-extrabold">Let's fix your search</h1>
      <p class="mt-1 text-ink-200"><?= e($search['error']) ?></p>
    <?php else: ?>
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p class="eyebrow text-saffron-300">Hotels · <?= plural($search['nights'], 'night') ?></p>
          <h1 class="mt-2 text-3xl font-extrabold tracking-[-0.03em] sm:text-5xl"><?= e($search['city']) ?></h1>
          <p class="mt-2 text-sm text-ink-200"><?= date('D j M', strtotime($search['check_in'])) ?> – <?= date('D j M Y', strtotime($search['check_out'])) ?> · <?= plural($search['rooms'], 'room') ?> · <?= plural($search['adults'], 'adult') ?><?= $search['children'] ? ', ' . plural($search['children'], 'child', 'children') : '' ?></p>
        </div>
        <button type="button" onclick="var b=document.getElementById('search-again'); b.hidden=!b.hidden" class="btn ring-1 ring-inset ring-ink-500 hover:bg-ink-800">Change search</button>
      </div>
    <?php endif; ?>
    <div id="search-again" class="mt-6" <?= $search['error'] ? '' : 'hidden' ?>>
      <?php $tab = 'hotels'; include 'includes/search-box.php'; ?>
    </div>
  </div>
</section>

<?php if (!$search['error']): ?>
<section class="container-page py-6 sm:py-8">
  <div class="grid gap-6 lg:grid-cols-[16rem_1fr]">
    <aside class="h-fit rounded-xl bg-white p-5 ring-1 ring-paper-200 lg:sticky lg:top-24" id="hotel-filters">
      <div class="flex items-center justify-between"><p class="font-display text-lg font-bold">Filters</p><button type="button" id="reset" class="text-sm underline underline-offset-4">Reset</button></div>
      <fieldset class="mt-5">
        <legend class="field-label">Stars</legend>
        <div class="mt-2 flex flex-wrap gap-2">
          <?php foreach ([5, 4, 3] as $s): ?>
            <label class="cursor-pointer"><input type="checkbox" name="stars" value="<?= $s ?>" class="peer sr-only">
              <span class="block rounded-md px-3 py-2 text-sm font-semibold ring-1 ring-ink-300 peer-checked:bg-ink-900 peer-checked:text-paper-50 peer-checked:ring-ink-900 peer-focus-visible:outline peer-focus-visible:outline-3 peer-focus-visible:outline-saffron-500"><?= $s ?> ★</span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <fieldset class="mt-6">
        <legend class="field-label">Maximum price for the stay</legend>
        <input type="range" id="max-price" class="mt-3 w-full accent-ink-900">
        <p class="mt-1 text-sm font-semibold num" id="max-price-text"></p>
      </fieldset>
    </aside>

    <div class="min-w-0">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-ink-700" id="count" aria-live="polite">Looking for hotels…</p>
        <label class="flex items-center gap-2 text-sm">Sort by
          <select id="sort" class="rounded-md border-0 bg-white px-3 py-2 ring-1 ring-ink-300">
            <option value="price">Lowest price</option><option value="score">Guest rating</option><option value="stars">Stars</option>
          </select>
        </label>
      </div>
      <div id="message" class="mt-4 hidden rounded-xl bg-white p-8 text-center ring-1 ring-paper-200">
        <p class="font-display text-lg font-bold" id="message-title"></p><p class="mt-1 text-ink-700" id="message-text"></p>
        <a class="btn btn-dark mt-4" href="contact.php">Ask us to find a hotel</a>
      </div>
      <div id="hotels" class="mt-4 space-y-4" data-url="api/hotel-search.php?<?= e(hotel_search_query($search)) ?>">
        <?php for ($i = 0; $i < 3; $i++): ?>
          <div class="grid animate-pulse overflow-hidden rounded-2xl bg-white ring-1 ring-ink-900/5 sm:grid-cols-[17rem_1fr]">
            <div class="aspect-[4/3] bg-ink-100 sm:aspect-auto sm:h-56"></div>
            <div class="space-y-3 p-5"><div class="h-4 w-24 rounded bg-ink-100"></div><div class="h-6 w-2/3 rounded bg-ink-100"></div><div class="h-4 w-1/2 rounded bg-ink-100"></div><div class="mt-10 h-10 w-40 rounded bg-ink-100"></div></div>
          </div>
        <?php endfor; ?>
      </div>
    </div>
  </div>
</section>

<script>
  // Load the hotels, then filter and sort them without reloading the page
  (function () {
    var list = document.getElementById('hotels');
    var hotels = [];
    var slider = document.getElementById('max-price');
    function naira(k) { return '₦' + Math.round(k / 100).toLocaleString('en-NG'); }
    function message(title, text) {
      list.innerHTML = '';
      document.getElementById('message-title').textContent = title;
      document.getElementById('message-text').textContent = text;
      document.getElementById('message').classList.remove('hidden');
      document.getElementById('count').textContent = '';
    }
    function apply() {
      var stars = Array.from(document.querySelectorAll('[name=stars]:checked')).map(function (c) { return c.value; });
      var max = +slider.value, shown = 0;
      document.getElementById('max-price-text').textContent = 'Up to ' + naira(max);
      hotels.forEach(function (h) {
        var ok = +h.dataset.price <= max && (!stars.length || stars.indexOf(h.dataset.stars) !== -1);
        h.hidden = !ok;
        if (ok) shown++;
      });
      document.getElementById('count').textContent = shown === hotels.length ? hotels.length + ' hotels found' : 'Showing ' + shown + ' of ' + hotels.length + ' hotels';
      var by = document.getElementById('sort').value;
      hotels.slice().sort(function (a, b) {
        if (by === 'price') return a.dataset.price - b.dataset.price;
        if (by === 'score') return b.dataset.score - a.dataset.score;
        return b.dataset.stars - a.dataset.stars || a.dataset.price - b.dataset.price;
      }).forEach(function (h) { list.appendChild(h); });
    }
    fetch(list.dataset.url).then(function (r) { return r.json(); }).then(function (data) {
      if (!data.ok) return message("We couldn't search right now", data.message);
      if (!data.count) return message('No hotels found', data.message);
      list.innerHTML = data.html;
      hotels = Array.from(list.querySelectorAll('.hotel'));
      var step = Math.max(1, Math.ceil((data.max_price - data.min_price) / 100));
      slider.min = data.min_price; slider.max = data.min_price + step * 100; slider.step = step; slider.value = slider.max;
      apply();
    }).catch(function () { message("We couldn't search right now", 'Please check your connection and try again.'); });
    document.getElementById('hotel-filters').addEventListener('change', apply);
    slider.addEventListener('input', apply);
    document.getElementById('sort').addEventListener('change', apply);
    document.getElementById('reset').addEventListener('click', function () {
      document.querySelectorAll('[name=stars]').forEach(function (c) { c.checked = false; });
      slider.value = slider.max; apply();
    });
  })();
</script>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>
