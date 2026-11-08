<?php
require '../includes/init.php';
require '../includes/admin.php';
require '../includes/visa.php';

$id = (int) get('id');
$app = db_one("SELECT a.*, d.country_name FROM visa_applications a JOIN visa_destinations d ON d.id = a.destination_id WHERE a.id = ?", [$id]);
if (!$app) {
    redirect('visas.php');
}
$names = visa_status_names();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_check();
    $note = post('note');
    if (post('action') == 'status') {
        $to = post('status');
        if ($to == 'documents_requested' && $note == '') {
            flash('Write which documents the customer needs to send. They see this note.', 'error');
        } elseif (visa_status($id, $to, $note, post('public') == '1', $_SESSION['staff']['id'])) {
            flash('Status changed to "' . $names[$to] . '". The customer has been emailed.');
        } else {
            flash('That change is not allowed from the current status.', 'error');
        }
    } elseif (post('action') == 'note' && $note != '') {
        db_insert('visa_status_history', ['application_id' => $id, 'from_status' => $app['status'], 'to_status' => $app['status'],
            'note' => $note, 'is_public' => 0, 'changed_by' => $_SESSION['staff']['id']]);
        flash('Note added (staff only).');
    } elseif (post('action') == 'assign') {
        db_query("UPDATE visa_applications SET assigned_to = ? WHERE id = ?", [$_SESSION['staff']['id'], $id]);
        flash('Assigned to you.');
    }
    redirect('visa.php?id=' . $id);
}

$docs = db_all("SELECT * FROM visa_documents WHERE application_id = ? ORDER BY id", [$id]);
$history = db_all("SELECT h.*, u.full_name FROM visa_status_history h LEFT JOIN users u ON u.id = h.changed_by WHERE h.application_id = ? ORDER BY h.id DESC", [$id]);
$assigned = $app['assigned_to'] ? db_one("SELECT full_name FROM users WHERE id = ?", [$app['assigned_to']]) : null;
$next = visa_next_statuses($app['status']);

$title = $app['reference'];
$section = 'visas';
include '../includes/admin-header.php';
$small = 'font-mono text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-600';
$service_names = ['application' => 'Full application', 'support' => 'Document check only', 'visit' => 'Visit visa'];
?>
<a href="visas.php" class="text-sm text-ink-600 hover:text-ink-900">← Visa applications</a>
<div class="mt-2 flex flex-wrap items-center gap-3">
  <h1 class="font-mono text-3xl font-bold"><?= e($app['reference']) ?></h1>
  <?= status_badge($app['status']) ?>
</div>
<p class="mt-1 text-ink-700"><?= e($app['country_name']) ?> · <?= e(ucfirst($app['visa_type'])) ?> visa · <?= e($service_names[$app['service']]) ?></p>

<div class="mt-6 grid gap-6 xl:grid-cols-[1.4fr_1fr]">
  <div class="space-y-6">
    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5">
      <dl class="grid gap-4 sm:grid-cols-2 text-sm">
        <div><dt class="<?= $small ?>">Applicant</dt><dd class="mt-1 font-semibold"><?= e($app['full_name']) ?></dd></div>
        <div><dt class="<?= $small ?>">Contact</dt><dd class="mt-1"><?= e($app['email']) ?><br><?= e($app['phone']) ?></dd></div>
        <div><dt class="<?= $small ?>">Passport</dt><dd class="mt-1 font-mono"><?= e($app['passport_number'] ?: '—') ?></dd></div>
        <div><dt class="<?= $small ?>">Travel</dt><dd class="mt-1 num"><?= nice_date($app['travel_date']) ?><?= $app['return_date'] ? ' – ' . nice_date($app['return_date']) : '' ?></dd></div>
        <div class="sm:col-span-2"><dt class="<?= $small ?>">Purpose</dt><dd class="mt-1"><?= e($app['purpose'] ?: '—') ?></dd></div>
        <div><dt class="<?= $small ?>">Our fee</dt><dd class="mt-1 num"><?= naira($app['service_fee_kobo']) ?></dd></div>
        <div><dt class="<?= $small ?>">Handled by</dt><dd class="mt-1"><?= $assigned ? e($assigned['full_name']) : 'Nobody yet' ?></dd></div>
      </dl>
    </section>

    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5">
      <h2 class="font-display text-lg font-bold">Documents</h2>
      <ul class="mt-3 divide-y divide-paper-200 text-sm">
        <?php foreach ($docs as $d): ?>
          <li class="flex items-center justify-between gap-3 py-2.5">
            <span class="min-w-0"><b class="block truncate"><?= e($d['original_name']) ?></b><span class="text-xs text-ink-600"><?= e(str_replace('_', ' ', $d['doc_type'])) ?> · <?= round($d['size_bytes'] / 1024) ?> KB · from <?= e($d['uploaded_by']) ?> · <?= date('j M H:i', strtotime($d['created_at'])) ?></span></span>
            <a class="btn btn-ghost shrink-0 px-3 py-1.5" href="download.php?id=<?= $d['id'] ?>">Download</a>
          </li>
        <?php endforeach; ?>
        <?php if (!$docs): ?><li class="py-3 text-ink-600">No documents.</li><?php endif; ?>
      </ul>
    </section>

    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5">
      <h2 class="font-display text-lg font-bold">History</h2>
      <ol class="mt-3 space-y-3 text-sm">
        <?php foreach ($history as $h): ?>
          <li class="rounded-lg p-3 <?= $h['is_public'] ? 'bg-paper-50' : 'bg-warn-100/60' ?>">
            <p><b><?= $h['from_status'] == $h['to_status'] ? 'Note' : e($names[$h['to_status']]) ?></b>
              <span class="text-xs text-ink-600">· <?= date('j M Y H:i', strtotime($h['created_at'])) ?> · <?= e($h['full_name'] ?: 'customer/system') ?> · <?= $h['is_public'] ? 'customer can see' : 'staff only' ?></span></p>
            <?php if ($h['note']): ?><p class="mt-1 text-ink-800"><?= nl2br(e($h['note'])) ?></p><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>
  </div>

  <div class="space-y-6">
    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5">
      <h2 class="font-display text-lg font-bold">Change status</h2>
      <?php if ($next): ?>
        <form method="post" class="mt-4 space-y-3">
          <?= csrf_field() ?><input type="hidden" name="action" value="status">
          <select name="status" class="field mt-0"><?php foreach ($next as $n): ?><option value="<?= $n ?>"><?= $names[$n] ?></option><?php endforeach; ?></select>
          <textarea name="note" rows="4" class="field mt-0" placeholder="Message for the customer, e.g. which documents to send or the appointment date"></textarea>
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="public" value="1" checked class="size-4 accent-ink-900"> Show this note to the customer</label>
          <button class="btn btn-dark w-full">Update and email the customer</button>
        </form>
      <?php else: ?><p class="mt-3 text-sm text-ink-600">This application is completed.</p><?php endif; ?>
    </section>
    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5">
      <h2 class="font-display text-lg font-bold">Staff note</h2>
      <form method="post" class="mt-3 space-y-3">
        <?= csrf_field() ?><input type="hidden" name="action" value="note">
        <textarea name="note" rows="3" class="field mt-0" placeholder="Only staff see this"></textarea>
        <button class="btn btn-ghost w-full">Add note</button>
      </form>
      <?php if (!$app['assigned_to'] || $app['assigned_to'] != $_SESSION['staff']['id']): ?>
        <form method="post" class="mt-3"><?= csrf_field() ?><input type="hidden" name="action" value="assign"><button class="btn btn-ghost w-full">Assign to me</button></form>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php include '../includes/admin-footer.php'; ?>
