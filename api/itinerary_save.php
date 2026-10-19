<?php
// Saves an itinerary built on /itinerari/roma-N-giorni and returns its code (patch 10, Oct 2026).
// POST JSON: {"n":3,"days":[{"level":"medium","stops":["colosseo","u1",...]},...],"custom":{"u1":{...}},"lang":"it","code":""}
// "code" (optional, patch 12): overwrite that itinerary instead of creating a new one (game points stay).
// Reply:     {"ok":true,"code":"K7PM-4RQX","url":"/itinerari/salvato/K7PM4RQX"}
// Files: /data/itineraries/{CODE}.json (private, never in the repo).
require_once __DIR__ . '/../lib/lib.php';
require_once __DIR__ . '/../lib/i18n.php';

send_security_headers(['no_store' => true]);
header('Content-Type: application/json; charset=utf-8');

function itin_api_fail($code, $msg) {
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') itin_api_fail(405, 'POST only');

// Only from our own pages: browsers send Origin on POST fetch, and it must be the host that served the page
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '') {
  $oHost = sanitize_host((string)parse_url($origin, PHP_URL_HOST));
  if ($oHost === '' || $oHost !== sanitize_host((string)($_SERVER['HTTP_HOST'] ?? ''))) itin_api_fail(403, 'Origin not allowed');
}

$raw = file_get_contents('php://input', false, null, 0, 20000);
$in = json_decode((string)$raw, true);
$plan = itin_clean_plan($in);
if (!$plan) itin_api_fail(400, 'Invalid itinerary');

// 20 saves per hour per IP
[$allowed, $retry] = rate_limit_hit('itin_save', client_ip(), 20, 3600);
if (!$allowed) {
  header('Retry-After: ' . (int)$retry);
  itin_api_fail(429, 'Too many itineraries saved, try later');
}

$lang = i18n_normalize_lang((string)($in['lang'] ?? 'it'));
if (!in_array($lang, itin_languages(), true)) $lang = itin_languages()[0];

$code = itin_save($plan, $lang, (string)($in['code'] ?? ''));
if ($code === '') itin_api_fail(500, 'Could not save');

echo json_encode([
  'ok' => true,
  'code' => itin_code_display($code),
  'url' => i18n_url('itin_saved', $lang) . '/' . $code,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
