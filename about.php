<?php
require 'includes/init.php';

$title = 'About us';
include 'includes/header.php';
?>

<section class="relative isolate overflow-hidden bg-ink-900 text-paper-50">
  <?= photo('plane-wing', 'View over an aeroplane wing above the clouds', 'absolute inset-0 -z-20 h-full w-full object-cover object-[50%_35%]', false, [800, 1600]) ?>
  <div class="absolute inset-0 -z-10 bg-gradient-to-r from-ink-950/95 via-ink-950/80 to-ink-950/30"></div>
  <div class="container-page py-14 sm:py-24">
    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-saffron-300">About us · Your window to the world</p>
    <h1 class="mt-3 max-w-2xl text-3xl sm:text-5xl font-bold leading-tight">Global travel made simple, accessible and stress-free.</h1>
    <p class="mt-4 max-w-xl text-lg text-ink-100 leading-relaxed">Mickey Travels &amp; Tours is a travel and tourism agency in Port Harcourt, looking after individuals, families and companies.</p>
  </div>
</section>

<section class="container-page py-10 sm:py-14 max-w-3xl">

  <h2 class="text-xl font-bold">Who we are</h2>
  <p class="mt-3 text-ink-700 leading-relaxed">Mickey Travels &amp; Tours is a dynamic travel and tourism agency committed to making global travel simple, accessible, and stress-free. We provide complete travel solutions to individuals, families and corporate clients, combining professionalism, reliability and personalised customer care.</p>
  <p class="mt-3 text-ink-700 leading-relaxed">Our passion is connecting people to destinations, opportunities and experiences around the world. With a strong commitment to excellence and customer satisfaction, we work to make every journey seamless, memorable and worthwhile.</p>

  <h2 class="mt-10 text-xl font-bold">Our services</h2>
  <dl class="mt-4 grid gap-5 sm:grid-cols-2">
    <?php
    // [name, short description, link]
    $services = [
        ['Air ticketing and reservations', 'Local and international fares, which you can search and book here on our site.', 'flights.php'],
        ['Corporate travel', 'Flights, hotels and visas for your staff, handled by one team.', 'contact.php?topic=corporate'],
        ['Hotel reservations', 'Rooms in Nigeria and abroad, with clear cancellation terms.', 'hotels.php'],
        ['Medical tourism', 'Travel, accommodation and paperwork for treatment abroad.', 'contact.php?topic=medical'],
        ['Visa procurement', 'Document checks, forms, appointments and interview preparation, tracked online.', 'visa.php'],
        ['Study abroad and student visas', 'Help with student visa applications for international studies.', 'contact.php?topic=study'],
        ['Airport logistics', 'Transfers, meet-and-greet and help at the airport.', 'contact.php?topic=airport'],
        ['Air and sea cargo', 'Help sending goods abroad by air or by sea.', 'contact.php?topic=cargo'],
        ['International passports', 'Help applying for or renewing your international passport.', 'contact.php?topic=passport'],
    ];
    foreach ($services as $service): ?>
      <div>
        <dt class="font-semibold"><a class="link" href="<?= $service[2] ?>"><?= $service[0] ?></a></dt>
        <dd class="mt-1 text-ink-700"><?= $service[1] ?></dd>
      </div>
    <?php endforeach; ?>
  </dl>
  <p class="mt-5 text-ink-700">We also run <a class="link" href="tours.php">tours and holiday packages</a> with set departure dates.</p>

  <?php if (setting('contact_licences')): ?>
    <h2 class="mt-10 text-xl font-bold">Licences and registration</h2>
    <p class="mt-3 text-ink-700"><?= e(contact('licences')) ?></p>
  <?php endif; ?>

  <h2 class="mt-10 text-xl font-bold">Find us</h2>
  <p class="mt-3 text-ink-700"><?= e(contact('address')) ?></p>
  <p class="mt-1 text-ink-700"><?= e(contact('hours')) ?></p>
  <p class="mt-1 text-ink-700"><?= e(contact('phone')) ?> · <?= e(contact('email')) ?></p>
  <p class="mt-6"><a class="btn btn-dark" href="contact.php">Contact us</a></p>
</section>

<?php include 'includes/footer.php'; ?>
