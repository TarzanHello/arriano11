<?php
require_once __DIR__ . '/../lib/lib.php';
require_once __DIR__ . '/../lib/stripe.php';
require_once __DIR__ . '/../lib/i18n.php';

// Security headers + no-cache (API)
send_security_headers(['no_store' => true]);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error_code' => 'request_failed', 'error' => 'Method not allowed.'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

// Rate limiting (mitigate abuse)
[$okRL, $retryRL] = rate_limit_hit('checkout_request', client_ip(), 12, 60 * 60); // 12 requests per hour
if (!$okRL) {
  http_response_code(429);
  echo json_encode(['ok' => false, 'error_code' => 'request_failed', 'error' => 'Too many requests. Retry in ' . (int)$retryRL . ' seconds.'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

$csrf = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';
if (!csrf_verify($csrf)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error_code' => 'request_failed', 'error' => 'Security token invalid.'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

// Language (from hidden field in booking form)
$langRaw = isset($_POST['lang']) ? (string)$_POST['lang'] : '';
$lang = i18n_normalize_lang($langRaw);
$supported = i18n_supported_languages();
if (!$lang || !isset($supported[$lang])) {
  $lang = 'en';
}
i18n_init($lang);

function respond_error($code, $fallbackMsg = '') {
  $msg = $fallbackMsg;
  $tKey = 'booking.messages.' . $code;
  $tVal = t($tKey);
  if ($tVal && $tVal !== $tKey) {
    $msg = $tVal;
  }
  echo json_encode(['ok' => false, 'error_code' => $code, 'error' => $msg], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

$startRaw = isset($_POST['start']) ? (string)$_POST['start'] : '';
$endRaw   = isset($_POST['end']) ? (string)$_POST['end'] : '';

$start = normalize_ymd($startRaw);
$end   = normalize_ymd($endRaw);

$guests = normalize_guests($_POST['guests'] ?? '', 2);

$name = normalize_text($_POST['name'] ?? '', 80, false);
$email = normalize_text($_POST['email'] ?? '', 120, false);
$phone = normalize_text($_POST['phone'] ?? '', 40, false);
$message = normalize_text($_POST['message'] ?? ($_POST['notes'] ?? ''), 2000, true);

if (!$start || !$end || $end <= $start) {
  respond_error('dates_invalid');
}

$today = date('Y-m-d');
if ($start < $today) {
  respond_error('past_not_allowed');
}

if ($guests === null) {
  respond_error('request_failed');
}

if ($name === '' || $email === '') {
  respond_error('request_failed');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  respond_error('request_failed');
}

$store = load_store();

// Cleanup expired holds
$changed = prune_expired_payment_holds($store);
if ($changed) {
  @save_store($store);
}

// Keep availability aligned with Booking (iCal)
maybe_auto_sync($store, true);

// Enforce min nights + compute quote (includes cleaning + city tax)
$quote = quote_stay($store, $start, $end, (int)$guests);
if (empty($quote['ok'])) {
  respond_error('request_failed');
}

// Prevent overlaps with unavailable dates
if (!is_range_available_for_request($store, $start, $end)) {
  respond_error('unavailable');
}

$currency = (string)($quote['currency'] ?? ($store['settings']['currency'] ?? 'EUR'));
$total = (float)($quote['direct_total_with_fees'] ?? 0);

$requirePayment = !empty($store['settings']['stripe_require_payment']);
$secretKey = stripe_secret_key($store);

// If Stripe is configured and payment is enabled, we create a Checkout Session.
if ($requirePayment && $secretKey !== '') {
  $holdMin = (int)($store['settings']['stripe_hold_minutes'] ?? 30);
  // Stripe constraint: expires_at must be >= 30 minutes from creation
  if ($holdMin < 30) $holdMin = 30;
  if ($holdMin > 240) $holdMin = 240;
  $expiresAt = time() + ($holdMin * 60);

  $depositPct = (float)($store['settings']['stripe_deposit_percent'] ?? 0.30);
  if ($depositPct <= 0) $depositPct = 1.0;
  if ($depositPct > 1.0) $depositPct = 1.0;
  if ($depositPct < 0.05) $depositPct = 0.05;

  $amountDue = round($total * $depositPct, 2);
  if ($amountDue <= 0) {
    $amountDue = $total;
    $depositPct = 1.0;
  }

  $amountCents = (int)round($amountDue * 100);
  if ($amountCents < 50) {
    respond_error('payment_failed');
  }

  $id = new_id('bk_');
  $store['bookings'][] = [
    'id' => $id,
    'start' => $start,
    'end' => $end,
    'nights' => (int)($quote['nights'] ?? 0),
    'guests' => (int)$guests,

    // Cost breakdown
    'accommodation_total' => (float)($quote['direct_total'] ?? 0),
    'cleaning_fee' => (float)($quote['cleaning_fee'] ?? 0),
    'city_tax_total' => (float)($quote['city_tax_total'] ?? 0),
    'direct_total_with_fees' => $total,

    // Payment
    'status' => 'payment_pending',
    'created_at' => time(),
    'expires_at' => $expiresAt,
    'amount_due' => $amountDue,
    'deposit_percent' => $depositPct,
    'currency' => $currency,

    // Language
    'lang' => $lang,

    // Guest
    'source' => 'direct',
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
    'message' => $message,
  ];

  if (!save_store($store)) {
    respond_error('request_failed', 'Unable to save booking.');
  }

  $siteName = (string)($store['settings']['site_name'] ?? 'Via Arriano 11');
  $productName = $siteName;
  $desc = $start . ' → ' . $end;

  $successUrl = rtrim(base_url(), '/') . i18n_url('booking_success', $lang) . '?session_id={CHECKOUT_SESSION_ID}';
  $cancelUrl  = rtrim(base_url(), '/') . i18n_url('booking_cancel', $lang) . '?session_id={CHECKOUT_SESSION_ID}';

  $params = [
    'mode' => 'payment',
    'success_url' => $successUrl,
    'cancel_url' => $cancelUrl,
    'locale' => ($lang === 'en' ? 'en' : $lang),
    'customer_email' => $email,
    'client_reference_id' => $id,
    'expires_at' => (int)$expiresAt,
    'metadata' => [
      'booking_id' => $id,
      'start' => $start,
      'end' => $end,
      'guests' => (int)$guests,
      'lang' => $lang,
    ],
    'payment_method_types' => ['card'],
    'line_items' => [
      [
        'quantity' => 1,
        'price_data' => [
          'currency' => strtolower($currency),
          'unit_amount' => $amountCents,
          'product_data' => [
            'name' => $productName,
            'description' => $desc,
          ],
        ],
      ],
    ],
  ];

  $res = stripe_create_checkout_session($secretKey, $params);
  if (empty($res['ok'])) {
    // Remove the temporary hold
    $store = load_store();
    $store['bookings'] = array_values(array_filter(($store['bookings'] ?? []), function($b) use ($id) {
      return (string)($b['id'] ?? '') !== $id;
    }));
    @save_store($store);

    respond_error('payment_failed');
  }

  $session = $res['data'] ?? [];
  $sessionId = (string)($session['id'] ?? '');
  $checkoutUrl = (string)($session['url'] ?? '');

  if ($sessionId !== '') {
    $store = load_store();
    foreach (($store['bookings'] ?? []) as &$bk) {
      if ((string)($bk['id'] ?? '') === $id) {
        $bk['stripe_session_id'] = $sessionId;
        break;
      }
    }
    unset($bk);
    @save_store($store);
  }

  if ($checkoutUrl === '') {
    respond_error('payment_failed', 'Payment URL not available.');
  }

  echo json_encode([
    'ok' => true,
    'mode' => 'checkout',
    'checkout_url' => $checkoutUrl,
    'booking_id' => $id,
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

// Fallback: create a simple booking request (pending)
$id = new_id('req_');
$store['bookings'][] = [
  'id' => $id,
  'start' => $start,
  'end' => $end,
  'nights' => (int)($quote['nights'] ?? 0),
  'guests' => (int)$guests,
  'lang' => $lang,
  'status' => 'pending',
  'source' => 'direct',
  'name' => $name,
  'email' => $email,
  'phone' => $phone,
  'message' => $message,
  'created_at' => time(),

  // Cost breakdown
  'accommodation_total' => (float)($quote['direct_total'] ?? 0),
  'cleaning_fee' => (float)($quote['cleaning_fee'] ?? 0),
  'city_tax_total' => (float)($quote['city_tax_total'] ?? 0),
  'direct_total_with_fees' => $total,
  'currency' => $currency,
];

if (!save_store($store)) {
  respond_error('request_failed', 'Unable to send request.');
}

// Notify owner (request mode)
$idx = count($store['bookings'] ?? []) - 1;
if ($idx >= 0 && isset($store['bookings'][$idx]) && is_array($store['bookings'][$idx])) {
  $sent = maybe_notify_owner_booking($store, $store['bookings'][$idx], 'request');
  if ($sent) {
    @save_store($store);
  }
}

echo json_encode([
  'ok' => true,
  'mode' => 'request',
  'id' => $id,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
