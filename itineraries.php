<?php
// Rome itineraries (Oct 2026, patch 10).
// /itinerari                 → hub: choose 3, 4 or 5 days, or reopen a saved itinerary with its code
// /itinerari/roma-N-giorni   → builder: map, day tabs, three levels, selectable stops, save with a code
// Data in lib/itineraries.php, texts in i18n/itineraries/{lang}.php.
require_once __DIR__ . '/lib/lib.php';
require_once __DIR__ . '/lib/i18n.php';

i18n_init();
$itRoute = i18n_current_route();
if (!in_array($itRoute, ['itineraries', 'itin_3', 'itin_4', 'itin_5'], true)) $itRoute = 'itineraries';

// Language without texts → first available language; wrong slug → right one
$itLang = i18n_route_lang($itRoute, current_lang());
$itWanted = i18n_url($itRoute, $itLang);
$itAsked = '/' . trim(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '', '/');
if ($itLang !== current_lang() || rtrim($itAsked, '/') !== rtrim($itWanted, '/')) {
  parse_str((string)($_SERVER['QUERY_STRING'] ?? ''), $q);
  unset($q['lang'], $q['page']);
  header('Location: ' . $itWanted . ($q ? ('?' . http_build_query($q)) : ''), true, 301);
  exit;
}

$T = itin_texts();
$U = $T['ui'];
$nDays = (int)(itin_routes()[$itRoute]['days'] ?? 0);

// "Edit this itinerary": ?codice=XXXX-XXXX preloads a saved plan (only if it has this many days)
$itPlan = null;
if ($nDays > 0 && isset($_GET['codice'])) {
  $p = itin_load((string)$_GET['codice']);
  if ($p && (int)$p['n'] === $nDays) $itPlan = $p;
}

include __DIR__ . '/partials/header.php';

$pg = $T['pages'][$itRoute];
$pageHero = [
  'title' => (string)$pg['title'],
  'lead'  => (string)$pg['lead'],
  'image' => '/assets/images/gallery/giardino_1.jpg',
  'crumb' => $nDays > 0 ? (string)$pg['title'] : (string)$U['crumb'],
  'crumbs' => $nDays > 0 ? [['label' => (string)$U['crumb'], 'route' => 'itineraries']] : [],
];
include __DIR__ . '/partials/page-hero.php';

$days = itin_days();
$places = itin_places();
$levels = $T['levels'];

// Structured data: one TouristTrip per builder page (recommended medium level), ItemList on the hub
if ($nDays > 0) {
  $trip = [
    '@context' => 'https://schema.org',
    '@type' => 'TouristTrip',
    'name' => (string)$pg['title'],
    'description' => (string)$pg['meta_desc'],
    'inLanguage' => html_lang(current_lang()),
    'url' => abs_url_for_route($itRoute),
    'touristType' => ['Cultural tourism'],
    'provider' => ['@type' => 'Organization', 'name' => $siteName, 'url' => abs_url_for_route('home')],
    'itinerary' => ['@type' => 'ItemList', 'itemListElement' => []],
  ];
  $pos = 0;
  for ($d = 1; $d <= $nDays; $d++) {
    foreach (itin_level_default($d, 'medium') as $pid) {
      if (!empty($places[$pid]['meal'])) continue;
      $trip['itinerary']['itemListElement'][] = [
        '@type' => 'ListItem', 'position' => ++$pos,
        'item' => [
          '@type' => 'TouristAttraction',
          'name' => (string)$T['places'][$pid],
          'geo' => ['@type' => 'GeoCoordinates', 'latitude' => $places[$pid]['lat'], 'longitude' => $places[$pid]['lng']],
        ],
      ];
    }
  }
  echo '<script type="application/ld+json">' . json_encode($trip, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
} else {
  $hubLd = ['@context' => 'https://schema.org', '@type' => 'ItemList', 'itemListElement' => []];
  foreach ([3, 4, 5] as $i => $n) {
    $r = 'itin_' . $n;
    $hubLd['itemListElement'][] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => (string)$T['pages'][$r]['title'], 'url' => abs_url_for_route($r)];
  }
  echo '<script type="application/ld+json">' . json_encode($hubLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
}

$codeError = isset($_GET['codice_errato']);
?>

<main class="site-main">
<?php if ($nDays === 0): ?>
  <section class="section-pad section-muted">
    <div class="container">
      <div class="itin-hub">
        <h2 class="section-heading"><?php echo e($U['choose_days']); ?></h2>
        <div class="guide-cards itin-hub-cards">
          <?php foreach ([3, 4, 5] as $n): $r = 'itin_' . $n; ?>
            <a class="guide-card itin-hub-card" href="<?php echo e(i18n_url($r)); ?>">
              <span class="itin-hub-num"><?php echo (int)$n; ?></span>
              <span class="guide-card-title"><?php echo e((string)$T['pages'][$r]['title']); ?></span>
              <span class="guide-card-text"><?php echo e((string)$T['hub_cards'][$n]); ?></span>
            </a>
          <?php endforeach; ?>
        </div>

        <?php include __DIR__ . '/partials/itinerary-retrieve.php'; ?>
      </div>
    </div>
  </section>
<?php else: ?>
  <section class="itin-tool-wrap">
    <div class="container">
      <noscript><p class="notice itin-nojs"><?php echo e($U['nojs']); ?></p></noscript>
      <div class="itin-tool" id="itinTool" data-mode="build">
        <div class="itin-map-col">
          <div class="itin-map" id="itinMap" role="region" aria-label="<?php echo e($U['map_label']); ?>"></div>
          <div class="itin-summary" id="itinSummary" aria-live="polite"></div>
        </div>
        <div class="itin-ctrl-col">
          <div class="itin-days" role="tablist" aria-label="<?php echo e($U['days_label']); ?>">
            <?php for ($d = 1; $d <= $nDays; $d++): ?>
              <button class="itin-day-btn" type="button" role="tab" data-day="<?php echo $d; ?>" aria-selected="<?php echo $d === 1 ? 'true' : 'false'; ?>">
                <span class="itin-day-n"><?php echo e(str_replace('{n}', (string)$d, $U['day'])); ?></span>
                <span class="itin-day-t"><?php echo e((string)$T['days'][$d]['title']); ?></span>
              </button>
            <?php endfor; ?>
          </div>

          <?php // Server-rendered recommended plan (medium): crawlable text and fallback without JavaScript ?>
          <div id="itinPanels">
            <?php for ($d = 1; $d <= $nDays; $d++): $L = $T['days'][$d]['levels']['medium']; ?>
              <div class="itin-panel" data-day-panel="<?php echo $d; ?>"<?php echo $d > 1 ? ' hidden' : ''; ?>>
                <h2 class="itin-day-title"><?php echo e(str_replace('{n}', (string)$d, $U['day']) . ' · ' . $T['days'][$d]['title']); ?></h2>
                <p class="itin-day-intro"><?php echo e((string)$T['days'][$d]['intro']); ?></p>
                <div class="itin-levels" role="radiogroup" aria-label="<?php echo e($U['level_label']); ?>">
                  <?php foreach (VIA_ITIN_LEVELS as $lv): ?>
                    <button class="itin-level" type="button" role="radio" data-level="<?php echo e($lv); ?>" aria-checked="<?php echo $lv === 'medium' ? 'true' : 'false'; ?>">
                      <span class="itin-level-name"><?php echo e($levels[$lv]['label']); ?></span>
                      <span class="itin-level-desc"><?php echo e($levels[$lv]['desc']); ?></span>
                    </button>
                  <?php endforeach; ?>
                </div>
                <div class="itin-stops-box" data-stops>
                  <p class="itin-go"><strong><?php echo e($U['how_to_go']); ?>:</strong> <?php echo e((string)$L['go']); ?></p>
                  <ol class="itin-ssr-list">
                    <?php foreach ($days[$d]['levels']['medium']['stops'] as $s): ?>
                      <li><strong><?php echo e((string)$T['places'][$s[0]]); ?></strong><?php $nt = (string)($L['notes'][$s[0]] ?? ''); echo $nt !== '' ? ' — ' . e($nt) : ''; ?></li>
                    <?php endforeach; ?>
                  </ol>
                  <p class="itin-back"><strong><?php echo e($U['how_to_back']); ?>:</strong> <?php echo e((string)$L['back']); ?></p>
                </div>
              </div>
            <?php endfor; ?>
          </div>

          <div class="itin-save card" id="itinSave" hidden>
            <h2 class="itin-save-title"><?php echo e($U['save_title']); ?></h2>
            <p><?php echo e($U['save_text']); ?></p>
            <button class="btn primary" type="button" data-save><?php echo e($U['save_btn']); ?></button>
            <div class="itin-save-result" data-save-result aria-live="polite"></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <dialog class="itin-guide" id="itinGuide" aria-labelledby="itinGuideTitle">
    <div class="itin-guide-inner">
      <button class="itin-guide-close" type="button" data-guide-close aria-label="<?php echo e($U['guide_close']); ?>">×</button>
      <div data-guide-body></div>
    </div>
  </dialog>

  <?php
    $payload = itin_js_payload($nDays, current_lang());
    $payload['texts'] = [
      'ui' => $U,
      'levels' => $T['levels'],
      'days' => array_intersect_key($T['days'], array_flip(range(1, $nDays))),
      'stations' => $T['stations'],
      'guides' => $T['guides'],
    ];
    $payload['lang'] = current_lang();
    $payload['page'] = $itRoute;
    $payload['saveUrl'] = '/api/itinerary_save.php';
    if ($itPlan) $payload['plan'] = ['n' => $itPlan['n'], 'days' => $itPlan['days']];
  ?>
  <script type="application/json" id="itinData" nonce="<?php echo e(csp_nonce()); ?>"><?php echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>

  <section class="section-pad section-muted">
    <div class="container">
      <div class="itin-other-lengths">
        <?php foreach ([3, 4, 5] as $n): if ($n === $nDays) continue; $r = 'itin_' . $n; ?>
          <a class="btn" href="<?php echo e(i18n_url($r)); ?>"><?php echo e(str_replace('{n}', (string)$n, $U['days_n'])); ?></a>
        <?php endforeach; ?>
      </div>
      <?php include __DIR__ . '/partials/itinerary-retrieve.php'; ?>
    </div>
  </section>
<?php endif; ?>

  <section class="section-pad">
    <div class="container">
      <article class="card guide">
        <h2><?php echo e((string)$T['how']['title']); ?></h2>
        <?php foreach ($T['how']['p'] as $p): ?><p><?php echo e((string)$p); ?></p><?php endforeach; ?>
        <ul class="article-list">
          <?php foreach ($T['how']['list'] as $li): ?><li><?php echo $li; /* trusted static translation HTML */ ?></li><?php endforeach; ?>
        </ul>
        <h2><?php echo e((string)$T['before']['title']); ?></h2>
        <ul class="article-list">
          <?php foreach ($T['before']['list'] as $li): ?><li><?php echo $li; /* trusted static translation HTML */ ?></li><?php endforeach; ?>
        </ul>
        <p class="small muted"><?php echo e($U['times_note']); ?></p>

        <aside class="article-cta">
          <div class="article-cta-title"><?php echo e($U['cta_title']); ?></div>
          <p><?php echo e($U['cta_text']); ?></p>
          <div class="actions">
            <a class="btn primary" href="<?php echo e(i18n_url('booking')); ?>"><?php echo e($U['cta_book']); ?></a>
            <a class="btn" href="<?php echo e(i18n_url('apartment')); ?>"><?php echo e($U['cta_apartment']); ?></a>
          </div>
        </aside>
      </article>
      <div style="max-width:820px; margin:28px auto 0;">
        <?php include __DIR__ . '/partials/price-match.php'; ?>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
