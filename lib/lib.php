<?php

// Common helpers for Arriano 11 (PHP version, no npm)

$config = require __DIR__ . '/config.php';
if (!empty($config['timezone'])) {
  date_default_timezone_set($config['timezone']);
}

// Production-safe error settings (override in lib/config.php -> debug)
$__via_debug = !empty($config['debug']);
if (php_sapi_name() !== 'cli') {
  @ini_set('display_errors', $__via_debug ? '1' : '0');
  @ini_set('display_startup_errors', $__via_debug ? '1' : '0');
  @ini_set('log_errors', '1');
}

function e($value) {
  return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}


// ------------------------------------------------------------
// Security helpers (headers, sessions, rate limiting)
// ------------------------------------------------------------

function is_https_request() {
  $https = false;
  if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') $https = true;
  if (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) $https = true;
  if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') $https = true;
  return $https;
}

function sanitize_host($host) {
  $host = trim((string)$host);
  // Drop any CR/LF to prevent header injection
  $host = preg_replace('/[\r\n].*/', '', $host);
  // Strip port
  $host = preg_replace('/:.*/', '', $host);
  $host = strtolower($host);
  $host = preg_replace('/[^a-z0-9.-]/', '', $host);
  $host = trim($host, '.');
  return $host;
}

function trusted_host() {
  global $config;

  // Requested host is user-controlled, so we only accept it if explicitly allowed.
  $requested = sanitize_host($_SERVER['HTTP_HOST'] ?? '');
  $serverName = sanitize_host($_SERVER['SERVER_NAME'] ?? '');

  $allowed = [];

  if (!empty($config['allowed_hosts']) && is_array($config['allowed_hosts'])) {
    foreach ($config['allowed_hosts'] as $h) {
      $h = sanitize_host($h);
      if ($h !== '') $allowed[] = $h;
    }
  }

  // Fallback to server config if no allowlist is provided
  if (empty($allowed) && $serverName !== '') {
    $allowed = [$serverName, 'www.' . $serverName];
  }

  // Canonical host wins (defence against Host header injection)
  $canonical = '';
  if (!empty($config['canonical_host'])) {
    $canonical = sanitize_host($config['canonical_host']);
    if ($canonical !== '' && !in_array($canonical, $allowed, true)) {
      $allowed[] = $canonical;
    }
  }

  if ($canonical !== '') {
    return $canonical;
  }

  if ($requested !== '' && !empty($allowed) && in_array($requested, $allowed, true)) {
    return $requested;
  }

  if (!empty($allowed)) {
    return $allowed[0];
  }

  if ($serverName !== '') return $serverName;
  if ($requested !== '') return $requested;
  return 'localhost';
}

function enforce_https_if_configured() {
  global $config;

  if (php_sapi_name() === 'cli') return;
  if (empty($config['force_https'])) return;
  if (is_https_request()) return;

  // If we cannot determine the host, do not attempt a redirect.
  $host = trusted_host();
  if ($host === '') return;

  $uri = $_SERVER['REQUEST_URI'] ?? '/';
  if ($uri === '') $uri = '/';
  $uri = preg_replace('/[\r\n]/', '', (string)$uri);

  $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
  // Preserve method for POST requests
  $code = ($method === 'GET' || $method === 'HEAD') ? 301 : 307;

  header('Location: https://' . $host . $uri, true, $code);
  exit;
}

// Enforce HTTPS early (protects cookies + admin sessions).
if (php_sapi_name() !== 'cli') {
  enforce_https_if_configured();
}


function csp_nonce() {
  if (!isset($GLOBALS['_VIA_CSP_NONCE'])) {
    // URL-safe base64
    $raw = random_bytes_fallback(18);
    $GLOBALS['_VIA_CSP_NONCE'] = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
  }
  return (string)$GLOBALS['_VIA_CSP_NONCE'];
}

function send_security_headers($options = []) {
  if (headers_sent()) return;

  // Best-effort: hide technology disclosure header
  if (function_exists('header_remove')) {
    @header_remove('X-Powered-By');
  }

  $isAdmin = !empty($options['admin']);
  $noStore = !empty($options['no_store']);

  header('X-Frame-Options: SAMEORIGIN');
  header('X-Content-Type-Options: nosniff');
  header('Referrer-Policy: strict-origin-when-cross-origin');
  header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');
  header('Cross-Origin-Opener-Policy: same-origin');
  header('Cross-Origin-Resource-Policy: same-origin');
  header('X-Permitted-Cross-Domain-Policies: none');

  if ($isAdmin) {
    header('X-Robots-Tag: noindex, nofollow', true);
  }

  if ($noStore) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
  }

  // HSTS only when HTTPS is actually used
  if (is_https_request()) {
    header('Strict-Transport-Security: max-age=31536000');
  }

  // Content Security Policy (nonce-based for inline scripts)
  $nonce = csp_nonce();
  $csp = [];
  $csp[] = "default-src 'self'";
  $csp[] = "base-uri 'self'";
  $csp[] = "form-action 'self'";
  $csp[] = "frame-ancestors 'self'";
  $csp[] = "object-src 'none'";
  $csp[] = "img-src 'self' data: https:";
  $csp[] = "font-src 'self' data:";
  $csp[] = "style-src 'self' 'unsafe-inline'";
  $csp[] = "script-src 'self' 'nonce-" . $nonce . "'";
  $csp[] = "connect-src 'self'";
  if (is_https_request()) {
    $csp[] = "upgrade-insecure-requests";
    $csp[] = "block-all-mixed-content";
  }
  header('Content-Security-Policy: ' . implode('; ', $csp));
}

function client_ip() {
  $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
  return preg_replace('/[^0-9a-fA-F:.\-]/', '', $ip);
}

function rate_limit_file_path() {
  return __DIR__ . '/../data/rate_limits.json';
}

function ensure_private_dir_protected($dir) {
  $dir = rtrim((string)$dir, '/');
  if ($dir === '') return;
  $ht = $dir . '/.htaccess';
  if (!file_exists($ht)) {
    $rules = "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n";
    @file_put_contents($ht, $rules, LOCK_EX);
  }
  $index = $dir . '/index.html';
  if (!file_exists($index)) {
    @file_put_contents($index, "", LOCK_EX);
  }
}

function rate_limit_with_lock($callback) {
  $path = rate_limit_file_path();
  $dir = dirname($path);
  if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
  }
  ensure_private_dir_protected($dir);

  $fh = @fopen($path, 'c+');
  if (!$fh) {
    // If we cannot open the file, degrade gracefully: allow everything.
    return $callback(null, []);
  }

  @flock($fh, LOCK_EX);

  rewind($fh);
  $raw = stream_get_contents($fh);
  $data = json_decode($raw ?: '', true);
  if (!is_array($data)) $data = [];

  $result = $callback($fh, $data);

  @flock($fh, LOCK_UN);
  @fclose($fh);
  return $result;
}

function rate_limit_key($bucket, $key) {
  return (string)$bucket . ':' . sha1((string)$key);
}

function rate_limit_prune($timestamps, $windowSeconds, $now) {
  if (!is_array($timestamps)) return [];
  $min = $now - (int)$windowSeconds;
  $out = [];
  foreach ($timestamps as $ts) {
    $ts = (int)$ts;
    if ($ts >= $min) $out[] = $ts;
  }
  sort($out);
  if (count($out) > 200) {
    $out = array_slice($out, -200);
  }
  return $out;
}

function rate_limit_is_blocked($bucket, $key, $maxAttempts, $windowSeconds) {
  $now = time();
  return rate_limit_with_lock(function($fh, $data) use ($bucket, $key, $maxAttempts, $windowSeconds, $now) {
    $k = rate_limit_key($bucket, $key);
    $arr = rate_limit_prune($data[$k] ?? [], $windowSeconds, $now);

    if (count($arr) >= (int)$maxAttempts) {
      $retry = ((int)$arr[0] + (int)$windowSeconds) - $now;
      if ($retry < 1) $retry = 1;
      return [true, $retry];
    }
    return [false, 0];
  });
}

function rate_limit_hit($bucket, $key, $maxAttempts, $windowSeconds) {
  $now = time();
  return rate_limit_with_lock(function($fh, $data) use ($bucket, $key, $maxAttempts, $windowSeconds, $now) {
    $k = rate_limit_key($bucket, $key);
    $arr = rate_limit_prune($data[$k] ?? [], $windowSeconds, $now);

    if (count($arr) >= (int)$maxAttempts) {
      $retry = ((int)$arr[0] + (int)$windowSeconds) - $now;
      if ($retry < 1) $retry = 1;
      return [false, $retry];
    }

    $arr[] = $now;
    $data[$k] = $arr;

    if ($fh) {
      $json = json_encode($data, JSON_UNESCAPED_SLASHES);
      if ($json !== false) {
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, $json);
        fflush($fh);
      }
    }

    return [true, 0];
  });
}

function rate_limit_clear($bucket, $key) {
  return rate_limit_with_lock(function($fh, $data) use ($bucket, $key) {
    $k = rate_limit_key($bucket, $key);
    if (isset($data[$k])) {
      unset($data[$k]);
      if ($fh) {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES);
        if ($json !== false) {
          ftruncate($fh, 0);
          rewind($fh);
          fwrite($fh, $json);
          fflush($fh);
        }
      }
    }
    return true;
  });
}

function normalize_text($value, $maxLen = 200, $allowNewlines = false) {
  $s = (string)$value;
  $s = str_replace("\0", '', $s);
  $s = trim($s);

  if ($allowNewlines) {
    $s = str_replace("\r\n", "\n", $s);
    $s = str_replace("\r", "\n", $s);
  } else {
    $s = str_replace(["\r", "\n"], ' ', $s);
  }

  // Remove other control chars
  $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);

  if (function_exists('mb_strlen') && function_exists('mb_substr')) {
    if (mb_strlen($s, 'UTF-8') > (int)$maxLen) {
      $s = mb_substr($s, 0, (int)$maxLen, 'UTF-8');
    }
  } else {
    if (strlen($s) > (int)$maxLen) {
      $s = substr($s, 0, (int)$maxLen);
    }
  }

  return $s;
}


function store_file_path() {
  return __DIR__ . '/../data/store.json';
}

function load_store() {
  $path = store_file_path();
  $dir = dirname($path);
  if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
  }
  ensure_private_dir_protected($dir);

  $blank = [
    'settings' => [],
    'daily' => [],
    'overrides' => [],
    'blocks' => [],
    'bookings' => [],
    'holds' => [],
  ];

  $data = null;
  $createdNew = false;

  if (!file_exists($path)) {
    $createdNew = true;
    $data = $blank;
  } else {
    $raw = @file_get_contents($path);
    $data = json_decode($raw ?: '', true);

    // Second try (covers rare partial reads from legacy non-atomic writes)
    if (!is_array($data)) {
      @usleep(50000);
      $raw2 = @file_get_contents($path);
      if ($raw2 !== false) {
        $data = json_decode($raw2 ?: '', true);
      }
    }

    if (!is_array($data)) {
      // Try to restore the last known good backup (created by save_store)
      $bakPath = $dir . '/store.json.bak';
      $bakRaw = @file_get_contents($bakPath);
      $bak = json_decode($bakRaw ?: '', true);

      // Preserve corrupted store to avoid silent data loss
      $stamp = date('Ymd_His');
      @rename($path, $dir . '/store.corrupt.' . $stamp . '.json');

      $createdNew = true;

      if (is_array($bak)) {
        $data = $bak;
        // Restore store.json from the backup so the site keeps working
        @save_store($data);
      } else {
        $data = $blank;
      }
    }
  }

  // Normalize top-level keys
  if (!isset($data['settings']) || !is_array($data['settings'])) $data['settings'] = [];
  if (!isset($data['daily']) || !is_array($data['daily'])) $data['daily'] = [];
  if (!isset($data['overrides']) || !is_array($data['overrides'])) $data['overrides'] = [];
  if (!isset($data['blocks']) || !is_array($data['blocks'])) $data['blocks'] = [];
  if (!isset($data['bookings']) || !is_array($data['bookings'])) $data['bookings'] = [];
  if (!isset($data['holds']) || !is_array($data['holds'])) $data['holds'] = [];

  // Defaults for non-sensitive settings
  $defaults = [
    'nightly_rate' => 120,
    'cleaning_fee' => 0,
    'min_nights' => 3,
    'check_in_time' => '15:00',
    'check_out_time' => '11:00',
    'tourist_tax_per_person_per_night' => 6,
    'tourist_tax_max_nights' => 10,
    'max_guests' => 4,
    'admin_user' => 'admin',
    // NOTE: admin_pass_hash is intentionally NOT defaulted to a known value for security.
    'booking_email' => '',
    'booking_phone' => '',
    'booking_address' => '',
    'booking_ical_url' => '',
    'sync_interval_minutes' => 5,
    'cron_sync_token' => '',
    'stripe_secret_key' => '',
    'stripe_webhook_secret' => '',
    'deposit_amount' => 150,
    'deposit_currency' => 'eur',
    'hold_minutes' => 30,
    'ical_export_secret' => '',
    'ical_export_public_enabled' => 0,
    'default_lang' => 'it',
  ];

  foreach ($defaults as $k => $v) {
    if (!array_key_exists($k, $data['settings'])) {
      $data['settings'][$k] = $v;
    }
  }

  // Hard requirement: keep iCal import sync at 5 minutes
  $syncEvery = (int)($data['settings']['sync_interval_minutes'] ?? 5);
  if ($syncEvery < 5) $syncEvery = 5;
  if ($syncEvery > 5) $syncEvery = 5;
  if (!isset($data['settings']['sync_interval_minutes']) || (int)$data['settings']['sync_interval_minutes'] !== $syncEvery) {
    $data['settings']['sync_interval_minutes'] = $syncEvery;
    $createdNew = true;
  }

  // Export secret (needed for public iCal export URLs)
  if (empty($data['settings']['ical_export_secret'])) {
    $data['settings']['ical_export_secret'] = bin2hex(random_bytes_fallback(16));
    $createdNew = true;
  }

  // Cron token for the scheduler endpoint (/api/cron_sync.php)
  if (empty($data['settings']['cron_sync_token'])) {
    $data['settings']['cron_sync_token'] = bin2hex(random_bytes_fallback(16));
    $createdNew = true;
  }

  // Admin password: never ship with a known default.
  if (empty($data['settings']['admin_pass_hash'])) {
    $initialPassword = bin2hex(random_bytes_fallback(8)); // 16 hex chars

    $data['settings']['admin_pass_hash'] = password_hash($initialPassword, PASSWORD_DEFAULT);
    $createdNew = true;

    // Write one-time credentials file (not web-accessible; /data is denied by .htaccess)
    $credFile = $dir . '/INITIAL_ADMIN_CREDENTIALS.txt';
    $msg = "Initial admin credentials (generated " . date('c') . ")\n";
    $msg .= "Username: " . (string)$data['settings']['admin_user'] . "\n";
    $msg .= "Password: " . $initialPassword . "\n\n";
    $msg .= "IMPORTANT: Login, change username/password in /admin, then delete this file.\n";
    @file_put_contents($credFile, $msg, LOCK_EX);
    @chmod($credFile, 0640);
  }

  // Persist new store or fixes if needed
  if ($createdNew) {
    @save_store($data);
  }

  return $data;
}


function save_store($store) {
  $path = store_file_path();
  $dir = dirname($path);

  if (!is_dir($dir)) {
    if (!@mkdir($dir, 0755, true)) {
      return false;
    }
  }
  ensure_private_dir_protected($dir);

  $json = json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  if ($json === false) {
    return false;
  }

  // Backup the current store (best-effort) so we can recover from unexpected corruption.
  if (file_exists($path)) {
    $old = @file_get_contents($path);
    $oldDecoded = json_decode($old ?: '', true);
    if (is_array($oldDecoded)) {
      @file_put_contents($dir . '/store.json.bak', $old, LOCK_EX);
      @chmod($dir . '/store.json.bak', 0640);
    }
  }

  // Atomic write: write to a temp file then rename.
  $tmp = $dir . '/store.tmp.' . bin2hex(random_bytes_fallback(6)) . '.json';
  $ok = @file_put_contents($tmp, $json, LOCK_EX);
  if ($ok === false) {
    @unlink($tmp);
    return false;
  }
  @chmod($tmp, 0640);

  if (@rename($tmp, $path)) {
    @chmod($path, 0640);
    return true;
  }

  // Fallback for environments where rename() cannot replace the target.
  $ok2 = @file_put_contents($path, $json, LOCK_EX) !== false;
  @unlink($tmp);
  @chmod($path, 0640);
  return $ok2;
}


// ------------------------------------------------------------
// Verified reviews (guest-only)
// ------------------------------------------------------------

function reviews_file_path() {
  return __DIR__ . '/../data/reviews.json';
}

function load_reviews() {
  $path = reviews_file_path();
  $dir = dirname($path);

  if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
  }
  ensure_private_dir_protected($dir);

  // Create the file if missing
  if (!file_exists($path)) {
    @file_put_contents($path, "[]", LOCK_EX);
    @chmod($path, 0640);
    return [];
  }

  $raw = @file_get_contents($path);
  $data = json_decode($raw ?: '', true);

  // Second try (rare partial reads)
  if (!is_array($data)) {
    @usleep(50000);
    $raw2 = @file_get_contents($path);
    if ($raw2 !== false) {
      $data = json_decode($raw2 ?: '', true);
    }
  }

  if (!is_array($data)) {
    // Attempt recovery from backup
    $bakPath = $dir . '/reviews.json.bak';
    $bakRaw = @file_get_contents($bakPath);
    $bak = json_decode($bakRaw ?: '', true);

    // Preserve corrupted file for debugging (best-effort)
    $stamp = date('Ymd_His');
    @rename($path, $dir . '/reviews.corrupt.' . $stamp . '.json');

    if (is_array($bak)) {
      $data = $bak;
      @save_reviews($data);
    } else {
      $data = [];
    }
  }

  // Normalize
  $out = [];
  foreach ($data as $r) {
    if (is_array($r)) $out[] = $r;
  }
  return $out;
}

function save_reviews($reviews) {
  $path = reviews_file_path();
  $dir = dirname($path);

  if (!is_dir($dir)) {
    if (!@mkdir($dir, 0755, true)) {
      return false;
    }
  }
  ensure_private_dir_protected($dir);

  $json = json_encode($reviews, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  if ($json === false) return false;

  // Backup current file (best-effort)
  if (file_exists($path)) {
    $old = @file_get_contents($path);
    $oldDecoded = json_decode($old ?: '', true);
    if (is_array($oldDecoded)) {
      @file_put_contents($dir . '/reviews.json.bak', $old, LOCK_EX);
      @chmod($dir . '/reviews.json.bak', 0640);
    }
  }

  $tmp = $dir . '/reviews.tmp.' . bin2hex(random_bytes_fallback(6)) . '.json';
  $ok = @file_put_contents($tmp, $json, LOCK_EX);
  if ($ok === false) {
    @unlink($tmp);
    return false;
  }
  @chmod($tmp, 0640);

  if (@rename($tmp, $path)) {
    @chmod($path, 0640);
    return true;
  }

  $ok2 = @file_put_contents($path, $json, LOCK_EX) !== false;
  @unlink($tmp);
  @chmod($path, 0640);
  return $ok2;
}

function reviews_approved($reviews) {
  $out = [];
  foreach (($reviews ?? []) as $r) {
    if (!is_array($r)) continue;
    if ((string)($r['status'] ?? '') === 'approved') {
      $out[] = $r;
    }
  }
  return $out;
}

function reviews_stats($reviews) {
  $approved = reviews_approved($reviews);
  $count = 0;
  $sum = 0.0;
  foreach ($approved as $r) {
    $rating = isset($r['rating']) ? (int)$r['rating'] : 0;
    if ($rating < 1 || $rating > 5) continue;
    $count++;
    $sum += (float)$rating;
  }
  $avg = ($count > 0) ? round($sum / $count, 1) : 0.0;
  return ['count' => $count, 'avg' => $avg];
}

function review_default_display_name($fullName) {
  $name = normalize_text($fullName, 80, false);
  $name = trim($name);
  if ($name === '') return '';

  $parts = preg_split('/\s+/u', $name);
  if (is_array($parts) && count($parts) >= 2) {
    $first = (string)$parts[0];
    $last = (string)$parts[count($parts)-1];
    $initial = '';
    if (function_exists('mb_substr')) {
      $initial = mb_substr($last, 0, 1, 'UTF-8');
    } else {
      $initial = substr($last, 0, 1);
    }
    if ($initial !== '') {
      return $first . ' ' . $initial . '.';
    }
  }
  return $name;
}

function booking_review_token() {
  return bin2hex(random_bytes_fallback(16));
}

function booking_is_eligible_for_review($booking) {
  if (!is_array($booking)) return false;
  if ((string)($booking['status'] ?? '') !== 'booked') return false;
  $end = normalize_ymd($booking['end'] ?? '');
  if (!$end) return false;
  $today = date('Y-m-d');
  // Allow review from the checkout date onwards
  return ($end <= $today);
}

function booking_review_link($booking, $token) {
  require_once __DIR__ . '/i18n.php';

  $lang = booking_guest_lang($booking);
  $path = i18n_url('review', $lang);

  return rtrim(base_url(), '/') . $path . '?token=' . urlencode((string)$token);
}

// Sends the personal review link to the guest.
// Returns true if mail() reported success.
function maybe_send_review_invite_email(&$store, &$booking) {
  if (!is_array($booking)) return false;
  $email = trim((string)($booking['email'] ?? ''));
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return false;

  // Generate or reuse a token
  if (empty($booking['review_token'])) {
    $booking['review_token'] = booking_review_token();
    $booking['review_token_created_at'] = time();
  }

  $token = (string)$booking['review_token'];
  $link = booking_review_link($booking, $token);

  $site = (string)($store['settings']['site_name'] ?? 'Via Arriano 11');

  // Guest language (fallback: EN)
  $guestLang = booking_guest_lang($booking);
  $subject = i18n_with_lang($guestLang, function() use ($site) {
    $s = t('emails.review_invite_subject', ['site' => $site]);
    return ($s && $s !== 'emails.review_invite_subject') ? $s : ('Leave a review · ' . $site);
  });

  $guestName = trim((string)($booking['name'] ?? ''));
  $firstName = $guestName;
  if ($guestName !== '') {
    $parts = preg_split('/\s+/u', $guestName);
    if (is_array($parts) && count($parts) > 0) {
      $firstName = (string)$parts[0];
    }
  }
  $firstName = $firstName ?: 'Guest';

  $body = i18n_with_lang($guestLang, function() use ($site, $firstName, $link) {
    $tmpl = t('emails.review_invite_body', ['name' => $firstName, 'site' => $site, 'link' => $link]);
    if ($tmpl && $tmpl !== 'emails.review_invite_body') return $tmpl;
    return "Hi {$firstName},\n\nPlease leave a review:\n{$link}\n\nThanks,\n{$site}";
  });

  $replyTo = notify_email($store);
  $ok = send_mail_utf8($email, $subject, $body, $replyTo);
  if ($ok) {
    $booking['review_invited_at'] = time();
  }
  return $ok;
}


function ensure_session() {
  if (session_status() === PHP_SESSION_ACTIVE) return;

  $secure = is_https_request();

  // Harden PHP session settings (best-effort; some shared hostings may override)
  @ini_set('session.use_strict_mode', '1');
  @ini_set('session.use_only_cookies', '1');
  @ini_set('session.use_trans_sid', '0');
  @ini_set('session.cookie_httponly', '1');
  @ini_set('session.cookie_samesite', 'Lax');
  if ($secure) {
    @ini_set('session.cookie_secure', '1');
  }

  // Set cookie parameters before starting the session
  if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
    @session_set_cookie_params([
      'lifetime' => 0,
      'path' => '/',
      'secure' => $secure,
      'httponly' => true,
      'samesite' => 'Lax',
    ]);
  } else {
    // Fallback (older PHP): append SameSite in the path
    @session_set_cookie_params(0, '/; samesite=Lax', '', $secure, true);
  }

  // On some shared hostings, custom session_name helps reduce collisions.
  @session_name('ARRIANO11SESSID');
  @session_start();
}

function csrf_token() {
  ensure_session();
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes_fallback(16));
  }
  return $_SESSION['csrf_token'];
}

function csrf_verify($token) {
  ensure_session();
  return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function random_bytes_fallback($length) {
  if (function_exists('random_bytes')) {
    return random_bytes($length);
  }
  if (function_exists('openssl_random_pseudo_bytes')) {
    return openssl_random_pseudo_bytes($length);
  }
  // Worst-case fallback.
  $bytes = '';
  for ($i = 0; $i < $length; $i++) {
    $bytes .= chr(mt_rand(0, 255));
  }
  return $bytes;
}

function is_admin() {
  ensure_session();
  if (empty($_SESSION['admin_logged_in'])) return false;

  // Inactivity timeout (seconds)
  $timeout = 2 * 60 * 60; // 2 hours
  $now = time();
  $last = (int)($_SESSION['admin_last_activity'] ?? 0);

  if ($last > 0 && ($now - $last) > $timeout) {
    admin_logout();
    return false;
  }

  // Basic session binding (reduces the impact of a stolen cookie)
  $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
  $savedUa = (string)($_SESSION['admin_ua'] ?? '');
  if ($savedUa !== '' && $ua !== '' && !hash_equals($savedUa, $ua)) {
    admin_logout();
    return false;
  }

  $_SESSION['admin_last_activity'] = $now;
  return true;
}

function require_admin_password_only() {
  if (!is_admin()) {
    header('Location: /admin/login.php');
    exit;
  }
}

function admin_2fa_path() {
  return __DIR__ . '/../data/admin_2fa.json';
}

function admin_2fa_state() {
  $path = admin_2fa_path();
  if (!file_exists($path)) {
    return ['enabled' => false, 'secret' => '', 'recovery_hashes' => []];
  }
  $raw = @file_get_contents($path);
  if ($raw === false) {
    return ['enabled' => false, 'secret' => '', 'recovery_hashes' => []];
  }
  $json = json_decode($raw, true);
  if (!is_array($json)) {
    return ['enabled' => false, 'secret' => '', 'recovery_hashes' => []];
  }

  $enabled = !empty($json['enabled']);
  $secret = (string)($json['secret'] ?? '');
  $recovery = $json['recovery_hashes'] ?? [];
  if (!is_array($recovery)) $recovery = [];

  // Enabled only if a secret exists
  if ($secret === '') $enabled = false;

  return ['enabled' => $enabled, 'secret' => $secret, 'recovery_hashes' => $recovery];
}

function admin_2fa_enabled() {
  $s = admin_2fa_state();
  return !empty($s['enabled']);
}

function admin_2fa_save_state($state) {
  $path = admin_2fa_path();
  $dir = dirname($path);
  if (!is_dir($dir)) return false;

  $json = json_encode($state, JSON_UNESCAPED_SLASHES);
  if ($json === false) return false;

  $ok = @file_put_contents($path, $json, LOCK_EX);
  if ($ok === false) return false;

  @chmod($path, 0600);
  return true;
}

function admin_2fa_disable() {
  // Keep file but disable (avoids permission issues on delete)
  return admin_2fa_save_state(['enabled' => false, 'secret' => '', 'recovery_hashes' => []]);
}

function require_admin() {
  if (!is_admin()) {
    header('Location: /admin/login.php');
    exit;
  }
  if (admin_2fa_enabled() && empty($_SESSION['admin_2fa_ok'])) {
    header('Location: /admin/2fa_verify.php');
    exit;
  }
}

function admin_login($username, $password) {
  $store = load_store();
  $u = (string)($store['settings']['admin_user'] ?? 'admin');
  $hash = (string)($store['settings']['admin_pass_hash'] ?? '');

  if (!hash_equals($u, (string)$username)) {
    return false;
  }
  if (empty($hash) || !password_verify((string)$password, $hash)) {
    return false;
  }

  ensure_session();
  session_regenerate_id(true);

  $_SESSION['admin_logged_in'] = true;
  unset($_SESSION['admin_2fa_ok']);
  $_SESSION['admin_last_activity'] = time();
  $_SESSION['admin_ua'] = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');

  // Regenerate CSRF token on login
  $_SESSION['csrf_token'] = bin2hex(random_bytes_fallback(16));
  return true;
}


function admin_logout() {
  ensure_session();
  $_SESSION = [];

  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
      setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'] ?? '/',
        'domain' => $params['domain'] ?? '',
        'secure' => !empty($params['secure']),
        'httponly' => !empty($params['httponly']),
        'samesite' => $params['samesite'] ?? 'Lax',
      ]);
    } else {
      setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'], $params['secure'], $params['httponly']
      );
    }
  }

  session_destroy();
}

function new_id($prefix = '') {
  return $prefix . bin2hex(random_bytes_fallback(8));
}

function normalize_ymd($ymd) {
  // Accepts YYYY-MM-DD, returns normalized string or null.
  if (!is_string($ymd)) return null;
  $ymd = trim($ymd);
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) return null;
  $t = strtotime($ymd . ' 00:00:00');
  if ($t === false) return null;
  return date('Y-m-d', $t);
}

function add_days_ymd($ymd, $days) {
  $t = strtotime($ymd . ' 00:00:00');
  if ($t === false) return $ymd;
  return date('Y-m-d', strtotime(($days >= 0 ? '+' : '') . (int)$days . ' days', $t));
}

function ymd_to_ics($ymd) {
  return str_replace('-', '', $ymd);
}

function date_in_range($date, $start, $end_exclusive) {
  // [start, end)
  return ($date >= $start) && ($date < $end_exclusive);
}

function ranges_overlap($startA, $endA, $startB, $endB) {
  // Overlap if A starts before B ends AND B starts before A ends
  return ($startA < $endB) && ($startB < $endA);
}

function get_booking_price_for_date($store, $dateYmd) {
  $base = (float)($store['settings']['booking_base_price'] ?? 0);

  // Overrides: if date in [start, end) override booking price.
  foreach (($store['overrides'] ?? []) as $ov) {
    $s = normalize_ymd($ov['start'] ?? '');
    $e = normalize_ymd($ov['end'] ?? '');
    if (!$s || !$e) continue;
    if (date_in_range($dateYmd, $s, $e)) {
      return (float)($ov['booking_price'] ?? $base);
    }
  }
  return $base;
}

function get_direct_price_for_date($store, $dateYmd) {
  // Priority: daily override price > base_price setting > legacy Booking price * (1 - discount).
  $dateYmd = normalize_ymd($dateYmd);
  if (!$dateYmd) return 0.0;

  $daily = $store['daily'] ?? [];
  if (is_array($daily) && isset($daily[$dateYmd]) && is_array($daily[$dateYmd])) {
    $p = $daily[$dateYmd]['price'] ?? null;
    if (is_numeric($p)) {
      $p = (float)$p;
      if ($p > 0) return round($p, 2);
    }
  }

  $base = (float)($store['settings']['base_price'] ?? 0);
  if ($base > 0) {
    return round($base, 2);
  }

  // Backward compatibility
  $bookingPrice = get_booking_price_for_date($store, $dateYmd);
  $discount = (float)($store['settings']['discount'] ?? 0.0);
  if ($discount < 0) $discount = 0;
  if ($discount > 0.95) $discount = 0.95; // safety
  return round($bookingPrice * (1.0 - $discount), 2);
}

function nights_between($start, $end) {
  $ts = strtotime($start . ' 00:00:00');
  $te = strtotime($end . ' 00:00:00');
  if ($ts === false || $te === false) return 0;
  $diff = (int)(($te - $ts) / 86400);
  return max(0, $diff);
}


function base_url() {
  global $config;

  $scheme = is_https_request() ? 'https' : 'http';
  if (!empty($config['force_https'])) {
    $scheme = 'https';
  }

  $host = trusted_host();
  return $scheme . '://' . $host;
}

// ------------------------------------------------------------
// Email notifications
// ------------------------------------------------------------

function notify_email($store) {
  $to = trim((string)($store['settings']['notify_email'] ?? ''));
  if ($to === '') $to = 'viaarriano11@gmail.com';
  if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    $to = 'viaarriano11@gmail.com';
  }
  return $to;
}

function mail_from_address() {
  $host = trusted_host();
  if ($host === '') $host = 'viaarriano11.it';
  return 'noreply@' . $host;
}

function mail_encode_subject($subject) {
  $subject = (string)$subject;
  if (function_exists('mb_encode_mimeheader')) {
    return mb_encode_mimeheader($subject, 'UTF-8');
  }
  return $subject;
}

function sanitize_email_header_value($value) {
  $v = (string)$value;
  // Prevent header injection: strip CR/LF and other control chars
  $v = str_replace(["\r", "\n"], ' ', $v);
  $v = preg_replace('/[\x00-\x1F\x7F]/u', '', $v);
  return trim($v);
}


function send_mail_utf8($to, $subject, $body, $replyTo = '') {
  $to = trim((string)$to);
  if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

  $fromAddr = mail_from_address();
  $fromName = sanitize_email_header_value('Via Arriano 11');
  $subject = sanitize_email_header_value($subject);

  $headers = [];
  $headers[] = 'MIME-Version: 1.0';
  $headers[] = 'Content-Type: text/plain; charset=UTF-8';
  $headers[] = 'From: ' . $fromName . ' <' . $fromAddr . '>';
  if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
    $headers[] = 'Reply-To: ' . sanitize_email_header_value($replyTo);
  }
  $headers[] = 'X-Mailer: PHP/' . phpversion();

  return @mail($to, mail_encode_subject($subject), (string)$body, implode("\r\n", $headers));
}


function booking_notification_subject($store, $booking, $kind) {
  $site = (string)($store['settings']['site_name'] ?? 'Via Arriano 11');
  $s = (string)($booking['start'] ?? '');
  $e = (string)($booking['end'] ?? '');
  if ($kind === 'booked') {
    return 'Prenotazione confermata: ' . $s . ' → ' . $e . ' · ' . $site;
  }
  return 'Nuova richiesta prenotazione: ' . $s . ' → ' . $e . ' · ' . $site;
}

function booking_notification_body($store, $booking, $kind) {
  $site = (string)($store['settings']['site_name'] ?? 'Via Arriano 11');
  $currency = (string)($booking['currency'] ?? ($store['settings']['currency'] ?? 'EUR'));

  $lines = [];
  $lines[] = $site;
  $lines[] = str_repeat('-', 34);
  $lines[] = ($kind === 'booked')
    ? 'Nuova prenotazione CONFERMATA (pagamento ricevuto)'
    : 'Nuova richiesta di prenotazione dal sito';
  $lines[] = '';
  $lines[] = 'Periodo: ' . (string)($booking['start'] ?? '') . ' → ' . (string)($booking['end'] ?? '');
  if (isset($booking['nights'])) {
    $lines[] = 'Notti: ' . (string)($booking['nights'] ?? '');
  }
  if (isset($booking['guests'])) {
    $lines[] = 'Ospiti: ' . (string)($booking['guests'] ?? '');
  }
  $lines[] = '';
  $lines[] = 'Nome: ' . (string)($booking['name'] ?? '');
  $lines[] = 'Email: ' . (string)($booking['email'] ?? '');
  if (!empty($booking['phone'])) {
    $lines[] = 'Telefono: ' . (string)($booking['phone'] ?? '');
  }

  if (!empty($booking['message'])) {
    $lines[] = '';
    $lines[] = 'Note ospite:';
    $lines[] = (string)($booking['message'] ?? '');
  }

  // Breakdown (if available)
  $hasBreakdown = isset($booking['accommodation_total']) || isset($booking['cleaning_fee']) || isset($booking['city_tax_total']) || isset($booking['direct_total_with_fees']);
  if ($hasBreakdown) {
    $lines[] = '';
    $lines[] = '--- Costi ---';
    if (isset($booking['accommodation_total']) && is_numeric($booking['accommodation_total'])) {
      $lines[] = 'Soggiorno: ' . format_currency((float)$booking['accommodation_total'], $currency);
    } elseif (isset($booking['direct_total']) && is_numeric($booking['direct_total'])) {
      $lines[] = 'Soggiorno: ' . format_currency((float)$booking['direct_total'], $currency);
    }
    if (isset($booking['cleaning_fee']) && is_numeric($booking['cleaning_fee'])) {
      $lines[] = 'Pulizie (una tantum): ' . format_currency((float)$booking['cleaning_fee'], $currency);
    }
    if (isset($booking['city_tax_total']) && is_numeric($booking['city_tax_total'])) {
      $lines[] = 'Tassa di soggiorno: ' . format_currency((float)$booking['city_tax_total'], $currency);
    }
    if (isset($booking['direct_total_with_fees']) && is_numeric($booking['direct_total_with_fees'])) {
      $lines[] = 'Totale: ' . format_currency((float)$booking['direct_total_with_fees'], $currency);
    }
  }

  // Amount paid (caparra)
  if (isset($booking['amount_paid']) && is_numeric($booking['amount_paid'])) {
    $lines[] = '';
    $lines[] = 'Importo pagato: ' . format_currency((float)$booking['amount_paid'], $currency);
  }

  $lines[] = '';
  $lines[] = 'Admin: ' . base_url() . '/admin/index.php?tab=bookings';

  return implode("\n", $lines);
}

// Sends a notification email to the owner (one email per booking).
// Returns true if mail() reported success.
function maybe_notify_owner_booking(&$store, &$booking, $kind) {
  // Skip if already notified, or if imported from Booking
  if (!empty($booking['owner_notified_at'])) return false;
  if ((string)($booking['source'] ?? '') === 'booking') return false;

  $to = notify_email($store);
  if ($to === '') return false;

  $subject = booking_notification_subject($store, $booking, $kind);
  $body = booking_notification_body($store, $booking, $kind);
  $replyTo = (string)($booking['email'] ?? '');

  $ok = send_mail_utf8($to, $subject, $body, $replyTo);
  if ($ok) {
    $booking['owner_notified_at'] = time();
  }
  return $ok;
}

// ------------------------------------------------------------
// Paid booking emails (guest + owner)
// ------------------------------------------------------------

function format_date_it($ymd) {
  $ymd = normalize_ymd($ymd);
  if (!$ymd) return (string)$ymd;
  $dt = DateTime::createFromFormat('Y-m-d', $ymd);
  if (!$dt) return $ymd;
  return $dt->format('d/m/Y');
}

function booking_paid_calc($store, $booking) {
  $currency = (string)($booking['currency'] ?? ($store['settings']['currency'] ?? 'EUR'));

  $accommodation = 0.0;
  if (isset($booking['accommodation_total']) && is_numeric($booking['accommodation_total'])) {
    $accommodation = (float)$booking['accommodation_total'];
  } elseif (isset($booking['direct_total']) && is_numeric($booking['direct_total'])) {
    $accommodation = (float)$booking['direct_total'];
  }

  $cleaning = (isset($booking['cleaning_fee']) && is_numeric($booking['cleaning_fee'])) ? (float)$booking['cleaning_fee'] : 0.0;
  $cityTax  = (isset($booking['city_tax_total']) && is_numeric($booking['city_tax_total'])) ? (float)$booking['city_tax_total'] : 0.0;

  $total = 0.0;
  if (isset($booking['direct_total_with_fees']) && is_numeric($booking['direct_total_with_fees'])) {
    $total = (float)$booking['direct_total_with_fees'];
  } else {
    $total = $accommodation + $cleaning + $cityTax;
  }

  $depositPaid = 0.0;
  if (isset($booking['amount_paid']) && is_numeric($booking['amount_paid'])) {
    $depositPaid = (float)$booking['amount_paid'];
  } elseif (isset($booking['amount_due']) && is_numeric($booking['amount_due'])) {
    $depositPaid = (float)$booking['amount_due'];
  }
  $depositPaid = round($depositPaid, 2);

  $balance = round(max(0.0, $total - $depositPaid), 2);

  return [
    'currency' => $currency,
    'accommodation_total' => round($accommodation, 2),
    'cleaning_fee' => round($cleaning, 2),
    'city_tax_total' => round($cityTax, 2),
    'total' => round($total, 2),
    'deposit_paid' => $depositPaid,
    'balance_due' => $balance,
  ];
}


// ------------------------------------------------------------
// Paid booking email templates (guest + owner)
// Language:
// - guest email uses the booking language (fallback: EN)
// - owner email is always Italian (host preference)
// ------------------------------------------------------------

function i18n_with_lang($lang, $fn) {
  // Lazy-load i18n helpers
  require_once __DIR__ . '/i18n.php';

  $prevLang = $GLOBALS['_VIA_LANG'] ?? null;
  $prevDict = $GLOBALS['_VIA_I18N'] ?? null;

  i18n_init($lang);
  $result = $fn();

  // Restore previous state (best-effort)
  if ($prevLang === null) {
    unset($GLOBALS['_VIA_LANG']);
  } else {
    $GLOBALS['_VIA_LANG'] = $prevLang;
  }
  if ($prevDict === null) {
    unset($GLOBALS['_VIA_I18N']);
  } else {
    $GLOBALS['_VIA_I18N'] = $prevDict;
  }

  return $result;
}

function booking_guest_lang($booking) {
  require_once __DIR__ . '/i18n.php';
  $supported = i18n_supported_languages();

  $raw = (string)($booking['lang'] ?? '');
  $norm = i18n_normalize_lang($raw);
  if ($norm && isset($supported[$norm])) return $norm;

  // If not set or unsupported, fall back to English (tourism-friendly default)
  return 'en';
}

function booking_paid_subject_guest($store, $booking) {
  $site = (string)($store['settings']['site_name'] ?? 'Via Arriano 11');
  return t('emails.guest_subject', ['site' => $site]);
}

function booking_paid_subject_owner($store, $booking) {
  $site = (string)($store['settings']['site_name'] ?? 'Via Arriano 11');
  return t('emails.owner_subject', ['site' => $site]);
}

function booking_paid_body_guest($store, $booking) {
  $calc = booking_paid_calc($store, $booking);
  $cur = $calc['currency'];

  $name = trim((string)($booking['name'] ?? ''));
  if ($name === '') $name = 'Guest';

  $start = (string)($booking['start'] ?? '');
  $end   = (string)($booking['end'] ?? '');

  $lines = [];
  $lines[] = t('emails.guest_greeting', ['name' => $name]);
  $lines[] = '';
  $lines[] = t('emails.guest_thanks');
  $lines[] = t('emails.guest_dates', ['start' => $start, 'end' => $end]);
  $lines[] = t('emails.guest_address');
  $lines[] = '';
  $lines[] = t('emails.guest_breakdown');
  $lines[] = t('emails.lines.nights')   . ': ' . format_currency($calc['accommodation_total'], $cur);
  $lines[] = t('emails.lines.cleaning') . ': ' . format_currency($calc['cleaning_fee'], $cur);
  $lines[] = t('emails.lines.city_tax') . ': ' . format_currency($calc['city_tax_total'], $cur);
  $lines[] = t('emails.lines.total')    . ': ' . format_currency($calc['total'], $cur);
  $lines[] = '';
  $lines[] = t('emails.lines.deposit')  . ': ' . format_currency($calc['deposit_paid'], $cur);
  $lines[] = t('emails.lines.balance')  . ': ' . format_currency($calc['balance_due'], $cur);
  $lines[] = '';
  $lines[] = t('emails.closing');

  return implode("\n", $lines);
}

function booking_paid_body_owner($store, $booking) {
  $calc = booking_paid_calc($store, $booking);
  $cur = $calc['currency'];

  $name = trim((string)($booking['name'] ?? ''));
  if ($name === '') $name = 'Un ospite';

  $start = (string)($booking['start'] ?? '');
  $end   = (string)($booking['end'] ?? '');
  $guests = (int)($booking['guests'] ?? 0);
  $guestsText = ($guests > 0) ? ($guests . ' ' . strtolower((string)t('booking.labels.guests'))) : '';

  $lines = [];
  $lines[] = t('emails.owner_title');
  $lines[] = t('emails.owner_line', ['name' => $name, 'start' => $start, 'end' => $end, 'guests' => $guestsText]);
  $lines[] = '';
  $lines[] = t('emails.guest_breakdown');
  $lines[] = t('emails.lines.nights')   . ': ' . format_currency($calc['accommodation_total'], $cur);
  $lines[] = t('emails.lines.cleaning') . ': ' . format_currency($calc['cleaning_fee'], $cur);
  $lines[] = t('emails.lines.city_tax') . ': ' . format_currency($calc['city_tax_total'], $cur);
  $lines[] = t('emails.lines.total')    . ': ' . format_currency($calc['total'], $cur);
  $lines[] = '';
  $lines[] = t('emails.lines.deposit')  . ': ' . format_currency($calc['deposit_paid'], $cur);
  $lines[] = t('emails.lines.balance')  . ': ' . format_currency($calc['balance_due'], $cur);

  // Guest contacts (useful for host)
  if (!empty($booking['email'])) {
    $lines[] = '';
    $lines[] = 'Email ospite: ' . (string)$booking['email'];
  }
  if (!empty($booking['phone'])) {
    $lines[] = 'Telefono ospite: ' . (string)$booking['phone'];
  }

  return implode("\n", $lines);
}

// Sends the "paid" emails (guest + owner). Idempotent.
// Returns true if at least one email has been sent.
function maybe_send_paid_booking_emails(&$store, &$booking) {
  // Only for paid direct bookings
  if ((string)($booking['source'] ?? '') === 'booking') return false;
  if ((string)($booking['status'] ?? '') !== 'booked') return false;

  // Require a positive amount paid to consider it a payment-confirmed booking.
  $amountPaid = isset($booking['amount_paid']) && is_numeric($booking['amount_paid'])
    ? (float)$booking['amount_paid']
    : 0.0;
  if ($amountPaid <= 0) return false;

  $sentAny = false;

  $ownerTo = notify_email($store);
  $guestTo = trim((string)($booking['email'] ?? ''));

  // Guest email (localized)
  if (empty($booking['guest_paid_email_sent_at']) && $guestTo !== '' && filter_var($guestTo, FILTER_VALIDATE_EMAIL)) {
    $guestLang = booking_guest_lang($booking);

    $ok = i18n_with_lang($guestLang, function() use (&$store, &$booking, $guestTo, $ownerTo) {
      $sub = booking_paid_subject_guest($store, $booking);
      $body = booking_paid_body_guest($store, $booking);
      // Reply-To to the host/owner mailbox
      $reply = ($ownerTo !== '' && filter_var($ownerTo, FILTER_VALIDATE_EMAIL)) ? $ownerTo : '';
      return send_mail_utf8($guestTo, $sub, $body, $reply);
    });

    if ($ok) {
      $booking['guest_paid_email_sent_at'] = time();
      $sentAny = true;
    }
  }

  // Owner email (always Italian)
  if (empty($booking['owner_paid_email_sent_at']) && $ownerTo !== '' && filter_var($ownerTo, FILTER_VALIDATE_EMAIL)) {
    $ok = i18n_with_lang('it', function() use (&$store, &$booking, $guestTo, $ownerTo) {
      $sub = booking_paid_subject_owner($store, $booking);
      $body = booking_paid_body_owner($store, $booking);
      // Reply-To to the guest
      $reply = ($guestTo !== '' && filter_var($guestTo, FILTER_VALIDATE_EMAIL)) ? $guestTo : '';
      return send_mail_utf8($ownerTo, $sub, $body, $reply);
    });

    if ($ok) {
      $booking['owner_paid_email_sent_at'] = time();
      $sentAny = true;
    }
  }

  return $sentAny;
}

function prune_expired_payment_holds(&$store) {
  $now = time();
  $before = count($store['bookings'] ?? []);
  $store['bookings'] = array_values(array_filter(($store['bookings'] ?? []), function($b) use ($now) {
    $st = (string)($b['status'] ?? '');
    if ($st !== 'payment_pending') return true;
    $exp = (int)($b['expires_at'] ?? 0);
    if ($exp > 0 && $exp < $now) {
      return false;
    }
    return true;
  }));
  return count($store['bookings'] ?? []) !== $before;
}

function get_min_nights_for_arrival($store, $arrivalYmd) {
  $arrivalYmd = normalize_ymd($arrivalYmd);
  if (!$arrivalYmd) return (int)($store['settings']['min_nights'] ?? 1);

  $daily = $store['daily'] ?? [];
  if (is_array($daily) && isset($daily[$arrivalYmd]) && is_array($daily[$arrivalYmd])) {
    $mn = $daily[$arrivalYmd]['min_nights'] ?? null;
    if (is_numeric($mn)) {
      $mn = (int)$mn;
      if ($mn >= 1) return $mn;
    }
  }

  return (int)($store['settings']['min_nights'] ?? 1);
}

function normalize_guests($guests, $default = 2) {
  if ($guests === null || $guests === '') return (int)$default;
  if (!is_numeric($guests)) return null;
  $g = (int)$guests;
  if ($g < 1 || $g > 3) return null;
  return $g;
}

function occupancy_multiplier($guests) {
  $g = (int)$guests;
  if ($g === 1) return 0.90;
  if ($g === 3) return 1.10;
  return 1.00;
}

function get_city_tax_per_person_per_night($store) {
  $v = $store['settings']['city_tax_per_person_per_night'] ?? 6;
  if (!is_numeric($v)) return 6.0;
  $v = (float)$v;
  if ($v < 0) $v = 0.0;
  return $v;
}

function quote_stay($store, $start, $end, $guests = 2) {
  $start = normalize_ymd($start);
  $end = normalize_ymd($end);
  if (!$start || !$end) {
    return ['ok' => false, 'error' => 'Date non valide.'];
  }
  if ($end <= $start) {
    return ['ok' => false, 'error' => 'La data di check-out deve essere successiva al check-in.'];
  }

  $g = normalize_guests($guests, 2);
  if ($g === null) {
    return ['ok' => false, 'error' => 'Numero ospiti non valido.'];
  }

  $nights = nights_between($start, $end);
  $minNights = get_min_nights_for_arrival($store, $start);
  if ($nights < $minNights) {
    return ['ok' => false, 'error' => 'Soggiorno minimo: ' . $minNights . ' notti.'];
  }

  $mult = occupancy_multiplier($g);

  $bookingTotal = 0.0;
  $directTotal = 0.0;
  $cursor = $start;
  while ($cursor < $end) {
    $bookingTotal += round(get_booking_price_for_date($store, $cursor) * $mult, 2);
    $directTotal += round(get_direct_price_for_date($store, $cursor) * $mult, 2);
    $cursor = add_days_ymd($cursor, 1);
  }

  $cleaning = (float)($store['settings']['cleaning_fee'] ?? 0);
  if ($cleaning < 0) $cleaning = 0;

  $cityTaxPppn = get_city_tax_per_person_per_night($store);
  $cityTaxTotal = round($cityTaxPppn * $g * $nights, 2);

  $bookingTotalWithFees = round($bookingTotal + $cleaning + $cityTaxTotal, 2);
  $directTotalWithFees = round($directTotal + $cleaning + $cityTaxTotal, 2);

  return [
    'ok' => true,
    'start' => $start,
    'end' => $end,
    'nights' => $nights,
    'guests' => $g,
    'occupancy_multiplier' => $mult,
    'booking_total' => round($bookingTotal, 2),
    'direct_total' => round($directTotal, 2),
    'booking_total_with_fees' => $bookingTotalWithFees,
    'direct_total_with_fees' => $directTotalWithFees,
    'cleaning_fee' => round($cleaning, 2),
    'city_tax_per_person_per_night' => round($cityTaxPppn, 2),
    'city_tax_total' => $cityTaxTotal,
    'currency' => (string)($store['settings']['currency'] ?? 'EUR'),
    'discount' => (float)($store['settings']['discount'] ?? 0.0)
  ];
}

function day_slots_for_day($store, $dateYmd) {
  $dateYmd = normalize_ymd($dateYmd);
  if (!$dateYmd) {
    return ['status' => 'available', 'can_checkin' => true, 'can_checkout' => true, 'is_stay' => false];
  }

  // Close past days: no bookings in the past (local timezone)
  $today = date('Y-m-d');
  if ($dateYmd < $today) {
    return ['status' => 'closed', 'can_checkin' => false, 'can_checkout' => false, 'is_stay' => false];
  }

  // Daily override (closed=true or forced-open closed=false)
  $daily = $store['daily'] ?? [];
  $dailyClosed = null;
  if (is_array($daily) && isset($daily[$dateYmd]) && is_array($daily[$dateYmd]) && array_key_exists('closed', $daily[$dateYmd])) {
    $v = $daily[$dateYmd]['closed'];
    if ($v === true || $v === false) {
      $dailyClosed = $v;
    }
  }

  if ($dailyClosed === true) {
    return ['status' => 'closed', 'can_checkin' => false, 'can_checkout' => false, 'is_stay' => false];
  }

  // Blocks apply unless the day is explicitly forced open (closed=false)
  if ($dailyClosed !== false) {
    foreach (($store['blocks'] ?? []) as $b) {
      $s = normalize_ymd($b['start'] ?? '');
      $e = normalize_ymd($b['end'] ?? '');
      if ($s && $e && date_in_range($dateYmd, $s, $e)) {
        return ['status' => 'closed', 'can_checkin' => false, 'can_checkout' => false, 'is_stay' => false];
      }
    }
  }

  $canCheckin = true;
  $canCheckout = true;
  $isStay = false; // true only for days strictly inside a booking (between start and end)

  $now = time();
  foreach (($store['bookings'] ?? []) as $bk) {
    $status = (string)($bk['status'] ?? '');
    if (!in_array($status, ['booked', 'pending', 'payment_pending'], true)) continue;

    // Skip expired payment holds
    if ($status === 'payment_pending') {
      $exp = (int)($bk['expires_at'] ?? 0);
      if ($exp > 0 && $exp < $now) {
        continue;
      }
    }

    $s = normalize_ymd($bk['start'] ?? '');
    $e = normalize_ymd($bk['end'] ?? '');
    if (!$s || !$e || $e <= $s) continue;

    // Day of arrival: occupies check-in slot
    if ($dateYmd === $s) {
      $canCheckin = false;
    }

    // Days in between: occupies the full day
    if ($dateYmd > $s && $dateYmd < $e) {
      $canCheckin = false;
      $canCheckout = false;
      $isStay = true;
    }

    // Day of departure: occupies check-out slot
    if ($dateYmd === $e) {
      $canCheckout = false;
    }

    if (!$canCheckin && !$canCheckout) {
      // Fully blocked, no need to keep scanning
      break;
    }
  }

  $status = 'available';
  if (!$canCheckin && !$canCheckout) {
    $status = 'booked';
  } elseif ($canCheckin && $canCheckout) {
    $status = 'available';
  } else {
    $status = 'turnover';
  }

  return ['status' => $status, 'can_checkin' => $canCheckin, 'can_checkout' => $canCheckout, 'is_stay' => $isStay];
}

function date_status_for_day($store, $dateYmd) {
  $slots = day_slots_for_day($store, $dateYmd);
  return (string)($slots['status'] ?? 'available');
}

function availability_for_month($store, $monthKey) {
  // monthKey: YYYY-MM
  if (!preg_match('/^\d{4}-\d{2}$/', (string)$monthKey)) {
    return ['ok' => false, 'error' => 'Formato mese non valido.'];
  }

  $first = $monthKey . '-01';
  $t = strtotime($first . ' 00:00:00');
  if ($t === false) {
    return ['ok' => false, 'error' => 'Mese non valido.'];
  }

  $daysInMonth = (int)date('t', $t);
  $days = [];
  for ($d = 1; $d <= $daysInMonth; $d++) {
    $ymd = sprintf('%s-%02d', $monthKey, $d);
    $slots = day_slots_for_day($store, $ymd);
    $days[$ymd] = [
      'status' => (string)($slots['status'] ?? 'available'),
      'can_checkin' => !empty($slots['can_checkin']),
      'can_checkout' => !empty($slots['can_checkout']),
      'is_stay' => !empty($slots['is_stay']),
      'price' => get_direct_price_for_date($store, $ymd),
      'min_nights' => get_min_nights_for_arrival($store, $ymd)
    ];
  }

  return [
    'ok' => true,
    'month' => $monthKey,
    'days' => $days,
    'currency' => (string)($store['settings']['currency'] ?? 'EUR'),
    'min_nights' => (int)($store['settings']['min_nights'] ?? 1)
  ];
}

function is_range_available_for_request($store, $start, $end) {
  $start = normalize_ymd($start);
  $end = normalize_ymd($end);
  if (!$start || !$end) return false;
  if ($end <= $start) return false;

  // Check-in day must allow check-in
  $s = day_slots_for_day($store, $start);
  if (empty($s['can_checkin']) || ($s['status'] ?? '') === 'closed') {
    return false;
  }

  // Check-out day must allow check-out
  $e = day_slots_for_day($store, $end);
  if (empty($e['can_checkout']) || ($e['status'] ?? '') === 'closed') {
    return false;
  }

  // Days in between must be fully available (no arrivals/departures inside stay)
  $cursor = add_days_ymd($start, 1);
  while ($cursor < $end) {
    $d = day_slots_for_day($store, $cursor);
    if (($d['status'] ?? '') !== 'available' || empty($d['can_checkin']) || empty($d['can_checkout'])) {
      return false;
    }
    $cursor = add_days_ymd($cursor, 1);
  }

  return true;
}

function maybe_auto_sync(&$store, $force = false) {
  $url = trim((string)($store['settings']['booking_ical_url'] ?? ''));
  if ($url === '') return;

  // Default: sync every 5 minutes (as requested). Can be increased from settings.
  $interval = (int)($store['settings']['sync_interval_minutes'] ?? 5);
  if ($interval < 5) $interval = 5;

  if (!$force) {
    $last = (int)($store['settings']['last_sync'] ?? 0);
    $now = time();
    if ($last > 0 && ($now - $last) < ($interval * 60)) {
      return;
    }
  }

  $res = sync_from_booking($store);
  if (is_array($res) && isset($res[0]) && $res[0] === true) {
    save_store($store);
  }
}




function is_public_ip_address($ip) {
  $ip = trim((string)$ip);
  if ($ip === '') return false;
  return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
}

function resolve_host_ips($host) {
  $host = trim((string)$host);
  if ($host === '') return [];

  // If it's already an IP, return it.
  if (filter_var($host, FILTER_VALIDATE_IP)) return [$host];

  $ips = [];

  if (function_exists('dns_get_record')) {
    $recs = @dns_get_record($host, DNS_A + DNS_AAAA);
    if (is_array($recs)) {
      foreach ($recs as $r) {
        if (!empty($r['ip']) && filter_var($r['ip'], FILTER_VALIDATE_IP)) {
          $ips[] = $r['ip'];
        }
        if (!empty($r['ipv6']) && filter_var($r['ipv6'], FILTER_VALIDATE_IP)) {
          $ips[] = $r['ipv6'];
        }
      }
    }
  }

  if (empty($ips) && function_exists('gethostbynamel')) {
    $arr = @gethostbynamel($host);
    if (is_array($arr)) {
      foreach ($arr as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP)) $ips[] = $ip;
      }
    }
  }

  if (empty($ips)) {
    $ip = @gethostbyname($host);
    if ($ip && $ip !== $host && filter_var($ip, FILTER_VALIDATE_IP)) {
      $ips[] = $ip;
    }
  }

  $ips = array_values(array_unique($ips));
  return $ips;
}

function validate_outbound_url($url) {
  global $config;

  $url = trim((string)$url);
  if ($url === '') return [false, 'URL mancante.'];

  $parts = @parse_url($url);
  if (!is_array($parts)) return [false, 'URL non valida.'];

  $scheme = strtolower((string)($parts['scheme'] ?? ''));
  if ($scheme !== 'http' && $scheme !== 'https') {
    return [false, 'Schema URL non supportato (solo http/https).'];
  }

  if ($scheme === 'http' && empty($config['allow_insecure_ical_http'])) {
    return [false, 'Per sicurezza sono accettati solo feed iCal in https.'];
  }

  if (!empty($parts['user']) || !empty($parts['pass'])) {
    return [false, 'Credenziali nell’URL non consentite.'];
  }

  $host = sanitize_host((string)($parts['host'] ?? ''));
  if ($host === '') return [false, 'Host mancante nell’URL.'];

  // Block common internal hostnames
  if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
    return [false, 'Host non consentito.'];
  }

  // Allow only standard ports (defence-in-depth)
  $port = isset($parts['port']) ? (int)$parts['port'] : 0;
  if ($port !== 0 && !in_array($port, [80, 443], true)) {
    return [false, 'Porta non consentita.'];
  }

  $ips = resolve_host_ips($host);
  if (empty($ips)) return [false, 'Impossibile risolvere l’host dell’URL.'];

  foreach ($ips as $ip) {
    if (!is_public_ip_address($ip)) {
      return [false, 'URL non consentita (destinazione privata/non instradabile).'];
    }
  }

  return [true, ''];
}

function resolve_url($baseUrl, $location) {
  $location = trim((string)$location);
  if ($location === '') return '';

  // Protocol-relative URL (//example.com/path)
  if (strpos($location, '//') === 0) {
    $bp = @parse_url($baseUrl);
    $scheme = strtolower((string)($bp['scheme'] ?? 'https'));
    return $scheme . ':' . $location;
  }

  // Absolute URL
  if (preg_match('~^[a-zA-Z][a-zA-Z0-9+.-]*://~', $location)) {
    return $location;
  }

  $bp = @parse_url($baseUrl);
  if (!is_array($bp)) return $location;

  $scheme = (string)($bp['scheme'] ?? 'https');
  $host = (string)($bp['host'] ?? '');
  $port = isset($bp['port']) ? (int)$bp['port'] : 0;
  $path = (string)($bp['path'] ?? '/');

  // Location might be just a query string
  if (strpos($location, '?') === 0) {
    $location = $path . $location;
  }

  // Fragment-only
  if (strpos($location, '#') === 0) {
    $location = $path . $location;
  }

  // Build new path
  if (strpos($location, '/') === 0) {
    $new = $location;
  } else {
    $dir = preg_replace('~/[^/]*$~', '/', $path);
    $new = $dir . $location;
  }

  // Split fragment and query
  $frag = '';
  if (strpos($new, '#') !== false) {
    [$new, $f] = explode('#', $new, 2);
    $frag = '#' . $f;
  }
  $query = '';
  if (strpos($new, '?') !== false) {
    [$new, $q] = explode('?', $new, 2);
    $query = '?' . $q;
  }

  // Normalize path segments (.. and .)
  $segments = [];
  foreach (explode('/', $new) as $seg) {
    if ($seg === '' || $seg === '.') continue;
    if ($seg === '..') {
      array_pop($segments);
      continue;
    }
    $segments[] = $seg;
  }
  $normPath = '/' . implode('/', $segments);
  if ($new !== '/' && substr($new, -1) === '/') {
    $normPath .= '/';
  }

  $out = $scheme . '://' . $host;
  if ($port) {
    $defaultPort = ($scheme === 'https') ? 443 : 80;
    if ($port !== $defaultPort) $out .= ':' . $port;
  }
  return $out . $normPath . $query . $frag;
}

function fetch_url($url) {
  global $config;

  $url = trim((string)$url);
  if ($url === '') return [false, 'URL mancante.'];

  $timeout = (int)($config['curl_timeout_seconds'] ?? 10);
  if ($timeout < 5) $timeout = 5;
  if ($timeout > 30) $timeout = 30;

  $maxBytes = 1024 * 1024 * 2; // 2MB safety limit
  $ua = 'ViaArriano11/1.0 (+https://' . trusted_host() . ')';

  // Validate the initial URL
  [$ok, $err] = validate_outbound_url($url);
  if (!$ok) return [false, $err];

  // Fallback without cURL: do not follow redirects (safer)
  if (!function_exists('curl_init')) {
    $ctx = stream_context_create([
      'http' => [
        'timeout' => $timeout,
        'follow_location' => 0,
        'max_redirects' => 0,
        'user_agent' => $ua,
      ],
    ]);
    $data = @file_get_contents($url, false, $ctx);
    if ($data === false) return [false, 'Impossibile scaricare l’URL.'];
    if (strlen($data) > $maxBytes) return [false, 'Risposta troppo grande.'];
    return [true, $data];
  }

  $current = $url;
  $redirects = 0;
  $maxRedirects = 5;

  while (true) {
    [$ok2, $err2] = validate_outbound_url($current);
    if (!$ok2) return [false, $err2];

    $ch = curl_init();
    if ($ch === false) return [false, 'Impossibile inizializzare cURL.'];

    $downloaded = 0;

    curl_setopt($ch, CURLOPT_URL, $current);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(10, $timeout));
    curl_setopt($ch, CURLOPT_USERAGENT, $ua);

    // Restrict protocols (defence-in-depth)
    if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
      curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
    }
    if (defined('CURLOPT_REDIR_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
      curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
    }

    // TLS verification
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    // Hard limit on response size
    curl_setopt($ch, CURLOPT_NOPROGRESS, false);
    curl_setopt(
      $ch,
      CURLOPT_PROGRESSFUNCTION,
      function ($resource, $dltotal, $dlnow, $ultotal, $ulnow) use (&$downloaded, $maxBytes) {
        $downloaded = (int)$dlnow;
        if ($downloaded > $maxBytes) return 1;
        return 0;
      }
    );

    $resp = curl_exec($ch);
    $errno = curl_errno($ch);
    $errstr = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $primaryIp = (string)curl_getinfo($ch, CURLINFO_PRIMARY_IP);
    curl_close($ch);

    // Defence-in-depth: prevent DNS rebinding / internal network access.
    if ($primaryIp !== '' && !is_public_ip_address($primaryIp)) {
      return [false, 'URL non consentita (destinazione privata/non instradabile).'];
    }

    if ($resp === false || $errno) {
      $msg = $errstr ?: 'Errore sconosciuto.';
      return [false, 'Errore download: ' . $msg];
    }

    $headers = substr($resp, 0, $headerSize);
    $body = substr($resp, $headerSize);

    if ($downloaded > $maxBytes || strlen($body) > $maxBytes) {
      return [false, 'Risposta troppo grande.'];
    }

    // Manual redirect handling (we validate each hop)
    if ($status >= 300 && $status < 400) {
      if ($redirects >= $maxRedirects) {
        return [false, 'Troppi redirect.'];
      }

      $loc = '';
      if (preg_match('/^Location:\s*(.+)$/im', $headers, $m)) {
        $loc = trim($m[1]);
      }
      if ($loc === '') return [false, 'Redirect senza Location.'];

      $next = resolve_url($current, $loc);
      if ($next === '') return [false, 'Redirect non valido.'];

      $current = $next;
      $redirects++;
      continue;
    }

    if ($status < 200 || $status >= 300) {
      return [false, 'Risposta HTTP non valida: ' . $status];
    }

    return [true, $body];
  }
}



function unfold_ical_lines($ics) {
  $ics = str_replace("\r\n", "\n", (string)$ics);
  $lines = explode("\n", $ics);
  $out = [];
  foreach ($lines as $line) {
    if ($line === '') continue;
    // Line folding: if a line starts with space or tab, it continues previous line.
    if (!empty($out) && (isset($line[0]) && ($line[0] === ' ' || $line[0] === "\t"))) {
      $out[count($out) - 1] .= substr($line, 1);
    } else {
      $out[] = trim($line);
    }
  }
  return $out;
}

function ical_date_to_ymd($value) {
  // Accepts 20250101 or 20250101T120000Z, returns YYYY-MM-DD
  $value = trim((string)$value);
  if (preg_match('/^(\d{4})(\d{2})(\d{2})/', $value, $m)) {
    return $m[1] . '-' . $m[2] . '-' . $m[3];
  }
  return null;
}

function parse_ical_events($ics) {
  $lines = unfold_ical_lines($ics);
  $events = [];
  $inEvent = false;
  $cur = [];

  foreach ($lines as $line) {
    if (strtoupper($line) === 'BEGIN:VEVENT') {
      $inEvent = true;
      $cur = [];
      continue;
    }
    if (strtoupper($line) === 'END:VEVENT') {
      if ($inEvent) {
        $uid = $cur['UID'] ?? null;
        $dtstart = $cur['DTSTART'] ?? null;
        $dtend = $cur['DTEND'] ?? null;

        $start = $dtstart ? ical_date_to_ymd($dtstart) : null;
        $end = $dtend ? ical_date_to_ymd($dtend) : null;
        if ($start) {
          if (!$end) {
            $end = add_days_ymd($start, 1);
          }
          $events[] = [
            'uid' => $uid ?: ('no-uid-' . md5($start . '|' . $end)),
            'start' => $start,
            'end' => $end
          ];
        }
      }
      $inEvent = false;
      $cur = [];
      continue;
    }

    if (!$inEvent) continue;

    // Split key/value
    $pos = strpos($line, ':');
    if ($pos === false) continue;
    $left = substr($line, 0, $pos);
    $value = substr($line, $pos + 1);

    // Remove params (e.g. DTSTART;VALUE=DATE)
    $key = strtoupper(explode(';', $left)[0]);
    if (in_array($key, ['UID', 'DTSTART', 'DTEND'], true)) {
      $cur[$key] = $value;
    }
  }

  return $events;
}

function sync_from_booking(&$store) {
  $url = trim((string)($store['settings']['booking_ical_url'] ?? ''));
  if ($url === '') {
    return [false, 'URL iCal Booking.com non impostato.'];
  }

  list($ok, $bodyOrError) = fetch_url($url);
  if (!$ok) {
    return [false, $bodyOrError];
  }

  $ics = (string)$bodyOrError;
  $events = parse_ical_events($ics);

  // Remove previous booking-source imports.
  $clean = [];
  foreach (($store['bookings'] ?? []) as $bk) {
    if (($bk['source'] ?? '') !== 'booking') {
      $clean[] = $bk;
    }
  }

  foreach ($events as $ev) {
    $clean[] = [
      'id' => 'booking-' . md5($ev['uid']),
      'start' => $ev['start'],
      'end' => $ev['end'],
      'status' => 'booked',
      'source' => 'booking',
      'external_uid' => $ev['uid'],
      'created_at' => time()
    ];
  }

  $store['bookings'] = $clean;
  $store['settings']['last_sync'] = time();

  return [true, count($events)];
}

function build_ics_from_store($store) {
  // Export our busy/closed dates as an iCalendar feed.
  // Use ZapCalLib to generate strict and well-formed iCalendar for channels like Booking.

  require_once __DIR__ . '/zapcal/zapcallib.php';

  $ical = new ZCiCal();

  // Override default PRODID (ZapCalLib sets its own by default)
  $ical->curnode->data['PRODID'] = new ZCiCalDataNode('PRODID:-//ViaArriano11//Calendar//IT');
  $ical->curnode->data['CALSCALE'] = new ZCiCalDataNode('CALSCALE:GREGORIAN');
  $ical->curnode->data['METHOD'] = new ZCiCalDataNode('METHOD:PUBLISH');
  $ical->curnode->addNode(new ZCiCalDataNode('X-WR-CALNAME:Via Arriano 11'));
  $ical->curnode->addNode(new ZCiCalDataNode('X-WR-TIMEZONE:Europe/Rome'));

  $now = gmdate('Ymd\THis\Z');
  $hasEvent = false;

  $addEvent = function($uid, $s, $e, $summary) use ($ical, $now, &$hasEvent) {
    $s = normalize_ymd($s);
    $e = normalize_ymd($e);
    if (!$s || !$e || $e <= $s) return;

    $node = new ZCiCalNode('VEVENT', $ical->curnode);
    $node->addNode(new ZCiCalDataNode('UID:' . $uid));
    $node->addNode(new ZCiCalDataNode('DTSTAMP:' . $now));
    $node->addNode(new ZCiCalDataNode('DTSTART;VALUE=DATE:' . ymd_to_ics($s)));
    $node->addNode(new ZCiCalDataNode('DTEND;VALUE=DATE:' . ymd_to_ics($e)));
    $node->addNode(new ZCiCalDataNode('SUMMARY:' . $summary));
    $hasEvent = true;
  };

  // Export confirmed bookings (and pending holds) as busy events.
  foreach (($store['bookings'] ?? []) as $bk) {
    $status = (string)($bk['status'] ?? '');
    if (!in_array($status, ['booked', 'pending', 'payment_pending'], true)) continue;

    // Skip expired payment holds
    if ($status === 'payment_pending') {
      $exp = (int)($bk['expires_at'] ?? 0);
      if ($exp > 0 && $exp < time()) {
        continue;
      }
    }

    $s = normalize_ymd($bk['start'] ?? '');
    $e = normalize_ymd($bk['end'] ?? '');
    if (!$s || !$e || $e <= $s) continue;

    $uidBase = (string)($bk['id'] ?? '') ?: ('bk-' . md5($s . '|' . $e));
    $uid = $uidBase . '@viaarriano11.it';
    $addEvent($uid, $s, $e, 'Busy');
  }

  // Export manual blocks (e.g. maintenance)
  foreach (($store['blocks'] ?? []) as $b) {
    $s = normalize_ymd($b['start'] ?? '');
    $e = normalize_ymd($b['end'] ?? '');
    if (!$s || !$e || $e <= $s) continue;

    $uidBase = (string)($b['id'] ?? '') ?: ('blk-' . md5($s . '|' . $e));
    $uid = $uidBase . '@viaarriano11.it';
    $addEvent($uid, $s, $e, 'Closed');
  }

  // Export daily closures as busy dates (grouped into ranges)
  $daily = $store['daily'] ?? [];
  if (is_array($daily) && !empty($daily)) {
    $closedDays = [];
    foreach ($daily as $ymd => $info) {
      $ymd = normalize_ymd($ymd);
      if (!$ymd) continue;
      if (is_array($info) && isset($info['closed']) && $info['closed'] === true) {
        $closedDays[] = $ymd;
      }
    }

    sort($closedDays);

    $ranges = [];
    $curStart = null;
    $prev = null;
    foreach ($closedDays as $d) {
      if ($curStart === null) {
        $curStart = $d;
        $prev = $d;
        continue;
      }
      $next = add_days_ymd($prev, 1);
      if ($d === $next) {
        $prev = $d;
      } else {
        $ranges[] = [$curStart, add_days_ymd($prev, 1)];
        $curStart = $d;
        $prev = $d;
      }
    }
    if ($curStart !== null) {
      $ranges[] = [$curStart, add_days_ymd($prev, 1)];
    }

    $i = 0;
    foreach ($ranges as $r) {
      $i++;
      $s = $r[0];
      $e = $r[1];
      $uid = 'daily-closed-' . $i . '-' . md5($s . '|' . $e) . '@viaarriano11.it';
      $addEvent($uid, $s, $e, 'Closed');
    }
  }

  // If there are no events, export an empty VCALENDAR.
  // NOTE: Some channels (incl. Booking in some cases) reject a feed with no VEVENT.
  // To keep the feed always parseable without impacting future availability, we
  // add a single "seed" event in the past.

  if (!$hasEvent) {
    $seedStart = date('Y-m-d', strtotime('-1 day'));
    $seedEnd = date('Y-m-d'); // exclusive
    $uid = 'seed-' . md5($seedStart . '|' . $seedEnd) . '@viaarriano11.it';
    $addEvent($uid, $seedStart, $seedEnd, 'Calendar active');
  }

  return $ical->export();
}

function format_currency($amount, $currency) {
  $amount = (float)$amount;
  $currency = strtoupper((string)$currency);
  $symbol = '€';
  if ($currency === 'EUR') $symbol = '€';
  if ($currency === 'USD') $symbol = '$';

  // Basic formatting (Italian style)
  return $symbol . ' ' . number_format($amount, 2, ',', '.');
}

// Compact currency for tight calendar cells (mobile portrait).
// Example: "€120" instead of "€ 120,00".
function format_currency_compact($amount, $currency) {
  $amount = (float)$amount;
  $currency = strtoupper((string)$currency);
  $symbol = '€';
  if ($currency === 'EUR') $symbol = '€';
  if ($currency === 'USD') $symbol = '$';

  $rounded = round($amount, 2);
  $isInt = abs($rounded - round($rounded)) < 0.001;
  if ($isInt) {
    return $symbol . (string)round($rounded);
  }

  // Keep up to 2 decimals; strip trailing zeros.
  $s = number_format($rounded, 2, ',', '.');
  $s = rtrim(rtrim($s, '0'), ',');
  return $symbol . $s;
}

function current_year() {
  return date('Y');
}

// Optional module: motore prezzi (admin)
// Keeps the public site clean: if the file is missing, nothing breaks.
$__pe_file = __DIR__ . '/pricing_engine.php';
if (is_file($__pe_file)) {
  require_once $__pe_file;
}

?>
