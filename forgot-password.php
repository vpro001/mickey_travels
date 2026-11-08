<?php
require 'includes/init.php';
require 'includes/mail.php';

// Ask for a password reset link. The answer is the same whether or not the email has an account.
$sent = false;
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_check();
    $email = strtolower(post('email'));
    $user = valid_email($email) ? db_one("SELECT id, full_name, email FROM users WHERE email = ? AND role = 'customer' AND is_active = 1", [$email]) : null;
    // At most 3 links per account per hour
    $recent = $user ? db_one("SELECT COUNT(*) n FROM password_resets WHERE user_id = ? AND created_at > NOW() - INTERVAL 1 HOUR", [$user['id']])['n'] : 0;
    if ($user && $recent < 3) {
        $token = bin2hex(random_bytes(32));   // only its SHA-256 hash is stored
        db_insert('password_resets', ['user_id' => $user['id'], 'token_hash' => hash('sha256', $token), 'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour'))]);
        $link = SITE_URL . '/reset-password.php?token=' . $token;
        send_mail($user['email'], 'Reset your password',
            '<div style="font-family:Arial,sans-serif;color:#0f1f33;max-width:600px"><p>Hello ' . e($user['full_name']) . ',</p>'
            . '<p>Use this link to choose a new password. It works once, for the next hour:</p><p><a href="' . e($link) . '">Choose a new password</a></p>'
            . '<p>If you did not ask for this, you can ignore this email; your password stays the same.</p><p>' . e(SITE_NAME) . '</p></div>');
    }
    $sent = true;
}

$title = 'Forgot your password';
include 'includes/header.php';
?>
<section class="container-page py-14 sm:py-20">
  <div class="mx-auto max-w-md">
    <h1 class="text-4xl font-extrabold">Forgot your password?</h1>
    <?php if ($sent): ?>
      <p class="mt-6 rounded-xl bg-ok-100 p-4 text-ok-700" role="status">If there's an account with that email, we've sent a link to reset the password. It works for one hour.</p>
      <p class="mt-4"><a class="link" href="login.php">Back to sign in</a></p>
    <?php else: ?>
      <p class="mt-2 text-ink-700">Enter your email and we'll send you a link to choose a new one.</p>
      <form method="post" class="mt-8 space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5">
        <?= csrf_field() ?>
        <div><label class="field-label" for="email">Email</label><input id="email" name="email" type="email" autocomplete="email" class="field" required></div>
        <button class="btn btn-dark w-full py-3">Send reset link</button>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php include 'includes/footer.php'; ?>
