<?php
// Minimal Stripe helpers (no composer) for Arriano 11

function stripe_secret_key($store) {
  return trim((string)($store['settings']['stripe_secret_key'] ?? ''));
}

function stripe_webhook_secret($store) {
  return trim((string)($store['settings']['stripe_webhook_secret'] ?? ''));
}

// True when Stripe is both configured (secret key present) and enabled for bookings.
function stripe_configured($store) {
  $requirePayment = !empty($store['settings']['stripe_require_payment']);
  $secretKey = stripe_secret_key($store);
  return $requirePayment && $secretKey !== '';
}


function stripe_api_request($method, $path, $secretKey, $params = null) {
  if (!function_exists('curl_init')) {
    return ['ok' => false, 'error' => 'cURL non disponibile sul server (necessario per Stripe).'];
  }

  $method = strtoupper((string)$method);
  $base = 'https://api.stripe.com/v1';
  $url = $base . $path;

  $ch = curl_init();
  if ($ch === false) {
    return ['ok' => false, 'error' => 'Impossibile inizializzare cURL.'];
  }

  $headers = [
    'Authorization: Bearer ' . $secretKey,
    'Content-Type: application/x-www-form-urlencoded'
  ];

  if ($method === 'GET' && is_array($params) && count($params)) {
    $url .= '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
  }

  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_TIMEOUT, 20);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

  // TLS verification (explicit)
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

  // Restrict protocols to HTTPS only (defence-in-depth)
  if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
    curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
  }
  if (defined('CURLOPT_REDIR_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
    curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
  }

  if ($method === 'POST') {
    curl_setopt($ch, CURLOPT_POST, true);
    $body = is_array($params) ? http_build_query($params, '', '&', PHP_QUERY_RFC3986) : '';
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
  } elseif ($method !== 'GET') {
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    $body = is_array($params) ? http_build_query($params, '', '&', PHP_QUERY_RFC3986) : '';
    if ($body !== '') {
      curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
  }

  $resp = curl_exec($ch);
  $errno = curl_errno($ch);
  $err = curl_error($ch);
  $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($resp === false || $errno) {
    return ['ok' => false, 'error' => 'Errore Stripe (cURL): ' . ($err ?: 'errore sconosciuto')];
  }

  $data = json_decode($resp, true);
  if (!is_array($data)) {
    return ['ok' => false, 'error' => 'Risposta Stripe non valida.', 'status' => $status, 'raw' => $resp];
  }

  if ($status < 200 || $status >= 300) {
    $msg = $data['error']['message'] ?? 'Errore Stripe.';
    return ['ok' => false, 'error' => $msg, 'status' => $status, 'data' => $data];
  }

  return ['ok' => true, 'status' => $status, 'data' => $data];
}

function stripe_create_checkout_session($secretKey, $params) {
  return stripe_api_request('POST', '/checkout/sessions', $secretKey, $params);
}

function stripe_retrieve_checkout_session($secretKey, $sessionId) {
  $sessionId = trim((string)$sessionId);
  if ($sessionId === '') {
    return ['ok' => false, 'error' => 'Session ID mancante.'];
  }
  return stripe_api_request('GET', '/checkout/sessions/' . rawurlencode($sessionId), $secretKey, null);
}

// Asks Stripe to expire an open Checkout Session (used when the guest cancels),
// so the old payment page cannot be paid after the dates were released.
function stripe_expire_checkout_session($secretKey, $sessionId) {
  $sessionId = trim((string)$sessionId);
  if ($sessionId === '') {
    return ['ok' => false, 'error' => 'Session ID mancante.'];
  }
  return stripe_api_request('POST', '/checkout/sessions/' . rawurlencode($sessionId) . '/expire', $secretKey, []);
}

// Marks the booking of a PAID Checkout Session as confirmed (shared by the webhook and
// the success page). Runs under the store lock. Idempotent.
// FIX (webhook): the booking is looked up BEFORE pruning expired holds, and it is
// re-created from the session metadata if the temporary hold is gone, so a completed
// payment can never be lost. If the dates are no longer free the owner gets an alert.
// Returns ['ok' => true, 'booking' => [...], 'conflict' => bool] or ['ok' => false, 'error_key' => ...].
function stripe_confirm_paid_session($session) {
  if (!is_array($session)) return ['ok' => false, 'error_key' => 'booking_success.err_verify_failed'];

  $meta = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
  $sessionId = (string)($session['id'] ?? '');
  $bookingId = (string)($meta['booking_id'] ?? ($session['client_reference_id'] ?? ''));
  $start = normalize_ymd((string)($meta['start'] ?? ''));
  $end = normalize_ymd((string)($meta['end'] ?? ''));
  $guests = normalize_guests($meta['guests'] ?? '', 2);
  $amountTotal = isset($session['amount_total']) ? ((int)$session['amount_total'] / 100.0) : 0.0;
  $currency = strtoupper((string)($session['currency'] ?? 'EUR'));
  $paymentIntent = (string)($session['payment_intent'] ?? '');
  $custName = (string)($session['customer_details']['name'] ?? '');
  $custEmail = (string)($session['customer_details']['email'] ?? ($session['customer_email'] ?? ''));
  $lang = (string)($meta['lang'] ?? 'it');

  [$store, $res] = store_mutate(function(&$s) use ($sessionId, $bookingId, $start, $end, $guests, $amountTotal, $currency, $paymentIntent, $custName, $custEmail, $lang) {
    $idx = -1;
    foreach (($s['bookings'] ?? []) as $i => $bk) {
      $bid = (string)($bk['id'] ?? '');
      $sid = (string)($bk['stripe_session_id'] ?? '');
      if (($bookingId !== '' && $bid === $bookingId) || ($sessionId !== '' && $sid === $sessionId)) {
        $idx = $i;
        break;
      }
    }

    $conflict = false;
    if ($idx < 0) {
      if (!$start || !$end || $end <= $start) {
        return ['ok' => false, 'error_key' => 'booking_success.err_missing_details'];
      }
      $conflict = !is_range_available_for_request($s, $start, $end);
      $g = ($guests === null) ? 2 : (int)$guests;
      $quote = quote_stay($s, $start, $end, $g);
      $qok = !empty($quote['ok']);
      $s['bookings'][] = [
        'id' => ($bookingId !== '') ? $bookingId : new_id('bk_'),
        'start' => $start,
        'end' => $end,
        'nights' => nights_between($start, $end),
        'guests' => $g,
        'status' => 'booked',
        'source' => 'direct',
        'name' => $custName,
        'email' => $custEmail,
        'phone' => '',
        'message' => '',
        'created_at' => time(),
        'lang' => $lang,
        'accommodation_total' => $qok ? (float)($quote['direct_total'] ?? 0) : null,
        'cleaning_fee' => $qok ? (float)($quote['cleaning_fee'] ?? 0) : null,
        'city_tax_total' => $qok ? (float)($quote['city_tax_total'] ?? 0) : null,
        'direct_total_with_fees' => $qok ? (float)($quote['direct_total_with_fees'] ?? 0) : null,
        'recreated_from_payment' => true,
        'needs_check' => $conflict,
      ];
      $idx = count($s['bookings']) - 1;
    }

    $bk = $s['bookings'][$idx];
    $bk['status'] = 'booked';
    if (empty($bk['paid_at'])) $bk['paid_at'] = time();
    if ($sessionId !== '') $bk['stripe_session_id'] = $sessionId;
    if ($paymentIntent !== '') $bk['stripe_payment_intent'] = $paymentIntent;
    $bk['amount_paid'] = round($amountTotal, 2);
    $bk['currency'] = $currency;
    if (empty($bk['nights']) && !empty($bk['start']) && !empty($bk['end'])) {
      $bk['nights'] = nights_between($bk['start'], $bk['end']);
    }

    // Paid confirmation emails (guest + owner), idempotent
    maybe_send_paid_booking_emails($s, $bk);

    if ($conflict && empty($bk['conflict_alert_sent_at'])) {
      $site = (string)($s['settings']['site_name'] ?? 'Via Arriano 11');
      $body = "ATTENZIONE: è arrivato un pagamento Stripe ma le date non risultano più libere sul calendario.\n\n"
        . 'Periodo: ' . (string)$bk['start'] . ' → ' . (string)$bk['end'] . "\n"
        . 'Ospite: ' . (string)($bk['name'] ?? '') . ' ' . (string)($bk['email'] ?? '') . "\n"
        . 'Importo: ' . format_currency((float)$bk['amount_paid'], $currency) . "\n\n"
        . "Controlla subito le prenotazioni (possibile doppia prenotazione) e, se serve, rimborsa da Stripe.\n"
        . base_url() . '/admin/index.php?tab=bookings';
      if (send_mail_utf8(notify_email($s), 'ATTENZIONE possibile doppia prenotazione · ' . $site, $body, (string)($bk['email'] ?? ''))) {
        $bk['conflict_alert_sent_at'] = time();
      }
    }

    $s['bookings'][$idx] = $bk;
    prune_expired_payment_holds($s);

    return ['ok' => true, 'booking' => $bk, 'conflict' => $conflict];
  });

  return is_array($res) ? $res : ['ok' => false, 'error_key' => 'booking_success.err_verify_failed'];
}

