# API Notes — verified against official sources

Every external call the code makes must have an entry here. Status key: **✅ confirmed** (read in an official source), **⚠️ unverified** (could not read the source; do not rely on it until confirmed).

---

## Duffel (flights + stays)

Read 2026-11-07. Sources:
- https://duffel.com/docs/api/overview/making-requests
- https://duffel.com/docs/api/overview/versioning
- https://duffel.com/docs/api/overview/errors (includes rate limits)
- https://duffel.com/docs/api/overview/pagination
- https://duffel.com/docs/api/overview/test-mode
- https://duffel.com/docs/api/v2/offer-requests/create-offer-request
- https://duffel.com/docs/api/v2/offers/get-offer-by-id
- https://duffel.com/docs/guides/getting-started-with-flights
- https://duffel.com/docs/api/v2/places/get-place-suggestions
- https://duffel.com/docs/guides/receiving-webhooks
- Official client types (for the create-order body, which the docs page did not render for us): https://github.com/duffelhq/duffel-api-javascript — `src/booking/Orders/OrdersTypes.ts`, `src/booking/Offers/OfferTypes.ts`, `src/types/shared.ts` (main branch, read 2026-11-07)

### Common ✅
- Base URL: `https://api.duffel.com`
- Headers on every request:
  - `Authorization: Bearer <token>`
  - `Accept: application/json` (required)
  - `Accept-Encoding: gzip` (recommended; we decompress via cURL `CURLOPT_ENCODING`)
  - `Duffel-Version: v2` — **v2 is current.** v1 was available only until 23-01-2025, so v1 examples online are dead.
  - `Content-Type: application/json` on requests with a body
- Request bodies are wrapped in `{"data": {...}}`. Responses are `{"data": ..., "meta": ...}`.
- Response headers include `x-request-id`. We log it on every call.
- Test tokens are prefixed `duffel_test_`. Test resources have `live_mode: false`.

### Errors ✅
```json
{"errors":[{"type":"validation_error","title":"...","message":"...","code":"validation_required",
  "documentation_url":"...","source":{"field":"...","pointer":"..."}}],
 "meta":{"request_id":"...","status":422}}
```
- `type`: `authentication_error`, `airline_error`, `invalid_state_error`, `rate_limit_error`, `validation_error`, `invalid_request_error`, `api_error`.
- **Do not retry 202, 500 or 502** (the docs say so explicitly, because a booking may have been made). Retry 503/504 later.
  - ⚠️ **Conflict with prompt §2:** the prompt says to "retry on 429/5xx". The docs win. `http_request()` retries only 429, 503 and 504, and only for idempotent GETs or calls passing `'retry' => true`. POST `/air/orders` and `/stays/bookings` are **never** retried automatically.
- Order-creation codes we handle: `offer_no_longer_available`, `price_changed`, `offer_expired`, `insufficient_balance`, `payment_amount_does_not_match_order_amount`, `payment_currency_does_not_match_order_currency`, `order_not_created`, `duplicate_booking`, `duplicate_passenger_names`, `invalid_passenger_title`, `invalid_passenger_name`, `invalid_phone_number`, `invalid_email_address`, `order_creation_already_attempted`, `offer_request_already_booked`, `airline_internal`, `airline_unknown`.

### Rate limits ✅
- Status 429, `type: rate_limit_error`, `code: rate_limit_exceeded`.
- Headers: `ratelimit-limit` (e.g. 60), `ratelimit-remaining`, `ratelimit-reset` (an HTTP date). The period is currently 60 s and "subject to change". No `Retry-After` is documented, so we back off until `ratelimit-reset`, capped.
- Timeouts: airline calls can take up to 120 s. The docs recommend a client timeout of **≥130 s** for order and booking creation.

### Places — airport autocomplete ✅
`GET /places/suggestions?query=<text>` (optional `lat`, `lng`, `rad` in metres). Note: no `/air` prefix. The `name` param is deprecated; use `query`.
Response `data[]`: `type` (`airport` | `city`), `id` (e.g. `arp_lhr_gb`), `iata_code`, `name`, `city_name`, `iata_country_code`, `iata_city_code`, `latitude`, `longitude`, `time_zone`, `icao_code`, `city` (airports), `airports[]` (cities).
Use: autocomplete fallback when the local `airports` table has no match; also returns lat/lng we can use for Stays.

### Offer request ✅
`POST /air/offer_requests?return_offers=true&supplier_timeout=20000`
- `supplier_timeout` is in ms; the valid range is 2000–60000 and the default is 20000.
```json
{"data":{
  "slices":[{"origin":"LOS","destination":"LHR","departure_date":"2026-12-01"}],
  "passengers":[{"type":"adult"},{"age":8},{"age":1}],
  "cabin_class":"economy",
  "max_connections":1}}
```
- Passenger: give `type` **or** `age`, never both. We send `type:"adult"` for adults and `age` for children and infants.
- `cabin_class`: `first` | `business` | `premium_economy` | `economy`.
- `max_connections` defaults to 1. Set 0 for direct only. We send 2 so results aren't artificially thin, and filter client-side.
- Response `data.id` (`orq_…`), `data.offers[]`.

**Offer fields we use:** `id`, `total_amount` (string), `total_currency`, `base_amount`, `tax_amount`, `expires_at`, `owner{name,iata_code,logo_symbol_url,logo_lockup_url}`, `passengers[]{id,type,age}`, `passenger_identity_documents_required` (bool), `payment_requirements{requires_instant_payment,payment_required_by,price_guarantee_expires_at}`, `conditions{refund_before_departure,change_before_departure}` (each `{allowed,penalty_amount,penalty_currency}` or null), `slices[]{origin,destination,duration (ISO-8601, e.g. PT02H26M),fare_brand_name,segments[]}`.
**Segment fields:** `departing_at`, `arriving_at` (local time, no offset), `origin{iata_code,name,city_name}`, `destination{…}`, `origin_terminal`, `destination_terminal`, `duration`, `marketing_carrier{name,iata_code}`, `marketing_carrier_flight_number`, `operating_carrier{name,iata_code}`, `operating_carrier_flight_number`, `aircraft{name}`, `stops[]`, `passengers[]{passenger_id,cabin_class_marketing_name,baggages[]{type:"checked"|"carry_on",quantity}}`.
- The docs ask integrators to show `operating_carrier.name` prominently.

### List offers ✅ (via official client types)
`GET /air/offers?offer_request_id=orq_…&sort=total_amount|total_duration&max_connections=N&limit=…&after=…`
Pagination: `limit` default 50 (1–200); cursors `after`/`before` in `meta`.

### Get single offer (re-price) ✅
`GET /air/offers/{id}?return_available_services=false`
- Quoted from the docs: this endpoint "does not guarantee that the offer will be available at the time of booking"; "You should always use data (e.g prices, service IDs) from the latest call to this endpoint when booking an offer."
- Offers typically expire within ~30 minutes. Check `expires_at`.
- Possible errors: `offer_expired`, `offer_no_longer_available`, `price_changed`.

### Create order ✅
`POST /air/orders` (timeout ≥130 s, **never auto-retry**)
```json
{"data":{
  "type":"instant",
  "selected_offers":["off_…"],
  "payments":[{"type":"balance","currency":"<offer total_currency>","amount":"<offer total_amount>"}],
  "passengers":[{
    "id":"pas_… (offer passenger id)",
    "title":"mr|ms|mrs|miss|dr",
    "gender":"m|f",
    "given_name":"…","family_name":"…",
    "born_on":"YYYY-MM-DD",
    "email":"…","phone_number":"+234…",
    "infant_passenger_id":"pas_… (on the responsible adult only)",
    "identity_documents":[{"type":"passport","unique_identifier":"…","issuing_country_code":"NG","expires_on":"YYYY-MM-DD"}]
  }],
  "metadata":{"booking_ref":"MTT-…"}}}
```
- `selected_offers` must contain exactly one offer. Only one payment is supported.
- `payments.type`: `balance` (Managed Content, which is our case) or `arc_bsp_cash` (IATA agents). The amount and currency **must equal** the latest offer's `total_amount`/`total_currency`.
- `identity_documents` is required only when the offer has `passenger_identity_documents_required: true`.
- Each infant needs a distinct responsible adult.
- ⚠️ **Conflict:** the docs describe order `type` as `instant` | `hold`, while the official client types say `instant` | `pay_later`. We only use `instant`, so this doesn't affect us. Recorded for Phase 8.
- Response: `id` (`ord_…`), `booking_reference` (PNR), `booking_references[]`, `documents[]{type:"electronic_ticket",unique_identifier,passenger_ids}`, `total_amount`, `total_currency`, `passengers`, `slices`, `available_actions`, `payment_status`, `live_mode`.
- Status 201 = created. 202 = "do not retry" (the outcome arrives by webhook: `order.created` / `order.creation_failed`).

### How the site uses orders (Phase 3)
- `issue_ticket()` in `includes/booking.php` re-fetches `GET /air/offers/{id}` right before ordering, then sends `POST /air/orders` with `type: instant`, `payments: [{type: balance, currency, amount}]` using the **latest** offer amount (it must match exactly, per the docs).
- Timeout 130 s (docs: airline calls can take up to 120 s). No automatic retry. Error codes `connection_failed`, `unknown`, `airline_unknown` and `airline_internal` are treated as "outcome unclear: check the Duffel dashboard before retrying".
- A 2xx answer without `booking_reference` is treated as unclear too (a 202 would mean the order is still being made).
- ⚠️ Not yet confirmed against the live test API: the age rules for `adult` (we allow 12+), and whether a child's real date of birth must match the `age: 10` sent in the search.

### Test mode ✅
- Test airline **Duffel Airways**, IATA `ZZ` (shown as the offer `owner`). The docs say it is "much more reliable" but schedules and prices aren't realistic. It supports orders, services, seats, cancel and change.
- The documented example search is LHR→JFK, economy, 1 adult. For the demo we will use LHR→JFK and also try LOS→LHR, and record what test mode actually returns in `DEMO_SCRIPT.md`.
- No "magic" error-trigger routes are documented for flights.
- ⚠️ The docs don't explicitly say that `balance` payment works in test mode (the test balance is topped up in the dashboard). **Verify with the first real test order.**

### Webhooks ✅
- Header `X-Duffel-Signature: t=<timestamp>,v1=<hex>`.
- Signature = lowercase hex `HMAC-SHA256(secret, "<t>.<raw_body>")`. Compare in constant time.
- The secret is returned **once**, at `POST /air/webhooks` creation.
- Deduplicate on the event `idempotency_key` (we also store the event `id`, `wev_…`).
- Events: `order.created`, `order.creation_failed`, `order.updated`, `order.airline_initiated_change_detected`, `ping.triggered`, `stays.booking.created`, `stays.booking_creation_failed`.

### Stays ✅
Sources: https://duffel.com/docs/guides/getting-started-with-stays, https://duffel.com/docs/api/v2/search/stays-search, https://duffel.com/docs/api/v2/search-result/fetch-all-rates, https://duffel.com/docs/api/v2/quotes/create-quote, https://duffel.com/docs/api/v2/bookings/create-booking, https://duffel.com/docs/guides/test-hotels, https://duffel.com/docs/guides/searching-for-stays
- **Access is gated.** You must request access via https://duffel.com/contact-us (it is not self-serve). → TODO item.
- Flow: `POST /stays/search` → `POST /stays/search_results/{srr_id}/actions/fetch_all_rates` → `POST /stays/quotes {rate_id}` → `POST /stays/bookings`.
- Search body: `check_in_date`, `check_out_date` (≤330 days ahead, ≤99 nights), `rooms` (int), `guests[]` (`{type:"adult"}` / `{type:"child",age}`), and `location{radius (km), geographic_coordinates{latitude,longitude}}` **or** `accommodation{ids[],fetch_rates}`.
- Search result: `id` (`srr_…`), `expires_at`, `cheapest_rate_total_amount`, `cheapest_rate_currency`, `accommodation{id,name,rating,review_score,photos[].url,location{address,geographic_coordinates},amenities}`.
- Rates: `rooms[].rates[]{id (rat_…),total_amount,total_currency,board_type,cancellation_timeline[]{refund_amount,currency,before},payment_type,available_payment_methods,quantity_available,expires_at}`.
- Quote: `id` (`quo_…`), `total_amount`, `total_currency`, `due_at_accommodation_amount`, `deposit_amount`.
- Booking body: `quote_id`, `guests[]{given_name,family_name,born_on?}`, `email`, `phone_number` (E.164), optional `accommodation_special_requests`, `metadata`. **Omit `payment` to pay with balance.**
- Booking response: `id` (`bok_…`), `reference` (hotel confirmation), `status` (`confirmed`|`cancelled`), `confirmed_at`.
- On a 500 during booking: don't retry; contact support with the request ID.
- **Test hotels:** search at `latitude -24.38, longitude -128.32`, radius 2 (test mode only). Scenario rates include "Successful Booking with Balance" and "Rate Unavailable at Booking".
- **Geocoding:** Duffel provides none for Stays. Its docs suggest a third-party geocoder (they mention Mapbox). Our plan for Phase 5: use the `latitude`/`longitude` from `/places/suggestions` for city or airport places (✅ fields confirmed above), plus a curated `destinations` table for popular cities. No third-party key is needed.

### How the site uses Stays (Phase 5)
- **Finding a city:** the search box gives an airport code; we search a **25 km** radius around that airport's coordinates (from `database/airports.sql`, OurAirports, or Duffel `/places/suggestions` lat/lng). That's our documented geocoding method; no third-party geocoder is needed. `near=TEST` searches Duffel's test-hotel coordinates (−24.38, −128.32, radius 2), and only with a `duffel_test_` token.
- **Guests:** adults as `{type: adult}`, children as `{type: child, age: 8}` (the search asks for a count, not ages).
- **Flow:** `stays_search()` → `stays_rates()` (fetch_all_rates) → `stays_quote()` on page load and again on submit (price-change check) → booking saved + Paystack → after verified payment, `book_hotel()` sends `POST /stays/bookings` with `quote_id`, `guests` (one name per room), `email`, `phone_number`, optional `accommodation_special_requests`, and **no `payment`** (balance, per the docs). Never retried; on failure → `needs_attention` + admin alert.
- ⚠️ Not yet confirmed against the live API (Stays access is still pending): whether a rate's `total_amount` covers **all** requested rooms (we assume yes, since the search sends `rooms`), the exact `accommodation.location.address` field names (we read `line_one` and `city_name` and show nothing if missing), and whether `guests` needs one entry per room or per person (we send one per room).

---

## Paystack

Read 2026-11-07.
⚠️ **paystack.com/docs returns HTTP 403 to automated fetches.** I did not try to get around the block. The facts below come from Paystack's **official** GitHub sources instead:
- OpenAPI spec: https://github.com/PaystackOSS/openapi → `dist/paystack.yaml` (main, last pushed 2026-06-09)
- Official WooCommerce plugin (webhook verification code): https://github.com/PaystackOSS/woo-paystack → `includes/class-wc-gateway-paystack.php`

### Common ✅
- Base URL `https://api.paystack.co`. Auth `Authorization: Bearer <secret_key>`. JSON bodies.
- Error shape: `{"status":false,"message":"…","meta":{"nextStep":"…"},"type":"validation_error|processor_error|api_error","code":"…"}`. Example: `code: duplicate_reference`, `message: "Duplicate Transaction Reference"`.

### Initialize ✅
`POST /transaction/initialize`
- Required: `email`, `amount` (integer, **smallest currency unit**, i.e. kobo for NGN).
- Optional, as we use them: `currency` (`NGN`), `reference` (unique; **only `-`, `.`, `=` and alphanumerics allowed**), `callback_url`, `metadata` (JSON object), `channels[]` (`card`, `bank`, `bank_transfer`, `ussd`, `qr`, `mobile_money`, …).
- Response: `{status:true, message, data:{authorization_url, access_code, reference}}`. Redirect the customer to `authorization_url`.

### Verify ✅
`GET /transaction/verify/{reference}`
- Response `data`: `id`, `status`, `reference`, `amount` (kobo), `currency`, `paid_at`, `channel`, `gateway_response`, `metadata`, `fees`, `authorization{last4,card_type,bank,…}`, `customer`.
- We fulfil only when `data.status === "success"` **and** `data.amount === payments.amount_kobo` **and** `data.currency === payments.currency`.
- ⚠️ The full `data.status` enum isn't listed in the spec (`success`, `failed`, `abandoned` and `ongoing` appear in Paystack's public material but are unconfirmed). We treat **anything other than `success`** as not paid, and `failed`/`abandoned` as terminal failures.

### Webhook ✅ (from official plugin code)
- Header `x-paystack-signature` (PHP: `$_SERVER['HTTP_X_PAYSTACK_SIGNATURE']`).
- Signature = `hash_hmac('sha512', <raw php://input body>, <secret_key>)`. The plugin uses `!==`; we use `hash_equals()` to compare in constant time.
- Body is JSON: `{"event":"charge.success","data":{…same shape as verify data…}}`.
- Handling: return 200 quickly, then verify by API (we never trust the webhook body for amounts).
- ⚠️ **Webhook source IP list: unverified** (the docs page is blocked). The IP allow-list is **off by default** (`paystack_webhook_ips` empty in config). The signature check is the real control.
- ⚠️ Retry cadence for undelivered webhooks: unverified. Our idempotency doesn't depend on it.

### How the site uses Paystack (Phase 3)
- Reference: `MTF-XXXXXXXX-xxxxxx` (only letters, numbers and `-`, as the docs require), saved in `payments` **before** redirecting.
- `callback_url`: `SITE_URL/payment-callback.php`. Paystack adds `?reference=…&trxref=…`.
- Webhook URL to set in the dashboard: `SITE_URL/webhooks/paystack.php`.
- Fulfilment needs verify `status = success` AND `amount` = our `amount_kobo` AND `currency` = NGN.
- Tested locally with a dummy key: a real `POST /transaction/initialize` returned 401 `{"status":false,"message":"Invalid key","meta":{"nextStep":…},"type":"validation_error"…}`, which matches the documented error shape.

### Test cards ⚠️ UNVERIFIED
The test-payments page (https://paystack.com/docs/payments/test-payments/) is blocked to automated reads. **Per the prompt, test cards must not come from memory.** Before the demo, someone must open that page in a browser and copy the card numbers into `docs/DEMO_SCRIPT.md`. → Open item in TODO.md.

---

## Tailwind CSS ✅ — via CDN (browser build)

Read 2026-11-07. Sources: https://tailwindcss.com/docs/installation/tailwind-cli, https://tailwindcss.com/docs/theme, https://tailwindcss.com/docs/font-family, https://tailwindcss.com/docs/font-variant-numeric, jsDelivr package API for `@tailwindcss/browser`.

- **Decision (user, 2026-11-07): use the Tailwind CDN, no build step.** This replaces prompt §2 (standalone CLI).
- Script: `https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4.3.5/dist/index.global.js`. It is pinned to an exact version; 4.3.5 was the latest on 2026-11-07 (~74 KB brotli, 287 KB raw).
- v4 is CSS-first, with no `tailwind.config.js`. The theme goes in `<style type="text/tailwindcss">` in `includes/header.php` using `@theme { --color-*; --font-* }`, which generates `bg-*`, `text-*` and `font-*` utilities.
- Gotcha (hit and fixed): in v4 you cannot `@apply` your own component class inside another one (e.g. `.btn-primary { @apply btn … }`), and it silently breaks the whole stylesheet. Use `class="btn btn-primary"` instead.
- Fonts are plain `@font-face` rules in `public/assets/css/fonts.css`; `tabular-nums` / `.num` handle prices.
