</main>

<footer class="print:hidden relative mt-20 overflow-hidden bg-ink-950 text-ink-200">
  <div class="container-page pt-16 pb-24">
    <div class="grid gap-10 lg:grid-cols-[1.4fr_1fr_1fr_1.2fr]">
      <div>
        <img src="assets/img/logo-192.png" alt="Mickey Travels" width="80" height="80" class="mb-6 size-20 rounded-full">
        <p class="eyebrow text-saffron-300">Visit us</p>
        <p class="mt-3 max-w-xs text-lg leading-relaxed text-paper-50"><?= e(contact('address')) ?></p>
        <p class="mt-2 text-sm"><?= e(contact('hours')) ?></p>
      </div>
      <div>
        <p class="eyebrow text-ink-300">Book</p>
        <ul class="mt-3 space-y-2 text-sm">
          <li><a class="hover:text-saffron-300" href="flights.php">Flights</a></li>
          <li><a class="hover:text-saffron-300" href="hotels.php">Hotels</a></li>
          <li><a class="hover:text-saffron-300" href="visa.php">Visa services</a></li>
          <li><a class="hover:text-saffron-300" href="tours.php">Tours &amp; holidays</a></li>
        </ul>
      </div>
      <div>
        <p class="eyebrow text-ink-300">Help</p>
        <ul class="mt-3 space-y-2 text-sm">
          <li><a class="hover:text-saffron-300" href="manage-booking.php">Manage booking</a></li>
          <li><a class="hover:text-saffron-300" href="visa-track.php">Track visa application</a></li>
          <li><a class="hover:text-saffron-300" href="contact.php">Contact us</a></li>
          <li><a class="hover:text-saffron-300" href="about.php">About us</a></li>
        </ul>
      </div>
      <div>
        <p class="eyebrow text-ink-300">Talk to us</p>
        <p class="mt-3 text-sm">Phone <span class="num text-paper-50"><?= e(contact('phone')) ?></span></p>
        <p class="mt-1 text-sm">Email <a class="text-paper-50 hover:text-saffron-300" href="mailto:<?= e(contact('email')) ?>"><?= e(contact('email')) ?></a></p>
        <?php $facebook = setting('contact_facebook'); $instagram = ltrim(setting('contact_instagram'), '@'); ?>
        <?php if ($facebook || $instagram): ?>
          <p class="mt-3 flex gap-4 text-sm">
            <?php if ($facebook): ?><a class="text-paper-50 underline decoration-saffron-400 underline-offset-4 hover:text-saffron-300" href="<?= e($facebook) ?>" target="_blank" rel="noopener">Facebook</a><?php endif; ?>
            <?php if ($instagram): ?><a class="text-paper-50 underline decoration-saffron-400 underline-offset-4 hover:text-saffron-300" href="https://www.instagram.com/<?= e($instagram) ?>/" target="_blank" rel="noopener">Instagram</a><?php endif; ?>
          </p>
        <?php endif; ?>
        <p class="mt-4 text-xs text-ink-300">Licences: <?= e(contact('licences')) ?></p>
        <p class="mt-4 inline-flex items-center gap-2 rounded-full bg-white/5 px-3 py-1.5 text-xs text-ink-200 ring-1 ring-white/10"><?= icon('card', 'size-4 text-saffron-300') ?> Payments secured by Paystack</p>
      </div>
    </div>

    <!-- Big wordmark -->
    <p class="mt-16 select-none font-display text-[22vw] font-extrabold leading-[0.8] tracking-[-0.06em] text-white/[0.04] lg:text-[13rem]" aria-hidden="true">Mickey</p>

    <div class="flex flex-col gap-2 border-t border-white/10 pt-6 text-xs text-ink-300 sm:flex-row sm:justify-between">
      <p>© <?= date('Y') ?> Mickey Travels &amp; Tours · Your window to the world. Prices are shown in Naira (₦).</p>
      <p>Photos: Unsplash contributors</p>
    </div>
  </div>
</footer>

<?php
// WhatsApp chat button. Until the client gives us their number it shows a placeholder
// and links to the contact page.
$whatsapp = preg_replace('/\D/', '', setting('contact_whatsapp'));
?>
<a href="<?= $whatsapp ? 'https://wa.me/' . $whatsapp : 'contact.php' ?>" <?= $whatsapp ? 'target="_blank" rel="noopener"' : '' ?>
   class="print:hidden fixed bottom-4 right-4 z-40 inline-flex items-center gap-2 rounded-full bg-ok-700 py-3 pl-3.5 pr-4 text-sm font-semibold text-white shadow-lg shadow-ok-700/30 transition hover:-translate-y-0.5 hover:bg-[#185a3b]">
  <?= icon('chat') ?>
  <span><?= $whatsapp ? 'Chat on WhatsApp' : 'WhatsApp {{CLIENT_WHATSAPP}}' ?></span>
</a>
<script src="assets/js/site.js" defer></script>
</body>
</html>
