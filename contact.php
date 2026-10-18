<?php
require_once __DIR__ . '/lib/lib.php';
$store = load_store();
$phone = trim((string)($store['settings']['phone'] ?? ''));
$email = trim((string)($store['settings']['email'] ?? ''));
if ($email === '') {
  $email = trim((string)($store['settings']['notify_email'] ?? ''));
}
if ($email === '') $email = 'viaarriano11@gmail.com';

include __DIR__ . '/partials/header.php';

$pageHero = [
  'title' => t('contact.hero.title'),
  'lead' => t('contact.hero.lead'),
  'image' => '/assets/images/gallery/camera_da_letto_wide.jpg',
  'actions' => [
    ['href' => i18n_url('booking'), 'label' => t('contact.hero.cta_availability'), 'class' => 'btn'],
    ['href' => i18n_url('apartment'), 'label' => t('contact.hero.cta_apartment'), 'class' => 'btn secondary'],
  ]
];
include __DIR__ . '/partials/page-hero.php';
?>


<main class="site-main">
<section class="section-pad section-muted">
  <div class="container">
    <div class="panel-grid">
      <div class="card panel">
        <h2 style="margin:0 0 10px;"><?php echo e(t('contact.phone_title')); ?></h2>
        <?php if ($phone): ?>
          <p style="margin:0;"><strong><a href="tel:<?php echo e(str_replace(' ', '', $phone)); ?>" style="color:inherit; text-decoration:none; border-bottom:1px solid var(--border);"><?php echo e($phone); ?></a></strong></p>
          <p class="muted" style="margin:8px 0 0;"><?php echo e(t('contact.phone_hint')); ?></p>
        <?php else: ?>
          <p class="muted" style="margin:0;"><?php echo e(t('contact.phone_missing')); ?></p>
        <?php endif; ?>
      </div>

      <div class="card panel panel-highlight">
        <h2 style="margin:0 0 10px;"><?php echo e(t('contact.email_title')); ?></h2>
        <p style="margin:0;"><strong><a href="mailto:<?php echo e($email); ?>" style="color:inherit; text-decoration:none; border-bottom:1px solid rgba(255,255,255,.35);"><?php echo e($email); ?></a></strong></p>
        <p class="muted" style="margin:8px 0 0;"><?php echo e(t('contact.email_hint')); ?></p>
      </div>
    </div>

    <div class="section" style="margin-top:18px;">
      <div class="notice">
        <?php echo t('contact.booking_notice'); ?>
      </div>
      <div class="actions" style="margin-top:12px;">
        <a class="btn" href="<?php echo e(i18n_url('booking')); ?>"><?php echo e(t('contact.cta_calendar')); ?></a>
        <a class="btn secondary" href="<?php echo e(i18n_url('tips')); ?>"><?php echo e(t('contact.cta_tips')); ?></a>
      </div>
    </div>
  </div>
</section>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
