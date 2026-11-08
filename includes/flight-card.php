<?php
// One flight on the results page. Expects $offer (from simplify_offer()) and $n (its position).
// The data-* attributes are what the filters and sorting in results.js read.
$p = $offer['price'];
$outbound_hour = (int) substr($offer['slices'][0]['depart'], 11, 2);
?>
<article class="flight card overflow-hidden rounded-xl"
         data-price="<?= $p['total_kobo'] ?>" data-minutes="<?= $offer['minutes'] ?>" data-stops="<?= $offer['stops'] ?>"
         data-airline="<?= e($offer['airline_code']) ?>" data-hour="<?= $outbound_hour ?>" data-order="<?= $n ?>">

  <div class="grid gap-4 p-4 sm:p-5 md:grid-cols-[1fr_auto]">
    <!-- Airline and times -->
    <div class="min-w-0">
      <div class="flex items-center gap-3">
        <?php if ($offer['logo']): ?>
          <img src="<?= e($offer['logo']) ?>" alt="" class="size-8 shrink-0 object-contain" loading="lazy">
        <?php else: ?>
          <span class="flex size-8 shrink-0 items-center justify-center rounded-md bg-ink-900 text-[11px] font-bold text-paper-50"><?= e($offer['airline_code']) ?></span>
        <?php endif; ?>
        <p class="font-semibold"><?= e($offer['airline']) ?></p>
        <?php if ($offer['slices'][0]['brand']): ?>
          <span class="badge bg-paper-100 text-ink-700"><?= e($offer['slices'][0]['brand']) ?></span>
        <?php endif; ?>
      </div>

      <?php foreach ($offer['slices'] as $s):
          $days_later = (int) round((strtotime(substr($s['arrive'], 0, 10)) - strtotime(substr($s['depart'], 0, 10))) / 86400);
      ?>
      <div class="mt-4 grid grid-cols-[auto_1fr_auto] items-center gap-3 sm:gap-5">
        <div>
          <p class="font-display text-xl sm:text-2xl font-semibold num leading-none"><?= substr($s['depart'], 11, 5) ?></p>
          <p class="mt-1 text-sm font-medium text-ink-600"><?= e($s['from']) ?></p>
        </div>
        <div class="text-center">
          <p class="text-xs text-ink-600 num"><?= minutes_text($s['minutes']) ?></p>
          <div class="relative my-1.5 h-px bg-ink-300">
            <?php foreach ($s['via'] as $k => $via): ?>
              <span class="absolute top-1/2 size-2 -translate-y-1/2 rounded-full bg-saffron-500 ring-2 ring-white" style="left: <?= round(($k + 1) / (count($s['via']) + 1) * 100) ?>%"></span>
            <?php endforeach; ?>
          </div>
          <p class="text-xs <?= $s['stops'] ? 'text-warn-700' : 'text-ok-700' ?> font-medium">
            <?= $s['stops'] ? plural($s['stops'], 'stop') . ' · ' . implode(', ', $s['via']) : 'Direct' ?>
          </p>
        </div>
        <div class="text-right">
          <p class="font-display text-xl sm:text-2xl font-semibold num leading-none"><?= substr($s['arrive'], 11, 5) ?><?php if ($days_later > 0): ?><sup class="ml-0.5 text-xs font-sans text-bad-700">+<?= $days_later ?></sup><?php endif; ?></p>
          <p class="mt-1 text-sm font-medium text-ink-600"><?= e($s['to']) ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Price -->
    <div class="flex items-center justify-between gap-4 border-t border-paper-200 pt-4 md:min-w-44 md:flex-col md:items-end md:justify-center md:border-l md:border-t-0 md:pl-5 md:pt-0">
      <div class="md:text-right">
        <p class="font-display text-2xl font-bold num"><?= naira($p['total_kobo']) ?></p>
        <p class="text-xs text-ink-600"><?= $offer['passengers'] > 1 ? 'Total for ' . plural($offer['passengers'], 'traveller') : 'Total price' ?></p>
      </div>
      <a href="flight-book.php?offer=<?= e($offer['id']) ?>" class="btn btn-primary px-6">Select</a>
    </div>
  </div>

  <!-- Bags, rules and the details toggle -->
  <div class="flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-paper-200 bg-paper-50 px-4 py-2.5 text-xs text-ink-700 sm:px-5">
    <span class="flex items-center gap-1.5"><?= icon('bag', 'size-4') ?> <?= $offer['checked_bags'] ? plural($offer['checked_bags'], 'checked bag') : 'No checked bag' ?><?= $offer['carry_on'] ? ' · cabin bag' : '' ?></span>
    <?php if ($offer['refund'] !== null): ?>
      <span><?= !empty($offer['refund']['allowed']) ? 'Refundable (fee applies)' : 'Non-refundable' ?></span>
    <?php endif; ?>
    <?php if (!empty($offer['change']['allowed'])): ?>
      <span>Changes allowed (fee applies)</span>
    <?php endif; ?>
    <button type="button" class="details-toggle ml-auto font-semibold text-ink-800 underline decoration-saffron-500 decoration-2 underline-offset-4" aria-expanded="false">Flight details</button>
  </div>

  <!-- Details (opened by the button above) -->
  <div class="details hidden border-t border-paper-200 px-4 py-5 sm:px-5" >
    <div class="grid gap-6 lg:grid-cols-[3fr_2fr]">
      <div class="space-y-6">
        <?php foreach ($offer['slices'] as $s): ?>
          <div>
            <p class="text-sm font-semibold"><?= e($s['from_city']) ?> to <?= e($s['to_city']) ?> · <span class="font-normal text-ink-600"><?= date('D j M', strtotime($s['depart'])) ?></span></p>
            <ol class="mt-3 space-y-3 border-l-2 border-ink-200 pl-4">
              <?php foreach ($s['legs'] as $leg): ?>
                <li class="text-sm">
                  <p><b class="num"><?= substr($leg['depart'], 11, 5) ?></b> <?= e($leg['from_name']) ?> (<?= e($leg['from']) ?>)<?= $leg['from_terminal'] ? ', Terminal ' . e($leg['from_terminal']) : '' ?></p>
                  <p class="my-1 text-xs text-ink-600"><?= e($leg['flight']) ?> · <?= e($leg['operated_by']) ?><?= $leg['aircraft'] ? ' · ' . e($leg['aircraft']) : '' ?><?= $leg['cabin'] ? ' · ' . e($leg['cabin']) : '' ?><?= $leg['duration'] ? ' · ' . minutes_text(duration_minutes($leg['duration'])) : '' ?></p>
                  <p><b class="num"><?= substr($leg['arrive'], 11, 5) ?></b> <?= e($leg['to_name']) ?> (<?= e($leg['to']) ?>)<?= $leg['to_terminal'] ? ', Terminal ' . e($leg['to_terminal']) : '' ?></p>
                  <?php if ($leg['layover'] > 0): ?>
                    <p class="mt-2 inline-block rounded bg-warn-100 px-2 py-0.5 text-xs font-medium text-warn-700"><?= minutes_text($leg['layover']) ?> to change planes in <?= e($leg['to_name']) ?></p>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ol>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="space-y-5 text-sm">
        <div class="rounded-lg bg-paper-100 p-4">
          <p class="font-semibold">Price breakdown</p>
          <dl class="mt-2 space-y-1">
            <div class="flex justify-between gap-4"><dt class="text-ink-700">Airline fare and taxes</dt><dd class="num"><?= naira($p['base_kobo']) ?></dd></div>
            <div class="flex justify-between gap-4"><dt class="text-ink-700">Our service fee</dt><dd class="num"><?= naira($p['markup_kobo']) ?></dd></div>
            <div class="flex justify-between gap-4 border-t border-paper-300 pt-1 font-semibold"><dt>Total</dt><dd class="num"><?= naira($p['total_kobo']) ?></dd></div>
          </dl>
          <?php if ($p['supplier_currency'] != 'NGN'): ?>
            <p class="mt-2 text-xs text-ink-600">The airline prices this fare in <?= e($p['supplier_currency']) ?>. We convert it at ₦<?= number_format($p['fx_rate'], 2) ?> per <?= e($p['supplier_currency']) ?>.</p>
          <?php endif; ?>
        </div>
        <div>
          <p class="font-semibold">Fare rules</p>
          <ul class="mt-1 space-y-1 text-ink-700">
            <li>Refunds: <?= $offer['refund'] === null ? 'Ask us before booking' : (!empty($offer['refund']['allowed']) ? 'Allowed, the airline charges a fee' : 'Not refundable') ?></li>
            <li>Changes: <?= $offer['change'] === null ? 'Ask us before booking' : (!empty($offer['change']['allowed']) ? 'Allowed, the airline charges a fee plus any fare difference' : 'Not allowed') ?></li>
            <li>Baggage: <?= $offer['checked_bags'] ? plural($offer['checked_bags'], 'checked bag') . ' per person' : 'No checked bag included' ?><?= $offer['carry_on'] ? ', plus a cabin bag' : '' ?></li>
            <?php if ($offer['needs_passport']): ?><li>You'll need passport details for each traveller.</li><?php endif; ?>
          </ul>
        </div>
      </div>
    </div>
  </div>
</article>
