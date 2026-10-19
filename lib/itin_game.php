<?php
// Itinerary game (patch 12, Oct 2026).
// The visitor presses "Avvia percorso" on a saved itinerary; every stop reached (checked with the phone's GPS
// by the server, never trusted from the browser) earns points and badges. A nickname puts them on the public
// leaderboard shown on /itinerari. The itinerary code is the player's identity: no accounts.
//
// Files (private, never in the repo):
//   /data/itineraries/{CODE}.game.json   progress of one itinerary
//   /data/itineraries/leaderboard.json   one row per itinerary with points (nick, points, stops, badges, hidden)
// The GPS position is used only to check the distance and is never stored.

const VIA_GAME_POINTS_STOP = 10;
const VIA_GAME_POINTS_DAY = 30;
const VIA_GAME_POINTS_BADGE = 20;

// Badge key => icon. Names and descriptions are in the texts file (ui badges).
function itin_badges() {
  return [
    'first'     => '👣',
    'gladiator' => '⚔️',
    'pilgrim'   => '⛪',
    'fountains' => '⛲',
    'appia'     => '🛣️',
    'early'     => '🌅',
    'walker'    => '🥾',
    'day'       => '✅',
    'explorer'  => '🧭',
    'emperor'   => '👑',
  ];
}

// Arrival radius in metres: big sites and lunch areas are wider than a fountain
function itin_game_radius($place) {
  if (!empty($place['custom'])) return 80;
  if (!empty($place['meal'])) return 250;
  $inner = (float)($place['inner'] ?? 0);
  if ($inner >= 1.5) return 220;
  if ($inner >= 0.5) return 130;
  return 80;
}

// Straight-line distance in metres
function itin_game_dist_m($lat1, $lng1, $lat2, $lng2) {
  $dLat = deg2rad($lat2 - $lat1);
  $dLng = deg2rad($lng2 - $lng1);
  $h = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
  return 2 * 6371000 * asin(min(1, sqrt($h)));
}

// Place record for a stop of a plan (official place or the visitor's own stop), null if unknown
function itin_game_place($plan, $id) {
  $places = itin_places();
  if (isset($places[$id])) return $places[$id];
  $c = $plan['custom'][$id] ?? null;
  if (!$c) return null;
  return ['lat' => (float)$c['lat'], 'lng' => (float)$c['lng'], 'custom' => true];
}

// Does this stop earn points? Monuments yes; lunch areas and the visitor's own stops no (not verifiable)
function itin_game_scores($plan, $id) {
  $p = itin_game_place($plan, $id);
  return $p && empty($p['custom']) && empty($p['meal']);
}

function itin_game_file($code) {
  return itin_dir() . '/' . $code . '.game.json';
}

function itin_game_empty() {
  return ['v' => 1, 'nick' => '', 'points' => 0, 'visits' => [], 'days_done' => [], 'badges' => [], 'last' => null];
}

function itin_game_load($code) {
  $code = itin_code_normalize($code);
  if ($code === '') return itin_game_empty();
  $raw = @file_get_contents(itin_game_file($code));
  $g = $raw ? json_decode($raw, true) : null;
  if (!is_array($g)) return itin_game_empty();
  $g = $g + itin_game_empty();
  foreach (['visits', 'days_done', 'badges'] as $k) if (!is_array($g[$k])) $g[$k] = [];
  return $g;
}

function itin_game_write($code, $g) {
  $f = itin_game_file($code);
  $tmp = $f . '.tmp.' . bin2hex(random_bytes(4));
  if (@file_put_contents($tmp, json_encode($g, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) return false;
  if (!@rename($tmp, $f)) { @unlink($tmp); return false; }
  return true;
}

// Badges earned by a game state (keys)
function itin_game_badges_for($g, $plan) {
  $v = $g['visits'];
  $has = function ($id) use ($v) { return isset($v[$id]); };
  $official = array_filter(array_keys($v), function ($id) use ($plan) { return itin_game_scores($plan, $id); });
  $out = [];
  if (count($official) >= 1) $out[] = 'first';
  if ($has('colosseo') && $has('foro') && $has('palatino')) $out[] = 'gladiator';
  if ($has('vaticani') && ($has('san_pietro') || $has('cupola'))) $out[] = 'pilgrim';
  if (count(array_filter(['trevi', 'navona', 'spagna', 'popolo', 'campo_fiori'], $has)) >= 3) $out[] = 'fountains';
  if (array_filter(['san_callisto', 'san_sebastiano', 'cecilia_metella', 'appia_basoli', 'acquedotti'], $has)) $out[] = 'appia';
  foreach ($official as $id) {
    if (!empty($v[$id]['early'])) { $out[] = 'early'; break; }
  }
  if (count($official) >= 10) $out[] = 'walker';
  if ($g['days_done']) $out[] = 'day';
  foreach (array_keys($v) as $id) {
    if (isset($plan['custom'][$id])) { $out[] = 'explorer'; break; }
  }
  if (count($g['days_done']) >= (int)$plan['n']) $out[] = 'emperor';
  return $out;
}

// A day is complete when every scoring stop of that day (at least two) has been reached
function itin_game_day_complete($g, $plan, $day) {
  $stops = $plan['days'][$day - 1]['stops'] ?? [];
  $need = array_values(array_filter($stops, function ($id) use ($plan) { return itin_game_scores($plan, $id); }));
  if (count($need) < 2) return false;
  foreach ($need as $id) if (!isset($g['visits'][$id])) return false;
  return true;
}

// Check-in at a stop. Returns ['ok' => bool, 'reason' => far|inaccurate|fast|stop, 'dist' => m, 'gained' => n, 'new_badges' => [...]]
function itin_game_checkin($code, $plan, $day, $id, $lat, $lng, $acc) {
  $day = (int)$day;
  $stops = $plan['days'][$day - 1]['stops'] ?? null;
  if (!is_array($stops) || !in_array($id, $stops, true)) return ['ok' => false, 'reason' => 'stop'];
  $place = itin_game_place($plan, $id);
  if (!$place) return ['ok' => false, 'reason' => 'stop'];
  if ($acc <= 0 || $acc > 150) return ['ok' => false, 'reason' => 'inaccurate'];

  $dist = itin_game_dist_m($lat, $lng, (float)$place['lat'], (float)$place['lng']);
  $allowed = itin_game_radius($place) + min($acc, 50);
  if ($dist > $allowed) return ['ok' => false, 'reason' => 'far', 'dist' => (int)round($dist)];

  $g = itin_game_load($code);
  if (isset($g['visits'][$id])) return ['ok' => true, 'already' => true, 'gained' => 0, 'new_badges' => [], 'game' => $g];

  $now = time();
  // Faster than a taxi between two stops? Then the position is not real.
  if (is_array($g['last']) && isset($g['last']['place'], $g['last']['t']) && $g['last']['place'] !== $id) {
    $prev = itin_game_place($plan, (string)$g['last']['place']);
    if ($prev) {
      $km = itin_game_dist_m((float)$prev['lat'], (float)$prev['lng'], (float)$place['lat'], (float)$place['lng']) / 1000;
      $hours = max(1, $now - (int)$g['last']['t']) / 3600;
      if ($km > 1 && $km / $hours > 40) return ['ok' => false, 'reason' => 'fast'];
    }
  }

  $hour = (int)(new DateTime('now', new DateTimeZone('Europe/Rome')))->format('G');
  $gained = 0;
  $scores = itin_game_scores($plan, $id);
  $g['visits'][$id] = ['t' => $now, 'day' => $day] + ($scores && $hour >= 5 && $hour < 9 ? ['early' => 1] : []);
  $g['last'] = ['place' => $id, 't' => $now];
  if ($scores) $gained += VIA_GAME_POINTS_STOP;
  if (!isset($g['days_done'][$day]) && itin_game_day_complete($g, $plan, $day)) {
    $g['days_done'][$day] = $now;
    $gained += VIA_GAME_POINTS_DAY;
  }
  $new = [];
  foreach (itin_game_badges_for($g, $plan) as $b) {
    if (isset($g['badges'][$b])) continue;
    $g['badges'][$b] = $now;
    $new[] = $b;
    $gained += VIA_GAME_POINTS_BADGE;
  }
  $g['points'] = (int)$g['points'] + $gained;
  if (!itin_game_write($code, $g)) return ['ok' => false, 'reason' => 'save'];
  itin_board_put($code, $g, $plan);
  return ['ok' => true, 'gained' => $gained, 'new_badges' => $new, 'game' => $g];
}

// ------------------------------------------------------------
// Nicknames: public, so short, plain and polite
// ------------------------------------------------------------
function itin_nick_clean($nick) {
  $nick = itin_clean_text($nick, 40);
  $len = function_exists('mb_strlen') ? mb_strlen($nick, 'UTF-8') : strlen($nick);
  if ($len < 2 || $len > 20) return '';
  if (!preg_match('/^[\p{L}\p{N}][\p{L}\p{N}\p{M} _\-]*$/u', $nick)) return '';
  $flat = function_exists('mb_strtolower') ? mb_strtolower($nick, 'UTF-8') : strtolower($nick);
  $flat = preg_replace('/[^\p{L}\p{N}]+/u', '', $flat);
  $flat = strtr($flat, ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a']);
  foreach (['fuck', 'shit', 'bitch', 'cunt', 'dick', 'pussy', 'porn', 'nigg', 'fagg', 'nazi', 'hitler', 'rape',
            'cazz', 'merd', 'stronz', 'puttan', 'troia', 'vaffan', 'coglion', 'frocio', 'negro', 'figa', 'mignott',
            'mierda', 'puta', 'pendej', 'gilipoll', 'connard', 'salope', 'encul', 'pute', 'scheis', 'hure', 'fotze', 'wichs',
            'caralh', 'porra', 'buceta', 'blyat', 'suka', 'pizd', 'huj', 'kurwa', 'chuj', 'jeban', 'pula', 'muie',
            'fitt', 'kuk', 'luder', 'admin', 'viaarriano', 'http', 'www'] as $bad) {
    if (strpos($flat, $bad) !== false) return '';
  }
  return $nick;
}

function itin_game_set_nick($code, $plan, $nick) {
  $g = itin_game_load($code);
  $g['nick'] = $nick;
  if (!itin_game_write($code, $g)) return null;
  itin_board_put($code, $g, $plan);
  return $g;
}

// ------------------------------------------------------------
// Leaderboard
// ------------------------------------------------------------
function itin_board_file() {
  return itin_dir() . '/leaderboard.json';
}

// Read-modify-write under an exclusive lock
function itin_board_update(callable $fn) {
  $fh = @fopen(itin_board_file(), 'c+');
  if (!$fh) return false;
  flock($fh, LOCK_EX);
  $raw = stream_get_contents($fh);
  $rows = $raw ? json_decode($raw, true) : [];
  if (!is_array($rows)) $rows = [];
  $rows = $fn($rows);
  ftruncate($fh, 0);
  rewind($fh);
  fwrite($fh, json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
  fflush($fh);
  flock($fh, LOCK_UN);
  fclose($fh);
  return true;
}

function itin_board_put($code, $g, $plan) {
  $stops = count(array_filter(array_keys($g['visits']), function ($id) use ($plan) { return itin_game_scores($plan, $id); }));
  return itin_board_update(function ($rows) use ($code, $g, $stops) {
    $old = $rows[$code] ?? [];
    $rows[$code] = [
      'nick' => (string)$g['nick'],
      'points' => (int)$g['points'],
      'stops' => $stops,
      'badges' => array_keys($g['badges']),
      'updated' => time(),
      'hidden' => !empty($old['hidden']),
    ];
    return $rows;
  });
}

function itin_board_rows() {
  $raw = @file_get_contents(itin_board_file());
  $rows = $raw ? json_decode($raw, true) : [];
  return is_array($rows) ? $rows : [];
}

// Public ranking: rows with a nickname, not hidden by the admin, with points; best first (ties: who got there first)
function itin_board_public($limit = 20) {
  $rows = array_filter(itin_board_rows(), function ($r) {
    return is_array($r) && ($r['nick'] ?? '') !== '' && empty($r['hidden']) && (int)($r['points'] ?? 0) > 0;
  });
  uasort($rows, function ($a, $b) {
    return [(int)$b['points'], (int)$a['updated']] <=> [(int)$a['points'], (int)$b['updated']];
  });
  return $limit > 0 ? array_slice($rows, 0, $limit, true) : $rows;
}

// Position of an itinerary in the public ranking (1-based), 0 if not listed
function itin_board_rank($code) {
  $i = 0;
  foreach (itin_board_public(0) as $c => $r) {
    $i++;
    if ((string)$c === (string)$code) return $i;
  }
  return 0;
}

// State sent to the saved page
function itin_game_public_state($code, $plan) {
  $g = itin_game_load($code);
  return [
    'nick' => (string)$g['nick'],
    'points' => (int)$g['points'],
    'visits' => array_map(function ($v) { return (int)($v['day'] ?? 0); }, $g['visits']) ?: new stdClass(),
    'days_done' => array_map('intval', array_keys($g['days_done'])),
    'badges' => array_keys($g['badges']),
    'rank' => $g['nick'] !== '' ? itin_board_rank($code) : 0,
  ];
}
