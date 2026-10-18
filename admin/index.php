<?php
require_once __DIR__ . '/../lib/lib.php';
require_once __DIR__ . '/../lib/stripe.php';

require_admin();

// Security headers + no-cache for admin pages
send_security_headers(['admin' => true, 'no_store' => true]);

$store = load_store();

// Ensure we have a (private) token for exporting our iCal feed.
if (empty($store['settings']['ical_export_secret'])) {
  $store['settings']['ical_export_secret'] = bin2hex(random_bytes_fallback(16));
  // Best-effort persist (if /data is not writable, admin will show the error elsewhere).
  @save_store($store);
}

// Cleanup expired payment holds
prune_expired_payment_holds_locked($store);

// Keep availability aligned with Booking (iCal)
maybe_auto_sync($store);

$siteName = $store['settings']['site_name'] ?? 'Via Arriano 11';
$tab = isset($_GET['tab']) ? (string)$_GET['tab'] : 'calendar';
if (!in_array($tab, ['calendar','pricing_engine','bookings','reviews','sync','settings'], true)) {
  $tab = 'calendar';
}


$msg = isset($_GET['msg']) ? (string)$_GET['msg'] : '';
$err = isset($_GET['err']) ? (string)$_GET['err'] : '';
$csrf = csrf_token();

function month_title_it($ym) {
  // Basic Italian month names
  static $months = [
    1=>'Gennaio',2=>'Febbraio',3=>'Marzo',4=>'Aprile',5=>'Maggio',6=>'Giugno',7=>'Luglio',8=>'Agosto',9=>'Settembre',10=>'Ottobre',11=>'Novembre',12=>'Dicembre'
  ];
  if (!preg_match('/^(\d{4})-(\d{2})$/', $ym, $m)) return $ym;
  $y = (int)$m[1];
  $mo = (int)$m[2];
  return ($months[$mo] ?? $ym) . ' ' . $y;
}

?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin · <?php echo e($siteName); ?></title>
  <link rel="icon" href="/favicon.ico">
  <?php $css_v = @filemtime(__DIR__ . '/../assets/css/style.css') ?: 1; ?>
  <link rel="stylesheet" href="/assets/css/style.css?v=<?php echo e((string)$css_v); ?>">
</head>
<body>
  <header class="site-header">
    <div class="container header-inner">
      <div class="brand">
        <img src="/assets/brand/logo.png" alt="<?php echo e($siteName); ?>" width="44" height="44">
        <div>
          <div class="brand-title"><?php echo e($siteName); ?> · Admin</div>
          <div class="brand-sub">Gestione calendario, prezzi, disponibilità e prenotazioni</div>
        </div>
      </div>
      <div class="actions">
        <a class="btn" href="/index.php">Vai al sito</a>
        <form method="post" action="/admin/logout.php" style="display:inline; margin:0;">
          <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
          <button class="cta" type="submit">Esci</button>
        </form>
      </div>
    </div>
  </header>

  <main class="site-main">
    <div class="container" style="padding:18px 0 26px;">

      <nav class="admin-nav" style="display:flex; gap:10px; flex-wrap:wrap; margin:0 0 14px;">
        <a class="<?php echo $tab==='calendar'?'is-active':''; ?>" href="?tab=calendar">Calendario</a>
        <a class="<?php echo $tab==='pricing_engine'?'is-active':''; ?>" href="?tab=pricing_engine">Motore prezzi</a>
        <a class="<?php echo $tab==='bookings'?'is-active':''; ?>" href="?tab=bookings">Prenotazioni</a>
        <a class="<?php echo $tab==='reviews'?'is-active':''; ?>" href="?tab=reviews">Recensioni</a>
        <a class="<?php echo $tab==='sync'?'is-active':''; ?>" href="?tab=sync">Sync iCal</a>
        <a class="<?php echo $tab==='settings'?'is-active':''; ?>" href="?tab=settings">Impostazioni</a>
      </nav>

      <?php if ($err): ?>
        <div class="notice danger" style="margin-bottom:14px;"><?php echo e($err); ?></div>
      <?php endif; ?>
      <?php if ($msg): ?>
        <div class="notice" style="margin-bottom:14px;"><?php echo e($msg); ?></div>
      <?php endif; ?>

      <?php if ($tab === 'calendar'): ?>
        <?php
          $month = isset($_GET['month']) ? (string)$_GET['month'] : date('Y-m');
          if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
          }
          $data = availability_for_month($store, $month);
          $days = $data['days'] ?? [];

          $dt = new DateTime($month . '-01');
          $prev = (clone $dt)->modify('-1 month')->format('Y-m');
          $next = (clone $dt)->modify('+1 month')->format('Y-m');

          $daysInMonth = (int)$dt->format('t');
          $firstDow = (int)$dt->format('N'); // 1=Mon
          $currency = (string)($store['settings']['currency'] ?? 'EUR');
          $globalMin = (int)($store['settings']['min_nights'] ?? 1);
        ?>

        <div class="card" style="padding:18px;">
          <div class="cal-nav" style="justify-content: space-between; margin-bottom: 12px;">
            <a class="btn" href="?tab=calendar&amp;month=<?php echo e($prev); ?>" aria-label="Mese precedente">‹</a>
            <div style="font-weight:900; font-size:18px;"><?php echo e(month_title_it($month)); ?></div>
            <a class="btn" href="?tab=calendar&amp;month=<?php echo e($next); ?>" aria-label="Mese successivo">›</a>
          </div>

          <p class="muted" style="margin:0 0 14px;">
            Seleziona uno o più giorni (Shift+click per selezionare un intervallo) e applica:
            <strong>prezzo</strong>, <strong>apertura/chiusura</strong>, <strong>durata minima del soggiorno</strong>.
          </p>

          <div class="admin-cal-layout">
            <div class="admin-calendar">
              <table class="calendar" id="adminCalTable" aria-label="Calendario admin">
                <thead>
                  <tr>
                    <th>Lun</th><th>Mar</th><th>Mer</th><th>Gio</th><th>Ven</th><th>Sab</th><th>Dom</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                    $day = 1;
                    $cell = 1;

                    // First row
                    echo '<tr>';
                    for ($i=1; $i<$firstDow; $i++) {
                      echo '<td></td>';
                      $cell++;
                    }

                    while ($day <= $daysInMonth) {
                      $dateYmd = $month . '-' . str_pad((string)$day, 2, '0', STR_PAD_LEFT);
                      $info = $days[$dateYmd] ?? null;
                      $status = $info['status'] ?? 'available';
                      $price = isset($info['price']) ? (float)$info['price'] : 0.0;
                      $minN = isset($info['min_nights']) ? (int)$info['min_nights'] : $globalMin;

                      // Align admin day classes with the same booking rules used on the public calendar.
                      // In particular: "turnover" days (arrival/departure) are NOT fully booked.
                      // They must remain selectable/open in admin like they are in the user calendar.
                      // FIX: colour by NIGHT. A day whose night is occupied is shown as occupied
                      // (before, the arrival day of a booking looked "free", so 1-night stays
                      // were invisible). Arrival/departure days are shown half-and-half.
                      $nightBusy = !empty($info['night_busy']);
                      $prevBusy = !empty($info['prev_night_busy']);
                      $cls = 'day ';
                      if ($status === 'closed') {
                        $cls .= 'is-closed';
                      } elseif ($nightBusy && $prevBusy) {
                        $cls .= 'is-booked';
                      } elseif ($nightBusy) {
                        $cls .= 'is-available is-half-in';   // a stay starts tonight
                      } elseif ($prevBusy) {
                        $cls .= 'is-available is-half-out';  // a stay ends this morning
                      } else {
                        $cls .= 'is-available';
                      }

                      $hasDaily = !empty($store['daily'][$dateYmd]) && is_array($store['daily'][$dateYmd]);

                      // Show both full and compact price strings.
                      // Full stays visible on wide screens; compact is used on mobile portrait to avoid truncation.
                      $priceHtml = '';
                      if ($price > 0) {
                        $fullP = format_currency($price, $currency);
                        $compactP = format_currency_compact($price, $currency);
                        $priceHtml = '<span class="price" title="' . e($fullP) . '">' .
                          '<span class="price-full">' . e($fullP) . '</span>' .
                          '<span class="price-compact">' . e($compactP) . '</span>' .
                          '</span>';
                      }
                      $tagHtml = '';
                      if ($hasDaily) {
                        $tagHtml .= '<span class="small" style="position:absolute; left:10px; bottom:10px; color:rgba(37,99,235,.9); font-weight:900;">●</span>';
                      }
                      if ($minN !== $globalMin) {
                        $tagHtml .= '<span class="small" style="position:absolute; left:10px; bottom:26px; color:var(--muted); font-weight:900;">min ' . e((string)$minN) . '</span>';
                      }

                      echo '<td>'; 
                      echo '<button type="button" class="' . e($cls) . '" data-date="' . e($dateYmd) . '">';
                      echo '<span class="num">' . e((string)$day) . '</span>';
                      echo $priceHtml;
                      echo $tagHtml;
                      echo '</button>';
                      echo '</td>';

                      if (($cell % 7) === 0) {
                        echo '</tr>';
                        if ($day < $daysInMonth) echo '<tr>';
                      }

                      $day++;
                      $cell++;
                    }

                    // Trailing empty cells
                    while (($cell-1) % 7 !== 0) {
                      echo '<td></td>';
                      $cell++;
                      if (($cell-1) % 7 === 0) echo '</tr>';
                    }
                  ?>
                </tbody>
              </table>

              <div class="legend" style="margin-top:12px;">
                <div class="item"><span class="swatch available"></span> Aperto</div>
                <div class="item"><span class="swatch booked"></span> Notte occupata</div>
                <div class="item"><span class="swatch half"></span> Giorno di arrivo / partenza</div>
                <div class="item"><span class="swatch closed"></span> Chiuso</div>
                <div class="item"><span style="display:inline-block; width:12px;">●</span> Giorno con regole personalizzate</div>
              </div>
            </div>

            <div class="card" style="padding:14px;">

              <div class="admin-quick-select" aria-label="Selezione rapida">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">
                  <h3 style="margin:0; font-size:16px;">Selezione rapida</h3>
                  <button class="btn" type="button" id="adminClearSel">Svuota selezione</button>
                </div>

                <div class="qs-row" style="margin-top:10px;">
                  <div>
                    <label>Da</label>
                    <input type="date" id="adminRangeStart">
                  </div>
                  <div>
                    <label>A</label>
                    <input type="date" id="adminRangeEnd">
                  </div>
                </div>

                <div class="qs-actions" style="margin-top:10px;">
                  <button class="btn primary" type="button" id="adminSelectRangeBtn">Seleziona intervallo</button>
                  <button class="btn" type="button" id="adminSelectWeekendBtn">Seleziona weekend</button>
                  <button class="btn" type="button" id="adminDeselectWeekendBtn">Deseleziona weekend</button>
                </div>

                <div style="margin-top:10px;">
                  <div class="small muted" style="margin-bottom:8px;">Giorni della settimana (applica all’intervallo “Da/A”):</div>
                  <div class="qs-dow" role="group" aria-label="Giorni della settimana">
                    <label class="dow-chip"><input class="adminDow" type="checkbox" value="1"> Lun</label>
                    <label class="dow-chip"><input class="adminDow" type="checkbox" value="2"> Mar</label>
                    <label class="dow-chip"><input class="adminDow" type="checkbox" value="3"> Mer</label>
                    <label class="dow-chip"><input class="adminDow" type="checkbox" value="4"> Gio</label>
                    <label class="dow-chip"><input class="adminDow" type="checkbox" value="5"> Ven</label>
                    <label class="dow-chip"><input class="adminDow" type="checkbox" value="6"> Sab</label>
                    <label class="dow-chip"><input class="adminDow" type="checkbox" value="0"> Dom</label>
                  </div>
                  <div class="qs-actions" style="margin-top:10px;">
                    <button class="btn" type="button" id="adminAddDowBtn">Aggiungi</button>
                    <button class="btn" type="button" id="adminRemoveDowBtn">Rimuovi</button>
                  </div>
                </div>

                <p class="muted" style="margin:10px 0 0;" id="adminSelInfo">Nessun giorno selezionato.</p>
              </div>

              <h3 style="margin:14px 0 8px;">Modifica giorni selezionati</h3>

              <form method="post" action="/admin/action.php" id="adminBulkUpdate">
                <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
                <input type="hidden" name="action" value="calendar_bulk_update">
                <input type="hidden" name="month" value="<?php echo e($month); ?>">
                <input type="hidden" name="dates" id="adminDatesCsv" value="">

                <div style="margin-bottom:10px;">
                  <label>Prezzo per notte (<?php echo e($currency); ?>)</label>
                  <input name="price" type="number" step="1" min="0" placeholder="Lascia vuoto per non modificare">
                  <div class="small muted" style="margin-top:6px;">Applica lo stesso prezzo a tutti i giorni selezionati.</div>
                </div>

                <div style="margin-bottom:10px;">
                  <label>Disponibilità</label>
                  <select name="availability">
                    <option value="keep">Nessuna modifica</option>
                    <option value="open">Apri (forza aperto)</option>
                    <option value="closed">Chiudi</option>
                  </select>
                </div>

                <div style="margin-bottom:12px;">
                  <label>Durata minima (notti)</label>
                  <input name="min_nights" type="number" step="1" min="1" placeholder="Lascia vuoto per non modificare">
                  <div class="small muted" style="margin-top:6px;">Si applica al check-in del giorno selezionato.</div>
                </div>

                <button class="btn primary" type="submit">Applica</button>
              </form>

              <form method="post" action="/admin/action.php" id="adminBulkClear" style="margin-top:10px;">
                <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
                <input type="hidden" name="action" value="calendar_bulk_clear">
                <input type="hidden" name="month" value="<?php echo e($month); ?>">
                <input type="hidden" name="dates" id="adminDatesCsvClear" value="">
                <button class="btn" type="submit">Reset regole</button>
              </form>

              <div class="notice" style="margin-top:12px;">
                Suggerimento: per bloccare velocemente un periodo, seleziona i giorni e imposta <strong>Chiudi</strong>.
              </div>
            </div>
          </div>
        </div>

        <?php $adm_cal_js_v = @filemtime(__DIR__ . '/../assets/js/admin-calendar.js') ?: 1; ?>
        <script src="/assets/js/admin-calendar.js?v=<?php echo e((string)$adm_cal_js_v); ?>"></script>

      <?php elseif ($tab === 'pricing_engine'): ?>
        <?php
          $currency = (string)($store['settings']['currency'] ?? 'EUR');
          $pe = function_exists('pricing_engine_get_settings') ? pricing_engine_get_settings($store) : null;

          $today = date('Y-m-d');
          $covNow = function_exists('pe_coverage_days_from_today') ? pe_coverage_days_from_today($store) : 0;
          $nextMonthKey = function_exists('pe_next_month_key') ? pe_next_month_key($today) : null;
          $occNext = 0.0; $soldNext = 0; $openNext = 0;
          if ($nextMonthKey && function_exists('pe_month_occupancy')) {
            list($occNext, $soldNext, $openNext) = pe_month_occupancy($store, $nextMonthKey);
          }

          // Preview (GET)
          $pStart = isset($_GET['from']) ? normalize_ymd($_GET['from']) : null;
          $pEnd = isset($_GET['to']) ? normalize_ymd($_GET['to']) : null;
          $pPreview = isset($_GET['preview']) ? (int)$_GET['preview'] : 0;

          $pDow = isset($_GET['dow']) ? $_GET['dow'] : [];
          if (!is_array($pDow)) $pDow = [];
          $pDow = array_values(array_unique(array_map('intval', $pDow)));

          $pSkipUnavailable = isset($_GET['skip_unavailable']) ? (int)$_GET['skip_unavailable'] : 1;
          $pSkipIfPriceSet = isset($_GET['skip_if_price_set']) ? (int)$_GET['skip_if_price_set'] : 0;
          $pOverwrite = isset($_GET['overwrite']) ? (int)$_GET['overwrite'] : 1;

          // Optional per-run overrides (do NOT persist): floor/ceiling for the selected range.
          // Leave empty to use the saved engine settings.
          $pFloor = null;
          $pCeiling = null;
          if (isset($_GET['floor']) && $_GET['floor'] !== '') {
            $v = trim((string)$_GET['floor']);
            if (is_numeric($v)) $pFloor = (int)round((float)$v);
          }
          if (isset($_GET['ceiling']) && $_GET['ceiling'] !== '') {
            $v = trim((string)$_GET['ceiling']);
            if (is_numeric($v)) $pCeiling = (int)round((float)$v);
          }
          // Normalize overrides (avoid inverted ranges)
          if ($pFloor !== null && $pFloor < 0) $pFloor = 0;
          if ($pCeiling !== null && $pCeiling < 0) $pCeiling = 0;
          if ($pFloor !== null && $pCeiling !== null && $pCeiling < $pFloor) {
            $tmp = $pFloor; $pFloor = $pCeiling; $pCeiling = $tmp;
          }

          $previewRows = [];
          $previewErr = null;
          if ($pPreview === 1 && $pe && $pStart && $pEnd) {
            $opts = [
              'dow' => $pDow,
              'skip_unavailable' => ($pSkipUnavailable === 1),
              'skip_if_price_set' => ($pSkipIfPriceSet === 1),
              'floor_override' => $pFloor,
              'ceiling_override' => $pCeiling,
            ];
            list($ok, $err2, $rows) = pricing_engine_suggest_prices($store, $pStart, $pEnd, $opts);
            if (!$ok) {
              $previewErr = $err2 ?: 'Anteprima non disponibile.';
            } else {
              $previewRows = $rows;
            }
          }

          $dowNames = [1=>'Lun',2=>'Mar',3=>'Mer',4=>'Gio',5=>'Ven',6=>'Sab',7=>'Dom'];
        ?>

        <div class="card" style="padding:18px;">
          <h2 style="margin:0 0 8px;">Motore prezzi</h2>
          <p class="muted" style="margin:0 0 12px;">
            Genera prezzi suggeriti per notte (sito direct) e li applica ai giorni selezionati.
            Il motore usa: <strong>stagionalità</strong>, <strong>giorno della settimana</strong>,
            <strong>copertura (15/20 giorni)</strong> e <strong>event_factor</strong> (festivi/ponti + eventi manuali).
          </p>

          <?php if (!$pe): ?>
            <div class="notice danger">Modulo motore prezzi non disponibile sul server (pricing_engine.php mancante).</div>
          <?php else: ?>

            <div class="notice" style="margin:0 0 14px;">
              <div><strong>Oggi:</strong> <?php echo e($today); ?></div>
              <div><strong>Copertura attuale:</strong> <?php echo e((string)$covNow); ?> giorni</div>
              <?php if ($nextMonthKey): ?>
                <div><strong>Pace mese prossimo (<?php echo e($nextMonthKey); ?>):</strong> <?php echo e((string)((int)round($occNext*100))); ?>% (<?php echo e((string)$soldNext); ?>/<?php echo e((string)$openNext); ?> notti)</div>
              <?php endif; ?>
            </div>

            <details open>
              <summary style="cursor:pointer; font-weight:900;">Impostazioni motore</summary>

              <form method="post" action="/admin/action.php" style="margin-top:12px;">
                <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
                <input type="hidden" name="action" value="pricing_engine_save_settings">

                <div style="display:flex; gap:12px; flex-wrap:wrap;">
                  <div style="min-width:220px; flex:1;">
                    <label><input type="checkbox" name="pe_enabled" value="1" <?php echo !empty($pe['enabled'])?'checked':''; ?>> Abilitato</label>
                    <div class="small muted" style="margin-top:6px;">Se disabilitato, non genera né applica prezzi.</div>
                  </div>
                  <div style="min-width:220px;">
                    <label>Target copertura (giorni)</label>
                    <input type="number" name="pe_target_horizon_days" min="1" max="90" step="1" value="<?php echo e((string)$pe['target_horizon_days']); ?>">
                    <div class="small muted" style="margin-top:6px;">Consigliato: 15-20.</div>
                  </div>
                  <div style="min-width:220px;">
                    <label>Finestra spinta mese successivo (giorni)</label>
                    <input type="number" name="pe_push_next_month_window_days" min="0" max="60" step="1" value="<?php echo e((string)$pe['push_next_month_window_days']); ?>">
                    <div class="small muted" style="margin-top:6px;">Quando mancano ≤ X giorni a fine mese.</div>
                  </div>
                  <div style="min-width:220px;">
                    <label>Aggressività (0-1)</label>
                    <input type="number" name="pe_aggressiveness" min="0" max="1" step="0.05" value="<?php echo e((string)$pe['aggressiveness']); ?>">
                  </div>
                </div>

                <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:12px;">
                  <div style="min-width:180px;">
                    <label>Lead-time low (giorni)</label>
                    <input type="number" name="pe_window_low" min="1" max="90" step="1" value="<?php echo e((string)$pe['window_low']); ?>">
                  </div>
                  <div style="min-width:180px;">
                    <label>Lead-time high (giorni)</label>
                    <input type="number" name="pe_window_high" min="1" max="120" step="1" value="<?php echo e((string)$pe['window_high']); ?>">
                  </div>
                  <div style="min-width:220px;">
                    <label>Max sconto last-minute (0-0.60)</label>
                    <input type="number" name="pe_last_minute_max_discount" min="0" max="0.60" step="0.01" value="<?php echo e((string)$pe['last_minute_max_discount']); ?>">
                  </div>
                  <div style="min-width:180px;">
                    <label>Arrotonda a</label>
                    <select name="pe_round_to">
                      <option value="1" <?php echo ((int)$pe['round_to']===1)?'selected':''; ?>>1</option>
                      <option value="5" <?php echo ((int)$pe['round_to']===5)?'selected':''; ?>>5</option>
                      <option value="10" <?php echo ((int)$pe['round_to']===10)?'selected':''; ?>>10</option>
                    </select>
                  </div>
                </div>

                <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:12px;">
                  <div style="min-width:180px;">
                    <label>Floor (<?php echo e($currency); ?>)</label>
                    <input type="number" name="pe_floor_price" min="0" step="1" value="<?php echo e((string)$pe['floor_price']); ?>">
                  </div>
                  <div style="min-width:180px;">
                    <label>Ceiling (<?php echo e($currency); ?>)</label>
                    <input type="number" name="pe_ceiling_price" min="0" step="1" value="<?php echo e((string)$pe['ceiling_price']); ?>">
                  </div>
                  <div style="min-width:220px;">
                    <label>Ceiling eventi (<?php echo e($currency); ?>)</label>
                    <input type="number" name="pe_ceiling_event_price" min="0" step="1" value="<?php echo e((string)$pe['ceiling_event_price']); ?>">
                    <div class="small muted" style="margin-top:6px;">Usato quando event_lift ≥ 15%.</div>
                  </div>
                  <div style="min-width:220px;">
                    <label>Max event_lift (0-1)</label>
                    <input type="number" name="pe_event_max_lift" min="0" max="1" step="0.01" value="<?php echo e((string)$pe['event_max_lift']); ?>">
                  </div>
                </div>

                <details style="margin-top:12px;">
                  <summary style="cursor:pointer; font-weight:900;">Event factor (festivi/ponti/periodi)</summary>
                  <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:10px;">
                    <div style="min-width:210px;">
                      <label>Festivo major lift</label>
                      <input type="number" name="pe_holiday_major_lift" min="0" max="1" step="0.01" value="<?php echo e((string)$pe['holiday_major_lift']); ?>">
                    </div>
                    <div style="min-width:210px;">
                      <label>Festivo mid lift</label>
                      <input type="number" name="pe_holiday_mid_lift" min="0" max="1" step="0.01" value="<?php echo e((string)$pe['holiday_mid_lift']); ?>">
                    </div>
                    <div style="min-width:210px;">
                      <label>Ponte lift</label>
                      <input type="number" name="pe_bridge_lift" min="0" max="1" step="0.01" value="<?php echo e((string)$pe['bridge_lift']); ?>">
                    </div>
                    <div style="min-width:210px;">
                      <label>Periodo Natale lift</label>
                      <input type="number" name="pe_season_lift_christmas" min="0" max="1" step="0.01" value="<?php echo e((string)$pe['season_lift_christmas']); ?>">
                    </div>
                    <div style="min-width:210px;">
                      <label>Periodo Pasqua lift</label>
                      <input type="number" name="pe_season_lift_easter" min="0" max="1" step="0.01" value="<?php echo e((string)$pe['season_lift_easter']); ?>">
                    </div>
                  </div>
                </details>

                <details style="margin-top:12px;">
                  <summary style="cursor:pointer; font-weight:900;">Base per mese</summary>
                  <div class="small muted" style="margin-top:8px;">Valori consigliati (derivati dallo storico). Puoi adattarli.</div>
                  <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:10px;">
                    <?php for ($m=1; $m<=12; $m++): ?>
                      <div style="width:110px;">
                        <label><?php echo e((string)$m); ?></label>
                        <input type="number" name="pe_base_<?php echo e((string)$m); ?>" min="0" step="1" value="<?php echo e((string)($pe['base_by_month'][$m] ?? 0)); ?>">
                      </div>
                    <?php endfor; ?>
                  </div>
                </details>

                <details style="margin-top:12px;">
                  <summary style="cursor:pointer; font-weight:900;">Fattori giorno settimana</summary>
                  <div class="small muted" style="margin-top:8px;">ISO: 1=Lun … 7=Dom</div>
                  <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:10px;">
                    <?php for ($d=1; $d<=7; $d++): ?>
                      <div style="width:110px;">
                        <label><?php echo e($dowNames[$d]); ?></label>
                        <input type="number" name="pe_dow_<?php echo e((string)$d); ?>" min="0.50" max="1.80" step="0.01" value="<?php echo e((string)($pe['dow_factors'][$d] ?? 1.0)); ?>">
                      </div>
                    <?php endfor; ?>
                  </div>
                </details>

                <button class="btn primary" type="submit" style="margin-top:12px;">Salva impostazioni</button>
              </form>
            </details>

            <details style="margin-top:14px;" open>
              <summary style="cursor:pointer; font-weight:900;">Eventi manuali (extra)</summary>
              <p class="muted" style="margin:10px 0 12px;">Usa questo elenco per grandi eventi (concerti, fiere, Giubileo, ecc.) che impattano la domanda a Roma.</p>

              <form method="post" action="/admin/action.php" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
                <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
                <input type="hidden" name="action" value="pe_add_event">
                <div style="min-width:220px; flex:1;">
                  <label>Titolo</label>
                  <input type="text" name="title" placeholder="Es. Concerto / Fiera" required>
                </div>
                <div>
                  <label>Da</label>
                  <input type="date" name="start" required>
                </div>
                <div>
                  <label>A (check-out)</label>
                  <input type="date" name="end" required>
                </div>
                <div style="min-width:160px;">
                  <label>Lift (es. 0.10 = +10%)</label>
                  <input type="number" name="lift" min="0" max="1" step="0.01" value="0.10" required>
                </div>
                <button class="btn" type="submit">Aggiungi</button>
              </form>

              <?php $events = $pe['events'] ?? []; ?>
              <?php if (!$events): ?>
                <div class="notice" style="margin-top:12px;">Nessun evento manuale inserito.</div>
              <?php else: ?>
                <div style="overflow:auto; margin-top:12px;">
                  <table class="admin-table" style="width:100%; border-collapse:collapse;">
                    <thead>
                      <tr>
                        <th style="text-align:left; padding:10px; border-bottom:1px solid var(--border);">Titolo</th>
                        <th style="text-align:left; padding:10px; border-bottom:1px solid var(--border);">Periodo</th>
                        <th style="text-align:left; padding:10px; border-bottom:1px solid var(--border);">Lift</th>
                        <th style="text-align:left; padding:10px; border-bottom:1px solid var(--border);">Azioni</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($events as $ev): ?>
                        <tr>
                          <td style="padding:10px; border-bottom:1px solid var(--border);"><?php echo e((string)($ev['title'] ?? '')); ?></td>
                          <td style="padding:10px; border-bottom:1px solid var(--border); white-space:nowrap;"><?php echo e((string)($ev['start'] ?? '')); ?> → <?php echo e((string)($ev['end'] ?? '')); ?></td>
                          <td style="padding:10px; border-bottom:1px solid var(--border);">+<?php echo e((string)((int)round(((float)($ev['lift'] ?? 0))*100))); ?>%</td>
                          <td style="padding:10px; border-bottom:1px solid var(--border); white-space:nowrap;">
                            <form method="post" action="/admin/action.php" style="display:inline-block;">
                              <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
                              <input type="hidden" name="action" value="pe_delete_event">
                              <input type="hidden" name="id" value="<?php echo e((string)($ev['id'] ?? '')); ?>">
                              <button class="btn" type="submit">Rimuovi</button>
                            </form>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </details>

            <div class="card" style="padding:14px; margin-top:14px;">
              <h3 style="margin:0 0 8px;">Calcolo prezzi e applicazione</h3>
              <p class="muted" style="margin:0 0 12px;">Seleziona un intervallo, fai anteprima e poi applica i prezzi al calendario.</p>

              <form method="get" action="/admin/index.php" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
                <input type="hidden" name="tab" value="pricing_engine">
                <input type="hidden" name="preview" value="1">

                <div>
                  <label>Da</label>
                  <input type="date" name="from" value="<?php echo e($pStart ?: ''); ?>" required>
                </div>
                <div>
                  <label>A</label>
                  <input type="date" name="to" value="<?php echo e($pEnd ?: ''); ?>" required>
                </div>

                <div style="min-width:160px;">
                  <label>Floor (override)</label>
                  <input type="number" name="floor" min="0" step="1" value="<?php echo e($pFloor !== null ? (string)$pFloor : ''); ?>" placeholder="<?php echo e((string)($pe['floor_price'] ?? '')); ?>">
                  <div class="small muted" style="margin-top:6px;">Vuoto = usa impostazioni.</div>
                </div>
                <div style="min-width:160px;">
                  <label>Ceiling (override)</label>
                  <input type="number" name="ceiling" min="0" step="1" value="<?php echo e($pCeiling !== null ? (string)$pCeiling : ''); ?>" placeholder="<?php echo e((string)($pe['ceiling_price'] ?? '')); ?>">
                  <div class="small muted" style="margin-top:6px;">Vuoto = usa impostazioni.</div>
                </div>

                <div style="min-width:240px;">
                  <div class="small muted" style="margin-bottom:6px;">Giorni settimana</div>
                  <div style="display:flex; gap:6px; flex-wrap:wrap;">
                    <?php for ($d=1; $d<=7; $d++): ?>
                      <?php $checked = (!$pDow || in_array($d, $pDow, true)); ?>
                      <label class="dow-chip" style="display:inline-flex; align-items:center; gap:6px;">
                        <input type="checkbox" name="dow[]" value="<?php echo e((string)$d); ?>" <?php echo $checked?'checked':''; ?>> <?php echo e($dowNames[$d]); ?>
                      </label>
                    <?php endfor; ?>
                  </div>
                </div>

                <div style="min-width:240px;">
                  <label><input type="checkbox" name="skip_unavailable" value="1" <?php echo ($pSkipUnavailable===1)?'checked':''; ?>> Solo notti vendibili</label>
                  <div class="small muted" style="margin-top:6px;">Esclude notti già occupate o chiuse.</div>
                </div>

                <div style="min-width:260px;">
                  <label><input type="checkbox" name="skip_if_price_set" value="1" <?php echo ($pSkipIfPriceSet===1)?'checked':''; ?>> Non toccare giorni con prezzo già impostato</label>
                </div>

                <div style="min-width:220px;">
                  <label><input type="checkbox" name="overwrite" value="1" <?php echo ($pOverwrite===1)?'checked':''; ?>> Sovrascrivi prezzi esistenti</label>
                  <div class="small muted" style="margin-top:6px;">Vale solo quando applichi (non nell'anteprima).</div>
                </div>

                <button class="btn primary" type="submit">Anteprima</button>
              </form>

              <?php if ($previewErr): ?>
                <div class="notice danger" style="margin-top:12px;"><?php echo e($previewErr); ?></div>
              <?php endif; ?>

              <?php if ($previewRows): ?>
                <div style="overflow:auto; margin-top:12px;">
                  <table class="admin-table" style="width:100%; border-collapse:collapse;">
                    <thead>
                      <tr>
                        <th style="text-align:left; padding:10px; border-bottom:1px solid var(--border);">Data</th>
                        <th style="text-align:left; padding:10px; border-bottom:1px solid var(--border);">Stato</th>
                        <th style="text-align:left; padding:10px; border-bottom:1px solid var(--border);">Prezzo attuale</th>
                        <th style="text-align:left; padding:10px; border-bottom:1px solid var(--border);">Suggerito</th>
                        <th style="text-align:left; padding:10px; border-bottom:1px solid var(--border);">Note</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($previewRows as $r): ?>
                        <?php
                          $d = (string)($r['date'] ?? '');
                          $st = (string)($r['status'] ?? '');
                          $cur = (float)($r['current_price'] ?? 0);
                          $sug = (float)($r['suggested_price'] ?? 0);
                          $notes = $r['reasons'] ?? [];
                          if (!is_array($notes)) $notes = [];
                          $noteStr = implode(' · ', array_slice($notes, 0, 3));
                        ?>
                        <tr>
                          <td style="padding:10px; border-bottom:1px solid var(--border); white-space:nowrap;"><?php echo e($d); ?></td>
                          <td style="padding:10px; border-bottom:1px solid var(--border);"><span class="pill"><?php echo e($st); ?></span></td>
                          <td style="padding:10px; border-bottom:1px solid var(--border); white-space:nowrap;"><?php echo e(format_currency($cur, $currency)); ?></td>
                          <td style="padding:10px; border-bottom:1px solid var(--border); white-space:nowrap;"><strong><?php echo e(format_currency($sug, $currency)); ?></strong></td>
                          <td style="padding:10px; border-bottom:1px solid var(--border);" class="small muted"><?php echo e($noteStr); ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>

                <form method="post" action="/admin/action.php" style="margin-top:12px;">
                  <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
                  <input type="hidden" name="action" value="pricing_engine_apply">
                  <input type="hidden" name="from" value="<?php echo e($pStart ?: ''); ?>">
                  <input type="hidden" name="to" value="<?php echo e($pEnd ?: ''); ?>">
                  <input type="hidden" name="floor" value="<?php echo e($pFloor !== null ? (string)$pFloor : ''); ?>">
                  <input type="hidden" name="ceiling" value="<?php echo e($pCeiling !== null ? (string)$pCeiling : ''); ?>">
                  <?php foreach ($pDow as $d): ?>
                    <input type="hidden" name="dow[]" value="<?php echo e((string)$d); ?>">
                  <?php endforeach; ?>
                  <input type="hidden" name="skip_unavailable" value="<?php echo e((string)$pSkipUnavailable); ?>">
                  <input type="hidden" name="skip_if_price_set" value="<?php echo e((string)$pSkipIfPriceSet); ?>">
                  <input type="hidden" name="overwrite" value="<?php echo e((string)$pOverwrite); ?>">
                  <button class="btn primary" type="submit">Applica prezzi suggeriti</button>
                </form>
              <?php endif; ?>

            </div>

          <?php endif; ?>
        </div>

      <?php elseif ($tab === 'bookings'): ?>
        <?php
          $bookings = $store['bookings'] ?? [];
          // FIX: order by stay date (upcoming/current first, then past most recent first).
          // Before it was ordered by created_at, which for Booking imports is reset at every sync.
          $todayYmd = date('Y-m-d');
          usort($bookings, function($a, $b) use ($todayYmd) {
            $ae = (string)($a['end'] ?? ''); $be = (string)($b['end'] ?? '');
            $aPast = ($ae !== '' && $ae < $todayYmd); $bPast = ($be !== '' && $be < $todayYmd);
            if ($aPast !== $bPast) return $aPast ? 1 : -1;
            $as = (string)($a['start'] ?? ''); $bs = (string)($b['start'] ?? '');
            return $aPast ? strcmp($bs, $as) : strcmp($as, $bs);
          });
        ?>

        <div class="card" style="padding:18px;">
          <h2 style="margin:0 0 8px;">Prenotazioni</h2>
          <p class="muted" style="margin:0 0 14px;">Richieste, pagamenti e prenotazioni confermate.</p>

          <?php if (!$bookings): ?>
            <div class="notice">Nessuna prenotazione trovata.</div>
          <?php else: ?>
            <div style="overflow:auto;">
              <table class="admin-table" style="width:100%; border-collapse:collapse;">
                <thead>
                  <tr>
                    <th style="text-align:left; padding:10px; border-bottom:1px solid var(--border);">Periodo</th>
                    <th style="text-align:left; padding:10px; border-bottom:1px solid var(--border);">Notti</th>
                    <th style="text-align:left; padding:10px; border-bottom:1px solid var(--border);">Origine</th>
                    <th style="text-align:left; padding:10px; border-bottom:1px solid var(--border);">Ospite</th>
                    <th style="text-align:left; padding:10px; border-bottom:1px solid var(--border);">Stato</th>
                    <th style="text-align:left; padding:10px; border-bottom:1px solid var(--border);">Importo</th>
                    <th style="text-align:left; padding:10px; border-bottom:1px solid var(--border);">Azioni</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($bookings as $bk): ?>
                    <?php
                      $status = (string)($bk['status'] ?? '');
                      $period = e(($bk['start'] ?? '') . ' → ' . ($bk['end'] ?? ''));
                      $guest = trim((string)($bk['name'] ?? ''));
                      $email = trim((string)($bk['email'] ?? ''));
                      $phone = trim((string)($bk['phone'] ?? ''));

                      $label = $status;
                      if ($status === 'booked') $label = 'Confermata';
                      if ($status === 'pending') $label = 'Da gestire';
                      if ($status === 'payment_pending') $label = 'In pagamento';
                      if ($status === 'rejected') $label = 'Rifiutata';

                      $amount = '';
                      if (isset($bk['amount_paid']) && is_numeric($bk['amount_paid'])) {
                        $amount = format_currency((float)$bk['amount_paid'], (string)($bk['currency'] ?? 'EUR'));
                      } elseif (isset($bk['amount_due']) && is_numeric($bk['amount_due'])) {
                        $amount = format_currency((float)$bk['amount_due'], (string)($bk['currency'] ?? 'EUR'));
                      }

                      $nightsN = nights_between((string)($bk['start'] ?? ''), (string)($bk['end'] ?? ''));
                      $isBookingCom = ((string)($bk['source'] ?? '') === 'booking');
                      $sourceLabel = $isBookingCom ? 'Booking.com' : 'Sito';
                      if (!$isBookingCom && $guest === '' && $email === '') $guest = '—';
                      if ($isBookingCom && $guest === '') $guest = '(dati su Booking.com)';

                      $guestLine = e($guest ?: '—');
                      if (!empty($bk['needs_check'])) {
                        $guestLine .= '<div class="small" style="color:#b91c1c; font-weight:800;">⚠ Pagata ma date in conflitto: verificare</div>';
                      }
                      if ($email) $guestLine .= '<div class="small muted">' . e($email) . '</div>';
                      if ($phone) $guestLine .= '<div class="small muted">' . e($phone) . '</div>';
                    ?>
                    <tr>
                      <td style="padding:10px; border-bottom:1px solid var(--border); white-space:nowrap;"><?php echo $period; ?></td>
                      <td style="padding:10px; border-bottom:1px solid var(--border); white-space:nowrap;"><?php echo e((string)$nightsN); ?></td>
                      <td style="padding:10px; border-bottom:1px solid var(--border); white-space:nowrap;"><?php echo e($sourceLabel); ?></td>
                      <td style="padding:10px; border-bottom:1px solid var(--border);"><?php echo $guestLine; ?></td>
                      <td style="padding:10px; border-bottom:1px solid var(--border);"><span class="pill"><?php echo e($label); ?></span></td>
                      <td style="padding:10px; border-bottom:1px solid var(--border); white-space:nowrap;"><?php echo e($amount ?: '—'); ?></td>
                      <td style="padding:10px; border-bottom:1px solid var(--border); white-space:nowrap;">
                        <?php if ($status === 'pending'): ?>
                          <form method="post" action="/admin/action.php" style="display:inline-block;">
                            <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
                            <input type="hidden" name="action" value="approve_booking">
                            <input type="hidden" name="id" value="<?php echo e((string)($bk['id'] ?? '')); ?>">
                            <button class="btn primary" type="submit">Conferma</button>
                          </form>
                          <form method="post" action="/admin/action.php" style="display:inline-block; margin-left:6px;">
                            <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
                            <input type="hidden" name="action" value="reject_booking">
                            <input type="hidden" name="id" value="<?php echo e((string)($bk['id'] ?? '')); ?>">
                            <button class="btn" type="submit">Rifiuta</button>
                          </form>
                        <?php elseif ($status === 'payment_pending'): ?>
                          <?php
                            $exp = (int)($bk['expires_at'] ?? 0);
                            if ($exp) {
                              echo '<span class="small muted">Scade: ' . e(date('Y-m-d H:i', $exp)) . '</span>';
                            } else {
                              echo '<span class="small muted">In attesa di pagamento</span>';
                            }
                          ?>
                        <?php elseif ($status === 'booked' && $isBookingCom): ?>
                          <span class="small muted">Gestita su Booking.com</span>
                        <?php elseif ($status === 'booked'): ?>
                          <?php
                            $eligible = booking_is_eligible_for_review($bk);
                            $invitedAt = (int)($bk['review_invited_at'] ?? 0);
                            $submittedAt = (int)($bk['review_submitted_at'] ?? 0);
                            $token = (string)($bk['review_token'] ?? '');
                            $reviewLink = ($token !== '') ? booking_review_link($bk, $token) : '';
                          ?>

                          <?php if ($submittedAt): ?>
                            <span class="small">Recensione ricevuta</span>
                            <div class="small muted">Data: <?php echo e(date('Y-m-d', $submittedAt)); ?></div>
                            <div style="margin-top:6px;"><a class="btn" href="?tab=reviews">Gestisci</a></div>
                          <?php elseif ($eligible): ?>
                            <form method="post" action="/admin/action.php" style="display:inline-block;">
                              <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
                              <input type="hidden" name="action" value="send_review_invite">
                              <input type="hidden" name="id" value="<?php echo e((string)($bk['id'] ?? '')); ?>">
                              <button class="btn" type="submit"><?php echo e($invitedAt ? 'Re-invia link' : 'Invita recensione'); ?></button>
                            </form>

                            <?php if ($invitedAt): ?>
                              <div class="small muted" style="margin-top:4px;">Inviato: <?php echo e(date('Y-m-d', $invitedAt)); ?></div>
                            <?php endif; ?>

                            <?php if ($reviewLink): ?>
                              <div class="small" style="margin-top:4px;"><a href="<?php echo e($reviewLink); ?>" target="_blank" rel="noopener">Apri link</a></div>
                            <?php endif; ?>
                          <?php else: ?>
                            <span class="small muted">Disponibile dopo checkout</span>
                          <?php endif; ?>
                        <?php else: ?>
                          <span class="small muted">—</span>
                        <?php endif; ?>

                        <?php if (!$isBookingCom && $status !== 'payment_pending'): ?>
                          <form method="post" action="/admin/action.php" style="display:block; margin-top:6px;" data-confirm="Eliminare definitivamente questa prenotazione/richiesta? Le date torneranno libere.">
                            <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
                            <input type="hidden" name="action" value="delete_booking">
                            <input type="hidden" name="id" value="<?php echo e((string)($bk['id'] ?? '')); ?>">
                            <button class="btn" type="submit">Elimina</button>
                          </form>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

      <?php elseif ($tab === 'reviews'): ?>
        <?php
          $reviews = load_reviews();
          // Newest first
          usort($reviews, function($a, $b) {
            return (int)($b['created_at'] ?? 0) <=> (int)($a['created_at'] ?? 0);
          });

          $stats = reviews_stats($reviews);
          $avg = (float)($stats['avg'] ?? 0);
          $count = (int)($stats['count'] ?? 0);

          $pending = [];
          $approved = [];
          $rejected = [];
          foreach ($reviews as $r) {
            if (!is_array($r)) continue;
            $st = (string)($r['status'] ?? 'pending');
            if ($st === 'approved') $approved[] = $r;
            elseif ($st === 'rejected') $rejected[] = $r;
            else $pending[] = $r;
          }
        ?>

        <div class="card" style="padding:18px;">
          <h2 style="margin:0 0 8px;">Recensioni</h2>
          <p class="muted" style="margin:0 0 14px;">Solo le recensioni <strong>approvate</strong> vengono mostrate sul sito e inserite nei dati strutturati.</p>

          <div class="notice" style="margin:0 0 14px;">
            <strong>Media:</strong> <?php echo e(number_format($avg, 1)); ?>/5 · <strong>Totale:</strong> <?php echo e($count); ?>
          </div>

          <?php if (!$reviews): ?>
            <div class="notice">Nessuna recensione ricevuta.</div>
          <?php else: ?>
            <div style="overflow:auto;">
              <table class="admin-table" style="width:100%; border-collapse:collapse;">
                <thead>
                  <tr>
                    <th style="text-align:left; padding:10px; border-bottom:1px solid var(--border);">Data</th>
                    <th style="text-align:left; padding:10px; border-bottom:1px solid var(--border);">Ospite</th>
                    <th style="text-align:left; padding:10px; border-bottom:1px solid var(--border);">Voto</th>
                    <th style="text-align:left; padding:10px; border-bottom:1px solid var(--border);">Testo</th>
                    <th style="text-align:left; padding:10px; border-bottom:1px solid var(--border);">Stato</th>
                    <th style="text-align:left; padding:10px; border-bottom:1px solid var(--border);">Azioni</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($reviews as $r): ?>
                    <?php
                      $rid = (string)($r['id'] ?? '');
                      $date = (string)($r['date_published'] ?? '');
                      $author = (string)($r['author'] ?? '');
                      $rating = (int)($r['rating'] ?? 0);
                      $body = (string)($r['body'] ?? '');
                      $st = (string)($r['status'] ?? 'pending');
                      $stLabel = ($st === 'approved') ? 'Approvata' : (($st === 'rejected') ? 'Rifiutata' : 'Da approvare');
                    ?>
                    <tr>
                      <td style="padding:10px; border-bottom:1px solid var(--border); white-space:nowrap;"><?php echo e($date ?: '—'); ?></td>
                      <td style="padding:10px; border-bottom:1px solid var(--border); white-space:nowrap;"><?php echo e($author ?: '—'); ?></td>
                      <td style="padding:10px; border-bottom:1px solid var(--border); white-space:nowrap;"><?php echo e($rating ? ($rating . '/5') : '—'); ?></td>
                      <td style="padding:10px; border-bottom:1px solid var(--border); min-width:280px;"><span class="small"><?php echo e($body); ?></span></td>
                      <td style="padding:10px; border-bottom:1px solid var(--border); white-space:nowrap;"><span class="pill"><?php echo e($stLabel); ?></span></td>
                      <td style="padding:10px; border-bottom:1px solid var(--border); white-space:nowrap;">
                        <?php if ($st !== 'approved'): ?>
                          <form method="post" action="/admin/action.php" style="display:inline-block;">
                            <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
                            <input type="hidden" name="action" value="approve_review">
                            <input type="hidden" name="id" value="<?php echo e($rid); ?>">
                            <button class="btn primary" type="submit">Approva</button>
                          </form>
                        <?php endif; ?>

                        <?php if ($st !== 'rejected'): ?>
                          <form method="post" action="/admin/action.php" style="display:inline-block; margin-left:6px;">
                            <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
                            <input type="hidden" name="action" value="reject_review">
                            <input type="hidden" name="id" value="<?php echo e($rid); ?>">
                            <button class="btn" type="submit">Rifiuta</button>
                          </form>
                        <?php endif; ?>

                        <form method="post" action="/admin/action.php" style="display:inline-block; margin-left:6px;" data-confirm="Eliminare questa recensione?">
                          <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
                          <input type="hidden" name="action" value="delete_review">
                          <input type="hidden" name="id" value="<?php echo e($rid); ?>">
                          <button class="btn" type="submit">Elimina</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

      <?php elseif ($tab === 'sync'): ?>
        <?php
          $ical = (string)($store['settings']['booking_ical_url'] ?? '');
          $last = (int)($store['settings']['last_sync'] ?? 0);
          $exportToken = (string)($store['settings']['ical_export_secret'] ?? '');
          $exportUrlPublic = base_url() . '/calendar.ics';
          $exportUrlPrivate = base_url() . '/calendar.ics?token=' . $exportToken;
          $publicEnabled = !empty($store['settings']['ical_export_public_enabled']);

          // Token for the cron endpoint (auto sync every 5 minutes)
          if (empty($store['settings']['cron_sync_token'])) {
            $store['settings']['cron_sync_token'] = bin2hex(random_bytes_fallback(16));
            @save_store($store);
          }
          $cronToken = (string)($store['settings']['cron_sync_token'] ?? '');
          $cronUrl = base_url() . '/api/cron_sync.php?token=' . $cronToken;

        ?>
        <div class="card" style="padding:18px;">
          <h2 style="margin:0 0 8px;">Sync iCal (Booking / Channel)</h2>
          <p class="muted" style="margin:0 0 14px;">
            La sincronizzazione iCal aggiorna <strong>solo le disponibilità</strong>. I prezzi e le regole li gestisci qui nel calendario.
          </p>

          <div class="kv" style="margin:0 0 12px;">
            <div class="k">Import (Booking → sito)</div>
            <div class="v"><code style="word-break:break-all;"><?php echo e($ical ?: '—'); ?></code></div>
            <div class="k">Ultimo sync import</div>
            <div class="v"><?php echo $last ? e(date('Y-m-d H:i', $last)) : 'Mai'; ?></div>
            <?php $lastSyncErr = (string)($store['settings']['last_sync_error'] ?? ''); ?>
            <?php if ($lastSyncErr !== ''): ?>
              <div class="k">Ultimo errore sync</div>
              <div class="v"><span style="color:#b91c1c;"><?php echo e($lastSyncErr); ?></span></div>
            <?php endif; ?>

            <div class="k">Export (sito → Booking)</div>
            <div class="v">
              <?php if ($publicEnabled): ?>
                <div class="notice warn" style="margin-bottom:10px;">
                  <strong>Export pubblico attivo</strong>: chiunque conosca l’URL può vedere le date occupate. Per privacy e sicurezza è consigliato usare il link con token.
                </div>
              <?php else: ?>
                <div class="notice" style="margin-bottom:10px;">
                  <strong>Export privato</strong>: per accedere al feed è richiesto il <strong>token</strong>.
                </div>
              <?php endif; ?>

              <div class="small muted" style="margin-bottom:6px;"><?php echo $publicEnabled ? 'URL pubblico (sconsigliato):' : 'URL pubblico (disabilitato):'; ?></div>
              <code style="word-break:break-all;"><?php echo e($exportUrlPublic); ?></code>

              <div class="small muted" style="margin-top:10px; margin-bottom:6px;">URL con token (consigliato):</div>
              <code style="word-break:break-all;"><?php echo e($exportUrlPrivate); ?></code>

              <div class="small muted" style="margin-top:8px;">
                In Booking: incolla l’URL con token nella sezione “Import calendar”. Se hai già impostato l’URL pubblico, aggiornalo qui.
              </div>

              <form method="post" action="/admin/action.php" class="actions" style="margin-top:12px;">
                <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
                <input type="hidden" name="action" value="toggle_ical_public">
                <input type="hidden" name="enable" value="<?php echo $publicEnabled ? '0' : '1'; ?>">
                <button class="btn" type="submit"><?php echo $publicEnabled ? 'Disabilita export pubblico' : 'Abilita export pubblico (sconsigliato)'; ?></button>
              </form>
</div>
          </div>

          <form method="post" action="/admin/action.php" class="actions" style="margin-top:10px;">
            <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
            <input type="hidden" name="action" value="regen_ical_token">
            <button class="btn" type="submit">Rigenera link export</button>
          </form>

          <div class="card" style="padding:14px; margin-top:14px;">
            <h3 style="margin:0 0 8px;">Sincronizzazione automatica (ogni 5 minuti)</h3>
            <p class="muted" style="margin:0 0 10px;">
              Per avere un sync davvero automatico anche quando nessuno visita il sito, imposta un <strong>cron</strong> sul server ogni 5 minuti
              che richiami questo URL (protetto da token):
            </p>
            <code style="word-break:break-all;"><?php echo e($cronUrl); ?></code>
            <div class="small muted" style="margin-top:10px;">
              Esempio (Linux): <code>curl -fsS "<?php echo e($cronUrl); ?>" >/dev/null</code>
            </div>
          </div>

          <form method="post" action="/admin/action.php" class="actions" style="margin-top:10px;">
            <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
            <input type="hidden" name="action" value="manual_sync">
            <button class="btn primary" type="submit">Esegui sync ora</button>
          </form>
        </div>

      <?php elseif ($tab === 'settings'): ?>
        <?php
          $s = $store['settings'] ?? [];
          $currency = (string)($s['currency'] ?? 'EUR');
          $stripeKey = stripe_secret_key($store);
          $webhookSecret = stripe_webhook_secret($store);
          $depositPct = (float)($s['stripe_deposit_percent'] ?? 0.30);
          $holdMin = (int)($s['stripe_hold_minutes'] ?? 30);
          if ($holdMin < 30) $holdMin = 30;
          if ($holdMin > 240) $holdMin = 240;
        ?>

        <div class="card" style="padding:18px;">
          <h2 style="margin:0 0 8px;">Impostazioni</h2>

          <form method="post" action="/admin/action.php">
            <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
            <input type="hidden" name="action" value="save_settings">

            <h3 style="margin:14px 0 10px;">Dati struttura</h3>
            <div class="form-row">
              <div>
                <label>Nome struttura</label>
                <input name="site_name" value="<?php echo e((string)($s['site_name'] ?? '')); ?>" required>
              </div>
              <div>
                <label>Telefono</label>
                <input name="phone" value="<?php echo e((string)($s['phone'] ?? '')); ?>">
              </div>
            </div>
            <div class="form-row" style="margin-top:10px;">
              <div>
                <label>Indirizzo</label>
                <input name="address" value="<?php echo e((string)($s['address'] ?? '')); ?>">
              </div>
              <div>
                <label>Email (opzionale)</label>
                <input name="email" value="<?php echo e((string)($s['email'] ?? '')); ?>" type="email">
              </div>
            </div>

            <div class="form-row" style="margin-top:10px;">
              <div>
                <label>Email notifiche prenotazioni</label>
                <input name="notify_email" value="<?php echo e((string)($s['notify_email'] ?? 'viaarriano11@gmail.com')); ?>" type="email" placeholder="viaarriano11@gmail.com">
                <div class="small muted" style="margin-top:6px;">Riceverai una mail per ogni prenotazione / richiesta inviata dal sito.</div>
              </div>
            </div>

                        <h3 style="margin:18px 0 10px;">Prezzi e regole</h3>
                        <div class="form-row">
                          <div>
                            <label>Prezzo base per notte (<?php echo e($currency); ?>)</label>
                            <input name="base_price" type="number" min="0" step="1" value="<?php echo e((string)($s['base_price'] ?? '')); ?>" required>
                          </div>
                          <div>
                            <label>Pulizie (una tantum) (<?php echo e($currency); ?>)</label>
                            <input name="cleaning_fee" type="number" min="0" step="1" value="<?php echo e((string)($s['cleaning_fee'] ?? '0')); ?>">
                          </div>
                        </div>

                        <div style="margin-top:10px;">
                          <label>Tassa di soggiorno (per persona / notte) (<?php echo e($currency); ?>)</label>
                          <input name="city_tax_per_person_per_night" type="number" min="0" step="1" value="<?php echo e((string)($s['city_tax_per_person_per_night'] ?? '6')); ?>">
                          <div class="small muted" style="margin-top:6px;">Viene calcolata automaticamente nel preventivo.</div>
                        </div>

                        <div class="form-row" style="margin-top:10px;">
                          <div>
                            <label>Durata minima soggiorno (globale)</label>
                            <input name="min_nights" type="number" min="1" step="1" value="<?php echo e((string)($s['min_nights'] ?? '1')); ?>">
                          </div>
                          <div>
                            <label>Valuta</label>
                            <select name="currency">
                              <?php foreach (['EUR','USD','GBP'] as $cur): ?>
                                <option value="<?php echo e($cur); ?>" <?php echo $currency===$cur?'selected':''; ?>><?php echo e($cur); ?></option>
                              <?php endforeach; ?>
                            </select>
                          </div>
                        </div>

                        <h3 style="margin:18px 0 10px;">Sync iCal</h3>
            <div>
              <label>URL iCal Booking / Channel</label>
              <input name="booking_ical_url" value="<?php echo e((string)($s['booking_ical_url'] ?? '')); ?>" placeholder="https://...">
              <div class="small muted" style="margin-top:6px;">Usalo per importare le prenotazioni e bloccare le date.</div>
            </div>

            <h3 style="margin:18px 0 10px;">Pagamento (Stripe)</h3>
            <div class="notice" style="margin-bottom:12px;">
              Per confermare una prenotazione con pagamento online:
              inserisci <strong>Secret Key</strong> e (consigliato) configura il <strong>Webhook</strong>.
              Endpoint webhook: <code><?php echo e(base_url() . '/api/stripe_webhook.php'); ?></code>
            </div>

            <div class="form-row">
              <div>
                <label>Stripe Secret Key</label>
                <input name="stripe_secret_key" value="<?php echo e($stripeKey); ?>" placeholder="sk_live_...">
              </div>
              <div>
                <label>Stripe Webhook Secret</label>
                <input name="stripe_webhook_secret" value="<?php echo e($webhookSecret); ?>" placeholder="whsec_...">
              </div>
            </div>

            <div class="form-row" style="margin-top:10px;">
              <div>
                <label>Richiedi pagamento per conferma</label>
                <select name="stripe_require_payment">
                  <option value="0" <?php echo empty($s['stripe_require_payment'])?'selected':''; ?>>No (richiesta manuale)</option>
                  <option value="1" <?php echo !empty($s['stripe_require_payment'])?'selected':''; ?>>Sì (Checkout Stripe)</option>
                </select>
              </div>
              <div>
                <label>Caparra (% del totale)</label>
                <input name="stripe_deposit_percent" type="number" min="0.1" max="1" step="0.05" value="<?php echo e((string)$depositPct); ?>">
                <div class="small muted" style="margin-top:6px;">1 = 100% del totale. Esempio: 0.30 = 30%.</div>
              </div>
            </div>

            <div style="margin-top:10px;">
              <label>Tempo massimo per completare il pagamento (minuti)</label>
              <input name="stripe_hold_minutes" type="number" min="30" max="240" step="5" value="<?php echo e((string)$holdMin); ?>">
              <div class="small muted" style="margin-top:6px;">Minimo 30 minuti (richiesto da Stripe).</div>
            </div>

            <div class="actions" style="margin-top:16px;">
              <button class="btn primary" type="submit">Salva impostazioni</button>
            </div>
          </form>

          <hr style="border:none; border-top:1px solid var(--border); margin:18px 0;">

          <h3 style="margin:0 0 10px;">Sicurezza</h3>
          <form method="post" action="/admin/action.php" style="margin-top:10px; margin-bottom:14px;">
            <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
            <input type="hidden" name="action" value="change_username">
            <div class="form-row">
              <div>
                <label>Nome utente admin</label>
                <input name="new_username" value="<?php echo e((string)($s['admin_user'] ?? 'admin')); ?>" required minlength="3" maxlength="32" pattern="[A-Za-z0-9._-]{3,32}">
                <div class="small muted" style="margin-top:6px;">Usa solo lettere, numeri, punti, underscore (_) o trattino (-).</div>
              </div>
            </div>
            <div class="actions" style="margin-top:14px;">
              <button class="btn" type="submit">Cambia nome utente</button>
            </div>
          </form>


          <form method="post" action="/admin/action.php">
            <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
            <input type="hidden" name="action" value="change_password">
            <div class="form-row">
              <div>
                <label>Nuova password admin</label>
                <input type="password" name="new_password" required minlength="8">
              </div>
              <div>
                <label>Conferma password</label>
                <input type="password" name="new_password_confirm" required minlength="8">
              </div>
            </div>
            <div class="actions" style="margin-top:14px;">
              <button class="btn" type="submit">Cambia password</button>
            </div>
          </form>


          <hr style="border:none; border-top:1px solid var(--border); margin:18px 0;">
          <?php
            $twofa = admin_2fa_state();
            $twoEnabled = !empty($twofa['enabled']) && !empty($twofa['secret']);
          ?>
          <h3 style="margin:0 0 10px;">Autenticazione a due fattori (2FA)</h3>
          <div class="notice" style="margin-bottom:12px;">
            Se attivi la 2FA, dopo username e password dovrai inserire un codice di Google Authenticator. Conserva anche i codici di recupero.
          </div>
          <p style="margin:0 0 12px;">Stato: <strong><?php echo $twoEnabled ? 'Attiva' : 'Disattivata'; ?></strong></p>
          <div class="actions" style="margin-top:10px;">
            <a class="btn" href="/admin/2fa_setup.php">Configura / Gestisci 2FA</a>
            <?php if ($twoEnabled): ?>
              <form method="post" action="/admin/2fa_disable.php" style="display:inline; margin:0;" data-confirm="Disattivare la 2FA?">
                <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
                <button class="btn" type="submit">Disattiva 2FA</button>
              </form>
            <?php endif; ?>
          </div>
        </div>

      <?php endif; ?>

    </div>
  </main>
  <?php $adm_js_v = @filemtime(__DIR__ . '/../assets/js/admin.js') ?: 1; ?>
  <script src="/assets/js/admin.js?v=<?php echo e((string)$adm_js_v); ?>"></script>
</body>
</html>
