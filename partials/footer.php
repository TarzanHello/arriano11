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
          <a href="<?php echo e(i18n_url('neighborhood')); ?>"><?php echo e(t('nav.neighborhood')); ?></a>
          <a href="<?php echo e(i18n_url('policlinico', i18n_route_lang('policlinico', current_lang()))); ?>"><?php echo e(t('policlinico.nav')); ?></a>
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
      <?php $cin = trim((string)($store['settings']['cin'] ?? '')); ?>
      <?php if ($cin !== ''): ?>
        <div class="small footer-cin" style="opacity:.92;">
          <abbr title="Codice Identificativo Nazionale">CIN</abbr>: <strong><?php echo e($cin); ?></strong>
          <span aria-hidden="true">·</span> <?php echo e(t('home.official.license_label')); ?> n. 6603
        </div>
      <?php endif; ?>
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
<?php
  // WhatsApp quick contact (direct channel, no intermediaries).
  $waDigits = preg_replace('/\D+/', '', $phone);
  if ($waDigits !== '' && strpos($waDigits, '39') !== 0) $waDigits = '39' . $waDigits;
  $waText = t('ui.whatsapp_text');
  $waLabel = t('ui.whatsapp_label');
?>
<?php if ($waDigits !== '' && $waText !== 'ui.whatsapp_text'): ?>
  <a class="wa-float" href="https://wa.me/<?php echo e($waDigits); ?>?text=<?php echo e(rawurlencode($waText)); ?>" target="_blank" rel="noopener" aria-label="<?php echo e($waLabel); ?>" title="<?php echo e($waLabel); ?>">
    <svg viewBox="0 0 32 32" width="28" height="28" aria-hidden="true" focusable="false"><path fill="currentColor" d="M16 3C9 3 3.3 8.6 3.3 15.6c0 2.5.7 4.8 2 6.8L3 29l6.8-2.2c1.9 1 4 1.6 6.2 1.6 7 0 12.7-5.7 12.7-12.7S23 3 16 3zm0 23.2c-2 0-3.9-.6-5.6-1.6l-.4-.2-4 1.3 1.3-3.9-.3-.4c-1.1-1.7-1.7-3.7-1.7-5.8C5.3 9.8 10.1 5.1 16 5.1s10.6 4.8 10.6 10.6S21.9 26.2 16 26.2zm5.8-7.9c-.3-.2-1.9-.9-2.2-1-.3-.1-.5-.2-.7.2-.2.3-.8 1-1 1.2-.2.2-.4.2-.7.1-.3-.2-1.3-.5-2.5-1.6-.9-.8-1.6-1.8-1.7-2.1-.2-.3 0-.5.1-.6l.5-.6c.2-.2.2-.3.3-.5.1-.2 0-.4 0-.6l-1-2.4c-.3-.6-.5-.5-.7-.5h-.6c-.2 0-.6.1-.9.4-.3.3-1.2 1.1-1.2 2.8s1.2 3.2 1.4 3.5c.2.2 2.4 3.6 5.7 5 .8.3 1.4.5 1.9.7.8.2 1.5.2 2.1.1.6-.1 1.9-.8 2.2-1.5.3-.7.3-1.4.2-1.5-.1-.2-.3-.3-.6-.4z"/></svg>
    <span class="wa-float-label"><?php echo e($waLabel); ?></span>
  </a>
<?php endif; ?>

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
<?php $gaId = (string)($store['settings']['ga4_id'] ?? ''); ?>
<?php if (preg_match('/^G-[A-Z0-9]{4,20}$/', $gaId)): ?>
  <?php $v_an = @filemtime(__DIR__ . '/../assets/js/analytics.js') ?: 1; ?>
  <script nonce="<?php echo e(csp_nonce()); ?>">window.__VIA_GA_ID = <?php echo json_encode($gaId); ?>;</script>
  <script src="/assets/js/analytics.js?v=<?php echo e((string)$v_an); ?>" defer></script>
<?php endif; ?>
<script src="/assets/js/cookie-consent.js?v=<?php echo e((string)$v_cookie); ?>" defer></script>
</body>
</html>
