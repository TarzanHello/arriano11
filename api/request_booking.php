<?php
require_once __DIR__ . '/../lib/lib.php';

// Security headers + no-cache (API)
send_security_headers(['no_store' => true]);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['ok' => false, 'error' => 'Metodo non consentito'], JSON_UNESCAPED_UNICODE);
  exit;
}

// Basic spam/rate limiting (per IP)
[$okRL, $retryRL] = rate_limit_hit('booking_request', client_ip(), 20, 60 * 60); // 20 requests per hour
if (!$okRL) {
  http_response_code(429);
  echo json_encode(['ok' => false, 'error' => 'Troppi tentativi. Riprova tra ' . (int)$retryRL . ' secondi.'], JSON_UNESCAPED_UNICODE);
  exit;
}

$csrf = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';
if (!csrf_verify($csrf)) {
  echo json_encode(['ok' => false, 'error' => 'Token di sicurezza non valido. Ricarica la pagina.'], JSON_UNESCAPED_UNICODE);
  exit;
}

$start = isset($_POST['start']) ? (string)$_POST['start'] : '';
$end = isset($_POST['end']) ? (string)$_POST['end'] : '';
$name = normalize_text($_POST['name'] ?? '', 80, false);
$email = normalize_text($_POST['email'] ?? '', 120, false);
$phone = normalize_text($_POST['phone'] ?? '', 40, false);
$message = normalize_text($_POST['message'] ?? '', 2000, true);

if ($name === '' || $email === '') {
  echo json_encode(['ok' => false, 'error' => 'Nome e email sono obbligatori.'], JSON_UNESCAPED_UNICODE);
  exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  echo json_encode(['ok' => false, 'error' => 'Email non valida.'], JSON_UNESCAPED_UNICODE);
  exit;
}

$store = load_store();

// Cleanup expired payment holds
$changed = prune_expired_payment_holds($store);
if ($changed) {
  save_store($store);
}

// Keep availability aligned with Booking (iCal)
maybe_auto_sync($store, true);

$startN = normalize_ymd($start);
$endN = normalize_ymd($end);
if (!$startN || !$endN || $endN <= $startN) {
  echo json_encode(['ok' => false, 'error' => 'Date non valide.'], JSON_UNESCAPED_UNICODE);
  exit;
}

// Prevent booking in the past
$today = date('Y-m-d');
if ($startN < $today) {
  echo json_encode(['ok' => false, 'error' => 'Non è possibile prenotare date passate.'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

// Enforce min nights
$quote = quote_stay($store, $startN, $endN);
if (empty($quote['ok'])) {
  echo json_encode(['ok' => false, 'error' => $quote['error'] ?? 'Errore di calcolo.'], JSON_UNESCAPED_UNICODE);
  exit;
}

// Prevent overlaps with unavailable dates
if (!is_range_available_for_request($store, $startN, $endN)) {
  echo json_encode(['ok' => false, 'error' => 'Le date selezionate non sono disponibili.'], JSON_UNESCAPED_UNICODE);
  exit;
}

$id = new_id('req_');
$store['bookings'][] = [
  'id' => $id,
  'start' => $startN,
  'end' => $endN,
  'status' => 'pending',
  'source' => 'direct',
  'name' => $name,
  'email' => $email,
  'phone' => $phone,
  'message' => $message,
  'created_at' => time(),
  'direct_total_with_fees' => $quote['direct_total_with_fees'],
  'currency' => $quote['currency']
];

if (!save_store($store)) {
  echo json_encode(['ok' => false, 'error' => 'Impossibile salvare la richiesta. Controlla i permessi della cartella /data.'], JSON_UNESCAPED_UNICODE);
  exit;
}

// Notify owner
$idx = count($store['bookings'] ?? []) - 1;
if ($idx >= 0 && isset($store['bookings'][$idx]) && is_array($store['bookings'][$idx])) {
  $sent = maybe_notify_owner_booking($store, $store['bookings'][$idx], 'request');
  if ($sent) {
    @save_store($store);
  }
}

echo json_encode(['ok' => true, 'id' => $id], JSON_UNESCAPED_UNICODE);
