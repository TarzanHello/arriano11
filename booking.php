<?php
require_once __DIR__ . '/lib/lib.php';
require_once __DIR__ . '/lib/stripe.php';

$store = load_store();

// IMPORTANT: start the session (and generate CSRF) BEFORE any HTML is sent.
// On some hosting stacks, calling session_start() after output begins triggers
// "headers already sent" and the CSRF token won't persist, breaking bookings.
$csrf = csrf_token();

// Stripe enabled only when required + secret key present.
$requirePayment = !empty($store['settings']['stripe_require_payment']);
$secretKey = trim((string)($store['settings']['stripe_secret_key'] ?? ''));
$stripeEnabled = ($requirePayment && $secretKey !== '');

// Deposit percentage: accept both 0.30 and 30 formats.
$depositPct = (float)($store['settings']['stripe_deposit_percent'] ?? 0.30);
if ($depositPct > 1) $depositPct = $depositPct / 100;
if ($depositPct <= 0) $depositPct = 0.30;
if ($depositPct < 0.05) $depositPct = 0.05;
if ($depositPct > 1.0) $depositPct = 1.0;
$depositPctDisplay = (int)round($depositPct * 100);

include __DIR__ . '/partials/header.php';

$ctaText = $stripeEnabled ? t('booking.cta.pay') : t('booking.cta.request');
$depositLine = $stripeEnabled ? t('booking.availability_help_deposit', ['pct' => $depositPctDisplay]) : '';

$pageHero = [
  'title' => t('booking.hero.title'),
  'lead'  => t('booking.hero.lead'),
  'image' => '/assets/images/gallery/camera_da_letto_wide.jpg',
  'actions' => [
    ['href' => i18n_url('apartment'), 'label' => t('booking.hero.cta_apartment'), 'class' => 'btn ghost'],
    ['href' => i18n_url('contact'),  'label' => t('booking.hero.cta_contact'),  'class' => 'btn secondary'],
  ],
];
include __DIR__ . '/partials/page-hero.php';
?>

<main class="site-main">
  <section class="section-pad section-muted">
    <div class="container">
      <div
        id="bookingCalendar"
        class="card calendar-wrap"
        data-api-base="/api"
        data-csrf="<?php echo e($csrf); ?>"
      >
        <div class="calendar-header">
          <div>
            <h2 class="section-heading" style="margin:0 0 6px;"><?php echo e(t('booking.availability_title')); ?></h2>
            <p class="muted" style="margin:0; max-width:70ch;">
              <?php echo t('booking.availability_help_select'); ?><br>
              <?php echo $stripeEnabled ? t('booking.availability_help_pay') : t('booking.availability_help_request'); ?>
              <?php if ($depositLine): ?>
                <br><?php echo $depositLine; ?>
              <?php endif; ?>
            </p>
          </div>
        </div>

        <noscript>
          <div class="notice warn" style="margin-top:12px;"><?php echo e(t('ui.enable_js')); ?></div>
        </noscript>

        <div id="calMsg" class="notice" style="display:none; margin-top:12px;"></div>

        <div class="booking-grid">
          <div>
            <div class="cal-controls" aria-label="<?php echo e(t('booking.availability_title')); ?>">
              <div class="control">
                <label for="startDate"><?php echo e(t('booking.labels.checkin')); ?></label>
                <input type="date" id="startDate" name="start" min="<?php echo e(date('Y-m-d')); ?>">
              </div>
              <div class="control">
                <label for="endDate"><?php echo e(t('booking.labels.checkout')); ?></label>
                <input type="date" id="endDate" name="end" min="<?php echo e(date('Y-m-d')); ?>">
              </div>
              <div class="control">
                <label for="guests"><?php echo e(t('booking.labels.guests')); ?></label>
                <select id="guests" name="guests">
                  <option value="1">1</option>
                  <option value="2" selected>2</option>
                  <option value="3">3</option>
                </select>
              </div>
            </div>

            <div class="cal-monthbar" aria-label="<?php echo e(t('booking.availability_title')); ?>">
              <button type="button" class="cal-month-btn" id="calPrev" aria-label="Prev">‹</button>
              <div class="cal-month-title" id="calTitle"></div>
              <button type="button" class="cal-month-btn" id="calNext" aria-label="Next">›</button>
            </div>

            <table class="calendar" id="calTable" aria-label="Calendar"></table>

            <div class="legend" aria-label="Legend">
              <div class="item"><span class="swatch open"></span><?php echo e(t('booking.labels.bookable')); ?></div>
              <div class="item"><span class="swatch unavailable"></span><?php echo e(t('booking.labels.unavailable')); ?></div>
              <?php $turnoverLabel = t('booking.labels.turnover'); ?>
              <?php if ($turnoverLabel !== 'booking.labels.turnover'): ?>
                <div class="item"><span class="swatch half"></span><?php echo e($turnoverLabel); ?></div>
              <?php endif; ?>
            </div>
          </div>

          <div class="card booking-panel">
            <h2 class="section-heading" style="margin:0 0 6px;"><?php echo e(t('booking.labels.guest_data')); ?></h2>
            <p class="small muted" id="bookingSummary" style="margin:0 0 10px;">
              <?php echo e(t('booking.summary.select_dates')); ?>
            </p>
            <div id="bookingQuote" aria-live="polite" style="margin:0 0 10px;"></div>

            <form id="bookingForm" class="form" method="post" action="#" autocomplete="on">
              <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
              <input type="hidden" name="lang" value="<?php echo e(current_lang()); ?>">

              <div class="form-row">
                <div>
                  <label for="name"><?php echo e(t('booking.labels.name')); ?></label>
                  <input type="text" id="name" name="name" required>
                </div>
                <div>
                  <label for="email"><?php echo e(t('booking.labels.email')); ?></label>
                  <input type="email" id="email" name="email" required>
                </div>
              </div>

              <div class="form-row">
                <div>
                  <label for="phone"><?php echo e(t('booking.labels.phone')); ?> <span class="small muted"><?php echo e(t('booking.labels.optional')); ?></span></label>
                  <input type="tel" id="phone" name="phone" placeholder="<?php echo e(t('booking.labels.optional')); ?>">
                </div>
                <div>
                  <label for="notes"><?php echo e(t('booking.labels.notes')); ?> <span class="small muted"><?php echo e(t('booking.labels.optional')); ?></span></label>
                  <textarea id="notes" name="notes" placeholder="<?php echo e(t('booking.labels.optional')); ?>"></textarea>
                </div>
              </div>

              <div class="actions" style="display:flex; gap:10px; flex-wrap:wrap; margin-top:12px;">
                <button type="submit" class="btn primary" id="bookingSubmit"><?php echo e($ctaText); ?></button>
                <button type="button" class="btn" id="calClear"><?php echo e(t('booking.labels.clear')); ?></button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
