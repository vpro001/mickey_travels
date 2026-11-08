# Going live on cPanel

A step-by-step guide for putting the site on a normal cPanel host (Apache + PHP).

## 1. What the host needs

- PHP **8.2 or newer** with these extensions: `mysqli`, `curl`, `fileinfo`, `gd`, `mbstring`, `openssl` (most cPanel hosts have them; check in **Select PHP Version → Extensions**)
- MySQL 5.7+ or MariaDB 10.4+
- An SSL certificate (cPanel **SSL/TLS Status → Run AutoSSL** gives a free one)

## 2. Upload the files

1. Zip the project folder **without** `config.php`, `storage/logs/*`, `storage/cache/*` and `storage/uploads/*`.
2. In cPanel **File Manager**, upload the zip to `public_html` (or a subfolder) and extract it.
3. Folder permissions: folders `755`, files `644`. The web server must be able to write to `storage/` and `assets/img/tours/` (on most cPanel hosts PHP runs as your user, so `755` is enough).
4. Check that these return **403 Forbidden** in a browser: `/config.php`, `/includes/functions.php`, `/storage/`, `/database/schema.sql`, `/docs/DEPLOY.md`.

## 3. Database

1. cPanel **MySQL Databases**: create a database and a user with a strong password; add the user to the database with **All privileges**.
2. **phpMyAdmin** → select the database → **Import**, in this order:
   1. `database/schema.sql`
   2. `database/airports.sql`
   3. `database/demo-content.sql`: **optional**. It adds the sample tours and visa destinations. For the live site you can import it and then edit or replace everything in the admin, or skip it and add your own.

## 4. config.php

Copy `config.sample.php` to `config.php` and set:

| Setting | Live value |
|---|---|
| `SITE_URL` | `https://www.your-domain.com` (no slash at the end) |
| `APP_SECRET` | 64 random characters: run `php -r "echo bin2hex(random_bytes(32));"` |
| `DEBUG` | `false` |
| `DB_HOST` / `DB_USER` / `DB_PASS` / `DB_NAME` | from step 3 (`DB_HOST` is usually `localhost`) |
| `COOKIE_SECURE` | `true` |
| `FLIGHT_DATA` | `'live'` |
| `DUFFEL_TOKEN` | `duffel_live_…` (see section 6) |
| `PAYSTACK_SECRET_KEY` / `PAYSTACK_PUBLIC_KEY` | `sk_live_…` / `pk_live_…` |
| `MAIL_METHOD` | `'smtp'` with an email account made in cPanel **Email Accounts** (`SMTP_HOST` like `mail.your-domain.com`, port `465` or `587`) |
| `MAIL_FROM` | an address on your domain, e.g. `bookings@your-domain.com` |
| `ADMIN_EMAIL` | the office inbox that receives "ACTION NEEDED" alerts |

## 5. Web server settings (`.htaccess`)

- **Force HTTPS**: in `.htaccess`, remove the `#` from the three "Force HTTPS" lines.
- **404 page**: remove the `#` from `ErrorDocument 404 /404.php` (if the site is in a subfolder, use `/subfolder/404.php`).
- `robots.txt`: change the `Sitemap:` line to your address, then submit `https://your-domain.com/sitemap.php` in Google Search Console.

## 6. Switching from test to live

**Paystack**
1. Complete business verification in the Paystack dashboard so live mode is activated.
2. Copy the **live** secret and public keys into `config.php`.
3. **Settings → API Keys & Webhooks**: set the live webhook URL to `https://your-domain.com/webhooks/paystack.php`.
4. Make one real small payment (for example a cheap domestic flight or a tour deposit) and refund it from the dashboard.

**Duffel**
1. Activate live mode in the Duffel dashboard (company details, agreement).
2. **Top up the Duffel balance**. Every flight and hotel is paid from it, so a low balance means bookings fail (they go to "needs attention"; no customer money is lost).
3. Create a **live** access token and put it in `DUFFEL_TOKEN`.
4. Hotels: Duffel Stays must be switched on for the live account too.
5. Do one real booking end to end and cancel it in the Duffel dashboard if needed.

**Exchange rates**: in the admin, **Fees, rates & contact**, set today's real rates. Update them every morning.

## 7. Backups

- **Weekly full backup**: cPanel **Backup Wizard → Full backup**, sent to your email or a remote destination. Keep at least the last 4.
- **Daily database backup** with cPanel **Cron Jobs** (once a day, e.g. 02:00). Replace the user, password and database name:

  ```
  mkdir -p ~/backups && mysqldump --single-transaction -u DBUSER -p'DBPASS' DBNAME | gzip > ~/backups/mickey-$(date +\%F).sql.gz && find ~/backups -name 'mickey-*.sql.gz' -mtime +14 -delete
  ```

  This keeps 14 days. `~/backups` is outside `public_html`, so it isn't public.
- Uploaded visa documents live in `storage/uploads/`, which is included in the full backup. **These are passport scans: never store backups anywhere public.**
- Test a restore once: import a backup into a new empty database in phpMyAdmin.

## 8. Housekeeping (optional cron jobs)

```
# Every night: delete cached searches older than a day and logs older than 90 days
find ~/public_html/storage/cache -name '*.json' -mtime +1 -delete
find ~/public_html/storage/logs -name '*.log' -mtime +90 -delete
```

## 9. Security notes

- **Content security policy**: `.htaccess` sends a policy that only allows scripts from this site and the Tailwind CDN, only lets forms post to this site and Paystack, and blocks the site being framed by others. It has to allow `'unsafe-inline'` for scripts and styles because the Tailwind CDN builds the styles in the browser and pages use small inline scripts. Moving to a compiled CSS file later would let us remove that.
- Admin login, customer login, searches, sign-ups, contact messages and visa applications all have rate limits.
- Staff accounts: give each person their own login (**Fees, rates & contact → Staff accounts**) and switch accounts off when people leave.
- Keep `DEBUG` set to `false` on the live site. Errors are logged in `storage/logs/php-errors.log`, and customers see a friendly page with a reference code.

## 10. Pre-launch checklist

- [ ] HTTPS works and http:// redirects to https://
- [ ] `/config.php`, `/storage/`, `/includes/…`, `/database/…`, `/docs/…` all return 403
- [ ] `DEBUG` is `false`, `COOKIE_SECURE` is `true`, `APP_SECRET` is set
- [ ] Admin account created; staff accounts added
- [ ] Contact details, WhatsApp number and licence numbers filled in (no `{{CLIENT_…}}` left on the site)
- [ ] Exchange rates and service fees checked
- [ ] Visa destinations: requirements checked against current embassy rules; fees set
- [ ] Tour packages: real photos, prices and departure dates; demo packages removed or unpublished
- [ ] Paystack live keys, live webhook URL set, one real payment tested and refunded
- [ ] Duffel live token, balance topped up, Stays enabled, one real booking tested
- [ ] SMTP sends: test the contact form, a password reset and a booking confirmation
- [ ] `ADMIN_EMAIL` receives alerts (trigger one by searching with an expired offer in test mode)
- [ ] Daily database backup cron job added and one restore tested
- [ ] `robots.txt` sitemap line updated; sitemap submitted to Google Search Console
- [ ] Privacy policy and terms pages written (needed before taking real payments; not built yet, client to supply text)
