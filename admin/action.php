<?php
require_once __DIR__ . '/../lib/lib.php';
require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405);
  echo 'Method Not Allowed';
  exit;
}

$csrf = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';
if (!csrf_verify($csrf)) {
  header('Location: /admin/index.php?tab=calendar&err=' . urlencode('Sessione scaduta. Riprova.'));
  exit;
}

$action = isset($_POST['action']) ? (string)$_POST['action'] : '';

function redirect_tab($tab, $msg = null, $err = null, $extra = []) {
  $q = ['tab' => $tab];
  if ($msg) $q['msg'] = $msg;
  if ($err) $q['err'] = $err;
  if (is_array($extra)) {
    foreach ($extra as $k => $v) {
      if ($v === null || $v === '') continue;
      $q[$k] = $v;
    }
  }
  header('Location: /admin/index.php?' . http_build_query($q));
  exit;
}

function require_save_or_redirect($store, $tab, $extra = [], $customErr = null) {
  if (!save_store($store)) {
    $err = $customErr ?: 'Impossibile salvare. Verifica i permessi della cartella /data.';
    redirect_tab($tab, null, $err, $extra);
  }
}

// Hold the store lock for the whole admin action: re-read the data while locked so
// a concurrent iCal sync / Stripe webhook cannot be overwritten by this save.
store_lock();
$store = load_store();

switch ($action) {
  case 'save_settings':
    $store['settings']['site_name'] = trim((string)($_POST['site_name'] ?? ''));
    $store['settings']['address'] = trim((string)($_POST['address'] ?? ''));
    $store['settings']['phone'] = trim((string)($_POST['phone'] ?? ''));
    $store['settings']['email'] = trim((string)($_POST['email'] ?? ''));
    $notifyEmail = trim((string)($_POST['notify_email'] ?? ''));
    if ($notifyEmail === '' || !filter_var($notifyEmail, FILTER_VALIDATE_EMAIL)) {
      $notifyEmail = 'viaarriano11@gmail.com';
    }
    $store['settings']['notify_email'] = $notifyEmail;
    $store['settings']['booking_ical_url'] = trim((string)($_POST['booking_ical_url'] ?? ''));

    $basePrice = (float)($_POST['base_price'] ?? 0);
    if ($basePrice < 0) $basePrice = 0;
    $store['settings']['base_price'] = round($basePrice, 2);

    $cleaning = (float)($_POST['cleaning_fee'] ?? 0);
    if ($cleaning < 0) $cleaning = 0;
    $store['settings']['cleaning_fee'] = round($cleaning, 2);

    $cityTax = (float)($_POST['city_tax_per_person_per_night'] ?? 6);
    if ($cityTax < 0) $cityTax = 0;
    // Tassa di soggiorno: importo per persona per notte
    $store['settings']['city_tax_per_person_per_night'] = round($cityTax, 2);

    $minNights = (int)($_POST['min_nights'] ?? 1);
    if ($minNights < 1) $minNights = 1;
    if ($minNights > 60) $minNights = 60;
    $store['settings']['min_nights'] = $minNights;

    $currency = strtoupper(trim((string)($_POST['currency'] ?? 'EUR')));
    if (!preg_match('/^[A-Z]{3}$/', $currency)) $currency = 'EUR';
    $store['settings']['currency'] = $currency;

    // Stripe
    // From a <select> we receive the chosen value, so we must read it (not just isset()).
    $reqPayment = (int)($_POST['stripe_require_payment'] ?? 0);
    $store['settings']['stripe_require_payment'] = ($reqPayment === 1) ? 1 : 0;

    $deposit = (float)($_POST['stripe_deposit_percent'] ?? 1.0);
    if ($deposit <= 0) $deposit = 1.0;
    if ($deposit > 1) $deposit = 1.0;
    // Avoid absurdly small amounts (still configurable, but keep sane defaults)
    if ($deposit < 0.05) $deposit = 0.05;
    $store['settings']['stripe_deposit_percent'] = round($deposit, 2);

    $hold = (int)($_POST['stripe_hold_minutes'] ?? 30);
    // Stripe requires the Checkout Session expires_at to be at least 30 minutes from creation.
    // Keep admin + Stripe aligned by enforcing a minimum of 30 minutes.
    if ($hold < 30) $hold = 30;
    if ($hold > 240) $hold = 240;
    $store['settings']['stripe_hold_minutes'] = $hold;

    // Google Analytics 4 measurement ID (e.g. G-ABC123XYZ). Empty = disabled.
    $ga4 = strtoupper(trim((string)($_POST['ga4_id'] ?? '')));
    if ($ga4 !== '' && !preg_match('/^G-[A-Z0-9]{4,20}$/', $ga4)) {
      redirect_tab('settings', null, 'ID Google Analytics non valido: deve iniziare con G- (es. G-ABC123XYZ).');
    }
    $store['settings']['ga4_id'] = $ga4;

    $store['settings']['stripe_secret_key'] = trim((string)($_POST['stripe_secret_key'] ?? ''));
    $store['settings']['stripe_webhook_secret'] = trim((string)($_POST['stripe_webhook_secret'] ?? ''));

    // Optional: sync interval may not be present in the form (keep existing value).
    $syncEvery = isset($_POST['sync_interval_minutes'])
      ? (int)$_POST['sync_interval_minutes']
      : (int)($store['settings']['sync_interval_minutes'] ?? 5);
    if ($syncEvery < 5) $syncEvery = 5;
    if ($syncEvery > 1440) $syncEvery = 1440;
    $store['settings']['sync_interval_minutes'] = $syncEvery;

    require_save_or_redirect($store, 'settings');
    redirect_tab('settings', 'Impostazioni salvate.');
    break;

  case 'change_password':
    $new = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['new_password_confirm'] ?? '');
    if ($new === '' || $confirm === '' || $new !== $confirm) {
      redirect_tab('settings', null, 'Le password non coincidono.');
    }
    if (strlen($new) < 8) {
      redirect_tab('settings', null, 'Password troppo corta (minimo 8 caratteri).');
    }
    // Optional hardening: forbid very long passwords to avoid abuse (still plenty).
    if (strlen($new) > 200) {
      redirect_tab('settings', null, 'Password troppo lunga.');
    }
    $store['settings']['admin_pass_hash'] = password_hash($new, PASSWORD_DEFAULT);
    require_save_or_redirect($store, 'settings', [], 'Impossibile salvare la password. Verifica i permessi della cartella /data.');
    redirect_tab('settings', 'Password aggiornata.');
    break;

  case 'change_username':
    $newUser = trim((string)($_POST['new_username'] ?? ''));
    // Allow only safe username chars
    if ($newUser === '' || strlen($newUser) < 3 || strlen($newUser) > 32 || !preg_match('/^[A-Za-z0-9._-]{3,32}$/', $newUser)) {
      redirect_tab('settings', null, 'Nome utente non valido. Usa 3-32 caratteri: lettere, numeri, punti, _ o -');
    }
    $store['settings']['admin_user'] = $newUser;
    require_save_or_redirect($store, 'settings', [], 'Impossibile salvare il nome utente. Verifica i permessi della cartella /data.');
    redirect_tab('settings', 'Nome utente aggiornato.');
    break;

  case 'approve_booking':
    $id = (string)($_POST['id'] ?? '');
    $found = false;
    foreach ($store['bookings'] as $i => $bk) {
      if ($id !== '' && (string)($bk['id'] ?? '') === $id) {
        $found = true;
        // FIX: before confirming, check that the nights are still free (a Booking.com
        // reservation may have arrived in the meantime) → avoids double bookings.
        if (!is_range_available_excluding($store, (string)($bk['start'] ?? ''), (string)($bk['end'] ?? ''), $id)) {
          redirect_tab('bookings', null, 'Impossibile confermare: le date ' . (string)($bk['start'] ?? '') . ' → ' . (string)($bk['end'] ?? '') . ' non sono più libere (es. prenotazione arrivata da Booking). Rifiuta la richiesta o contatta l’ospite.');
        }
        $store['bookings'][$i]['status'] = 'booked';
        $store['bookings'][$i]['approved_at'] = time();
      }
    }
    if (!$found) {
      redirect_tab('bookings', null, 'Prenotazione non trovata.');
    }
    require_save_or_redirect($store, 'bookings');
    redirect_tab('bookings', 'Prenotazione confermata. Ricordati di scrivere all’ospite per confermargli il soggiorno.');
    break;

  case 'reject_booking':
    $id = (string)($_POST['id'] ?? '');
    foreach ($store['bookings'] as &$bk) {
      if (($bk['id'] ?? '') === $id) {
        $bk['status'] = 'rejected';
        $bk['rejected_at'] = time();
      }
    }
    unset($bk);
    require_save_or_redirect($store, 'bookings');
    redirect_tab('bookings', 'Richiesta rifiutata.');
    break;

  case 'delete_booking':
    $id = (string)($_POST['id'] ?? '');
    $store['bookings'] = array_values(array_filter($store['bookings'], function($b) use ($id) {
      return ($b['id'] ?? '') !== $id;
    }));
    require_save_or_redirect($store, 'bookings');
    redirect_tab('bookings', 'Prenotazione eliminata.');
    break;

  case 'send_review_invite':
    $id = (string)($_POST['id'] ?? '');
    $found = false;
    $ok = false;
    foreach ($store['bookings'] as &$bk) {
      if ((string)($bk['id'] ?? '') === $id && $id !== '') {
        $found = true;
        if (!booking_is_eligible_for_review($bk)) {
          unset($bk);
          redirect_tab('bookings', null, 'Invito recensione disponibile solo dopo il check-out (e per prenotazioni confermate).');
        }

        // Send email (token generated if missing)
        $ok = maybe_send_review_invite_email($store, $bk);
        break;
      }
    }
    unset($bk);

    if (!$found) {
      redirect_tab('bookings', null, 'Prenotazione non trovata.');
    }

    // Save the store even if the email fails (we may have generated a token)
    require_save_or_redirect($store, 'bookings');

    if ($ok) {
      redirect_tab('bookings', 'Invito recensione inviato.');
    }
    redirect_tab('bookings', null, 'Email non inviata. Copia il link recensione dalla riga e invialo manualmente.');
    break;

  case 'approve_review':
    $rid = (string)($_POST['id'] ?? '');
    $reviews = load_reviews();
    $changed = false;
    foreach ($reviews as &$r) {
      if ((string)($r['id'] ?? '') === $rid && $rid !== '') {
        $r['status'] = 'approved';
        $r['approved_at'] = time();
        $changed = true;
        break;
      }
    }
    unset($r);
    if (!$changed) {
      redirect_tab('reviews', null, 'Recensione non trovata.');
    }
    if (!save_reviews($reviews)) {
      redirect_tab('reviews', null, 'Impossibile salvare le recensioni. Verifica i permessi della cartella /data.');
    }
    redirect_tab('reviews', 'Recensione approvata.');
    break;

  case 'reject_review':
    $rid = (string)($_POST['id'] ?? '');
    $reviews = load_reviews();
    $changed = false;
    foreach ($reviews as &$r) {
      if ((string)($r['id'] ?? '') === $rid && $rid !== '') {
        $r['status'] = 'rejected';
        $r['rejected_at'] = time();
        $changed = true;
        break;
      }
    }
    unset($r);
    if (!$changed) {
      redirect_tab('reviews', null, 'Recensione non trovata.');
    }
    if (!save_reviews($reviews)) {
      redirect_tab('reviews', null, 'Impossibile salvare le recensioni. Verifica i permessi della cartella /data.');
    }
    redirect_tab('reviews', 'Recensione rifiutata.');
    break;

  case 'delete_review':
    $rid = (string)($_POST['id'] ?? '');
    $reviews = load_reviews();
    $before = count($reviews);
    $reviews = array_values(array_filter($reviews, function($r) use ($rid) {
      return is_array($r) && (string)($r['id'] ?? '') !== (string)$rid;
    }));
    if (count($reviews) === $before) {
      redirect_tab('reviews', null, 'Recensione non trovata.');
    }
    if (!save_reviews($reviews)) {
      redirect_tab('reviews', null, 'Impossibile salvare le recensioni. Verifica i permessi della cartella /data.');
    }
    // Best-effort cleanup: remove the review_id reference from bookings
    foreach ($store['bookings'] as &$bk) {
      if ((string)($bk['review_id'] ?? '') === $rid) {
        $bk['review_id'] = '';
      }
    }
    unset($bk);
    @save_store($store);
    redirect_tab('reviews', 'Recensione eliminata.');
    break;

  case 'manual_sync':
    // Download outside the lock (can take seconds), then apply under the lock.
    store_unlock();
    $res = sync_booking_locked($store);
    if (is_array($res) && !empty($res[0])) {
      $n = isset($res[1]) ? (int)$res[1] : 0;
      redirect_tab('sync', 'Sync completato. Importati ' . $n . ' eventi.');
    }
    $errMsg = (is_array($res) && isset($res[1])) ? (string)$res[1] : 'Sync non riuscita.';
    redirect_tab('sync', null, $errMsg);
    break;

  case 'indexnow_submit':
    store_unlock();
    [$okIn, $msgIn] = indexnow_submit(indexnow_public_urls());
    if ($okIn) redirect_tab('settings', $msgIn);
    redirect_tab('settings', null, $msgIn);
    break;

  case 'regen_ical_token':
    // Regenerate the private token used for iCal export (sito -> Booking)
    $store['settings']['ical_export_secret'] = bin2hex(random_bytes_fallback(16));
    require_save_or_redirect($store, 'sync');
    redirect_tab('sync', 'Link export rigenerato. Aggiorna il link anche su Booking se lo stavi gia usando.');
    break;
  case 'toggle_ical_public':
    // Enable/disable public iCal export (privacy hardening)
    $enable = (int)($_POST['enable'] ?? 0);
    $store['settings']['ical_export_public_enabled'] = ($enable === 1) ? 1 : 0;
    require_save_or_redirect($store, 'sync');
    if ($enable === 1) {
      redirect_tab('sync', 'Export pubblico abilitato (sconsigliato).');
    } else {
      redirect_tab('sync', 'Export pubblico disabilitato: ora serve il token.');
    }
    break;


  case 'calendar_bulk_update':
    $datesCsv = trim((string)($_POST['dates'] ?? ''));
    $month = isset($_POST['month']) ? (string)$_POST['month'] : '';

    $dates = array_values(array_filter(array_map('trim', explode(',', $datesCsv))));
    $dates = array_values(array_filter(array_map('normalize_ymd', $dates)));

    if (!$dates) {
      redirect_tab('calendar', null, 'Seleziona almeno un giorno.', ['month' => $month]);
    }

    if (!isset($store['daily']) || !is_array($store['daily'])) {
      $store['daily'] = [];
    }

    $priceRaw = trim((string)($_POST['price'] ?? ''));
    $price = null;
    if ($priceRaw !== '') {
      $p = (float)$priceRaw;
      if ($p < 0) $p = 0;
      $price = round($p, 2);
    }

    $availability = (string)($_POST['availability'] ?? 'keep');
    if (!in_array($availability, ['keep', 'open', 'closed'], true)) {
      $availability = 'keep';
    }

    $minRaw = trim((string)($_POST['min_nights'] ?? ''));
    $minN = null;
    if ($minRaw !== '') {
      $m = (int)$minRaw;
      if ($m < 1) $m = 1;
      if ($m > 60) $m = 60;
      $minN = $m;
    }

    foreach ($dates as $d) {
      $entry = $store['daily'][$d] ?? [];
      if (!is_array($entry)) $entry = [];

      if ($price !== null) {
        $entry['price'] = $price;
      }

      if ($availability === 'open') {
        $entry['closed'] = false;
      } elseif ($availability === 'closed') {
        $entry['closed'] = true;
      }

      if ($minN !== null) {
        $entry['min_nights'] = $minN;
      }

      // Clean empty entry
      $clean = [];
      foreach (['price', 'closed', 'min_nights'] as $k) {
        if (array_key_exists($k, $entry)) {
          $clean[$k] = $entry[$k];
        }
      }
      if (!$clean) {
        unset($store['daily'][$d]);
      } else {
        $store['daily'][$d] = $clean;
      }
    }

    require_save_or_redirect($store, 'calendar', ['month' => $month], 'Impossibile salvare il calendario. Verifica i permessi della cartella /data.');
    redirect_tab('calendar', 'Aggiornamento applicato.', null, ['month' => $month]);
    break;

  case 'calendar_bulk_clear':
    $datesCsv = trim((string)($_POST['dates'] ?? ''));
    $month = isset($_POST['month']) ? (string)$_POST['month'] : '';

    $dates = array_values(array_filter(array_map('trim', explode(',', $datesCsv))));
    $dates = array_values(array_filter(array_map('normalize_ymd', $dates)));

    if (!$dates) {
      redirect_tab('calendar', null, 'Seleziona almeno un giorno.', ['month' => $month]);
    }

    if (!isset($store['daily']) || !is_array($store['daily'])) {
      $store['daily'] = [];
    }

    foreach ($dates as $d) {
      unset($store['daily'][$d]);
    }

    require_save_or_redirect($store, 'calendar', ['month' => $month], 'Impossibile salvare il calendario. Verifica i permessi della cartella /data.');
    redirect_tab('calendar', 'Impostazioni rimosse.', null, ['month' => $month]);
    break;

  // ------------------------------------------------------------
  // Motore prezzi
  // ------------------------------------------------------------

  case 'pricing_engine_save_settings':
    if (!function_exists('pricing_engine_save_settings')) {
      redirect_tab('pricing_engine', null, 'Motore prezzi non disponibile sul server.');
    }
    list($ok, $errMsg) = pricing_engine_save_settings($store, $_POST);
    if (!$ok) {
      redirect_tab('pricing_engine', null, $errMsg ?: 'Impossibile salvare le impostazioni.');
    }
    require_save_or_redirect($store, 'pricing_engine', [], 'Impossibile salvare. Verifica i permessi della cartella /data.');
    redirect_tab('pricing_engine', 'Impostazioni motore salvate.');
    break;

  case 'pe_add_event':
    if (!function_exists('pe_add_event')) {
      redirect_tab('pricing_engine', null, 'Motore prezzi non disponibile sul server.');
    }
    $title = (string)($_POST['title'] ?? '');
    $start = (string)($_POST['start'] ?? '');
    $end = (string)($_POST['end'] ?? '');
    $lift = $_POST['lift'] ?? 0;
    list($ok, $errMsg) = pe_add_event($store, $title, $start, $end, $lift);
    if (!$ok) {
      redirect_tab('pricing_engine', null, $errMsg ?: 'Evento non aggiunto.');
    }
    require_save_or_redirect($store, 'pricing_engine', [], 'Impossibile salvare. Verifica i permessi della cartella /data.');
    redirect_tab('pricing_engine', 'Evento aggiunto.');
    break;

  case 'pe_delete_event':
    if (!function_exists('pe_delete_event')) {
      redirect_tab('pricing_engine', null, 'Motore prezzi non disponibile sul server.');
    }
    $id = (string)($_POST['id'] ?? '');
    list($ok, $errMsg) = pe_delete_event($store, $id);
    if (!$ok) {
      redirect_tab('pricing_engine', null, $errMsg ?: 'Evento non rimosso.');
    }
    require_save_or_redirect($store, 'pricing_engine', [], 'Impossibile salvare. Verifica i permessi della cartella /data.');
    redirect_tab('pricing_engine', 'Evento rimosso.');
    break;

  case 'pricing_engine_apply':
    if (!function_exists('pricing_engine_apply_suggestions')) {
      redirect_tab('pricing_engine', null, 'Motore prezzi non disponibile sul server.');
    }
    $from = (string)($_POST['from'] ?? '');
    $to = (string)($_POST['to'] ?? '');
    $dow = $_POST['dow'] ?? [];
    if (!is_array($dow)) $dow = [];
    $skipUnavailable = ((int)($_POST['skip_unavailable'] ?? 1) === 1);
    $skipIfPriceSet = ((int)($_POST['skip_if_price_set'] ?? 0) === 1);
    $overwrite = ((int)($_POST['overwrite'] ?? 1) === 1);

    // Optional per-run overrides (do NOT persist): floor/ceiling for this apply run.
    $floorOv = null;
    $ceilOv = null;
    if (isset($_POST['floor']) && $_POST['floor'] !== '') {
      $v = trim((string)$_POST['floor']);
      if (is_numeric($v)) $floorOv = (int)round((float)$v);
    }
    if (isset($_POST['ceiling']) && $_POST['ceiling'] !== '') {
      $v = trim((string)$_POST['ceiling']);
      if (is_numeric($v)) $ceilOv = (int)round((float)$v);
    }
    if ($floorOv !== null && $floorOv < 0) $floorOv = 0;
    if ($ceilOv !== null && $ceilOv < 0) $ceilOv = 0;
    if ($floorOv !== null && $ceilOv !== null && $ceilOv < $floorOv) {
      $tmp = $floorOv; $floorOv = $ceilOv; $ceilOv = $tmp;
    }

    $opts = [
      'dow' => $dow,
      'skip_unavailable' => $skipUnavailable,
      'skip_if_price_set' => $skipIfPriceSet,
      'overwrite' => $overwrite,
      'floor_override' => $floorOv,
      'ceiling_override' => $ceilOv,
    ];

    list($ok, $errMsg, $n) = pricing_engine_apply_suggestions($store, $from, $to, $opts);
    if (!$ok) {
      redirect_tab('pricing_engine', null, $errMsg ?: 'Impossibile applicare i prezzi.');
    }

    require_save_or_redirect($store, 'pricing_engine', [], 'Impossibile salvare. Verifica i permessi della cartella /data.');
    redirect_tab('pricing_engine', 'Prezzi applicati: ' . (int)$n);
    break;

  default:
    redirect_tab('calendar', null, 'Azione non valida.');
}
