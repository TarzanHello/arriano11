<?php
require_once __DIR__ . '/lib/lib.php';
include __DIR__ . '/partials/header.php';

$pageHero = [
  'title' => t('neighborhood.hero.title'),
  'lead' => t('neighborhood.hero.lead'),
  'image' => '/assets/images/gallery/giardino_2.jpg',
  'actions' => [
    ['href' => i18n_url('tips'), 'label' => t('neighborhood.hero.cta_tips'), 'class' => 'btn'],
    ['href' => i18n_url('booking'), 'label' => t('neighborhood.hero.cta_book'), 'class' => 'btn secondary'],
  ]
];
include __DIR__ . '/partials/page-hero.php';
?>


<main class="site-main">
<section class="section-pad">
  <div class="container">
    <div class="panel-grid">
      <div class="card panel">
        <h2 style="margin:0 0 10px;"><?php echo e(t('neighborhood.panel1_title')); ?></h2>
        <p class="muted" style="margin:0; max-width:75ch;"><?php echo t('neighborhood.panel1_text'); ?></p>
      </div>

      <div class="card panel panel-highlight">
        <h2 style="margin:0 0 10px;"><?php echo e(t('neighborhood.panel2_title')); ?></h2>
        <p class="muted" style="margin:0; max-width:75ch;"><?php echo t('neighborhood.panel2_text'); ?></p>
      </div>
    </div>

    <div class="section" style="margin-top:18px;">
      <div class="tile-grid">
        <div class="tile">
          <div class="tile-icon">🎨</div>
          <div>
            <h3><?php echo t('neighborhood.tile1_title'); ?></h3>
            <p><?php echo t('neighborhood.tile1_text'); ?></p>
          </div>
        </div>
        <div class="tile">
          <div class="tile-icon">🚇</div>
          <div>
            <h3><?php echo e(t('neighborhood.tile2_title')); ?></h3>
            <p><?php echo e(t('neighborhood.tile2_text')); ?></p>
          </div>
        </div>
        <div class="tile">
          <div class="tile-icon">🌿</div>
          <div>
            <h3><?php echo e(t('neighborhood.tile3_title')); ?></h3>
            <p><?php echo e(t('neighborhood.tile3_text')); ?></p>
          </div>
        </div>
        <div class="tile">
          <div class="tile-icon">🍕</div>
          <div>
            <h3><?php echo t('neighborhood.tile4_title'); ?></h3>
            <p><?php echo t('neighborhood.tile4_text'); ?></p>
          </div>
        </div>
      </div>
    </div>

    <div class="section" style="margin-top:18px;">
      <div class="notice">
        <?php echo t('neighborhood.tip'); ?>
      </div>
      <div class="actions" style="margin-top:12px;">
        <a class="btn" href="<?php echo e(i18n_url('tips')); ?>"><?php echo e(t('neighborhood.hero.cta_tips')); ?></a>
        <a class="btn secondary" href="https://g.co/kgs/mNAchXz" target="_blank" rel="noopener"><?php echo e(t('neighborhood.cta_maps')); ?></a>
      </div>
    </div>
  </div>
</section>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
