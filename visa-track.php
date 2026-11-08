<?php
require 'includes/init.php';
require 'includes/visa.php';
require 'includes/uploads.php';

// Track a visa application with its reference and email.
$ref = strtoupper(get('ref'));
$email = get('email');
$access = isset($_SESSION['visa_access']) ? $_SESSION['visa_access'] : [];

$app = null;
if (preg_match('/^MTV-[A-Z0-9]{8}$/', $ref)) {
    $found = db_one("SELECT a.*, d.country_name, d.slug FROM visa_applications a JOIN visa_destinations d ON d.id = a.destination_id WHERE a.reference = ?", [$ref]);
    if ($found && (in_array($ref, $access) || strcasecmp($email, $found['email']) == 0)) {
        $app = $found;
        $_SESSION['visa_access'][] = $ref;
    }
}
$not_found = ($ref != '' || $email != '') && !$app;

// Customer sends documents that staff asked for
$upload_error = '';
if ($app && $_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_check();
    if ($app['status'] == 'documents_requested') {
        $names = [];
        foreach (array_slice(list_files('docs'), 0, 5) as $file) {
            if ($file['error'] == UPLOAD_ERR_NO_FILE) continue;
            $r = save_upload($file, 'visa');
            if (!$r['ok']) { $upload_error = $file['name'] . ': ' . $r['error']; break; }
            db_insert('visa_documents', ['application_id' => $app['id'], 'doc_type' => 'requested', 'original_name' => $r['original_name'],
                'stored_name' => $r['stored_name'], 'mime' => $r['mime'], 'size_bytes' => $r['size_bytes'], 'sha256' => $r['sha256'], 'uploaded_by' => 'customer']);
            $names[] = $r['original_name'];
        }
        if (!$upload_error && $names) {
            visa_status($app['id'], 'under_review', 'You sent: ' . implode(', ', $names) . '. We are reviewing them.');
            flash('Thank you. We have your documents.');
            redirect('visa-track.php?ref=' . urlencode($ref));
        } elseif (!$upload_error) {
            $upload_error = 'Please choose at least one file.';
        }
    }
}

if ($app) {
    $history = db_all("SELECT * FROM visa_status_history WHERE application_id = ? AND is_public = 1 ORDER BY id DESC", [$app['id']]);
    $docs = db_all("SELECT doc_type, original_name, uploaded_by, created_at FROM visa_documents WHERE application_id = ? ORDER BY id", [$app['id']]);
}
$names = visa_status_names();
$steps = ['submitted', 'under_review', 'processing', 'approved', 'completed'];

$title = 'Track your visa application';
$page = 'visa';
include 'includes/header.php';
?>
<section class="bg-ink-950 text-paper-50">
  <div class="container-page py-10 sm:py-14">
    <p class="eyebrow text-saffron-300">Visa tracking</p>
    <h1 class="mt-3 text-4xl font-extrabold tracking-[-0.03em] sm:text-5xl"><?= $app ? e($app['country_name']) . ' visa' : 'Track your application' ?></h1>
    <?php if ($app): ?><p class="mt-2 font-mono text-lg tracking-wider text-saffron-300"><?= e($app['reference']) ?></p><?php endif; ?>
  </div>
</section>

<section class="container-page py-10 sm:py-14">
<?php if (!$app): ?>
  <div class="mx-auto max-w-lg">
    <?php if ($not_found): ?>
      <p class="mb-6 rounded-xl bg-bad-100 p-4 text-sm text-bad-700" role="alert">We couldn't find an application with that reference and email. Check both and try again.</p>
    <?php endif; ?>
    <form method="get" class="space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5">
      <p class="text-ink-700">Use the reference from your email (it starts with MTV-) and the email you applied with.</p>
      <div><label class="field-label" for="ref">Reference</label><input id="ref" name="ref" class="field font-mono uppercase tracking-wider" placeholder="MTV-XXXXXXXX" value="<?= e($ref) ?>" required></div>
      <div><label class="field-label" for="email">Email</label><input id="email" name="email" type="email" class="field" value="<?= e($email) ?>" required></div>
      <button class="btn btn-dark w-full py-3">Track application</button>
    </form>
  </div>
<?php else: ?>
  <div class="mx-auto max-w-4xl space-y-6">
    <?php if (get('new')): ?>
      <div class="rounded-2xl bg-ok-100 p-5 text-ok-700" role="status">
        <p class="font-display text-xl font-bold">Application sent</p>
        <p class="mt-1">Keep your reference <b class="font-mono"><?= e($app['reference']) ?></b>. We've emailed it to <?= e($app['email']) ?> and will be in touch within two working days.</p>
      </div>
    <?php endif; ?>

    <!-- Progress -->
    <?php
    $current = $app['status'];
    $position = array_search($current == 'documents_requested' ? 'under_review' : ($current == 'refused' ? 'approved' : $current), $steps);
    ?>
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5">
      <p class="font-mono text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-600">Status</p>
      <p class="mt-1 font-display text-3xl font-bold <?= $current == 'refused' ? 'text-bad-700' : ($current == 'documents_requested' ? 'text-warn-700' : '') ?>"><?= e($names[$current]) ?></p>
      <ol class="mt-6 grid grid-cols-5 gap-2">
        <?php foreach ($steps as $n => $step):
            $done = $n < $position || ($n == $position && in_array($current, ['completed']));
            $now = $n == $position && $current != 'completed';
            $label = $step == 'approved' && $current == 'refused' ? 'Refused' : ['submitted' => 'Received', 'under_review' => 'Review', 'processing' => 'Embassy', 'approved' => 'Decision', 'completed' => 'Done'][$step];
        ?>
          <li>
            <div class="h-1.5 rounded-full <?= $done ? 'bg-ok-700' : ($now ? ($current == 'refused' ? 'bg-bad-700' : 'bg-saffron-500') : 'bg-paper-200') ?>"></div>
            <p class="mt-2 text-xs font-semibold <?= $now ? 'text-ink-900' : 'text-ink-600' ?>"><?= $label ?></p>
          </li>
        <?php endforeach; ?>
      </ol>
    </div>

    <?php if ($current == 'documents_requested'): ?>
      <form method="post" enctype="multipart/form-data" class="rounded-2xl bg-warn-100 p-6 text-warn-700">
        <?= csrf_field() ?>
        <p class="font-display text-xl font-bold">We need more documents from you</p>
        <p class="mt-1 text-sm">See the note below for what to send. PDF, JPG or PNG, up to 5 MB each.</p>
        <?php if ($upload_error): ?><p class="mt-3 rounded-lg bg-white p-3 text-sm text-bad-700"><?= e($upload_error) ?></p><?php endif; ?>
        <input type="file" name="docs[]" multiple accept=".pdf,.jpg,.jpeg,.png" class="mt-4 block w-full rounded-lg bg-white p-2 text-sm text-ink-900 file:mr-3 file:rounded-md file:border-0 file:bg-ink-900 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-paper-50">
        <button class="btn btn-dark mt-4 px-6 py-3">Send documents</button>
      </form>
    <?php endif; ?>

    <div class="grid gap-6 md:grid-cols-[1.4fr_1fr]">
      <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5">
        <p class="font-display text-xl font-bold">Updates</p>
        <ol class="mt-4 space-y-5 border-l-2 border-paper-200 pl-5">
          <?php foreach ($history as $h): ?>
            <li class="relative">
              <span class="absolute -left-[1.62rem] top-1.5 size-3 rounded-full bg-ink-900 ring-4 ring-white"></span>
              <p class="font-semibold"><?= e($names[$h['to_status']]) ?></p>
              <p class="text-xs text-ink-600 num"><?= date('j M Y, H:i', strtotime($h['created_at'])) ?></p>
              <?php if ($h['note']): ?><p class="mt-1 text-sm text-ink-700"><?= nl2br(e($h['note'])) ?></p><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
      <div class="space-y-6">
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5 text-sm">
          <p class="font-display text-xl font-bold">Your application</p>
          <dl class="mt-3 space-y-2">
            <div class="flex justify-between gap-3"><dt class="text-ink-600">Name</dt><dd class="text-right font-medium"><?= e($app['full_name']) ?></dd></div>
            <div class="flex justify-between gap-3"><dt class="text-ink-600">Travel date</dt><dd class="num"><?= nice_date($app['travel_date']) ?></dd></div>
            <div class="flex justify-between gap-3"><dt class="text-ink-600">Our fee</dt><dd class="num"><?= naira($app['service_fee_kobo']) ?></dd></div>
          </dl>
        </div>
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5 text-sm">
          <p class="font-display text-xl font-bold">Documents</p>
          <ul class="mt-3 space-y-2">
            <?php foreach ($docs as $d): ?>
              <li class="flex items-center gap-2"><?= icon('document', 'size-4 shrink-0 text-ink-500') ?> <span class="truncate"><?= e($d['original_name']) ?></span></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>
</section>
<?php include 'includes/footer.php'; ?>
