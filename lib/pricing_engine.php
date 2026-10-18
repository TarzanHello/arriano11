<?php

// ------------------------------------------------------------
// Motore prezzi (admin)
//
// Obiettivo: suggerire e applicare prezzi giornalieri (direct)
// in base a:
// - stagionalità (base per mese)
// - giorno della settimana
// - copertura (horizon) 15/20 giorni
// - spinta sul mese successivo (entro finestra configurabile)
// - event_factor: festivi IT + ponti + eventi manuali
//
// NOTE:
// - Non è un "ML black box": ogni fattore restituisce anche le motivazioni.
// - Tutto è clampato (floor/ceiling) per non distruggere conversione.
// ------------------------------------------------------------

function pricing_engine_default_settings() {
  // Default tarati sullo storico fornito (2023-2024) di Via Arriano.
  // Valori in EUR/notte (poi moltiplicati per i fattori DOW + event + pace).
  $baseByMonth = [
    1 => 78,
    2 => 75,
    3 => 81,
    4 => 105,
    5 => 109,
    6 => 105,
    7 => 112,
    8 => 103,
    9 => 106,
    10 => 111,
    11 => 95,
    12 => 87,
  ];

  // Fattori giorno settimana (ISO-8601: 1=Lun ... 7=Dom)
  // Derivati in modo prudente dal venduto; modificabili da admin.
  $dow = [
    1 => 0.98, // Lun
    2 => 0.93, // Mar
    3 => 0.96, // Mer
    4 => 1.00, // Gio
    5 => 1.00, // Ven
    6 => 1.00, // Sab
    7 => 1.11, // Dom
  ];

  return [
    'enabled' => 1,

    // Obiettivo: avere sempre una copertura (notti "piene") di 15/20 giorni.
    'target_horizon_days' => 18,

    // Quando mancano <= X giorni alla fine del mese corrente, iniziamo a "spingere"
    // per riempire il mese successivo.
    'push_next_month_window_days' => 20,

    // Aggressività dei fattori (0..1). 0 = conservativo; 1 = aggressivo.
    'aggressiveness' => 0.65,

    // Intervallo lead-time target (giorni) dove vogliamo "stare bene".
    // Sotto window_low: last-minute scontato (se serve).
    'window_low' => 15,
    'window_high' => 20,
    'last_minute_max_discount' => 0.25, // max -25%

    // Prezzi min/max (hard). "ceiling_event" si usa quando il lift eventi è alto.
    'floor_price' => 50,
    'ceiling_price' => 150,
    'ceiling_event_price' => 220,

    // Arrotondamento finale (1, 5, 10)
    'round_to' => 1,

    // Event factor: somma dei lift (non moltiplichiamo tutto tra loro per evitare esplosioni).
    'event_max_lift' => 0.45, // max +45%

    // Pesature base per festivi/ponti (lift).
    'holiday_major_lift' => 0.18,
    'holiday_mid_lift' => 0.12,
    'bridge_lift' => 0.06,
    'season_lift_christmas' => 0.08,
    'season_lift_easter' => 0.10,

    // Tabelle
    'base_by_month' => $baseByMonth,
    'dow_factors' => $dow,

    // Eventi manuali (array di: id, title, start, end, lift)
    'events' => [],
  ];
}


function pricing_engine_get_settings($store) {
  $defaults = pricing_engine_default_settings();
  $pe = [];
  if (is_array($store) && isset($store['pricing_engine']) && is_array($store['pricing_engine'])) {
    $pe = $store['pricing_engine'];
  }

  // Merge (defaults + overrides)
  foreach ($defaults as $k => $v) {
    if (!array_key_exists($k, $pe)) {
      $pe[$k] = $v;
    }
  }

  // Normalize
  $pe['enabled'] = !empty($pe['enabled']) ? 1 : 0;
  $pe['target_horizon_days'] = max(1, min(90, (int)($pe['target_horizon_days'] ?? 18)));
  $pe['push_next_month_window_days'] = max(0, min(60, (int)($pe['push_next_month_window_days'] ?? 20)));
  $pe['aggressiveness'] = (float)($pe['aggressiveness'] ?? 0.65);
  if ($pe['aggressiveness'] < 0) $pe['aggressiveness'] = 0;
  if ($pe['aggressiveness'] > 1) $pe['aggressiveness'] = 1;

  $pe['window_low'] = max(1, min(60, (int)($pe['window_low'] ?? 15)));
  $pe['window_high'] = max($pe['window_low'], min(120, (int)($pe['window_high'] ?? 20)));

  $pe['last_minute_max_discount'] = (float)($pe['last_minute_max_discount'] ?? 0.25);
  if ($pe['last_minute_max_discount'] < 0) $pe['last_minute_max_discount'] = 0;
  if ($pe['last_minute_max_discount'] > 0.60) $pe['last_minute_max_discount'] = 0.60;

  $pe['floor_price'] = (int)($pe['floor_price'] ?? 50);
  $pe['ceiling_price'] = (int)($pe['ceiling_price'] ?? 150);
  $pe['ceiling_event_price'] = (int)($pe['ceiling_event_price'] ?? 220);
  if ($pe['floor_price'] < 0) $pe['floor_price'] = 0;
  if ($pe['ceiling_price'] < $pe['floor_price']) $pe['ceiling_price'] = $pe['floor_price'];
  if ($pe['ceiling_event_price'] < $pe['ceiling_price']) $pe['ceiling_event_price'] = $pe['ceiling_price'];

  $pe['round_to'] = (int)($pe['round_to'] ?? 1);
  if (!in_array($pe['round_to'], [1,5,10], true)) $pe['round_to'] = 1;

  $pe['event_max_lift'] = (float)($pe['event_max_lift'] ?? 0.45);
  if ($pe['event_max_lift'] < 0) $pe['event_max_lift'] = 0;
  if ($pe['event_max_lift'] > 1.0) $pe['event_max_lift'] = 1.0;

  foreach (['holiday_major_lift','holiday_mid_lift','bridge_lift','season_lift_christmas','season_lift_easter'] as $k) {
    $pe[$k] = (float)($pe[$k] ?? 0);
    if ($pe[$k] < 0) $pe[$k] = 0;
    if ($pe[$k] > 1.0) $pe[$k] = 1.0;
  }

  // Base per mese
  if (!isset($pe['base_by_month']) || !is_array($pe['base_by_month'])) {
    $pe['base_by_month'] = $defaults['base_by_month'];
  }
  for ($m=1; $m<=12; $m++) {
    $v = $pe['base_by_month'][$m] ?? $defaults['base_by_month'][$m];
    if (!is_numeric($v)) $v = $defaults['base_by_month'][$m];
    $pe['base_by_month'][$m] = (int)round((float)$v);
    if ($pe['base_by_month'][$m] < 0) $pe['base_by_month'][$m] = 0;
  }

  // DOW
  if (!isset($pe['dow_factors']) || !is_array($pe['dow_factors'])) {
    $pe['dow_factors'] = $defaults['dow_factors'];
  }
  for ($d=1; $d<=7; $d++) {
    $v = $pe['dow_factors'][$d] ?? $defaults['dow_factors'][$d];
    if (!is_numeric($v)) $v = $defaults['dow_factors'][$d];
    $pe['dow_factors'][$d] = (float)$v;
    if ($pe['dow_factors'][$d] < 0.50) $pe['dow_factors'][$d] = 0.50;
    if ($pe['dow_factors'][$d] > 1.80) $pe['dow_factors'][$d] = 1.80;
  }

  // Manual events
  if (!isset($pe['events']) || !is_array($pe['events'])) {
    $pe['events'] = [];
  }
  // Normalize list
  $cleanEvents = [];
  foreach ($pe['events'] as $ev) {
    if (!is_array($ev)) continue;
    $s = normalize_ymd($ev['start'] ?? '');
    $e = normalize_ymd($ev['end'] ?? '');
    if (!$s || !$e || $e <= $s) continue;
    $lift = $ev['lift'] ?? 0;
    if (!is_numeric($lift)) $lift = 0;
    $lift = (float)$lift;
    if ($lift < 0) $lift = 0;
    if ($lift > 1.0) $lift = 1.0;
    $cleanEvents[] = [
      'id' => (string)($ev['id'] ?? ('ev-' . md5($s . '|' . $e . '|' . ($ev['title'] ?? '')))),
      'title' => trim((string)($ev['title'] ?? '')),
      'start' => $s,
      'end' => $e,
      'lift' => $lift,
    ];
  }
  $pe['events'] = $cleanEvents;

  return $pe;
}


function pricing_engine_save_settings(&$store, $post) {
  if (!is_array($store)) return [false, 'Store non valido.'];
  $pe = pricing_engine_get_settings($store);

  $pe['enabled'] = ((int)($post['pe_enabled'] ?? 0) === 1) ? 1 : 0;
  $pe['target_horizon_days'] = (int)($post['pe_target_horizon_days'] ?? $pe['target_horizon_days']);
  $pe['push_next_month_window_days'] = (int)($post['pe_push_next_month_window_days'] ?? $pe['push_next_month_window_days']);

  $ag = $post['pe_aggressiveness'] ?? $pe['aggressiveness'];
  $pe['aggressiveness'] = is_numeric($ag) ? (float)$ag : $pe['aggressiveness'];

  $pe['window_low'] = (int)($post['pe_window_low'] ?? $pe['window_low']);
  $pe['window_high'] = (int)($post['pe_window_high'] ?? $pe['window_high']);
  $lm = $post['pe_last_minute_max_discount'] ?? $pe['last_minute_max_discount'];
  $pe['last_minute_max_discount'] = is_numeric($lm) ? (float)$lm : $pe['last_minute_max_discount'];

  $pe['floor_price'] = (int)($post['pe_floor_price'] ?? $pe['floor_price']);
  $pe['ceiling_price'] = (int)($post['pe_ceiling_price'] ?? $pe['ceiling_price']);
  $pe['ceiling_event_price'] = (int)($post['pe_ceiling_event_price'] ?? $pe['ceiling_event_price']);

  $pe['round_to'] = (int)($post['pe_round_to'] ?? $pe['round_to']);
  $mx = $post['pe_event_max_lift'] ?? $pe['event_max_lift'];
  $pe['event_max_lift'] = is_numeric($mx) ? (float)$mx : $pe['event_max_lift'];

  foreach (['holiday_major_lift','holiday_mid_lift','bridge_lift','season_lift_christmas','season_lift_easter'] as $k) {
    $key = 'pe_' . $k;
    if (array_key_exists($key, $post)) {
      $v = $post[$key];
      if (is_numeric($v)) $pe[$k] = (float)$v;
    }
  }

  // base_by_month
  for ($m=1; $m<=12; $m++) {
    $key = 'pe_base_' . $m;
    if (array_key_exists($key, $post)) {
      $v = $post[$key];
      if (is_numeric($v)) {
        $pe['base_by_month'][$m] = (int)round((float)$v);
      }
    }
  }

  // dow factors
  for ($d=1; $d<=7; $d++) {
    $key = 'pe_dow_' . $d;
    if (array_key_exists($key, $post)) {
      $v = $post[$key];
      if (is_numeric($v)) {
        $pe['dow_factors'][$d] = (float)$v;
      }
    }
  }

  // Normalize again
  $store['pricing_engine'] = pricing_engine_get_settings(['pricing_engine' => $pe]);
  return [true, null];
}


// ------------------------------------------------------------
// Event factor
// ------------------------------------------------------------

function pe_easter_ymd($year) {
  // Anonymous Gregorian algorithm
  $y = (int)$year;
  $a = $y % 19;
  $b = intdiv($y, 100);
  $c = $y % 100;
  $d = intdiv($b, 4);
  $e = $b % 4;
  $f = intdiv($b + 8, 25);
  $g = intdiv($b - $f + 1, 3);
  $h = (19*$a + $b - $d - $g + 15) % 30;
  $i = intdiv($c, 4);
  $k = $c % 4;
  $l = (32 + 2*$e + 2*$i - $h - $k) % 7;
  $m = intdiv($a + 11*$h + 22*$l, 451);
  $month = intdiv($h + $l - 7*$m + 114, 31);
  $day = (($h + $l - 7*$m + 114) % 31) + 1;
  return sprintf('%04d-%02d-%02d', $y, $month, $day);
}

function pe_holidays_for_year($year, $pe) {
  $y = (int)$year;
  $maj = (float)($pe['holiday_major_lift'] ?? 0.18);
  $mid = (float)($pe['holiday_mid_lift'] ?? 0.12);

  $hol = [
    sprintf('%04d-01-01', $y) => ['Capodanno', $maj],
    sprintf('%04d-01-06', $y) => ['Epifania', $mid],
    sprintf('%04d-04-25', $y) => ['25 Aprile', $mid],
    sprintf('%04d-05-01', $y) => ['1 Maggio', $mid],
    sprintf('%04d-06-02', $y) => ['2 Giugno', $mid],
    sprintf('%04d-06-29', $y) => ['Santi Pietro e Paolo (Roma)', $mid],
    sprintf('%04d-08-15', $y) => ['Ferragosto', $mid],
    sprintf('%04d-11-01', $y) => ['Ognissanti', $mid],
    sprintf('%04d-12-08', $y) => ['Immacolata', $mid],
    sprintf('%04d-12-24', $y) => ['Vigilia di Natale', $mid],
    sprintf('%04d-12-25', $y) => ['Natale', $maj],
    sprintf('%04d-12-26', $y) => ['Santo Stefano', $mid],
    sprintf('%04d-12-31', $y) => ['Capodanno (notte)', $maj],
  ];

  $easter = pe_easter_ymd($y);
  $hol[$easter] = ['Pasqua', $maj];
  $hol[add_days_ymd($easter, 1)] = ['Pasquetta', $mid];

  return $hol;
}

function pe_event_lift_for_date($store, $dateYmd, $pe) {
  $dateYmd = normalize_ymd($dateYmd);
  if (!$dateYmd) return [0.0, []];

  $reasons = [];
  $lift = 0.0;

  $year = (int)substr($dateYmd, 0, 4);
  $holidays = pe_holidays_for_year($year, $pe);

  // 1) Festivi
  if (isset($holidays[$dateYmd])) {
    $lift += (float)$holidays[$dateYmd][1];
    $reasons[] = $holidays[$dateYmd][0];
  }

  // 2) Ponti (bridge days)
  //    Se festivo è martedì → lunedì ponte. Se è giovedì → venerdì ponte. Se è mercoledì → mar+gio (più leggero).
  $bridge = (float)($pe['bridge_lift'] ?? 0.06);
  foreach ($holidays as $hDate => $info) {
    $dow = (int)date('N', strtotime($hDate . ' 00:00:00')); // 1..7
    if ($dow === 2) {
      // Tuesday holiday → Monday bridge
      $d = add_days_ymd($hDate, -1);
      if ($d === $dateYmd && !isset($holidays[$d])) {
        $lift += $bridge;
        $reasons[] = 'Ponte (' . $info[0] . ')';
      }
    } elseif ($dow === 4) {
      // Thursday holiday → Friday bridge
      $d = add_days_ymd($hDate, 1);
      if ($d === $dateYmd && !isset($holidays[$d])) {
        $lift += $bridge;
        $reasons[] = 'Ponte (' . $info[0] . ')';
      }
    } elseif ($dow === 3) {
      // Wednesday holiday → Tuesday/Thursday lighter
      $d1 = add_days_ymd($hDate, -1);
      $d2 = add_days_ymd($hDate, 1);
      if (($d1 === $dateYmd || $d2 === $dateYmd) && !isset($holidays[$dateYmd])) {
        $lift += max(0.0, $bridge * 0.65);
        $reasons[] = 'Mezzo ponte (' . $info[0] . ')';
      }
    }
  }

  // 3) Periodi stagionali (Natale / Pasqua)
  $christmasLift = (float)($pe['season_lift_christmas'] ?? 0.08);
  $easterLift = (float)($pe['season_lift_easter'] ?? 0.10);

  // Natale: 20/12 → 06/01
  $y = $year;
  $xmasStart = sprintf('%04d-12-20', $y);
  $xmasEnd = sprintf('%04d-01-07', $y+1); // exclusive
  if (date_in_range($dateYmd, $xmasStart, $xmasEnd)) {
    $lift += $christmasLift;
    $reasons[] = 'Periodo Natale';
  }

  // Pasqua: da Venerdì Santo (Pasqua-2) a Martedì (Pasqua+2)
  $easter = pe_easter_ymd($year);
  $es = add_days_ymd($easter, -2);
  $ee = add_days_ymd($easter, 3); // exclusive (+2)
  if (date_in_range($dateYmd, $es, $ee)) {
    $lift += $easterLift;
    $reasons[] = 'Periodo Pasqua';
  }

  // 4) Eventi manuali (inseriti da admin)
  $events = $pe['events'] ?? [];
  if (is_array($events)) {
    foreach ($events as $ev) {
      if (!is_array($ev)) continue;
      $s = normalize_ymd($ev['start'] ?? '');
      $e = normalize_ymd($ev['end'] ?? '');
      if (!$s || !$e || $e <= $s) continue;
      if (date_in_range($dateYmd, $s, $e)) {
        $lv = $ev['lift'] ?? 0;
        if (!is_numeric($lv)) $lv = 0;
        $lv = (float)$lv;
        if ($lv < 0) $lv = 0;
        if ($lv > 1.0) $lv = 1.0;
        if ($lv > 0) {
          $lift += $lv;
          $title = trim((string)($ev['title'] ?? 'Evento'));
          $reasons[] = $title ?: 'Evento';
        }
      }
    }
  }

  // Clamp lift
  $maxLift = (float)($pe['event_max_lift'] ?? 0.45);
  if ($lift > $maxLift) $lift = $maxLift;
  if ($lift < 0) $lift = 0.0;

  return [$lift, $reasons];
}


// ------------------------------------------------------------
// Pace / occupancy helpers
// ------------------------------------------------------------

function pe_is_sellable_night($store, $dateYmd) {
  $slots = day_slots_for_day($store, $dateYmd);
  if (($slots['status'] ?? '') === 'closed') return false;
  // Se è occupato (arrivo o stay) non è vendibile.
  if (empty($slots['can_checkin'])) return false;
  return true;
}

function pe_is_sold_night($store, $dateYmd) {
  $slots = day_slots_for_day($store, $dateYmd);
  if (($slots['status'] ?? '') === 'closed') return false;
  // Notte venduta se il check-in è bloccato (arrivo o stay)
  return empty($slots['can_checkin']);
}

function pe_coverage_days_from_today($store, $maxDays = 90) {
  $today = date('Y-m-d');
  $cov = 0;
  for ($i=0; $i<$maxDays; $i++) {
    $d = add_days_ymd($today, $i);
    $slots = day_slots_for_day($store, $d);
    if (($slots['status'] ?? '') === 'closed') {
      $cov++;
      continue;
    }
    if (empty($slots['can_checkin'])) {
      $cov++;
      continue;
    }
    break;
  }
  return $cov;
}

function pe_month_key_for_date($ymd) {
  $ymd = normalize_ymd($ymd);
  if (!$ymd) return null;
  return substr($ymd, 0, 7);
}

function pe_month_range($monthKey) {
  if (!preg_match('/^\d{4}-\d{2}$/', (string)$monthKey)) return [null, null];
  $first = $monthKey . '-01';
  $t = strtotime($first . ' 00:00:00');
  if ($t === false) return [null, null];
  $daysInMonth = (int)date('t', $t);
  $start = $first;
  $end = date('Y-m-d', strtotime('+' . $daysInMonth . ' days', $t));
  return [$start, $end]; // [start, end)
}

function pe_month_occupancy($store, $monthKey) {
  list($s, $e) = pe_month_range($monthKey);
  if (!$s || !$e) return [0.0, 0, 0];
  $sold = 0;
  $open = 0;
  $d = $s;
  while ($d < $e) {
    $slots = day_slots_for_day($store, $d);
    if (($slots['status'] ?? '') !== 'closed') {
      // Inventory open (non chiuso)
      $open++;
      if (empty($slots['can_checkin'])) {
        $sold++;
      }
    }
    $d = add_days_ymd($d, 1);
  }
  $occ = ($open > 0) ? ($sold / $open) : 0.0;
  return [$occ, $sold, $open];
}

function pe_days_to_end_of_month($ymd) {
  $ymd = normalize_ymd($ymd);
  if (!$ymd) return 0;
  $t = strtotime($ymd . ' 00:00:00');
  if ($t === false) return 0;
  $end = date('Y-m-t', $t);
  return max(0, (int)((strtotime($end . ' 00:00:00') - $t) / 86400));
}

function pe_next_month_key($ymd) {
  $ymd = normalize_ymd($ymd);
  if (!$ymd) return null;
  $t = strtotime($ymd . ' 00:00:00');
  if ($t === false) return null;
  return date('Y-m', strtotime('+1 month', strtotime(date('Y-m-01', $t) . ' 00:00:00')));
}


// ------------------------------------------------------------
// Core pricing
// ------------------------------------------------------------

function pe_round_price($value, $roundTo) {
  $v = (float)$value;
  $r = (int)$roundTo;
  if ($r <= 1) return (int)round($v);
  return (int)(round($v / $r) * $r);
}

function pricing_engine_price_for_date($store, $dateYmd, $pe, $ctx = []) {
  $dateYmd = normalize_ymd($dateYmd);
  if (!$dateYmd) return [0, []];

  $reasons = [];

  $t = strtotime($dateYmd . ' 00:00:00');
  if ($t === false) return [0, []];

  $month = (int)date('n', $t);
  $dow = (int)date('N', $t); // 1..7
  $baseMonth = (float)($pe['base_by_month'][$month] ?? 0);
  $dowFactor = (float)($pe['dow_factors'][$dow] ?? 1.0);
  $base = $baseMonth * $dowFactor;
  $reasons[] = 'Base mese ' . $month . ' (' . (int)round($baseMonth) . ') × DOW ' . $dow . ' (' . rtrim(rtrim(number_format($dowFactor, 2, '.', ''), '0'), '.') . ')';

  // Event factor
  list($eventLift, $eventReasons) = pe_event_lift_for_date($store, $dateYmd, $pe);
  $eventFactor = 1.0 + (float)$eventLift;
  if ($eventLift > 0.0001) {
    $reasons[] = 'Eventi +' . (int)round($eventLift * 100) . '%: ' . implode(', ', array_slice($eventReasons, 0, 4)) . (count($eventReasons) > 4 ? '…' : '');
  }

  $today = $ctx['today'] ?? date('Y-m-d');
  $covNow = isset($ctx['coverage_days']) ? (int)$ctx['coverage_days'] : pe_coverage_days_from_today($store);
  $targetCov = (int)($pe['target_horizon_days'] ?? 18);
  $aggr = (float)($pe['aggressiveness'] ?? 0.65);

  // Days to arrival
  $dta = max(0, (int)((strtotime($dateYmd . ' 00:00:00') - strtotime($today . ' 00:00:00')) / 86400));

  // Horizon factor: spinge i prezzi (su/giù) nelle prossime ~1.2×targetCov giornate
  $pressure = ($targetCov > 0) ? (($covNow - $targetCov) / $targetCov) : 0.0; // negativo => siamo "corti"
  // Peso in base alla distanza: vicino => impatta di più
  $w = 1.0;
  $fade = max(1, (int)round($targetCov * 1.2));
  if ($dta >= $fade) {
    $w = 0.0;
  } else {
    $w = 1.0 - ($dta / $fade);
  }
  $hK = 0.35 * $aggr; // aggressività
  $horizonFactor = 1.0 + ($pressure * $hK * $w);
  // Clamp
  if ($horizonFactor < 0.85) $horizonFactor = 0.85;
  if ($horizonFactor > 1.20) $horizonFactor = 1.20;
  if (abs($horizonFactor - 1.0) > 0.01 && $w > 0) {
    $reasons[] = 'Copertura ' . $covNow . 'g vs target ' . $targetCov . 'g → ' . (int)round(($horizonFactor - 1.0) * 100) . '%';
  }

  // Spinta mese successivo (solo se siamo entro finestra)
  $monthFactor = 1.0;
  $pushWindow = (int)($pe['push_next_month_window_days'] ?? 20);
  if ($pushWindow > 0) {
    $nextMonth = pe_next_month_key($today);
    $thisMonth = substr($today, 0, 7);
    $dateMonth = substr($dateYmd, 0, 7);
    if ($dateMonth === $nextMonth) {
      $daysToEnd = pe_days_to_end_of_month($today);
      list($occNext, $soldNext, $openNext) = pe_month_occupancy($store, $nextMonth);

      // Target che sale linearmente quando mancano <= pushWindow giorni.
      $targetNow = 0.0;
      if ($daysToEnd <= $pushWindow) {
        $targetNow = 1.0 - ($daysToEnd / $pushWindow);
      }

      // Prima della finestra: non scontiamo (solo eventuale aumento se già pieno).
      if ($targetNow <= 0.0001) {
        if ($occNext > 0.60) {
          $monthFactor = 1.0 + (0.10 * $aggr);
          $reasons[] = 'Mese prossimo già forte (' . (int)round($occNext*100) . '%)';
        }
      } else {
        $diff = $occNext - $targetNow;
        $mK = 0.55 * $aggr;
        $monthFactor = 1.0 + ($diff * $mK);
        if ($monthFactor < 0.85) $monthFactor = 0.85;
        if ($monthFactor > 1.15) $monthFactor = 1.15;

        $reasons[] = 'Pace mese prossimo ' . (int)round($occNext*100) . '% vs target ' . (int)round($targetNow*100) . '%';
      }
    } elseif ($dateMonth === $thisMonth) {
      // Mese corrente: leggerissima spinta solo last-minute, già coperta da horizon.
    }
  }

  // Lead-time curve (15/20 giorni)
  $leadFactor = 1.0;
  $wLow = (int)($pe['window_low'] ?? 15);
  $wHigh = (int)($pe['window_high'] ?? 20);
  $maxDisc = (float)($pe['last_minute_max_discount'] ?? 0.25);
  if ($dta < $wLow) {
    $x = ($wLow > 0) ? (($wLow - $dta) / $wLow) : 0.0; // 0..1
    $disc = $maxDisc * $x * $aggr;
    $leadFactor = 1.0 - $disc;
    if ($disc > 0.01) {
      $reasons[] = 'Last-minute ' . $dta . 'g (-' . (int)round($disc*100) . '%)';
    }
  } elseif ($dta > $wHigh) {
    // leggero premium se molto lontano, ma solo se non stiamo "corti" di copertura
    if ($covNow >= $targetCov) {
      $leadFactor = 1.0 + (0.04 * $aggr);
    }
  }

  // Compose
  $raw = $base * $eventFactor * $horizonFactor * $monthFactor * $leadFactor;

  // Clamp floor/ceiling
  $floor = (float)($pe['floor_price'] ?? 0);
  $ceil = (float)($pe['ceiling_price'] ?? 0);
  $ceilEvent = (float)($pe['ceiling_event_price'] ?? $ceil);
  $useCeil = ($eventLift >= 0.15) ? $ceilEvent : $ceil;

  if ($raw < $floor) $raw = $floor;
  if ($useCeil > 0 && $raw > $useCeil) $raw = $useCeil;

  $price = pe_round_price($raw, (int)($pe['round_to'] ?? 1));
  if ($price < 0) $price = 0;

  return [$price, $reasons];
}


function pricing_engine_suggest_prices($store, $startYmd, $endYmd, $opts = []) {
  $pe = pricing_engine_get_settings($store);

  // Optional per-run overrides (do NOT persist): floor/ceiling for this suggestion run.
  // This is useful when you want different bounds for specific date ranges without changing saved settings.
  $floorOv = $opts['floor_override'] ?? null;
  $ceilOv = $opts['ceiling_override'] ?? null;
  if ($floorOv !== null && $floorOv !== '') {
    if (is_numeric($floorOv)) {
      $pe['floor_price'] = (int)round((float)$floorOv);
      if ($pe['floor_price'] < 0) $pe['floor_price'] = 0;
    }
  }
  if ($ceilOv !== null && $ceilOv !== '') {
    if (is_numeric($ceilOv)) {
      $pe['ceiling_price'] = (int)round((float)$ceilOv);
      if ($pe['ceiling_price'] < 0) $pe['ceiling_price'] = 0;
    }
  }
  // Keep invariants
  if ($pe['ceiling_price'] < $pe['floor_price']) {
    $pe['ceiling_price'] = $pe['floor_price'];
  }
  if (($pe['ceiling_event_price'] ?? 0) < $pe['ceiling_price']) {
    $pe['ceiling_event_price'] = $pe['ceiling_price'];
  }
  if (empty($pe['enabled'])) {
    return [false, 'Motore prezzi disabilitato.', []];
  }

  $startYmd = normalize_ymd($startYmd);
  $endYmd = normalize_ymd($endYmd);
  if (!$startYmd || !$endYmd) {
    return [false, 'Date non valide.', []];
  }
  if ($endYmd < $startYmd) {
    $tmp = $startYmd; $startYmd = $endYmd; $endYmd = $tmp;
  }

  $filterDow = $opts['dow'] ?? null; // array di ISO dows
  if (is_array($filterDow)) {
    $clean = [];
    foreach ($filterDow as $d) {
      $d = (int)$d;
      if ($d >= 1 && $d <= 7) $clean[] = $d;
    }
    $filterDow = array_values(array_unique($clean));
  }

  $skipUnavailable = !empty($opts['skip_unavailable']);
  $skipIfPriceSet = !empty($opts['skip_if_price_set']);

  $today = date('Y-m-d');
  $covNow = pe_coverage_days_from_today($store);

  $ctx = [
    'today' => $today,
    'coverage_days' => $covNow,
  ];

  $out = [];
  $d = $startYmd;
  $max = 370; // safety for very large ranges
  $i = 0;
  while ($d <= $endYmd) {
    $i++;
    if ($i > $max) break;

    $t = strtotime($d . ' 00:00:00');
    $isoDow = $t ? (int)date('N', $t) : null;

    if ($filterDow && $isoDow && !in_array($isoDow, $filterDow, true)) {
      $d = add_days_ymd($d, 1);
      continue;
    }

    $slots = day_slots_for_day($store, $d);
    $sellable = pe_is_sellable_night($store, $d);

    if ($skipUnavailable && !$sellable) {
      $d = add_days_ymd($d, 1);
      continue;
    }

    $hasOverride = false;
    $curOverride = null;
    if (isset($store['daily']) && is_array($store['daily']) && isset($store['daily'][$d]) && is_array($store['daily'][$d])) {
      if (array_key_exists('price', $store['daily'][$d]) && is_numeric($store['daily'][$d]['price'])) {
        $hasOverride = true;
        $curOverride = (float)$store['daily'][$d]['price'];
      }
    }

    if ($skipIfPriceSet && $hasOverride) {
      $d = add_days_ymd($d, 1);
      continue;
    }

    $current = get_direct_price_for_date($store, $d);
    list($suggested, $reasons) = pricing_engine_price_for_date($store, $d, $pe, $ctx);

    $out[] = [
      'date' => $d,
      'dow' => $isoDow,
      'status' => (string)($slots['status'] ?? 'available'),
      'sellable' => $sellable ? 1 : 0,
      'current_price' => $current,
      'has_override' => $hasOverride ? 1 : 0,
      'override_price' => $curOverride,
      'suggested_price' => $suggested,
      'reasons' => $reasons,
    ];

    $d = add_days_ymd($d, 1);
  }

  return [true, null, $out];
}


function pricing_engine_apply_suggestions(&$store, $startYmd, $endYmd, $opts = []) {
  list($ok, $err, $rows) = pricing_engine_suggest_prices($store, $startYmd, $endYmd, $opts);
  if (!$ok) return [false, $err, 0];

  if (!isset($store['daily']) || !is_array($store['daily'])) {
    $store['daily'] = [];
  }

  $overwrite = !empty($opts['overwrite']);
  $applied = 0;
  foreach ($rows as $r) {
    $d = $r['date'];
    $price = (float)($r['suggested_price'] ?? 0);
    if ($price <= 0) continue;

    // Don't write prices for non sellable nights (unless explicitly requested)
    if (!empty($opts['skip_unavailable']) && empty($r['sellable'])) {
      continue;
    }

    $hasOverride = !empty($r['has_override']);
    if ($hasOverride && !$overwrite) {
      continue;
    }

    $entry = $store['daily'][$d] ?? [];
    if (!is_array($entry)) $entry = [];
    $entry['price'] = round($price, 2);

    // Keep other daily keys (closed/min_nights) intact.
    $store['daily'][$d] = $entry;
    $applied++;
  }

  return [true, null, $applied];
}


// ------------------------------------------------------------
// Manual events management
// ------------------------------------------------------------

function pe_add_event(&$store, $title, $start, $end, $lift) {
  $pe = pricing_engine_get_settings($store);
  $title = trim((string)$title);
  $s = normalize_ymd($start);
  $e = normalize_ymd($end);
  if (!$s || !$e || $e <= $s) {
    return [false, 'Date evento non valide.'];
  }
  $lv = $lift;
  if (!is_numeric($lv)) $lv = 0;
  $lv = (float)$lv;
  if ($lv < 0) $lv = 0;
  if ($lv > 1.0) $lv = 1.0;
  if ($lv <= 0) {
    return [false, 'Lift evento non valido (usa es. 0.10 per +10%).'];
  }

  $id = 'ev-' . md5($s . '|' . $e . '|' . $title . '|' . microtime(true));
  $pe['events'][] = [
    'id' => $id,
    'title' => $title,
    'start' => $s,
    'end' => $e,
    'lift' => $lv,
  ];
  $store['pricing_engine'] = pricing_engine_get_settings(['pricing_engine' => $pe]);
  return [true, null];
}

function pe_delete_event(&$store, $id) {
  $id = (string)$id;
  $pe = pricing_engine_get_settings($store);
  $events = $pe['events'] ?? [];
  if (!is_array($events) || !$events) {
    return [false, 'Nessun evento da rimuovere.'];
  }
  $new = [];
  $removed = false;
  foreach ($events as $ev) {
    if (is_array($ev) && (string)($ev['id'] ?? '') === $id) {
      $removed = true;
      continue;
    }
    $new[] = $ev;
  }
  if (!$removed) {
    return [false, 'Evento non trovato.'];
  }
  $pe['events'] = $new;
  $store['pricing_engine'] = pricing_engine_get_settings(['pricing_engine' => $pe]);
  return [true, null];
}
