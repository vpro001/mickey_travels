<?php
require 'includes/init.php';
require 'includes/stays.php';
require 'includes/paystack.php';
require 'includes/hotel-search.php';

// Guest details for one hotel rate, then off to Paystack.
// We ask Duffel for a quote (a confirmed price) when the page opens and again when the form is
// sent, so the customer never pays a price the hotel no longer offers.

$rate_id = get('rate');
$srr = get('srr');
$search = read_hotel_search($_GET);
$error = '';
if (!preg_match('/^rat_[A-Za-z0-9_]{4,60}$/', $rate_id) || $search['error']) {
    $error = 'Please search again.';
} else {
    $q = stays_quote($rate_id);
    if (!$q['ok']) {
        $error = 'This room is no longer available at that price. Please choose another.';
    }
}
if (!$error) {
    $quote = $q['data'];
    $price = hotel_price($quote['total_amount'], $quote['total_currency'], $search['rooms']);
    if (!$price) $error = "We can't price this room in Naira right now. Please contact us.";
}
if ($error) {
    $title = 'Room not available';
    include 'includes/header.php'; ?>
    <section class="container-page max-w-xl py-20">
      <h1 class="text-4xl font-extrabold">Let's find another room</h1>
      <p class="mt-3 text-lg text-ink-700"><?= e($error) ?></p>
      <div class="mt-6 flex gap-3">
        <?php if ($srr && !$search['error']): ?><a class="btn btn-dark px-5 py-3" href="hotel.php?id=<?= e($srr) ?>&<?= e(hotel_search_query($search)) ?>">Back to the rooms</a><?php endif; ?>
        <a class="btn btn-ghost px-5 py-3" href="hotels.php">New search</a>
      </div>
    </section>
    <?php include 'includes/footer.php';
    exit;
}

$acc = $quote['accommodation'];
$room = isset($acc['rooms'][0]) ? $acc['rooms'][0] : ['name' => 'Room', 'rates' => [[]]];
$rate = isset($room['rates'][0]) ? $room['rates'][0] : [];
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    $_SESSION['hotel_quote'][$rate_id] = $price['total_kobo'];
}

$f = ['email' => '', 'phone' => '', 'requests' => '', 'guests' => []];
$errors = [];
$notice = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_check();
    $f['email'] = post('email');
    $f['phone'] = post('phone');
    $f['requests'] = substr(post('requests'), 0, 500);
    $names = [];
    for ($i = 0; $i < $search['rooms']; $i++) {
        $given = isset($_POST['given'][$i]) ? trim((string) $_POST['given'][$i]) : '';
        $family = isset($_POST['family'][$i]) ? trim((string) $_POST['family'][$i]) : '';
        $f['guests'][$i] = [$given, $family];
        if (!preg_match("/^[A-Za-z][A-Za-z '\-]{0,49}$/", $given) || !preg_match("/^[A-Za-z][A-Za-z '\-]{0,49}$/", $family)) {
            $errors["guest$i"] = 'Enter the first name and surname (English letters only).';
        }
        $names[] = ['given_name' => $given, 'family_name' => $family];
    }
    if (!valid_email($f['email'])) $errors['email'] = 'Enter an email address. The confirmation is sent there.';
    if (clean_phone($f['phone']) == '') $errors['phone'] = 'Enter a phone number, e.g. 0803 123 4567.';
    if (!$errors && !paystack_ready()) $errors['form'] = "Online payment isn't switched on yet. Please contact us to finish this booking.";

    if (!$errors && $price['total_kobo'] != $_SESSION['hotel_quote'][$rate_id]) {
        $notice = 'The hotel has changed the price from ' . naira($_SESSION['hotel_quote'][$rate_id]) . ' to ' . naira($price['total_kobo']) . '. Please check it and press the button again.';
        $_SESSION['hotel_quote'][$rate_id] = $price['total_kobo'];
    }

    if (!$errors && !$notice) {
        $ref = make_reference('MTH');
        $reference = $ref . '-' . bin2hex(random_bytes(3));
        $hotel = ['name' => $acc['name'], 'address' => isset($acc['location']['address']['line_one']) ? $acc['location']['address']['line_one'] : '',
            'city' => $search['city'], 'rating' => isset($acc['rating']) ? $acc['rating'] : 0, 'photo' => isset($acc['photos'][0]['url']) ? $acc['photos'][0]['url'] : '',
            'room' => $room['name'], 'board' => isset($rate['board_type']) ? $rate['board_type'] : '',
            'cancellation' => cancellation_text(isset($rate['cancellation_timeline']) ? $rate['cancellation_timeline'] : [], $quote['total_amount'])];
        db_begin();
        $id = db_insert('hotel_bookings', [
            'booking_ref' => $ref, 'user_id' => user_id_or_null(), 'contact_email' => $f['email'], 'contact_phone' => clean_phone($f['phone']),
            'search_result_id' => $srr, 'rate_id' => $rate_id, 'quote_id' => $quote['id'],
            'accommodation_json' => json_encode($hotel), 'guests_json' => json_encode(['names' => $names, 'requests' => $f['requests'], 'adults' => $search['adults'], 'children' => $search['children']]),
            'check_in_date' => $search['check_in'], 'check_out_date' => $search['check_out'], 'rooms' => $search['rooms'],
            'supplier_currency' => $price['supplier_currency'], 'supplier_amount' => $price['supplier_amount'], 'fx_rate' => $price['fx_rate'],
            'fx_buffer_pct' => $price['fx_buffer_pct'], 'base_kobo' => $price['base_kobo'], 'markup_type' => $price['markup_type'],
            'markup_value' => $price['markup_value'], 'markup_kobo' => $price['markup_kobo'], 'total_kobo' => $price['total_kobo'],
        ]);
        db_insert('payments', ['reference' => $reference, 'booking_type' => 'hotel', 'booking_id' => $id, 'amount_kobo' => $price['total_kobo'], 'currency' => 'NGN', 'email' => $f['email']]);
        db_commit();

        $_SESSION['my_hotels'][] = $ref;
        $pay = paystack_initialize($f['email'], $price['total_kobo'], $reference, SITE_URL . '/payment-callback.php', ['booking_ref' => $ref, 'booking_type' => 'hotel']);
        if ($pay['ok']) {
            db_query("UPDATE payments SET authorization_url = ? WHERE reference = ?", [$pay['data']['authorization_url'], $reference]);
            header('Location: ' . $pay['data']['authorization_url']);
            exit;
        }
        write_log('payments', 'Paystack initialize failed (hotel)', ['reference' => $reference, 'message' => $pay['message']]);
        $errors['form'] = "We couldn't open the payment page. Your booking $ref is saved; please try again in a moment.";
    }
}

$title = 'Guest details';
$page = 'hotels';
include 'includes/header.php';
$field = 'mt-1 block w-full rounded-lg border-0 bg-paper-50 px-3.5 py-3 text-base ring-1 ring-ink-300 focus:outline-none focus:ring-2 focus:ring-saffron-500';
$label = 'block font-mono text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-600';
$photo = isset($acc['photos'][0]['url']) ? $acc['photos'][0]['url'] : '';
?>
<section class="container-page py-8 sm:py-12">
  <div class="grid gap-8 lg:grid-cols-[1fr_24rem]">
    <form method="post" novalidate class="space-y-6">
      <?= csrf_field() ?>
      <div>
        <p class="eyebrow text-saffron-700">Hotel booking</p>
        <h1 class="mt-2 text-3xl font-extrabold sm:text-4xl">Who's staying?</h1>
        <p class="mt-2 text-ink-700">One name per room, as it is on the guest's ID. They'll need it at check-in.</p>
      </div>
      <?php if ($notice): ?><div class="rounded-xl bg-warn-100 p-4 text-warn-700" role="alert"><p class="font-semibold">The price has changed</p><p class="mt-1 text-sm"><?= e($notice) ?></p></div><?php endif; ?>
      <?php if (isset($errors['form'])): ?><div class="rounded-xl bg-bad-100 p-4 text-bad-700" role="alert"><?= e($errors['form']) ?></div>
      <?php elseif ($errors): ?><div class="rounded-xl bg-bad-100 p-4 text-bad-700" role="alert">Please check the highlighted details.</div><?php endif; ?>

      <?php for ($i = 0; $i < $search['rooms']; $i++): $g = isset($f['guests'][$i]) ? $f['guests'][$i] : ['', '']; ?>
        <fieldset class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-ink-900/5 sm:p-6">
          <legend class="sr-only">Room <?= $i + 1 ?></legend>
          <p class="font-display text-xl font-bold">Room <?= $i + 1 ?></p>
          <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div><label class="<?= $label ?>" for="given<?= $i ?>">First name</label><input id="given<?= $i ?>" name="given[]" class="<?= $field ?>" value="<?= e($g[0]) ?>" <?= $i == 0 ? 'autocomplete="given-name"' : '' ?>></div>
            <div><label class="<?= $label ?>" for="family<?= $i ?>">Surname</label><input id="family<?= $i ?>" name="family[]" class="<?= $field ?>" value="<?= e($g[1]) ?>" <?= $i == 0 ? 'autocomplete="family-name"' : '' ?>></div>
          </div>
          <?= isset($errors["guest$i"]) ? '<p class="field-error">' . e($errors["guest$i"]) . '</p>' : '' ?>
        </fieldset>
      <?php endfor; ?>

      <fieldset class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-ink-900/5 sm:p-6">
        <legend class="sr-only">Contact</legend>
        <p class="font-display text-xl font-bold">Contact details</p>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
          <div><label class="<?= $label ?>" for="email">Email</label><input id="email" type="email" name="email" autocomplete="email" class="<?= $field ?>" value="<?= e($f['email']) ?>"><?= isset($errors['email']) ? '<p class="field-error">' . e($errors['email']) . '</p>' : '' ?></div>
          <div><label class="<?= $label ?>" for="phone">Mobile number</label><input id="phone" type="tel" name="phone" autocomplete="tel" placeholder="0803 123 4567" class="<?= $field ?> num" value="<?= e($f['phone']) ?>"><?= isset($errors['phone']) ? '<p class="field-error">' . e($errors['phone']) . '</p>' : '' ?></div>
          <div class="sm:col-span-2"><label class="<?= $label ?>" for="requests">Requests for the hotel <span class="normal-case tracking-normal font-normal">(optional, not guaranteed)</span></label><textarea id="requests" name="requests" rows="2" class="<?= $field ?>" placeholder="e.g. Late check-in around 11pm"><?= e($f['requests']) ?></textarea></div>
        </div>
      </fieldset>

      <div class="rounded-2xl bg-ink-950 p-5 text-paper-50 sm:flex sm:items-center sm:justify-between sm:gap-6 sm:p-6">
        <div>
          <p class="font-mono text-[11px] uppercase tracking-[0.16em] text-ink-300">Total to pay</p>
          <p class="font-display text-3xl font-bold num"><?= naira($price['total_kobo']) ?></p>
          <p class="mt-1 text-xs text-ink-300">Pay securely on Paystack: card, bank transfer or USSD.</p>
        </div>
        <button class="btn btn-primary mt-4 w-full px-7 py-4 text-base sm:mt-0 sm:w-auto"><?= icon('card', 'size-5') ?> Continue to payment</button>
      </div>
    </form>

    <aside class="lg:sticky lg:top-24 lg:self-start">
      <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-ink-900/5">
        <?php if ($photo): ?><img src="<?= e($photo) ?>" alt="" class="aspect-[16/9] w-full object-cover"><?php endif; ?>
        <div class="p-5">
          <p class="text-sm text-saffron-600"><?= str_repeat('★', isset($acc['rating']) ? (int) $acc['rating'] : 0) ?></p>
          <p class="font-display text-xl font-bold"><?= e($acc['name']) ?></p>
          <p class="mt-1 text-sm text-ink-700"><?= e($room['name']) ?> · <?= e(board_name(isset($rate['board_type']) ? $rate['board_type'] : '')) ?></p>
          <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
            <div><dt class="<?= $label ?>">Check-in</dt><dd class="mt-0.5 font-semibold num"><?= date('D j M Y', strtotime($search['check_in'])) ?></dd></div>
            <div><dt class="<?= $label ?>">Check-out</dt><dd class="mt-0.5 font-semibold num"><?= date('D j M Y', strtotime($search['check_out'])) ?></dd></div>
          </dl>
          <p class="mt-4 text-sm <?= isset($rate['cancellation_timeline']) && $rate['cancellation_timeline'] ? 'text-ok-700' : 'text-ink-600' ?>"><?= e(cancellation_text(isset($rate['cancellation_timeline']) ? $rate['cancellation_timeline'] : [], $quote['total_amount'])) ?></p>
        </div>
        <dl class="space-y-1.5 border-t border-dashed border-paper-300 bg-paper-50 p-5 text-sm">
          <div class="flex justify-between"><dt class="text-ink-700">Hotel price (<?= plural($search['nights'], 'night') ?>, <?= plural($search['rooms'], 'room') ?>)</dt><dd class="num"><?= naira($price['base_kobo']) ?></dd></div>
          <div class="flex justify-between"><dt class="text-ink-700">Our service fee</dt><dd class="num"><?= naira($price['markup_kobo']) ?></dd></div>
          <div class="flex justify-between border-t border-paper-300 pt-2 text-base font-bold"><dt>Total</dt><dd class="num"><?= naira($price['total_kobo']) ?></dd></div>
        </dl>
      </div>
    </aside>
  </div>
</section>
<?php include 'includes/footer.php'; ?>
