<?php
// Visa applications: status rules and customer emails.
// Status steps (docs/STATE_MACHINES.md):
//   submitted → under_review → documents_requested → processing → approved / refused → completed

require_once __DIR__ . '/mail.php';

function visa_status_names()
{
    return [
        'submitted'           => 'Application received',
        'under_review'        => 'Being reviewed',
        'documents_requested' => 'Documents needed from you',
        'processing'          => 'Submitted to the embassy',
        'approved'            => 'Visa approved',
        'refused'             => 'Visa refused',
        'completed'           => 'Completed',
    ];
}

// Which statuses each status can move to
function visa_next_statuses($status)
{
    $rules = [
        'submitted'           => ['under_review'],
        'under_review'        => ['documents_requested', 'processing', 'refused'],
        'documents_requested' => ['under_review', 'processing'],
        'processing'          => ['approved', 'refused', 'documents_requested'],
        'approved'            => ['completed'],
        'refused'             => ['completed'],
        'completed'           => [],
    ];
    return isset($rules[$status]) ? $rules[$status] : [];
}

// Move an application to a new status (only if allowed), record it in the history
// and email the customer. $note is shown to the customer when $public is true.
function visa_status($application_id, $new_status, $note = '', $public = true, $staff_id = null)
{
    $app = db_one("SELECT * FROM visa_applications WHERE id = ?", [$application_id]);
    if (!$app || !in_array($new_status, visa_next_statuses($app['status']))) {
        return false;
    }
    $stmt = db_query("UPDATE visa_applications SET status = ? WHERE id = ? AND status = ?", [$new_status, $application_id, $app['status']]);
    if (mysqli_stmt_affected_rows($stmt) != 1) {
        return false;   // someone else changed it at the same moment
    }
    db_insert('visa_status_history', [
        'application_id' => $application_id,
        'from_status' => $app['status'],
        'to_status' => $new_status,
        'note' => $note,
        'is_public' => $public ? 1 : 0,
        'changed_by' => $staff_id,
    ]);
    visa_email($application_id, $new_status, $public ? $note : '');
    return true;
}

function visa_email($application_id, $status, $note = '')
{
    $app = db_one("SELECT a.*, d.country_name FROM visa_applications a JOIN visa_destinations d ON d.id = a.destination_id WHERE a.id = ?", [$application_id]);
    $names = visa_status_names();
    $link = SITE_URL . '/visa-track.php?ref=' . urlencode($app['reference']) . '&email=' . urlencode($app['email']);
    $html = '<div style="font-family:Arial,sans-serif;color:#0f1f33;max-width:600px">'
        . '<p>Hello ' . e($app['full_name']) . ',</p>'
        . '<p>Your ' . e($app['country_name']) . ' visa application <b>' . e($app['reference']) . '</b> is now: <b>' . e($names[$status]) . '</b>.</p>'
        . ($note ? '<p>' . nl2br(e($note)) . '</p>' : '')
        . '<p><a href="' . e($link) . '">See your application</a></p>'
        . '<p>' . e(SITE_NAME) . '</p></div>';
    send_mail($app['email'], 'Visa application ' . $app['reference'] . ': ' . $names[$status], $html);
}
