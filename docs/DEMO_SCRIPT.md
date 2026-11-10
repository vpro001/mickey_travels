# Demo script: Mickey Travels & Tours (about 7 minutes)

For the client presentation. Follow it top to bottom; every click is written out.

---

## Before the meeting (15 minutes, the day before)

1. **Fresh install** (skip if the site is already running):
   - Copy the project folder to the server (local: `htdocs/mickey`).
   - In phpMyAdmin, create a database `mickey` (collation `utf8mb4_unicode_ci`) and import, in this order: `database/schema.sql`, `database/airports.sql`, `database/demo-content.sql`.
   - Copy `config.sample.php` to `config.php` and fill in the database login and `SITE_URL`.
   - Make sure the web server can write to `storage/` and `assets/img/tours/`.
2. **Choose the flight mode** in `config.php`:
   - `define('FLIGHT_DATA', 'demo');`: sample flights for any route, with a yellow "Demo data" banner. Always works, with no Duffel account needed.
   - `define('FLIGHT_DATA', 'live');` + `DUFFEL_TOKEN` = a `duffel_test_…` token: real Duffel test mode. Test flights come from the pretend airline **Duffel Airways (ZZ)**. Duffel's docs use **London Heathrow (LHR) → New York JFK** as their example route; search that first and note here which routes return results: `{{TEST_ROUTES_CONFIRMED}}`.
3. **Paystack test keys** in `config.php`: `PAYSTACK_SECRET_KEY` = `sk_test_…`, `PAYSTACK_PUBLIC_KEY` = `pk_test_…`. In the Paystack dashboard set the webhook URL to `<SITE_URL>/webhooks/paystack.php` (it must be reachable from the internet).
4. **Test card details**, copied by hand from https://paystack.com/docs/payments/test-payments/ (we can't copy them automatically, and must not guess them):
   - Successful card: `{{PAYSTACK_TEST_CARD_SUCCESS}}` · CVV `{{CVV}}` · expiry `{{EXPIRY}}` · PIN/OTP `{{PIN_OTP}}`
   - Declined card (optional): `{{PAYSTACK_TEST_CARD_DECLINED}}`
5. **Admin account**: open `<SITE_URL>/admin/`. The first visit asks you to create the admin account. Write the login down somewhere safe.
6. **Admin → Fees, rates & contact**: check the exchange rates (the demo values are made up) and fill in the agency's real phone, WhatsApp, email and address if you have them. Empty fields show as `{{CLIENT_…}}` placeholders.
7. **Dry run**: do the whole script once. Then delete the test booking's email from the inbox so the real run looks fresh.

Open these tabs before you start: the homepage, `admin/` (logged in), and the email inbox (or `storage/logs/mail-<date>.log` if `MAIL_METHOD` is `log`).

---

## The script

### 1. First impression (1 minute): homepage

- "This is the homepage. The search box is the first thing customers see, designed like a boarding pass."
- Point at the **departures board** below it as it flips into place: "Popular routes from Lagos, each one opens today's fares."
- Scroll slowly: destinations, **visa** (with the stamp), **holidays**.
- "Everything is in Naira. Payments go through Paystack: card, bank transfer or USSD."

### 2. Search flights (1½ minutes)

- In the search box tap the **"Lagos → London"** quick pick (live test mode: type the confirmed test route instead).
- Keep **Return**, 1 adult, Economy. Press **Search flights**.
- "Results load in the background. The grey cards show it's working."
- Tap **Direct only** in Filters, then **Any number of stops** again. Tap **Fastest**, then **Cheapest**.
- On the first result press **Flight details**: "each flight, the stop-over time, baggage, refund rules, and the price breakdown. The agency's ₦2,000 fee is already included, so the price you see is the price you pay."
- Point at **"Prices held for 29:xx"**: "airlines hold a price for about 30 minutes; we show that honestly."

### 3. Book and pay (2 minutes)

- Press **Select** on a flight.
- "We check the fare with the airline again before anything else."
- Fill in Traveller 1: Mr / `Ada` / `Obi` / date of birth `1990-05-01` / Male (+ passport details if shown: `A12345678`, Nigeria, an expiry date a few years ahead).
- Email: an inbox you can open during the demo. Phone: `08031234567`.
- Press **Continue to payment** → Paystack's test checkout opens.
- Pay with the **successful test card** from step 4 above.
- You return to the booking page: **"You're booked"**, with the **airline reference (PNR)** and **e-ticket numbers**.
- Press **Print itinerary** to show the clean printout. Open the inbox to show the confirmation email.

### 3b. Hotels (optional, 1 minute)

- Menu → **Hotels**. In "Where are you staying?" type `Dubai` and pick it from the list; keep the dates. Press **Search hotels**.
- Filter **4 ★**, sort by **Lowest price**, open a hotel with **See rooms**.
- "Every room shows its meals and its cancellation terms; the green ones can be cancelled for free until the date shown."
- **Choose** a rate: the price is confirmed with the hotel again before paying. (In demo mode the hotels are fictional and marked "(demo)". With a Duffel test token, use the "Show Duffel's test hotels" link on the Hotels page instead.)

### 4. Visa (1 minute)

- Menu → **Visa** → **United Kingdom** → "the documents you'll usually need, our fee, processing time".
- Press **Start your application**. Fill name, email, phone, a travel date, and upload any PDF or photo for the two required documents. Press **Send application**.
- "The customer gets a reference and can track every step."
- Switch to the **admin tab** → **Visa applications** → open it → change status to **Documents needed from you** with the note "Please send your bank statement" → **Update and email the customer**.
- Back on the customer's tracking page, refresh: the request and an upload box appear.

### 5. Holidays (30 seconds)

- Menu → **Tours** → **Zanzibar Island Escape**: day-by-day plan, what's included, dates with places left.
- Choose 2 travellers and **30% deposit**: "the deposit holds the places; the balance can be paid later from the booking page."

### 6. The back office (1 minute)

- **Dashboard**: payments today and this month, fees earned, visa applications waiting, enquiries.
- **Flight bookings** → open the booking you just made: "every naira is traced: the airline's price and currency, the exchange rate used, our fee, the Paystack payment, and the full history."
- **Fees, rates & contact**: "the service fee can be per booking, per traveller or a percentage. Change it here and new searches use it straight away." (Don't save a change during the demo unless you mean to.)
- Close: "If anything ever goes wrong after a customer pays, for example the airline changes the price, the booking is flagged here and the office gets an email. Money is never lost silently."

---

## If something goes wrong during the demo

| What you see | What to do |
|---|---|
| "Flight search isn't switched on yet" | `DUFFEL_TOKEN` is empty. Set `FLIGHT_DATA` to `'demo'` in `config.php` and refresh. |
| No flights found (live test mode) | Use a confirmed test route from step 2, or switch to `'demo'`. |
| "Online payment isn't switched on yet" | Paystack keys are missing from `config.php`. |
| Paystack page shows an error | Check you used the **test** keys (`sk_test_`), not live ones. |
| Stuck on "Issuing your ticket" | It refreshes by itself every few seconds. If it says "Our team is finishing your booking", open the booking in the admin to see why. |
| "This fare has expired" | Normal after about 30 minutes. Search again. |
| No email arrives | With `MAIL_METHOD = 'log'`, emails are saved in `storage/logs/mail-<date>.log` instead of being sent. |
