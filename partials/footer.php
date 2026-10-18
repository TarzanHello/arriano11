<?php
$store = load_store();
$siteName = (string)($store['settings']['site_name'] ?? 'Via Arriano 11');
$address = (string)($store['settings']['address'] ?? '');
$phone = (string)($store['settings']['phone'] ?? '');

$email = trim((string)($store['settings']['email'] ?? ''));
if ($email === '') {
  $email = trim((string)($store['settings']['notify_email'] ?? ''));
}
if ($email === '') {
  $email = 'viaarriano11@gmail.com';
}
?>

<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <div class="footer-title"><?php echo e($siteName); ?></div>
        <div class="small" style="margin:0 0 12px; opacity:.92;">
          <?php echo e(t('footer.tagline')); ?>
        </div>
        <div class="footer-list">
          <?php if ($address): ?>
            <div class="footer-item"><span class="footer-ico">📍</span> <span><?php echo e($address); ?></span></div>
          <?php endif; ?>
          <?php if ($phone): ?>
            <div class="footer-item"><span class="footer-ico">📞</span> <a href="tel:<?php echo e(str_replace(" ", "", $phone)); ?>"><?php echo e($phone); ?></a></div>
          <?php endif; ?>
          <div class="footer-item"><span class="footer-ico">✉️</span> <a href="mailto:<?php echo e($email); ?>"><?php echo e($email); ?></a></div>
        </div>
      </div>

      <div>
        <div class="footer-title"><?php echo e(t('footer.links_title')); ?></div>
        <div class="footer-links">
          <a href="<?php echo e(i18n_url('home')); ?>"><?php echo e(t('nav.home')); ?></a>
          <a href="<?php echo e(i18n_url('apartment')); ?>"><?php echo e(t('nav.apartment')); ?></a>
          <a href="<?php echo e(i18n_url('booking')); ?>"><?php echo e(t('nav.book')); ?></a>
          <a href="<?php echo e(i18n_url('contact')); ?>"><?php echo e(t('nav.contact')); ?></a>
          <a href="<?php echo e(i18n_url('rules')); ?>"><?php echo e(t('nav.rules')); ?></a>
          <a href="<?php echo e(i18n_url('tips')); ?>"><?php echo e(t('nav.tips')); ?></a>
          <a href="<?php echo e(i18n_url('privacy')); ?>"><?php echo e(t('footer.privacy')); ?></a>
          <a href="<?php echo e(i18n_url('cookies')); ?>"><?php echo e(t('footer.cookies')); ?></a>
        </div>
      </div>
    </div>

    <div class="footer-bottom">
      <div>© <?php echo e(current_year()); ?> <?php echo e($siteName); ?></div>
      <div class="small" style="opacity:.92;">
        <?php echo e(t('footer.owner')); ?>
      </div>
      <div class="small footer-legal" style="opacity:.92;">
        <a href="<?php echo e(i18n_url('privacy')); ?>"><?php echo e(t('footer.privacy')); ?></a>
        <span aria-hidden="true">·</span>
        <a href="<?php echo e(i18n_url('cookies')); ?>"><?php echo e(t('footer.cookies')); ?></a>
        <span aria-hidden="true">·</span>
        <button class="linklike" type="button" data-cookie-manage><?php echo e(t('footer.manage_cookies')); ?></button>
      </div>
    </div>
  </div>
</footer>

<!-- Cookie banner + preferences (shown only until a choice is saved) -->
<div class="cookie-banner" data-cookie-banner data-consent-version="1" hidden>
  <div class="cookie-card" role="dialog" aria-live="polite" aria-label="Cookie banner">
    <div class="cookie-copy">
      <div class="cookie-title">🍪 <?php echo e(t('cookies.banner_title')); ?></div>
      <div class="cookie-desc"><?php echo e(t('cookies.banner_text')); ?></div>
      <div class="cookie-links">
        <a href="<?php echo e(i18n_url('privacy')); ?>"><?php echo e(t('cookies.link_privacy')); ?></a>
        <span aria-hidden="true">·</span>
        <a href="<?php echo e(i18n_url('cookies')); ?>"><?php echo e(t('cookies.link_cookies')); ?></a>
      </div>
    </div>
    <div class="cookie-actions">
      <button class="btn ghost" type="button" data-cookie-settings><?php echo e(t('cookies.banner_settings')); ?></button>
      <button class="btn" type="button" data-cookie-reject><?php echo e(t('cookies.banner_reject')); ?></button>
      <button class="btn primary" type="button" data-cookie-accept><?php echo e(t('cookies.banner_accept')); ?></button>
    </div>
  </div>
</div>

<div class="cookie-modal" data-cookie-modal hidden>
  <div class="cookie-backdrop" data-cookie-close aria-hidden="true"></div>
  <div class="cookie-panel" role="dialog" aria-modal="true" aria-label="<?php echo e(t('cookies.modal_title')); ?>">
    <div class="cookie-panel-head">
      <h3 style="margin:0;"><?php echo e(t('cookies.modal_title')); ?></h3>
      <button class="icon-btn" type="button" data-cookie-close aria-label="<?php echo e(t('ui.close')); ?>">✕</button>
    </div>
    <p class="cookie-panel-intro"><?php echo e(t('cookies.modal_intro')); ?></p>

    <div class="cookie-option">
      <div>
        <div class="cookie-opt-title"><?php echo e(t('cookies.necessary_title')); ?></div>
        <div class="cookie-opt-desc"><?php echo e(t('cookies.necessary_desc')); ?></div>
      </div>
      <div class="cookie-pill"><?php echo e(t('cookies.always_on')); ?></div>
    </div>

    <div class="cookie-option">
      <div>
        <div class="cookie-opt-title"><?php echo e(t('cookies.analytics_title')); ?></div>
        <div class="cookie-opt-desc"><?php echo e(t('cookies.analytics_desc')); ?></div>
      </div>
      <label class="switch" aria-label="<?php echo e(t('cookies.analytics_title')); ?>">
        <input type="checkbox" data-cookie-analytics>
        <span class="slider" aria-hidden="true"></span>
      </label>
    </div>

    <div class="cookie-panel-actions">
      <button class="btn ghost" type="button" data-cookie-cancel><?php echo e(t('cookies.cancel')); ?></button>
      <button class="btn primary" type="button" data-cookie-save><?php echo e(t('cookies.save')); ?></button>
    </div>
  </div>
</div>

<?php
  // Route-based script loading (keeps the home page lighter for PageSpeed).
  $routeNow = function_exists('i18n_current_route') ? i18n_current_route() : '';
  $v_site = @filemtime(__DIR__ . '/../assets/js/site.js') ?: 1;
  $v_carousel = @filemtime(__DIR__ . '/../assets/js/carousel.js') ?: 1;
  $v_lightbox = @filemtime(__DIR__ . '/../assets/js/lightbox.js') ?: 1;
  $v_calendar = @filemtime(__DIR__ . '/../assets/js/calendar.js') ?: 1;
  $v_cookie = @filemtime(__DIR__ . '/../assets/js/cookie-consent.js') ?: 1;
?>
<script src="/assets/js/site.js?v=<?php echo e((string)$v_site); ?>" defer></script>
<?php if ($routeNow === 'home'): ?>
  <script src="/assets/js/carousel.js?v=<?php echo e((string)$v_carousel); ?>" defer></script>
<?php endif; ?>
<?php if (in_array($routeNow, ['home','apartment'], true)): ?>
  <script src="/assets/js/lightbox.js?v=<?php echo e((string)$v_lightbox); ?>" defer></script>
<?php endif; ?>
<?php if ($routeNow === 'booking'): ?>
  <script src="/assets/js/calendar.js?v=<?php echo e((string)$v_calendar); ?>" defer></script>
<?php endif; ?>
<script src="/assets/js/cookie-consent.js?v=<?php echo e((string)$v_cookie); ?>" defer></script>
</body>
</html>
