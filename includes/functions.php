<?php
// Functions used across the site.


// ------------------------------------------------------------ database
// Every query uses a prepared statement. Examples:
//   $rows = db_all("SELECT * FROM tour_packages WHERE is_published = ?", [1]);
//   $user = db_one("SELECT * FROM users WHERE email = ?", [$email]);   // one row or null
//   db_query("UPDATE payments SET status = ? WHERE id = ?", ['success', $id]);
//   $id = db_insert('enquiries', ['name' => $name, 'email' => $email]);

function db_query($sql, $params = [])
{
    global $conn;
    $stmt = mysqli_prepare($conn, $sql);
    if ($params) {
        $types = '';
        foreach ($params as $p) {
            $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
        }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    return $stmt;
}

function db_all($sql, $params = [])
{
    $result = mysqli_stmt_get_result(db_query($sql, $params));
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function db_one($sql, $params = [])
{
    $result = mysqli_stmt_get_result(db_query($sql, $params));
    return mysqli_fetch_assoc($result);
}

function db_insert($table, $data)
{
    $columns = implode(', ', array_keys($data));
    $marks = implode(', ', array_fill(0, count($data), '?'));
    $stmt = db_query("INSERT INTO $table ($columns) VALUES ($marks)", array_values($data));
    return mysqli_stmt_insert_id($stmt);
}

// Transactions, for every booking and payment change:
//   db_begin();  ...queries...  db_commit();   (or db_rollback() if something fails)
function db_begin()
{
    global $conn;
    mysqli_begin_transaction($conn);
}

function db_commit()
{
    global $conn;
    mysqli_commit($conn);
}

function db_rollback()
{
    global $conn;
    mysqli_rollback($conn);
}


// ------------------------------------------------------------ output

// Escape text before printing it in HTML. Use it on everything you echo.
function e($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function redirect($page)
{
    header('Location: ' . $page);
    exit;
}

// Inline SVG icon from assets/icons/<name>.svg
function icon($name, $class = 'size-5')
{
    $svg = file_get_contents(__DIR__ . '/../assets/icons/' . $name . '.svg');
    return str_replace('<svg ', '<svg class="' . $class . '" aria-hidden="true" ', $svg);
}


// A photo from assets/img/. Each photo is saved in two widths (e.g. london-480.webp and
// london-800.webp) and the browser downloads whichever suits the screen.
//   photo('london', 'Tower Bridge in London', 'h-48 w-full object-cover')
// Use $lazy = false for the first big image on a page, so it shows straight away.
function photo($name, $alt, $class = '', $lazy = true, $sizes = [480, 800], $display = '100vw')
{
    // Our own photos are .webp; photos uploaded in the admin are .jpg
    $ext = file_exists(__DIR__ . "/../assets/img/$name-" . end($sizes) . '.webp') ? 'webp' : 'jpg';
    $srcset = [];
    foreach ($sizes as $w) {
        $srcset[] = "assets/img/$name-$w.$ext {$w}w";
    }
    return '<img src="assets/img/' . $name . '-' . end($sizes) . '.' . $ext . '" srcset="' . implode(', ', $srcset) . '"'
        . ' sizes="' . $display . '" alt="' . e($alt) . '" class="' . $class . '"'
        . ($lazy ? ' loading="lazy" decoding="async"' : ' fetchpriority="high"') . '>';
}


// ------------------------------------------------------------ forms

// Put csrf_field() inside every <form method="post">,
// and call csrf_check() before handling the form.
function csrf_field()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf'] . '">';
}

function csrf_check()
{
    $sent = isset($_POST['csrf']) ? $_POST['csrf'] : '';
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(403);
        die('Your session expired. Please go back, refresh the page and try again.');
    }
}

// A message shown once on the next page, e.g. "Thank you, we got your message".
function flash($message, $type = 'success')
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

// Trimmed form value, or '' if it's missing.
function post($key)
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
}

function get($key)
{
    return isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : '';
}

function valid_email($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// A real date written as YYYY-MM-DD
function valid_date($date)
{
    $parts = explode('-', $date);
    return count($parts) === 3 && checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
}

// Phone number in international format: 0803 123 4567 -> +2348031234567.
// Returns '' if it doesn't look like a phone number.
function clean_phone($phone)
{
    $digits = preg_replace('/[^\d+]/', '', $phone);
    if (preg_match('/^0([789][01]\d{8})$/', $digits, $m)) {
        return '+234' . $m[1];
    }
    if (preg_match('/^234([789][01]\d{8})$/', $digits, $m)) {
        return '+234' . $m[1];
    }
    if (preg_match('/^\+[1-9]\d{6,14}$/', $digits)) {
        return $digits;
    }
    return '';
}


// ------------------------------------------------------------ formatting
// Money is stored in kobo (₦1 = 100 kobo) so there are no rounding errors.

// 18525000 -> "₦185,250"
function naira($kobo)
{
    return '₦' . number_format($kobo / 100);
}

// "2026-12-01" -> "1 Dec 2026"
function nice_date($date)
{
    return date('j M Y', strtotime($date));
}

// plural(2, 'night') -> "2 nights", plural(2, 'child', 'children') -> "2 children"
function plural($count, $word, $many = null)
{
    if ($count == 1) return $count . ' ' . $word;
    return $count . ' ' . ($many ? $many : $word . 's');
}


// ------------------------------------------------------------ settings

// A value from the settings table (fee, contact details, ...), loaded once per page.
function setting($key, $default = '')
{
    static $settings = null;
    if ($settings === null) {
        $settings = [];
        foreach (db_all("SELECT name, value FROM settings") as $row) {
            $settings[$row['name']] = $row['value'];
        }
    }
    return isset($settings[$key]) && $settings[$key] !== '' ? $settings[$key] : $default;
}

// Contact details the client hasn't given us yet show as {{CLIENT_PHONE}} etc.
function contact($key)
{
    return setting('contact_' . $key, '{{CLIENT_' . strtoupper($key) . '}}');
}


// The signed-in customer's id, saved with each new booking (null if not signed in)
function user_id_or_null()
{
    return isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null;
}


// ------------------------------------------------------------ rate limiting

// Allow at most $max requests per $seconds for a bucket (e.g. 'flight-search:' . IP).
// Returns false when the limit is reached. Uses the rate_limits table.
//   if (!rate_limit('flight-search:' . $_SERVER['REMOTE_ADDR'], 20, 60)) { ...too many... }
function rate_limit($bucket, $max, $seconds)
{
    $bucket = substr($bucket, 0, 191);
    db_query("INSERT INTO rate_limits (bucket, window_start, hits) VALUES (?, NOW(), 1)
              ON DUPLICATE KEY UPDATE
                hits = IF(window_start < NOW() - INTERVAL ? SECOND, 1, hits + 1),
                window_start = IF(window_start < NOW() - INTERVAL ? SECOND, NOW(), window_start)",
        [$bucket, (int) $seconds, (int) $seconds]);
    $row = db_one("SELECT hits FROM rate_limits WHERE bucket = ?", [$bucket]);
    return $row['hits'] <= $max;
}


// ------------------------------------------------------------ references

// A booking reference like MTF-7K3QX9PA (no 0, O, 1 or I, which are easy to misread)
function make_reference($prefix)
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $ref = '';
    for ($i = 0; $i < 8; $i++) {
        $ref .= $chars[random_int(0, 31)];
    }
    return $prefix . '-' . $ref;
}


// ------------------------------------------------------------ logging

// Write a line to storage/logs/<file>-<date>.log
function write_log($file, $message, $data = [])
{
    $line = date('Y-m-d H:i:s') . ' ' . $message . ($data ? ' ' . json_encode($data) : '') . "\n";
    // never write API keys to the log
    $line = preg_replace('/(duffel_(test|live)_|sk_(test|live)_)\w+/', '$1***', $line);
    file_put_contents(__DIR__ . '/../storage/logs/' . $file . '-' . date('Y-m-d') . '.log', $line, FILE_APPEND);
}
