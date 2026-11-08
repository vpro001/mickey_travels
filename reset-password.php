<?php
require 'includes/init.php';
require 'includes/account.php';

// Choose a new password with the emailed link: reset-password.php?token=…
$token = get('token') ?: post('token');
$reset = preg_match('/^[a-f0-9]{64}$/', $token)
    ? db_one("SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()", [hash('sha256', $token)])
    : null;
$error = '';

if ($reset && $_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_check();
    $password = isset($_POST['password']) ? (string) $_POST['password'] : '';
    if (strlen($password) < 8) {
        $error = 'Use at least 8 characters.';
    } else {
        db_begin();
        // Mark the link used (only if nobody used it a moment ago), then change the password
        $stmt = db_query("UPDATE password_resets SET used_at = NOW() WHERE id = ? AND used_at IS NULL", [$reset['id']]);
        if (mysqli_stmt_affected_rows($stmt) == 1) {
            db_query("UPDATE users SET password_hash = ? WHERE id = ?", [password_hash($password, PASSWORD_DEFAULT), $reset['user_id']]);
            db_query("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL", [$reset['user_id']]);   // other links stop working
            db_commit();
            log_in_user(db_one("SELECT * FROM users WHERE id = ?", [$reset['user_id']]));
            flash('Your password has been changed.');
            redirect('account.php');
        }
        db_rollback();
        $reset = null;
    }
}

$title = 'Choose a new password';
include 'includes/header.php';
?>
<section class="container-page py-14 sm:py-20">
  <div class="mx-auto max-w-md">
    <h1 class="text-4xl font-extrabold">Choose a new password</h1>
    <?php if (!$reset): ?>
      <p class="mt-6 rounded-xl bg-bad-100 p-4 text-bad-700" role="alert">This link has expired or has already been used.</p>
      <p class="mt-4"><a class="link" href="forgot-password.php">Ask for a new link</a></p>
    <?php else: ?>
      <?php if ($error): ?><p class="mt-6 rounded-xl bg-bad-100 p-4 text-sm text-bad-700" role="alert"><?= e($error) ?></p><?php endif; ?>
      <form method="post" class="mt-8 space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5">
        <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
        <div><label class="field-label" for="password">New password</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="8" class="field" required></div>
        <button class="btn btn-dark w-full py-3">Save new password</button>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php include 'includes/footer.php'; ?>
