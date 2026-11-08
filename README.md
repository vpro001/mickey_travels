# Mickey's Travel and Tour

Plain PHP pages and a MySQL database. No framework and no build step.

## Install

1. Copy the folder to your web server (e.g. `htdocs/mickey` on XAMPP, or `public_html` on cPanel).
2. In phpMyAdmin, create a database (utf8mb4_unicode_ci), then import these files in order:
   1. `database/schema.sql`
   2. `database/airports.sql`
   3. `database/demo-content.sql` (sample tours and visa destinations for the demo)
3. Copy `config.sample.php` to `config.php` and fill in your database login, `SITE_URL`, a random `APP_SECRET` and the API keys.
4. Make sure the web server can write to `storage/` and `assets/img/tours/`.
5. Open the site, e.g. http://localhost/mickey/
6. Open `/admin/`. The first visit asks you to create the admin account.

For the client demo, follow `docs/DEMO_SCRIPT.md`.

## Files

| | |
|---|---|
| `index.php`, `flights.php`, `contact.php`, … | The pages. Each one starts with `require 'includes/init.php';` |
| `includes/init.php` | Loads config, connects to the database, starts the session |
| `includes/functions.php` | Shared functions: database (`db_all`, `db_one`, `db_insert`), `e()` for safe output, CSRF, formatting |
| `includes/header.php`, `footer.php` | Top and bottom of every page |
| `includes/search-box.php` | The Flights / Hotels / Visa / Tours search box |
| `includes/http.php`, `duffel.php` | Calls to the flight and hotel API |
| `api/airport-suggest.php` | Airport suggestions for the search box |
| `assets/` | CSS (fonts), JavaScript, fonts, icons |
| `database/` | SQL files to import |
| `docs/` | Notes on the APIs and the design |
| `storage/` | Logs and uploaded files (not public) |

Folders that must not be public (`includes`, `database`, `storage`, `docs`) each have an `.htaccess` that blocks web access.
