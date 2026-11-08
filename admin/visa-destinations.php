<?php
require '../includes/init.php';
require '../includes/admin.php';

// Visa destinations shown on the site: requirements, fee, processing time.
$types = ['tourist' => 'Tourist', 'business' => 'Business', 'visit' => 'Family visit', 'student' => 'Student', 'transit' => 'Transit'];
$id = (int) get('id');
$edit = $id ? db_one("SELECT * FROM visa_destinations WHERE id = ?", [$id]) : null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_check();
    $chosen = isset($_POST['types']) && is_array($_POST['types']) ? array_intersect(array_keys($types), $_POST['types']) : [];
    $data = [
        'country_name' => post('country_name'),
        'slug' => strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', post('slug') ?: post('country_name')), '-')),
        'country_code' => strtoupper(post('country_code')),
        'visa_types' => implode(',', $chosen),
        'summary' => post('summary'),
        'requirements' => trim(post('requirements')),
        'processing_time' => post('processing_time'),
        'service_fee_kobo' => (int) round((float) str_replace(',', '', post('fee')) * 100),
        'is_active' => post('is_active') ? 1 : 0,
        'sort_order' => (int) post('sort_order'),
    ];
    if (strlen($data['country_name']) < 2) $errors[] = 'Enter the country name.';
    if (!preg_match('/^[A-Z]{2}$/', $data['country_code'])) $errors[] = 'Country code must be 2 letters (EU for Schengen).';
    if (!$chosen) $errors[] = 'Tick at least one visa type.';
    if ($data['summary'] == '' || $data['requirements'] == '') $errors[] = 'Write a summary and the list of requirements.';
    if (db_one("SELECT id FROM visa_destinations WHERE slug = ? AND id <> ?", [$data['slug'], $id])) $errors[] = 'Another destination already uses that web address.';
    if (!$errors) {
        if ($id) {
            $sets = implode(', ', array_map(function ($c) { return "$c = ?"; }, array_keys($data)));
            db_query("UPDATE visa_destinations SET $sets WHERE id = ?", array_merge(array_values($data), [$id]));
        } else {
            $id = db_insert('visa_destinations', $data);
        }
        db_insert('audit_log', ['user_id' => $_SESSION['staff']['id'], 'action' => 'save', 'entity_type' => 'visa_destination', 'entity_id' => $id, 'details' => json_encode(['country' => $data['country_name']]), 'ip' => $_SERVER['REMOTE_ADDR']]);
        flash($data['country_name'] . ' saved.');
        redirect('visa-destinations.php');
    }
    $edit = array_merge($edit ?: [], $data);
}

$all = db_all("SELECT d.*, (SELECT COUNT(*) FROM visa_applications a WHERE a.destination_id = d.id) AS apps FROM visa_destinations d ORDER BY d.sort_order, d.country_name");
$show_form = $edit || get('new') || $errors;
$e = $edit ?: ['country_name' => '', 'slug' => '', 'country_code' => '', 'visa_types' => 'tourist', 'summary' => '', 'requirements' => '', 'processing_time' => '', 'service_fee_kobo' => 0, 'is_active' => 1, 'sort_order' => 100];

$title = 'Visa destinations';
$section = 'visa-destinations';
include '../includes/admin-header.php';
?>
<div class="flex flex-wrap items-center justify-between gap-3">
  <h1 class="text-3xl font-extrabold">Visa destinations</h1>
  <a href="visa-destinations.php?new=1" class="btn btn-dark">+ New destination</a>
</div>
<p class="mt-2 max-w-2xl text-sm text-ink-700">These show on the Visa pages. Keep each requirement list checked against the embassy's current rules.</p>

<?php if ($show_form): ?>
  <?php if ($errors): ?><div class="mt-5 rounded-xl bg-bad-100 p-4 text-sm text-bad-700"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div><?php endif; ?>
  <form method="post" action="visa-destinations.php<?= $id ? '?id=' . $id : '' ?>" class="mt-6 grid gap-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5 lg:grid-cols-2">
    <?= csrf_field() ?>
    <div><label class="field-label" for="country_name">Country</label><input id="country_name" name="country_name" class="field" value="<?= e($e['country_name']) ?>"></div>
    <div class="grid grid-cols-2 gap-3">
      <div><label class="field-label" for="country_code">Code</label><input id="country_code" name="country_code" maxlength="2" class="field uppercase" value="<?= e($e['country_code']) ?>"></div>
      <div><label class="field-label" for="slug">Web address</label><input id="slug" name="slug" class="field font-mono text-sm" value="<?= e($e['slug']) ?>" placeholder="from the name"></div>
    </div>
    <div class="lg:col-span-2"><label class="field-label" for="summary">Summary</label><input id="summary" name="summary" maxlength="300" class="field" value="<?= e($e['summary']) ?>"></div>
    <div class="lg:col-span-2"><label class="field-label" for="requirements">Requirements (one per line)</label><textarea id="requirements" name="requirements" rows="7" class="field"><?= e($e['requirements']) ?></textarea></div>
    <div><label class="field-label" for="processing_time">Processing time</label><input id="processing_time" name="processing_time" class="field" value="<?= e($e['processing_time']) ?>" placeholder="e.g. Usually 3 weeks"></div>
    <div class="grid grid-cols-2 gap-3">
      <div><label class="field-label" for="fee">Our fee (₦)</label><input id="fee" name="fee" inputmode="decimal" class="field num" value="<?= $e['service_fee_kobo'] ? number_format($e['service_fee_kobo'] / 100, 0, '.', '') : '' ?>"></div>
      <div><label class="field-label" for="sort_order">Order</label><input id="sort_order" type="number" name="sort_order" class="field" value="<?= (int) $e['sort_order'] ?>"></div>
    </div>
    <fieldset class="lg:col-span-2"><legend class="field-label">Visa types</legend>
      <div class="mt-2 flex flex-wrap gap-4 text-sm">
        <?php foreach ($types as $k => $l): ?><label class="flex items-center gap-2"><input type="checkbox" name="types[]" value="<?= $k ?>" class="size-4 accent-ink-900" <?= in_array($k, explode(',', $e['visa_types'])) ? 'checked' : '' ?>> <?= $l ?></label><?php endforeach; ?>
      </div>
    </fieldset>
    <label class="flex items-center gap-2 text-sm lg:col-span-2"><input type="checkbox" name="is_active" value="1" class="size-4 accent-ink-900" <?= $e['is_active'] ? 'checked' : '' ?>> Show on the site</label>
    <div class="flex gap-3 lg:col-span-2"><button class="btn btn-dark">Save</button><a class="btn btn-ghost" href="visa-destinations.php">Cancel</a></div>
  </form>
<?php endif; ?>

<div class="mt-6 overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-ink-900/5">
  <table class="w-full text-left text-sm">
    <thead class="border-b border-paper-200 text-xs text-ink-600"><tr><th class="px-5 py-3 font-medium">Country</th><th class="px-3 py-3 font-medium">Fee</th><th class="px-3 py-3 font-medium">Processing</th><th class="px-3 py-3 font-medium">Applications</th><th class="px-3 py-3 font-medium">On site</th><th class="px-5 py-3"></th></tr></thead>
    <tbody class="divide-y divide-paper-200">
      <?php foreach ($all as $d): ?>
        <tr class="hover:bg-paper-50">
          <td class="px-5 py-3 font-semibold"><?= e($d['country_name']) ?> <span class="font-mono text-xs text-ink-500"><?= e($d['country_code']) ?></span></td>
          <td class="px-3 py-3 num"><?= naira($d['service_fee_kobo']) ?></td>
          <td class="px-3 py-3 text-ink-700"><?= e($d['processing_time']) ?></td>
          <td class="px-3 py-3"><?= (int) $d['apps'] ?></td>
          <td class="px-3 py-3"><?= $d['is_active'] ? '<span class="badge bg-ok-100 text-ok-700">Shown</span>' : '<span class="badge bg-ink-100 text-ink-700">Hidden</span>' ?></td>
          <td class="px-5 py-3 text-right"><a class="link" href="visa-destinations.php?id=<?= $d['id'] ?>">Edit</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php include '../includes/admin-footer.php'; ?>
