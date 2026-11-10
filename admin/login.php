<?php
require '../includes/init.php';

// Staff login. If there are no staff accounts yet, this page creates the first admin.
$has_staff = db_one("SELECT COUNT(*) n FROM users WHERE role IN ('staff', 'admin')")['n'] > 0;
$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_check();
    $email = strtolower(post('email'));
    $password = isset($_POST['password']) ? (string) $_POST['password'] : '';
    $ip = $_SERVER['REMOTE_ADDR'];

    if (!$has_staff) {
        // First run: create the admin account
        $name = post('name');
        if (strlen($name) < 2 || !valid_email($email) || strlen($password) < 10) {
            $error = 'Enter your name, email and a password of at least 10 characters.';
        } else {
            $id = db_insert('users', ['email' => $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'full_name' => $name, 'role' => 'admin']);
            session_regenerate_id(true);
            $_SESSION['staff'] = ['id' => $id, 'name' => $name, 'role' => 'admin'];
            redirect('index.php');
        }
    } else {
        // Too many wrong passwords in the last 15 minutes, for this email or from this address?
        $fails = db_one("SELECT COUNT(*) n FROM login_attempts WHERE success = 0 AND attempted_at > NOW() - INTERVAL 15 MINUTE AND (email = ? OR ip = ?)", [$email, $ip])['n'];
        if ($fails >= 5) {
            $error = 'Too many attempts. Please wait 15 minutes and try again.';
        } else {
            $user = db_one("SELECT * FROM users WHERE email = ? AND role IN ('staff', 'admin') AND is_active = 1", [$email]);
            $ok = $user && password_verify($password, $user['password_hash']);
            db_insert('login_attempts', ['email' => $email, 'ip' => $ip, 'success' => $ok ? 1 : 0]);
            if ($ok) {
                if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                    db_query("UPDATE users SET password_hash = ? WHERE id = ?", [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
                }
                db_query("UPDATE users SET last_login_at = NOW() WHERE id = ?", [$user['id']]);
                session_regenerate_id(true);   // new session id after login
                $_SESSION['staff'] = ['id' => $user['id'], 'name' => $user['full_name'], 'role' => $user['role']];
                redirect('index.php');
            }
            $error = 'That email and password do not match.';
        }
    }
}

$title = $has_staff ? 'Log in' : 'Set up the admin account';
include '../includes/admin-header.php';
?>
<div class="mx-auto mt-[10vh] max-w-sm">
  <div class="flex items-center gap-2.5">
    <span class="flex size-10 items-center justify-center rounded-lg bg-saffron-400 font-display text-xl font-extrabold text-ink-950">M</span>
    <span class="leading-none"><span class="block font-display text-lg font-bold">Mickey Travels</span><span class="font-mono text-[10px] uppercase tracking-[0.2em] text-saffron-700">Back office</span></span>
  </div>
  <h1 class="mt-8 text-3xl font-extrabold"><?= $has_staff ? 'Staff log in' : 'Create the admin account' ?></h1>
  <?php if (!$has_staff): ?><p class="mt-2 text-sm text-ink-700">No staff accounts exist yet. The account you create now is the main admin.</p><?php endif; ?>
  <?php if ($error): ?><p class="mt-5 rounded-lg bg-bad-100 p-3 text-sm text-bad-700" role="alert"><?= e($error) ?></p><?php endif; ?>
  <form method="post" class="mt-6 space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5">
    <?= csrf_field() ?>
    <?php if (!$has_staff): ?>
      <div><label class="field-label" for="name">Your name</label><input id="name" name="name" class="field" autocomplete="name" required></div>
    <?php endif; ?>
    <div><label class="field-label" for="email">Email</label><input id="email" name="email" type="email" class="field" autocomplete="username" value="<?= e($email) ?>" required></div>
    <div><label class="field-label" for="password">Password</label><input id="password" name="password" type="password" class="field" autocomplete="<?= $has_staff ? 'current-password' : 'new-password' ?>" required <?= $has_staff ? '' : 'minlength="10"' ?>></div>
    <button class="btn btn-dark w-full py-3"><?= $has_staff ? 'Log in' : 'Create account and log in' ?></button>
  </form>
</div>
<?php include '../includes/admin-footer.php'; ?>
