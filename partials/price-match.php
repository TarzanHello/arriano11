<?php
// "Best price" promise: if the guest finds a lower price on Booking for the same dates,
// they send a screenshot by WhatsApp/email and get 10% off that price (owner decision, Oct 2026).
if (!isset($store) || !is_array($store)) $store = load_store();
$pmTitle = t('pricematch.title');
if ($pmTitle !== 'pricematch.title'):
  $pmPhone = preg_replace('/\D+/', '', (string)($store['settings']['phone'] ?? ''));
  if ($pmPhone !== '' && strpos($pmPhone, '39') !== 0) $pmPhone = '39' . $pmPhone;
  $pmEmail = trim((string)($store['settings']['email'] ?? ''));
  if ($pmEmail === '') $pmEmail = notify_email($store);
  $pmWaHref = $pmPhone !== '' ? ('https://wa.me/' . $pmPhone . '?text=' . rawurlencode(t('pricematch.whatsapp_text'))) : '';
  $pmMailHref = 'mailto:' . $pmEmail . '?subject=' . rawurlencode(t('pricematch.email_subject')) . '&body=' . rawurlencode(t('pricematch.whatsapp_text'));
?>
<div class="price-match" role="note">
  <div class="price-match-badge" aria-hidden="true">-10%</div>
  <div class="price-match-body">
    <h3 class="price-match-title"><?php echo e($pmTitle); ?></h3>
    <p class="price-match-text"><?php echo e(t('pricematch.text')); ?></p>
    <div class="price-match-actions">
      <?php if ($pmWaHref !== ''): ?>
        <a class="btn primary" href="<?php echo e($pmWaHref); ?>" target="_blank" rel="noopener" data-track="pricematch_whatsapp"><?php echo e(t('pricematch.cta_whatsapp')); ?></a>
      <?php endif; ?>
      <a class="btn" href="<?php echo e($pmMailHref); ?>" data-track="pricematch_email"><?php echo e(t('pricematch.cta_email')); ?></a>
    </div>
  </div>
</div>
<?php endif; ?>
