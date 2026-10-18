<?php
// Inner page hero (redesign v2): breadcrumbs + title on an ink band over a photo.
// Usage (unchanged):
// $pageHero = ['title' => ..., 'lead' => ..., 'image' => ..., 'actions' => [ ['href'=>..,'label'=>..,'class'=>..] ]];

$phStore = load_store();
$phSite = (string)($phStore['settings']['site_name'] ?? 'Via Arriano 11');
$phTitle = (string)($pageHero['title'] ?? '');
$phLead = (string)($pageHero['lead'] ?? '');
$phImage = (string)($pageHero['image'] ?? '/assets/images/gallery/living_wide.jpg');
$phActions = $pageHero['actions'] ?? [];
if (!is_array($phActions)) $phActions = [];

// Prefer a lighter responsive variant for the background
$phBase = preg_replace('/\.jpg$/i', '', $phImage);
if (file_exists(__DIR__ . '/..' . $phBase . '_1200.jpg')) $phImage = $phBase . '_1200.jpg';

// Breadcrumb label for the current page
$phRoute = function_exists('i18n_current_route') ? i18n_current_route() : '';
$phLabels = [
  'apartment' => t('nav.apartment'),
  'neighborhood' => t('nav.neighborhood'),
  'tips' => t('nav.tips'),
  'rules' => t('nav.rules'),
  'contact' => t('nav.contact'),
  'booking' => t('nav.book'),
  'privacy' => t('footer.privacy'),
  'cookies' => t('footer.cookies'),
  'policlinico' => t('policlinico.nav'),
];
$phCrumb = $phLabels[$phRoute] ?? $phTitle;
$phIndexable = !in_array($phRoute, ['booking_success', 'booking_cancel', 'review'], true);

if ($phIndexable && $phRoute !== '' && $phRoute !== 'home') {
  $phLd = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
      ['@type' => 'ListItem', 'position' => 1, 'name' => $phSite, 'item' => abs_url_for_route('home')],
      ['@type' => 'ListItem', 'position' => 2, 'name' => $phCrumb, 'item' => abs_url_for_route($phRoute)],
    ],
  ];
  echo '<script type="application/ld+json">' . json_encode($phLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
}
?>
<section class="page-hero">
  <div class="page-hero-media" aria-hidden="true">
    <img src="<?php echo e($phImage); ?>" alt="" decoding="async" fetchpriority="high">
  </div>
  <div class="container page-hero-inner">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
      <ol>
        <li><a href="<?php echo e(i18n_url('home')); ?>"><?php echo e(t('nav.home')); ?></a></li>
        <li aria-current="page"><?php echo e($phCrumb); ?></li>
      </ol>
    </nav>
    <h1><?php echo e($phTitle); ?></h1>
    <?php if ($phLead !== ''): ?>
      <p class="lead"><?php echo e($phLead); ?></p>
    <?php endif; ?>
    <?php if (!empty($phActions)): ?>
      <div class="hero-actions">
        <?php foreach ($phActions as $a):
          if (!is_array($a)) continue;
          $label = (string)($a['label'] ?? '');
          if ($label === '') continue;
          $class = (string)($a['class'] ?? 'btn');
          // On the dark band, secondary buttons are outlined in white
          if (strpos($class, 'primary') === false) $class = 'btn ghost';
        ?>
          <a class="<?php echo e($class); ?>" href="<?php echo e((string)($a['href'] ?? '#')); ?>"<?php echo (strpos((string)($a['href'] ?? ''), 'https://') === 0) ? ' target="_blank" rel="noopener"' : ''; ?>><?php echo e($label); ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php // Inner pages open their own <main>; give it an anchor for the skip link ?>
<div id="main"></div>
