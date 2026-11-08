<?php
// Copy this file to config.php and fill in your own values.
// config.php holds your real passwords and keys, so never share or commit it.

define('SITE_NAME', "Mickey's Travel and Tour");
// Full web address of the site, no slash at the end (used in payment and email links)
define('SITE_URL', 'https://www.example.com');
// A long random secret for signing links (email verification). Make one with:
//   php -r "echo bin2hex(random_bytes(32));"
define('APP_SECRET', '');
define('DEBUG', false);              // true shows PHP errors on screen; only on your own computer

// Database
define('DB_HOST', 'localhost');
define('DB_USER', 'mickey');
define('DB_PASS', '');
define('DB_NAME', 'mickey');

// Set to false only when testing on http://localhost (the live site must use https)
define('COOKIE_SECURE', true);

// 'live' = real Duffel API, 'demo' = sample results from database/fixtures (shows a "Demo data" banner)
define('FLIGHT_DATA', 'live');

// Duffel (flights and hotels): https://app.duffel.com
define('DUFFEL_TOKEN', '');          // duffel_test_... or duffel_live_...
define('DUFFEL_WEBHOOK_SECRET', '');

// Paystack (payments): https://dashboard.paystack.com
define('PAYSTACK_SECRET_KEY', '');   // sk_test_... or sk_live_...
define('PAYSTACK_PUBLIC_KEY', '');   // pk_test_... or pk_live_...

// Email: 'smtp', 'mail' (PHP mail function) or 'log' (save emails to storage/logs instead of sending)
define('MAIL_METHOD', 'log');
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('MAIL_FROM', 'no-reply@example.com');
define('ADMIN_EMAIL', '');           // receives alerts when something needs a person to look at it
