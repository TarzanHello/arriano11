<?php
// Interactive Rome itineraries (Oct 2026, patch 10).
//
// Pages:   /itinerari (hub), /itinerari/roma-3-giorni, -4-giorni, -5-giorni (builder),
//          /itinerari/salvato/{CODE} (saved itinerary, noindex).
// Data:    places, stations and day plans live here (language-neutral);
//          texts (names, notes, monument guides) live in /i18n/itineraries/{lang}.php.
// Saving:  /api/itinerary_save.php writes /data/itineraries/{CODE}.json (never in the repo).

// Languages with a texts file. Others are redirected to the first available one.
function itin_languages() {
  static $langs = null;
  if ($langs === null) {
    $langs = [];
    foreach (['it', 'en', 'fr', 'de', 'es', 'pt', 'ru', 'ro', 'pl', 'se', 'dk', 'ja', 'ko', 'zh'] as $l) {
      if (file_exists(__DIR__ . '/../i18n/itineraries/' . $l . '.php')) $langs[] = $l;
    }
    if (!$langs) $langs = ['it'];
  }
  return $langs;
}

const VIA_ITIN_LEVELS = ['easy', 'medium', 'hard'];

// Home: Via Arriano 11 (same coordinates as the structured data on the home page)
const VIA_ITIN_HOME = [41.88541438, 12.53654213];

// Routes: hub + one builder page per trip length. Slugs are per language (Italian first).
function itin_routes() {
  return [
    'itineraries' => ['days' => 0, 'slugs' => ['it' => 'itinerari', 'en' => 'itineraries', 'fr' => 'itineraires', 'de' => 'reiserouten', 'es' => 'itinerarios', 'pt' => 'roteiros', 'ru' => 'marshruty', 'ro' => 'itinerarii', 'pl' => 'trasy', 'se' => 'resrutter', 'dk' => 'ruter', 'ja' => 'itineraries', 'ko' => 'itineraries', 'zh' => 'itineraries']],
    'itin_3' => ['days' => 3, 'slugs' => ['it' => 'itinerari/roma-3-giorni', 'en' => 'itineraries/rome-3-days', 'fr' => 'itineraires/rome-3-jours', 'de' => 'reiserouten/rom-3-tage', 'es' => 'itinerarios/roma-3-dias', 'pt' => 'roteiros/roma-3-dias', 'ru' => 'marshruty/rim-3-dnya', 'ro' => 'itinerarii/roma-3-zile', 'pl' => 'trasy/rzym-3-dni', 'se' => 'resrutter/rom-3-dagar', 'dk' => 'ruter/rom-3-dage', 'ja' => 'itineraries/rome-3-days', 'ko' => 'itineraries/rome-3-days', 'zh' => 'itineraries/rome-3-days']],
    'itin_4' => ['days' => 4, 'slugs' => ['it' => 'itinerari/roma-4-giorni', 'en' => 'itineraries/rome-4-days', 'fr' => 'itineraires/rome-4-jours', 'de' => 'reiserouten/rom-4-tage', 'es' => 'itinerarios/roma-4-dias', 'pt' => 'roteiros/roma-4-dias', 'ru' => 'marshruty/rim-4-dnya', 'ro' => 'itinerarii/roma-4-zile', 'pl' => 'trasy/rzym-4-dni', 'se' => 'resrutter/rom-4-dagar', 'dk' => 'ruter/rom-4-dage', 'ja' => 'itineraries/rome-4-days', 'ko' => 'itineraries/rome-4-days', 'zh' => 'itineraries/rome-4-days']],
    'itin_5' => ['days' => 5, 'slugs' => ['it' => 'itinerari/roma-5-giorni', 'en' => 'itineraries/rome-5-days', 'fr' => 'itineraires/rome-5-jours', 'de' => 'reiserouten/rom-5-tage', 'es' => 'itinerarios/roma-5-dias', 'pt' => 'roteiros/roma-5-dias', 'ru' => 'marshruty/rim-5-dney', 'ro' => 'itinerarii/roma-5-zile', 'pl' => 'trasy/rzym-5-dni', 'se' => 'resrutter/rom-5-dagar', 'dk' => 'ruter/rom-5-dage', 'ja' => 'itineraries/rome-5-days', 'ko' => 'itineraries/rome-5-days', 'zh' => 'itineraries/rome-5-days']],
    'itin_saved' => ['days' => 0, 'slugs' => ['it' => 'itinerari/salvato', 'en' => 'itineraries/saved', 'fr' => 'itineraires/sauvegarde', 'de' => 'reiserouten/gespeichert', 'es' => 'itinerarios/guardado', 'pt' => 'roteiros/guardado', 'ru' => 'marshruty/sokhranennyy', 'ro' => 'itinerarii/salvat', 'pl' => 'trasy/zapisana', 'se' => 'resrutter/sparad', 'dk' => 'ruter/gemt', 'ja' => 'itineraries/saved', 'ko' => 'itineraries/saved', 'zh' => 'itineraries/saved']],
  ];
}

function is_itin_route($route) {
  return isset(itin_routes()[(string)$route]);
}

function itin_slug($route, $lang) {
  $r = itin_routes();
  if (!isset($r[$route])) return '';
  return (string)($r[$route]['slugs'][$lang] ?? $r[$route]['slugs']['it']);
}

// Route for a request slug (without language prefix), '' if unknown.
function itin_route_for_slug($slug) {
  $slug = trim((string)$slug, '/');
  foreach (itin_routes() as $route => $info) {
    foreach ($info['slugs'] as $s) {
      if ($slug === $s) return $route;
      if ($route === 'itin_saved' && strpos($slug, $s . '/') === 0) return $route;
    }
  }
  return '';
}

// ------------------------------------------------------------
// Places. guide = key of the monument guide in the texts file; book = needs a ticket booked ahead.
// ------------------------------------------------------------
function itin_places() {
  return [
    // Day 1 — Ancient Rome
    'colosseo'          => ['lat' => 41.89021, 'lng' => 12.49223, 'inner' => 0.8, 'guide' => 'colosseo', 'book' => true],
    'palatino'          => ['lat' => 41.88830, 'lng' => 12.48820, 'inner' => 1.5, 'guide' => 'palatino'],
    'foro'              => ['lat' => 41.89246, 'lng' => 12.48532, 'inner' => 1.5, 'guide' => 'foro'],
    'fori_imperiali'    => ['lat' => 41.89580, 'lng' => 12.48430, 'inner' => 0.5, 'guide' => 'fori_imperiali'],
    'mercati_traiano'   => ['lat' => 41.89570, 'lng' => 12.48620, 'inner' => 0.6, 'guide' => 'fori_imperiali'],
    'campidoglio'       => ['lat' => 41.89330, 'lng' => 12.48290, 'guide' => 'campidoglio'],
    'capitolini'        => ['lat' => 41.89300, 'lng' => 12.48230, 'inner' => 0.8, 'guide' => 'campidoglio'],
    'vittoriano'        => ['lat' => 41.89460, 'lng' => 12.48310, 'guide' => 'vittoriano'],
    'san_clemente'      => ['lat' => 41.88930, 'lng' => 12.49750, 'guide' => 'san_clemente'],
    'circo_massimo'     => ['lat' => 41.88610, 'lng' => 12.48510, 'inner' => 0.6, 'guide' => 'circo_massimo'],
    'aventino'          => ['lat' => 41.88440, 'lng' => 12.47890, 'inner' => 0.8, 'guide' => 'aventino'],
    // Day 2 — Vatican
    'vaticani'          => ['lat' => 41.90650, 'lng' => 12.45360, 'inner' => 2.0, 'guide' => 'vaticani', 'book' => true],
    'san_pietro'        => ['lat' => 41.90220, 'lng' => 12.45390, 'inner' => 0.5, 'guide' => 'san_pietro'],
    'cupola'            => ['lat' => 41.90210, 'lng' => 12.45330, 'inner' => 0.6, 'guide' => 'san_pietro'],
    'castel_santangelo' => ['lat' => 41.90310, 'lng' => 12.46630, 'inner' => 0.6, 'guide' => 'castel_santangelo', 'book' => true],
    'coronari'          => ['lat' => 41.89990, 'lng' => 12.47060, 'guide' => null],
    // Day 3 — Baroque Rome
    'spagna'            => ['lat' => 41.90580, 'lng' => 12.48230, 'guide' => 'spagna'],
    'trevi'             => ['lat' => 41.90090, 'lng' => 12.48330, 'guide' => 'trevi'],
    'pantheon'          => ['lat' => 41.89860, 'lng' => 12.47690, 'guide' => 'pantheon', 'book' => true],
    'sant_ignazio'      => ['lat' => 41.89900, 'lng' => 12.47970, 'guide' => 'sant_ignazio'],
    'san_luigi'         => ['lat' => 41.89960, 'lng' => 12.47460, 'guide' => 'san_luigi'],
    'navona'            => ['lat' => 41.89920, 'lng' => 12.47310, 'inner' => 0.3, 'guide' => 'navona'],
    'campo_fiori'       => ['lat' => 41.89560, 'lng' => 12.47220, 'inner' => 0.4, 'guide' => 'campo_fiori'],
    'largo_argentina'   => ['lat' => 41.89550, 'lng' => 12.47680, 'guide' => null],
    'popolo'            => ['lat' => 41.91070, 'lng' => 12.47630, 'inner' => 0.3, 'guide' => 'popolo'],
    'pincio'            => ['lat' => 41.91130, 'lng' => 12.47970, 'guide' => 'villa_borghese'],
    'ara_pacis'         => ['lat' => 41.90630, 'lng' => 12.47540, 'guide' => 'ara_pacis'],
    'ghetto'            => ['lat' => 41.89250, 'lng' => 12.47820, 'inner' => 0.6, 'guide' => 'ghetto'],
    'isola_tiberina'    => ['lat' => 41.89050, 'lng' => 12.47740, 'guide' => 'ghetto'],
    // Day 4 — Villa Borghese & Trastevere
    'galleria_borghese' => ['lat' => 41.91420, 'lng' => 12.49220, 'inner' => 0.4, 'guide' => 'galleria_borghese', 'book' => true],
    'villa_borghese'    => ['lat' => 41.91270, 'lng' => 12.48460, 'inner' => 2.0, 'guide' => 'villa_borghese'],
    'trastevere'        => ['lat' => 41.88950, 'lng' => 12.46990, 'inner' => 1.0, 'guide' => 'trastevere'],
    'santa_cecilia'     => ['lat' => 41.88760, 'lng' => 12.47600, 'guide' => 'trastevere'],
    'farnesina'         => ['lat' => 41.89350, 'lng' => 12.46750, 'inner' => 0.2, 'guide' => 'farnesina'],
    'gianicolo'         => ['lat' => 41.89160, 'lng' => 12.46140, 'inner' => 1.0, 'guide' => 'gianicolo'],
    // Day 5 — Appian Way
    'caracalla'         => ['lat' => 41.87900, 'lng' => 12.49250, 'inner' => 1.0, 'guide' => 'caracalla'],
    'porta_sansebastiano' => ['lat' => 41.87350, 'lng' => 12.50150, 'inner' => 0.6, 'guide' => 'appia'],
    'quo_vadis'         => ['lat' => 41.86760, 'lng' => 12.50300, 'guide' => 'appia'],
    'san_callisto'      => ['lat' => 41.85880, 'lng' => 12.51060, 'inner' => 0.6, 'guide' => 'catacombe'],
    'san_sebastiano'    => ['lat' => 41.85560, 'lng' => 12.51550, 'inner' => 0.5, 'guide' => 'catacombe'],
    'cecilia_metella'   => ['lat' => 41.85210, 'lng' => 12.52260, 'inner' => 0.4, 'guide' => 'appia'],
    'appia_basoli'      => ['lat' => 41.84400, 'lng' => 12.53300, 'inner' => 2.5, 'guide' => 'appia'],
    'acquedotti'        => ['lat' => 41.85300, 'lng' => 12.55600, 'inner' => 1.5, 'guide' => 'acquedotti'],
    // Lunch breaks (selectable like any stop)
    'lunch_monti'       => ['lat' => 41.89500, 'lng' => 12.49200, 'meal' => true],
    'lunch_borgo'       => ['lat' => 41.90300, 'lng' => 12.46000, 'meal' => true],
    'lunch_prati'       => ['lat' => 41.90700, 'lng' => 12.46000, 'meal' => true],
    'lunch_pantheon'    => ['lat' => 41.89900, 'lng' => 12.47550, 'meal' => true],
    'lunch_popolo'      => ['lat' => 41.90950, 'lng' => 12.47700, 'meal' => true],
    'lunch_trastevere'  => ['lat' => 41.88900, 'lng' => 12.47100, 'meal' => true],
    'lunch_appia'       => ['lat' => 41.85350, 'lng' => 12.52050, 'meal' => true],
    'lunch_appia_nord'  => ['lat' => 41.86500, 'lng' => 12.50500, 'meal' => true],
  ];
}

// Metro / bus / tram stops where the day starts and ends.
function itin_stations() {
  return [
    'colosseo_c'      => [41.89200, 12.48930],
    'circo_massimo_b' => [41.88360, 12.48800],
    'ottaviano'       => [41.90920, 12.45800],
    'lepanto'         => [41.91310, 12.46450],
    'spagna'          => [41.90630, 12.48370],
    'flaminio'        => [41.91160, 12.47620],
    'barberini'       => [41.90380, 12.48870],
    'tram_belli'      => [41.88990, 12.47430],
    'porta_metronia'  => [41.88070, 12.49740],
    'bus_fosse'       => [41.85960, 12.51120],
    'bus_porta'       => [41.87280, 12.50200],
    'bus_cecilia'     => [41.85150, 12.52350],
    'giulio_agricola' => [41.85840, 12.56280],
  ];
}

// ------------------------------------------------------------
// Day plans. Each level: start time, first stop reached by transit (from/go minutes),
// ordered stops [place, minutes, optional 'leg' = minutes of transit before this stop],
// last stop back to a station (to/back minutes of transit home).
// The day's pool of optional stops = all stops of the three levels.
// ------------------------------------------------------------
function itin_days() {
  return [
    1 => ['levels' => [
      'easy' => ['start' => '09:30', 'from' => 'colosseo_c', 'go' => 25, 'to' => 'colosseo_c', 'back' => 25, 'stops' => [
        ['colosseo', 120], ['lunch_monti', 75], ['foro', 75], ['campidoglio', 30], ['vittoriano', 30],
      ]],
      'medium' => ['start' => '08:00', 'from' => 'colosseo_c', 'go' => 25, 'to' => 'colosseo_c', 'back' => 25, 'stops' => [
        ['colosseo', 105], ['palatino', 75], ['foro', 75], ['lunch_monti', 75], ['fori_imperiali', 30], ['capitolini', 120], ['vittoriano', 45],
      ]],
      'hard' => ['start' => '07:45', 'from' => 'colosseo_c', 'go' => 25, 'to' => 'circo_massimo_b', 'back' => 30, 'stops' => [
        ['colosseo', 150], ['palatino', 60], ['foro', 60], ['lunch_monti', 45], ['mercati_traiano', 75], ['san_clemente', 60], ['circo_massimo', 20], ['aventino', 45],
      ]],
    ]],
    2 => ['levels' => [
      'easy' => ['start' => '09:00', 'from' => 'ottaviano', 'go' => 40, 'to' => 'lepanto', 'back' => 40, 'stops' => [
        ['vaticani', 180], ['lunch_borgo', 75], ['san_pietro', 75], ['castel_santangelo', 30],
      ]],
      'medium' => ['start' => '07:45', 'from' => 'ottaviano', 'go' => 40, 'to' => 'lepanto', 'back' => 40, 'stops' => [
        ['vaticani', 210], ['san_pietro', 60], ['lunch_borgo', 75], ['castel_santangelo', 120],
      ]],
      'hard' => ['start' => '06:45', 'from' => 'ottaviano', 'go' => 40, 'to' => 'spagna', 'back' => 35, 'stops' => [
        ['san_pietro', 30], ['cupola', 90], ['vaticani', 240], ['lunch_prati', 60], ['castel_santangelo', 120], ['coronari', 30],
      ]],
    ]],
    3 => ['levels' => [
      'easy' => ['start' => '09:30', 'from' => 'spagna', 'go' => 30, 'to' => 'colosseo_c', 'back' => 25, 'stops' => [
        ['spagna', 30], ['trevi', 45], ['pantheon', 45], ['lunch_pantheon', 75], ['navona', 45], ['campo_fiori', 30], ['largo_argentina', 15],
      ]],
      'medium' => ['start' => '08:15', 'from' => 'flaminio', 'go' => 35, 'to' => 'colosseo_c', 'back' => 25, 'stops' => [
        ['popolo', 45], ['spagna', 30], ['trevi', 30], ['sant_ignazio', 20], ['pantheon', 30], ['san_luigi', 20], ['lunch_pantheon', 75], ['navona', 30], ['campo_fiori', 40], ['ghetto', 45],
      ]],
      'hard' => ['start' => '07:00', 'from' => 'barberini', 'go' => 30, 'to' => 'colosseo_c', 'back' => 25, 'stops' => [
        ['trevi', 25], ['spagna', 20], ['pincio', 20], ['popolo', 45], ['ara_pacis', 60], ['sant_ignazio', 20], ['pantheon', 30], ['san_luigi', 20], ['lunch_pantheon', 60], ['navona', 45], ['campo_fiori', 45], ['ghetto', 40], ['isola_tiberina', 20],
      ]],
    ]],
    4 => ['levels' => [
      'easy' => ['start' => '09:15', 'from' => 'spagna', 'go' => 30, 'to' => 'tram_belli', 'back' => 45, 'stops' => [
        ['galleria_borghese', 120], ['villa_borghese', 120], ['pincio', 30], ['trastevere', 120, 'leg' => 25],
      ]],
      'medium' => ['start' => '08:15', 'from' => 'spagna', 'go' => 30, 'to' => 'tram_belli', 'back' => 45, 'stops' => [
        ['galleria_borghese', 120], ['villa_borghese', 45], ['pincio', 20], ['lunch_popolo', 75], ['santa_cecilia', 30, 'leg' => 30], ['trastevere', 75], ['gianicolo', 60],
      ]],
      'hard' => ['start' => '08:00', 'from' => 'spagna', 'go' => 30, 'to' => 'tram_belli', 'back' => 45, 'stops' => [
        ['galleria_borghese', 120], ['villa_borghese', 40], ['pincio', 20], ['lunch_trastevere', 60], ['santa_cecilia', 30], ['trastevere', 45], ['farnesina', 60], ['gianicolo', 75],
      ]],
    ]],
    5 => ['levels' => [
      'easy' => ['start' => '09:30', 'from' => 'bus_fosse', 'go' => 45, 'to' => 'bus_cecilia', 'back' => 50, 'stops' => [
        ['san_callisto', 75], ['san_sebastiano', 45], ['cecilia_metella', 45], ['lunch_appia', 75],
      ]],
      'medium' => ['start' => '08:45', 'from' => 'bus_porta', 'go' => 40, 'to' => 'bus_cecilia', 'back' => 50, 'stops' => [
        ['porta_sansebastiano', 60], ['quo_vadis', 15], ['san_callisto', 75], ['san_sebastiano', 60], ['lunch_appia', 75], ['cecilia_metella', 60], ['appia_basoli', 60],
      ]],
      'hard' => ['start' => '08:45', 'from' => 'porta_metronia', 'go' => 25, 'to' => 'giulio_agricola', 'back' => 30, 'stops' => [
        ['caracalla', 75], ['porta_sansebastiano', 45], ['quo_vadis', 15], ['lunch_appia_nord', 60], ['san_callisto', 60], ['san_sebastiano', 30], ['cecilia_metella', 45], ['appia_basoli', 30], ['acquedotti', 45, 'leg' => 35],
      ]],
    ]],
  ];
}

// All stops a visitor can pick on a day: [place => ['dur' => min, 'leg' => min|0, 'level' => level the note comes from]]
function itin_day_pool($day) {
  $days = itin_days();
  if (!isset($days[$day])) return [];
  $pool = [];
  // medium first: its durations are the most "average" for stops added from another level
  foreach (['medium', 'easy', 'hard'] as $lv) {
    foreach ($days[$day]['levels'][$lv]['stops'] as $s) {
      if (isset($pool[$s[0]])) continue;
      $pool[$s[0]] = ['dur' => (int)$s[1], 'leg' => (int)($s['leg'] ?? 0), 'level' => $lv];
    }
  }
  return $pool;
}

// Default selection for a level, in order.
function itin_level_default($day, $level) {
  $days = itin_days();
  if (!isset($days[$day]['levels'][$level])) return [];
  return array_map(function ($s) { return $s[0]; }, $days[$day]['levels'][$level]['stops']);
}

// Stop parameters for a day+level: the level's own values if the stop belongs to it, else the pool's.
function itin_stop_params($day, $level, $place) {
  $days = itin_days();
  foreach ($days[$day]['levels'][$level]['stops'] ?? [] as $s) {
    if ($s[0] === $place) return ['dur' => (int)$s[1], 'leg' => (int)($s['leg'] ?? 0), 'level' => $level];
  }
  $pool = itin_day_pool($day);
  return $pool[$place] ?? ['dur' => 30, 'leg' => 0, 'level' => $level];
}

// ------------------------------------------------------------
// Distances and schedule (same rules as assets/js/itinerary.js — keep them in sync).
// Walking: straight line × 1.3 (Rome's streets are not straight), 4.5 km/h, rounded up to 5 minutes.
// ------------------------------------------------------------
function itin_km($a, $b) {
  $r = 6371.0;
  $dLat = deg2rad($b[0] - $a[0]);
  $dLng = deg2rad($b[1] - $a[1]);
  $h = sin($dLat / 2) ** 2 + cos(deg2rad($a[0])) * cos(deg2rad($b[0])) * sin($dLng / 2) ** 2;
  return 2 * $r * asin(min(1, sqrt($h))) * 1.3;
}

function itin_walk_min($km) {
  if ($km < 0.05) return 0;
  return (int)(ceil(($km / 4.5 * 60) / 5) * 5);
}

function itin_hhmm($minutes) {
  $minutes = (int)round($minutes);
  return sprintf('%02d:%02d', intdiv($minutes, 60) % 24, $minutes % 60);
}

// Returns ['start','end','km','items' => [[place, arrive, leave, walkMin, walkKm, transitMin]...]]
// $custom: the visitor's own stops (plan['custom']), id => [lat, lng, dur, ...]
function itin_schedule($day, $level, $order, $custom = []) {
  $days = itin_days();
  $places = itin_places();
  foreach ((array)$custom as $cid => $c) {
    $places[$cid] = ['lat' => (float)$c['lat'], 'lng' => (float)$c['lng'], 'custom' => true, 'dur' => (int)$c['dur']];
  }
  $stations = itin_stations();
  $lv = $days[$day]['levels'][$level] ?? null;
  if (!$lv) return null;

  [$h, $m] = array_map('intval', explode(':', $lv['start']));
  $t = $h * 60 + $m + (int)$lv['go'];
  $pos = $stations[$lv['from']];
  $km = 0.0;
  $items = [];
  foreach ($order as $p) {
    if (!isset($places[$p])) continue;
    $sp = !empty($places[$p]['custom']) ? ['dur' => $places[$p]['dur'], 'leg' => 0, 'level' => $level] : itin_stop_params($day, $level, $p);
    $here = [$places[$p]['lat'], $places[$p]['lng']];
    $walkKm = 0.0; $walkMin = 0; $transit = 0;
    if ($sp['leg'] > 0 && $items) {
      $transit = $sp['leg'];
      $t += $transit;
    } else {
      $walkKm = itin_km($pos, $here);
      $walkMin = itin_walk_min($walkKm);
      $km += $walkKm;
      $t += $walkMin;
    }
    $km += (float)($places[$p]['inner'] ?? 0);
    $items[] = ['place' => $p, 'arrive' => $t, 'leave' => $t + $sp['dur'], 'walk_min' => $walkMin, 'walk_km' => $walkKm, 'transit_min' => $transit, 'dur' => $sp['dur']];
    $t += $sp['dur'];
    $pos = $here;
  }
  $endStation = $stations[$lv['to']];
  $lastWalk = itin_km($pos, $endStation);
  $km += $lastWalk;
  $t += itin_walk_min($lastWalk);
  $home = $t + (int)$lv['back'];
  return [
    'start' => $h * 60 + $m,
    'home' => $home,
    'km' => $km,
    'last_walk_min' => itin_walk_min($lastWalk),
    'items' => $items,
  ];
}

// ------------------------------------------------------------
// Texts
// ------------------------------------------------------------
function itin_texts($lang = null) {
  static $cache = [];
  if ($lang === null) $lang = function_exists('current_lang') ? current_lang() : 'it';
  if (!in_array($lang, itin_languages(), true)) $lang = itin_languages()[0];
  if (!isset($cache[$lang])) {
    $f = __DIR__ . '/../i18n/itineraries/' . $lang . '.php';
    $d = file_exists($f) ? (require $f) : [];
    $cache[$lang] = is_array($d) ? $d : [];
  }
  return $cache[$lang];
}

function itin_t($path, $lang = null) {
  $cur = itin_texts($lang);
  foreach (explode('.', (string)$path) as $p) {
    if (!is_array($cur) || !array_key_exists($p, $cur)) return '';
    $cur = $cur[$p];
  }
  return is_string($cur) ? $cur : (is_array($cur) ? $cur : '');
}

// Last change of the itinerary data or texts (sitemap)
function itin_mtime() {
  $m = (int)@filemtime(__FILE__);
  foreach (glob(__DIR__ . '/../i18n/itineraries/*.php') ?: [] as $f) $m = max($m, (int)@filemtime($f));
  foreach (['itineraries.php'] as $f) $m = max($m, (int)@filemtime(__DIR__ . '/../' . $f));
  return $m;
}

// ------------------------------------------------------------
// Saved itineraries: /data/itineraries/{CODE}.json
// Code: 8 characters without look-alikes (no 0/O, 1/I/L), shown as XXXX-XXXX.
// ------------------------------------------------------------
const VIA_ITIN_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

function itin_code_normalize($code) {
  $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$code));
  if (strlen($code) !== 8) return '';
  for ($i = 0; $i < 8; $i++) {
    if (strpos(VIA_ITIN_ALPHABET, $code[$i]) === false) return '';
  }
  return $code;
}

function itin_code_display($code) {
  return substr($code, 0, 4) . '-' . substr($code, 4, 4);
}

function itin_dir() {
  $dir = __DIR__ . '/../data/itineraries';
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  if (function_exists('ensure_private_dir_protected')) ensure_private_dir_protected($dir);
  return $dir;
}

function itin_new_code() {
  $alpha = VIA_ITIN_ALPHABET;
  $n = strlen($alpha);
  for ($try = 0; $try < 20; $try++) {
    $bytes = random_bytes(8);
    $code = '';
    for ($i = 0; $i < 8; $i++) $code .= $alpha[ord($bytes[$i]) % $n];
    if (!file_exists(itin_dir() . '/' . $code . '.json')) return $code;
  }
  return '';
}

// ------------------------------------------------------------
// The visitor's own stops (patch 12): private, saved only inside their itinerary.
// plan['custom'] = ['u1' => ['day' => 2, 'name' => '…', 'tip' => '…', 'lat' => 41.9, 'lng' => 12.4, 'dur' => 30], …]
// ------------------------------------------------------------
const VIA_ITIN_CUSTOM_MAX = 12;
const VIA_ITIN_CUSTOM_DURS = [15, 30, 45, 60, 90, 120];

// Rome and surroundings (Ostia, Castelli excluded on purpose: the itineraries are walking days)
function itin_in_rome($lat, $lng) {
  return $lat >= 41.65 && $lat <= 42.10 && $lng >= 12.20 && $lng <= 12.90;
}

function itin_clean_text($s, $max) {
  $s = (string)$s;
  if ($s === '' || !preg_match('//u', $s)) return '';
  $s = strip_tags($s);
  $s = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s);
  $s = trim((string)preg_replace('/\s+/u', ' ', $s));
  return function_exists('mb_substr') ? mb_substr($s, 0, $max, 'UTF-8') : substr($s, 0, $max);
}

function itin_clean_custom($list, $n) {
  $out = [];
  if (!is_array($list)) return $out;
  foreach ($list as $key => $c) {
    if (count($out) >= VIA_ITIN_CUSTOM_MAX) break;
    if (!is_array($c)) continue;
    $id = (string)($c['id'] ?? $key);
    if (!preg_match('/^u[1-9][0-9]?$/', $id) || isset($out[$id])) continue;
    $lat = (float)($c['lat'] ?? 0);
    $lng = (float)($c['lng'] ?? 0);
    if (!itin_in_rome($lat, $lng)) continue;
    $name = itin_clean_text($c['name'] ?? '', 60);
    if ($name === '') continue;
    $dur = (int)($c['dur'] ?? 30);
    if (!in_array($dur, VIA_ITIN_CUSTOM_DURS, true)) $dur = 30;
    $day = (int)($c['day'] ?? 0);
    if ($day < 1 || $day > $n) continue;
    $out[$id] = ['day' => $day, 'name' => $name, 'tip' => itin_clean_text($c['tip'] ?? '', 280), 'lat' => round($lat, 6), 'lng' => round($lng, 6), 'dur' => $dur];
  }
  return $out;
}

// Checks a plan sent by the browser and returns a clean copy, or null.
// Plan: ['n' => 3..5, 'days' => [ ['level' => 'easy', 'stops' => ['colosseo', 'u1', ...]], ... ], 'custom' => [...]]
function itin_clean_plan($plan) {
  if (!is_array($plan)) return null;
  $n = (int)($plan['n'] ?? 0);
  if ($n < 3 || $n > 5) return null;
  $days = $plan['days'] ?? null;
  if (!is_array($days) || count($days) !== $n) return null;
  $custom = itin_clean_custom($plan['custom'] ?? [], $n);
  $out = ['n' => $n, 'days' => [], 'custom' => $custom];
  $i = 0;
  foreach (array_values($days) as $d) {
    $i++;
    if (!is_array($d)) return null;
    $level = (string)($d['level'] ?? '');
    if (!in_array($level, VIA_ITIN_LEVELS, true)) return null;
    $pool = itin_day_pool($i);
    $stops = [];
    foreach ((array)($d['stops'] ?? []) as $s) {
      $s = (string)$s;
      $ok = isset($pool[$s]) || (isset($custom[$s]) && $custom[$s]['day'] === $i);
      if ($ok && !in_array($s, $stops, true)) $stops[] = $s;
      if (count($stops) >= 20) break;
    }
    $out['days'][] = ['level' => $level, 'stops' => $stops];
  }
  return $out;
}

// $code: overwrite this existing itinerary (the visitor edited it and kept the same code: game points stay)
function itin_save($plan, $lang, $code = '') {
  $created = date('c');
  $code = itin_code_normalize($code);
  if ($code !== '') {
    $old = itin_load($code);
    if (!$old || (int)$old['n'] !== (int)$plan['n']) return '';
    if ($old['created'] !== '') $created = $old['created'];
  } else {
    $code = itin_new_code();
  }
  if ($code === '') return '';
  $rec = $plan + ['v' => 1, 'lang' => (string)$lang, 'created' => $created, 'updated' => date('c')];
  $tmp = itin_dir() . '/' . $code . '.tmp.' . bin2hex(random_bytes(4));
  if (@file_put_contents($tmp, json_encode($rec, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) return '';
  if (!@rename($tmp, itin_dir() . '/' . $code . '.json')) { @unlink($tmp); return ''; }
  return $code;
}

function itin_load($code) {
  $code = itin_code_normalize($code);
  if ($code === '') return null;
  $f = itin_dir() . '/' . $code . '.json';
  if (!is_file($f)) return null;
  $rec = json_decode((string)@file_get_contents($f), true);
  $plan = itin_clean_plan($rec);
  if (!$plan) return null;
  $plan['code'] = $code;
  $plan['created'] = (string)($rec['created'] ?? '');
  return $plan;
}

// Data sent to the browser for the builder (places with coordinates + day plans).
function itin_js_payload($nDays, $lang) {
  $places = itin_places();
  $T = itin_texts($lang);
  $out = ['home' => VIA_ITIN_HOME, 'stations' => itin_stations(), 'places' => [], 'days' => [], 'n' => $nDays];
  foreach ($places as $id => $p) {
    $out['places'][$id] = [
      'lat' => $p['lat'], 'lng' => $p['lng'],
      'name' => (string)($T['places'][$id] ?? $id),
      'guide' => $p['guide'] ?? null,
      'book' => !empty($p['book']),
      'meal' => !empty($p['meal']),
      'inner' => (float)($p['inner'] ?? 0),
    ];
  }
  $days = itin_days();
  for ($d = 1; $d <= $nDays; $d++) {
    $lv = [];
    foreach (VIA_ITIN_LEVELS as $l) {
      $L = $days[$d]['levels'][$l];
      $lv[$l] = ['start' => $L['start'], 'from' => $L['from'], 'go' => $L['go'], 'to' => $L['to'], 'back' => $L['back'], 'stops' => array_map(function ($s) { return [$s[0], (int)$s[1], (int)($s['leg'] ?? 0)]; }, $L['stops'])];
    }
    $out['days'][$d] = ['levels' => $lv];
  }
  return $out;
}

// Navigation game: points, badges, leaderboard (patch 12)
require_once __DIR__ . '/itin_game.php';
