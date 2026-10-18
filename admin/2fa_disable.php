<?php
require_once __DIR__ . '/../lib/lib.php';

// Security headers + no-cache for admin pages
send_security_headers(['admin' => true, 'no_store' => true]);

require_admin();

$state = admin_2fa_state();
if (empty($state['enabled']) || empty($state['secret'])) {
  header('Location: /admin/2fa_setup.php?msg=' . urlencode('2FA già disattivata.'));
  exit;
}

$err = '';
$csrf = csrf_token();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $token = (string)($_POST['csrf'] ?? '');
  if (!csrf_verify($token)) {
    $err = 'Sessione scaduta. Ricarica e riprova.';
  } else {
    if (admin_2fa_disable()) {
      unset($_SESSION['admin_2fa_ok']);
      header('Location: /admin/index.php?tab=settings&msg=' . urlencode('2FA disattivata.'));
      exit;
    }
    $err = 'Impossibile disattivare 2FA. Verifica i permessi della cartella /data.';
  }
}

$store = load_store();
$siteName = (string)($store['settings']['site_name'] ?? 'Via Arriano 11');
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Disattiva 2FA · <?php echo e($siteName); ?></title>
  <link rel="icon" href="/favicon.ico">
  <?php $css_v = @filemtime(__DIR__ . '/../assets/css/style.css') ?: 1; ?>
  <link rel="stylesheet" href="/assets/css/style.css?v=<?php echo e((string)$css_v); ?>">
</head>
<body>
  <main class="site-main">
    <div class="container">
      <div class="card" style="padding:22px; max-width:640px; margin:40px auto;">
        <h1 style="margin:0 0 10px;">Disattiva 2FA</h1>
        <p class="muted" style="margin:0 0 14px;">
          Questa operazione rimuove l’autenticazione a due fattori per l’area admin.
        </p>

        <?php if ($err): ?>
          <div class="notice danger" style="margin-bottom:12px;"><?php echo e($err); ?></div>
        <?php endif; ?>

        <form method="post">
          <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
          <div class="actions" style="margin-top:14px; display:flex; gap:10px; flex-wrap:wrap;">
            <button class="cta" type="submit">Conferma disattivazione</button>
            <a class="btn" href="/admin/2fa_setup.php">Annulla</a>
          </div>
        </form>

      </div>
    </div>
  </main>
</body>
</html>
