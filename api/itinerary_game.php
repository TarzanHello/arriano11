<?php
// Itinerary game API (patch 12, Oct 2026). POST JSON only, from our own pages.
//   {"action":"checkin","code":"K7PM4RQX","day":1,"place":"colosseo","lat":41.89,"lng":12.49,"acc":12}
//   {"action":"nick","code":"K7PM4RQX","nick":"Gladiatrice"}
// Reply: {"ok":true,"gained":10,"new_badges":["first"],"state":{...}} or {"ok":false,"reason":"far","dist":340}
// The position is used only for the distance check and is not stored.
require_once __DIR__ . '/../lib/lib.php';
require_once __DIR__ . '/../lib/i18n.php';

send_security_headers(['no_store' => true]);
header('Content-Type: application/json; charset=utf-8');

function itin_game_api_out($code, $data) {
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') itin_game_api_out(405, ['ok' => false, 'reason' => 'method']);

$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '') {
  $oHost = sanitize_host((string)parse_url($origin, PHP_URL_HOST));
  if ($oHost === '' || $oHost !== sanitize_host((string)($_SERVER['HTTP_HOST'] ?? ''))) itin_game_api_out(403, ['ok' => false, 'reason' => 'origin']);
}

$in = json_decode((string)file_get_contents('php://input', false, null, 0, 4096), true);
if (!is_array($in)) itin_game_api_out(400, ['ok' => false, 'reason' => 'input']);

[$allowed, $retry] = rate_limit_hit('itin_game', client_ip(), 300, 3600);
if (!$allowed) {
  header('Retry-After: ' . (int)$retry);
  itin_game_api_out(429, ['ok' => false, 'reason' => 'limit']);
}

$code = itin_code_normalize((string)($in['code'] ?? ''));
$plan = $code !== '' ? itin_load($code) : null;
if (!$plan) itin_game_api_out(404, ['ok' => false, 'reason' => 'code']);

$action = (string)($in['action'] ?? '');

if ($action === 'checkin') {
  $lat = (float)($in['lat'] ?? 0);
  $lng = (float)($in['lng'] ?? 0);
  $acc = (float)($in['acc'] ?? 0);
  if (!itin_in_rome($lat, $lng)) itin_game_api_out(200, ['ok' => false, 'reason' => 'far', 'dist' => 99999]);
  $r = itin_game_checkin($code, $plan, (int)($in['day'] ?? 0), (string)($in['place'] ?? ''), $lat, $lng, $acc);
  if (empty($r['ok'])) itin_game_api_out(200, $r);
  itin_game_api_out(200, [
    'ok' => true,
    'already' => !empty($r['already']),
    'gained' => (int)$r['gained'],
    'new_badges' => $r['new_badges'],
    'state' => itin_game_public_state($code, $plan),
  ]);
}

if ($action === 'nick') {
  $raw = trim((string)($in['nick'] ?? ''));
  if ($raw === '') {
    $g = itin_game_set_nick($code, $plan, '');
  } else {
    $nick = itin_nick_clean($raw);
    if ($nick === '') itin_game_api_out(200, ['ok' => false, 'reason' => 'nick']);
    $g = itin_game_set_nick($code, $plan, $nick);
  }
  if ($g === null) itin_game_api_out(500, ['ok' => false, 'reason' => 'save']);
  itin_game_api_out(200, ['ok' => true, 'state' => itin_game_public_state($code, $plan)]);
}

itin_game_api_out(400, ['ok' => false, 'reason' => 'action']);
