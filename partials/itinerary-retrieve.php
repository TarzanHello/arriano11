<?php
// "Hai già un codice?" box (itinerary hub and builder pages). Needs $U (itinerary UI texts).
$irBad = isset($_GET['codice_errato']);
?>
<form class="itin-retrieve card" method="get" action="<?php echo e(i18n_url('itin_saved')); ?>">
  <h2 class="itin-retrieve-title"><?php echo e($U['retrieve_title']); ?></h2>
  <p><?php echo e($U['retrieve_text']); ?></p>
  <div class="itin-retrieve-row">
    <label class="sr-only" for="itinCode"><?php echo e($U['code_label']); ?></label>
    <input id="itinCode" name="codice" type="text" inputmode="text" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="12" placeholder="<?php echo e($U['retrieve_placeholder']); ?>" required>
    <button class="btn primary" type="submit"><?php echo e($U['retrieve_btn']); ?></button>
  </div>
  <?php if ($irBad): ?>
    <p class="itin-retrieve-bad" role="alert"><?php echo e($U['retrieve_bad']); ?></p>
  <?php endif; ?>
</form>
