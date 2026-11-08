<?php
require 'includes/init.php';

// Find a booking with its reference and email
$ref = strtoupper(get('ref'));
$email = get('email');
$error = '';

if ($ref != '' || $email != '') {
    if (db_one("SELECT id FROM flight_bookings WHERE booking_ref = ? AND contact_email = ?", [$ref, $email])) {
        redirect('booking.php?ref=' . urlencode($ref) . '&email=' . urlencode($email));
    }
    if (db_one("SELECT id FROM hotel_bookings WHERE booking_ref = ? AND contact_email = ?", [$ref, $email])) {
        redirect('hotel-booking.php?ref=' . urlencode($ref) . '&email=' . urlencode($email));
    }
    if (db_one("SELECT id FROM tour_bookings WHERE booking_ref = ? AND email = ?", [$ref, $email])) {
        redirect('tour-booking.php?ref=' . urlencode($ref) . '&email=' . urlencode($email));
    }
    // Same message whether the reference exists or not
    $error = "We couldn't find a booking with that reference and email. Check both and try again.";
}

$title = 'Manage booking';
include 'includes/header.php';
?>
<section class="container-page py-16 sm:py-24">
  <div class="mx-auto max-w-lg">
    <p class="eyebrow text-saffron-700">Manage booking</p>
    <h1 class="mt-3 text-4xl font-extrabold sm:text-5xl">Find your booking</h1>
    <p class="mt-3 text-ink-700">Use the reference from your confirmation email (MTF- for flights, MTH- for hotels, MTT- for tours) and the email address you booked with.</p>

    <?php if ($error): ?>
      <p class="mt-6 rounded-xl bg-bad-100 p-4 text-sm text-bad-700" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="get" class="mt-8 space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5">
      <div>
        <label class="field-label" for="ref">Booking reference</label>
        <input id="ref" name="ref" class="field font-mono uppercase tracking-wider" placeholder="MTF-XXXXXXXX or MTT-XXXXXXXX" value="<?= e($ref) ?>" required>
      </div>
      <div>
        <label class="field-label" for="email">Email</label>
        <input id="email" name="email" type="email" class="field" autocomplete="email" value="<?= e($email) ?>" required>
      </div>
      <button class="btn btn-dark w-full py-3">Find booking</button>
    </form>
    <p class="mt-4 text-sm text-ink-600">Applied for a visa? <a class="link" href="visa-track.php">Track your visa application</a>.</p>
  </div>
</section>
<?php include 'includes/footer.php'; ?>
