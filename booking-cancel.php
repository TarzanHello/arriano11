<?php
require_once __DIR__ . '/lib/lib.php';
require_once __DIR__ . '/lib/stripe.php';

$store = load_store();

$sessionId = isset($_GET['session_id']) ? (string)$_GET['session_id'] : '';
$sessionId = normalize_text($sessionId, 255, false);
if ($sessionId !== '' && !preg_match('/^cs_[A-Za-z0-9_]+$/', $sessionId)) {
  $sessionId = '';
}

// Release the temporary hold (under the store lock) and close the Stripe payment page.
if ($sessionId !== '') {
  [$rlOk, $rlRetry] = rate_limit_hit('stripe_cancel', client_ip(), 30, 60 * 60);
  if ($rlOk) {
    $secretKey = stripe_secret_key($store);

    // FIX: expire the Checkout Session on Stripe. Before, the hold was released but the
    // payment page stayed valid: going "back" in the browser the guest could still pay
    // for dates that in the meantime might have been sold to someone else.
    $bookingId = '';
    if ($secretKey !== '') {
      $exp = stripe_expire_checkout_session($secretKey, $sessionId);
      $sess = (!empty($exp['ok']) && is_array($exp['data'] ?? null)) ? $exp['data'] : null;
      if (!$sess) {
        $r = stripe_retrieve_checkout_session($secretKey, $sessionId);
        $sess = (!empty($r['ok']) && is_array($r['data'] ?? null)) ? $r['data'] : null;
      }
      if ($sess) {
        $meta = $sess['metadata'] ?? [];
        $bookingId = (string)($meta['booking_id'] ?? ($sess['client_reference_id'] ?? ''));
        // If it was paid after all, do not release anything.
        if ((string)($sess['payment_status'] ?? '') === 'paid') {
          $sessionId = '';
        }
      }
    }

    if ($sessionId !== '') {
      store_mutate(function(&$s) use ($sessionId, $bookingId) {
        $s['bookings'] = array_values(array_filter(($s['bookings'] ?? []), function($b) use ($sessionId, $bookingId) {
          $match = ((string)($b['stripe_session_id'] ?? '') === $sessionId)
            || ($bookingId !== '' && (string)($b['id'] ?? '') === $bookingId);
          if (!$match) return true;
          // Remove only temporary holds
          return (string)($b['status'] ?? '') !== 'payment_pending';
        }));
      });
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
