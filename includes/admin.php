<?php
// Included at the top of every admin page (after init.php).
// Sends anyone who isn't logged in as staff to the login page.

if (empty($_SESSION['staff'])) {
    redirect('login.php');
}
// Re-check the account is still active (it may have been switched off since login)
$staff_user = db_one("SELECT id, full_name, role, is_active FROM users WHERE id = ?", [$_SESSION['staff']['id']]);
if (!$staff_user || !$staff_user['is_active'] || !in_array($staff_user['role'], ['staff', 'admin'])) {
    unset($_SESSION['staff']);
    redirect('login.php');
}

function admin_only()
{
    if ($_SESSION['staff']['role'] != 'admin') {
        flash('Only an admin can do that.', 'error');
        redirect('index.php');
    }
}

// A coloured label for a status
function status_badge($status)
{
    $green = ['confirmed', 'paid', 'approved', 'completed', 'success', 'closed'];
    $red = ['payment_failed', 'expired', 'cancelled', 'refunded', 'refused', 'failed', 'abandoned', 'needs_attention'];
    $class = in_array($status, $green) ? 'bg-ok-100 text-ok-700' : (in_array($status, $red) ? 'bg-bad-100 text-bad-700' : 'bg-warn-100 text-warn-700');
    return '<span class="badge ' . $class . '">' . e(str_replace('_', ' ', $status)) . '</span>';
}
