<?php
// FAQ block (copy v2) + FAQPage structured data. Rendered only when the current
// language has its own FAQ texts. Answers use the real values from the settings.
$faqItems = copy_get('faq', copy_vars($store));
$faqTitle = copy_get('faq_title');
if (is_array($faqItems) && $faqItems):
  $faqLd = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => []];
?>
<section class="section-pad faq-section" aria-labelledby="faq-title">
  <div class="container">
    <h2 id="faq-title"><?php echo e((string)$faqTitle); ?></h2>
    <div class="faq">
      <?php foreach ($faqItems as $i => $f): ?>
        <?php
          $q = (string)($f['q'] ?? ''); $a = (string)($f['a'] ?? '');
          if ($q === '' || $a === '') continue;
          $faqLd['mainEntity'][] = ['@type' => 'Question', 'name' => $q, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a]];
        ?>
        <details<?php echo $i === 0 ? ' open' : ''; ?>>
          <summary><?php echo e($q); ?></summary>
          <p><?php echo e($a); ?></p>
        </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php if (!isset($faqSchema) || $faqSchema): // structured data only once per site (home) ?>
<script type="application/ld+json"><?php echo json_encode($faqLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
<?php endif; ?>
<?php endif; ?>
