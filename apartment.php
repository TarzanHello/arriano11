<?php
require_once __DIR__ . '/lib/lib.php';
include __DIR__ . '/partials/header.php';

// Map gallery filenames to translated captions
function apt_caption_key($file){
  $f = strtolower((string)$file);
  if (strpos($f, 'bagno') !== false) return 'bathroom';
  if (strpos($f, 'camera_da_letto') !== false) return 'bedroom';
  if (strpos($f, 'living') !== false) return 'living';
  if (strpos($f, 'divano_letto') !== false) return 'living';
  if (strpos($f, 'giardino') !== false) return 'outdoor';
  if (strpos($f, 'cucina') !== false) return 'kitchen';
  if (strpos($f, 'macchina_caffe') !== false) return 'coffee';
  if (strpos($f, 'proiettore') !== false) return 'projector';
  if (strpos($f, 'finestra_giardino') !== false) return 'garden_view';
  return 'photo';
}

// Build a clean gallery list.
// IMPORTANT:
// - We keep only the "original" photos.
// - We skip responsive variants we generate for PageSpeed (e.g. *_480.jpg, *_800.jpg, *_1200.jpg)
//   because those files do not have matching thumbnails in /thumbs and would show as "broken".
// - We also skip any item whose thumbnail AND original image are missing.
function apt_is_responsive_variant($file){
  // Matches *_480.jpg, *_800.jpg, *_1200.jpg, *_1600.jpg ... (3-4 digits before extension)
  return (bool)preg_match('/_\d{3,4}\.jpg$/i', (string)$file);
}

function apt_best_thumb_url($file){
  $file = (string)$file;
  $base = preg_replace('/\.jpg$/i', '', $file);

  $thumbFs = __DIR__ . '/assets/images/gallery/thumbs/' . $file;
  if (file_exists($thumbFs)) return '/assets/images/gallery/thumbs/' . $file;

  // Fallback: use a smaller responsive variant if present.
  $v480 = __DIR__ . '/assets/images/gallery/' . $base . '_480.jpg';
  if (file_exists($v480)) return '/assets/images/gallery/' . $base . '_480.jpg';

  // Final fallback: use the original.
  $orig = __DIR__ . '/assets/images/gallery/' . $file;
  if (file_exists($orig)) return '/assets/images/gallery/' . $file;

  return '';
}

$gallery = glob(__DIR__ . '/assets/images/gallery/*.jpg');
if (!is_array($gallery)) $gallery = [];

// Filter out responsive variants and anything we cannot render safely.
$gallery = array_values(array_filter($gallery, function($p){
  $file = basename((string)$p);
  if ($file === '') return false;
  if (apt_is_responsive_variant($file)) return false;

  // Ensure the original exists (href target)
  if (!file_exists(__DIR__ . '/assets/images/gallery/' . $file)) return false;

  // Ensure we have a renderable thumbnail source
  $thumbUrl = apt_best_thumb_url($file);
  return $thumbUrl !== '';
}));

// Show the strongest photos first: terrace, living, bedroom, kitchen, then the rest.
$aptOrder = ['giardino', 'living', 'camera_da_letto_wide', 'camera_da_letto', 'cucina', 'divano', 'finestra', 'macchina', 'proiettore', 'bagno'];
usort($gallery, function($a, $b) use ($aptOrder) {
  $rank = function($p) use ($aptOrder) {
    $f = basename($p);
    foreach ($aptOrder as $i => $k) { if (strpos($f, $k) === 0) return $i; }
    return count($aptOrder);
  };
  $ra = $rank($a); $rb = $rank($b);
  return $ra === $rb ? strcmp($a, $b) : $ra <=> $rb;
});

$pageHero = [
  'title' => copy_get('apt_hero_title') ?? t('apartment.hero.title'),
  'lead'  => t('apartment.hero.lead'),
  'image' => '/assets/images/gallery/living_wide.jpg',
  'actions' => [
    ['href' => i18n_url('booking'), 'label' => t('apartment.hero.cta_availability'), 'class' => 'btn primary'],
    ['href' => i18n_url('contact'), 'label' => t('apartment.hero.cta_contact'), 'class' => 'btn ghost'],
  ],
];
include __DIR__ . '/partials/page-hero.php';
?>

<main class="site-main">
  <section class="section-pad">
    <div class="container split">
      <div>
        <h2><?php echo e(copy_get('apt_title') ?? t('apartment.section1.title')); ?></h2>
        <p class="lead"><?php echo e(copy_get('apt_text') ?? t('apartment.section1.text')); ?></p>
        <?php include __DIR__ . '/partials/facts.php'; ?>
        <div class="actions" style="margin-top:28px;">
          <a class="btn primary" href="<?php echo e(i18n_url('booking')); ?>"><?php echo e(t('apartment.hero.cta_availability')); ?></a>
          <a class="btn" href="<?php echo e(i18n_url('rules')); ?>"><?php echo e(t('apartment.section1.cta_rules')); ?></a>
        </div>
      </div>
      <div class="photo-pair">
        <img src="/assets/images/gallery/giardino_2_800.jpg" width="800" height="450" loading="lazy" decoding="async" alt="<?php echo e(t('apartment.captions.outdoor')); ?>">
        <img src="/assets/images/gallery/thumbs/divano_letto.jpg" width="480" height="320" loading="lazy" decoding="async" alt="<?php echo e(t('apartment.captions.living')); ?>">
      </div>
    </div>
  </section>

  <?php $rooms = copy_get('rooms'); ?>
  <?php if (is_array($rooms) && $rooms): ?>
  <section class="section-pad section-ink rooms-band" aria-label="<?php echo e((string)copy_get('apt_title')); ?>">
    <div class="container">
      <dl class="rooms-list">
        <?php foreach ($rooms as $r): ?>
          <div>
            <dt><?php echo e((string)($r['h'] ?? '')); ?></dt>
            <dd><?php echo e((string)($r['p'] ?? '')); ?></dd>
          </div>
        <?php endforeach; ?>
      </dl>
    </div>
  </section>
  <?php endif; ?>

  <section class="section-pad section-muted" id="gallery" aria-labelledby="gallery-title">
    <div class="container">
      <div class="section-head">
        <h2 id="gallery-title"><?php echo e(t('apartment.gallery.title')); ?></h2>
        <p><?php echo e(t('apartment.gallery.subtitle')); ?></p>
      </div>
      <div class="gallery" data-lightbox="apartment">
        <?php foreach ($gallery as $p):
          $file = basename($p);
          $key = apt_caption_key($file);
          $alt = t('apartment.captions.' . $key);
          if ($alt === 'apartment.captions.' . $key) $alt = t('apartment.captions.photo');
          $thumb = apt_best_thumb_url($file);
          if ($thumb === '') continue;
        ?>
          <a href="/assets/images/gallery/<?php echo e($file); ?>" aria-label="<?php echo e(t('ui.open_photo')); ?>: <?php echo e($alt); ?>" data-alt="<?php echo e($alt); ?>">
            <img loading="lazy" decoding="async" src="<?php echo e($thumb); ?>" alt="<?php echo e($alt); ?>">
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="section-pad">
    <div class="container">
      <?php include __DIR__ . '/partials/price-match.php'; ?>
    </div>
  </section>
  <?php $faqSchema = false; include __DIR__ . '/partials/faq.php'; ?>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
