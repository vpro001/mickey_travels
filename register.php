<?php
require 'includes/init.php';
require 'includes/account.php';

if (current_user()) {
    redirect('account.php');
}

$f = ['name' => '', 'email' => '', 'phone' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_check();
    $f['name'] = post('name');
    $f['email'] = strtolower(post('email'));
    $f['phone'] = post('phone');
    $password = isset($_POST['password']) ? (string) $_POST['password'] : '';

    if (!rate_limit('register:' . $_SERVER['REMOTE_ADDR'], 10, 3600)) $errors['email'] = 'Too many new accounts from your connection. Please try again later.';
    if (strlen($f['name']) < 2) $errors['name'] = 'Enter your name.';
    if (!valid_email($f['email'])) $errors['email'] = 'Enter a valid email address.';
    if ($f['phone'] != '' && clean_phone($f['phone']) == '') $errors['phone'] = 'Check the phone number, e.g. 0803 123 4567.';
    if (strlen($password) < 8) $errors['password'] = 'Use at least 8 characters.';

    if (!$errors && db_one("SELECT id FROM users WHERE email = ?", [$f['email']])) {
        // Don't reveal more than needed: point them to sign in or reset
        $errors['email'] = 'There is already an account with this email. Sign in, or reset your password.';
    }

    if (!$errors) {
        $id = db_insert('users', [
            'email' => $f['email'],
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'full_name' => substr($f['name'], 0, 120),
            'phone' => clean_phone($f['phone']) ?: null,
            'role' => 'customer',
        ]);
        $user = db_one("SELECT * FROM users WHERE id = ?", [$id]);
        send_verification_email($user);
        log_in_user($user);
        flash('Welcome! We have sent you an email to confirm your address.');
        redirect('account.php');
    }
}

$title = 'Create an account';
include 'includes/header.php';
$field = 'mt-1 block w-full rounded-lg border-0 bg-paper-50 px-3.5 py-3 text-base ring-1 ring-ink-300 focus:outline-none focus:ring-2 focus:ring-saffron-500';
?>
<section class="container-page py-14 sm:py-20">
  <div class="mx-auto max-w-md">
    <p class="eyebrow text-saffron-700">Your account</p>
    <h1 class="mt-3 text-4xl font-extrabold">Create an account</h1>
    <p class="mt-2 text-ink-700">See all your flights, hotels, holidays and visa applications in one place.</p>
    <form method="post" class="mt-8 space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5" novalidate>
      <?= csrf_field() ?>
      <div><label class="field-label" for="name">Full name</label><input id="name" name="name" autocomplete="name" class="<?= $field ?>" value="<?= e($f['name']) ?>"><?= isset($errors['name']) ? '<p class="field-error">' . e($errors['name']) . '</p>' : '' ?></div>
      <div><label class="field-label" for="email">Email</label><input id="email" name="email" type="email" autocomplete="email" class="<?= $field ?>" value="<?= e($f['email']) ?>"><?= isset($errors['email']) ? '<p class="field-error">' . e($errors['email']) . '</p>' : '' ?></div>
      <div><label class="field-label" for="phone">Phone <span class="normal-case font-normal">(optional)</span></label><input id="phone" name="phone" type="tel" autocomplete="tel" class="<?= $field ?> num" value="<?= e($f['phone']) ?>"><?= isset($errors['phone']) ? '<p class="field-error">' . e($errors['phone']) . '</p>' : '' ?></div>
      <div><label class="field-label" for="password">Password</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="8" class="<?= $field ?>"><?= isset($errors['password']) ? '<p class="field-error">' . e($errors['password']) . '</p>' : '<p class="mt-1 text-xs text-ink-600">At least 8 characters.</p>' ?></div>
      <button class="btn btn-dark w-full py-3">Create account</button>
    </form>
    <p class="mt-4 text-sm text-ink-700">Already have an account? <a class="link" href="login.php">Sign in</a></p>
  </div>
</section>
<?php include 'includes/footer.php'; ?>
