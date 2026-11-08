<?php
require 'includes/init.php';
http_response_code(404);

$title = 'Page not found';
include 'includes/header.php';
?>

<section class="container-page py-16 max-w-xl">
  <p class="text-sm font-semibold text-saffron-700">Error 404</p>
  <h1 class="mt-2 text-3xl font-bold">We can't find that page</h1>
  <p class="mt-3 text-ink-700">The link may be old or mistyped.</p>
  <p class="mt-8"><a class="btn btn-dark" href="index.php">Go to the homepage</a></p>
</section>

<?php include 'includes/footer.php'; ?>
