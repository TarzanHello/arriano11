<?php
require_once __DIR__ . '/lib/lib.php';
include __DIR__ . '/partials/header.php';

// Home — redesign v2 (Oct 2026). Texts come from the existing i18n keys (14 languages).

// Responsive image srcset helper (16:9 gallery assets with *_480/_800/_1200 variants).
function responsive_srcset_16x9($src) {
  $ext = pathinfo($src, PATHINFO_EXTENSION);
  if (!$ext) return '';
  $base = substr($src, 0, -(strlen($ext) + 1));
  $set = [];
  foreach (['480', '800', '1200'] as $w) {
    if (file_exists(__DIR__ . $base . '_' . $w . '.' . $ext)) $set[] = $base . '_' . $w . '.' . $ext . ' ' . $w . 'w';
  }
  $set[] = $src . ' 1600w';
  return implode(', ', $set);
}

$heroImg = '/assets/images/gallery/giardino_2.jpg';
$address = (string)($store['settings']['address'] ?? '');
$addressDisplay = $address ?: 'Via Arriano 11, 00176 Roma';
$mapUrl = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($addressDisplay);
$cinHome = trim((string)($store['settings']['cin'] ?? ''));
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

$filmstrip = [
  ['file' => 'giardino_1.jpg', 'alt' => t('apartment.captions.outdoor')],
  ['file' => 'camera_da_letto_wide.jpg', 'alt' => t('apartment.captions.bedroom')],
  ['file' => 'living_wide.jpg', 'alt' => t('apartment.captions.living')],
  ['file' => 'cucina_top.jpg', 'alt' => t('apartment.captions.kitchen')],
  ['file' => 'camera_da_letto_3.jpg', 'alt' => t('apartment.captions.bedroom')],
  ['file' => 'bagno_2.jpg', 'alt' => t('apartment.captions.bathroom')],
  ['file' => 'divano_letto_2.jpg', 'alt' => t('apartment.captions.living')],
  ['file' => 'macchina_caffe.jpg', 'alt' => t('apartment.captions.coffee')],
  ['file' => 'proiettore.jpg', 'alt' => t('apartment.captions.projector')],
];
function home_thumb($file) {
  $base = preg_replace('/\.jpg$/i', '', $file);
  if (file_exists(__DIR__ . '/assets/images/gallery/' . $base . '_480.jpg')) return '/assets/images/gallery/' . $base . '_480.jpg';
  if (file_exists(__DIR__ . '/assets/images/gallery/thumbs/' . $file)) return '/assets/images/gallery/thumbs/' . $file;
  return '/assets/images/gallery/' . $file;
}

$guide = i18n_get('neighborhood_guide');
?>

<main class="site-main" id="main">

  <!-- Hero: the terrace, with the headline pasted on like a street poster -->
  <section class="hero-home">
    <div class="hero-home-media">
      <img src="<?php echo e($heroImg); ?>" srcset="<?php echo e(responsive_srcset_16x9($heroImg)); ?>" sizes="100vw"
        width="1600" height="900" alt="<?php echo e(t('apartment.captions.outdoor')); ?>" fetchpriority="high" decoding="async">
    </div>
    <div class="container hero-home-inner">
      <h1><?php echo strip_tags(t('home.hero.title'), '<br>'); ?></h1>
      <p class="lead"><?php echo e(t('meta.home.description')); ?></p>
    </div>
  </section>

  <!-- Quick availability: opens the booking calendar with the dates already selected -->
  <div class="container quick-book">
    <form action="<?php echo e(i18n_url('booking')); ?>" method="get" aria-label="<?php echo e(t('booking.availability_title')); ?>">
      <label>
        <span><?php echo e(t('booking.labels.checkin')); ?></span>
        <input type="date" name="start" min="<?php echo e($today); ?>" required>
      </label>
      <label>
        <span><?php echo e(t('booking.labels.checkout')); ?></span>
        <input type="date" name="end" min="<?php echo e($tomorrow); ?>" required>
      </label>
      <label>
        <span><?php echo e(t('booking.labels.guests')); ?></span>
        <select name="guests">
          <option value="1">1</option>
          <option value="2" selected>2</option>
          <option value="3">3</option>
        </select>
      </label>
      <button class="btn primary" type="submit"><?php echo e(t('home.spaces.cta_availability')); ?></button>
    </form>
  </div>

  <!-- Route: the real Metro C trip from the door to the Colosseum -->
  <section class="route" aria-labelledby="route-title">
    <div class="container">
      <h2 id="route-title"><?php echo e(t('v2.title')); ?></h2>
      <ol class="route-line">
        <li><span class="route-dot" aria-hidden="true"></span><span class="route-name">Via Arriano 11</span><span class="route-note"><?php echo e(t('v2.start')); ?></span></li>
        <li><span class="route-dot" aria-hidden="true"></span><span class="route-name">Metro C</span><span class="route-note"><?php echo e(t('v2.walk')); ?></span></li>
        <li><span class="route-dot" aria-hidden="true"></span><span class="route-name">San Giovanni</span><span class="route-note"><?php echo e(t('v2.metro_a')); ?></span></li>
        <li><span class="route-dot" aria-hidden="true"></span><span class="route-name">Colosseo</span><span class="route-note"><?php echo e(t('v2.colosseo')); ?></span></li>
      </ol>
    </div>
  </section>

  <!-- The apartment -->
  <section class="section-pad">
    <div class="container split">
      <div>
        <h2><?php echo e(t('home.intro.title')); ?></h2>
        <p class="lead"><?php echo e(t('apartment.section1.text')); ?></p>
        <?php include __DIR__ . '/partials/facts.php'; ?>
        <div class="actions" style="margin-top:28px;">
          <a class="btn primary" href="<?php echo e(i18n_url('booking')); ?>"><?php echo e(t('home.hero.cta_primary')); ?></a>
          <a class="btn" href="<?php echo e(i18n_url('apartment')); ?>"><?php echo e(t('home.hero.cta_secondary')); ?></a>
        </div>
      </div>
      <div class="photo-pair">
        <img src="/assets/images/gallery/camera_da_letto_wide_800.jpg" width="800" height="450" loading="lazy" decoding="async" alt="<?php echo e(t('apartment.captions.bedroom')); ?>">
        <img src="/assets/images/gallery/living_1_480.jpg" width="480" height="270" loading="lazy" decoding="async" alt="<?php echo e(t('apartment.captions.living')); ?>">
      </div>
    </div>
  </section>

  <!-- Film strip: the photos, like frames of a Pasolini reel -->
  <section class="filmstrip" aria-label="<?php echo e(t('v2.photos')); ?>">
    <div class="filmstrip-track" data-lightbox="home">
      <?php foreach ($filmstrip as $f): ?>
        <a href="/assets/images/gallery/<?php echo e($f['file']); ?>" aria-label="<?php echo e(t('ui.open_photo')); ?>: <?php echo e($f['alt']); ?>" data-alt="<?php echo e($f['alt']); ?>">
          <img src="<?php echo e(home_thumb($f['file'])); ?>" alt="<?php echo e($f['alt']); ?>" loading="lazy" decoding="async" width="450" height="300">
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <div class="container filmstrip-foot">
    <p class="muted" style="margin:0;"><?php echo e(t('home.gallery.subtitle')); ?></p>
    <a class="btn" href="<?php echo e(i18n_url('apartment')); ?>#gallery"><?php echo e(t('home.gallery.cta_all_photos')); ?></a>
  </div>

  <!-- Best price promise -->
  <section class="section-pad">
    <div class="container">
      <?php include __DIR__ . '/partials/price-match.php'; ?>
    </div>
  </section>

  <!-- Verified reviews (only when there are approved ones) -->
  <?php
    $allReviews = load_reviews();
    $approvedReviews = reviews_approved($allReviews);
    usort($approvedReviews, function($a, $b) { return (int)($b['created_at'] ?? 0) <=> (int)($a['created_at'] ?? 0); });
    $stats = reviews_stats($allReviews);
    $count = (int)($stats['count'] ?? 0);
    $avgLabel = number_format((float)($stats['avg'] ?? 0), 1, current_lang() === 'it' ? ',' : '.', '');
  ?>
  <?php if ($count > 0): ?>
  <section class="section-pad section-muted" aria-labelledby="reviews-title">
    <div class="container">
      <div class="section-head">
        <h2 id="reviews-title"><?php echo e(t('reviews.section_title')); ?></h2>
        <p><?php echo e(t('reviews.avg_label')); ?>: <strong><?php echo e($avgLabel); ?>/5</strong>, <?php echo e($count); ?> <?php echo e(t('reviews.count_label')); ?></p>
      </div>
      <div class="reviews-grid">
        <?php foreach (array_slice($approvedReviews, 0, 6) as $r): ?>
          <?php
            $rating = (int)($r['rating'] ?? 0);
            if ($rating < 1 || $rating > 5) $rating = 0;
          ?>
          <article class="review">
            <div class="review-stars" aria-label="<?php echo e($rating); ?>/5"><?php echo e(str_repeat('★', $rating) . str_repeat('☆', 5 - $rating)); ?></div>
            <p style="white-space:pre-line; margin:8px 0;"><?php echo e(trim((string)($r['body'] ?? ''))); ?></p>
            <p class="small muted" style="margin:0;"><strong><?php echo e(trim((string)($r['author'] ?? '')) ?: 'Guest'); ?></strong> <?php echo e((string)($r['date_published'] ?? '')); ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- The neighbourhood -->
  <section class="section-pad section-ink band-quartiere" aria-labelledby="quartiere-title">
    <div class="container split">
      <div>
        <h2 id="quartiere-title"><?php echo e(is_array($guide) ? (string)($guide['title'] ?? t('nav.neighborhood')) : t('nav.neighborhood')); ?></h2>
        <?php if (is_array($guide) && !empty($guide['intro'])): ?>
          <p class="lead"><?php echo e((string)$guide['intro']); ?></p>
        <?php endif; ?>
        <div class="actions" style="margin-top:24px;">
          <a class="btn primary" href="<?php echo e(i18n_url('neighborhood')); ?>"><?php echo e(t('nav.neighborhood')); ?></a>
          <a class="btn" href="<?php echo e(i18n_url('tips')); ?>"><?php echo e(t('nav.tips')); ?></a>
        </div>
      </div>
      <img src="/assets/images/gallery/giardino_1_800.jpg" width="800" height="450" loading="lazy" decoding="async" alt="<?php echo e(t('apartment.captions.outdoor')); ?>">
    </div>
  </section>

  <!-- Official information (entity data for Google, legal identifiers) -->
  <section class="section-pad section-muted official" aria-labelledby="official-title">
    <div class="container">
      <h2 id="official-title" style="font-size:clamp(24px, 2.6vw, 32px);"><?php echo e(t('home.official.heading')); ?></h2>
      <p style="max-width:75ch;"><?php echo t('home.official.text_html', ['name' => e($siteName), 'address' => e($addressDisplay)]); ?></p>
      <p><strong><?php echo e(t('home.official.address_label')); ?>:</strong> <?php echo e($addressDisplay); ?> (<a href="<?php echo e($mapUrl); ?>" target="_blank" rel="noopener"><?php echo e(t('home.official.map_label')); ?></a>)</p>
      <p><strong><?php echo e(t('home.official.license_label')); ?>:</strong> 6603<?php if ($cinHome !== ''): ?>, <strong><abbr title="Codice Identificativo Nazionale">CIN</abbr>:</strong> <?php echo e($cinHome); ?><?php endif; ?></p>
    </div>
  </section>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
