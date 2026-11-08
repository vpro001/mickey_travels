<?php
// Included at the top of every page:  require 'includes/init.php';
// Loads the config, connects to the database and starts the session.

require __DIR__ . '/../config.php';
require __DIR__ . '/functions.php';

date_default_timezone_set('Africa/Lagos');

// Show errors while developing, log them on the live site.
error_reporting(E_ALL);
ini_set('display_errors', DEBUG ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../storage/logs/php-errors.log');

// If something breaks, log it and show a friendly message instead of a blank or technical page
set_exception_handler(function ($e) {
    $id = substr(md5(uniqid('', true)), 0, 8);
    error_log("[$id] " . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) http_response_code(500);
    echo DEBUG
        ? '<pre>' . htmlspecialchars($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()) . '</pre>'
        : '<div style="font-family:sans-serif;max-width:32rem;margin:4rem auto;padding:0 1rem"><h1>Something went wrong on our side</h1>'
          . '<p>Please try again in a moment. If it keeps happening, contact us and quote reference <b>' . $id . '</b>.</p>'
          . '<p><a href="index.php">Go to the homepage</a></p></div>';
});

// Database (mysqli throws an error if a query fails, so nothing fails silently)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
mysqli_set_charset($conn, 'utf8mb4');
mysqli_query($conn, "SET time_zone = '+01:00'");

// Session (cookie can't be read by JavaScript and isn't sent from other sites)
session_set_cookie_params(array(
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => COOKIE_SECURE,
    'httponly' => true,
    'samesite' => 'Lax',
));
session_start();
