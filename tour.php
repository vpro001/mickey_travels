<?php
require 'includes/init.php';
require 'includes/paystack.php';
require 'includes/account.php';

$tour = db_one("SELECT * FROM tour_packages WHERE slug = ? AND is_published = 1", [get('slug')]);
if (!$tour) {
    redirect('tours.php');
}
$departures = db_all("SELECT * FROM tour_departures WHERE package_id = ? AND status = 'open' AND start_date > CURDATE() ORDER BY start_date", [$tour['id']]);
$itinerary = json_decode((string) $tour['itinerary_json'], true) ?: [];

$f = ['departure' => '', 'travellers' => 2, 'option' => $tour['deposit_pct'] > 0 ? 'deposit' : 'full', 'name' => '', 'email' => '', 'phone' => '', 'notes' => ''];
$errors = [];
$enquiry_sent = false;
$user = current_user();
$login_url = 'login.php?next=' . urlencode('tour.php?slug=' . $tour['slug'] . '#book');
if ($user) {
    // Fill in the lead traveller from the account
    $f['name'] = $user['full_name'];
    $f['email'] = $user['email'];
    $f['phone'] = (string) $user['phone'];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_check();

    if (post('action') == 'enquiry') {
        // Question about this tour
        $name = post('name');
        $email = post('email');
        $message = post('message');
        if ($name != '' && valid_email($email) && strlen($message) >= 10) {
            db_insert('enquiries', ['type' => 'tour', 'related_id' => $tour['id'], 'name' => substr($name, 0, 120), 'email' => $email,
                'phone' => clean_phone(post('phone')) ?: null, 'subject' => $tour['title'], 'message' => substr($message, 0, 3000), 'ip' => $_SERVER['REMOTE_ADDR']]);
            flash("Thank you, $name. We'll reply by email about " . $tour['title'] . '.');
            redirect('tour.php?slug=' . urlencode($tour['slug']) . '#ask');
        }
        $errors['enquiry'] = 'Please enter your name, a valid email and your question.';
    } else {
        // Booking (signed-in customers only)
        if (!$user) {
            flash('Please sign in to book this tour.', 'error');
            redirect($login_url);
        }
        foreach ($f as $key => $v) $f[$key] = post($key);
        $dep = db_one("SELECT * FROM tour_departures WHERE id = ? AND package_id = ? AND status = 'open' AND start_date > CURDATE()", [(int) $f['departure'], $tour['id']]);
        $travellers = (int) $f['travellers'];
        if (!$dep) $errors['departure'] = 'Choose a departure date.';
        elseif ($travellers < 1 || $travellers > min(10, $dep['seats_total'] - $dep['seats_booked'])) $errors['travellers'] = 'Only ' . ($dep['seats_total'] - $dep['seats_booked']) . ' places are left on that date.';
        if (!in_array($f['option'], ['deposit', 'full']) || ($f['option'] == 'deposit' && $tour['deposit_pct'] == 0)) $errors['option'] = 'Choose how you want to pay.';
        if (strlen($f['name']) < 3) $errors['name'] = 'Enter the lead traveller\'s full name.';
        if (!valid_email($f['email'])) $errors['email'] = 'Enter an email address.';
        if (clean_phone($f['phone']) == '') $errors['phone'] = 'Enter a phone number, e.g. 0803 123 4567.';
        if (!$errors && !paystack_ready()) $errors['form'] = "Online payment isn't switched on yet. Please use the question form below and we'll book it for you.";

        if (!$errors) {
            $unit = $dep['price_kobo_override'] ?: $tour['price_kobo'];
            $total = $unit * $travellers;
            $due = $f['option'] == 'deposit' ? (int) round($total * $tour['deposit_pct'] / 100) : $total;
            $ref = make_reference('MTT');
            $reference = $ref . '-' . bin2hex(random_bytes(3));

            db_begin();
            $id = db_insert('tour_bookings', [
                'booking_ref' => $ref, 'user_id' => user_id_or_null(), 'package_id' => $tour['id'], 'departure_id' => $dep['id'], 'lead_name' => substr($f['name'], 0, 120),
                'email' => $f['email'], 'phone' => clean_phone($f['phone']), 'travellers' => $travellers, 'unit_price_kobo' => $unit,
                'total_kobo' => $total, 'payment_option' => $f['option'], 'notes' => substr($f['notes'], 0, 1000),
            ]);
            db_insert('payments', ['reference' => $reference, 'booking_type' => 'tour', 'booking_id' => $id, 'purpose' => $f['option'] == 'deposit' ? 'deposit' : 'full',
                'amount_kobo' => $due, 'currency' => 'NGN', 'email' => $f['email']]);
            db_commit();

            $_SESSION['my_tours'][] = $ref;
            $pay = paystack_initialize($f['email'], $due, $reference, SITE_URL . '/payment-callback.php', ['booking_ref' => $ref, 'booking_type' => 'tour']);
            if ($pay['ok']) {
                db_query("UPDATE payments SET authorization_url = ? WHERE reference = ?", [$pay['data']['authorization_url'], $reference]);
                header('Location: ' . $pay['data']['authorization_url']);
                exit;
            }
            write_log('payments', 'Paystack initialize failed (tour)', ['reference' => $reference, 'message' => $pay['message']]);
            $errors['form'] = "We couldn't open the payment page. Your booking $ref is saved; please try again in a moment.";
        }
    }
}

$title = $tour['title'];
$description = $tour['summary'];
$page = 'tours';
include 'includes/header.php';
$field = 'mt-1 block w-full rounded-lg border-0 bg-paper-50 px-3.5 py-3 text-base text-ink-900 ring-1 ring-ink-300 focus:outline-none focus:ring-2 focus:ring-saffron-500';
$label = 'block font-mono text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-600';
?>

<section class="relative isolate overflow-hidden bg-ink-950 text-paper-50">
  <?php if ($tour['cover_image']): ?><?= photo($tour['cover_image'], $tour['destination'], 'hero-photo absolute inset-0 -z-20 h-full w-full object-cover', false, [480, 800]) ?><?php endif; ?>
  <div class="absolute inset-0 -z-10 bg-[linear-gradient(180deg,rgba(10,20,32,0.3)_0%,rgba(10,20,32,0.6)_50%,#0a1420_100%)]"></div>
  <div class="container-page pt-28 pb-12 sm:pt-48 sm:pb-16">
    <a href="tours.php" class="font-mono text-xs uppercase tracking-[0.2em] text-ink-200 hover:text-saffron-300">← All holidays</a>
    <p class="mt-4 eyebrow text-saffron-300"><?= e($tour['destination']) ?> · <?= plural($tour['duration_days'], 'day') ?> · <?= plural($tour['duration_nights'], 'night') ?></p>
    <h1 class="mt-3 max-w-3xl text-5xl font-extrabold leading-[0.95] tracking-[-0.035em] sm:text-7xl"><?= e($tour['title']) ?></h1>
    <p class="mt-4 max-w-2xl text-lg text-ink-100"><?= e($tour['summary']) ?></p>
  </div>
</section>

<section class="container-page py-12 sm:py-16">
  <div class="grid gap-10 lg:grid-cols-[1.5fr_1fr]">
    <div class="space-y-12">
      <div>
        <p class="text-lg leading-relaxed text-ink-800"><?= nl2br(e($tour['description'])) ?></p>
      </div>

      <?php if ($itinerary): ?>
      <div>
        <h2 class="text-3xl font-extrabold">Day by day</h2>
        <ol class="mt-6 space-y-0">
          <?php foreach ($itinerary as $day): ?>
            <li class="grid grid-cols-[4rem_1fr] gap-4 border-t border-paper-200 py-5">
              <p class="font-mono text-sm font-semibold text-saffron-700">Day <?= (int) $day['day'] ?></p>
              <div>
                <p class="font-display text-xl font-bold"><?= e($day['title']) ?></p>
                <p class="mt-1 text-ink-700"><?= e($day['body']) ?></p>
              </div>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
      <?php endif; ?>

      <div class="grid gap-6 sm:grid-cols-2">
        <div class="rounded-2xl bg-ok-100/60 p-6">
          <p class="font-display text-xl font-bold">Included</p>
          <ul class="mt-3 space-y-2 text-sm">
            <?php foreach (array_filter(preg_split('/\r?\n/', (string) $tour['inclusions'])) as $line): ?>
              <li class="flex gap-2"><?= icon('check', 'size-4 shrink-0 text-ok-700 mt-0.5') ?> <?= e($line) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div class="rounded-2xl bg-paper-100 p-6">
          <p class="font-display text-xl font-bold">Not included</p>
          <ul class="mt-3 space-y-2 text-sm text-ink-700">
            <?php foreach (array_filter(preg_split('/\r?\n/', (string) $tour['exclusions'])) as $line): ?>
              <li class="flex gap-2"><span class="mt-2 size-1.5 shrink-0 rounded-full bg-ink-400"></span> <?= e($line) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>

      <!-- Questions -->
      <div id="ask" class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5">
        <h2 class="text-2xl font-extrabold">Have a question?</h2>
        <p class="mt-1 text-ink-700">Different dates, a private group, or something you'd like to change? Ask and we'll reply by email.</p>
        <?php if (isset($errors['enquiry'])): ?><p class="mt-3 rounded-lg bg-bad-100 p-3 text-sm text-bad-700"><?= e($errors['enquiry']) ?></p><?php endif; ?>
        <form method="post" action="tour.php?slug=<?= e($tour['slug']) ?>#ask" class="mt-4 grid gap-3 sm:grid-cols-2">
          <?= csrf_field() ?><input type="hidden" name="action" value="enquiry">
          <input name="name" placeholder="Your name" class="<?= $field ?>" aria-label="Your name">
          <input name="email" type="email" placeholder="Email" class="<?= $field ?>" aria-label="Email">
          <input name="phone" type="tel" placeholder="Phone (optional)" class="<?= $field ?> sm:col-span-2" aria-label="Phone">
          <textarea name="message" rows="3" placeholder="Your question" class="<?= $field ?> sm:col-span-2" aria-label="Your question"></textarea>
          <button class="btn btn-dark sm:col-span-2 sm:justify-self-start px-6 py-3">Send question</button>
        </form>
      </div>
    </div>

    <!-- Booking -->
    <aside class="lg:sticky lg:top-24 lg:self-start">
      <form method="post" action="tour.php?slug=<?= e($tour['slug']) ?>#book" id="book" class="overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-ink-900/5" novalidate>
        <?= csrf_field() ?>
        <div class="bg-ink-950 p-6 text-paper-50">
          <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-ink-300">From</p>
          <p class="font-display text-4xl font-bold num"><?= naira($tour['price_kobo']) ?></p>
          <p class="text-sm text-ink-300"><?= e($tour['price_basis']) ?></p>
        </div>
        <div class="space-y-4 p-6">
          <?php if (isset($errors['form'])): ?><p class="rounded-lg bg-bad-100 p-3 text-sm text-bad-700"><?= e($errors['form']) ?></p><?php endif; ?>
          <?php if (!$departures): ?>
            <p class="text-ink-700">New dates are coming soon. <a class="link" href="#ask">Ask us</a> to be the first to know.</p>
          <?php else: ?>
            <fieldset>
              <legend class="<?= $label ?>">Departure date</legend>
              <div class="mt-2 space-y-2">
                <?php foreach ($departures as $d): $left = $d['seats_total'] - $d['seats_booked']; $price = $d['price_kobo_override'] ?: $tour['price_kobo']; ?>
                  <label class="relative block">
                    <input type="radio" name="departure" value="<?= $d['id'] ?>" data-price="<?= $price ?>" class="peer sr-only" <?= $left < 1 ? 'disabled' : '' ?> <?= $f['departure'] == $d['id'] ? 'checked' : '' ?>>
                    <span class="flex items-center justify-between rounded-lg px-4 py-3 ring-1 ring-ink-300 peer-checked:bg-ink-950 peer-checked:text-paper-50 peer-checked:ring-ink-950 peer-disabled:opacity-50 peer-focus-visible:outline peer-focus-visible:outline-3 peer-focus-visible:outline-saffron-500">
                      <span class="font-semibold num"><?= date('j M', strtotime($d['start_date'])) ?> – <?= date('j M Y', strtotime($d['end_date'])) ?></span>
                      <span class="text-xs <?= $left <= 4 ? 'font-semibold' : 'opacity-75' ?>"><?= $left < 1 ? 'Full' : plural($left, 'place') . ' left' ?></span>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
              <?= isset($errors['departure']) ? '<p class="field-error">' . e($errors['departure']) . '</p>' : '' ?>
            </fieldset>

            <div>
              <label class="<?= $label ?>" for="travellers">Travellers</label>
              <input id="travellers" type="number" name="travellers" min="1" max="10" value="<?= (int) $f['travellers'] ?>" class="<?= $field ?> num">
              <?= isset($errors['travellers']) ? '<p class="field-error">' . e($errors['travellers']) . '</p>' : '' ?>
            </div>

            <?php if ($tour['deposit_pct'] > 0): ?>
            <fieldset>
              <legend class="<?= $label ?>">How would you like to pay?</legend>
              <div class="mt-2 grid grid-cols-2 gap-2">
                <?php foreach (['deposit' => $tour['deposit_pct'] . '% deposit now', 'full' => 'Pay in full'] as $k => $text): ?>
                  <label class="relative"><input type="radio" name="option" value="<?= $k ?>" class="peer sr-only" <?= $f['option'] == $k ? 'checked' : '' ?>>
                    <span class="block rounded-lg px-3 py-3 text-center text-sm font-semibold ring-1 ring-ink-300 peer-checked:bg-ink-950 peer-checked:text-paper-50 peer-checked:ring-ink-950"><?= $text ?></span></label>
                <?php endforeach; ?>
              </div>
            </fieldset>
            <?php else: ?><input type="hidden" name="option" value="full"><?php endif; ?>

            <?php if ($user): ?>
            <div class="space-y-3 border-t border-dashed border-paper-300 pt-4">
              <div><label class="<?= $label ?>" for="name">Lead traveller</label><input id="name" name="name" autocomplete="name" class="<?= $field ?>" value="<?= e($f['name']) ?>"><?= isset($errors['name']) ? '<p class="field-error">' . e($errors['name']) . '</p>' : '' ?></div>
              <div><label class="<?= $label ?>" for="email">Email</label><input id="email" name="email" type="email" autocomplete="email" class="<?= $field ?>" value="<?= e($f['email']) ?>"><?= isset($errors['email']) ? '<p class="field-error">' . e($errors['email']) . '</p>' : '' ?></div>
              <div><label class="<?= $label ?>" for="phone">Phone</label><input id="phone" name="phone" type="tel" autocomplete="tel" class="<?= $field ?> num" value="<?= e($f['phone']) ?>"><?= isset($errors['phone']) ? '<p class="field-error">' . e($errors['phone']) . '</p>' : '' ?></div>
              <div><label class="<?= $label ?>" for="notes">Anything we should know? <span class="normal-case tracking-normal font-normal">(optional)</span></label><textarea id="notes" name="notes" rows="2" class="<?= $field ?>"><?= e($f['notes']) ?></textarea></div>
            </div>

            <div class="rounded-xl bg-paper-100 p-4 text-sm">
              <div class="flex justify-between"><span class="text-ink-700">Total</span><b class="num" id="total">–</b></div>
              <div class="mt-1 flex justify-between font-semibold"><span>To pay now</span><span class="num" id="now">–</span></div>
            </div>
            <button class="btn btn-primary w-full py-4 text-base"><?= icon('card', 'size-5') ?> Book and pay securely</button>
            <p class="text-center text-xs text-ink-600">Card, bank transfer or USSD through Paystack.</p>
            <?php else: ?>
            <div class="rounded-xl bg-paper-100 p-4 text-sm">
              <div class="flex justify-between"><span class="text-ink-700">Total</span><b class="num" id="total">–</b></div>
              <div class="mt-1 flex justify-between font-semibold"><span>To pay now</span><span class="num" id="now">–</span></div>
            </div>
            <a class="btn btn-primary w-full py-4 text-base" href="<?= e($login_url) ?>">Sign in to book</a>
            <p class="text-center text-xs text-ink-600">No account? <a class="link" href="register.php">Create one</a> in a minute.</p>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </form>
    </aside>
  </div>
</section>

<script>
  // Show the total and the amount to pay now as choices change (the server works it out again)
  (function () {
    var form = document.getElementById('book');
    if (!form.querySelector('[name=travellers]')) return;
    var depositPct = <?= (int) $tour['deposit_pct'] ?>;
    function naira(kobo) { return '₦' + Math.round(kobo / 100).toLocaleString('en-NG'); }
    function update() {
      var dep = form.querySelector('[name=departure]:checked');
      var people = Math.max(1, parseInt(form.travellers.value, 10) || 1);
      if (!dep) return;
      var total = dep.dataset.price * people;
      var option = form.querySelector('[name=option]:checked') || form.querySelector('[name=option]');
      var now = option.value === 'deposit' ? Math.round(total * depositPct / 100) : total;
      document.getElementById('total').textContent = naira(total);
      document.getElementById('now').textContent = naira(now);
    }
    form.addEventListener('change', update);
    form.addEventListener('input', update);
    var first = form.querySelector('[name=departure]:not(:disabled)');
    if (first && !form.querySelector('[name=departure]:checked')) first.checked = true;
    update();
  })();
</script>
<?php include 'includes/footer.php'; ?>
