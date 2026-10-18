<?php
require_once __DIR__ . '/lib/lib.php';
require_once __DIR__ . '/lib/stripe.php';

$store = load_store();

$sessionId = isset($_GET['session_id']) ? (string)$_GET['session_id'] : '';
$sessionId = normalize_text($sessionId, 255, false);
if ($sessionId !== '' && !preg_match('/^cs_[A-Za-z0-9_]+$/', $sessionId)) {
  $sessionId = '';
}

// Best effort: release the temporary hold without calling Stripe (avoids needless API calls).
if ($sessionId !== '') {
  $before = count($store['bookings'] ?? []);
  $store['bookings'] = array_values(array_filter(($store['bookings'] ?? []), function($b) use ($sessionId) {
    if ((string)($b['stripe_session_id'] ?? '') !== $sessionId) return true;
    // Remove only temporary holds
    return (string)($b['status'] ?? '') !== 'payment_pending';
  }));

  if (count($store['bookings'] ?? []) !== $before) {
    save_store($store);
  } else {
    // Legacy fallback: retrieve session metadata to find booking_id
    [$rlOk, $rlRetry] = rate_limit_hit('stripe_verify', client_ip(), 30, 60 * 60);
    if ($rlOk) {
      $secretKey = stripe_secret_key($store);
      if ($secretKey !== '') {
        $res = stripe_retrieve_checkout_session($secretKey, $sessionId);
        if (!empty($res['ok'])) {
          $session = $res['data'] ?? [];
          $meta = $session['metadata'] ?? [];
          $bookingId = (string)($meta['booking_id'] ?? ($session['client_reference_id'] ?? ''));

          if ($bookingId !== '') {
            $store = load_store();
            $store['bookings'] = array_values(array_filter(($store['bookings'] ?? []), function($b) use ($bookingId) {
              if ((string)($b['id'] ?? '') !== $bookingId) return true;
              // Remove only temporary holds
              return (string)($b['status'] ?? '') !== 'payment_pending';
            }));
            save_store($store);
          }
        }
      }
    }
  }
}

require __DIR__ . '/partials/header.php';

$pageHero = [
  'title' => t('booking_cancel.hero_title'),
  'lead' => t('booking_cancel.hero_lead'),
  'image' => '/assets/images/gallery/camera_da_letto_wide.jpg',
  'actions' => [
    ['href' => i18n_url('booking'), 'label' => t('booking_cancel.cta_retry'), 'class' => 'btn'],
    ['href' => i18n_url('contact'), 'label' => t('booking_cancel.cta_contact'), 'class' => 'btn secondary'],
  ]
];
include __DIR__ . '/partials/page-hero.php';
?>


<main class="site-main">
<section class="section-pad section-muted">
  <div class="container">
    <div class="card" style="padding:18px;">
      <div class="notice" style="margin:0;">
        <?php echo e(t('booking_cancel.notice')); ?>
      </div>

      <div class="actions" style="margin-top:14px;">
        <a class="btn" href="<?php echo e(i18n_url('booking')); ?>"><?php echo e(t('booking_cancel.cta_calendar')); ?></a>
        <a class="btn secondary" href="<?php echo e(i18n_url('home')); ?>"><?php echo e(t('booking_cancel.cta_home')); ?></a>
      </div>
    </div>
  </div>
</section>

</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
