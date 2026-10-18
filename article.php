<?php
// Guide article (Oct 2026): /guide/{slug} and /{lang}/guide/{slug}.
// Texts: /i18n/articles/{dir}/{lang}.php — registry in lib/articles.php.
require_once __DIR__ . '/lib/lib.php';
require_once __DIR__ . '/lib/i18n.php';

i18n_init();
$artRoute = article_route_for_slug((string)($_GET['slug'] ?? ''));
if ($artRoute === '') {
  header('Location: ' . i18n_url('guides', i18n_route_lang('guides', current_lang())), true, 301);
  exit;
}
// Language without this article → English version; wrong slug for the language → right one
$artLang = i18n_route_lang($artRoute, current_lang());
$artWanted = i18n_url($artRoute, $artLang);
$artAsked = '/' . trim(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '', '/');
if ($artLang !== current_lang() || rtrim($artAsked, '/') !== rtrim($artWanted, '/')) {
  $qs = (string)($_SERVER['QUERY_STRING'] ?? '');
  // keep tracking parameters (utm_…), drop the internal ones added by .htaccess
  parse_str($qs, $q);
  unset($q['lang'], $q['slug']);
  header('Location: ' . $artWanted . ($q ? ('?' . http_build_query($q)) : ''), true, 301);
  exit;
}

$art = article_content($artRoute, current_lang());
$artMeta = via_articles()[$artRoute];

include __DIR__ . '/partials/header.php';

// {url:route} placeholders inside the article HTML
$artUrl = function ($html) {
  return preg_replace_callback('/\{url:([a-z_]+)\}/', function ($m) {
    $r = $m[1];
    return e(i18n_url($r, i18n_route_lang($r, current_lang())));
  }, (string)$html);
};

$pageHero = [
  'title' => (string)($art['title'] ?? ''),
  'lead'  => (string)($art['lead'] ?? ''),
  'image' => '/assets/images/gallery/' . $artMeta['image'] . '.jpg',
  'crumb' => (string)($art['crumb'] ?? ($art['title'] ?? '')),
  'crumbs' => [['label' => guides_ui('crumb'), 'route' => 'guides']],
  'actions' => [
    ['href' => i18n_url('booking'), 'label' => guides_ui('cta_book'), 'class' => 'btn primary'],
  ],
];
include __DIR__ . '/partials/page-hero.php';

// Article structured data
$artModified = article_mtime($artRoute);
$artLd = [
  '@context' => 'https://schema.org',
  '@type' => 'Article',
  'headline' => (string)($art['title'] ?? ''),
  'description' => (string)($art['meta_desc'] ?? ''),
  'inLanguage' => html_lang(current_lang()),
  'image' => [rtrim(base_url(), '/') . '/assets/images/gallery/' . $artMeta['image'] . '.jpg'],
  'datePublished' => $artMeta['published'],
  'dateModified' => $artModified > 0 ? date('Y-m-d', max($artModified, strtotime($artMeta['published']))) : $artMeta['published'],
  'mainEntityOfPage' => abs_url_for_route($artRoute),
  'author' => ['@type' => 'Organization', 'name' => $siteName, 'url' => abs_url_for_route('home')],
  'publisher' => ['@type' => 'Organization', 'name' => $siteName, 'url' => abs_url_for_route('home')],
];
echo '<script type="application/ld+json">' . json_encode($artLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";

$artSummary = is_array($art['summary'] ?? null) ? $art['summary'] : [];
$artSections = is_array($art['sections'] ?? null) ? $art['sections'] : [];
?>

<main class="site-main">
  <section class="section-pad section-muted">
    <div class="container">
      <article class="card guide">
        <?php if ($artSummary): ?>
          <div class="article-brief">
            <div class="article-brief-title"><?php echo e(guides_ui('in_brief')); ?></div>
            <ul>
              <?php foreach ($artSummary as $s): ?>
                <li><?php echo $artUrl($s); /* trusted static translation HTML */ ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php foreach ($artSections as $sec): ?>
          <?php if (!is_array($sec)) continue; ?>
          <h2><?php echo e((string)($sec['h'] ?? '')); ?></h2>
          <?php foreach ((array)($sec['p'] ?? []) as $para): ?>
            <p><?php echo $artUrl($para); /* trusted static translation HTML */ ?></p>
          <?php endforeach; ?>
          <?php if (!empty($sec['list']) && is_array($sec['list'])): ?>
            <ul class="article-list">
              <?php foreach ($sec['list'] as $li): ?>
                <li><?php echo $artUrl($li); /* trusted static translation HTML */ ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <?php foreach ((array)($sec['p2'] ?? []) as $para): ?>
            <p><?php echo $artUrl($para); /* trusted static translation HTML */ ?></p>
          <?php endforeach; ?>
        <?php endforeach; ?>

        <p class="small muted article-updated">
          <?php echo e(guides_ui('updated')); ?>
          <time datetime="<?php echo e($artLd['dateModified']); ?>"><?php echo e(date('d/m/Y', strtotime($artLd['dateModified']))); ?></time>
        </p>

        <aside class="article-cta">
          <div class="article-cta-title"><?php echo e(guides_ui('cta_title')); ?></div>
          <p><?php echo e(guides_ui('cta_text')); ?></p>
          <div class="actions">
            <a class="btn primary" href="<?php echo e(i18n_url('booking')); ?>"><?php echo e(guides_ui('cta_book')); ?></a>
            <a class="btn" href="<?php echo e(i18n_url('apartment')); ?>"><?php echo e(guides_ui('cta_apartment')); ?></a>
          </div>
        </aside>
      </article>

      <nav class="guide-related" aria-label="<?php echo e(guides_ui('related')); ?>">
        <h2 class="section-heading"><?php echo e(guides_ui('related')); ?></h2>
        <div class="guide-cards">
          <?php foreach (via_articles() as $r => $a): ?>
            <?php if ($r === $artRoute) continue; $c = article_content($r, current_lang()); ?>
            <a class="guide-card" href="<?php echo e(i18n_url($r)); ?>">
              <span class="guide-card-title"><?php echo e((string)($c['crumb'] ?? $c['title'] ?? '')); ?></span>
              <span class="guide-card-text"><?php echo e((string)($c['card'] ?? '')); ?></span>
            </a>
          <?php endforeach; ?>
          <a class="guide-card" href="<?php echo e(i18n_url('neighborhood')); ?>">
            <span class="guide-card-title"><?php echo e(guides_ui('neighborhood_title')); ?></span>
            <span class="guide-card-text"><?php echo e(guides_ui('neighborhood_text')); ?></span>
          </a>
        </div>
      </nav>

      <div style="max-width:820px; margin:28px auto 0;">
        <?php include __DIR__ . '/partials/price-match.php'; ?>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
