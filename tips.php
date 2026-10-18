<?php
require_once __DIR__ . '/lib/lib.php';
include __DIR__ . '/partials/header.php';

$pageHero = [
  'title' => t('tips.hero.title'),
  'lead' => t('tips.hero.lead'),
  'image' => '/assets/images/gallery/camera_da_letto_wide.jpg',
  'actions' => [
    ['href' => i18n_url('neighborhood'), 'label' => t('tips.hero.cta_neighborhood'), 'class' => 'btn secondary'],
    ['href' => i18n_url('booking'), 'label' => t('tips.hero.cta_book'), 'class' => 'btn'],
  ]
];
include __DIR__ . '/partials/page-hero.php';
?>


<main class="site-main">
<section class="section-pad section-muted">
  <div class="container">
    <div class="panel-grid">
      <div class="card panel">
        <h2 style="margin:0 0 10px;"><?php echo e(t('tips.eat_title')); ?></h2>
        <ul class="list">
          <li>
            <div class="title">Pizzeria da Simone Malatesta</div>
            <div class="meta"><?php echo t('tips.eat_meta_pizza'); ?> <a href="tel:+390664800220">+39 06 6480 0220</a></div>
            <a class="link" href="https://g.co/kgs/MgwrWnh" target="_blank" rel="noopener"><?php echo e(t('tips.open_maps')); ?></a>
          </li>
          <li>
            <div class="title">Da Domenico al Pigneto</div>
            <div class="meta"><?php echo t('tips.eat_meta_trattoria'); ?> <a href="tel:+3906295613">+39 06 295613</a></div>
            <a class="link" href="https://g.co/kgs/nCbSVq3" target="_blank" rel="noopener"><?php echo e(t('tips.open_maps')); ?></a>
          </li>
          <li>
            <div class="title">Va.Do al Pigneto</div>
            <div class="meta"><?php echo t('tips.eat_meta_restaurant'); ?> <a href="tel:+390645553582">+39 06 4555 3582</a></div>
            <a class="link" href="https://g.co/kgs/ra2Jnnu" target="_blank" rel="noopener"><?php echo e(t('tips.open_maps')); ?></a>
          </li>
          <li>
            <div class="title">Fattori – gelato artigianale</div>
            <div class="meta"><?php echo t('tips.eat_meta_gelato'); ?> <a href="tel:+390627858227">+39 06 2785 8227</a></div>
            <a class="link" href="https://g.co/kgs/9f3LXYf" target="_blank" rel="noopener"><?php echo e(t('tips.open_maps')); ?></a>
          </li>
        </ul>
      </div>

      <div class="card panel">
        <h2 style="margin:0 0 10px;"><?php echo e(t('tips.drink_title')); ?></h2>
        <ul class="list">
          <li>
            <div class="title">Makai Restaurant &amp; Tiki Bar</div>
            <div class="meta"><?php echo t('tips.drink_meta'); ?></div>
            <a class="link" href="https://share.google/iRl9FzwnYyE79o1tb" target="_blank" rel="noopener"><?php echo e(t('tips.open_google')); ?></a>
          </li>
          <li>
            <div class="title">Spirito Pigneto</div>
            <a class="link" href="https://share.google/2Sd4Cihl2nU3eMyce" target="_blank" rel="noopener"><?php echo e(t('tips.open_google')); ?></a>
          </li>
          <li>
            <div class="title">Buseto</div>
            <a class="link" href="https://share.google/IBkXRwP1dzQc4OaGE" target="_blank" rel="noopener"><?php echo e(t('tips.open_google')); ?></a>
          </li>
        </ul>

        <div class="notice" style="margin-top:14px;">
          <?php echo t('tips.good_stay'); ?>
        </div>
      </div>
    </div>

    <div class="card panel" style="margin-top:18px;">
      <h2 style="margin:0 0 10px;"><?php echo t('tips.grocery_title'); ?></h2>
      <ul class="list">
        <li>
          <div class="title">Il Castoro Supermercati – Erasmo Gattamelata</div>
          <div class="meta"><?php echo t('tips.grocery_meta_supermarket'); ?> <a href="tel:+3906273455">+39 06 273455</a></div>
          <a class="link" href="https://g.co/kgs/9atFd2A" target="_blank" rel="noopener"><?php echo e(t('tips.open_maps')); ?></a>
        </li>
        <li>
          <div class="title">Lago Bullicante</div>
          <div class="meta"><?php echo t('tips.grocery_meta_park'); ?></div>
          <a class="link" href="https://g.co/kgs/mNAchXz" target="_blank" rel="noopener"><?php echo e(t('tips.open_maps')); ?></a>
        </li>
      </ul>
    </div>
  </div>
</section>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
