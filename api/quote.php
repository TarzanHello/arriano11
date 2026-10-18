<?php
require_once __DIR__ . '/../lib/lib.php';

// Security headers + no-cache (API)
send_security_headers(['no_store' => true]);


header('Content-Type: application/json; charset=utf-8');

$store = load_store();

// Best-effort: keep availability aligned with Booking (iCal)
maybe_auto_sync($store);


$start = isset($_GET['start']) ? normalize_ymd($_GET['start']) : null;
$end   = isset($_GET['end']) ? normalize_ymd($_GET['end']) : null;
$guestsRaw = isset($_GET['guests']) ? $_GET['guests'] : '';

if (!$start || !$end) {
  echo json_encode(['ok' => false, 'error' => 'Date non valide.']);
  exit;
}

$guests = normalize_guests($guestsRaw, 2);
if ($guests === null) {
  echo json_encode(['ok' => false, 'error' => 'Numero ospiti non valido.']);
  exit;
}

if (!is_range_available_for_request($store, $start, $end)) {
  echo json_encode(['ok' => false, 'error' => 'Intervallo non disponibile.']);
  exit;
}

$q = quote_stay($store, $start, $end, $guests);
if (!$q['ok']) {
  echo json_encode($q);
  exit;
}

$currency = $q['currency'];

echo json_encode([
  'ok' => true,
  'start' => $q['start'],
  'end' => $q['end'],
  'nights' => $q['nights'],
  'guests' => $q['guests'],
  'accommodation_total' => $q['direct_total'],
  'cleaning_fee' => $q['cleaning_fee'],
  'city_tax_total' => $q['city_tax_total'],
  'city_tax_per_person_per_night' => $q['city_tax_per_person_per_night'],
  'total' => $q['direct_total_with_fees'],

  // formatted helpers
  'accommodation_total_formatted' => format_currency($q['direct_total'], $currency),
  'cleaning_fee_formatted' => format_currency($q['cleaning_fee'], $currency),
  'city_tax_total_formatted' => format_currency($q['city_tax_total'], $currency),
  'total_formatted' => format_currency($q['direct_total_with_fees'], $currency),
  'currency' => $currency,
]);
