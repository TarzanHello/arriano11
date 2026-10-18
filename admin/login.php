<?php
require_once __DIR__ . '/../lib/lib.php';

// Security headers + no-cache for admin pages
send_security_headers(['admin' => true, 'no_store' => true]);

// Start a session + CSRF token (login CSRF hardening)
$csrf = csrf_token();

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = (string)($_POST['csrf'] ?? '');
  if (!csrf_verify($token)) {
    $err = 'Sessione scaduta. Ricarica la pagina e riprova.';
  } else {
    $u = (string)($_POST['username'] ?? '');
    $p = (string)($_POST['password'] ?? '');

  // Brute-force protection
  $key = client_ip();
  [$blocked, $retry] = rate_limit_is_blocked('admin_login', $key, 8, 15 * 60); // 8 attempts / 15 minutes
  if ($blocked) {
    $err = 'Troppi tentativi. Riprova tra circa ' . (int)$retry . ' secondi.';
  } else {
    if (admin_login($u, $p)) {
      rate_limit_clear('admin_login', $key);
      if (admin_2fa_enabled()) {
        header('Location: /admin/2fa_verify.php');
      } else {
        header('Location: /admin/index.php');
      }
      exit;
    }
    // Count only failed attempts
    rate_limit_hit('admin_login', $key, 8, 15 * 60);
    $err = 'Credenziali non valide.';
  }
  }
}

$store = load_store();
$siteName = $store['settings']['site_name'] ?? 'Arriano 11';
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login Admin · <?php echo e($siteName); ?></title>
  <link rel="icon" href="/favicon.ico">
  <?php $css_v = @filemtime(__DIR__ . '/../assets/css/style.css') ?: 1; ?>
  <link rel="stylesheet" href="/assets/css/style.css?v=<?php echo e((string)$css_v); ?>">
</head>
<body>
  <main class="site-main">
    <div class="container">
      <div class="card" style="padding:22px; max-width:540px; margin:40px auto;">
        <h1 style="margin:0 0 10px;">Area Admin</h1>
        <p class="muted" style="margin:0 0 14px;">Accedi per gestire calendario, blocchi, prezzi e sincronizzazione Booking (iCal).</p>

        <?php if ($err): ?>
          <div class="notice danger" style="margin-bottom:12px;"><?php echo e($err); ?></div>
        <?php endif; ?>

        <form method="post">
          <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
          <div class="form-row">
            <div>
              <label>Username</label>
              <input name="username" required autocomplete="username">
            </div>
            <div>
              <label>Password</label>
              <input type="password" name="password" required autocomplete="current-password">
            </div>
          </div>
          <div class="actions" style="margin-top:14px;">
            <button class="cta" type="submit">Entra</button>
            <a class="btn" href="/index.php">Torna al sito</a>
          </div>
        </form>


      </div>
    </div>
  </main>
</body>
</html>
