<?php
require '../includes/init.php';
require '../includes/admin.php';
require '../includes/visa.php';

$status = get('status');
$names = visa_status_names();
$sql = "SELECT a.*, d.country_name, (SELECT COUNT(*) FROM visa_documents x WHERE x.application_id = a.id) AS docs
        FROM visa_applications a JOIN visa_destinations d ON d.id = a.destination_id";
$params = [];
if (isset($names[$status])) {
    $sql .= " WHERE a.status = ?";
    $params[] = $status;
}
$apps = db_all($sql . " ORDER BY FIELD(a.status, 'submitted', 'under_review', 'documents_requested', 'processing') DESC, a.id DESC LIMIT 200", $params);

$title = 'Visa applications';
$section = 'visas';
include '../includes/admin-header.php';
?>
<h1 class="text-3xl font-extrabold">Visa applications</h1>
<div class="mt-5 flex flex-wrap gap-2 text-sm">
  <a href="visas.php" class="rounded-full px-3 py-1.5 ring-1 <?= $status == '' ? 'bg-ink-900 text-paper-50 ring-ink-900' : 'bg-white ring-paper-300' ?>">All</a>
  <?php foreach ($names as $k => $n): ?>
    <a href="visas.php?status=<?= $k ?>" class="rounded-full px-3 py-1.5 ring-1 <?= $status == $k ? 'bg-ink-900 text-paper-50 ring-ink-900' : 'bg-white ring-paper-300' ?>"><?= $n ?></a>
  <?php endforeach; ?>
</div>
<div class="mt-5 overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-ink-900/5">
  <table class="w-full text-left text-sm">
    <thead class="border-b border-paper-200 text-xs text-ink-600">
      <tr><th class="px-5 py-3 font-medium">Reference</th><th class="px-3 py-3 font-medium">Applicant</th><th class="px-3 py-3 font-medium">Destination</th><th class="px-3 py-3 font-medium">Travel</th><th class="px-3 py-3 font-medium">Docs</th><th class="px-5 py-3 font-medium">Status</th></tr>
    </thead>
    <tbody class="divide-y divide-paper-200">
      <?php foreach ($apps as $a): ?>
        <tr class="hover:bg-paper-50">
          <td class="px-5 py-3"><a class="font-mono font-semibold hover:underline" href="visa.php?id=<?= $a['id'] ?>"><?= e($a['reference']) ?></a><br><span class="text-xs text-ink-600"><?= date('j M Y', strtotime($a['created_at'])) ?></span></td>
          <td class="px-3 py-3"><?= e($a['full_name']) ?><br><span class="text-xs text-ink-600"><?= e($a['email']) ?></span></td>
          <td class="px-3 py-3"><?= e($a['country_name']) ?><br><span class="text-xs capitalize text-ink-600"><?= e($a['visa_type'] . ' · ' . $a['service']) ?></span></td>
          <td class="px-3 py-3 num"><?= nice_date($a['travel_date']) ?></td>
          <td class="px-3 py-3"><?= (int) $a['docs'] ?></td>
          <td class="px-5 py-3"><?= status_badge($a['status']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$apps): ?><tr><td colspan="6" class="px-5 py-10 text-center text-ink-600">No applications.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php include '../includes/admin-footer.php'; ?>
