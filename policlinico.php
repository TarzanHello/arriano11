<?php
// Landing page: accommodation near Policlinico Umberto I / Sapienza / San Lorenzo (Oct 2026).
// Exists in Italian and English only: other languages are redirected to the English version.
require_once __DIR__ . '/lib/lib.php';
require_once __DIR__ . '/lib/i18n.php';

i18n_init();
if (current_lang() !== i18n_route_lang('policlinico', current_lang())) {
  header('Location: ' . i18n_url('policlinico', 'en'), true, 301);
  exit;
}

include __DIR__ . '/partials/header.php';

$pcStore = load_store();
$pcPhone = preg_replace('/\D+/', '', (string)($pcStore['settings']['phone'] ?? ''));
if ($pcPhone !== '' && strpos($pcPhone, '39') !== 0) $pcPhone = '39' . $pcPhone;
$pcWa = $pcPhone !== '' ? ('https://wa.me/' . $pcPhone . '?text=' . rawurlencode(t('policlinico.whatsapp_text'))) : '';

$pageHero = [
  'title' => t('policlinico.hero_title'),
  'lead'  => t('policlinico.hero_lead'),
  'image' => '/assets/images/gallery/camera_da_letto_wide.jpg',
  'actions' => array_values(array_filter([
    ['href' => i18n_url('booking'), 'label' => t('policlinico.cta_book'), 'class' => 'btn primary'],
    $pcWa !== '' ? ['href' => $pcWa, 'label' => t('policlinico.cta_whatsapp'), 'class' => 'btn ghost'] : null,
  ])),
];
include __DIR__ . '/partials/page-hero.php';

$pcSections = i18n_get('policlinico.sections');
if (!is_array($pcSections)) $pcSections = [];
$pcNeighborhoodUrl = i18n_url('neighborhood');
?>

<main class="site-main">
  <section class="section-pad section-muted">
    <div class="container">
      <article class="card guide" style="padding:22px; margin:0 auto;">
        <?php foreach ($pcSections as $i => $sec): ?>
          <?php if (!is_array($sec)) continue; ?>
          <h2<?php echo $i === 0 ? ' style="margin-top:0;"' : ''; ?>><?php echo e((string)($sec['h'] ?? '')); ?></h2>
          <p><?php echo str_replace('{neighborhood_url}', e($pcNeighborhoodUrl), (string)($sec['html'] ?? '')); /* trusted static translation HTML */ ?></p>
        <?php endforeach; ?>

        <div class="actions" style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
          <a class="btn primary" href="<?php echo e(i18n_url('booking')); ?>"><?php echo e(t('policlinico.cta_book')); ?></a>
          <?php if ($pcWa !== ''): ?>
            <a class="btn" href="<?php echo e($pcWa); ?>" target="_blank" rel="noopener"><?php echo e(t('policlinico.cta_whatsapp')); ?></a>
          <?php endif; ?>
          <a class="btn" href="<?php echo e(i18n_url('apartment')); ?>"><?php echo e(t('nav.apartment')); ?></a>
        </div>
      </article>

      <div style="max-width:820px; margin:18px auto 0;">
        <?php $store = $pcStore; include __DIR__ . '/partials/price-match.php'; ?>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
