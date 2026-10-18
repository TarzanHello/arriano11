<?php
require_once __DIR__ . '/../lib/lib.php';

// Security headers + no-cache (API)
send_security_headers(['no_store' => true]);

header('Content-Type: application/json; charset=utf-8');

// This endpoint is meant to be called by a server-side scheduler (Cron) every 5 minutes.
// It is protected by a secret token stored in /data/store.json (admin only can view).

$store = load_store();
$expected = (string)($store['settings']['cron_sync_token'] ?? '');

// Token required
$token = '';
if (isset($_GET['token'])) {
  $token = (string)$_GET['token'];
} elseif (!empty($_SERVER['HTTP_X_CRON_TOKEN'])) {
  $token = (string)$_SERVER['HTTP_X_CRON_TOKEN'];
} elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
  // Support: Authorization: Bearer <token>
  if (preg_match('/^Bearer\s+(.+)$/i', (string)$_SERVER['HTTP_AUTHORIZATION'], $m)) {
    $token = (string)$m[1];
  }
}
$token = preg_replace('/[^a-f0-9]/i', '', $token);

if ($expected === '' || $token === '' || !hash_equals($expected, $token)) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'Forbidden'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

// Rate limit (defence-in-depth)
[$okRL, $retryRL] = rate_limit_hit('cron_sync', client_ip(), 120, 60 * 60); // 120 runs per hour per IP
if (!$okRL) {
  http_response_code(429);
  echo json_encode(['ok' => false, 'error' => 'Too Many Requests', 'retry_after' => (int)$retryRL], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

// Cleanup expired payment holds
prune_expired_payment_holds_locked($store);

$start = microtime(true);

// Force a sync (ignores interval). Download happens outside the lock, saving inside it.
$res = sync_booking_locked($store);
if (is_array($res) && isset($res[0]) && $res[0] === true) {
  $elapsedMs = (int)round((microtime(true) - $start) * 1000);
  $count = isset($res[1]) ? (int)$res[1] : 0;

  echo json_encode([
    'ok' => true,
    'imported_events' => $count,
    'last_sync' => (int)($store['settings']['last_sync'] ?? time()),
    'elapsed_ms' => $elapsedMs,
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

// Error (do not expose internals too much)
$err = (is_array($res) && isset($res[1])) ? (string)$res[1] : 'Sync failed.';
http_response_code(500);
echo json_encode(['ok' => false, 'error' => $err], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
