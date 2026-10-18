<?php
// Guides hub (Oct 2026): /guide and /{lang}/guide — lists the articles + the neighbourhood guide.
require_once __DIR__ . '/lib/lib.php';
require_once __DIR__ . '/lib/i18n.php';

i18n_init();
if (current_lang() !== i18n_route_lang('guides', current_lang())) {
  header('Location: ' . i18n_url('guides', 'en'), true, 301);
  exit;
}

include __DIR__ . '/partials/header.php';

$pageHero = [
  'title' => guides_ui('hub_title'),
  'lead'  => guides_ui('hub_lead'),
  'image' => '/assets/images/gallery/giardino_1.jpg',
  'actions' => [
    ['href' => i18n_url('booking'), 'label' => guides_ui('cta_book'), 'class' => 'btn primary'],
  ],
];
include __DIR__ . '/partials/page-hero.php';

$hubItems = [];
foreach (via_articles() as $r => $a) {
  $c = article_content($r, current_lang());
  $hubItems[] = [
    'url' => i18n_url($r),
    'title' => (string)($c['title'] ?? ''),
    'text' => (string)($c['card'] ?? ''),
  ];
}
$hubItems[] = ['url' => i18n_url('neighborhood'), 'title' => guides_ui('neighborhood_title'), 'text' => guides_ui('neighborhood_text')];
$hubItems[] = ['url' => i18n_url('tips'), 'title' => t('nav.tips'), 'text' => guides_ui('tips_text')];
if (in_array(current_lang(), i18n_route_languages('policlinico'), true)) {
  $hubItems[] = ['url' => i18n_url('policlinico'), 'title' => t('policlinico.nav'), 'text' => guides_ui('policlinico_text')];
}

// ItemList structured data
$hubLd = ['@context' => 'https://schema.org', '@type' => 'ItemList', 'itemListElement' => []];
foreach ($hubItems as $i => $it) {
  $hubLd['itemListElement'][] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $it['title'], 'url' => rtrim(base_url(), '/') . $it['url']];
}
echo '<script type="application/ld+json">' . json_encode($hubLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
?>

<main class="site-main">
  <section class="section-pad section-muted">
    <div class="container">
      <div class="guide-cards guide-cards-hub">
        <?php foreach ($hubItems as $it): ?>
          <a class="guide-card" href="<?php echo e($it['url']); ?>">
            <span class="guide-card-title"><?php echo e($it['title']); ?></span>
            <span class="guide-card-text"><?php echo e($it['text']); ?></span>
          </a>
        <?php endforeach; ?>
      </div>

      <aside class="card article-cta" style="max-width:820px; margin:32px auto 0;">
        <div class="article-cta-title"><?php echo e(guides_ui('cta_title')); ?></div>
        <p><?php echo e(guides_ui('cta_text')); ?></p>
        <div class="actions">
          <a class="btn primary" href="<?php echo e(i18n_url('booking')); ?>"><?php echo e(guides_ui('cta_book')); ?></a>
          <a class="btn" href="<?php echo e(i18n_url('apartment')); ?>"><?php echo e(guides_ui('cta_apartment')); ?></a>
        </div>
      </aside>
    </div>
  </section>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
