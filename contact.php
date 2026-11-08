<?php
require 'includes/init.php';

$topics = [
    'general'     => 'General question',
    'flight'      => 'Flights',
    'hotel'       => 'Hotels',
    'visa'        => 'Visa',
    'tour'        => 'Tours and holidays',
    'custom_trip' => 'Plan a custom trip for me',
];

$name = '';
$email = '';
$phone = '';
$topic = 'general';
$message = '';
$errors = [];

// ---------- Form sent ----------
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_check();

    $name = post('name');
    $email = post('email');
    $phone = post('phone');
    $topic = isset($topics[post('topic')]) ? post('topic') : 'general';
    $message = post('message');

    if ($name == '') {
        $errors['name'] = 'Please tell us your name.';
    }
    if (!valid_email($email)) {
        $errors['email'] = 'Please enter a valid email address so we can reply.';
    }
    if ($phone != '' && clean_phone($phone) == '') {
        $errors['phone'] = 'Please enter a valid phone number, e.g. 0803 123 4567.';
    }
    if (strlen($message) < 10) {
        $errors['message'] = 'Please tell us a little more (at least 10 characters).';
    }

    // Stop spam: at most 5 messages an hour from the same address
    $recent = db_one("SELECT COUNT(*) AS total FROM enquiries WHERE ip = ? AND created_at > NOW() - INTERVAL 1 HOUR", [$_SERVER['REMOTE_ADDR']]);
    if ($recent['total'] >= 5) {
        $errors['form'] = 'You have sent several messages in the last hour. Please wait a little, or call us.';
    }

    // "website" is a hidden field that people never see. Only spam robots fill it in.
    $is_robot = post('website') != '';

    if (!$errors) {
        if (!$is_robot) {
            db_insert('enquiries', [
                'type'    => $topic,
                'name'    => substr($name, 0, 120),
                'email'   => $email,
                'phone'   => clean_phone($phone),
                'subject' => $topics[$topic],
                'message' => substr($message, 0, 3000),
                'ip'      => $_SERVER['REMOTE_ADDR'],
            ]);
        }
        flash("Thank you, $name. We have your message and will reply by email.");
        redirect('contact.php');
    }
}

$title = 'Contact us';
include 'includes/header.php';
?>

<section class="container-page py-10 sm:py-14 grid gap-10 lg:grid-cols-[3fr_2fr]">
  <div>
    <h1 class="text-2xl sm:text-3xl font-bold">Contact us</h1>
    <p class="mt-2 text-ink-700">Ask us anything about a trip. We reply by email, usually within one working day.</p>

    <?php if (isset($errors['form'])): ?>
      <p class="mt-5 rounded-md bg-bad-100 px-4 py-3 text-sm font-medium text-bad-700"><?= e($errors['form']) ?></p>
    <?php elseif ($errors): ?>
      <p class="mt-5 rounded-md bg-bad-100 px-4 py-3 text-sm font-medium text-bad-700">Please check the highlighted fields.</p>
    <?php endif; ?>

    <form method="post" action="contact.php" class="mt-6 space-y-4" novalidate>
      <?= csrf_field() ?>
      <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">

      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="field-label" for="name">Your name</label>
          <input id="name" name="name" class="field" type="text" autocomplete="name" value="<?= e($name) ?>">
          <?php if (isset($errors['name'])): ?><p class="field-error"><?= $errors['name'] ?></p><?php endif; ?>
        </div>
        <div>
          <label class="field-label" for="email">Email</label>
          <input id="email" name="email" class="field" type="email" autocomplete="email" value="<?= e($email) ?>">
          <?php if (isset($errors['email'])): ?><p class="field-error"><?= $errors['email'] ?></p><?php endif; ?>
        </div>
        <div>
          <label class="field-label" for="phone">Phone <span class="normal-case font-normal">(optional)</span></label>
          <input id="phone" name="phone" class="field num" type="tel" autocomplete="tel" value="<?= e($phone) ?>">
          <?php if (isset($errors['phone'])): ?><p class="field-error"><?= $errors['phone'] ?></p><?php endif; ?>
        </div>
        <div>
          <label class="field-label" for="topic">About</label>
          <select id="topic" name="topic" class="field">
            <?php foreach ($topics as $value => $label): ?>
              <option value="<?= $value ?>" <?= $topic == $value ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div>
        <label class="field-label" for="message">Message</label>
        <textarea id="message" name="message" class="field min-h-36" placeholder="Where and when you'd like to travel, how many people, and anything else we should know"><?= e($message) ?></textarea>
        <?php if (isset($errors['message'])): ?><p class="field-error"><?= $errors['message'] ?></p><?php endif; ?>
      </div>

      <button type="submit" class="btn btn-primary px-6 py-3">Send message</button>
    </form>
  </div>

  <aside class="h-fit space-y-5 rounded-lg bg-paper-100 p-5 sm:p-6">
    <h2 class="text-lg font-bold">Visit or call us</h2>
    <p class="flex gap-3"><?= icon('pin', 'size-5 shrink-0 text-ink-600 mt-0.5') ?> <?= e(contact('address')) ?></p>
    <p class="flex gap-3"><?= icon('phone', 'size-5 shrink-0 text-ink-600 mt-0.5') ?> <?= e(contact('phone')) ?></p>
    <p class="flex gap-3"><?= icon('mail', 'size-5 shrink-0 text-ink-600 mt-0.5') ?> <?= e(contact('email')) ?></p>
    <p class="flex gap-3"><?= icon('clock', 'size-5 shrink-0 text-ink-600 mt-0.5') ?> <?= e(contact('hours')) ?></p>
  </aside>
</section>

<?php include 'includes/footer.php'; ?>
