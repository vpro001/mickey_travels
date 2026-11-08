# BUILD PROMPT — Mickey's Travel and Tour (OTA website)


## 0. Who you are and how you work

You are a senior full-stack PHP engineer and product designer. You have shipped online travel agency (OTA) products and payment integrations in Nigeria. You write code that a client could run in production. You do not write throwaway demo code that has to be rewritten later.

Work rules, which apply for the whole session:

1. **Create `TODO.md` first.** Before you write any code, create `TODO.md` at the project root using the template in §9. Update it as you finish each task by ticking boxes and adding notes. If you find new work, add it to the right phase. Do not delete items. Strike them through and say why.
2. **Never assume how an API works.** For every external API (Duffel, Paystack, and any others you add), open the official documentation with your web tools *before* you write the integration. Record what you confirmed in `docs/API_NOTES.md`: the endpoint, method, required headers, request body, response fields you use, error shape, test-mode behaviour, the source URL, and the date you read it. If the docs and this prompt disagree, **the docs win**. Note the conflict in `API_NOTES.md` and in `TODO.md`.
3. **Work one phase at a time.** Finish a phase, check it against its acceptance criteria, update `TODO.md`, then start the next phase. Do not scaffold every phase at once.
4. **Don't fake data or claims.** Never invent the client's phone number, address, IATA/NANTA/NCAA licence numbers, reviews, testimonials, partner logos, or awards. Use clearly marked placeholders (`{{CLIENT_PHONE}}`) and list them under "Client inputs needed" in `TODO.md`.
5. **Ask only when you're blocked.** If a decision is cheap to change later, make it, write it under "Decisions" in `TODO.md`, and keep going.

---

## 1. Business context

**Client:** Mickey's Travel and Tour, a travel agency in Nigeria.
**Benchmark sites:** wakanow.com and travelbeta.com. Study both before you design anything, using your web tools. Note their information architecture, search-form behaviour, results filtering, booking steps, and trust signals. **Do not copy their branding, layout, copy, or assets.** The goal is to match their *functional* standard with a distinct identity.

**Deadline reality:** We are presenting a working demo to the prospect on **Tuesday**, and the internal review is **Monday**. Phases 0–4 are the demo. They must run end-to-end in test mode (real API sandbox calls, real Paystack test checkout) and look finished. Later phases make it production-complete.

**What the client wants the site to do:**
- **Flights:** customers search, compare, book, and pay for airline tickets directly on the site, without going to the airline's site. The agency adds a service fee on top of the airline fare, **₦2,000 per booking by default**. The fee must be configurable in the admin as a flat amount per booking, a flat amount per passenger, or a percentage.
- **Hotels:** search and reserve hotels.
- **Visa services:** visa application assistance, visa support, and visit visas. This is a service workflow handled by agency staff, not an API.
- **Tours and holiday packages:** packages the agency curates and sells.
- **Admin back office:** manage bookings, visa applications, packages, enquiries, pricing and markup, customers, and site content.

---

## 2. Hard technical constraints

- **PHP 8.2+, procedural only.** No classes for application logic, no MVC, and no frameworks such as Laravel, Slim, or CodeIgniter. Organise the code into function libraries in `includes/` with clear prefixes (`db_`, `auth_`, `duffel_`, `paystack_`, `fmt_`, `view_`). Each page is a PHP file that does its own request handling at the top and renders below.
- **MySQL/MariaDB with mysqli.** Every query that touches user input must be a **prepared statement**. Turn on `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)`. Use `utf8mb4` everywhere. Use transactions for any booking or payment state change.
- **No Composer dependencies** unless a dependency is truly unavoidable. Do HTTP with PHP's cURL extension through one wrapper, `http_request()`, which handles timeouts, retries on 429/5xx with backoff, gzip, JSON decoding, and logging.
- **Tailwind CSS through the standalone Tailwind CLI**, compiled to `public/assets/css/app.css`. Do not use the CDN script in production. Check the current Tailwind version and its install and config method in the official docs before you set it up, because v4 configuration differs from v3. Keep JavaScript to small vanilla scripts with no SPA framework. Alpine.js is allowed for small interactions if you need it.
- **Shared-hosting friendly.** The site must run on a typical cPanel host (Apache + PHP-FPM, `.htaccess`). The document root is `public/`, and everything else sits outside the web root.
- **Config** lives in `config/config.php`, which reads from a `.env`-style file you parse yourself. Commit `config/config.example.php` and never commit real keys.

### Security baseline (required)
- CSRF token on every POST form. Session cookies set to `HttpOnly`, `Secure`, and `SameSite=Lax`, and call `session_regenerate_id()` on login.
- Use `password_hash()` / `password_verify()`. Rate-limit login attempts and store the attempts in the database.
- Escape all output through one helper, `e()`, which wraps `htmlspecialchars` with `ENT_QUOTES` and UTF-8.
- File uploads (visa documents): check an allow-list of MIME types with `finfo`, cap the size, use randomised filenames, store files outside `public/`, and serve them only through an authenticated download script.
- Never trust prices from the browser. Re-fetch or re-price every offer server-side before you take payment.
- Verify Paystack webhook signatures, and make all payment handling idempotent (see §5).
- Log API errors to `storage/logs/` with request IDs. Never show raw API errors or stack traces to customers.

---

## 3. Design direction: no generic AI UI

The site has to look like a Nigerian travel brand with a point of view, not a template. Rules:

**Avoid:** purple/indigo gradients, glassmorphism, floating blobs, emoji used as icons, a centered "Your journey starts here" hero over a stock beach photo, three identical feature cards with icons, `rounded-3xl` on everything, gray-on-gray low-contrast text, and fake "Trusted by 10,000+ travellers" counters.

**Do:**
- Pick one deliberate palette and write it down in `docs/DESIGN.md` as Tailwind theme tokens. Suggested starting point, which you may refine: a deep ink/navy base, one confident accent (warm saffron or terracotta), and a neutral sand/paper background. Check WCAG AA contrast for every text/background pair.
- Typography: one characterful display face for headings and one highly legible text face, both from Google Fonts and self-hosted. Use tabular numerals for prices and times.
- **The search box is the hero.** Put it above the fold on desktop and mobile, with tabs for Flights, Hotels, Visa, and Tours. Include airport autocomplete that shows city, airport name, and IATA code; a date picker; a passenger/cabin selector; and one-way, return, and multi-city options.
- Flight results should be dense and scannable like a real OTA: airline logo, times, duration, stops, baggage, fare total in ₦, and filters for stops, airlines, departure time, and price. Include a sort bar (cheapest, fastest, best). Show skeleton loaders while results load.
- Design mobile-first. Most of the client's customers will be on phones over patchy connections, so pages must stay light. Target a Lighthouse performance score of 90+ on mobile for the homepage.
- Write copy in plain, warm Nigerian English. Show prices in Naira with `₦` and thousands separators.
- Trust signals should be real or placeholders only: payment-provider badges, the client's licence numbers once supplied, a physical office address, and a WhatsApp contact button.

Before you build pages, create `docs/DESIGN.md` with the tokens, type scale, spacing, and the component inventory (buttons, inputs, search tabs, flight card, hotel card, status badges, stepper, tables).

---

## 4. External integrations: what we know and what you must verify

Research done on 7 Nov 2026 — **re-verify all of this against the official docs before coding**:

### Flights and hotels: Duffel (primary)
- **Do not use Amadeus Self-Service.** Amadeus decommissioned the Self-Service portal and its API keys on 17 July 2026. Only Enterprise (contracted) access remains. Do not follow old tutorials that use `test.api.amadeus.com`.
- Use **Duffel** (`https://api.duffel.com`). It has self-serve sign-up and test-mode tokens prefixed `duffel_test_`, and it covers flights from 300+ airlines, including African carriers, plus **Stays** (hotels).
- Things to confirm in the docs and record in `API_NOTES.md`:
  - the current `Duffel-Version` header value (v2 appears current; v1 examples still exist online), and the required `Authorization: Bearer`, `Accept`, `Accept-Encoding: gzip`, and `Content-Type` headers;
  - the flight flow: **Offer Requests → Offers → (re-fetch the single offer to re-price) → Orders**, including passenger fields, the `payments` object (type `balance` for agency-paid tickets), instant vs hold orders, and offer expiry (`expires_at`);
  - the test airline(s) and test routes available in test mode, so the demo books successfully every time;
  - **Places / airport suggestions** endpoint for autocomplete;
  - the **Stays** flow: search → fetch all rates → quote → booking, the **test hotel coordinates** (the docs list a test-hotel location to search), and whether Stays needs account activation;
  - webhooks (order events), the error format, and rate limits;
  - which **currencies** offers come back in. Offers are usually not in NGN. Design a conversion layer (see below).
- **Money flow (confirm with docs):** the customer pays the agency in NGN through Paystack. The agency's Duffel balance pays the airline or hotel. The site creates the Duffel order **only after** Paystack payment is verified server-side, and only after re-pricing the offer. If the price went up, show the new price and ask the customer to confirm before charging any difference.
- **FX:** store an admin-set exchange rate per currency (`fx_rates` table, with `updated_at` and `updated_by`), plus a configurable FX buffer %. Show customers NGN prices. Store the supplier currency, the supplier amount, the rate used, the markup, and the NGN total on every booking for audit.

### Payments: Paystack (primary), Flutterwave (optional later)
- Confirm in the docs: `POST /transaction/initialize` (amounts in **kobo**, `reference`, `callback_url`, `metadata`), `GET /transaction/verify/:reference`, the webhook `charge.success` event, and the `x-paystack-signature` header (**HMAC-SHA512 of the raw request body with the secret key**). Also confirm Paystack's published webhook source IPs if you add an IP allow-list.
- Rules: generate the reference server-side, keep it unique, and store it before redirecting. On callback **and** webhook, always call verify, check that the amount and currency match the database, and fulfil only once (use a DB unique key plus a status transition guard, because webhooks can arrive twice). Respond 200 to the webhook quickly and do the slow work after that.
- Use test mode for the demo. Put test card details in `docs/DEMO_SCRIPT.md`, sourced from Paystack's docs and not from memory.

### Visa, tours, contact
- **No API.** Visa is a form plus document upload plus a staff workflow. Statuses: `submitted → under_review → documents_requested → processing → approved / refused → completed`. Customers can track their application status with a reference and email. Notify by email on each status change.
- Tours are packages managed in the admin (itinerary days, inclusions/exclusions, gallery, price per person, departure dates). Customers book with a deposit or full payment through Paystack, or send an enquiry.
- **Email:** use SMTP with your own minimal procedural SMTP client, or PHP `mail()` as a fallback. Make it configurable. Send booking confirmations with the PNR/booking reference, the e-ticket details returned by Duffel, and a printable itinerary page.
- **WhatsApp:** a click-to-chat link (`wa.me`) only. No WhatsApp Business API for now.

### Demo fallback
If Duffel test credentials are not available on Monday, build `DATA_SOURCE=fixtures`. In this mode the same `duffel_*` functions return recorded JSON fixtures captured from real test-mode responses or built exactly to the documented schema. Show a small "Demo data" badge in this mode. The UI code must not know which source it is using.

---

## 5. Database (initial schema to refine in Phase 0)

Write `database/schema.sql` and numbered migration files (`database/migrations/001_init.sql`, …) with InnoDB, utf8mb4, foreign keys, and indexes. The minimum tables:

`users` (customers + staff, `role` enum), `login_attempts`, `settings` (key/value: markup type/amount, FX buffer, contact info), `fx_rates`, `airports` (seeded from a reliable open dataset; note the source and licence), `flight_bookings` (offer id, Duffel order id, booking reference/PNR, passengers JSON, supplier currency/amount, fx rate, markup, total_ngn, status, expires_at), `flight_passengers`, `hotel_bookings`, `payments` (reference UNIQUE, provider, amount_kobo, currency, status, raw verify response, paid_at, booking_type, booking_id), `webhook_events` (provider, event id/hash UNIQUE, payload, processed_at), `visa_applications`, `visa_documents`, `visa_status_history`, `tour_packages`, `tour_departures`, `tour_bookings`, `enquiries`, `pages` (editable content), `audit_log`.

Booking statuses must be explicit state machines. Document the allowed transitions in `docs/STATE_MACHINES.md` and enforce them in a single function per entity.

---

## 6. Folder structure

```
/public              ← document root
  index.php, flights.php, flight-results.php, flight-book.php, checkout.php,
  payment-callback.php, hotels.php, hotel-results.php, hotel-book.php,
  visa.php, visa-apply.php, visa-track.php, tours.php, tour.php,
  account/…, admin/…, api/ (small JSON endpoints: airport-suggest.php, …),
  webhooks/paystack.php, webhooks/duffel.php
  assets/css, assets/js, assets/img, .htaccess
/includes            ← bootstrap.php, db.php, auth.php, csrf.php, http.php,
                       duffel.php, paystack.php, pricing.php, fx.php, mail.php,
                       uploads.php, view.php (layout/partials), validate.php
/templates           ← partials: header, footer, search-box, flight-card, …
/config              ← config.example.php
/database            ← schema.sql, migrations/, seeds/
/storage             ← logs/, uploads/ (outside web root), cache/
/docs                ← API_NOTES.md, DESIGN.md, STATE_MACHINES.md, DEMO_SCRIPT.md, DEPLOY.md
/tailwind            ← input.css (+ config if the current version needs one)
TODO.md, README.md
```

---

## 7. Phases

Each phase ends with its acceptance criteria checked and `TODO.md` updated.

### Phase 0: Research and foundation
- Create `TODO.md`. Study the Wakanow and Travelbeta UX and write a one-page summary in `docs/BENCHMARK.md` covering features and flows, not design copying.
- Read the Duffel, Paystack, and Tailwind docs and fill in `docs/API_NOTES.md`.
- Bootstrap: config loader, mysqli connection, error handling, the `e()` helper, CSRF, sessions, the HTTP wrapper, logging, schema and migrations, the Tailwind build, and the base layout.
- **Accept when:** the homepage shell renders with compiled Tailwind, the DB migrates cleanly, and `http_request()` successfully calls one Duffel test endpoint and logs it.

### Phase 1: Design system and marketing pages (demo-critical)
- `docs/DESIGN.md`, the components, a homepage with the tabbed search hero, popular routes (real links into search), a visa and tours teaser, an About/Contact page with placeholders, a footer, and the WhatsApp button.
- **Accept when:** the homepage is responsive from 360px to 1440px, passes AA contrast, scores 90+ on Lighthouse mobile performance, and has no placeholder Lorem ipsum.

### Phase 2: Flight search and results (demo-critical)
- Airport autocomplete (Duffel places + local `airports` table cache), offer requests, a results page with filters/sort/skeletons, the offer detail drawer (fare conditions and baggage as the API returns them), NGN pricing with markup and FX applied in **one** pricing function, and offer expiry handling.
- **Accept when:** a one-way and a return search on Duffel test data return results, filters work without a full page reload, and prices match `pricing.php` output exactly.

### Phase 3: Flight booking and payment (demo-critical)
- Passenger form with validation rules taken from the docs (names as on passport, DOB, gender, title, email, phone), server-side re-price, booking record, Paystack initialize → callback → verify → create Duffel order (balance) → confirmation page and email, plus the webhook handler with signature check and idempotency.
- Failure paths: payment failed, offer expired, price changed, order creation failed after payment. In that last case, mark the booking `needs_attention`, alert the admin, and tell the customer clearly that staff will contact them. Never silently lose money.
- **Accept when:** a full test booking goes from search to a confirmation with a booking reference, the webhook replayed twice does not double-fulfil, and a tampered webhook signature is rejected.

### Phase 4: Visa, tours, admin core, demo polish (demo-critical)
- Visa service pages, the application form with uploads, and the tracking page. Tour listing and detail pages, enquiry and booking. Admin: login, dashboard (bookings today, revenue, pending visas), bookings list and detail, visa workflow with status changes, packages CRUD, and markup/FX settings.
- Seed realistic but clearly fictional demo content (tours, visa destinations), with no fake reviews.
- Write `docs/DEMO_SCRIPT.md`, a 7-minute click-path for Tuesday's presentation that includes test card details and test routes.
- **Accept when:** someone non-technical can follow `DEMO_SCRIPT.md` from start to finish without errors on a fresh install.

### Phase 5: Hotels
- Duffel Stays: destination search (geocode city → coordinates, using a documented method), results, rates, quote, booking, payment, confirmation. If Stays access is pending, ship the flow against the documented test hotels and record the activation step in `TODO.md`.

### Phase 6: Customer accounts and post-booking
- Register/login, password reset (a time-limited token stored hashed), My Bookings, My Visa Applications, downloadable itinerary/receipt (printable HTML; PDF optional), and cancellation requests routed to staff. Use the Duffel cancellation endpoint only after you confirm the flow in the docs.

### Phase 7: Production hardening
- Security review against §2, rate limiting on search endpoints, caching of airport data, backups, `.htaccess` hardening, security headers, error pages, a sitemap and SEO meta, an analytics placeholder, `docs/DEPLOY.md` for cPanel, and a switch from test keys to live keys with a pre-launch checklist (Duffel live activation, Paystack live activation, SMTP, SSL).

### Phase 8 (optional): Extras
- Flutterwave as a second gateway behind the same `payment_*` function interface, multi-city flights, seat selection or bags if Duffel supports them for the carrier, promo codes, and an agent/sub-agent (B2B) portal.

---

## 8. Definition of done (every phase)
- No PHP warnings or notices with `error_reporting(E_ALL)`.
- Every query uses a prepared statement, and every output is escaped.
- No hard-coded keys or URLs. Everything goes through config.
- Each new external call has an entry in `API_NOTES.md` with a source link.
- The phase's pages work on a 360px-wide screen.
- `TODO.md` is updated.

---

## 9. `TODO.md` template (create this first)

```markdown
# Mickey's Travel and Tour — Build Tracker
Last updated: YYYY-MM-DD HH:MM

## Status
Current phase: 0 — Research & foundation
Demo target: Monday (internal review) → Tuesday (client presentation)

## Client inputs needed
- [ ] Logo / brand assets
- [ ] Office address, phone, WhatsApp number, email
- [ ] Licence numbers (NCAA / IATA / NANTA, if any)
- [ ] Preferred service fee model (default ₦2,000 per booking)
- [ ] Visa destinations offered + requirements per country
- [ ] Tour packages to feature

## Accounts / credentials needed
- [ ] Duffel account (test token) — activation for live
- [ ] Duffel Stays access confirmed
- [ ] Paystack account (test keys) — live activation
- [ ] SMTP credentials
- [ ] Hosting / domain

## Phase 0 — Research & foundation
- [ ] ...
(one section per phase from the prompt, each item a checkbox, each phase ending with its acceptance criteria as checkboxes)

## Decisions
- YYYY-MM-DD — decision — reason

## Open questions / risks
- ...
```

---

Start now: create `TODO.md`, then begin Phase 0 by reading the official Duffel, Paystack, and Tailwind documentation.