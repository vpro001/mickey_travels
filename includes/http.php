<?php
// http_request(): sends a request to an outside API (Duffel, Paystack) with cURL.
//
//   $result = http_request('GET', 'https://api.duffel.com/places/suggestions?query=lagos', $headers);
//   $result = http_request('POST', $url, $headers, $data);    // $data is sent as JSON
//
// Returns: ['ok' => true/false, 'status' => 200, 'data' => decoded JSON, 'request_id' => ...]
//
// GET requests that fail with 429/503/504 (busy or rate-limited) are tried up to 3 times.
// POST requests are never repeated: Duffel's docs warn that repeating a booking
// request can create a second booking.

function http_request($method, $url, $headers = [], $data = null, $timeout = 30)
{
    $header_lines = [];
    foreach ($headers as $name => $value) {
        $header_lines[] = "$name: $value";
    }
    if ($data !== null) {
        $header_lines[] = 'Content-Type: application/json';
    }

    $tries = $method === 'GET' ? 3 : 1;

    for ($try = 1; $try <= $tries; $try++) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header_lines);
        curl_setopt($ch, CURLOPT_ENCODING, '');       // accept gzip, unzip automatically
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        if (!in_array($status, [0, 429, 503, 504]) || $try == $tries) {
            break;
        }
        sleep($try);   // wait 1 second, then 2, before trying again
    }

    $head = substr((string) $response, 0, $header_size);
    $body = substr((string) $response, $header_size);
    preg_match('/x-request-id:\s*(\S+)/i', $head, $m);
    $request_id = isset($m[1]) ? $m[1] : '';

    $result = [
        'ok'         => $status >= 200 && $status < 300,
        'status'     => $status,
        'data'       => json_decode($body, true),
        'error'      => $error,
        'request_id' => $request_id,   // quote this to Duffel/Paystack support
    ];

    // Log every call (without the query string, which can contain customer details)
    $log = ['status' => $status, 'tries' => $try, 'request_id' => $request_id];
    if (!$result['ok']) {
        $log['error'] = $error;
        $log['response'] = substr($body, 0, 1000);
    }
    write_log('api', $method . ' ' . strtok($url, '?'), $log);

    return $result;
}
