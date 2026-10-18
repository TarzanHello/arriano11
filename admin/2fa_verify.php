<?php
require_once __DIR__ . '/../lib/lib.php';
require_once __DIR__ . '/../lib/totp.php';

// Security headers + no-cache for admin pages
send_security_headers(['admin' => true, 'no_store' => true]);

require_admin_password_only();

$state = admin_2fa_state();
if (empty($state['enabled']) || empty($state['secret'])) {
  // 2FA not enabled: go straight to admin
  $_SESSION['admin_2fa_ok'] = true;
  header('Location: /admin/index.php');
  exit;
}

// If already verified in this session
if (!empty($_SESSION['admin_2fa_ok'])) {
  header('Location: /admin/index.php');
  exit;
}

$err = '';
$csrf = csrf_token();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $token = (string)($_POST['csrf'] ?? '');
  if (!csrf_verify($token)) {
    $err = 'Sessione scaduta. Ricarica e riprova.';
  } else {
    // Rate limit 2FA attempts
    $key = client_ip();
    [$blocked, $retry] = rate_limit_is_blocked('admin_2fa', $key, 10, 10 * 60); // 10 attempts / 10 minutes
    if ($blocked) {
      $err = 'Troppi tentativi. Riprova tra circa ' . (int)$retry . ' secondi.';
    } else {
      $input = strtoupper(trim((string)($_POST['code'] ?? '')));
      $input = preg_replace('/\s+/', '', $input);

      $ok = false;

      // TOTP code (6 digits)
      if (preg_match('/^\d{6}$/', $input)) {
        $ok = totp_verify_code($state['secret'], $input, 1, 6);
      } else {
        // Recovery code (format: XXXX-XXXX)
        if (preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/', $input)) {
          $hashes = $state['recovery_hashes'] ?? [];
          if (is_array($hashes)) {
            foreach ($hashes as $i => $h) {
              if (is_string($h) && $h !== '' && password_verify($input, $h)) {
                // Consume recovery code
                unset($hashes[$i]);
                $state['recovery_hashes'] = array_values($hashes);
                admin_2fa_save_state($state);
                $ok = true;
                break;
              }
            }
          }
        }
      }

      if ($ok) {
        rate_limit_clear('admin_2fa', $key);
        $_SESSION['admin_2fa_ok'] = true;
        header('Location: /admin/index.php');
        exit;
      }

      // Count only failed attempts
      rate_limit_hit('admin_2fa', $key, 10, 10 * 60);
      $err = 'Codice non valido. Riprova.';
    }
  }
}

$store = load_store();
$siteName = $store['settings']['site_name'] ?? 'Via Arriano 11';
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verifica 2FA · <?php echo e($siteName); ?></title>
  <link rel="icon" href="/favicon.ico">
  <?php $css_v = @filemtime(__DIR__ . '/../assets/css/style.css') ?: 1; ?>
  <link rel="stylesheet" href="/assets/css/style.css?v=<?php echo e((string)$css_v); ?>">
</head>
<body>
  <main class="site-main">
    <div class="container">
      <div class="card" style="padding:22px; max-width:540px; margin:40px auto;">
        <h1 style="margin:0 0 10px;">Verifica 2FA</h1>
        <p class="muted" style="margin:0 0 14px;">Inserisci il codice a 6 cifre da Google Authenticator (oppure un codice di recupero).</p>

        <?php if ($err): ?>
          <div class="notice danger" style="margin-bottom:12px;"><?php echo e($err); ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
          <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
          <div>
            <label>Codice</label>
            <input name="code" required maxlength="12" placeholder="123456 oppure ABCD-EFGH" style="letter-spacing:1px;">
            <div class="small muted" style="margin-top:6px;">Codice app: 6 cifre. Recupero: formato <code>ABCD-EFGH</code>.</div>
          </div>
          <div class="actions" style="margin-top:14px;">
            <button class="cta" type="submit">Verifica</button>
          </div>
        </form>

        <form method="post" action="/admin/logout.php" style="margin:10px 0 0;">
          <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
          <button class="btn" type="submit">Annulla e esci</button>
        </form>

      </div>
    </div>
  </main>
</body>
</html>
