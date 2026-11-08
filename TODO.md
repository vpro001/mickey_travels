# Mickey's Travel and Tour — Build Tracker
Last updated: 2026-11-08 15:00

## Status
Current phase: 7 — Production hardening (done; launch items need the client and live accounts) → Phase 8 is optional
Demo target: Monday (internal review) → Tuesday (client presentation)

## Client inputs needed
- [ ] Logo / brand assets
- [ ] Office address, phone, WhatsApp number, email, opening hours (shown as `{{CLIENT_ADDRESS}}`, `{{CLIENT_PHONE}}`, `{{CLIENT_WHATSAPP}}`, `{{CLIENT_EMAIL}}`, `{{CLIENT_HOURS}}`; stored in the `settings` table)
- [ ] Licence numbers (NCAA / IATA / NANTA, if any) (`{{CLIENT_LICENCES}}`)
- [ ] Preferred service fee model (default ₦2,000 per booking)
- [ ] Visa destinations offered + requirements per country (staff can edit them in the database for now; an admin screen for visa destinations is a Phase 7 item) (demo lists in `020_demo_content.sql` are a general guide, not checked against embassies)
- [ ] Visa service fees (demo values ₦35,000–₦85,000 in the seed)
- [ ] "Our story" text for the About page (`{{CLIENT_ABOUT_STORY}}`)
- [ ] Tour packages to feature (4 fictional demo packages with demo prices are seeded)
- [ ] Own photos for tours (Zanzibar and Obudu use illustrative Unsplash photos, see docs/IMAGE_CREDITS.md)
- [ ] Real exchange rates (demo values: USD 1,550, GBP 2,050, EUR 1,720)

## Accounts / credentials needed
- [ ] Duffel account (test token `duffel_test_…`) — activation for live — **blocks Phase 0 acceptance + Phases 2/3**
- [ ] Duffel Stays access (gated: request via https://duffel.com/contact-us)
- [ ] Paystack account (test keys `sk_test_…`, `pk_test_…`) — live activation — **blocks Phase 3**
- [ ] Paystack test card numbers copied by a person from https://paystack.com/docs/payments/test-payments/ (the page blocks automated reads; must not come from memory)
- [ ] SMTP credentials
- [ ] Hosting / domain

## Phase 0 — Research & foundation
- [x] Create TODO.md
- [x] Benchmark Wakanow + Travelbeta → `docs/BENCHMARK.md` (static HTML only; results/checkout not observable)
- [x] Duffel docs → `docs/API_NOTES.md` (headers/v2, errors, rate limits, offer requests, offers, orders, places, stays, webhooks, test mode)
- [x] Paystack → `docs/API_NOTES.md` (from official OpenAPI spec + official plugin; docs site returns 403)
- [ ] Paystack webhook IP list + test cards — **unverified**, docs site blocked (see Accounts)
- [x] Tailwind docs → `docs/API_NOTES.md`
- [x] Config: plain PHP array `config/config.php` (copy of `config/config.example.php`)
- [x] ~~Config loader (`.env` parser)~~ — dropped: user asked for no .env (2026-11-07)
- [x] Bootstrap (`includes/bootstrap.php`): errors → exceptions, logging with request ids, hardened session, `e()`, CSRF, flash, settings
- [x] mysqli connection (strict reporting, utf8mb4, strict sql_mode) + `db_*` helpers (`includes/db.php`)
- [x] `http_request()` (`includes/http.php`): timeouts, retry 429/503/504 on GET only, gzip, JSON, logging
- [x] `database/migrations/001_init.sql` + `bin/migrate.php` (re-run is a no-op)
- [x] `database/schema.sql` — generated from the migrated DB with mysqldump (regenerate after each migration; command in the file header)
- [x] ~~Tailwind standalone CLI build~~ — replaced by Tailwind CDN (user decision, 2026-11-07)
- [x] Tailwind CDN `@tailwindcss/browser@4.3.5` + theme tokens in `includes/header.php`
- [x] Self-hosted fonts (Bricolage Grotesque + IBM Plex Sans, OFL), with ₦ glyph checked
- [x] Base layout: `includes/header.php`, `includes/footer.php`, `includes/error-page.php`
- [x] `.htaccess`: project root blocks config/includes/storage/etc. (verified 403); public/ security headers
- [x] Acceptance: homepage shell renders with Tailwind (screenshots at 360px and 1280px)
- [x] Acceptance: DB migrates cleanly (fresh DB, 24 tables, second run no-op)
- [ ] Acceptance: `http_request()` calls one Duffel test endpoint and logs it — wrapper verified with an unauthenticated call (HTTP 401 `missing_authorization_header`, Duffel request id logged); **authenticated call pending test token**. Run `php bin/duffel-ping.php` once `duffel_token` is set.

## Phase 1 — Design system & marketing pages
- [x] `docs/DESIGN.md` (tokens, type scale, spacing, component inventory)
- [x] `bin/contrast.php` — WCAG check of all 27 text/background pairs
- [x] Self-hosted fonts
- [x] Components: buttons, inputs, badges, cards (header.php `@layer components`), search tabs, autocomplete, passenger picker
- [ ] Components still to build with their pages: flight card (Phase 2), stepper (Phase 3), tables (Phase 4), hotel card (Phase 5)
- [x] Airports: 3,245 commercial airports from OurAirports (public domain), `database/seeds/airports.csv` + `010_airports.php`, Nigerian/popular airports ranked first, spelling fixes (Enugu, Warri)
- [x] `api/airport-suggest.php`: local search first, Duffel `/places/suggestions` fallback that caches new airports
- [x] Search box (`includes/search-box.php` + `assets/js/search.js`): Flights / Hotels / Visa / Tours tabs, return / one way / multi-city (up to 4 flights), native date pickers, travellers + cabin picker, works without JS
- [x] Homepage: search hero, 12 popular routes (real search links, no invented prices), visa teaser, tours teaser, help strip
- [x] `flights.php`, `hotels.php` landing pages (service fee text comes from settings)
- [x] `contact.php` (CSRF, validation, honeypot, 5/hour/IP limit → `enquiries`) and `about.php` with placeholders
- [x] Footer + floating WhatsApp button (wa.me; shows `{{CLIENT_WHATSAPP}}` until the number is set)
- [x] Demo content seed `020_demo_content.sql`: 6 visa destinations, 4 fictional tours with 3 departures each (dates relative to today)
- [x] `.htaccess`: gzip (HTML 54 KB → 8 KB), correct MIME for .js/.woff2, 1-year font caching
- [ ] Email staff when an enquiry arrives (needs `includes/mail.php`, Phase 3)
- [x] Acceptance: responsive 360–1440px (screenshots at 360, 768, 1280, 1440; search button above the fold at 360×800)
- [x] Acceptance: AA contrast on all text pairs (`php bin/contrast.php` → all pass; lowest 4.81:1)
- [ ] Acceptance: Lighthouse mobile perf 90+ — **not measured**: installing Lighthouse from npm was blocked by the permission system. Run it in Chrome DevTools, or allow `npx lighthouse`. Expected risk: the Tailwind CDN script.
- [x] Acceptance: no Lorem ipsum (grep clean)
- [x] JS behaviour test (14 checks: autocomplete, keyboard, trip types, multi-city, steppers, tabs, validation): all pass
- Links to pages from later phases (`visa.php`, `tours.php`, `tour.php`, `visa-track.php`, `manage-booking.php`, `flight-results.php`, `hotel-results.php`) return 404 until those phases are built.

## Phase 2 — Flight search & results
- [x] Airport autocomplete (local `airports` table first, Duffel `/places/suggestions` fallback that saves new airports)
- [x] Airports from an open dataset (OurAirports, public domain), `database/airports.sql`
- [x] `includes/pricing.php` → `price_in_naira()`: the ONE pricing function (FX rate + buffer + service fee), all in kobo
- [x] Offer requests (`duffel_search_flights()`): one-way, return and multi-city (up to 4 flights)
- [x] `includes/flight-search.php`: checks the search (IATA codes, dates within 330 days and in order, max 9 travellers, infants ≤ adults)
- [x] `flight-results.php`: grey placeholders while searching, then results from `api/flight-search.php`
- [x] Filters without reload: stops, departure time, max price, airlines (with each airline's cheapest price)
- [x] Sort: cheapest, fastest, best value (price and time weighted equally)
- [x] Flight details panel (opens inside the card): each flight, terminals, aircraft, layovers, fare rules, baggage, price breakdown
- [x] Offer expiry: "Prices held for mm:ss" countdown; when it runs out the Select buttons are switched off and a "Search again" box appears
- [x] Results cached 15 min per search (never past the prices' expiry)
- [x] Demo mode (`FLIGHT_DATA = 'demo'`): `includes/demo-flights.php` makes offers in Duffel's documented format; "Demo data" banner on every page
- [x] Acceptance (demo mode): one-way, return and multi-city searches return results
- [x] Acceptance: filters work without a full reload (13 automated browser checks pass)
- [x] Acceptance: prices match `pricing.php` exactly (16 of 16 offers checked against an independent `price_in_naira()` call)
- [ ] Acceptance on **live Duffel test data**: run once `DUFFEL_TOKEN` is set and `FLIGHT_DATA` is `live`
- [ ] Airline logos: live mode uses Duffel's `owner.logo_symbol_url`; demo mode shows the airline code
- [ ] Children are searched as age 10 and infants as age 1 (the search box asks for age groups, not exact ages). Phase 3 must re-check the fare with each child's real date of birth.

## Phase 3 — Flight booking & payment
- [x] `flight-book.php`: re-checks the fare with the airline on every load and on submit; expired/unavailable fares get a friendly "search again" page
- [x] Traveller form: title, names (English letters, as on passport), gender, date of birth checked against adult (12+), child (2–11) and infant (under 2) on the last travel date; passport number, issuing country and expiry (valid after the last flight) when the airline needs them; email + phone (normalised to +234…)
- [x] Infants are attached to a different adult each (`infant_passenger_id`)
- [x] Price-change check: if the fare changed since the page was shown, the customer sees old and new prices and must press again before paying
- [x] Booking + passengers + payment saved in one transaction BEFORE going to Paystack
- [x] `includes/paystack.php`: initialize, verify, signature check (HMAC-SHA512, `hash_equals`)
- [x] `payment-callback.php`: verifies with Paystack (never trusts the browser), then `confirm_payment()`
- [x] `webhooks/paystack.php`: rejects bad signatures (401), stores each event once, answers 200 first, verifies before acting, retries events that weren't processed
- [x] `confirm_payment()`: locks the payment row, checks amount AND currency, sets `fulfilled_at` once (idempotent)
- [x] `issue_ticket()`: one process only (status lock `paid → ticketing`), re-checks the fare, creates the Duffel order with `balance`, never retried automatically, saves PNR and e-ticket numbers per passenger
- [x] Failure paths: payment failed, offer expired, price changed before payment, price rose after payment, fare gone after payment, order failed or unclear, amount mismatch → `needs_attention` + admin email + clear customer message
- [x] `booking.php`: status page + printable itinerary (prints without site header/footer); "Pay now" retry; auto-refresh while ticketing; asks Paystack again for bookings still waiting
- [x] `manage-booking.php`: find a booking by reference + email (same message whether it exists or not)
- [x] `includes/mail.php`: log / mail() / small SMTP client (STARTTLS or SSL); confirmation email with PNR, e-tickets, itinerary link; admin alerts
- [x] Status rules in one function (`flight_status()`), documented in `docs/STATE_MACHINES.md`, every change in `audit_log`
- [x] Friendly error page for any uncaught error (with a reference code in the log)
- [x] Acceptance (local, demo flights + made-up Paystack answers): paid booking → confirmed with PNR and 3 e-tickets; second confirmation changes nothing (1 airline order)
- [x] Acceptance: webhook replayed twice does not double-fulfil (stored once, replay ignored once processed)
- [x] Acceptance: tampered body, wrong signature and missing signature → 401
- [ ] Acceptance with **real Paystack test checkout**: needs `PAYSTACK_SECRET_KEY` / `PAYSTACK_PUBLIC_KEY` (sk_test_/pk_test_)
- [ ] Set the webhook URL in the Paystack dashboard: `<SITE_URL>/webhooks/paystack.php` (must be public; use the live host or a tunnel for local tests)
- [ ] Duffel webhook (`webhooks/duffel.php`, X-Duffel-Signature) for order changes: moved to Phase 6, not needed for `balance` orders, which answer straight away
- [ ] Set `ADMIN_EMAIL` and SMTP details in config.php so alerts actually reach staff

## Phase 4 — Visa, tours, admin core, demo polish
- [x] `visa.php`: all destinations, and one destination's requirements, fee, processing time and Apply button
- [x] `visa-apply.php`: service + visa type, traveller details, uploads (passport page + photo required, up to 5 more); files checked with finfo (PDF/JPG/PNG, 5 MB), random names, stored in `storage/uploads/visa/` (403 from the web); if any upload fails nothing is kept
- [x] `visa-track.php`: reference + email lookup (no data shown for a wrong email), progress bar, public history, customer upload when documents are requested (moves back to "under review")
- [x] Visa status rules + history + email on every change (`includes/visa.php`, `visa_status()`)
- [x] `tours.php` (filters: destination, month), `tour.php` (itinerary, included/not included, departures with places left, live total, deposit or full payment, enquiry form)
- [x] Tour payments through the same `confirm_payment()` (locked, amount + currency checked, once only); seats reserved on the first payment and never oversold; balance payment from `tour-booking.php`; emails
- [x] `manage-booking.php` finds flight (MTF-) and tour (MTT-) bookings
- [x] Admin login: first visit creates the admin; password_hash/verify; `session_regenerate_id`; 5 wrong tries per email or IP in 15 min → locked (stored in `login_attempts`)
- [x] Admin dashboard: payments today/this month, flight fees this month, visas waiting, enquiries, needs-attention banner
- [x] Admin flight bookings: list (status filter, search by ref/PNR/email) and detail (passengers, money trail, payments, history); actions: mark ticketed by hand with PNR, retry order (with Duffel-dashboard warning), cancel/refund, resend email
- [x] Admin tour bookings: confirm / cancel (seats returned) / refund
- [x] Admin visa workflow: status change with customer note (+ email), staff-only notes, assign to me, staff-only document download (logged)
- [x] Admin tour packages: create/edit, itinerary as "Title | text" lines, cover photo upload (cropped 4:3, 800/480 JPEG), departure dates (add, close, cancel)
- [x] Admin enquiries: open/closed, status, reply by email
- [x] Admin settings (admin only): service fee type/amount with a worked example, FX buffer, exchange rates with history, contact details (replace the {{CLIENT_…}} placeholders), staff accounts
- [x] Demo content seeded (fictional tours, visa destinations with a general requirements guide, demo exchange rates); no reviews or testimonials
- [x] `docs/DEMO_SCRIPT.md`: setup checklist + 7-minute click-path + "if something goes wrong" table
- [x] Acceptance (local): fresh install from the 3 SQL files → every public and admin page loads, admin setup works, visa application works, 0 PHP errors
- [ ] Acceptance: a non-technical person runs DEMO_SCRIPT.md end to end. Needs the Paystack test keys + test card numbers (and a Duffel test token for live flights)
- [ ] Admin pages were checked for errors and behaviour but not screenshotted (headless browser can't log in); give them a visual pass before the demo
- [ ] Logout is a plain link (GET). Move it to a POST form in Phase 7

## Phase 5 — Hotels
- [x] City → coordinates: the airport chosen in the search box + 25 km radius (documented in API_NOTES → "How the site uses Stays")
- [x] `includes/stays.php`: search, fetch all rates, quote, booking (balance, no `payment` object), hotel status rules, emails
- [x] `hotel-results.php` + `api/hotel-search.php`: placeholders while searching, star and price filters, sort by price / guest rating / stars, results cached 15 min
- [x] `hotel.php`: rooms and rates with meal plan, free-cancellation date (from `cancellation_timeline`), "only N left"
- [x] `hotel-book.php`: quote on load and on submit (price-change check), one guest name per room, contact, special requests → booking + Paystack
- [x] Payment → `confirm_payment()` (same locking and amount checks) → `book_hotel()` once → confirmed with the hotel's confirmation number, or `needs_attention` + admin alert
- [x] `hotel-booking.php`: status, hotel confirmation number, printable; pay again; asks Paystack again if still waiting
- [x] `manage-booking.php` finds MTH- bookings; admin **Hotel bookings** page (mark confirmed by hand, retry with warning, cancel/refund); dashboard banner; separate hotel fee in settings
- [x] Demo mode: `includes/demo-stays.php` (fictional hotels marked "(demo)", our own photos, **no review scores**)
- [x] Acceptance (demo): search → rooms → quote → paid → confirmed with hotel reference; repeat confirmation books once; failed hotel booking → needs_attention + alert
- [ ] **Request Duffel Stays access** (https://duffel.com/contact-us). Then test with a `duffel_test_` token via "Show Duffel's test hotels" and confirm the ⚠️ points in API_NOTES (room totals, address fields, guests per room)

## Phase 6 — Customer accounts & post-booking
- [x] `register.php`, `login.php` (5 wrong tries per email/IP in 15 min → locked; `session_regenerate_id`; `next=` only allows pages on this site), `logout.php` (POST + CSRF)
- [x] Email verification: signed link (HMAC-SHA256 with `APP_SECRET`), `verify-email.php`, "send again" button
- [x] Password reset: `forgot-password.php` (same answer whether or not the email exists; max 3 links/hour), 64-hex token emailed, only its SHA-256 stored, 1 hour, single use, other links cancelled on use (`reset-password.php`)
- [x] `account.php`: flights, hotels, holidays and visa applications. Shows bookings made while signed in, plus bookings made with the account's email **only once that email is verified** (so nobody can register someone else's email to see their trips)
- [x] New bookings and visa applications store the signed-in customer (`user_id`)
- [x] Printable itinerary/receipt: `booking.php` and `hotel-booking.php` print cleanly (done in Phases 3 and 5)
- [x] Cancellation requests from the account (confirmed flights/hotels, paid tours) → `cancellation_requested` + admin alert. Staff handle the airline/hotel side; **Duffel's cancellation endpoint is not used** until its flow is confirmed in the docs
- [x] Header shows "Sign in" / "My account"
- [x] Fixed while testing: the plain-text part of every email lost its links (verify, reset, itinerary). Links now appear as "Label: URL"
- [x] Admin logout changed to a POST form with CSRF (Phase 4 follow-up)
- [ ] Read Duffel's order-cancellation docs (`/air/order_cancellations`) and decide whether staff should cancel from the admin
- [ ] Duffel webhook (`webhooks/duffel.php`, X-Duffel-Signature HMAC-SHA256) for airline-initiated changes → email customer + staff

## Phase 7 — Production hardening
- [x] Security review against §2: every query prepared (automated sweep: only code-built SQL text concatenated); every unescaped output reviewed (only code constants/integers); 42/42 POST forms have CSRF and every POST handler calls `csrf_check()`; session cookie HttpOnly + SameSite=Lax + Secure (config); `session_regenerate_id` on every login; password_hash/verify; login rate limits; upload checks; server-side prices; webhook signature + idempotency; friendly error page with reference code
- [x] Rate limits (`rate_limit()` + `rate_limits` table): flight search 20/min, hotel search 20/min, airport suggestions 120/min, sign-ups 10/hour, visa applications 5/hour per IP (contact form 5/hour, logins 5 fails/15 min already)
- [x] Airport data cached in our own table (Duffel only asked when a place is unknown); search results cached 15 min
- [x] `.htaccess`: Content-Security-Policy, Permissions-Policy, HSTS (HTTPS only), X-Frame-Options, nosniff, Referrer-Policy; private folders blocked; commented "force HTTPS" and `ErrorDocument 404` lines for the live server
- [x] SEO: canonical links, Open Graph/Twitter tags, `noindex` on private pages, `sitemap.php` (pages, tours, visa destinations), `robots.txt`
- [x] Analytics placeholder (`includes/analytics.php`, empty until the client picks a service)
- [x] Admin **Visa destinations** screen (edit requirements, fee, processing time, types, show/hide)
- [x] `docs/DEPLOY.md`: cPanel steps, config table, HTTPS, test → live for Paystack and Duffel, daily DB backup cron line, housekeeping crons, security notes, pre-launch checklist
- [x] Fresh-install regression: all 24 public URLs and 11 admin pages load; private paths return 403. Fixed a PHP warning found there (hotels without a review score)
- [ ] Privacy policy and terms pages: the client must supply the text (needed before live payments)
- [ ] Optional: replace the Tailwind CDN with a compiled CSS file, which would let the CSP drop `'unsafe-inline'` and speed up first load (needs the user's OK; they chose the CDN)
- [ ] Lighthouse mobile run (still not measured; see Phase 1)

## Phase 8 (optional) — Extras
- [ ] Flutterwave behind `payment_*` interface
- [ ] Multi-city, seats/bags, promo codes, B2B portal

## Decisions
- 2026-11-07 — Local dev on XAMPP (PHP 8.2.12, MariaDB 10.4.32) — matches a typical cPanel minimum; code stays PHP 8.2 / MariaDB 10.4 compatible.
- 2026-11-07 — **User: keep it simple — no MVC, no templating, no .env.** Config is a plain PHP array; pages `require` `includes/header.php` / `footer.php` directly; no `templates/` folder or partial system. Overrides prompt §2 and §6.
- 2026-11-07 — **User: natural procedural PHP.** Procedural mysqli (`mysqli_prepare`, `mysqli_stmt_bind_param`, …), named functions instead of closures, `array()` syntax, no `declare(strict_types)` / `match` / `never`. Transactions are `db_begin()` / `db_commit()` / `db_rollback()`.
- 2026-11-07 — **User: plain PHP page flow, pages in the project root (no public/ folder).** Each page: `require 'includes/init.php';`, logic at the top, HTML below, plain `href="page.php"` links. Config is `config.php` with `define()` constants (copy of `config.sample.php`). Database is three .sql files imported in phpMyAdmin (`schema.sql`, `airports.sql`, `demo-content.sql`); the migration runner and bin/ scripts were removed. Private folders are blocked with a one-line `.htaccess` each. Supersedes the earlier config-array, public/ and migration decisions.
- 2026-11-07 — The price can rise between payment and ticketing (airline repricing). We never charge extra automatically: the booking goes to staff (`needs_attention`). If the price drops, we ticket at the new, lower supplier price and keep the customer's price.
- 2026-11-07 — Paystack references are `<booking ref>-<6 hex>`; a retry after a failed payment gets a new reference (Paystack references must be unique).
- 2026-11-07 — **User: the UI must be striking, without generic AI-template looks.** Redesign v2: boarding-pass search box, split-flap departures board with real distances, passport-stamp graphic, editorial numbered photo grid, big ExtraBold headlines, IBM Plex Mono labels, scroll reveals (off for reduced motion). The design tool suggested sky blue + brutalism + Inter, which was rejected as generic; we kept the ink/saffron palette that already passes AA. Details in docs/DESIGN.md → Visual language.
- 2026-11-07 — **User: the design must be polished with real photos, not bare.** 15 Unsplash photos (licence allows commercial use), cropped to WebP at two sizes; credits in docs/IMAGE_CREDITS.md. Photo heroes on home/flights/hotels/about; photo cards for destinations and tours.
- 2026-11-07 — Flight details open inside the card instead of a side drawer: simpler, and works the same on phones.
- 2026-11-07 — Results load in a background request so the page (with placeholders) shows at once; Duffel searches can take up to 20 seconds.
- 2026-11-08 — Bookings are linked to an account by `user_id`, or by email only after the email is verified (prevents someone registering another person's email to see their bookings).
- 2026-11-08 — Hotel search uses the city's airport coordinates with a 25 km radius instead of a paid geocoding service: no extra API key, and the coordinates are already in our airports table.
- 2026-11-08 — Demo hotels show no guest ratings or review counts: invented reviews are not allowed (prompt §3, rule 4), even in demo data.
- 2026-11-07 — **User: keep Duffel** for online flight (and hotel) booking, after it was explained as the provider that connects the site to airlines.
- 2026-11-07 — **User: use the Tailwind CDN, no bundling.** Overrides prompt §2 ("no CDN in production").
- 2026-11-07 — Money stored as integer kobo (`*_kobo BIGINT`), not `total_ngn` decimals — avoids float rounding; Paystack wants kobo anyway.
- 2026-11-07 — `http_request()` retries only 429/503/504 and only on GET — Duffel docs say never retry 500/502/202 (booking may exist). Conflicts with prompt §2 ("retry on 429/5xx"); docs win.
- 2026-11-07 — Duffel order `type` is `instant` | `hold` in docs vs `instant` | `pay_later` in the official JS client; we only use `instant`.
- 2026-11-07 — CSRF failure returns 403 (not 419): Apache turns the non-standard 419 into a 500.
- 2026-11-07 — Native `<input type=date>` instead of a JS date picker: zero JS weight, the phone shows its own calendar, and the format follows the phone locale (dd/mm/yyyy in en-NG).
- 2026-11-07 — Popular routes show no prices: we can only show a fare after a live search, and an invented "from ₦…" would break rule 4.
- ~~2026-11-07 — Tour cards use a typographic panel (destination on ink) until the agency uploads real photos; no stock images.~~ Replaced: the user asked for real photos (see the photo decision above).
- 2026-11-07 — Fonts: Bricolage Grotesque (display) + IBM Plex Sans (text), self-hosted; latin-ext subset loaded only for ₦.
- 2026-11-07 — `cookie_secure` is false only in the local config (http://localhost); example config defaults to true.
- 2026-11-07 — Storage is writable by Apache (`daemon`) through ACLs locally; DEPLOY.md must cover permissions on cPanel.

## Open questions / risks
- **Unverified in Duffel's docs:** whether passengers aged 12–17 can be booked as `adult` (our search treats 12+ as adult), and whether a child's real date of birth must match the age (10) used in the search. Check on the first live test booking; if Duffel rejects it, the order goes to `needs_attention` (no money is lost).
- **Tailwind CDN vs Lighthouse 90+:** the browser build is a ~74 KB (brotli) render-blocking script that compiles CSS on every page load, which will hurt mobile performance on slow connections. If Phase 1 misses 90+, the fallback is to save the generated CSS once into a static file (no build tool on the server). Needs a user decision then.
- No Duffel / Paystack test keys yet. Fixtures mode is the fallback for the demo, but Phase 3 acceptance (real Paystack test checkout) needs keys.
- Duffel `balance` payments in test mode are not explicitly documented — verify on the first test order.
- Paystack verify `data.status` enum not fully listed in the spec — code treats anything except `success` as unpaid.
