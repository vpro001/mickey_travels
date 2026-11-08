<?php
require 'includes/init.php';
require 'includes/account.php';

// The link from the "confirm your email" message: verify-email.php?id=…&token=…
$user = db_one("SELECT * FROM users WHERE id = ? AND role = 'customer'", [(int) get('id')]);
if ($user && hash_equals(verify_token($user), get('token'))) {
    if ($user['email_verified_at'] === null) {
        db_query("UPDATE users SET email_verified_at = NOW() WHERE id = ?", [$user['id']]);
    }
    flash('Thank you, your email is confirmed. Bookings made with it now show in your account.');
    redirect(current_user() ? 'account.php' : 'login.php');
}
flash('That confirmation link is not valid. Sign in and ask for a new one.', 'error');
redirect('login.php');
