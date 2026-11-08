<?php
// Paystack webhook: Paystack calls this URL in the background when a payment succeeds.
// Set it in the Paystack dashboard → Settings → API Keys & Webhooks:  https://<your site>/webhooks/paystack.php
//
// Safety rules (docs/API_NOTES.md → Paystack):
//  1. Reject anything without a valid x-paystack-signature (HMAC-SHA512 of the raw body).
//  2. Store each event once (unique key). A repeat of the same event is ignored.
//  3. Answer 200 straight away, then do the slow work.
//  4. Never trust the body: ask Paystack (verify) before confirming anything.

require '../includes/init.php';
require '../includes/booking.php';

$body = file_get_contents('php://input');
$signature = isset($_SERVER['HTTP_X_PAYSTACK_SIGNATURE']) ? $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] : '';

if ($_SERVER['REQUEST_METHOD'] != 'POST' || !paystack_signature_ok($body, $signature)) {
    write_log('webhooks', 'Paystack webhook rejected: bad signature', ['ip' => $_SERVER['REMOTE_ADDR']]);
    http_response_code(401);
    exit;
}

$event = json_decode($body, true);
$type = isset($event['event']) ? $event['event'] : '';
$reference = isset($event['data']['reference']) ? $event['data']['reference'] : '';
$key = $type . ':' . (isset($event['data']['id']) ? $event['data']['id'] : hash('sha256', $body));

// Save it once. If we've seen this event before, there's nothing more to do.
$stmt = db_query("INSERT IGNORE INTO webhook_events (provider, event_key, event_type, payload) VALUES ('paystack', ?, ?, ?)", [$key, $type, $body]);
$is_new = mysqli_stmt_affected_rows($stmt) == 1;
if (!$is_new) {
    // Seen before. Only skip it if it was handled successfully; otherwise try again
    // (confirm_payment() is safe to run more than once).
    $old = db_one("SELECT processed_at FROM webhook_events WHERE provider = 'paystack' AND event_key = ?", [$key]);
    $is_new = $old && $old['processed_at'] === null;
}

// Tell Paystack we got it, then carry on working
http_response_code(200);
echo 'OK';
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

if (!$is_new) {
    write_log('webhooks', 'Paystack webhook repeated (already handled), ignored', ['key' => $key]);
    exit;
}

if ($type == 'charge.success' && $reference != '') {
    $verify = paystack_verify($reference);
    if ($verify['ok']) {
        confirm_payment($reference, $verify['data']);
        db_query("UPDATE webhook_events SET processed_at = NOW(), process_error = NULL WHERE provider = 'paystack' AND event_key = ?", [$key]);
    } else {
        db_query("UPDATE webhook_events SET process_error = ? WHERE provider = 'paystack' AND event_key = ?", [substr('verify failed: ' . $verify['message'], 0, 500), $key]);
    }
} else {
    db_query("UPDATE webhook_events SET processed_at = NOW() WHERE provider = 'paystack' AND event_key = ?", [$key]);
}
