<?php
require 'includes/init.php';
require 'includes/account.php';

if (current_user()) {
    redirect('account.php');
}

// Where to go after signing in (only pages on this site)
$next = preg_match('/^[a-z0-9-]+\.php(\?[^\s"<>]*)?$/i', get('next')) ? get('next') : 'account.php';
$email = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_check();
    $email = strtolower(post('email'));
    $password = isset($_POST['password']) ? (string) $_POST['password'] : '';
    $next = preg_match('/^[a-z0-9-]+\.php(\?[^\s"<>]*)?$/i', post('next')) ? post('next') : 'account.php';

    if (login_locked($email)) {
        $error = 'Too many attempts. Please wait 15 minutes, or reset your password.';
    } else {
        $user = db_one("SELECT * FROM users WHERE email = ? AND role = 'customer' AND is_active = 1", [$email]);
        $ok = $user && password_verify($password, $user['password_hash']);
        db_insert('login_attempts', ['email' => $email, 'ip' => $_SERVER['REMOTE_ADDR'], 'success' => $ok ? 1 : 0]);
        if ($ok) {
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                db_query("UPDATE users SET password_hash = ? WHERE id = ?", [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            }
            log_in_user($user);
            redirect($next);
        }
        $error = 'That email and password do not match.';
    }
}

$title = 'Sign in';
include 'includes/header.php';
$field = 'mt-1 block w-full rounded-lg border-0 bg-paper-50 px-3.5 py-3 text-base ring-1 ring-ink-300 focus:outline-none focus:ring-2 focus:ring-saffron-500';
?>
<section class="container-page py-14 sm:py-20">
  <div class="mx-auto max-w-md">
    <p class="eyebrow text-saffron-700">Your account</p>
    <h1 class="mt-3 text-4xl font-extrabold">Sign in</h1>
    <?php if ($error): ?><p class="mt-6 rounded-xl bg-bad-100 p-4 text-sm text-bad-700" role="alert"><?= e($error) ?></p><?php endif; ?>
    <form method="post" class="mt-8 space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5">
      <?= csrf_field() ?><input type="hidden" name="next" value="<?= e($next) ?>">
      <div><label class="field-label" for="email">Email</label><input id="email" name="email" type="email" autocomplete="email" class="<?= $field ?>" value="<?= e($email) ?>" required></div>
      <div>
        <div class="flex items-center justify-between"><label class="field-label" for="password">Password</label><a class="text-xs link" href="forgot-password.php">Forgot it?</a></div>
        <input id="password" name="password" type="password" autocomplete="current-password" class="<?= $field ?>" required>
      </div>
      <button class="btn btn-dark w-full py-3">Sign in</button>
    </form>
    <p class="mt-4 text-sm text-ink-700">New here? <a class="link" href="register.php">Create an account</a>. You can also book without one.</p>
  </div>
</section>
<?php include 'includes/footer.php'; ?>
