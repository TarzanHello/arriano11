<?php
require_once __DIR__ . '/../lib/lib.php';
require_once __DIR__ . '/../lib/totp.php';

// Security headers + no-cache for admin pages
send_security_headers(['admin' => true, 'no_store' => true]);

require_admin_password_only();

// If 2FA is enabled, require a fully authenticated admin to manage it
if (admin_2fa_enabled() && empty($_SESSION['admin_2fa_ok'])) {
  header('Location: /admin/2fa_verify.php');
  exit;
}

$store = load_store();
$siteName = (string)($store['settings']['site_name'] ?? 'Via Arriano 11');
$adminUser = (string)($store['settings']['admin_user'] ?? 'admin');

$state = admin_2fa_state();
$enabled = !empty($state['enabled']) && !empty($state['secret']);

$csrf = csrf_token();
$err = '';
$msg = '';

function generate_recovery_codes($count = 8) {
  $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // avoid ambiguous chars
  $codes = [];
  for ($i = 0; $i < $count; $i++) {
    $raw = '';
    for ($j = 0; $j < 8; $j++) {
      $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    $codes[] = substr($raw, 0, 4) . '-' . substr($raw, 4);
  }
  return $codes;
}

$pendingSecret = (string)($_SESSION['admin_2fa_pending_secret'] ?? '');
$newRecoveryCodes = $_SESSION['admin_2fa_new_recovery_codes'] ?? null; // array shown once

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $token = (string)($_POST['csrf'] ?? '');
  if (!csrf_verify($token)) {
    $err = 'Sessione scaduta. Ricarica e riprova.';
  } else {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'start') {
      $_SESSION['admin_2fa_pending_secret'] = totp_generate_secret(20);
      unset($_SESSION['admin_2fa_new_recovery_codes']);
      header('Location: /admin/2fa_setup.php');
      exit;
    }

    if ($action === 'cancel') {
      unset($_SESSION['admin_2fa_pending_secret']);
      unset($_SESSION['admin_2fa_new_recovery_codes']);
      header('Location: /admin/2fa_setup.php');
      exit;
    }

    if ($action === 'ack_codes') {
      unset($_SESSION['admin_2fa_new_recovery_codes']);
      header('Location: /admin/2fa_setup.php');
      exit;
    }

    if ($action === 'confirm') {
      $pendingSecret = (string)($_SESSION['admin_2fa_pending_secret'] ?? '');
      if ($pendingSecret === '') {
        $err = 'Nessuna configurazione in corso. Premi "Avvia setup".';
      } else {
        $code = (string)($_POST['code'] ?? '');
        if (!totp_verify_code($pendingSecret, $code, 1, 6)) {
          $err = 'Codice non valido. Assicurati che l\'orario del telefono sia corretto e riprova.';
        } else {
          $codes = generate_recovery_codes(8);
          $hashes = [];
          foreach ($codes as $c) {
            $hashes[] = password_hash($c, PASSWORD_DEFAULT);
          }

          $ok = admin_2fa_save_state([
            'enabled' => true,
            'secret' => $pendingSecret,
            'recovery_hashes' => $hashes,
            'created_at' => time(),
          ]);

          if (!$ok) {
            $err = 'Impossibile salvare la configurazione 2FA. Verifica i permessi della cartella /data.';
          } else {
            $_SESSION['admin_2fa_ok'] = true;
            $_SESSION['admin_2fa_new_recovery_codes'] = $codes; // show once
            unset($_SESSION['admin_2fa_pending_secret']);

            $state = admin_2fa_state();
            $enabled = !empty($state['enabled']) && !empty($state['secret']);
            $pendingSecret = '';
            $newRecoveryCodes = $codes;
            $msg = '2FA attivata con successo.';
          }
        }
      }
    }

    if ($action === 'regen_recovery') {
      if (!$enabled) {
        $err = 'Attiva prima la 2FA.';
      } else {
        $codes = generate_recovery_codes(8);
        $hashes = [];
        foreach ($codes as $c) {
          $hashes[] = password_hash($c, PASSWORD_DEFAULT);
        }
        $ok = admin_2fa_save_state([
          'enabled' => true,
          'secret' => (string)($state['secret'] ?? ''),
          'recovery_hashes' => $hashes,
          'created_at' => (int)($state['created_at'] ?? time()),
        ]);
        if (!$ok) {
          $err = 'Impossibile salvare. Verifica i permessi della cartella /data.';
        } else {
          $_SESSION['admin_2fa_new_recovery_codes'] = $codes;
          $newRecoveryCodes = $codes;
          $msg = 'Nuovi codici di recupero generati.';
        }
      }
    }
  }
}

$issuer = $siteName;
$account = $adminUser;
$otpauth = '';
$qrUrl = '';

if ($pendingSecret !== '') {
  $label = rawurlencode($issuer . ':' . $account);
  $otpauth = "otpauth://totp/{$label}?secret={$pendingSecret}&issuer=" . rawurlencode($issuer) . "&digits=6&period=30";
  $qrUrl = ''; // QR disabled (privacy): do not send the secret to third-party services
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Setup 2FA · <?php echo e($siteName); ?></title>
  <link rel="icon" href="/favicon.ico">
  <?php $css_v = @filemtime(__DIR__ . '/../assets/css/style.css') ?: 1; ?>
  <link rel="stylesheet" href="/assets/css/style.css?v=<?php echo e((string)$css_v); ?>">
</head>
<body>
  <main class="site-main">
    <div class="container">
      <div class="card" style="padding:22px; max-width:720px; margin:40px auto;">
        <h1 style="margin:0 0 10px;">Autenticazione a due fattori (2FA)</h1>
        <p class="muted" style="margin:0 0 14px;">Protegge l\'accesso admin con un codice temporaneo (Google Authenticator).</p>

        <?php if ($err): ?>
          <div class="notice danger" style="margin-bottom:12px;"><?php echo e($err); ?></div>
        <?php endif; ?>
        <?php if ($msg): ?>
          <div class="notice" style="margin-bottom:12px;"><?php echo e($msg); ?></div>
        <?php endif; ?>

        <div class="notice" style="margin-bottom:12px;">
          <strong>Stato:</strong>
          <?php if ($enabled): ?>
            <span style="color:var(--ok); font-weight:800;">Attiva</span>
          <?php else: ?>
            <span style="color:var(--warn); font-weight:800;">Disattivata</span>
          <?php endif; ?>
        </div>

        <?php if (!$enabled && $pendingSecret === ''): ?>
          <h3 style="margin:16px 0 8px;">1) Avvia setup</h3>
          <p style="margin:0 0 12px;">Premi per generare il QR. Poi scansiona con Google Authenticator e conferma inserendo un codice a 6 cifre.</p>
          <form method="post" style="margin:0;">
            <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
            <input type="hidden" name="action" value="start">
            <button class="cta" type="submit">Avvia setup 2FA</button>
            <a class="btn" href="/admin/index.php?tab=settings" style="margin-left:8px;">Torna alle impostazioni</a>
          </form>

        <?php elseif (!$enabled && $pendingSecret !== ''): ?>
          <h3 style="margin:16px 0 8px;">1) Scansiona il QR</h3>
          <p style="margin:0 0 10px;">Google Authenticator → “+” → Scansiona un codice QR.</p>

          <div style="display:flex; gap:18px; flex-wrap:wrap; align-items:center; margin:12px 0 8px;">
            <div class="notice warn" style="max-width:420px;">
              <strong>QR disabilitato per privacy.</strong><br>
              Per evitare che il secret 2FA venga inviato a servizi esterni, il QR non viene generato automaticamente.
              Inserisci il secret manualmente nell’app (consigliato).
            </div>
            <div style="min-width:260px;">
              <div class="small muted" style="margin-bottom:6px;">Se non puoi scansionare, inserisci manualmente questo secret:</div>
              <code style="display:inline-block; padding:8px 10px; border:1px solid var(--border); border-radius:10px;"><?php echo e($pendingSecret); ?></code>
              <div class="small muted" style="margin-top:10px;">URI otpauth (per QR offline o app compatibili):</div>
              <code style="display:block; padding:8px 10px; border:1px solid var(--border); border-radius:10px; word-break:break-all;"><?php echo e($otpauth); ?></code>
              <div class="small muted" style="margin-top:10px;">Tipo: <strong>TOTP</strong> · 6 cifre · 30s</div>
            </div>
          </div>

          <h3 style="margin:16px 0 8px;">2) Conferma</h3>
          <p style="margin:0 0 10px;">Inserisci un codice generato dall\'app per completare l\'attivazione.</p>

          <form method="post" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
            <input type="hidden" name="action" value="confirm">
            <input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="123456" required
                   style="font-size:22px; padding:10px; width:180px; letter-spacing:4px;">
            <button class="cta" type="submit">Attiva 2FA</button>
          </form>

          <form method="post" style="margin-top:10px;">
            <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
            <input type="hidden" name="action" value="cancel">
            <button class="btn" type="submit">Annulla</button>
          </form>

        <?php else: ?>
          <h3 style="margin:16px 0 8px;">Codici di recupero</h3>
          <p style="margin:0 0 10px;">Conservali: servono se perdi il telefono. Ogni codice funziona una sola volta.</p>

          <?php if (is_array($newRecoveryCodes) && count($newRecoveryCodes) > 0): ?>
            <div class="notice" style="margin:10px 0 12px;">
              <strong>Salvali ora:</strong> non verranno mostrati di nuovo.
            </div>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap:8px;">
              <?php foreach ($newRecoveryCodes as $c): ?>
                <code style="display:block; padding:8px 10px; border:1px solid var(--border); border-radius:10px;"><?php echo e($c); ?></code>
              <?php endforeach; ?>
            </div>
            <form method="post" style="margin-top:12px;">
              <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
              <input type="hidden" name="action" value="ack_codes">
              <button class="btn" type="submit">Ho salvato i codici</button>
            </form>
          <?php endif; ?>

          <form method="post" style="margin-top:12px;">
            <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
            <input type="hidden" name="action" value="regen_recovery">
            <button class="btn" type="submit">Genera nuovi codici di recupero</button>
            <a class="btn" href="/admin/2fa_disable.php" style="margin-left:8px;">Disattiva 2FA</a>
            <a class="btn" href="/admin/index.php?tab=settings" style="margin-left:8px;">Torna alle impostazioni</a>
          </form>
        <?php endif; ?>

      </div>
    </div>
  </main>
</body>
</html>
