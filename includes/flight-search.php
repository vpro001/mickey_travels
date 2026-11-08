<?php
// Reads and checks a flight search from the URL (?trip=return&from[]=LOS&to[]=LHR&depart[]=...).
// Used by flight-results.php and api/flight-search.php.
//
// Returns ['error' => '...'] if something is wrong, otherwise:
//   ['trip', 'slices' => [['origin','destination','departure_date'], ...], 'adults', 'children', 'infants', 'cabin']

function read_flight_search($q)
{
    $trip = isset($q['trip']) && in_array($q['trip'], ['one_way', 'return', 'multi']) ? $q['trip'] : 'return';
    $from = isset($q['from']) ? (array) $q['from'] : [];
    $to = isset($q['to']) ? (array) $q['to'] : [];
    $depart = isset($q['depart']) ? (array) $q['depart'] : [];

    $legs = $trip == 'multi' ? min(4, count($from)) : 1;
    if ($legs < 1 || ($trip == 'multi' && $legs < 2)) {
        return ['error' => 'Please choose where you are flying from and to.'];
    }

    $today = date('Y-m-d');
    $last_day = date('Y-m-d', strtotime('+330 days'));
    $slices = [];
    $previous_date = $today;

    for ($i = 0; $i < $legs; $i++) {
        $o = isset($from[$i]) && is_string($from[$i]) ? strtoupper(trim($from[$i])) : '';
        $d = isset($to[$i]) && is_string($to[$i]) ? strtoupper(trim($to[$i])) : '';
        $date = isset($depart[$i]) && is_string($depart[$i]) ? $depart[$i] : '';

        if (!preg_match('/^[A-Z]{3}$/', $o) || !preg_match('/^[A-Z]{3}$/', $d)) {
            return ['error' => 'Please choose airports from the list.'];
        }
        if ($o == $d) {
            return ['error' => 'The departure and arrival airports are the same.'];
        }
        if (!valid_date($date) || $date < $today || $date > $last_day) {
            return ['error' => 'Please choose travel dates between today and ' . nice_date($last_day) . '.'];
        }
        if ($date < $previous_date) {
            return ['error' => 'Each flight must be on or after the date of the one before.'];
        }
        $previous_date = $date;
        $slices[] = ['origin' => $o, 'destination' => $d, 'departure_date' => $date];
    }

    if ($trip == 'return') {
        $return = isset($q['return']) && is_string($q['return']) ? $q['return'] : '';
        if (!valid_date($return) || $return < $slices[0]['departure_date'] || $return > $last_day) {
            return ['error' => 'Please choose a return date on or after your departure date.'];
        }
        $slices[] = ['origin' => $slices[0]['destination'], 'destination' => $slices[0]['origin'], 'departure_date' => $return];
    }

    $adults = isset($q['adults']) ? (int) $q['adults'] : 1;
    $children = isset($q['children']) ? (int) $q['children'] : 0;
    $infants = isset($q['infants']) ? (int) $q['infants'] : 0;
    if ($adults < 1 || $adults > 9 || $children < 0 || $infants < 0 || $adults + $children > 9) {
        return ['error' => 'You can book for up to 9 travellers (plus infants), with at least one adult.'];
    }
    if ($infants > $adults) {
        return ['error' => 'Each infant must travel with their own adult.'];
    }

    $cabin = isset($q['cabin']) && in_array($q['cabin'], ['economy', 'premium_economy', 'business', 'first']) ? $q['cabin'] : 'economy';

    return [
        'error' => '',
        'trip' => $trip,
        'slices' => $slices,
        'adults' => $adults,
        'children' => $children,
        'infants' => $infants,
        'cabin' => $cabin,
    ];
}
