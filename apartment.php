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

sort($gallery);

$pageHero = [
  'title' => t('apartment.hero.title'),
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
    <div class="container">
      <div class="split-showcase">
        <div>
          <div class="section-head">
            <h2 class="section-heading"><?php echo e(t('apartment.section1.title')); ?></h2>
            <p class="muted" style="margin:0; max-width:70ch;">
              <?php echo e(t('apartment.section1.text')); ?>
            </p>
          </div>

          <div class="tile-grid" aria-label="<?php echo e(t('apartment.section1.title')); ?>">
            <div class="tile">
              <div class="tile-icon" aria-hidden="true">📶</div>
              <div>
                <h3><?php echo e(t('apartment.section1.wifi_title')); ?></h3>
                <p><?php echo e(t('apartment.section1.wifi_text')); ?></p>
              </div>
            </div>
            <div class="tile">
              <div class="tile-icon" aria-hidden="true">❄️</div>
              <div>
                <h3><?php echo e(t('apartment.section1.ac_title')); ?></h3>
                <p><?php echo e(t('apartment.section1.ac_text')); ?></p>
              </div>
            </div>
            <div class="tile">
              <div class="tile-icon" aria-hidden="true">🍳</div>
              <div>
                <h3><?php echo e(t('apartment.section1.kitchen_title')); ?></h3>
                <p><?php echo e(t('apartment.section1.kitchen_text')); ?></p>
              </div>
            </div>
            <div class="tile">
              <div class="tile-icon" aria-hidden="true">🌿</div>
              <div>
                <h3><?php echo e(t('apartment.section1.outdoor_title')); ?></h3>
                <p><?php echo e(t('apartment.section1.outdoor_text')); ?></p>
              </div>
            </div>
          </div>

          <div class="center-actions" style="justify-content:flex-start; margin-top:18px;">
            <a class="btn secondary" href="<?php echo e(i18n_url('tips')); ?>"><?php echo e(t('apartment.section1.cta_tips')); ?></a>
            <a class="btn" href="<?php echo e(i18n_url('rules')); ?>"><?php echo e(t('apartment.section1.cta_rules')); ?></a>
          </div>
        </div>

        <div>
          <div class="showcase-card">
            <img loading="lazy" decoding="async" src="/assets/images/gallery/divano_letto.jpg" alt="<?php echo e(t('apartment.captions.living')); ?>">

            <div class="showcase-badges">
              <div class="mini-tile">
                <div class="tile-icon" aria-hidden="true" style="width:40px;height:40px;border-radius:14px;">🚇</div>
                <div>
                  <h3><?php echo e(t('apartment.section1.metro_title')); ?></h3>
                  <p><?php echo e(t('apartment.section1.metro_text')); ?></p>
                </div>
              </div>
              <div class="mini-tile">
                <div class="tile-icon" aria-hidden="true" style="width:40px;height:40px;border-radius:14px;">☕</div>
                <div>
                  <h3><?php echo e(t('apartment.section1.details_title')); ?></h3>
                  <p><?php echo e(t('apartment.section1.details_text')); ?></p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="container" style="margin-top:18px;">
      <?php include __DIR__ . '/partials/price-match.php'; ?>
    </div>
  </section>

  <section class="section-pad section-muted" id="gallery">
    <div class="container">
      <div class="section-title left">
        <div class="script-title"><?php echo e(t('apartment.gallery.title')); ?></div>
        <div class="section-subtitle"><?php echo e(t('apartment.gallery.subtitle')); ?></div>
      </div>

      <div class="gallery" data-lightbox>
        <?php foreach ($gallery as $p):
          $file = basename($p);
          $key = apt_caption_key($file);
          $alt = t('apartment.captions.' . $key);
          if ($alt === 'apartment.captions.' . $key) $alt = t('apartment.captions.photo');

          // Choose the best available thumbnail source (thumbs folder, responsive variant, or original).
          $thumb = apt_best_thumb_url($file);
          if ($thumb === '') continue; // Safety: never output broken images.
        ?>
          <a href="/assets/images/gallery/<?php echo e($file); ?>" aria-label="<?php echo e(t('ui.open_photo')); ?>">
            <img loading="lazy" decoding="async" src="<?php echo e($thumb); ?>" alt="<?php echo e($alt); ?>">
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
