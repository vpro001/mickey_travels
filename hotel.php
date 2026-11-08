<?php
require 'includes/init.php';
require 'includes/stays.php';
require 'includes/hotel-search.php';

// One hotel: every room and rate (Duffel "fetch all rates").
$id = get('id');
$search = read_hotel_search($_GET);
$error = '';
if (!preg_match('/^srr_[A-Za-z0-9_]{4,60}$/', $id) || $search['error']) {
    $error = 'Please search again.';
} else {
    $r = stays_rates($id);
    if (!$r['ok']) {
        $error = 'This hotel search has expired or the hotel has no rooms left for your dates.';
    }
}

$title = $error ? 'Hotel' : $r['data']['accommodation']['name'];
$page = 'hotels';
include 'includes/header.php';

if ($error): ?>
  <section class="container-page max-w-xl py-20">
    <h1 class="text-4xl font-extrabold">Let's search again</h1>
    <p class="mt-3 text-lg text-ink-700"><?= e($error) ?></p>
    <a class="btn btn-dark mt-6 px-5 py-3" href="hotels.php">Search hotels</a>
  </section>
<?php include 'includes/footer.php'; exit; endif;

$acc = $r['data']['accommodation'];
$photo = isset($acc['photos'][0]['url']) ? $acc['photos'][0]['url'] : '';
$address = isset($acc['location']['address']['line_one']) ? $acc['location']['address']['line_one'] . (isset($acc['location']['address']['city_name']) ? ', ' . $acc['location']['address']['city_name'] : '') : '';
$stars = isset($acc['rating']) ? (int) $acc['rating'] : 0;
?>
<section class="relative isolate overflow-hidden bg-ink-950 text-paper-50">
  <?php if ($photo): ?><img src="<?= e($photo) ?>" alt="<?= e($acc['name']) ?>" class="hero-photo absolute inset-0 -z-20 h-full w-full object-cover"><?php endif; ?>
  <div class="absolute inset-0 -z-10 bg-[linear-gradient(180deg,rgba(10,20,32,0.35)_0%,rgba(10,20,32,0.65)_50%,#0a1420_100%)]"></div>
  <div class="container-page pt-28 pb-10 sm:pt-40 sm:pb-14">
    <a href="hotel-results.php?<?= e(hotel_search_query($search)) ?>" class="font-mono text-xs uppercase tracking-[0.2em] text-ink-200 hover:text-saffron-300">← All hotels in <?= e($search['city']) ?></a>
    <p class="mt-4 text-saffron-300" aria-label="<?= $stars ?> star hotel"><?= str_repeat('★', $stars) ?></p>
    <h1 class="mt-1 max-w-3xl text-4xl font-extrabold leading-[0.95] tracking-[-0.03em] sm:text-6xl"><?= e($acc['name']) ?></h1>
    <p class="mt-3 flex items-center gap-2 text-ink-100"><?= icon('pin', 'size-5') ?> <?= e($address) ?></p>
    <p class="mt-4 font-mono text-xs uppercase tracking-[0.18em] text-ink-200"><?= date('D j M', strtotime($search['check_in'])) ?> – <?= date('D j M Y', strtotime($search['check_out'])) ?> · <?= plural($search['nights'], 'night') ?> · <?= plural($search['rooms'], 'room') ?></p>
  </div>
</section>

<section class="container-page py-10 sm:py-14">
  <div class="grid gap-10 lg:grid-cols-[1fr_20rem]">
    <div class="space-y-6">
      <h2 class="text-3xl font-extrabold">Choose your room</h2>
      <?php foreach ($acc['rooms'] as $room): ?>
        <article class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-ink-900/5">
          <div class="border-b border-paper-200 px-5 py-4">
            <p class="font-display text-xl font-bold"><?= e($room['name']) ?></p>
            <?php if (!empty($room['beds'])): ?>
              <p class="text-sm text-ink-600"><?= e(implode(', ', array_map(function ($b) { return $b['count'] . ' ' . $b['type'] . ' bed'; }, $room['beds']))) ?></p>
            <?php endif; ?>
          </div>
          <ul class="divide-y divide-paper-200">
            <?php foreach ($room['rates'] as $rate): $price = hotel_price($rate['total_amount'], $rate['total_currency'], $search['rooms']); if (!$price) continue;
                $cancel = cancellation_text(isset($rate['cancellation_timeline']) ? $rate['cancellation_timeline'] : [], $rate['total_amount']); ?>
              <li class="flex flex-wrap items-center justify-between gap-4 px-5 py-4">
                <div>
                  <p class="font-semibold"><?= e(board_name($rate['board_type'])) ?></p>
                  <p class="mt-0.5 text-sm <?= strpos($cancel, 'Free') === 0 ? 'text-ok-700' : 'text-ink-600' ?>"><?= e($cancel) ?></p>
                  <?php if (!empty($rate['quantity_available']) && $rate['quantity_available'] <= 3): ?><p class="mt-0.5 text-xs font-semibold text-warn-700">Only <?= (int) $rate['quantity_available'] ?> left</p><?php endif; ?>
                </div>
                <div class="flex items-center gap-4">
                  <div class="text-right">
                    <p class="font-display text-2xl font-bold num"><?= naira($price['total_kobo']) ?></p>
                    <p class="text-xs text-ink-600">total for <?= plural($search['nights'], 'night') ?></p>
                  </div>
                  <a class="btn btn-primary px-5" href="hotel-book.php?rate=<?= e($rate['id']) ?>&srr=<?= e($id) ?>&<?= e(hotel_search_query($search)) ?>">Choose</a>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        </article>
      <?php endforeach; ?>
      <?php if (!$acc['rooms']): ?><p class="rounded-2xl bg-white p-8 text-center text-ink-700 ring-1 ring-paper-200">No rooms left for these dates. Try different dates.</p><?php endif; ?>
    </div>

    <aside class="space-y-6 lg:sticky lg:top-24 lg:self-start">
      <?php if (!empty($acc['review_score'])): ?>
        <div class="flex items-center gap-4 rounded-2xl bg-white p-5 ring-1 ring-paper-200">
          <span class="rounded-xl bg-ink-900 px-3 py-2 font-display text-2xl font-bold text-paper-50 num"><?= number_format((float) $acc['review_score'], 1) ?></span>
          <p class="text-sm text-ink-700">Guest rating<?= !empty($acc['review_count']) ? '<br>from ' . number_format($acc['review_count']) . ' reviews' : '' ?></p>
        </div>
      <?php endif; ?>
      <?php if (!empty($acc['amenities'])): ?>
        <div class="rounded-2xl bg-white p-5 ring-1 ring-paper-200">
          <p class="font-display text-lg font-bold">At the hotel</p>
          <ul class="mt-3 space-y-2 text-sm">
            <?php foreach ($acc['amenities'] as $am): ?><li class="flex gap-2"><?= icon('check', 'size-4 shrink-0 text-ok-700 mt-0.5') ?> <?= e(isset($am['description']) ? $am['description'] : $am['type']) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
      <p class="text-xs text-ink-600">Prices include taxes and our service fee, for the whole stay. The final price is confirmed with the hotel on the next step.</p>
    </aside>
  </div>
</section>
<?php include 'includes/footer.php'; ?>
