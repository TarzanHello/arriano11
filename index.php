<?php
require_once __DIR__ . '/lib/lib.php';
include __DIR__ . '/partials/header.php';

/**
 * Home page layout aligned to the "Modern Cozy" stylesheet.
 * (Fix: / and home hero layout was using old classes like hero-grid not present in CSS)
 */

$heroSlides = [
  ['src' => '/assets/images/gallery/camera_da_letto_wide.jpg', 'alt' => t('apartment.captions.bedroom')],
  ['src' => '/assets/images/gallery/living_wide.jpg', 'alt' => t('apartment.captions.living')],
  ['src' => '/assets/images/gallery/giardino_1.jpg', 'alt' => t('apartment.captions.outdoor')],
];

$showcaseImg = ['src' => '/assets/images/gallery/living_1.jpg', 'alt' => t('apartment.captions.living')];

$rooms = [
  [
    'src' => '/assets/images/gallery/camera_da_letto_3.jpg',
    'alt' => t('apartment.captions.bedroom'),
    'title' => t('home.spaces.room1_title'),
    'desc' => t('home.spaces.room1_text'),
    'icon' => 'bed',
  ],
  [
    'src' => '/assets/images/gallery/giardino_2.jpg',
    'alt' => t('apartment.captions.outdoor'),
    'title' => t('home.spaces.room2_title'),
    'desc' => t('home.spaces.room2_text'),
    'icon' => 'terrace',
  ],
];

$galleryPreview = [
  ['file' => 'living_2.jpg', 'alt' => t('apartment.captions.living')],
  ['file' => 'camera_da_letto_2.jpg', 'alt' => t('apartment.captions.bedroom')],
  ['file' => 'giardino_1.jpg', 'alt' => t('apartment.captions.outdoor')],
  ['file' => 'bagno_2.jpg', 'alt' => t('apartment.captions.bathroom')],
  ['file' => 'cucina_top.jpg', 'alt' => t('apartment.captions.kitchen')],
  ['file' => 'macchina_caffe.jpg', 'alt' => t('apartment.captions.coffee')],
  ['file' => 'proiettore.jpg', 'alt' => t('apartment.captions.projector')],
  ['file' => 'divano_letto_2.jpg', 'alt' => t('apartment.captions.living')],
];

// Responsive image srcset helper (16:9 gallery assets).
// Requires *_480, *_800, *_1200 image variants to exist next to the 1600w original.
function responsive_srcset_16x9($src) {
  $ext = pathinfo($src, PATHINFO_EXTENSION);
  if (!$ext) return '';
  $base = substr($src, 0, -(strlen($ext) + 1));
  $set = [
    $base . '_480.' . $ext . ' 480w',
    $base . '_800.' . $ext . ' 800w',
    $base . '_1200.' . $ext . ' 1200w',
    $src . ' 1600w',
  ];
  return implode(', ', $set);
}
?>

<!-- HERO (wide images only) -->
<section class="hero-cover hero-modern" aria-label="Hero">
  <div class="hero-media" aria-hidden="true">
    <div class="carousel hero-carousel" data-carousel>
      <div class="carousel-track">
        <?php foreach ($heroSlides as $i => $s): ?>
          <?php $isFirst = ($i === 0); ?>
          <div class="carousel-slide">
            <img
              loading="<?php echo $isFirst ? 'eager' : 'lazy'; ?>"
              fetchpriority="<?php echo $isFirst ? 'high' : 'low'; ?>"
              decoding="async"
              width="1600"
              height="900"
              src="<?php echo e($s['src']); ?>"
              srcset="<?php echo e(responsive_srcset_16x9($s['src'])); ?>"
              sizes="100vw"
              alt="<?php echo e($s['alt']); ?>">
          </div>
        <?php endforeach; ?>
      </div>
      <button class="carousel-btn prev" type="button" aria-label="<?php echo e(t('ui.previous_photo')); ?>">‹</button>
      <button class="carousel-btn next" type="button" aria-label="<?php echo e(t('ui.next_photo')); ?>">›</button>
      <div class="carousel-dots" aria-hidden="true"></div>
    </div>
  </div>

  <div class="hero-overlay">
    <div class="container">
      <div class="hero-content">
        <div class="hero-kicker"><?php echo e($siteName ?? t('nav.brand')); ?></div>
        <h1 class="hero-title"><?php echo t('home.hero.title'); ?></h1>
        <p class="hero-lead"><?php echo e(t('home.hero.lead')); ?></p>
        <div class="hero-actions">
          <a class="btn primary" href="<?php echo e(i18n_url('booking')); ?>"><?php echo e(t('home.hero.cta_primary')); ?></a>
          <a class="btn ghost" href="<?php echo e(i18n_url('apartment')); ?>"><?php echo e(t('home.hero.cta_secondary')); ?></a>
        </div>
      </div>
    </div>
  </div>
</section>

<?php
  // SEO/entity reinforcement: keep a short, explicit “official site” block in server-side HTML.
  // Localized on every home language version to strengthen entity reconciliation across alternates.
  $mapQuery = $address ?: 'Via Arriano 11, 00176 Roma';
  $mapUrl = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($mapQuery);

  $addressDisplay = $address ?: 'Via Arriano 11, 00176 Roma';
  $officialHeading = t('home.official.heading');
  $officialTextHtml = t('home.official.text_html', [
    'name' => e($siteName ?? 'Via Arriano 11'),
    'address' => e($addressDisplay),
  ]);
  $addrLabel = t('home.official.address_label');
  $mapLabel = t('home.official.map_label');
  $licenseLabel = t('home.official.license_label');
?>
<section class="section-pad section-muted" aria-label="<?php echo e($officialHeading); ?>">
  <div class="container">
    <div class="card" style="padding:18px;">
      <h2 class="section-heading" style="margin:0 0 8px;"><?php echo e($officialHeading); ?></h2>
      <p class="muted" style="max-width:80ch; margin:0 0 10px;">
        <?php echo $officialTextHtml; ?>
      </p>
      <p class="small" style="margin:0;">
        <strong><?php echo e($addrLabel); ?>:</strong> <span><?php echo e($addressDisplay); ?></span>
        · <a href="<?php echo e($mapUrl); ?>" target="_blank" rel="noopener"><?php echo e($mapLabel); ?></a>
        · <strong><?php echo e($licenseLabel); ?>:</strong> 6603
      </p>
    </div>
    <div style="margin-top:14px;">
      <?php include __DIR__ . '/partials/price-match.php'; ?>
    </div>
  </div>
</section>

<main class="site-main">
  <!-- SECTION: Intro -->
  <section class="section-pad" aria-label="<?php echo e(t('home.intro.title')); ?>">
    <div class="container">
      <div class="split-showcase">
        <div class="showcase-text">
          <h2 class="section-heading"><?php echo e(t('home.intro.title')); ?></h2>
          <p class="muted"><?php echo e(t('home.intro.text')); ?></p>

          <div class="tile-grid" aria-label="<?php echo e(t('home.intro.title')); ?>">
            <div class="tile">
              <div class="tile-icon" aria-hidden="true">📶</div>
              <div>
                <h3><?php echo e(t('home.intro.tile_wifi_title')); ?></h3>
                <p><?php echo e(t('home.intro.tile_wifi_text')); ?></p>
              </div>
            </div>

            <div class="tile">
              <div class="tile-icon" aria-hidden="true">📍</div>
              <div>
                <h3><?php echo e(t('home.intro.tile_location_title')); ?></h3>
                <p><?php echo e(t('home.intro.tile_location_text')); ?></p>
              </div>
            </div>
          </div>
        </div>

        <div class="showcase-media">
          <div class="showcase-card">
            <img loading="lazy" decoding="async" width="1600" height="900" src="<?php echo e($showcaseImg['src']); ?>"
              srcset="<?php echo e(responsive_srcset_16x9($showcaseImg['src'])); ?>"
              sizes="(max-width: 900px) 100vw, 50vw"
              alt="<?php echo e($showcaseImg['alt']); ?>">

            <div class="showcase-badges" aria-label="<?php echo e(t('home.intro.title')); ?>">
              <div class="mini-tile">
                <div class="tile-icon" aria-hidden="true">
                  🛏️
                </div>
                <div>
                  <h3><?php echo e(t('home.intro.badge_comfort_title')); ?></h3>
                  <p><?php echo e(t('home.intro.badge_comfort_text')); ?></p>
                </div>
              </div>

              <div class="mini-tile">
                <div class="tile-icon" aria-hidden="true">
                  🌿
                </div>
                <div>
                  <h3><?php echo e(t('home.intro.badge_outdoor_title')); ?></h3>
                  <p><?php echo e(t('home.intro.badge_outdoor_text')); ?></p>
                </div>
              </div>
            </div>
          </div>

          <div class="center-actions" style="margin-top:16px;">
            <a class="btn primary" href="<?php echo e(i18n_url('apartment')); ?>#gallery"><?php echo e(t('home.intro.see_more_photos')); ?></a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- SECTION: Spaces -->
  <section class="section-pad section-muted" aria-label="<?php echo e(t('home.spaces.title')); ?>">
    <div class="container">
      <div class="section-head">
        <h2 class="section-heading"><?php echo e(t('home.spaces.title')); ?></h2>
        <p class="muted"><?php echo e(t('home.spaces.subtitle')); ?></p>
      </div>

      <div class="rooms-grid">
        <?php foreach ($rooms as $r): ?>
          <article class="room-xl">
            <div class="room-xl-media">
              <img loading="lazy" decoding="async" width="1600" height="900" src="<?php echo e($r['src']); ?>"
                srcset="<?php echo e(responsive_srcset_16x9($r['src'])); ?>"
                sizes="(max-width: 900px) 100vw, 50vw"
                alt="<?php echo e($r['alt']); ?>">
            </div>
            <div class="room-xl-body">
              <div class="room-xl-head">
                <div class="room-xl-icon" aria-hidden="true">
                  <?php if ($r['icon'] === 'bed'): ?>
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7v10"/><path d="M21 17V7"/><path d="M3 14h18"/><path d="M7 14V9h10v5"/></svg>
                  <?php else: ?>
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M7 21v-8a4 4 0 0 1 4-4h2a4 4 0 0 1 4 4v8"/><path d="M9 9V5a3 3 0 0 1 6 0v4"/></svg>
                  <?php endif; ?>
                </div>
                <h3><?php echo e($r['title']); ?></h3>
              </div>
              <p class="muted"><?php echo e($r['desc']); ?></p>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <div class="center-actions" style="margin-top:18px;">
        <a class="btn primary" href="<?php echo e(i18n_url('booking')); ?>"><?php echo e(t('home.spaces.cta_availability')); ?></a>
        <a class="btn" href="<?php echo e(i18n_url('contact')); ?>"><?php echo e(t('home.spaces.cta_contact')); ?></a>
      </div>
    </div>
  </section>

  <!-- SECTION: Services -->
  <section class="section-pad" aria-label="<?php echo e(t('home.services.title')); ?>">
    <div class="container">
      <div class="panel-grid">
        <div class="card panel">
          <h2 class="section-heading" style="margin-top:0;"><?php echo e(t('home.services.title')); ?></h2>
          <p class="muted"><?php echo e(t('home.services.text')); ?></p>

          <div class="amenities-grid" role="list">
            <div class="amenity" role="listitem">
              <div class="amenity-icon" aria-hidden="true">📶</div>
              <div>
                <div class="amenity-title"><?php echo e(t('home.services.amenity_wifi_title')); ?></div>
                <div class="amenity-sub"><?php echo e(t('home.services.amenity_wifi_sub')); ?></div>
              </div>
            </div>

            <div class="amenity" role="listitem">
              <div class="amenity-icon" aria-hidden="true">🍽️</div>
              <div>
                <div class="amenity-title"><?php echo e(t('home.services.amenity_kitchen_title')); ?></div>
                <div class="amenity-sub"><?php echo e(t('home.services.amenity_kitchen_sub')); ?></div>
              </div>
            </div>

            <div class="amenity" role="listitem">
              <div class="amenity-icon" aria-hidden="true">📍</div>
              <div>
                <div class="amenity-title"><?php echo e(t('home.services.amenity_location_title')); ?></div>
                <div class="amenity-sub"><?php echo e(t('home.services.amenity_location_sub')); ?></div>
              </div>
            </div>

            <div class="amenity" role="listitem">
              <div class="amenity-icon" aria-hidden="true">✉️</div>
              <div>
                <div class="amenity-title"><?php echo e(t('home.services.amenity_support_title')); ?></div>
                <div class="amenity-sub"><?php echo e(t('home.services.amenity_support_sub')); ?></div>
              </div>
            </div>
          </div>
        </div>

        <div class="card panel panel-highlight">
          <h2 class="section-heading" style="margin-top:0;"><?php echo e(t('home.services.panel_title')); ?></h2>
          <p class="muted"><?php echo e(t('home.services.panel_text')); ?></p>

          <div class="center-actions" style="justify-content:flex-start; margin-top:14px;">
            <a class="btn primary" href="<?php echo e(i18n_url('booking')); ?>"><?php echo e(t('home.services.panel_cta_calendar')); ?></a>
            <a class="btn" href="<?php echo e(i18n_url('contact')); ?>"><?php echo e(t('home.services.panel_cta_write')); ?></a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- SECTION: Verified reviews (guest-only) -->
  <?php
    $allReviews = load_reviews();
    $approvedReviews = reviews_approved($allReviews);
    // Newest first
    usort($approvedReviews, function($a, $b) {
      return (int)($b['created_at'] ?? 0) <=> (int)($a['created_at'] ?? 0);
    });
    $stats = reviews_stats($allReviews);
    $avg = (float)($stats['avg'] ?? 0);
    $count = (int)($stats['count'] ?? 0);
    $show = array_slice($approvedReviews, 0, 6);

    $avgLabel = number_format($avg, 1);
    if (current_lang() === 'it') {
      $avgLabel = str_replace('.', ',', $avgLabel);
    }
  ?>
  <section class="section-pad section-muted" aria-label="<?php echo e(t('reviews.section_title')); ?>">
    <div class="container">
      <div class="section-head">
        <h2 class="section-heading"><?php echo e(t('reviews.section_title')); ?></h2>
        <p class="muted"><?php echo e(strtr(t('reviews.section_lead'), ['{site}' => $siteName])); ?></p>
      </div>

      <?php if ($count <= 0): ?>
        <div class="card" style="padding:18px;">
          <p class="muted" style="margin:0;"><?php echo e(t('reviews.no_reviews_yet')); ?></p>
        </div>
      <?php else: ?>
        <div class="notice" style="margin:0 0 14px;">
          <strong><?php echo e(t('reviews.avg_label')); ?>:</strong> <?php echo e($avgLabel); ?>/5 · <strong><?php echo e($count); ?></strong> <?php echo e(t('reviews.count_label')); ?>
        </div>

        <div class="reviews-grid" style="display:grid; grid-template-columns:repeat(3,1fr); gap:14px;">
          <?php foreach ($show as $r): ?>
            <?php
              $author = trim((string)($r['author'] ?? '')) ?: 'Guest';
              $rating = (int)($r['rating'] ?? 0);
              if ($rating < 1 || $rating > 5) $rating = 0;
              $date = (string)($r['date_published'] ?? '');
              $body = trim((string)($r['body'] ?? ''));
              $stars = ($rating > 0) ? str_repeat('★', $rating) . str_repeat('☆', 5 - $rating) : '';
            ?>
            <article class="card" style="padding:16px;">
              <div style="display:flex; justify-content:space-between; gap:10px; align-items:flex-start;">
                <div>
                  <div style="font-weight:900; letter-spacing:.01em;"><?php echo e($author); ?></div>
                  <div class="small muted"><?php echo e($date ?: ''); ?></div>
                </div>
                <div class="small" aria-label="Rating" style="white-space:nowrap;"><?php echo e($stars); ?></div>
              </div>
              <p class="muted" style="margin:10px 0 0; white-space:pre-line;"><?php echo e($body); ?></p>
            </article>
          <?php endforeach; ?>
        </div>

        <style>
          @media (max-width: 980px){
            .reviews-grid{ grid-template-columns:repeat(2,1fr) !important; }
          }
          @media (max-width: 640px){
            .reviews-grid{ grid-template-columns:1fr !important; }
          }
        </style>
      <?php endif; ?>
    </div>
  </section>

  <!-- SECTION: Gallery preview -->
  <section class="section-pad section-muted section-gallery-preview" aria-label="<?php echo e(t('home.gallery.title')); ?>">
    <div class="container">
      <div class="section-head">
        <h2 class="section-heading"><?php echo e(t('home.gallery.title')); ?></h2>
        <p class="muted"><?php echo e(t('home.gallery.subtitle')); ?></p>
      </div>

      <div class="mini-gallery" data-lightbox>
        <?php foreach ($galleryPreview as $g): ?>
          <a href="/assets/images/gallery/<?php echo e($g['file']); ?>" aria-label="<?php echo e(t('ui.open_photo')); ?>">
            <img src="/assets/images/gallery/thumbs/<?php echo e($g['file']); ?>" alt="<?php echo e($g['alt']); ?>" loading="lazy" decoding="async">
          </a>
        <?php endforeach; ?>
      </div>

      <div class="center-actions" style="margin-top:18px;">
        <a class="btn" href="<?php echo e(i18n_url('apartment')); ?>#gallery"><?php echo e(t('home.gallery.cta_all_photos')); ?></a>
        <a class="btn primary" href="<?php echo e(i18n_url('booking')); ?>"><?php echo e(t('home.gallery.cta_book')); ?></a>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
