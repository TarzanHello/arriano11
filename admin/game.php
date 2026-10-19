<?php
// Admin → "Classifica itinerari" (patch 12, Oct 2026): the leaderboard of the itinerary game.
// Hide a nickname you don't like (it disappears from the public ranking; points stay) or show it again.
require_once __DIR__ . '/../lib/lib.php';
require_once __DIR__ . '/../lib/i18n.php';

require_admin();
send_security_headers(['admin' => true, 'no_store' => true]);

$store = load_store();
$siteName = $store['settings']['site_name'] ?? 'Via Arriano 11';
$msg = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if (!csrf_verify((string)($_POST['csrf'] ?? ''))) {
    $msg = 'Sessione scaduta: ricarica la pagina e riprova.';
  } else {
    $code = itin_code_normalize((string)($_POST['code'] ?? ''));
    $hide = ($_POST['do'] ?? '') === 'hide';
    if ($code !== '') {
      itin_board_update(function ($rows) use ($code, $hide) {
        foreach ($rows as $c => $r) {
          if ((string)$c === $code) $rows[$c]['hidden'] = $hide;
        }
        return $rows;
      });
      header('Location: /admin/game.php?ok=1', true, 303);
      exit;
    }
  }
}

$rows = itin_board_rows();
uasort($rows, function ($a, $b) { return (int)($b['points'] ?? 0) <=> (int)($a['points'] ?? 0); });
$public = itin_board_public(0);
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Classifica itinerari · <?php echo e($siteName); ?></title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>
    .gm-table{ width:100%; border-collapse:collapse; }
    .gm-table th, .gm-table td{ padding:8px 10px; border-bottom:1px solid #ddd; text-align:left; vertical-align:middle; }
    .gm-hidden td{ opacity:.5; }
    .gm-code{ font-family:ui-monospace, Menlo, Consolas, monospace; }
  </style>
</head>
<body>
  <main class="site-main">
    <div class="container" style="padding:18px 0 40px; max-width:980px;">
      <p><a class="btn" href="/admin/index.php">← Torna all'admin</a></p>
      <h1 style="margin:10px 0 6px;">Classifica itinerari</h1>
      <p class="muted">Gli ospiti che usano "Avvia percorso" sugli itinerari guadagnano punti quando il GPS conferma l'arrivo alle tappe. In classifica pubblica (pagina Itinerari) compaiono solo quelli con un soprannome. Se un soprannome non ti piace, nascondilo: i punti restano, ma non si vede più.</p>
      <?php if ($msg !== ''): ?><div class="notice danger"><?php echo e($msg); ?></div><?php endif; ?>
      <?php if (isset($_GET['ok'])): ?><div class="notice">Fatto.</div><?php endif; ?>

      <?php if (!$rows): ?>
        <p><strong>Nessun giocatore ancora.</strong></p>
      <?php else: ?>
        <table class="gm-table">
          <thead><tr><th>Soprannome</th><th>Punti</th><th>Tappe</th><th>Badge</th><th>Ultima attività</th><th>Codice</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($rows as $c => $r): $c = (string)$c; $hidden = !empty($r['hidden']); ?>
              <tr class="<?php echo $hidden ? 'gm-hidden' : ''; ?>">
                <td><?php echo ($r['nick'] ?? '') !== '' ? e((string)$r['nick']) : '<span class="muted">(nessuno)</span>'; ?><?php echo isset($public[$c]) ? '' : ' <span class="muted small">· non in classifica</span>'; ?></td>
                <td><?php echo (int)($r['points'] ?? 0); ?></td>
                <td><?php echo (int)($r['stops'] ?? 0); ?></td>
                <td><?php echo count((array)($r['badges'] ?? [])); ?></td>
                <td><?php echo !empty($r['updated']) ? e(date('d/m/Y H:i', (int)$r['updated'])) : ''; ?></td>
                <td class="gm-code"><a href="<?php echo e(i18n_url('itin_saved', 'it') . '/' . $c); ?>" target="_blank" rel="noopener"><?php echo e(itin_code_display($c)); ?></a></td>
                <td>
                  <form method="post" style="margin:0;">
                    <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                    <input type="hidden" name="code" value="<?php echo e($c); ?>">
                    <?php if ($hidden): ?>
                      <button class="btn" type="submit" name="do" value="show">Mostra</button>
                    <?php else: ?>
                      <button class="btn" type="submit" name="do" value="hide">Nascondi</button>
                    <?php endif; ?>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
