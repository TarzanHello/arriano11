<?php
// Saved itinerary (patch 10, Oct 2026): /itinerari/salvato/{CODE} — noindex.
// Also receives the "Hai già un codice?" form (?codice=…) and redirects to the clean address.
require_once __DIR__ . '/lib/lib.php';
require_once __DIR__ . '/lib/i18n.php';

i18n_init();
$svLang = i18n_route_lang('itin_saved', current_lang());
$svBase = i18n_url('itin_saved', $svLang);

// Form submission: ?codice=K7PM-4RQX → /itinerari/salvato/K7PM4RQX
if (isset($_GET['codice'])) {
  $c = itin_code_normalize((string)$_GET['codice']);
  if ($c !== '' && itin_load($c)) {
    header('Location: ' . $svBase . '/' . $c, true, 303);
  } else {
    header('Location: ' . i18n_url('itineraries', i18n_route_lang('itineraries', current_lang())) . '?codice_errato=1#itinCode', true, 303);
  }
  exit;
}

$plan = itin_load((string)($_GET['t'] ?? ''));
if (!$plan) {
  header('Location: ' . i18n_url('itineraries', i18n_route_lang('itineraries', current_lang())) . '?codice_errato=1#itinCode', true, 303);
  exit;
}
// Show the itinerary in the language it was saved in when this visitor's language has no texts
if (current_lang() !== $svLang) {
  header('Location: ' . $svBase . '/' . $plan['code'], true, 302);
  exit;
}
// Canonical form of the code in the address
$svWanted = $svBase . '/' . $plan['code'];
$svAsked = '/' . trim(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '', '/');
if ($svAsked !== $svWanted) {
  header('Location: ' . $svWanted, true, 301);
  exit;
}

$T = itin_texts();
$U = $T['ui'];
$n = (int)$plan['n'];
$builderRoute = 'itin_' . $n;
$svCode = itin_code_display($plan["code"]);

include __DIR__ . '/partials/header.php';

$pageHero = [
  'title' => (string)$T['pages']['itin_saved']['title'],
  'lead'  => (string)$T['pages']['itin_saved']['lead'],
  'image' => '/assets/images/gallery/giardino_1.jpg',
  'crumb' => $svCode,
  'crumbs' => [['label' => (string)$U['crumb'], 'route' => 'itineraries']],
];
include __DIR__ . '/partials/page-hero.php';

$places = itin_places();
$stations = itin_stations();
$days = itin_days();
$shareUrl = rtrim(base_url(), '/') . $svWanted;

$gmapsLL = function ($id) use ($places) { return $places[$id]['lat'] . ',' . $places[$id]['lng']; };
$homeAddr = 'Via Arriano 11, 00176 Roma';

$bookSeen = [];
$bookList = [];
?>

<main class="site-main">
  <section class="itin-tool-wrap">
    <div class="container">
      <div class="itin-saved-head">
        <div>
          <div class="small muted"><?php echo e($U['code_label']); ?></div>
          <div class="itin-saved-code"><?php echo e($svCode); ?></div>
          <?php if (!empty($plan['created'])): ?>
            <div class="small muted"><?php echo e(str_replace('{date}', date('d/m/Y', strtotime($plan['created'])), $U['saved_on'])); ?></div>
          <?php endif; ?>
        </div>
        <div class="actions itin-no-print">
          <button class="btn" type="button" data-copy="<?php echo e($shareUrl); ?>"><?php echo e($U['copy']); ?></button>
          <a class="btn btn-wa" href="https://wa.me/?text=<?php echo e(rawurlencode(str_replace('{url}', $shareUrl, $U['share_text']))); ?>" target="_blank" rel="noopener"><?php echo e($U['share_wa']); ?></a>
          <button class="btn" type="button" data-print><?php echo e($U['print']); ?></button>
          <a class="btn" href="<?php echo e(i18n_url($builderRoute) . '?codice=' . rawurlencode($plan['code'])); ?>"><?php echo e($U['edit']); ?></a>
        </div>
      </div>

      <div class="itin-tool" id="itinTool" data-mode="view">
        <div class="itin-map-col">
          <div class="itin-map" id="itinMap" role="region" aria-label="<?php echo e($U['map_label']); ?>"></div>
          <div class="itin-summary" id="itinSummary" aria-live="polite"></div>
        </div>
        <div class="itin-ctrl-col">
          <div class="itin-days" role="tablist" aria-label="<?php echo e($U['days_label']); ?>">
            <?php for ($d = 1; $d <= $n; $d++): ?>
              <button class="itin-day-btn" type="button" role="tab" data-day="<?php echo $d; ?>" aria-selected="<?php echo $d === 1 ? 'true' : 'false'; ?>">
                <span class="itin-day-n"><?php echo e(str_replace('{n}', (string)$d, $U['day'])); ?></span>
                <span class="itin-day-t"><?php echo e((string)$T['days'][$d]['title']); ?></span>
              </button>
            <?php endfor; ?>
          </div>

          <?php foreach ($plan['days'] as $i => $dp):
            $d = $i + 1;
            $lv = $dp['level'];
            $L = $T['days'][$d]['levels'][$lv];
            $C = $days[$d]['levels'][$lv];
            $sc = itin_schedule($d, $lv, $dp['stops']);
            $stopIds = array_map(function ($it) { return $it['place']; }, $sc['items']);
          ?>
            <div class="itin-panel itin-saved-day" data-day-panel="<?php echo $d; ?>"<?php echo $d > 1 ? ' hidden' : ''; ?>>
              <h2><?php echo e(str_replace('{n}', (string)$d, $U['day']) . ' · ' . $T['days'][$d]['title']); ?></h2>
              <p class="itin-saved-level">
                <?php echo e($U['level_label'] . ': ' . $T['levels'][$lv]['label']); ?> ·
                <?php echo e(number_format($sc['km'], 1, ',', '') . ' km ' . $U['summary_km']); ?> ·
                <?php echo e($U['summary_home'] . ' ' . itin_hhmm($sc['home'])); ?>
              </p>

              <p class="itin-go"><span class="itin-time"><?php echo e(itin_hhmm($sc['start'])); ?></span> <strong><?php echo e($U['from_home']); ?>.</strong> <?php echo e((string)$L['go']); ?></p>

              <?php if (!$sc['items']): ?>
                <p class="itin-empty"><?php echo e($U['empty']); ?></p>
              <?php endif; ?>
              <ol class="itin-list">
                <?php foreach ($sc['items'] as $k => $it):
                  $pid = $it['place'];
                  $P = $places[$pid];
                  $sp = itin_stop_params($d, $lv, $pid);
                  $note = (string)($L['notes'][$pid] ?? ($T['days'][$d]['levels'][$sp['level']]['notes'][$pid] ?? ''));
                  $g = !empty($P['guide']) ? ($T['guides'][$P['guide']] ?? null) : null;
                  if (!empty($P['book'])) {
                    $bk = $P['guide'] ?? $pid;
                    if (!isset($bookSeen[$bk])) { $bookSeen[$bk] = 1; $bookList[] = (string)$T['places'][$pid]; }
                  }
                ?>
                  <li class="itin-stop<?php echo !empty($P['meal']) ? ' is-meal' : ''; ?>" data-stop-id="<?php echo e($pid); ?>">
                    <?php if ($it['transit_min'] > 0): ?>
                      <div class="itin-leg itin-leg-transit"><?php echo e(str_replace('{min}', (string)$it['transit_min'], $U['transit'])); ?></div>
                    <?php elseif ($it['walk_min'] > 0): ?>
                      <div class="itin-leg"><?php echo e(str_replace('{min}', (string)$it['walk_min'], $U['walk']) . ' · ' . number_format($it['walk_km'], 1, ',', '') . ' km'); ?></div>
                    <?php endif; ?>
                    <div class="itin-stop-card">
                      <div class="itin-stop-head">
                        <span class="itin-num" aria-hidden="true"><?php echo $k + 1; ?></span>
                        <span class="itin-time"><?php echo e(itin_hhmm($it['arrive'])); ?></span>
                        <span class="itin-stop-name"><?php echo e((string)$T['places'][$pid]); ?></span>
                      </div>
                      <div class="itin-stop-meta">
                        <span><?php echo e($it['dur'] < 60 ? str_replace('{min}', (string)$it['dur'], $U['minutes']) : preg_replace('/ 0 min$/', '', str_replace(['{h}', '{min}'], [(string)intdiv($it['dur'], 60), (string)($it['dur'] % 60)], $U['hours']))); ?></span>
                        <?php if (!empty($P['book'])): ?><span class="itin-badge"><?php echo e($U['book_badge']); ?></span><?php endif; ?>
                        <?php if (!empty($P['meal'])): ?><span class="itin-badge itin-badge-meal"><?php echo e($U['meal_badge']); ?></span><?php endif; ?>
                      </div>
                      <?php if ($note !== ''): ?><p class="itin-note"><?php echo e($note); ?></p><?php endif; ?>
                      <?php if ($g): ?>
                        <details class="itin-saved-guide">
                          <summary><?php echo e($U['guide_open'] . ': ' . $g['name']); ?></summary>
                          <p><?php echo e((string)$g['brief']); ?></p>
                          <p><strong><?php echo e($U['guide_see']); ?>:</strong></p>
                          <ul><?php foreach ((array)$g['see'] as $s): ?><li><?php echo e((string)$s); ?></li><?php endforeach; ?></ul>
                          <p><strong><?php echo e($U['guide_ticket']); ?>.</strong> <?php echo e((string)$g['ticket']); ?></p>
                          <p><strong><?php echo e($U['guide_time']); ?>.</strong> <?php echo e((string)$g['time']); ?></p>
                          <?php if (!empty($g['tip'])): ?><p class="itin-guide-tip"><strong><?php echo e($U['guide_tip']); ?>.</strong> <?php echo e((string)$g['tip']); ?></p><?php endif; ?>
                        </details>
                      <?php endif; ?>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ol>
              <?php if ($sc['last_walk_min'] > 0): ?>
                <div class="itin-leg"><?php echo e(str_replace('{min}', (string)$sc['last_walk_min'], $U['walk'])); ?></div>
              <?php endif; ?>
              <p class="itin-back"><span class="itin-time"><?php echo e(itin_hhmm($sc['home'])); ?></span> <strong><?php echo e($U['back_home']); ?>.</strong> <?php echo e((string)$L['back']); ?></p>
              <p class="itin-evening"><strong><?php echo e($U['evening']); ?>:</strong> <?php echo e((string)$L['evening']); ?></p>

              <?php if ($stopIds): ?>
                <div class="itin-saved-links">
                  <?php
                    $first = $stopIds[0];
                    $transitUrl = 'https://www.google.com/maps/dir/?api=1&origin=' . rawurlencode($homeAddr) . '&destination=' . rawurlencode($gmapsLL($first)) . '&travelmode=transit';
                  ?>
                  <a class="btn" href="<?php echo e($transitUrl); ?>" target="_blank" rel="noopener"><?php echo e($U['maps_home']); ?></a>
                  <?php if (count($stopIds) > 1):
                    $walkIds = array_slice($stopIds, 0, 10);
                    $wp = array_map($gmapsLL, array_slice($walkIds, 1, -1));
                    $walkUrl = 'https://www.google.com/maps/dir/?api=1&origin=' . rawurlencode($gmapsLL($walkIds[0])) . '&destination=' . rawurlencode($gmapsLL($walkIds[count($walkIds) - 1])) . ($wp ? '&waypoints=' . rawurlencode(implode('|', $wp)) : '') . '&travelmode=walking';
                  ?>
                    <a class="btn" href="<?php echo e($walkUrl); ?>" target="_blank" rel="noopener"><?php echo e($U['maps_day']); ?></a>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>

          <?php if ($bookList): ?>
            <div class="itin-book"><strong><?php echo e($U['book_list']); ?>:</strong> <?php echo e(implode(', ', $bookList)); ?>.</div>
          <?php endif; ?>
          <p class="small muted" style="margin-top:16px;"><?php echo e($U['times_note']); ?></p>
        </div>
      </div>
    </div>
  </section>

  <?php
    $payload = itin_js_payload($n, current_lang());
    $payload['texts'] = [
      'ui' => $U,
      'levels' => $T['levels'],
      'days' => array_intersect_key($T['days'], array_flip(range(1, $n))),
      'stations' => $T['stations'],
      'guides' => [],
    ];
    $payload['lang'] = current_lang();
    $payload['plan'] = ['n' => $n, 'days' => $plan['days']];
  ?>
  <script type="application/json" id="itinData" nonce="<?php echo e(csp_nonce()); ?>"><?php echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>

  <section class="section-pad section-muted itin-no-print">
    <div class="container">
      <aside class="card article-cta" style="max-width:820px; margin:0 auto;">
        <div class="article-cta-title"><?php echo e($U['cta_title']); ?></div>
        <p><?php echo e($U['cta_text']); ?></p>
        <div class="actions">
          <a class="btn primary" href="<?php echo e(i18n_url('booking')); ?>"><?php echo e($U['cta_book']); ?></a>
          <a class="btn" href="<?php echo e(i18n_url('apartment')); ?>"><?php echo e($U['cta_apartment']); ?></a>
        </div>
      </aside>
    </div>
  </section>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
