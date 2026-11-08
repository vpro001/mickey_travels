# Benchmark — Wakanow & Travelbeta (functional study)

Read 2026-11-07 from the public pages (static HTML only — results, checkout and logged-in areas are JS-rendered or behind a search, so they were **not observed**). This covers function and flow only. Nothing here is a design reference; do not copy branding, layout or copy.

## Information architecture

| | Wakanow | Travelbeta |
|---|---|---|
| Primary products | Flights, Hotels, Packages, Tours, Visa | Flights, Hotels, Shortlets, Car rental, Visa, Packages |
| Secondary | Manage Booking, membership tier, app, travel card, corporate site, affiliates | Manage Bookings, Deals, Affiliates, Login/Create account |
| Search module | Tab strip of products on every product page | Tab strip on homepage hero |

**Takeaway for Mickey's:** four tabs (Flights, Hotels, Visa, Tours) plus a "Manage booking" entry point (reference + email lookup) in the header. Keep the product set small and do each one well.

## Flight search form

Both sites:
- Trip type: Round trip / One way / Multi-city. Wakanow defaults to round trip.
- Cabin: Economy, Premium Economy, Business, First. Default Economy.
- Travellers: Adults, Children (2–11), Infants (under 2). Default 1 adult.
- From / To autocomplete. Travelbeta shows "Recent places" and "Cities" groups.
- Wakanow's results URL is shareable and fully parameterised (`origin`, `destination`, `departureDate`, `returnDate`, `tripType`, `cabin`, `adults`, `children`, `infants`).
- Travelbeta pre-fills both dates with today, so return = departure by default. That is a weak default.

**Takeaway:** GET-based, shareable results URLs. Default departure to roughly 2 weeks ahead and return to 1 week after that. Show "City — Airport name (IATA)" in autocomplete, grouped by city. Keep recent searches in `localStorage`.

## Results & booking (not observable)

Neither site's results or checkout rendered in static HTML. Wakanow's marketing copy mentions cards, bank transfer and instalments ("pay small small"). Travelbeta shows payment-partner logos. We build to the standard set by the prompt: dense cards, filters for stops, airline, departure time and price, a sort bar, skeleton loaders and an offer-detail drawer.

## Visa

- **Wakanow:** eligibility check first (passport country, destination, number of travellers), then "pay & apply", then tracking. Splits eVisa, sticker visa and paid consultation.
- **Travelbeta:** one long assistance form (traveller info, travel dates, destination, passport country, passport number, passport upload, message) that goes to an agent. There is no payment step, and a "Booking confirmation" modal appears after submitting.

**Takeaway:** destination pages with requirements → application form with document upload → reference number → public tracking by reference and email. The status history is visible to the customer, which neither site shows publicly.

## Tours / packages

- **Travelbeta:** card grid with title, image and price, with explicit price basis ("per couple", "person sharing", "1 or 2 adults"). A "customise your own package" enquiry form includes a budget field.
- **Wakanow:** a package builder (flight + hotel); no listings were visible.

**Takeaway:** state the price basis on every card (per person sharing, etc.), offer a fixed-departure date list, and allow a deposit option plus an enquiry for custom trips.

## Trust signals observed

- Physical office addresses with branch phone numbers.
- WhatsApp click-to-chat links (Travelbeta).
- Payment-provider and IATA logos. **Neither site showed a licence number.**
- Self-claimed counters ("2M+ travellers", "95% approval rate"). **We will not do this** (prompt §3).

**Takeaway:** a real address, a WhatsApp button, Paystack badge, licence numbers once the client supplies them, a clear refund/cancellation policy page, and the staff contact for visa.

## Gaps we can beat

1. A return date that defaults to the departure date (Travelbeta).
2. Inconsistent phone numbers across pages (Travelbeta). We use one `settings` source for all contact details.
3. Visa status is not publicly trackable (Travelbeta). We provide a tracking page.
4. Copy mismatch: the visa form shows "flight booking terms" (Travelbeta). Each product gets its own terms.
