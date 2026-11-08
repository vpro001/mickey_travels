<?php
require '../includes/init.php';
require '../includes/admin.php';
require '../includes/pricing.php';
admin_only();

$contact_fields = ['phone' => 'Phone', 'whatsapp' => 'WhatsApp number (with country code, e.g. 2348031234567)', 'email' => 'Email', 'address' => 'Office address', 'hours' => 'Opening hours', 'licences' => 'Licence numbers (IATA, NANTA, NCAA…)'];

function save_setting($name, $value)
{
    db_query("INSERT INTO settings (name, value, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_by = VALUES(updated_by)",
        [$name, $value, $_SESSION['staff']['id']]);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_check();
    $action = post('action');

    if ($action == 'fees') {
        $type = post('markup_type');
        $value = (float) str_replace(',', '', post('markup_value'));
        $buffer = (float) post('fx_buffer_pct');
        if (!in_array($type, ['flat_booking', 'flat_passenger', 'percent']) || $value < 0 || ($type == 'percent' && $value > 50) || $buffer < 0 || $buffer > 20) {
            flash('Check the values: the fee must be 0 or more (a percentage at most 50) and the buffer between 0 and 20%.', 'error');
        } else {
            save_setting('markup_type', $type);
            save_setting('markup_value', (string) $value);
            save_setting('fx_buffer_pct', (string) $buffer);
            audit_settings('fees', ['markup_type' => $type, 'markup_value' => $value, 'fx_buffer_pct' => $buffer]);
            flash('Fees saved. New searches use them straight away.');
        }
    }

    if ($action == 'hotel_fees') {
        $type = post('hotel_markup_type') == 'percent' ? 'percent' : 'flat_booking';
        $value = (float) str_replace(',', '', post('hotel_markup_value'));
        if ($value < 0 || ($type == 'percent' && $value > 50)) {
            flash('The hotel fee must be 0 or more (a percentage at most 50).', 'error');
        } else {
            save_setting('hotel_markup_type', $type);
            save_setting('hotel_markup_value', (string) $value);
            audit_settings('hotel_fees', ['type' => $type, 'value' => $value]);
            flash('Hotel fee saved.');
        }
    }

    if ($action == 'rates') {
        $rates = isset($_POST['rate']) && is_array($_POST['rate']) ? $_POST['rate'] : [];
        $new_code = strtoupper(post('new_currency'));
        if ($new_code != '' && post('new_rate') != '') {
            $rates[$new_code] = post('new_rate');
        }
        $changed = 0;
        foreach ($rates as $code => $rate) {
            $rate = (float) str_replace(',', '', $rate);
            if (!preg_match('/^[A-Z]{3}$/', $code) || $code == 'NGN' || $rate <= 0) continue;
            $old = db_one("SELECT rate_to_ngn FROM fx_rates WHERE currency = ?", [$code]);
            if ($old && (float) $old['rate_to_ngn'] == $rate) continue;
            db_query("INSERT INTO fx_rates (currency, rate_to_ngn, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE rate_to_ngn = VALUES(rate_to_ngn), updated_by = VALUES(updated_by)",
                [$code, (string) $rate, $_SESSION['staff']['id']]);
            db_insert('fx_rate_history', ['currency' => $code, 'rate_to_ngn' => (string) $rate, 'updated_by' => $_SESSION['staff']['id']]);
            $changed++;
        }
        flash($changed ? plural($changed, 'rate') . ' updated.' : 'No rates changed.');
    }

    if ($action == 'contact') {
        foreach ($contact_fields as $key => $label) {
            save_setting('contact_' . $key, post($key));
        }
        audit_settings('contact', []);
        flash('Contact details saved. They now show across the site.');
    }

    if ($action == 'add_staff') {
        $email = strtolower(post('email'));
        $name = post('name');
        $password = isset($_POST['password']) ? (string) $_POST['password'] : '';
        $role = post('role') == 'admin' ? 'admin' : 'staff';
        if (!valid_email($email) || strlen($name) < 2 || strlen($password) < 10) {
            flash('Enter a name, an email and a password of at least 10 characters.', 'error');
        } elseif (db_one("SELECT id FROM users WHERE email = ?", [$email])) {
            flash('Someone already has that email.', 'error');
        } else {
            db_insert('users', ['email' => $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'full_name' => $name, 'role' => $role]);
            flash("$name can now log in.");
        }
    }

    if ($action == 'toggle_staff' && (int) post('id') != $_SESSION['staff']['id']) {
        db_query("UPDATE users SET is_active = 1 - is_active WHERE id = ? AND role IN ('staff', 'admin')", [(int) post('id')]);
        flash('Account updated.');
    }
    redirect('settings.php');
}

function audit_settings($what, $details)
{
    db_insert('audit_log', ['user_id' => $_SESSION['staff']['id'], 'action' => 'settings:' . $what, 'entity_type' => 'settings', 'details' => json_encode($details), 'ip' => $_SERVER['REMOTE_ADDR']]);
}

$rates = db_all("SELECT r.*, u.full_name FROM fx_rates r LEFT JOIN users u ON u.id = r.updated_by ORDER BY currency");
$staff = db_all("SELECT id, full_name, email, role, is_active, last_login_at FROM users WHERE role IN ('staff', 'admin') ORDER BY full_name");
$fee_type = setting('markup_type', 'flat_booking');
$example = price_in_naira('500.00', 'USD', 2);

$title = 'Fees, rates & contact';
$section = 'settings';
include '../includes/admin-header.php';
?>
<h1 class="text-3xl font-extrabold">Fees, rates &amp; contact</h1>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
  <form method="post" class="space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5">
    <?= csrf_field() ?><input type="hidden" name="action" value="fees">
    <h2 class="font-display text-xl font-bold">Our service fee on flights</h2>
    <div class="space-y-2 text-sm">
      <?php foreach (['flat_booking' => 'A fixed amount per booking', 'flat_passenger' => 'A fixed amount per traveller', 'percent' => 'A percentage of the airline price'] as $k => $l): ?>
        <label class="flex items-center gap-2"><input type="radio" name="markup_type" value="<?= $k ?>" class="size-4 accent-ink-900" <?= $fee_type == $k ? 'checked' : '' ?>> <?= $l ?></label>
      <?php endforeach; ?>
    </div>
    <div class="grid grid-cols-2 gap-3">
      <div><label class="field-label" for="markup_value">Amount (₦) or percentage</label><input id="markup_value" name="markup_value" inputmode="decimal" class="field num" value="<?= e(setting('markup_value', '2000')) ?>"></div>
      <div><label class="field-label" for="fx_buffer_pct">Exchange-rate buffer %</label><input id="fx_buffer_pct" name="fx_buffer_pct" inputmode="decimal" class="field num" value="<?= e(setting('fx_buffer_pct', '0')) ?>"></div>
    </div>
    <p class="text-xs text-ink-600">The buffer covers the rate moving before we pay the airline. It's added to foreign-currency fares only.</p>
    <?php if ($example): ?>
      <p class="rounded-lg bg-paper-100 p-3 text-sm">Example with the saved settings: a <b>$500</b> fare for 2 travellers costs the customer <b class="num"><?= naira($example['total_kobo']) ?></b> (airline <?= naira($example['base_kobo']) ?> + our fee <?= naira($example['markup_kobo']) ?>).</p>
    <?php endif; ?>
    <button class="btn btn-dark">Save fees</button>
  </form>

  <form method="post" class="space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5">
    <?= csrf_field() ?><input type="hidden" name="action" value="hotel_fees">
    <h2 class="font-display text-xl font-bold">Our service fee on hotels</h2>
    <div class="space-y-2 text-sm">
      <?php $hotel_type = setting('hotel_markup_type', 'flat_booking'); foreach (['flat_booking' => 'A fixed amount per booking', 'percent' => 'A percentage of the hotel price'] as $k => $l): ?>
        <label class="flex items-center gap-2"><input type="radio" name="hotel_markup_type" value="<?= $k ?>" class="size-4 accent-ink-900" <?= $hotel_type == $k ? 'checked' : '' ?>> <?= $l ?></label>
      <?php endforeach; ?>
    </div>
    <div class="w-48"><label class="field-label" for="hotel_markup_value">Amount (₦) or percentage</label><input id="hotel_markup_value" name="hotel_markup_value" inputmode="decimal" class="field num" value="<?= e(setting('hotel_markup_value', '2000')) ?>"></div>
    <button class="btn btn-dark">Save hotel fee</button>
  </form>

  <form method="post" class="space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5">
    <?= csrf_field() ?><input type="hidden" name="action" value="rates">
    <h2 class="font-display text-xl font-bold">Exchange rates</h2>
    <p class="text-sm text-ink-700">Naira for 1 unit of each currency. Airlines price in these; we convert at this rate (plus the buffer). Update them every morning.</p>
    <table class="w-full text-sm">
      <thead class="text-xs text-ink-600"><tr><th class="py-1 text-left font-medium">Currency</th><th class="py-1 text-left font-medium">₦ per unit</th><th class="py-1 text-left font-medium">Last changed</th></tr></thead>
      <tbody>
        <?php foreach ($rates as $r): ?>
          <tr>
            <td class="py-1.5 font-mono font-semibold"><?= e($r['currency']) ?></td>
            <td class="py-1.5"><input name="rate[<?= e($r['currency']) ?>]" inputmode="decimal" class="w-32 rounded-md border-0 bg-paper-50 px-2 py-1.5 ring-1 ring-ink-300 num" value="<?= rtrim(rtrim(number_format($r['rate_to_ngn'], 6, '.', ''), '0'), '.') ?>"></td>
            <td class="py-1.5 text-xs text-ink-600 num"><?= date('j M Y H:i', strtotime($r['updated_at'])) ?><?= $r['full_name'] ? ' · ' . e($r['full_name']) : '' ?></td>
          </tr>
        <?php endforeach; ?>
        <tr>
          <td class="py-1.5"><input name="new_currency" maxlength="3" placeholder="New" class="w-20 rounded-md border-0 bg-paper-50 px-2 py-1.5 font-mono uppercase ring-1 ring-ink-300"></td>
          <td class="py-1.5"><input name="new_rate" inputmode="decimal" placeholder="Rate" class="w-32 rounded-md border-0 bg-paper-50 px-2 py-1.5 ring-1 ring-ink-300"></td>
          <td></td>
        </tr>
      </tbody>
    </table>
    <button class="btn btn-dark">Save rates</button>
  </form>

  <form method="post" class="space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5">
    <?= csrf_field() ?><input type="hidden" name="action" value="contact">
    <h2 class="font-display text-xl font-bold">Contact details on the site</h2>
    <p class="text-sm text-ink-700">Empty fields show as placeholders like {{CLIENT_PHONE}}, so nothing made-up ever appears.</p>
    <?php foreach ($contact_fields as $key => $label): ?>
      <div>
        <label class="field-label" for="c-<?= $key ?>"><?= e($label) ?></label>
        <?php if ($key == 'address'): ?>
          <textarea id="c-<?= $key ?>" name="<?= $key ?>" rows="2" class="field"><?= e(setting('contact_' . $key)) ?></textarea>
        <?php else: ?>
          <input id="c-<?= $key ?>" name="<?= $key ?>" class="field" value="<?= e(setting('contact_' . $key)) ?>">
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <button class="btn btn-dark">Save contact details</button>
  </form>

  <div class="space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5">
    <h2 class="font-display text-xl font-bold">Staff accounts</h2>
    <ul class="divide-y divide-paper-200 text-sm">
      <?php foreach ($staff as $s): ?>
        <li class="flex items-center justify-between gap-3 py-2.5">
          <span><b><?= e($s['full_name']) ?></b> <span class="text-ink-600">· <?= e($s['email']) ?> · <?= e($s['role']) ?></span><br><span class="text-xs text-ink-500"><?= $s['last_login_at'] ? 'Last in ' . date('j M Y H:i', strtotime($s['last_login_at'])) : 'Never logged in' ?></span></span>
          <?php if ($s['id'] != $_SESSION['staff']['id']): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_staff"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button class="btn btn-ghost px-3 py-1"><?= $s['is_active'] ? 'Switch off' : 'Switch on' ?></button></form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <form method="post" class="grid gap-3 rounded-xl bg-paper-100 p-4 sm:grid-cols-2">
      <?= csrf_field() ?><input type="hidden" name="action" value="add_staff">
      <input name="name" placeholder="Name" class="field mt-0" aria-label="Name">
      <input name="email" type="email" placeholder="Email" class="field mt-0" aria-label="Email">
      <input name="password" type="password" placeholder="Password (10+ characters)" autocomplete="new-password" class="field mt-0" aria-label="Password">
      <select name="role" class="field mt-0" aria-label="Role"><option value="staff">Staff</option><option value="admin">Admin (can change these settings)</option></select>
      <button class="btn btn-dark sm:col-span-2">Add staff member</button>
    </form>
  </div>
</div>
<?php include '../includes/admin-footer.php'; ?>
