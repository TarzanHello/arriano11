<?php
require_once __DIR__ . '/lib/lib.php';
require_once __DIR__ . '/lib/stripe.php';

$store = load_store();

$sessionId = isset($_GET['session_id']) ? (string)$_GET['session_id'] : '';
$sessionId = normalize_text($sessionId, 255, false);
// Stripe Checkout Session IDs look like: cs_test_... / cs_live_...
if ($sessionId !== '' && !preg_match('/^cs_[A-Za-z0-9_]+$/', $sessionId)) {
  $sessionId = '';
}

$currency = (string)($store['settings']['currency'] ?? 'EUR');
$siteName = (string)($store['settings']['site_name'] ?? 'Via Arriano 11');

$confirmed = false;
$errorKey = '';
$errorVars = [];
$details = null;

// Fast path: if we already confirmed this session, avoid repeated Stripe API calls on refresh.
if ($sessionId !== '') {
  foreach (($store['bookings'] ?? []) as $bk) {
    if ((string)($bk['stripe_session_id'] ?? '') === $sessionId && (string)($bk['status'] ?? '') === 'booked') {
      $confirmed = true;
      $details = [
        'booking_id' => (string)($bk['id'] ?? ''),
        'start' => (string)($bk['start'] ?? ''),
        'end' => (string)($bk['end'] ?? ''),
        'amount_paid' => (float)($bk['amount_paid'] ?? 0),
        'currency' => (string)($bk['currency'] ?? $currency),
        'email' => (string)($bk['email'] ?? ''),
      ];
      break;
    }
  }
}

if (!$confirmed) {
  if ($sessionId === '') {
    $errorKey = 'booking_success.err_missing_session';
  } else {
    // Rate limit Stripe verification lookups (defence-in-depth against abuse)
    [$rlOk, $rlRetry] = rate_limit_hit('stripe_verify', client_ip(), 30, 60 * 60);
    if (!$rlOk) {
      $errorKey = 'booking_success.err_verify_failed';
    } else {
      $secretKey = stripe_secret_key($store);
      if ($secretKey === '') {
        $errorKey = 'booking_success.err_not_configured';
      } else {
        $res = stripe_retrieve_checkout_session($secretKey, $sessionId);
        if (empty($res['ok'])) {
          $errorKey = 'booking_success.err_verify_failed';
        } else {
          $session = $res['data'] ?? [];
          $paymentStatus = (string)($session['payment_status'] ?? '');
          if ($paymentStatus !== 'paid') {
            $errorKey = 'booking_success.err_not_paid';
          } else {
            // Confirm (or re-create) the booking under the store lock — shared with the webhook.
            $conf = stripe_confirm_paid_session($session);
            if (!empty($conf['ok']) && is_array($conf['booking'] ?? null)) {
              $bkc = $conf['booking'];
              $confirmed = true;
              $details = [
                'booking_id' => (string)($bkc['id'] ?? ''),
                'start' => (string)($bkc['start'] ?? ''),
                'end' => (string)($bkc['end'] ?? ''),
                'amount_paid' => (float)($bkc['amount_paid'] ?? 0),
                'currency' => (string)($bkc['currency'] ?? $currency),
                'email' => (string)($bkc['email'] ?? ($session['customer_details']['email'] ?? '')),
              ];
            } else {
              $errorKey = (string)($conf['error_key'] ?? 'booking_success.err_verify_failed');
              $errorVars = ['id' => $sessionId];
            }
          }
        }
      }
    }
  }
}

require __DIR__ . '/partials/header.php';

$pageHero = [
  'title' => $confirmed ? t('booking_success.hero_title_ok') : t('booking_success.hero_title_ko'),
  'lead' => $confirmed
    ? t('booking_success.hero_lead_ok', ['site' => $siteName])
    : t('booking_success.hero_lead_ko'),
  'image' => '/assets/images/gallery/living_wide.jpg',
  'actions' => [
    ['href' => i18n_url('home'), 'label' => t('booking_success.actions_home'), 'class' => 'btn secondary'],
    ['href' => i18n_url('contact'), 'label' => t('booking_success.actions_contact'), 'class' => 'btn'],
  ]
];
include __DIR__ . '/partials/page-hero.php';
?>


<main class="site-main">
<section class="section-pad section-muted">
  <div class="container">
    <div class="card" style="padding:18px;">
      <?php if ($confirmed): ?>
        <h2 style="margin:0 0 10px;"><?php echo e(t('booking_success.details_title')); ?></h2>

        <?php if (is_array($details)): ?>
          <div class="notice" style="margin-top:12px;">
            <div><strong><?php echo e(t('booking_success.details_checkin')); ?>:</strong> <?php echo e($details['start']); ?></div>
            <div><strong><?php echo e(t('booking_success.details_checkout')); ?>:</strong> <?php echo e($details['end']); ?></div>
            <?php if (!empty($details['email'])): ?>
              <div><strong><?php echo e(t('booking_success.details_email')); ?>:</strong> <?php echo e($details['email']); ?></div>
            <?php endif; ?>
            <?php if (!empty($details['amount_paid'])): ?>
              <div><strong><?php echo e(t('booking_success.details_paid')); ?>:</strong> <?php echo e(format_currency($details['amount_paid'], $details['currency'])); ?></div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <div class="actions" style="margin-top:14px;">
          <a class="btn" href="<?php echo e(i18n_url('home')); ?>"><?php echo e(t('booking_success.cta_back_home')); ?></a>
          <a class="btn secondary" href="<?php echo e(i18n_url('tips')); ?>"><?php echo e(t('booking_success.cta_tips')); ?></a>
        </div>

      <?php else: ?>
        <div class="notice danger" style="margin-top:12px;">
          <?php
            $msg = $errorKey ? t($errorKey, $errorVars) : t('booking_success.err_unknown');
            echo e($msg);
          ?>
        </div>
        <div class="actions" style="margin-top:14px;">
          <a class="btn" href="<?php echo e(i18n_url('booking')); ?>"><?php echo e(t('booking_success.cta_retry')); ?></a>
          <a class="btn secondary" href="<?php echo e(i18n_url('contact')); ?>"><?php echo e(t('booking_success.cta_contact')); ?></a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
