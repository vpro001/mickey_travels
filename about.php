<?php
require 'includes/init.php';

$title = 'About us';
include 'includes/header.php';
?>

<section class="relative isolate overflow-hidden bg-ink-900 text-paper-50">
  <?= photo('lekki-bridge', 'The Lekki-Ikoyi Link Bridge in Lagos', 'absolute inset-0 -z-20 h-full w-full object-cover object-[50%_35%]', false, [480, 800]) ?>
  <div class="absolute inset-0 -z-10 bg-gradient-to-r from-ink-950/95 via-ink-950/80 to-ink-950/30"></div>
  <div class="container-page py-14 sm:py-24">
    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-saffron-300">About us</p>
    <h1 class="mt-3 max-w-2xl text-3xl sm:text-5xl font-bold leading-tight">A Lagos travel agency that sorts the whole trip.</h1>
    <p class="mt-4 max-w-xl text-lg text-ink-100 leading-relaxed">We book flights and hotels, help with visa applications, and run holidays we've planned ourselves, all paid for in Naira.</p>
  </div>
</section>

<section class="container-page py-10 sm:py-14 max-w-3xl">

  <h2 class="text-xl font-bold">What we do</h2>
  <dl class="mt-4 grid gap-5 sm:grid-cols-2">
    <div>
      <dt class="font-semibold"><a class="link" href="flights.php">Flights</a></dt>
      <dd class="mt-1 text-ink-700">Fares from over 300 airlines, booked and ticketed here on our site.</dd>
    </div>
    <div>
      <dt class="font-semibold"><a class="link" href="hotels.php">Hotels</a></dt>
      <dd class="mt-1 text-ink-700">Rooms in Nigeria and abroad, with clear cancellation terms.</dd>
    </div>
    <div>
      <dt class="font-semibold"><a class="link" href="visa.php">Visa assistance</a></dt>
      <dd class="mt-1 text-ink-700">Document checks, forms, appointments and interview preparation, tracked online.</dd>
    </div>
    <div>
      <dt class="font-semibold"><a class="link" href="tours.php">Tours and holidays</a></dt>
      <dd class="mt-1 text-ink-700">Packages with set departure dates, which you can secure with a deposit.</dd>
    </div>
  </dl>

  <h2 class="mt-10 text-xl font-bold">Our story</h2>
  <p class="mt-3 text-ink-700 leading-relaxed">{{CLIENT_ABOUT_STORY}}</p>

  <h2 class="mt-10 text-xl font-bold">Licences and registration</h2>
  <p class="mt-3 text-ink-700"><?= e(contact('licences')) ?></p>

  <h2 class="mt-10 text-xl font-bold">Find us</h2>
  <p class="mt-3 text-ink-700"><?= e(contact('address')) ?></p>
  <p class="mt-1 text-ink-700"><?= e(contact('hours')) ?></p>
  <p class="mt-6"><a class="btn btn-dark" href="contact.php">Contact us</a></p>
</section>

<?php include 'includes/footer.php'; ?>
