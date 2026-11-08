<?php
require 'includes/init.php';

// visa.php                   → all destinations
// visa.php?country=<slug>    → requirements for one destination, with the Apply button

$slug = get('country');
$country = $slug ? db_one("SELECT * FROM visa_destinations WHERE slug = ? AND is_active = 1", [$slug]) : null;
$countries = db_all("SELECT * FROM visa_destinations WHERE is_active = 1 ORDER BY sort_order, country_name");
$type_names = ['tourist' => 'Tourist', 'business' => 'Business', 'visit' => 'Family visit', 'student' => 'Student', 'transit' => 'Transit'];

$title = $country ? $country['country_name'] . ' visa' : 'Visa services';
$page = 'visa';
include 'includes/header.php';
?>

<section class="relative isolate overflow-hidden bg-ink-950 text-paper-50">
  <?= photo('passport', 'A passport, camera and map laid out on a table', 'absolute inset-0 -z-20 h-full w-full object-cover opacity-60', false, [480, 800]) ?>
  <div class="absolute inset-0 -z-10 bg-gradient-to-r from-ink-950 via-ink-950/90 to-ink-950/40"></div>
  <div class="container-page py-14 sm:py-20">
    <p class="eyebrow text-saffron-300"><?= $country ? 'Visa · ' . e($country['country_name']) : 'Visa services' ?></p>
    <h1 class="mt-3 max-w-3xl text-4xl font-extrabold leading-[0.95] tracking-[-0.03em] sm:text-6xl">
      <?= $country ? e($country['country_name']) . ' visa' : 'Get your visa application right the first time.' ?>
    </h1>
    <p class="mt-4 max-w-xl text-lg text-ink-100"><?= $country ? e($country['summary']) : 'We check your documents, fill the forms with you, book the appointment and prepare you for the interview. Follow every step online.' ?></p>
  </div>
</section>

<?php if ($country): ?>
<section class="container-page py-12 sm:py-16">
  <div class="grid gap-10 lg:grid-cols-[1.4fr_1fr]">
    <div>
      <h2 class="text-2xl font-extrabold sm:text-3xl">What you'll usually need</h2>
      <p class="mt-2 text-ink-700">A general guide. We confirm the exact list for your trip when we review your application, because embassies change their rules.</p>
      <ul class="mt-6 space-y-3">
        <?php foreach (preg_split('/\r?\n/', trim($country['requirements'])) as $n => $line): ?>
          <li class="flex gap-4 rounded-xl bg-white p-4 ring-1 ring-paper-200">
            <span class="font-mono text-sm font-semibold text-saffron-700"><?= str_pad($n + 1, 2, '0', STR_PAD_LEFT) ?></span>
            <span><?= e($line) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <aside class="lg:sticky lg:top-24 lg:self-start">
      <div class="overflow-hidden rounded-2xl bg-ink-950 text-paper-50">
        <div class="p-6">
          <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-ink-300">Our service fee</p>
          <p class="mt-1 font-display text-4xl font-bold num"><?= naira($country['service_fee_kobo']) ?></p>
          <p class="mt-1 text-sm text-ink-300">The embassy's own visa fee is separate and paid as the embassy asks.</p>
          <dl class="mt-6 space-y-3 border-t border-white/10 pt-5 text-sm">
            <div class="flex justify-between gap-4"><dt class="text-ink-300">Processing time</dt><dd class="text-right"><?= e($country['processing_time']) ?></dd></div>
            <div class="flex justify-between gap-4"><dt class="text-ink-300">Visa types</dt>
              <dd class="text-right"><?= e(implode(', ', array_map(function ($t) use ($type_names) { return isset($type_names[$t]) ? $type_names[$t] : $t; }, explode(',', $country['visa_types'])))) ?></dd></div>
          </dl>
        </div>
        <div class="space-y-3 bg-white/5 p-6">
          <a class="btn btn-primary w-full py-3.5 text-base" href="visa-apply.php?country=<?= e($country['slug']) ?>">Start your application <?= icon('arrow-right', 'size-5') ?></a>
          <a class="btn w-full py-3 ring-1 ring-inset ring-white/20 hover:bg-white/10" href="contact.php">Ask a question first</a>
        </div>
      </div>
      <p class="mt-3 text-xs text-ink-600">The embassy makes the final decision on every visa. We can't guarantee an approval, but we'll make sure your application is complete and well prepared.</p>
    </aside>
  </div>
</section>
<?php endif; ?>

<section class="<?= $country ? 'bg-paper-100' : '' ?>">
  <div class="container-page py-12 sm:py-16">
    <h2 class="text-2xl font-extrabold sm:text-3xl"><?= $country ? 'Other destinations' : 'Where are you going?' ?></h2>
    <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      <?php foreach ($countries as $c): if ($country && $c['id'] == $country['id']) continue; ?>
        <a href="visa.php?country=<?= e($c['slug']) ?>" class="group flex flex-col rounded-2xl bg-white p-5 ring-1 ring-paper-200 transition hover:-translate-y-0.5 hover:shadow-lg">
          <div class="flex items-start justify-between gap-3">
            <p class="font-display text-xl font-bold"><?= e($c['country_name']) ?></p>
            <span class="font-mono text-xs font-semibold text-ink-500"><?= e($c['country_code']) ?></span>
          </div>
          <p class="mt-2 flex-1 text-sm text-ink-700"><?= e($c['summary']) ?></p>
          <div class="mt-4 flex items-end justify-between border-t border-paper-200 pt-3 text-sm">
            <span class="text-ink-600"><?= e($c['processing_time']) ?></span>
            <span class="font-semibold num">from <?= naira($c['service_fee_kobo']) ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <p class="mt-6 text-sm text-ink-700">Already applied? <a class="link" href="visa-track.php">Track your application</a>. Going somewhere else? <a class="link" href="contact.php">Ask us</a>.</p>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
