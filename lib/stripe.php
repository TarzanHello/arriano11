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
