<?php
require '../includes/init.php';
require '../includes/admin.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_check();
    $status = post('status');
    if (in_array($status, ['new', 'in_progress', 'closed'])) {
        db_query("UPDATE enquiries SET status = ?, assigned_to = ? WHERE id = ?", [$status, $_SESSION['staff']['id'], (int) post('id')]);
        flash('Enquiry updated.');
    }
    redirect('enquiries.php' . (get('show') ? '?show=' . urlencode(get('show')) : ''));
}

$show = get('show') == 'closed' ? 'closed' : 'open';
$enquiries = db_all("SELECT e.*, p.title AS tour_title FROM enquiries e LEFT JOIN tour_packages p ON e.type = 'tour' AND p.id = e.related_id
                     WHERE " . ($show == 'closed' ? "e.status = 'closed'" : "e.status <> 'closed'") . " ORDER BY e.id DESC LIMIT 200");

$title = 'Enquiries';
$section = 'enquiries';
include '../includes/admin-header.php';
?>
<div class="flex flex-wrap items-center justify-between gap-3">
  <h1 class="text-3xl font-extrabold">Enquiries</h1>
  <div class="flex gap-2 text-sm">
    <a href="enquiries.php" class="rounded-full px-3 py-1.5 ring-1 <?= $show == 'open' ? 'bg-ink-900 text-paper-50 ring-ink-900' : 'bg-white ring-paper-300' ?>">Open</a>
    <a href="enquiries.php?show=closed" class="rounded-full px-3 py-1.5 ring-1 <?= $show == 'closed' ? 'bg-ink-900 text-paper-50 ring-ink-900' : 'bg-white ring-paper-300' ?>">Closed</a>
  </div>
</div>
<div class="mt-5 space-y-3">
  <?php foreach ($enquiries as $q): ?>
    <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-ink-900/5">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p class="font-semibold"><?= e($q['name']) ?> <span class="font-normal text-ink-600">· <?= e($q['email']) ?><?= $q['phone'] ? ' · ' . e($q['phone']) : '' ?></span></p>
          <p class="text-xs text-ink-600"><?= date('j M Y H:i', strtotime($q['created_at'])) ?> · <?= e($q['tour_title'] ?: $q['subject']) ?></p>
        </div>
        <div class="flex items-center gap-2">
          <?= status_badge($q['status']) ?>
          <form method="post" class="flex gap-1"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $q['id'] ?>">
            <select name="status" class="rounded-md border-0 bg-paper-50 px-2 py-1 text-sm ring-1 ring-ink-300"><?php foreach (['new' => 'New', 'in_progress' => 'In progress', 'closed' => 'Closed'] as $k => $l): ?><option value="<?= $k ?>" <?= $q['status'] == $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
            <button class="btn btn-ghost px-3 py-1">Set</button>
          </form>
        </div>
      </div>
      <p class="mt-3 whitespace-pre-line text-sm text-ink-800"><?= e($q['message']) ?></p>
      <a class="mt-3 inline-block text-sm link" href="mailto:<?= e($q['email']) ?>?subject=<?= rawurlencode('Re: ' . ($q['tour_title'] ?: $q['subject'])) ?>">Reply by email</a>
    </article>
  <?php endforeach; ?>
  <?php if (!$enquiries): ?><p class="rounded-2xl bg-white p-10 text-center text-ink-600 ring-1 ring-ink-900/5">Nothing here.</p><?php endif; ?>
</div>
<?php include '../includes/admin-footer.php'; ?>
