<?php
require_once __DIR__ . '/lib/lib.php';
include __DIR__ . '/partials/header.php';

$pageHero = [
  'kicker' => t('rules.hero_pill'),
  'title' => t('rules.hero_title'),
  'lead'  => t('rules.hero_lead'),
  'image' => '/assets/images/gallery/living_wide.jpg',
  'actions' => [
    ['href' => i18n_url('booking'), 'label' => t('rules.cta_book'), 'class' => 'btn primary'],
    ['href' => i18n_url('contact'), 'label' => t('rules.cta_contact'), 'class' => 'btn ghost'],
  ],
];
include __DIR__ . '/partials/page-hero.php';
?>

<main class="site-main">
  <section class="section-pad section-muted">
    <div class="container">
      <div style="display:grid; grid-template-columns:1fr; gap:14px; max-width:820px; margin:0 auto;">
        <div class="card" style="padding:18px;">
          <h3 style="margin:0 0 10px;"><?php echo e(t('rules.card1_title')); ?></h3>
          <ul class="list">
            <li><?php echo e(t('rules.card1_li1')); ?></li>
            <li><?php echo e(t('rules.card1_li2')); ?></li>
          </ul>
        </div>

        <div class="card" style="padding:18px;">
          <h3 style="margin:0 0 10px;"><?php echo e(t('rules.card2_title')); ?></h3>
          <ul class="list">
            <li><?php echo e(t('rules.card2_li1')); ?></li>
            <li><?php echo e(t('rules.card2_li2')); ?></li>
            <li><?php echo e(t('rules.card2_li3')); ?></li>
          </ul>
        </div>

        <div class="card" style="padding:18px;">
          <h3 style="margin:0 0 10px;"><?php echo e(t('rules.card3_title')); ?></h3>
          <ul class="list">
            <li><?php echo t('rules.card3_li1'); ?></li>
            <li><?php echo t('rules.card3_li2'); ?></li>
          </ul>
        </div>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
