<?php
require_once __DIR__ . '/../lib/lib.php';

// Security headers + no-cache (API)
send_security_headers(['no_store' => true]);

header('Content-Type: application/json; charset=utf-8');

$month = isset($_GET['month']) ? (string)$_GET['month'] : '';
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
  $month = date('Y-m');
}

$store = load_store();

// Cleanup expired payment holds (so the calendar doesn't stay blocked forever)
prune_expired_payment_holds_locked($store);

// Try to keep availability aligned with Booking (iCal)
maybe_auto_sync($store);

$data = availability_for_month($store, $month);
if (!is_array($data) || empty($data['ok'])) {
  echo json_encode([
    'ok' => false,
    'error' => is_array($data) && isset($data['error']) ? (string)$data['error'] : 'Errore nel calendario.'
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

// Hide internal statuses in the public API
foreach ($data['days'] as &$d) {
  if (($d['status'] ?? '') === 'review') {
    $d['status'] = 'booked';
  }
}
unset($d);

echo json_encode([
  'ok' => true,
  'month' => $month,
  'currency' => $data['currency'],
  'min_nights' => $data['min_nights'],
  'days' => $data['days']
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
