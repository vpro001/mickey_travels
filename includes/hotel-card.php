<?php
// One hotel on the results page. Expects $h (a Duffel search result), $price (hotel_price()),
// $search and $n. The data-* attributes are read by the filters in hotel-results.php.
$acc = $h['accommodation'];
$photo = isset($acc['photos'][0]['url']) ? $acc['photos'][0]['url'] : '';
$address = isset($acc['location']['address']['line_one']) ? $acc['location']['address']['line_one'] : '';
$per_night = (int) round($price['total_kobo'] / $search['nights']);
$stars = isset($acc['rating']) ? (int) $acc['rating'] : 0;
?>
<article class="hotel group grid overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-ink-900/5 transition hover:shadow-lg sm:grid-cols-[17rem_1fr]"
         data-price="<?= $price['total_kobo'] ?>" data-stars="<?= $stars ?>" data-score="<?= isset($acc['review_score']) ? (float) $acc['review_score'] : 0 ?>" data-order="<?= $n ?>">
  <div class="relative aspect-[4/3] bg-ink-800 sm:aspect-auto">
    <?php if ($photo): ?><img src="<?= e($photo) ?>" alt="<?= e($acc['name']) ?>" loading="lazy" class="absolute inset-0 h-full w-full object-cover transition duration-700 group-hover:scale-105"><?php endif; ?>
  </div>
  <div class="flex flex-col p-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="min-w-0">
        <p class="text-sm text-saffron-600" aria-label="<?= $stars ?> star hotel"><?= str_repeat('★', $stars) ?><span class="text-ink-200"><?= str_repeat('★', max(0, 5 - $stars)) ?></span></p>
        <h2 class="mt-1 font-display text-xl font-bold leading-tight"><?= e($acc['name']) ?></h2>
        <?php if ($address): ?><p class="mt-1 flex items-center gap-1.5 text-sm text-ink-600"><?= icon('pin', 'size-4 shrink-0') ?> <?= e($address) ?></p><?php endif; ?>
      </div>
      <?php if (!empty($acc['review_score'])): ?>
        <div class="text-right">
          <span class="inline-block rounded-lg bg-ink-900 px-2.5 py-1 font-display text-lg font-bold text-paper-50 num"><?= number_format((float) $acc['review_score'], 1) ?></span>
          <?php if (!empty($acc['review_count'])): ?><p class="mt-1 text-xs text-ink-600"><?= number_format($acc['review_count']) ?> reviews</p><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php if (!empty($acc['amenities'])): ?>
      <ul class="mt-3 flex flex-wrap gap-1.5">
        <?php foreach (array_slice($acc['amenities'], 0, 5) as $am): ?>
          <li class="rounded-full bg-paper-100 px-2.5 py-1 text-xs text-ink-700"><?= e(isset($am['description']) ? $am['description'] : $am['type']) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <div class="mt-auto flex flex-wrap items-end justify-between gap-4 pt-5">
      <div>
        <p class="font-display text-2xl font-bold num"><?= naira($price['total_kobo']) ?></p>
        <p class="text-xs text-ink-600"><?= plural($search['nights'], 'night') ?>, <?= plural($search['rooms'], 'room') ?> · about <?= naira($per_night) ?> a night</p>
      </div>
      <a href="hotel.php?id=<?= e($h['id']) ?>&<?= e(hotel_search_query($search)) ?>" class="btn btn-primary px-6">See rooms</a>
    </div>
  </div>
</article>
