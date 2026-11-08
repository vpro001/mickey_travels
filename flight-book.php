<?php
require 'includes/init.php';
require 'includes/booking.php';

// Step 2 of booking: traveller details, then off to Paystack.
// The fare is fetched again from the airline every time this page loads and again when
// the form is sent, so we never charge a price the airline no longer offers.

$offer_id = get('offer');
$error = '';
if (!preg_match('/^off_[A-Za-z0-9_]{4,60}$/', $offer_id)) {
    $error = "We couldn't find that fare. Please search again.";
} else {
    $result = duffel('GET', '/air/offers/' . $offer_id);
    if (!$result['ok']) {
        $error = in_array($result['error_code'], ['offer_expired', 'offer_no_longer_available'])
            ? 'This fare is no longer available. Airlines only hold prices for a short time.'
            : "We couldn't check this fare with the airline just now. Please search again.";
    } elseif (strtotime($result['data']['expires_at']) < time()) {
        $error = 'This fare has expired. Airlines only hold prices for a short time.';
    }
}

if (!$error) {
    $raw = $result['data'];
    $offer = simplify_offer($raw);
    if (!$offer) {
        $error = "We can't price this fare in Naira right now. Please contact us.";
    }
}

if ($error) {
    $title = 'Fare not available';
    include 'includes/header.php';
    ?>
    <section class="container-page py-20 max-w-xl">
      <p class="eyebrow text-saffron-700">Booking</p>
      <h1 class="mt-3 text-4xl font-extrabold">Let's find you another fare</h1>
      <p class="mt-3 text-lg text-ink-700"><?= e($error) ?></p>
      <div class="mt-8 flex flex-wrap gap-3">
        <a class="btn btn-dark px-5 py-3" href="flights.php">Search again</a>
        <a class="btn btn-ghost px-5 py-3" href="contact.php">Ask us to find fares</a>
      </div>
    </section>
    <?php
    include 'includes/footer.php';
    exit;
}

$price = $offer['price'];
$last_flight_date = substr($offer['slices'][count($offer['slices']) - 1]['depart'], 0, 10);
$countries = db_all("SELECT DISTINCT country_code, country_name FROM airports WHERE country_name <> '' ORDER BY country_name");
$titles = ['mr' => 'Mr', 'mrs' => 'Mrs', 'ms' => 'Ms', 'miss' => 'Miss', 'dr' => 'Dr'];
$type_names = ['adult' => 'Adult', 'child' => 'Child (2–11)', 'infant_without_seat' => 'Infant (under 2)'];

// The price we showed the customer for this fare
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    $_SESSION['quote'][$offer_id] = $price['total_kobo'];
}

$form = ['email' => '', 'phone' => '', 'passengers' => []];
$errors = [];
$notice = '';

// Age in whole years on a given date
function age_on($born, $date)
{
    return (int) date_diff(date_create($born), date_create($date))->y;
}

// ---------------- Form sent ----------------
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_check();

    $form['email'] = post('email');
    $form['phone'] = post('phone');
    if (!valid_email($form['email'])) $errors['email'] = 'Enter an email address. Your e-ticket is sent there.';
    if (clean_phone($form['phone']) == '') $errors['phone'] = 'Enter a phone number, e.g. 0803 123 4567.';

    $sent = isset($_POST['p']) && is_array($_POST['p']) ? $_POST['p'] : [];
    foreach ($raw['passengers'] as $i => $p) {
        $in = isset($sent[$i]) && is_array($sent[$i]) ? $sent[$i] : [];
        $f = [];
        foreach (['title', 'given_name', 'family_name', 'gender', 'born_on', 'passport_number', 'passport_country', 'passport_expiry'] as $key) {
            $f[$key] = isset($in[$key]) && is_string($in[$key]) ? trim($in[$key]) : '';
        }
        $form['passengers'][$i] = $f;

        if (!isset($titles[$f['title']])) $errors["p$i.title"] = 'Choose a title.';
        if (!preg_match("/^[A-Za-z][A-Za-z '\-]{0,49}$/", $f['given_name'])) $errors["p$i.given_name"] = 'Enter the first name as it is on the ' . ($offer['needs_passport'] ? 'passport' : 'ID') . ' (English letters only).';
        if (!preg_match("/^[A-Za-z][A-Za-z '\-]{0,49}$/", $f['family_name'])) $errors["p$i.family_name"] = 'Enter the surname as it is on the ' . ($offer['needs_passport'] ? 'passport' : 'ID') . ' (English letters only).';
        if (!in_array($f['gender'], ['m', 'f'])) $errors["p$i.gender"] = 'Choose one.';

        if (!valid_date($f['born_on']) || $f['born_on'] >= date('Y-m-d')) {
            $errors["p$i.born_on"] = 'Enter a valid date of birth.';
        } else {
            $age = age_on($f['born_on'], $last_flight_date);
            if ($p['type'] == 'adult' && $age < 12) $errors["p$i.born_on"] = 'Adults must be 12 or older on the travel date. Search again with this traveller as a child.';
            if ($p['type'] == 'child' && ($age < 2 || $age > 11)) $errors["p$i.born_on"] = 'Children must be 2 to 11 on the travel date. Search again with the right age group.';
            if ($p['type'] == 'infant_without_seat' && $age >= 2) $errors["p$i.born_on"] = 'Infants must be under 2 on the travel date. Search again with this traveller as a child.';
        }

        if ($offer['needs_passport']) {
            if (!preg_match('/^[A-Za-z0-9]{5,20}$/', $f['passport_number'])) $errors["p$i.passport_number"] = 'Enter the passport number.';
            if (!preg_match('/^[A-Z]{2}$/', $f['passport_country'])) $errors["p$i.passport_country"] = 'Choose the country that issued the passport.';
            if (!valid_date($f['passport_expiry']) || $f['passport_expiry'] <= $last_flight_date) $errors["p$i.passport_expiry"] = 'The passport must still be valid after your last flight.';
        }
    }

    if (!$errors && !paystack_ready()) {
        $errors['form'] = "Online payment isn't switched on yet. Please contact us to finish this booking.";
    }

    // Has the price changed since we showed it?
    if (!$errors && $price['total_kobo'] != $_SESSION['quote'][$offer_id]) {
        $notice = 'The airline has changed the price of this fare from ' . naira($_SESSION['quote'][$offer_id]) . ' to ' . naira($price['total_kobo']) . '. Please check it and press the button again to continue.';
        $_SESSION['quote'][$offer_id] = $price['total_kobo'];
    }

    if (!$errors && !$notice) {
        // Build the passenger details exactly as Duffel wants them for the order
        $phone = clean_phone($form['phone']);
        $duffel_passengers = [];
        $adult_ids = [];
        foreach ($raw['passengers'] as $i => $p) {
            $f = $form['passengers'][$i];
            $item = [
                'id' => $p['id'],
                'title' => $f['title'],
                'gender' => $f['gender'],
                'given_name' => $f['given_name'],
                'family_name' => $f['family_name'],
                'born_on' => $f['born_on'],
                'email' => $form['email'],
                'phone_number' => $phone,
            ];
            if ($offer['needs_passport']) {
                $item['identity_documents'] = [[
                    'type' => 'passport',
                    'unique_identifier' => strtoupper($f['passport_number']),
                    'issuing_country_code' => $f['passport_country'],
                    'expires_on' => $f['passport_expiry'],
                ]];
            }
            if ($p['type'] == 'adult') $adult_ids[] = $i;
            $duffel_passengers[$i] = $item;
        }
        // Each infant sits on a different adult's lap
        $n = 0;
        foreach ($raw['passengers'] as $i => $p) {
            if ($p['type'] == 'infant_without_seat') {
                $duffel_passengers[$adult_ids[$n++]]['infant_passenger_id'] = $p['id'];
            }
        }

        // Save the booking and the payment record together
        $booking_ref = make_reference('MTF');
        $reference = $booking_ref . '-' . bin2hex(random_bytes(3));   // Paystack reference (letters, numbers and - only)
        db_begin();
        try {
            $booking_id = db_insert('flight_bookings', [
                'booking_ref' => $booking_ref,
                'user_id' => user_id_or_null(),
                'contact_email' => $form['email'],
                'contact_phone' => $phone,
                'offer_id' => $offer_id,
                'offer_snapshot' => json_encode($raw),
                'offer_expires_at' => date('Y-m-d H:i:s', strtotime($raw['expires_at'])),
                'passengers_json' => json_encode(array_values($duffel_passengers)),
                'itinerary_json' => json_encode(array_map(function ($s) { unset($s['legs']); return $s; }, $offer['slices'])),
                'supplier_currency' => $price['supplier_currency'],
                'supplier_amount' => $price['supplier_amount'],
                'fx_rate' => $price['fx_rate'],
                'fx_buffer_pct' => $price['fx_buffer_pct'],
                'base_kobo' => $price['base_kobo'],
                'markup_type' => $price['markup_type'],
                'markup_value' => $price['markup_value'],
                'markup_kobo' => $price['markup_kobo'],
                'total_kobo' => $price['total_kobo'],
                'status' => 'pending_payment',
            ]);
            foreach ($raw['passengers'] as $i => $p) {
                $f = $form['passengers'][$i];
                db_insert('flight_passengers', [
                    'booking_id' => $booking_id,
                    'duffel_passenger_id' => $p['id'],
                    'type' => $p['type'],
                    'title' => $f['title'],
                    'given_name' => $f['given_name'],
                    'family_name' => $f['family_name'],
                    'gender' => $f['gender'],
                    'born_on' => $f['born_on'],
                    'email' => $form['email'],
                    'phone' => $phone,
                    'passport_number' => $offer['needs_passport'] ? strtoupper($f['passport_number']) : null,
                    'passport_country' => $offer['needs_passport'] ? $f['passport_country'] : null,
                    'passport_expires_on' => $offer['needs_passport'] ? $f['passport_expiry'] : null,
                    'infant_duffel_id' => isset($duffel_passengers[$i]['infant_passenger_id']) ? $duffel_passengers[$i]['infant_passenger_id'] : null,
                ]);
            }
            db_insert('payments', [
                'reference' => $reference,
                'provider' => 'paystack',
                'booking_type' => 'flight',
                'booking_id' => $booking_id,
                'amount_kobo' => $price['total_kobo'],
                'currency' => 'NGN',
                'email' => $form['email'],
                'status' => 'initialized',
            ]);
            db_commit();
        } catch (Exception $ex) {
            db_rollback();
            throw $ex;
        }

        // Remember the booking in this browser so the customer can see it without logging in
        $_SESSION['my_bookings'][] = $booking_ref;

        $pay = paystack_initialize($form['email'], $price['total_kobo'], $reference, SITE_URL . '/payment-callback.php',
            ['booking_ref' => $booking_ref, 'booking_type' => 'flight']);
        if ($pay['ok']) {
            db_query("UPDATE payments SET authorization_url = ? WHERE reference = ?", [$pay['data']['authorization_url'], $reference]);
            header('Location: ' . $pay['data']['authorization_url']);
            exit;
        }
        write_log('payments', 'Paystack initialize failed', ['reference' => $reference, 'message' => $pay['message']]);
        $errors['form'] = "We couldn't open the payment page. Your booking $booking_ref is saved; please try again in a moment or contact us.";
    }
}

$title = 'Traveller details';
$page = 'flights';
include 'includes/header.php';

$field = 'mt-1 block w-full rounded-lg border-0 bg-paper-50 px-3.5 py-3 text-base text-ink-900 ring-1 ring-ink-300 placeholder:text-ink-500 focus:outline-none focus:ring-2 focus:ring-saffron-500';
$label = 'block font-mono text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-600';

// Small helpers for showing a field's value and error
function val($form, $i, $key) { return isset($form['passengers'][$i][$key]) ? $form['passengers'][$i][$key] : ''; }
function err($errors, $key) { return isset($errors[$key]) ? '<p class="field-error">' . e($errors[$key]) . '</p>' : ''; }
?>

<!-- Steps -->
<section class="bg-ink-950 text-paper-50">
  <div class="container-page py-6">
    <ol class="flex flex-wrap items-center gap-x-3 gap-y-2 font-mono text-xs font-semibold uppercase tracking-[0.15em]">
      <li class="flex items-center gap-2 text-ink-300"><span class="flex size-6 items-center justify-center rounded-full bg-ok-700 text-white"><?= icon('check', 'size-3.5') ?></span> Flight</li>
      <li class="h-px w-6 bg-ink-600" aria-hidden="true"></li>
      <li class="flex items-center gap-2 text-saffron-300" aria-current="step"><span class="flex size-6 items-center justify-center rounded-full bg-saffron-400 text-ink-950">2</span> Travellers</li>
      <li class="h-px w-6 bg-ink-600" aria-hidden="true"></li>
      <li class="flex items-center gap-2 text-ink-300"><span class="flex size-6 items-center justify-center rounded-full ring-1 ring-ink-500">3</span> Payment</li>
      <li class="h-px w-6 bg-ink-600" aria-hidden="true"></li>
      <li class="flex items-center gap-2 text-ink-300"><span class="flex size-6 items-center justify-center rounded-full ring-1 ring-ink-500">4</span> Ticket</li>
    </ol>
  </div>
</section>

<section class="container-page py-8 sm:py-12">
  <div class="grid gap-8 lg:grid-cols-[1fr_24rem]">

    <!-- Form -->
    <form method="post" action="flight-book.php?offer=<?= e($offer_id) ?>" novalidate class="space-y-6">
      <?= csrf_field() ?>
      <div>
        <h1 class="text-3xl font-extrabold sm:text-4xl">Who's travelling?</h1>
        <p class="mt-2 text-ink-700">Enter each name <b>exactly as it is on <?= $offer['needs_passport'] ? 'the passport' : 'their ID (passport, national ID card or driver\'s licence)' ?></b>. Airlines can refuse boarding if names don't match.</p>
      </div>

      <?php if ($notice): ?>
        <div class="rounded-xl bg-warn-100 p-4 text-warn-700" role="alert"><p class="font-semibold">The price has changed</p><p class="mt-1 text-sm"><?= e($notice) ?></p></div>
      <?php endif; ?>
      <?php if (isset($errors['form'])): ?>
        <div class="rounded-xl bg-bad-100 p-4 text-bad-700" role="alert"><?= e($errors['form']) ?></div>
      <?php elseif ($errors): ?>
        <div class="rounded-xl bg-bad-100 p-4 text-bad-700" role="alert"><p class="font-semibold">Please check the highlighted details.</p></div>
      <?php endif; ?>

      <?php foreach ($raw['passengers'] as $i => $p): ?>
        <fieldset class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-ink-900/5 sm:p-6">
          <legend class="sr-only">Traveller <?= $i + 1 ?></legend>
          <div class="flex items-center justify-between gap-3">
            <p class="font-display text-xl font-bold">Traveller <?= $i + 1 ?></p>
            <span class="rounded-full bg-paper-100 px-3 py-1 font-mono text-[11px] font-semibold uppercase tracking-wider text-ink-700"><?= $type_names[$p['type']] ?></span>
          </div>

          <div class="mt-5 grid gap-4 sm:grid-cols-[8rem_1fr_1fr]">
            <div>
              <label class="<?= $label ?>" for="p<?= $i ?>-title">Title</label>
              <select id="p<?= $i ?>-title" name="p[<?= $i ?>][title]" class="<?= $field ?>">
                <option value="">–</option>
                <?php foreach ($titles as $k => $t): ?><option value="<?= $k ?>" <?= val($form, $i, 'title') == $k ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?>
              </select>
              <?= err($errors, "p$i.title") ?>
            </div>
            <div>
              <label class="<?= $label ?>" for="p<?= $i ?>-given">First and middle names</label>
              <input id="p<?= $i ?>-given" name="p[<?= $i ?>][given_name]" class="<?= $field ?>" autocomplete="<?= $i == 0 ? 'given-name' : 'off' ?>" value="<?= e(val($form, $i, 'given_name')) ?>">
              <?= err($errors, "p$i.given_name") ?>
            </div>
            <div>
              <label class="<?= $label ?>" for="p<?= $i ?>-family">Surname</label>
              <input id="p<?= $i ?>-family" name="p[<?= $i ?>][family_name]" class="<?= $field ?>" autocomplete="<?= $i == 0 ? 'family-name' : 'off' ?>" value="<?= e(val($form, $i, 'family_name')) ?>">
              <?= err($errors, "p$i.family_name") ?>
            </div>
          </div>

          <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
              <label class="<?= $label ?>" for="p<?= $i ?>-born">Date of birth</label>
              <input id="p<?= $i ?>-born" type="date" name="p[<?= $i ?>][born_on]" max="<?= date('Y-m-d') ?>" class="<?= $field ?> num" value="<?= e(val($form, $i, 'born_on')) ?>">
              <?= err($errors, "p$i.born_on") ?>
            </div>
            <div>
              <span class="<?= $label ?>">Gender (as on <?= $offer['needs_passport'] ? 'passport' : 'ID' ?>)</span>
              <div class="mt-1 grid grid-cols-2 gap-2">
                <?php foreach (['m' => 'Male', 'f' => 'Female'] as $g => $gl): ?>
                  <label class="relative">
                    <input type="radio" name="p[<?= $i ?>][gender]" value="<?= $g ?>" class="peer sr-only" <?= val($form, $i, 'gender') == $g ? 'checked' : '' ?>>
                    <span class="block rounded-lg bg-paper-50 px-3.5 py-3 text-center font-medium ring-1 ring-ink-300 peer-checked:bg-ink-900 peer-checked:text-paper-50 peer-checked:ring-ink-900 peer-focus-visible:outline peer-focus-visible:outline-3 peer-focus-visible:outline-saffron-500"><?= $gl ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
              <?= err($errors, "p$i.gender") ?>
            </div>
          </div>

          <?php if ($offer['needs_passport']): ?>
            <div class="mt-5 border-t border-dashed border-paper-300 pt-5">
              <p class="font-mono text-[11px] font-semibold uppercase tracking-[0.16em] text-saffron-700">Passport</p>
              <div class="mt-3 grid gap-4 sm:grid-cols-3">
                <div>
                  <label class="<?= $label ?>" for="p<?= $i ?>-pp">Passport number</label>
                  <input id="p<?= $i ?>-pp" name="p[<?= $i ?>][passport_number]" class="<?= $field ?> uppercase" autocomplete="off" value="<?= e(val($form, $i, 'passport_number')) ?>">
                  <?= err($errors, "p$i.passport_number") ?>
                </div>
                <div>
                  <label class="<?= $label ?>" for="p<?= $i ?>-pc">Issued by</label>
                  <select id="p<?= $i ?>-pc" name="p[<?= $i ?>][passport_country]" class="<?= $field ?>">
                    <?php $chosen = val($form, $i, 'passport_country') ?: 'NG'; foreach ($countries as $c): ?>
                      <option value="<?= e($c['country_code']) ?>" <?= $chosen == $c['country_code'] ? 'selected' : '' ?>><?= e($c['country_name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <?= err($errors, "p$i.passport_country") ?>
                </div>
                <div>
                  <label class="<?= $label ?>" for="p<?= $i ?>-pe">Expiry date</label>
                  <input id="p<?= $i ?>-pe" type="date" name="p[<?= $i ?>][passport_expiry]" min="<?= date('Y-m-d') ?>" class="<?= $field ?> num" value="<?= e(val($form, $i, 'passport_expiry')) ?>">
                  <?= err($errors, "p$i.passport_expiry") ?>
                </div>
              </div>
              <p class="mt-3 text-xs text-ink-600">Many countries ask for a passport that is valid for 6 months after you arrive. Check before you book.</p>
            </div>
          <?php endif; ?>
        </fieldset>
      <?php endforeach; ?>

      <fieldset class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-ink-900/5 sm:p-6">
        <legend class="sr-only">Contact details</legend>
        <p class="font-display text-xl font-bold">Contact details</p>
        <p class="mt-1 text-sm text-ink-700">We send the e-ticket here, and the airline uses them if your flight changes.</p>
        <div class="mt-5 grid gap-4 sm:grid-cols-2">
          <div>
            <label class="<?= $label ?>" for="email">Email</label>
            <input id="email" type="email" name="email" autocomplete="email" class="<?= $field ?>" value="<?= e($form['email']) ?>">
            <?= err($errors, 'email') ?>
          </div>
          <div>
            <label class="<?= $label ?>" for="phone">Mobile number</label>
            <input id="phone" type="tel" name="phone" autocomplete="tel" placeholder="0803 123 4567" class="<?= $field ?> num" value="<?= e($form['phone']) ?>">
            <?= err($errors, 'phone') ?>
          </div>
        </div>
      </fieldset>

      <div class="rounded-2xl bg-ink-950 p-5 text-paper-50 sm:flex sm:items-center sm:justify-between sm:gap-6 sm:p-6">
        <div>
          <p class="font-mono text-[11px] uppercase tracking-[0.16em] text-ink-300">Total to pay</p>
          <p class="font-display text-3xl font-bold num"><?= naira($price['total_kobo']) ?></p>
          <p class="mt-1 text-xs text-ink-300">You'll pay securely on Paystack: card, bank transfer or USSD.</p>
        </div>
        <button type="submit" class="btn btn-primary mt-4 w-full px-7 py-4 text-base sm:mt-0 sm:w-auto"><?= icon('card', 'size-5') ?> Continue to payment</button>
      </div>
      <p class="text-xs text-ink-600">By continuing you agree to the airline's fare rules shown on this page. Your booking is only confirmed once payment is complete and the airline issues your ticket.</p>
    </form>

    <!-- Trip summary -->
    <aside class="lg:sticky lg:top-24 lg:self-start">
      <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-ink-900/5">
        <div class="flex items-center gap-3 border-b border-paper-200 p-5">
          <?php if ($offer['logo']): ?>
            <img src="<?= e($offer['logo']) ?>" alt="" class="size-9 object-contain">
          <?php else: ?>
            <span class="flex size-9 items-center justify-center rounded-md bg-ink-900 text-xs font-bold text-paper-50"><?= e($offer['airline_code']) ?></span>
          <?php endif; ?>
          <div>
            <p class="font-semibold"><?= e($offer['airline']) ?></p>
            <p class="text-xs text-ink-600"><?= plural($offer['passengers'], 'traveller') ?></p>
          </div>
        </div>
        <div class="space-y-4 p-5">
          <?php foreach ($offer['slices'] as $s): ?>
            <div>
              <p class="font-mono text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-600"><?= date('D j M Y', strtotime($s['depart'])) ?></p>
              <div class="mt-1 flex items-center justify-between gap-2">
                <p class="font-display text-xl font-bold num"><?= substr($s['depart'], 11, 5) ?> <span class="text-sm font-sans font-semibold text-ink-600"><?= e($s['from']) ?></span></p>
                <p class="text-center text-[11px] text-ink-600"><?= minutes_text($s['minutes']) ?><br><?= $s['stops'] ? plural($s['stops'], 'stop') : 'Direct' ?></p>
                <p class="text-right font-display text-xl font-bold num"><?= substr($s['arrive'], 11, 5) ?> <span class="text-sm font-sans font-semibold text-ink-600"><?= e($s['to']) ?></span></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <dl class="space-y-1.5 border-t border-dashed border-paper-300 bg-paper-50 p-5 text-sm">
          <div class="flex justify-between"><dt class="text-ink-700">Airline fare and taxes</dt><dd class="num"><?= naira($price['base_kobo']) ?></dd></div>
          <div class="flex justify-between"><dt class="text-ink-700">Our service fee</dt><dd class="num"><?= naira($price['markup_kobo']) ?></dd></div>
          <div class="flex justify-between border-t border-paper-300 pt-2 text-base font-bold"><dt>Total</dt><dd class="num"><?= naira($price['total_kobo']) ?></dd></div>
        </dl>
        <div class="space-y-1 p-5 text-xs text-ink-700">
          <p><b>Bags:</b> <?= $offer['checked_bags'] ? plural($offer['checked_bags'], 'checked bag') . ' each' : 'No checked bag' ?><?= $offer['carry_on'] ? ', plus a cabin bag' : '' ?></p>
          <p><b>Refunds:</b> <?= $offer['refund'] === null ? 'Ask us' : (!empty($offer['refund']['allowed']) ? 'Allowed, airline fee applies' : 'Not refundable') ?></p>
          <p><b>Changes:</b> <?= $offer['change'] === null ? 'Ask us' : (!empty($offer['change']['allowed']) ? 'Allowed, airline fee applies' : 'Not allowed') ?></p>
          <p class="pt-2 text-ink-600" id="hold" data-expires="<?= e($offer['expires_at']) ?>">The airline holds this price until <?= date('H:i', strtotime($offer['expires_at'])) ?>.</p>
        </div>
      </div>
    </aside>
  </div>
</section>

<script>
  // Count down the price hold, and send people back to search when it runs out
  (function () {
    var hold = document.getElementById('hold');
    var ends = new Date(hold.dataset.expires).getTime();
    function tick() {
      var left = Math.round((ends - Date.now()) / 1000);
      if (left <= 0) {
        hold.innerHTML = '<b class="text-bad-700">This price has expired.</b> <a class="link" href="flights.php">Search again</a>';
        document.querySelector('form button[type=submit]').disabled = true;
        return;
      }
      hold.textContent = 'The airline holds this price for ' + Math.floor(left / 60) + ':' + ('0' + left % 60).slice(-2) + ' more.';
      setTimeout(tick, 1000);
    }
    tick();
  })();
</script>

<?php include 'includes/footer.php'; ?>
