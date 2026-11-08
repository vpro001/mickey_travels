<?php
// THE place where customer prices are worked out. Every price on the site,
// on the results page, the booking page and in Paystack, comes from price_in_naira().
//
//   1. Convert the airline's price to Naira with the exchange rate set in the admin (fx_rates table)
//   2. Add the exchange-rate buffer (settings: fx_buffer_pct), to cover the rate moving before we pay the airline
//   3. Add our service fee (settings: markup_type + markup_value)
//
// All amounts come back in kobo (₦1 = 100 kobo). The whole array is saved with
// each booking, so we can always show how a price was made up.

function price_in_naira($amount, $currency, $passengers, $markup_type = null, $markup_value = null)
{
    $rate = exchange_rate($currency);
    if ($rate === null) {
        write_log('pricing', "No exchange rate for $currency. Add it in the admin.");
        return null;
    }

    $buffer = $currency == 'NGN' ? 0 : (float) setting('fx_buffer_pct', 0);
    $base_kobo = (int) round($amount * $rate * (1 + $buffer / 100) * 100);

    if ($markup_type === null) $markup_type = setting('markup_type', 'flat_booking');
    if ($markup_value === null) $markup_value = (float) setting('markup_value', 2000);

    if ($markup_type == 'percent') {
        $markup_kobo = (int) round($base_kobo * $markup_value / 100);
    } elseif ($markup_type == 'flat_passenger') {
        $markup_kobo = (int) round($markup_value * 100) * $passengers;
    } else {
        $markup_kobo = (int) round($markup_value * 100);   // flat_booking
    }

    return [
        'supplier_amount'   => $amount,            // what the airline charges, e.g. "512.40"
        'supplier_currency' => $currency,          // e.g. "USD"
        'fx_rate'           => $rate,              // naira per 1 unit of that currency
        'fx_buffer_pct'     => $buffer,
        'base_kobo'         => $base_kobo,         // airline price in naira (with buffer)
        'markup_type'       => $markup_type,
        'markup_value'      => $markup_value,
        'markup_kobo'       => $markup_kobo,       // our service fee
        'total_kobo'        => $base_kobo + $markup_kobo,   // what the customer pays
    ];
}

// Naira for one unit of $currency, from the fx_rates table. NGN is always 1.
function exchange_rate($currency)
{
    if ($currency == 'NGN') {
        return 1.0;
    }
    static $rates = null;
    if ($rates === null) {
        $rates = [];
        foreach (db_all("SELECT currency, rate_to_ngn FROM fx_rates") as $row) {
            $rates[$row['currency']] = (float) $row['rate_to_ngn'];
        }
    }
    return isset($rates[$currency]) ? $rates[$currency] : null;
}
