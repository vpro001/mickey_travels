<?php
// Customer accounts: who is logged in, logging in, email verification.

require_once __DIR__ . '/mail.php';

// The logged-in customer, or null
function current_user()
{
    if (empty($_SESSION['user']['id'])) {
        return null;
    }
    static $user = null;
    if ($user === null) {
        $user = db_one("SELECT id, email, full_name, phone, email_verified_at FROM users WHERE id = ? AND is_active = 1", [$_SESSION['user']['id']]);
        if (!$user) {
            unset($_SESSION['user']);
        }
    }
    return $user;
}

function require_login()
{
    if (!current_user()) {
        flash('Please sign in first.', 'error');
        redirect('login.php?next=' . urlencode(basename($_SERVER['REQUEST_URI'])));
    }
}

function log_in_user($user)
{
    session_regenerate_id(true);   // a new session id after logging in
    $_SESSION['user'] = ['id' => $user['id'], 'name' => $user['full_name']];
    db_query("UPDATE users SET last_login_at = NOW() WHERE id = ?", [$user['id']]);
}

// Too many wrong passwords in 15 minutes, for this email or from this address?
function login_locked($email)
{
    return db_one("SELECT COUNT(*) n FROM login_attempts WHERE success = 0 AND attempted_at > NOW() - INTERVAL 15 MINUTE AND (email = ? OR ip = ?)",
        [$email, $_SERVER['REMOTE_ADDR']])['n'] >= 5;
}

// Email verification links are signed with APP_SECRET, so they can't be made up
function verify_token($user)
{
    return hash_hmac('sha256', $user['id'] . '|' . strtolower($user['email']), APP_SECRET);
}

function send_verification_email($user)
{
    $link = SITE_URL . '/verify-email.php?id=' . $user['id'] . '&token=' . verify_token($user);
    send_mail($user['email'], 'Confirm your email for ' . SITE_NAME,
        '<div style="font-family:Arial,sans-serif;color:#0f1f33;max-width:600px"><p>Hello ' . e($user['full_name']) . ',</p>'
        . '<p>Please confirm your email address so we can show bookings made with it in your account:</p>'
        . '<p><a href="' . e($link) . '">Confirm my email</a></p>'
        . '<p>If you did not create an account, you can ignore this email.</p><p>' . e(SITE_NAME) . '</p></div>');
}

// Bookings that belong to this customer: made while signed in, or made with their
// email address once that email is verified.
function my_bookings($user)
{
    $verified = $user['email_verified_at'] !== null;
    $email = $verified ? $user['email'] : '-';
    return [
        'flights' => db_all("SELECT * FROM flight_bookings WHERE (user_id = ? OR contact_email = ?) AND status NOT IN ('expired') ORDER BY id DESC", [$user['id'], $email]),
        'hotels'  => db_all("SELECT * FROM hotel_bookings WHERE (user_id = ? OR contact_email = ?) AND status NOT IN ('expired') ORDER BY id DESC", [$user['id'], $email]),
        'tours'   => db_all("SELECT b.*, p.title, d.start_date FROM tour_bookings b JOIN tour_packages p ON p.id = b.package_id JOIN tour_departures d ON d.id = b.departure_id WHERE b.user_id = ? OR b.email = ? ORDER BY b.id DESC", [$user['id'], $email]),
        'visas'   => db_all("SELECT a.*, d.country_name FROM visa_applications a JOIN visa_destinations d ON d.id = a.destination_id WHERE a.user_id = ? OR a.email = ? ORDER BY a.id DESC", [$user['id'], $email]),
    ];
}
