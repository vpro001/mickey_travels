-- Demo content for the presentation. Import after schema.sql and airports.sql (safe to import again).
--
-- Visa: real countries, but the requirement lists are a GENERAL GUIDE written for
-- the demo, not copied from any embassy. The agency must replace them with their
-- own checked lists (TODO.md -> Client inputs). Service fees are demo values.
--
-- Tours: fictional packages with demo prices. No reviews, ratings or testimonials.

INSERT INTO visa_destinations (slug, country_name, country_code, visa_types, summary, requirements, processing_time, service_fee_kobo, sort_order) VALUES
('united-kingdom', 'United Kingdom', 'GB', 'tourist,business,visit,student',
 'Standard visitor visa for holidays, visiting family or short business trips.',
 'International passport valid for your whole stay, with a blank page\nRecent passport photograph\nBank statements for the last 6 months\nLetter from your employer or proof of business\nTravel itinerary and accommodation details\nInvitation letter if you are visiting family or friends',
 'Usually about 3 weeks after biometrics', 7500000, 10),
('united-states', 'United States', 'US', 'tourist,business,student',
 'B1/B2 visitor visa. We prepare your DS-160 and get you ready for the interview.',
 'International passport valid for at least 6 months beyond your stay\nDigital photo that meets US requirements\nProof of income and ties to Nigeria\nTravel plan\nPrevious travel history, if any',
 'Depends on interview availability', 8500000, 20),
('canada', 'Canada', 'CA', 'tourist,visit,student',
 'Visitor visa (temporary resident visa) for tourism and family visits.',
 'International passport valid for your whole stay\nDigital photograph\nBank statements for the last 4 to 6 months\nProof of employment or business\nTravel itinerary\nInvitation letter if visiting family',
 'Varies; often several weeks', 8000000, 30),
('united-arab-emirates', 'United Arab Emirates', 'AE', 'tourist,visit',
 'Tourist visa for Dubai and Abu Dhabi, applied for online.',
 'Passport bio-data page (valid at least 6 months)\nRecent passport photograph on a white background\nReturn flight booking\nHotel booking or host address',
 'Usually a few working days', 3500000, 40),
('schengen', 'Schengen area (France, Germany, Spain and more)', 'EU', 'tourist,business,visit',
 'Short-stay visa for the 29 Schengen countries. We help you apply to the right embassy.',
 'International passport issued in the last 10 years, valid 3 months beyond your return\nTwo recent passport photographs\nTravel medical insurance\nBank statements for the last 6 months\nFlight and hotel reservations\nEmployer letter or business documents',
 'Usually about 2 to 3 weeks after the appointment', 7000000, 50),
('south-africa', 'South Africa', 'ZA', 'tourist,business',
 'Visitor visa for holidays and business trips.',
 'International passport valid 30 days beyond your stay, with 2 blank pages\nPassport photographs\nBank statements for the last 3 months\nReturn flight booking\nProof of accommodation',
 'Usually 2 to 4 weeks', 5500000, 60)
ON DUPLICATE KEY UPDATE country_name = VALUES(country_name), summary = VALUES(summary), requirements = VALUES(requirements),
  processing_time = VALUES(processing_time), service_fee_kobo = VALUES(service_fee_kobo), sort_order = VALUES(sort_order),
  visa_types = VALUES(visa_types);

INSERT INTO tour_packages (slug, title, destination, country_code, summary, description, duration_days, duration_nights, price_kobo, price_basis, deposit_pct, itinerary_json, inclusions, exclusions, is_published, is_featured, sort_order) VALUES
('zanzibar-island-escape', 'Zanzibar Island Escape', 'Zanzibar', 'TZ',
 'Six nights between Stone Town''s old streets and the white beaches of the east coast.',
 'A relaxed week in Zanzibar. Start with two nights in Stone Town, walking the old market lanes with a local guide, then move to a beach hotel on the east coast for snorkelling, spice farms and long lazy afternoons.',
 7, 6, 165000000, 'per person sharing', 30,
 '[{"day":1,"title":"Arrive in Zanzibar","body":"Airport pickup and transfer to your Stone Town hotel. Evening at Forodhani Gardens food market."},{"day":2,"title":"Stone Town walk","body":"Guided walk through the old town, the House of Wonders area and the old slave market memorial."},{"day":3,"title":"Spice farm and east coast","body":"Morning spice farm tour, then transfer to the beach hotel."},{"day":4,"title":"Mnemba snorkelling","body":"Boat trip to snorkel off Mnemba Atoll. Lunch included."},{"day":5,"title":"Free day","body":"Beach, pool or an optional trip to Jozani Forest."},{"day":6,"title":"Free day","body":"Your day to enjoy the coast."},{"day":7,"title":"Fly home","body":"Transfer to the airport for your flight back to Lagos."}]',
 'Return flights from Lagos (economy)\n6 nights hotel with breakfast\nAirport transfers\nStone Town guided walk\nSpice farm tour\nMnemba snorkelling trip',
 'Visa fees\nTravel insurance\nLunches and dinners unless stated\nPersonal expenses and tips',
 1, 1, 10),
('dubai-family-break', 'Dubai Family Break', 'Dubai', 'AE',
 'Five nights of desert, theme parks and the city, planned with children in mind.',
 'An easy family holiday in Dubai. A central hotel close to the metro, a desert safari evening, a day at a theme park and plenty of free time for shopping and the beach.',
 6, 5, 125000000, 'per person sharing', 30,
 '[{"day":1,"title":"Arrive in Dubai","body":"Airport pickup and hotel check-in."},{"day":2,"title":"City tour","body":"Half-day tour of old Dubai, the creek and the souks."},{"day":3,"title":"Theme park day","body":"Full day at a theme park (tickets included)."},{"day":4,"title":"Desert safari","body":"Afternoon dune drive and evening camp dinner."},{"day":5,"title":"Free day","body":"Shopping, the beach or the aquarium."},{"day":6,"title":"Fly home","body":"Transfer to the airport."}]',
 'Return flights from Lagos (economy)\n5 nights hotel with breakfast\nAirport transfers\nCity tour\nTheme park tickets\nDesert safari with dinner\nUAE visa processing',
 'Travel insurance\nLunches and most dinners\nPersonal expenses',
 1, 1, 20),
('obudu-mountain-weekend', 'Obudu Mountain Weekend', 'Obudu, Cross River', 'NG',
 'Three nights in the cool hills of the Obudu plateau, with the cable car and canopy walk.',
 'Get out of the heat for a long weekend on the Obudu plateau. Fly to Calabar, take the scenic road up to the resort, ride the cable car and walk the canopy trail.',
 4, 3, 38500000, 'per person sharing', 50,
 '[{"day":1,"title":"Lagos to Calabar to Obudu","body":"Morning flight to Calabar, then road transfer to the resort."},{"day":2,"title":"Cable car and canopy walk","body":"Ride the cable car and walk the canopy walkway with a guide."},{"day":3,"title":"Grotto and natural pool","body":"Hike to the grotto waterfall and natural swimming pool."},{"day":4,"title":"Back to Lagos","body":"Road transfer to Calabar for your flight home."}]',
 'Return flights Lagos to Calabar\nRoad transfers\n3 nights resort accommodation with breakfast\nCable car ride\nGuided canopy walk',
 'Lunches and dinners\nPersonal expenses',
 1, 1, 30),
('cape-town-explorer', 'Cape Town Explorer', 'Cape Town', 'ZA',
 'Seven nights of mountains, coastline and the Winelands.',
 'Table Mountain, the Cape Peninsula and the Winelands in one well-paced week, with a driver-guide for the big days and free time to explore the city on your own.',
 8, 7, 215000000, 'per person sharing', 30,
 '[{"day":1,"title":"Arrive in Cape Town","body":"Airport pickup and hotel check-in."},{"day":2,"title":"Table Mountain and city","body":"Cable car up Table Mountain, then the Bo-Kaap and the waterfront."},{"day":3,"title":"Cape Peninsula","body":"Full-day drive to Cape Point and Boulders Beach penguins."},{"day":4,"title":"Winelands","body":"Day trip to Stellenbosch and Franschhoek."},{"day":5,"title":"Robben Island","body":"Morning ferry to Robben Island."},{"day":6,"title":"Free day","body":"Your day in the city."},{"day":7,"title":"Free day","body":"Optional shark-cage diving or whale watching in season."},{"day":8,"title":"Fly home","body":"Transfer to the airport."}]',
 'Return flights from Lagos (economy)\n7 nights hotel with breakfast\nAirport transfers\nThree guided day tours\nTable Mountain cable car\nRobben Island ferry',
 'Visa fees\nTravel insurance\nMost meals\nPersonal expenses',
 1, 0, 40)
ON DUPLICATE KEY UPDATE title = VALUES(title), destination = VALUES(destination), summary = VALUES(summary),
  description = VALUES(description), duration_days = VALUES(duration_days), duration_nights = VALUES(duration_nights),
  price_kobo = VALUES(price_kobo), price_basis = VALUES(price_basis), deposit_pct = VALUES(deposit_pct),
  itinerary_json = VALUES(itinerary_json), inclusions = VALUES(inclusions), exclusions = VALUES(exclusions),
  is_published = VALUES(is_published), is_featured = VALUES(is_featured), sort_order = VALUES(sort_order);

-- Departure dates: rebuilt each time relative to today, so the demo always has future dates.
DELETE d FROM tour_departures d
  JOIN tour_packages p ON p.id = d.package_id
 WHERE p.slug IN ('zanzibar-island-escape','dubai-family-break','obudu-mountain-weekend','cape-town-explorer')
   AND d.seats_booked = 0;

INSERT INTO tour_departures (package_id, start_date, end_date, seats_total)
SELECT p.id, DATE_ADD(CURDATE(), INTERVAL o.days DAY), DATE_ADD(CURDATE(), INTERVAL o.days + p.duration_days - 1 DAY), 16
  FROM tour_packages p
  JOIN (SELECT 30 AS days UNION ALL SELECT 58 UNION ALL SELECT 93) o
 WHERE p.slug IN ('zanzibar-island-escape','dubai-family-break','obudu-mountain-weekend','cape-town-explorer');

-- Tour photos (files in assets/img/, credits in docs/IMAGE_CREDITS.md)
UPDATE tour_packages SET cover_image = 'zanzibar' WHERE slug = 'zanzibar-island-escape';
UPDATE tour_packages SET cover_image = 'dubai-desert' WHERE slug = 'dubai-family-break';
UPDATE tour_packages SET cover_image = 'obudu' WHERE slug = 'obudu-mountain-weekend';
UPDATE tour_packages SET cover_image = 'cape-town' WHERE slug = 'cape-town-explorer';

-- Exchange rates (naira per unit). DEMO VALUES ONLY: staff must set today's real rates
-- in the admin before taking real payments.
INSERT INTO fx_rates (currency, rate_to_ngn) VALUES ('USD', 1550.00), ('GBP', 2050.00), ('EUR', 1720.00)
ON DUPLICATE KEY UPDATE rate_to_ngn = VALUES(rate_to_ngn);
