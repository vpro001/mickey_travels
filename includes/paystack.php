<?php
// Paystack (payments). What each call expects is written up in docs/API_NOTES.md.
// Amounts are always in kobo (₦1 = 100 kobo), which is what Paystack expects for NGN.

require_once __DIR__ . '/http.php';

function paystack_ready()
{
    return PAYSTACK_SECRET_KEY != '';
}

function paystack($method, $path, $body = null)
{
    $headers = [
        'Authorization' => 'Bearer ' . PAYSTACK_SECRET_KEY,
        'Accept'        => 'application/json',
    ];
    $r = http_request($method, 'https://api.paystack.co' . $path, $headers, $body, 30);
    $ok = $r['ok'] && !empty($r['data']['status']);
    return [
        'ok'      => $ok,
        'data'    => isset($r['data']['data']) ? $r['data']['data'] : null,
        'message' => isset($r['data']['message']) ? $r['data']['message'] : $r['error'],
    ];
}

// Start a payment. Returns ['ok' => true, 'data' => ['authorization_url' => ..., 'reference' => ...]]
// $reference may only contain letters, numbers, - . and =
function paystack_initialize($email, $amount_kobo, $reference, $callback_url, $metadata = [])
{
    return paystack('POST', '/transaction/initialize', [
        'email'        => $email,
        'amount'       => $amount_kobo,
        'currency'     => 'NGN',
        'reference'    => $reference,
        'callback_url' => $callback_url,
        'metadata'     => $metadata,
    ]);
}

// Ask Paystack what really happened to a payment. Never trust the browser or the webhook body alone.
function paystack_verify($reference)
{
    return paystack('GET', '/transaction/verify/' . rawurlencode($reference));
}

// Check the x-paystack-signature header: HMAC-SHA512 of the raw request body, using the secret key
function paystack_signature_ok($raw_body, $signature)
{
    if (!paystack_ready() || $signature == '') {
        return false;
    }
    return hash_equals(hash_hmac('sha512', $raw_body, PAYSTACK_SECRET_KEY), $signature);
}
