<?php
// Admin → "Controllo sito" (Oct 2026): tells whether every file of the current release is on the server
// and up to date, and whether PHP and the /data folder are fine. Useful after an FTP upload:
// a half-uploaded patch (some files old, some new) is the most common cause of "Errore 500".
// The list of expected files and their fingerprints is in /lib/release.php (regenerated with each patch).
require_once __DIR__ . '/../lib/lib.php';

require_admin();
send_security_headers(['admin' => true, 'no_store' => true]);

$store = load_store();
$siteName = $store['settings']['site_name'] ?? 'Via Arriano 11';
$root = realpath(__DIR__ . '/..');

$release = @include __DIR__ . '/../lib/release.php';
if (!is_array($release)) $release = ['version' => '?', 'date' => '', 'files' => []];

// Fingerprint ignoring Windows/Unix line endings (FTP in "ASCII" mode may change them)
function check_fingerprint($path) {
  $raw = @file_get_contents($path);
  if ($raw === false) return '';
  return sha1(str_replace("\r", '', $raw));
}

$missing = [];
$outdated = [];
foreach ((array)$release['files'] as $rel => $hash) {
  $p = $root . '/' . $rel;
  if (!is_file($p)) { $missing[] = $rel; continue; }
  if (check_fingerprint($p) !== $hash) $outdated[] = $rel;
}

// PHP and server
$checks = [];
$checks[] = ['Versione PHP ' . PHP_VERSION, version_compare(PHP_VERSION, '7.4.0', '>='), 'Serve PHP 7.4 o più recente (consigliato 8.1+). Si cambia dal pannello Aruba.'];
foreach (['json', 'mbstring', 'curl', 'openssl'] as $ext) {
  $checks[] = ['Estensione PHP ' . $ext, extension_loaded($ext), 'Da attivare nel pannello Aruba (estensioni PHP).'];
}
$dataDir = $root . '/data';
$checks[] = ['Cartella /data scrivibile', is_dir($dataDir) && is_writable($dataDir), 'Imposta i permessi della cartella /data a 775 (o 777) via FTP.'];
$itDir = $dataDir . '/itineraries';
if (!is_dir($itDir)) @mkdir($itDir, 0755, true);
$checks[] = ['Cartella /data/itineraries (itinerari salvati)', is_dir($itDir) && is_writable($itDir), 'Crea la cartella /data/itineraries e rendila scrivibile.'];

// Does the public part load? (catches fatal errors from mixed old/new files)
$loadError = '';
try {
  require_once __DIR__ . '/../lib/i18n.php';
  foreach (['via_articles', 'itin_texts', 'itin_schedule', 'copy_get'] as $fn) {
    if (!function_exists($fn)) $loadError .= 'Funzione mancante: ' . $fn . '. ';
  }
  if ($loadError === '' && function_exists('itin_texts')) {
    $t = itin_texts('it');
    if (empty($t['guides'])) $loadError = 'Testi degli itinerari non trovati (i18n/itineraries/it.php).';
  }
} catch (Throwable $ex) {
  $loadError = get_class($ex) . ': ' . $ex->getMessage() . ' (' . basename($ex->getFile()) . ':' . $ex->getLine() . ')';
}

$allOk = !$missing && !$outdated && $loadError === '' && !in_array(false, array_column($checks, 1), true);
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Controllo sito · <?php echo e($siteName); ?></title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>
    .ck-ok{ color:#18905C; font-weight:700; }
    .ck-ko{ color:#C8215A; font-weight:700; }
    .ck-list{ margin:6px 0 0; padding-left:20px; font-family:ui-monospace, Menlo, Consolas, monospace; font-size:14px; }
    .ck-table td{ padding:6px 10px 6px 0; vertical-align:top; }
    .ck-box{ padding:16px; border-radius:10px; margin:0 0 16px; }
    .ck-box.ok{ background:#E6F4EC; }
    .ck-box.ko{ background:#FBE8EF; }
  </style>
</head>
<body>
  <main class="site-main">
    <div class="container" style="padding:18px 0 40px; max-width:900px;">
      <p><a class="btn" href="/admin/index.php">← Torna all'admin</a></p>
      <h1 style="margin:10px 0 6px;">Controllo sito</h1>
      <p class="muted">Versione attesa: <strong><?php echo e((string)$release['version']); ?></strong><?php echo $release['date'] ? ' del ' . e((string)$release['date']) : ''; ?> · <?php echo count((array)$release['files']); ?> file controllati</p>

      <div class="ck-box <?php echo $allOk ? 'ok' : 'ko'; ?>">
        <?php if ($allOk): ?>
          <strong class="ck-ok">Tutto a posto.</strong> Tutti i file sono presenti e aggiornati, PHP e cartelle sono a posto.
        <?php else: ?>
          <strong class="ck-ko">Ci sono problemi da sistemare.</strong> Di solito basta ricaricare via FTP i file indicati qui sotto, prendendoli dall'ultimo pacchetto completo.
        <?php endif; ?>
      </div>

      <?php if ($loadError !== ''): ?>
        <h2>Errore nel caricamento delle pagine</h2>
        <p class="ck-ko"><?php echo e($loadError); ?></p>
        <p>Questo errore blocca le pagine del sito (Errore 500). Si risolve caricando tutti i file del pacchetto.</p>
      <?php endif; ?>

      <h2>File mancanti (<?php echo count($missing); ?>)</h2>
      <?php if ($missing): ?>
        <ul class="ck-list"><?php foreach ($missing as $f): ?><li><?php echo e($f); ?></li><?php endforeach; ?></ul>
      <?php else: ?><p class="ck-ok">Nessuno.</p><?php endif; ?>

      <h2>File vecchi o diversi (<?php echo count($outdated); ?>)</h2>
      <?php if ($outdated): ?>
        <ul class="ck-list"><?php foreach ($outdated as $f): ?><li><?php echo e($f); ?></li><?php endforeach; ?></ul>
      <?php else: ?><p class="ck-ok">Nessuno.</p><?php endif; ?>

      <h2>Server</h2>
      <table class="ck-table">
        <?php foreach ($checks as [$label, $ok, $help]): ?>
          <tr>
            <td><?php echo $ok ? '<span class="ck-ok">OK</span>' : '<span class="ck-ko">NO</span>'; ?></td>
            <td><?php echo e($label); ?><?php if (!$ok): ?><br><span class="muted"><?php echo e($help); ?></span><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </main>
</body>
</html>
