<?php
require_once __DIR__ . '/lib/lib.php';

// Security headers + no-cache (calendar feed)
send_security_headers(['no_store' => true]);

$store = load_store();

// Ensure we have a token for private exports
if (empty($store['settings']['ical_export_secret'])) {
  $store['settings']['ical_export_secret'] = bin2hex(random_bytes_fallback(16));
  @save_store($store);
}

$publicEnabled = !empty($store['settings']['ical_export_public_enabled']);
$secret = (string)($store['settings']['ical_export_secret'] ?? '');
$token = isset($_GET['token']) ? (string)$_GET['token'] : '';
$token = preg_replace('/[^a-f0-9]/i', '', $token);

// By default the export is private (token required). You can enable a public export from Admin -> Sync iCal.
if (!$publicEnabled) {
  if ($secret === '' || $token === '' || !hash_equals($secret, $token)) {
    // Rate limit invalid token attempts (best-effort, reduces brute force noise)
    [$allowed, $retry] = rate_limit_hit('ical_export_invalid', client_ip(), 30, 60);
    if (!$allowed) {
      http_response_code(429);
      header('Retry-After: ' . (string)$retry);
      header('Content-Type: text/plain; charset=utf-8');
      echo 'Too Many Requests';
      exit;
    }

    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    exit;
  }
}

$ics = build_ics_from_store($store);

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="calendar.ics"');
header('Cache-Control: no-store, no-cache, must-revalidate');

echo $ics;
