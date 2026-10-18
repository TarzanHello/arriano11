<?php
require_once __DIR__ . '/../lib/lib.php';
require_once __DIR__ . '/../lib/stripe.php';

// Security headers + no-cache (API)
send_security_headers(['no_store' => true]);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405);
  echo 'Method Not Allowed';
  exit;
}


// Stripe webhook endpoint
// Configure in Stripe Dashboard -> Developers -> Webhooks

$store = load_store();
$secret = stripe_webhook_secret($store);
if ($secret === '') {
  http_response_code(400);
  echo 'Webhook secret non configurato.';
  exit;
}

$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if ($payload === false) {
  http_response_code(400);
  echo 'Payload non valido.';
  exit;
}

// Basic DoS protection
if (strlen($payload) > 1024 * 1024) { // 1MB
  http_response_code(413);
  echo 'Payload troppo grande.';
  exit;
}

function stripe_webhook_verify($payload, $sigHeader, $secret, $tolerance = 300) {
  if ($sigHeader === '' || $payload === '' || $payload === false) return false;

  $parts = explode(',', (string)$sigHeader);
  $timestamp = null;
  $sigs = [];

  foreach ($parts as $p) {
    $kv = explode('=', trim($p), 2);
    if (count($kv) !== 2) continue;
    $k = trim((string)$kv[0]);
    $v = trim((string)$kv[1]);

    if ($k === 't') {
      $timestamp = (int)$v;
    } elseif ($k === 'v1') {
      if ($v !== '') $sigs[] = $v;
    }
  }

  if (!$timestamp || empty($sigs)) return false;
  if (abs(time() - $timestamp) > (int)$tolerance) return false;

  $signedPayload = $timestamp . '.' . $payload;
  $expected = hash_hmac('sha256', $signedPayload, $secret);

  foreach ($sigs as $sig) {
    if (function_exists('hash_equals')) {
      if (hash_equals($expected, $sig)) return true;
    } else {
      if ($expected === $sig) return true;
    }
  }

  return false;
}

if (!stripe_webhook_verify($payload, $sigHeader, $secret)) {
  http_response_code(400);
  echo 'Signature non valida.';
  exit;
}

$event = json_decode($payload, true);
if (!is_array($event)) {
  http_response_code(400);
  echo 'Payload non valido.';
  exit;
}

$type = (string)($event['type'] ?? '');
$obj = $event['data']['object'] ?? null;

if (($type === 'checkout.session.completed' || $type === 'checkout.session.async_payment_succeeded') && is_array($obj)) {
  $paymentStatus = (string)($obj['payment_status'] ?? '');
  if ($paymentStatus === 'paid') {
    // Confirms (or re-creates) the booking under the store lock; sends emails once.
    stripe_confirm_paid_session($obj);
  }
}

if ($type === 'checkout.session.expired' && is_array($obj)) {
  $meta = $obj['metadata'] ?? [];
  $bookingId = (string)($meta['booking_id'] ?? ($obj['client_reference_id'] ?? ''));
  if ($bookingId !== '') {
    store_mutate(function(&$s) use ($bookingId) {
      $s['bookings'] = array_values(array_filter(($s['bookings'] ?? []), function($b) use ($bookingId) {
        if ((string)($b['id'] ?? '') !== $bookingId) return true;
        return (string)($b['status'] ?? '') !== 'payment_pending';
      }));
    });
  }
}

http_response_code(200);
echo 'ok';
