<?php
require 'includes/init.php';
require 'includes/visa.php';
require 'includes/uploads.php';

$country = db_one("SELECT * FROM visa_destinations WHERE slug = ? AND is_active = 1", [get('country')]);
if (!$country) {
    redirect('visa.php');
}

$services = [
    'application' => ['Full application', 'We handle everything: document check, forms, appointment and interview preparation.'],
    'support'     => ['Document check only', 'You apply yourself; we review your documents and forms first.'],
    'visit'       => ['Visit visa', 'For visiting family or friends, with help preparing the invitation documents.'],
];
$type_names = ['tourist' => 'Tourist', 'business' => 'Business', 'visit' => 'Family visit', 'student' => 'Student', 'transit' => 'Transit'];
$types = array_intersect_key($type_names, array_flip(explode(',', $country['visa_types'])));

$f = ['service' => 'application', 'visa_type' => key($types), 'full_name' => '', 'email' => '', 'phone' => '', 'passport_number' => '', 'travel_date' => '', 'return_date' => '', 'purpose' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_check();
    foreach ($f as $key => $v) {
        $f[$key] = post($key);
    }
    if (!rate_limit('visa-apply:' . $_SERVER['REMOTE_ADDR'], 5, 3600)) $errors['full_name'] = 'You have sent several applications in the last hour. Please contact us if you need help.';
    if (!isset($services[$f['service']])) $errors['service'] = 'Choose a service.';
    if (!isset($types[$f['visa_type']])) $errors['visa_type'] = 'Choose a visa type.';
    if (strlen($f['full_name']) < 3) $errors['full_name'] = 'Enter your full name as it is on your passport.';
    if (!valid_email($f['email'])) $errors['email'] = 'Enter your email address. We send updates there.';
    if (clean_phone($f['phone']) == '') $errors['phone'] = 'Enter your phone number, e.g. 0803 123 4567.';
    if ($f['passport_number'] != '' && !preg_match('/^[A-Za-z0-9]{5,20}$/', $f['passport_number'])) $errors['passport_number'] = 'Check the passport number.';
    if (!valid_date($f['travel_date']) || $f['travel_date'] <= date('Y-m-d')) $errors['travel_date'] = 'Enter the date you plan to travel.';
    if ($f['return_date'] != '' && (!valid_date($f['return_date']) || $f['return_date'] < $f['travel_date'])) $errors['return_date'] = 'The return date must be after the travel date.';

    // Required documents
    $required = ['passport_page' => 'Passport photo page', 'photo' => 'Passport photograph'];
    foreach ($required as $field => $label) {
        if (empty($_FILES[$field]['name'])) $errors[$field] = "Please upload your $label.";
    }

    $saved = [];
    if (!$errors) {
        foreach ($required as $field => $label) {
            $r = save_upload($_FILES[$field], 'visa');
            if (!$r['ok']) { $errors[$field] = $r['error']; break; }
            $r['doc_type'] = $field;
            $saved[] = $r;
        }
        foreach (array_slice(list_files('other'), 0, 5) as $file) {
            if ($errors || $file['error'] == UPLOAD_ERR_NO_FILE) continue;
            $r = save_upload($file, 'visa');
            if (!$r['ok']) { $errors['other'] = $file['name'] . ': ' . $r['error']; break; }
            $r['doc_type'] = 'supporting';
            $saved[] = $r;
        }
        if ($errors) {
            // Something failed: don't keep half an application's files
            foreach ($saved as $s) @unlink(__DIR__ . '/storage/uploads/visa/' . $s['stored_name']);
        }
    }

    if (!$errors) {
        $reference = make_reference('MTV');
        db_begin();
        $id = db_insert('visa_applications', [
            'reference' => $reference,
            'user_id' => user_id_or_null(),
            'destination_id' => $country['id'],
            'visa_type' => $f['visa_type'],
            'service' => $f['service'],
            'full_name' => substr($f['full_name'], 0, 120),
            'email' => $f['email'],
            'phone' => clean_phone($f['phone']),
            'passport_number' => $f['passport_number'] ? strtoupper($f['passport_number']) : null,
            'travel_date' => $f['travel_date'],
            'return_date' => $f['return_date'] ?: null,
            'purpose' => substr($f['purpose'], 0, 500),
            'service_fee_kobo' => $country['service_fee_kobo'],
        ]);
        foreach ($saved as $s) {
            db_insert('visa_documents', [
                'application_id' => $id, 'doc_type' => $s['doc_type'], 'original_name' => $s['original_name'],
                'stored_name' => $s['stored_name'], 'mime' => $s['mime'], 'size_bytes' => $s['size_bytes'], 'sha256' => $s['sha256'],
            ]);
        }
        db_insert('visa_status_history', ['application_id' => $id, 'to_status' => 'submitted', 'note' => 'We have your application and documents.', 'is_public' => 1]);
        db_commit();

        visa_email($id, 'submitted', "We'll review your documents and contact you within two working days with the next steps and how to pay our service fee.");
        $_SESSION['visa_access'][] = $reference;
        redirect('visa-track.php?ref=' . urlencode($reference) . '&new=1');
    }
}

$title = 'Apply: ' . $country['country_name'] . ' visa';
$page = 'visa';
include 'includes/header.php';

$field = 'mt-1 block w-full rounded-lg border-0 bg-paper-50 px-3.5 py-3 text-base ring-1 ring-ink-300 focus:outline-none focus:ring-2 focus:ring-saffron-500';
$label = 'block font-mono text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-600';
function show_error($errors, $key) { return isset($errors[$key]) ? '<p class="field-error">' . e($errors[$key]) . '</p>' : ''; }
?>
<section class="bg-ink-950 text-paper-50">
  <div class="container-page py-10 sm:py-14">
    <a href="visa.php?country=<?= e($country['slug']) ?>" class="font-mono text-xs uppercase tracking-[0.2em] text-ink-300 hover:text-saffron-300">← <?= e($country['country_name']) ?> requirements</a>
    <h1 class="mt-3 text-4xl font-extrabold tracking-[-0.03em] sm:text-5xl"><?= e($country['country_name']) ?> visa application</h1>
    <p class="mt-2 text-ink-200">Takes about 5 minutes. You'll get a reference number to track every step.</p>
  </div>
</section>

<section class="container-page py-10 sm:py-14">
  <form method="post" enctype="multipart/form-data" novalidate class="mx-auto max-w-3xl space-y-6">
    <?= csrf_field() ?>
    <?php if ($errors): ?>
      <div class="rounded-xl bg-bad-100 p-4 text-bad-700" role="alert"><p class="font-semibold">Please check the highlighted details.</p></div>
    <?php endif; ?>

    <fieldset class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-ink-900/5 sm:p-6">
      <legend class="sr-only">Service</legend>
      <p class="font-display text-xl font-bold">1. How can we help?</p>
      <div class="mt-4 grid gap-3 sm:grid-cols-3">
        <?php foreach ($services as $key => $s): ?>
          <label class="relative">
            <input type="radio" name="service" value="<?= $key ?>" class="peer sr-only" <?= $f['service'] == $key ? 'checked' : '' ?>>
            <span class="block h-full rounded-xl p-4 ring-1 ring-ink-300 transition peer-checked:bg-ink-950 peer-checked:text-paper-50 peer-checked:ring-ink-950 peer-focus-visible:outline peer-focus-visible:outline-3 peer-focus-visible:outline-saffron-500">
              <b class="block"><?= $s[0] ?></b><span class="mt-1 block text-sm opacity-80"><?= $s[1] ?></span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
      <div class="mt-4 sm:w-64">
        <label class="<?= $label ?>" for="visa_type">Visa type</label>
        <select id="visa_type" name="visa_type" class="<?= $field ?>">
          <?php foreach ($types as $key => $name): ?><option value="<?= $key ?>" <?= $f['visa_type'] == $key ? 'selected' : '' ?>><?= $name ?></option><?php endforeach; ?>
        </select>
        <?= show_error($errors, 'visa_type') ?>
      </div>
    </fieldset>

    <fieldset class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-ink-900/5 sm:p-6">
      <legend class="sr-only">Your details</legend>
      <p class="font-display text-xl font-bold">2. About you and your trip</p>
      <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
          <label class="<?= $label ?>" for="full_name">Full name (as on your passport)</label>
          <input id="full_name" name="full_name" class="<?= $field ?>" autocomplete="name" value="<?= e($f['full_name']) ?>">
          <?= show_error($errors, 'full_name') ?>
        </div>
        <div>
          <label class="<?= $label ?>" for="email">Email</label>
          <input id="email" type="email" name="email" class="<?= $field ?>" autocomplete="email" value="<?= e($f['email']) ?>">
          <?= show_error($errors, 'email') ?>
        </div>
        <div>
          <label class="<?= $label ?>" for="phone">Mobile number</label>
          <input id="phone" type="tel" name="phone" class="<?= $field ?> num" autocomplete="tel" placeholder="0803 123 4567" value="<?= e($f['phone']) ?>">
          <?= show_error($errors, 'phone') ?>
        </div>
        <div>
          <label class="<?= $label ?>" for="passport_number">Passport number <span class="normal-case tracking-normal font-normal">(optional)</span></label>
          <input id="passport_number" name="passport_number" class="<?= $field ?> uppercase" value="<?= e($f['passport_number']) ?>">
          <?= show_error($errors, 'passport_number') ?>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="<?= $label ?>" for="travel_date">Travel date</label>
            <input id="travel_date" type="date" name="travel_date" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" class="<?= $field ?> num" value="<?= e($f['travel_date']) ?>">
            <?= show_error($errors, 'travel_date') ?>
          </div>
          <div>
            <label class="<?= $label ?>" for="return_date">Return</label>
            <input id="return_date" type="date" name="return_date" class="<?= $field ?> num" value="<?= e($f['return_date']) ?>">
            <?= show_error($errors, 'return_date') ?>
          </div>
        </div>
        <div class="sm:col-span-2">
          <label class="<?= $label ?>" for="purpose">Purpose of the trip</label>
          <textarea id="purpose" name="purpose" rows="3" class="<?= $field ?>" placeholder="e.g. Visiting my sister in Manchester for her graduation"><?= e($f['purpose']) ?></textarea>
        </div>
      </div>
    </fieldset>

    <fieldset class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-ink-900/5 sm:p-6">
      <legend class="sr-only">Documents</legend>
      <p class="font-display text-xl font-bold">3. Documents</p>
      <p class="mt-1 text-sm text-ink-700">PDF, JPG or PNG, up to 5 MB each. A clear phone photo is fine. Only our visa staff can see these files.</p>
      <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <?php foreach (['passport_page' => 'Passport photo page', 'photo' => 'Passport photograph'] as $name => $text): ?>
          <div>
            <label class="<?= $label ?>" for="<?= $name ?>"><?= $text ?></label>
            <input id="<?= $name ?>" type="file" name="<?= $name ?>" accept=".pdf,.jpg,.jpeg,.png" class="mt-1 block w-full rounded-lg bg-paper-50 p-2 text-sm ring-1 ring-ink-300 file:mr-3 file:rounded-md file:border-0 file:bg-ink-900 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-paper-50">
            <?= show_error($errors, $name) ?>
          </div>
        <?php endforeach; ?>
        <div class="sm:col-span-2">
          <label class="<?= $label ?>" for="other">Other documents <span class="normal-case tracking-normal font-normal">(optional, up to 5: bank statement, letters, bookings…)</span></label>
          <input id="other" type="file" name="other[]" multiple accept=".pdf,.jpg,.jpeg,.png" class="mt-1 block w-full rounded-lg bg-paper-50 p-2 text-sm ring-1 ring-ink-300 file:mr-3 file:rounded-md file:border-0 file:bg-ink-900 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-paper-50">
          <?= show_error($errors, 'other') ?>
        </div>
      </div>
    </fieldset>

    <div class="rounded-2xl bg-ink-950 p-5 text-paper-50 sm:flex sm:items-center sm:justify-between sm:gap-6 sm:p-6">
      <div>
        <p class="font-mono text-[11px] uppercase tracking-[0.16em] text-ink-300">Our service fee</p>
        <p class="font-display text-2xl font-bold num"><?= naira($country['service_fee_kobo']) ?></p>
        <p class="mt-1 text-xs text-ink-300">Nothing to pay now. We'll confirm the next steps and how to pay after we review your documents.</p>
      </div>
      <button class="btn btn-primary mt-4 w-full px-7 py-4 text-base sm:mt-0 sm:w-auto">Send application</button>
    </div>
  </form>
</section>
<?php include 'includes/footer.php'; ?>
