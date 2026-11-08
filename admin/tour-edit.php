<?php
require '../includes/init.php';
require '../includes/admin.php';

// Create or edit a tour package, its photo and its departure dates.
$id = (int) get('id');
$tour = $id ? db_one("SELECT * FROM tour_packages WHERE id = ?", [$id]) : null;
if ($id && !$tour) {
    redirect('tours.php');
}

// Itinerary is stored as JSON; staff edit it as one day per line: "Title | What happens"
function itinerary_to_text($json)
{
    $lines = [];
    foreach (json_decode((string) $json, true) ?: [] as $day) {
        $lines[] = $day['title'] . ' | ' . $day['body'];
    }
    return implode("\n", $lines);
}

function text_to_itinerary($text)
{
    $days = [];
    foreach (preg_split('/\r?\n/', trim($text)) as $line) {
        if (trim($line) == '') continue;
        $parts = array_map('trim', explode('|', $line, 2));
        $days[] = ['day' => count($days) + 1, 'title' => $parts[0], 'body' => isset($parts[1]) ? $parts[1] : ''];
    }
    return $days;
}

// Resize an uploaded photo to 800px and 480px wide JPEGs in assets/img/tours/
function save_tour_photo($file)
{
    if (empty($file['tmp_name']) || $file['error'] != UPLOAD_ERR_OK || $file['size'] > 8 * 1024 * 1024) {
        return [null, 'Choose a JPG or PNG photo up to 8 MB.'];
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    $source = $mime == 'image/jpeg' ? imagecreatefromjpeg($file['tmp_name']) : ($mime == 'image/png' ? imagecreatefrompng($file['tmp_name']) : null);
    if (!$source) {
        return [null, 'The photo must be a JPG or PNG.'];
    }
    $name = 'tours/' . bin2hex(random_bytes(8));
    $w = imagesx($source);
    $h = imagesy($source);
    // Crop to 4:3 from the middle, then resize
    $crop_w = min($w, (int) ($h * 4 / 3));
    $crop_h = (int) ($crop_w * 3 / 4);
    foreach ([800, 480] as $size) {
        $out = imagecreatetruecolor($size, (int) ($size * 3 / 4));
        imagecopyresampled($out, $source, 0, 0, (int) (($w - $crop_w) / 2), (int) (($h - $crop_h) / 2), $size, (int) ($size * 3 / 4), $crop_w, $crop_h);
        imagejpeg($out, __DIR__ . "/../assets/img/$name-$size.jpg", 80);
    }
    return [$name, ''];
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_check();
    $action = post('action');

    if ($action == 'save') {
        $data = [
            'title' => post('title'),
            'slug' => strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', post('slug') ?: post('title')), '-')),
            'destination' => post('destination'),
            'country_code' => strtoupper(post('country_code')),
            'summary' => post('summary'),
            'description' => post('description'),
            'duration_days' => (int) post('duration_days'),
            'duration_nights' => (int) post('duration_nights'),
            'price_kobo' => (int) round((float) str_replace(',', '', post('price')) * 100),
            'price_basis' => post('price_basis') ?: 'per person sharing',
            'deposit_pct' => max(0, min(100, (int) post('deposit_pct'))),
            'itinerary_json' => json_encode(text_to_itinerary(post('itinerary'))),
            'inclusions' => post('inclusions'),
            'exclusions' => post('exclusions'),
            'is_published' => post('is_published') ? 1 : 0,
            'is_featured' => post('is_featured') ? 1 : 0,
            'sort_order' => (int) post('sort_order'),
        ];
        if (strlen($data['title']) < 3) $errors[] = 'Give the package a title.';
        if ($data['destination'] == '') $errors[] = 'Enter the destination.';
        if (!preg_match('/^[A-Z]{2}$/', $data['country_code'])) $errors[] = 'Country code must be 2 letters, e.g. AE.';
        if ($data['summary'] == '' || $data['description'] == '') $errors[] = 'Write a summary and a description.';
        if ($data['duration_days'] < 1) $errors[] = 'Enter the number of days.';
        if ($data['price_kobo'] < 100) $errors[] = 'Enter the price per person in naira.';
        $clash = db_one("SELECT id FROM tour_packages WHERE slug = ? AND id <> ?", [$data['slug'], $id]);
        if ($clash) $errors[] = 'Another package already uses that web address (slug).';

        if (!empty($_FILES['photo']['name'])) {
            list($photo, $photo_error) = save_tour_photo($_FILES['photo']);
            if ($photo_error) $errors[] = $photo_error;
            else $data['cover_image'] = $photo;
        }

        if (!$errors) {
            if ($id) {
                $sets = implode(', ', array_map(function ($c) { return "$c = ?"; }, array_keys($data)));
                db_query("UPDATE tour_packages SET $sets WHERE id = ?", array_merge(array_values($data), [$id]));
            } else {
                $id = db_insert('tour_packages', $data);
            }
            flash('Package saved.');
            redirect('tour-edit.php?id=' . $id);
        }
        $tour = array_merge($tour ?: [], $data);
    }

    if ($action == 'add_departure' && $id) {
        $start = post('start_date');
        $seats = (int) post('seats');
        $override = post('price_override') !== '' ? (int) round((float) str_replace(',', '', post('price_override')) * 100) : null;
        if (!valid_date($start) || $start <= date('Y-m-d') || $seats < 1) {
            flash('Enter a future start date and at least 1 seat.', 'error');
        } else {
            $end = date('Y-m-d', strtotime($start . ' +' . ($tour['duration_days'] - 1) . ' days'));
            db_insert('tour_departures', ['package_id' => $id, 'start_date' => $start, 'end_date' => $end, 'seats_total' => $seats, 'price_kobo_override' => $override]);
            flash('Departure added.');
        }
        redirect('tour-edit.php?id=' . $id . '#dates');
    }

    if ($action == 'departure_status' && $id) {
        $status = post('status');
        if (in_array($status, ['open', 'closed', 'cancelled'])) {
            db_query("UPDATE tour_departures SET status = ? WHERE id = ? AND package_id = ?", [$status, (int) post('departure_id'), $id]);
            flash('Departure updated.' . ($status == 'cancelled' ? ' Contact anyone booked on it.' : ''));
        }
        redirect('tour-edit.php?id=' . $id . '#dates');
    }
}

$t = $tour ?: ['title' => '', 'slug' => '', 'destination' => '', 'country_code' => '', 'summary' => '', 'description' => '', 'duration_days' => 5, 'duration_nights' => 4,
    'price_kobo' => 0, 'price_basis' => 'per person sharing', 'deposit_pct' => 30, 'itinerary_json' => '[]', 'inclusions' => '', 'exclusions' => '', 'is_published' => 0, 'is_featured' => 0, 'sort_order' => 0, 'cover_image' => ''];
$departures = $id ? db_all("SELECT d.*, (SELECT COUNT(*) FROM tour_bookings b WHERE b.departure_id = d.id AND b.amount_paid_kobo > 0) AS bookings FROM tour_departures d WHERE d.package_id = ? ORDER BY d.start_date", [$id]) : [];

$title = $id ? 'Edit: ' . $t['title'] : 'New package';
$section = 'tours';
include '../includes/admin-header.php';
$label = 'field-label';
?>
<a href="tours.php" class="text-sm text-ink-600 hover:text-ink-900">← Tour packages</a>
<div class="mt-2 flex flex-wrap items-center justify-between gap-3">
  <h1 class="text-3xl font-extrabold"><?= $id ? e($t['title']) : 'New package' ?></h1>
  <?php if ($id && $t['is_published']): ?><a href="../tour.php?slug=<?= e($t['slug']) ?>" target="_blank" class="link text-sm">View on the site ↗</a><?php endif; ?>
</div>

<?php if ($errors): ?>
  <div class="mt-5 rounded-xl bg-bad-100 p-4 text-sm text-bad-700"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="mt-6 grid gap-6 xl:grid-cols-[1.5fr_1fr]">
  <?= csrf_field() ?><input type="hidden" name="action" value="save">
  <section class="space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5">
    <div class="grid gap-4 sm:grid-cols-2">
      <div class="sm:col-span-2"><label class="<?= $label ?>" for="title">Title</label><input id="title" name="title" class="field" value="<?= e($t['title']) ?>"></div>
      <div><label class="<?= $label ?>" for="destination">Destination</label><input id="destination" name="destination" class="field" value="<?= e($t['destination']) ?>" placeholder="e.g. Zanzibar"></div>
      <div><label class="<?= $label ?>" for="country_code">Country code</label><input id="country_code" name="country_code" maxlength="2" class="field uppercase" value="<?= e($t['country_code']) ?>" placeholder="TZ"></div>
      <div class="sm:col-span-2"><label class="<?= $label ?>" for="summary">Summary (one sentence, shown on cards)</label><input id="summary" name="summary" maxlength="300" class="field" value="<?= e($t['summary']) ?>"></div>
      <div class="sm:col-span-2"><label class="<?= $label ?>" for="description">Description</label><textarea id="description" name="description" rows="4" class="field"><?= e($t['description']) ?></textarea></div>
      <div class="sm:col-span-2">
        <label class="<?= $label ?>" for="itinerary">Day by day (one day per line: Title | What happens)</label>
        <textarea id="itinerary" name="itinerary" rows="7" class="field font-mono text-sm" placeholder="Arrive in Zanzibar | Airport pickup and transfer to your hotel."><?= e(itinerary_to_text($t['itinerary_json'])) ?></textarea>
      </div>
      <div><label class="<?= $label ?>" for="inclusions">Included (one per line)</label><textarea id="inclusions" name="inclusions" rows="6" class="field text-sm"><?= e($t['inclusions']) ?></textarea></div>
      <div><label class="<?= $label ?>" for="exclusions">Not included (one per line)</label><textarea id="exclusions" name="exclusions" rows="6" class="field text-sm"><?= e($t['exclusions']) ?></textarea></div>
    </div>
  </section>

  <section class="space-y-4">
    <div class="space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5">
      <div class="grid grid-cols-2 gap-3">
        <div><label class="<?= $label ?>" for="duration_days">Days</label><input id="duration_days" type="number" min="1" name="duration_days" class="field" value="<?= (int) $t['duration_days'] ?>"></div>
        <div><label class="<?= $label ?>" for="duration_nights">Nights</label><input id="duration_nights" type="number" min="0" name="duration_nights" class="field" value="<?= (int) $t['duration_nights'] ?>"></div>
        <div><label class="<?= $label ?>" for="price">Price per person (₦)</label><input id="price" name="price" inputmode="decimal" class="field num" value="<?= $t['price_kobo'] ? number_format($t['price_kobo'] / 100, 0, '.', '') : '' ?>"></div>
        <div><label class="<?= $label ?>" for="deposit_pct">Deposit %</label><input id="deposit_pct" type="number" min="0" max="100" name="deposit_pct" class="field" value="<?= (int) $t['deposit_pct'] ?>"></div>
        <div class="col-span-2"><label class="<?= $label ?>" for="price_basis">Price basis</label><input id="price_basis" name="price_basis" class="field" value="<?= e($t['price_basis']) ?>"></div>
        <div><label class="<?= $label ?>" for="slug">Web address (slug)</label><input id="slug" name="slug" class="field font-mono text-sm" value="<?= e($t['slug']) ?>" placeholder="from the title"></div>
        <div><label class="<?= $label ?>" for="sort_order">Order</label><input id="sort_order" type="number" name="sort_order" class="field" value="<?= (int) $t['sort_order'] ?>"></div>
      </div>
      <div class="space-y-2 text-sm">
        <label class="flex items-center gap-2"><input type="checkbox" name="is_published" value="1" class="size-4 accent-ink-900" <?= $t['is_published'] ? 'checked' : '' ?>> Published on the site</label>
        <label class="flex items-center gap-2"><input type="checkbox" name="is_featured" value="1" class="size-4 accent-ink-900" <?= $t['is_featured'] ? 'checked' : '' ?>> Featured on the homepage</label>
      </div>
    </div>
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5">
      <label class="<?= $label ?>" for="photo">Cover photo</label>
      <?php if ($t['cover_image']):
          $img = file_exists(__DIR__ . '/../assets/img/' . $t['cover_image'] . '-480.webp') ? $t['cover_image'] . '-480.webp' : $t['cover_image'] . '-480.jpg'; ?>
        <img src="../assets/img/<?= e($img) ?>" alt="" class="mt-2 aspect-[4/3] w-full rounded-lg object-cover">
      <?php endif; ?>
      <input id="photo" type="file" name="photo" accept=".jpg,.jpeg,.png" class="mt-2 block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-ink-900 file:px-3 file:py-2 file:text-paper-50">
      <p class="mt-1 text-xs text-ink-600">A landscape photo from the trip works best. It's cropped to 4:3.</p>
    </div>
    <button class="btn btn-primary w-full py-3.5">Save package</button>
  </section>
</form>

<?php if ($id): ?>
<section id="dates" class="mt-8 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5">
  <h2 class="font-display text-xl font-bold">Departure dates</h2>
  <table class="mt-4 w-full text-left text-sm">
    <thead class="text-xs text-ink-600"><tr><th class="py-2 font-medium">Dates</th><th class="py-2 font-medium">Seats</th><th class="py-2 font-medium">Price</th><th class="py-2 font-medium">Status</th><th class="py-2"></th></tr></thead>
    <tbody class="divide-y divide-paper-200">
      <?php foreach ($departures as $d): ?>
        <tr>
          <td class="py-2.5 num"><?= nice_date($d['start_date']) ?> – <?= nice_date($d['end_date']) ?></td>
          <td class="py-2.5 num"><?= (int) $d['seats_booked'] ?> / <?= (int) $d['seats_total'] ?> booked</td>
          <td class="py-2.5 num"><?= $d['price_kobo_override'] ? naira($d['price_kobo_override']) : 'Standard' ?></td>
          <td class="py-2.5"><?= status_badge($d['status'] == 'open' ? 'open' : $d['status']) ?></td>
          <td class="py-2.5 text-right">
            <form method="post" class="inline-flex gap-1" onsubmit="return this.status.value !== 'cancelled' || confirm('Cancel this departure? Anyone booked on it must be contacted.')">
              <?= csrf_field() ?><input type="hidden" name="action" value="departure_status"><input type="hidden" name="departure_id" value="<?= $d['id'] ?>">
              <select name="status" class="rounded-md border-0 bg-paper-50 px-2 py-1 text-sm ring-1 ring-ink-300"><?php foreach (['open', 'closed', 'cancelled'] as $s): ?><option <?= $d['status'] == $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select>
              <button class="btn btn-ghost px-3 py-1">Set</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$departures): ?><tr><td colspan="5" class="py-4 text-ink-600">No dates yet. Customers can only send enquiries until you add one.</td></tr><?php endif; ?>
    </tbody>
  </table>
  <form method="post" class="mt-5 grid gap-3 rounded-xl bg-paper-100 p-4 sm:grid-cols-[1fr_8rem_1fr_auto] sm:items-end">
    <?= csrf_field() ?><input type="hidden" name="action" value="add_departure">
    <div><label class="<?= $label ?>" for="start_date">Start date</label><input id="start_date" type="date" name="start_date" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" class="field"></div>
    <div><label class="<?= $label ?>" for="seats">Seats</label><input id="seats" type="number" name="seats" min="1" value="16" class="field"></div>
    <div><label class="<?= $label ?>" for="price_override">Different price per person (₦, optional)</label><input id="price_override" name="price_override" inputmode="decimal" class="field num"></div>
    <button class="btn btn-dark py-3">Add date</button>
  </form>
</section>
<?php endif; ?>
<?php include '../includes/admin-footer.php'; ?>
