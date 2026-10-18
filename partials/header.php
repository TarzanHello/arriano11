<?php
require_once __DIR__ . '/../lib/lib.php';
require_once __DIR__ . '/../lib/i18n.php';

$store = load_store();
$siteName = (string)($store['settings']['site_name'] ?? 'Via Arriano 11');
$address = (string)($store['settings']['address'] ?? '');
$phone = (string)($store['settings']['phone'] ?? '');

$email = (string)($store['settings']['email'] ?? '');
// Init i18n (language is detected from URL /?lang=.. from .htaccess)
i18n_init();
$lang = current_lang();
$route = i18n_current_route();
$bodyClass = 'page-' . preg_replace('/[^a-z0-9_]+/i', '-', $route);

function nav_active_route($want, $current) {
  return ($want === $current) ? 'is-active' : '';
}

// Meta tags
$metaKey = i18n_meta_key_for_route($route);
$metaTitle = t('meta.' . $metaKey . '.title');
$metaDesc = t('meta.' . $metaKey . '.description');

// If a translation key is missing, t() returns the key itself.
// Avoid leaking translation keys into SEO tags.
if (is_string($metaTitle) && strpos($metaTitle, 'meta.') === 0) $metaTitle = '';
if (is_string($metaDesc) && strpos($metaDesc, 'meta.') === 0) $metaDesc = '';

// SEO: put what people actually search first ("casa vacanze Pigneto…") and the brand
// at the end. Brand-first titles only rank for people who already know the name
// (typically guests who found the apartment on Booking).
$title = $siteName;
if ($metaTitle) {
  $title = $metaTitle . ' | ' . $siteName;
}

// Social share image (WhatsApp, Facebook, Telegram…): a real photo, not the logo.
$ogImage = rtrim(base_url(), '/') . '/assets/images/gallery/camera_da_letto_wide_1200.jpg';
$ogLocale = str_replace('-', '_', html_lang($lang));

$canonical = abs_url_for_route($route, $lang);
$hreflangs = hreflang_links_for_route($route);

// JS payload for client-side i18n (calendar, lightbox, auto language)
$urlsMap = urls_for_route($route);
$jsPayload = [
  'lang' => $lang,
  'locale' => current_locale(),
  'supported' => array_values(array_keys(i18n_supported_languages())),
  'fallback' => 'en',
  'prefixed' => is_lang_prefixed_request(),
  'urls' => $urlsMap,
  'strings' => [
    'ui' => i18n_get('ui'),
    'booking' => i18n_get('booking'),
  ],
];

$isNoIndex = in_array($route, ['booking_success', 'booking_cancel', 'review'], true);

// Security headers (CSP, HSTS, etc.)
send_security_headers([
  'admin' => false,
  'no_store' => $isNoIndex,
]);
?>
<!doctype html>
<html lang="<?php echo e(html_lang($lang)); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo e($title); ?></title>
  <?php if($metaDesc): ?>
    <meta name="description" content="<?php echo e($metaDesc); ?>">
  <?php endif; ?>
  <?php if($isNoIndex): ?>
    <meta name="robots" content="noindex, nofollow">
  <?php else: ?>
    <meta name="robots" content="index, follow, max-image-preview:large">
  <?php endif; ?>

  <link rel="canonical" href="<?php echo e($canonical); ?>">

  <?php foreach($hreflangs as $hl): ?>
    <link rel="alternate" hreflang="<?php echo e($hl['hreflang']); ?>" href="<?php echo e($hl['href']); ?>">
  <?php endforeach; ?>

  <meta property="og:title" content="<?php echo e($title); ?>">
  <?php if($metaDesc): ?>
    <meta property="og:description" content="<?php echo e($metaDesc); ?>">
  <?php endif; ?>
  <meta property="og:url" content="<?php echo e($canonical); ?>">
  <meta property="og:site_name" content="<?php echo e($siteName); ?>">
  <meta property="og:type" content="website">
  <meta property="og:locale" content="<?php echo e($ogLocale); ?>">
  <?php foreach (i18n_supported_languages() as $ogCode => $ogInfo): ?>
    <?php if ($ogCode !== $lang): ?>
      <meta property="og:locale:alternate" content="<?php echo e(str_replace('-', '_', (string)$ogInfo['hreflang'])); ?>">
    <?php endif; ?>
  <?php endforeach; ?>
  <meta property="og:image" content="<?php echo e($ogImage); ?>">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="675">
  <meta property="og:image:alt" content="<?php echo e($siteName . ' – Roma, Pigneto'); ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?php echo e($title); ?>">
  <?php if($metaDesc): ?>
    <meta name="twitter:description" content="<?php echo e($metaDesc); ?>">
  <?php endif; ?>
  <meta name="twitter:image" content="<?php echo e($ogImage); ?>">
<meta name="theme-color" content="#f97316">
  <link rel="icon" href="/favicon.ico">

  <?php if ($route === 'home'): ?>
    <!-- Preload LCP hero image -->
    <link rel="preload" as="image" href="/assets/images/gallery/camera_da_letto_wide_800.jpg" imagesrcset="/assets/images/gallery/camera_da_letto_wide_480.jpg 480w, /assets/images/gallery/camera_da_letto_wide_800.jpg 800w, /assets/images/gallery/camera_da_letto_wide_1200.jpg 1200w, /assets/images/gallery/camera_da_letto_wide.jpg 1600w" imagesizes="100vw">
  <?php endif; ?>
  <!-- Fonts: use system stack for performance (no render-blocking third-party requests) -->

  <?php $css_v = @filemtime(__DIR__ . '/../assets/css/style.css') ?: 1; ?>
  <link rel="stylesheet" href="/assets/css/style.css?v=<?php echo e((string)$css_v); ?>">

    <?php
    // Structured data:
    // - Full entity graph on the canonical entity page (Italian home)
    // - Lightweight WebPage → Entity link on other home language versions (keeps alternates aligned)
    if ($route === 'home'):
      $homeUrl = abs_url_for_route('home', 'it');
      $pageUrl = $canonical;

      // Collect available site languages (use hreflang codes)
      $siteLangs = [];
      foreach (i18n_supported_languages() as $c => $info) {
        if (!empty($info['hreflang'])) $siteLangs[] = (string)$info['hreflang'];
      }

      if ($lang === 'it'):
        // Extract address parts (fallback-safe)
        $streetAddress = 'Via Arriano 11';
        $postalCode = '00176';
        $locality = 'Roma';
        $region = 'RM';
        $country = 'IT';

        $rawAddr = (string)($store['settings']['address'] ?? '');
        $addrParts = array_values(array_filter(array_map('trim', explode(',', $rawAddr)), function($v){ return $v !== ''; }));
        if (count($addrParts) >= 1) $streetAddress = $addrParts[0];
        if (count($addrParts) >= 2) $postalCode = preg_replace('/[^0-9]/', '', $addrParts[1]) ?: $postalCode;
        if (count($addrParts) >= 3) {
          // Expect something like "Roma RM"
          $m = [];
          if (preg_match('/^(.+?)\s+([A-Z]{2})$/u', $addrParts[2], $m)) {
            $locality = trim($m[1]);
            $region = trim($m[2]);
          } else {
            $locality = trim($addrParts[2]);
          }
        }

        // Phone in E.164 (+39...)
        $telDigits = preg_replace('/\D+/', '', $phone);
        $telephone = '';
        if ($telDigits !== '') {
          if (strpos($telDigits, '39') === 0) {
            $telephone = '+' . $telDigits;
          } else {
            $telephone = '+39' . $telDigits;
          }
        }

        // Core entity description (kept short; main copy is in HTML).
        $entityDesc = 'Sito ufficiale di Via Arriano 11: alloggio turistico a Roma (Pigneto) con spazio esterno privato, Wi‑Fi e cucina attrezzata.';

        // Map link (helps Google reconcile the address entity)
        $hasMap = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($streetAddress . ', ' . $postalCode . ' ' . $locality . ' ' . $region . ', Italy');

        // JSON-LD graph: WebSite + WebPage + VacationRental (single entity, canonical URL)
        $graph = [
          '@context' => 'https://schema.org',
          '@graph' => [
            [
              '@type' => 'WebSite',
              '@id' => $homeUrl . '#website',
              'name' => $siteName,
              'url' => $homeUrl,
              'inLanguage' => $siteLangs,
            ],
            [
              '@type' => 'WebPage',
              '@id' => $homeUrl . '#webpage',
              'url' => $homeUrl,
              'name' => $title,
              'isPartOf' => ['@id' => $homeUrl . '#website'],
              // Connect the page to the entity (address-based query)
              'about' => ['@id' => $homeUrl . '#vacationrental'],
              'mainEntity' => ['@id' => $homeUrl . '#vacationrental'],
              'inLanguage' => html_lang($lang),
            ],
            [
              '@type' => ['VacationRental', 'LodgingBusiness'],
              '@id' => $homeUrl . '#vacationrental',
              'name' => $siteName,
              // Useful alternate name used by some OTA listings
              'alternateName' => ['Casa Arriano 11 al Pigneto'],
              // Recommended category for the listing (Google VacationRental)
              // Suggested values: Apartment, House, Villa, ...
              'additionalType' => 'Apartment',
              'description' => $entityDesc,
              'url' => $homeUrl,
              'mainEntityOfPage' => ['@id' => $homeUrl . '#webpage'],
              'isPartOf' => ['@id' => $homeUrl . '#website'],
              'image' => [
                rtrim(base_url(), '/') . '/assets/images/gallery/camera_da_letto_wide.jpg',
                rtrim(base_url(), '/') . '/assets/images/gallery/camera_da_letto_2.jpg',
                rtrim(base_url(), '/') . '/assets/images/gallery/living_wide.jpg',
                rtrim(base_url(), '/') . '/assets/images/gallery/living_1.jpg',
                rtrim(base_url(), '/') . '/assets/images/gallery/giardino_1.jpg',
                rtrim(base_url(), '/') . '/assets/images/gallery/giardino_2.jpg',
                rtrim(base_url(), '/') . '/assets/images/gallery/bagno_2.jpg',
                rtrim(base_url(), '/') . '/assets/images/gallery/bagno_3.jpg',
                rtrim(base_url(), '/') . '/assets/images/gallery/cucina_top.jpg',
              ],
              'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $streetAddress,
                'postalCode' => $postalCode,
                'addressLocality' => $locality,
                'addressRegion' => $region,
                'addressCountry' => $country,
              ],
              'geo' => [
                '@type' => 'GeoCoordinates',
                // Coordinates from the official registry (Turismo Roma)
                'latitude' => 41.88541438,
                'longitude' => 12.53654213,
              ],
              'hasMap' => $hasMap,
              'telephone' => $telephone,
              'email' => $email,
              'checkinTime' => (string)($store['settings']['check_in_time'] ?? '15:00'),
              'checkoutTime' => (string)($store['settings']['check_out_time'] ?? '11:00'),
              // Required by Google VacationRental: unique identifier must be Text
              // Keep it stable and identical across language versions.
              'identifier' => '6603',

              // Required by Google VacationRental: containsPlace + containsPlace.occupancy
              // Contains one Accommodation describing the unit (beds/amenities/occupancy).
              'containsPlace' => [
                '@type' => 'Accommodation',
                'additionalType' => 'EntirePlace',
                // Optional (but recommended): room & bed details
                'numberOfBedrooms' => 1,
                'numberOfBathroomsTotal' => 1,
                'bed' => [
                  // 1 king-size double bed + 1 sofa bed
                  ['@type' => 'BedDetails', 'numberOfBeds' => 1, 'typeOfBed' => 'King'],
                  ['@type' => 'BedDetails', 'numberOfBeds' => 1, 'typeOfBed' => 'Sofa bed'],
                ],
                'occupancy' => [
                  '@type' => 'QuantitativeValue',
                  'value' => max_guests_allowed($store),
                ],
                // For vacation rentals, Google expects amenityFeature.name values in English.
                // Only include values we are sure about.
                'amenityFeature' => [
                  ['@type' => 'LocationFeatureSpecification', 'name' => 'wifi', 'value' => true],
                  ['@type' => 'LocationFeatureSpecification', 'name' => 'internetType', 'value' => 'Free'],
                  ['@type' => 'LocationFeatureSpecification', 'name' => 'ac', 'value' => true],
                  ['@type' => 'LocationFeatureSpecification', 'name' => 'kitchen', 'value' => true],
                  // Outdoor private space (best match in Google list: patio)
                  ['@type' => 'LocationFeatureSpecification', 'name' => 'patio', 'value' => true],
                  // License number (recommended field for some regions)
                  ['@type' => 'LocationFeatureSpecification', 'name' => 'licenseNum', 'value' => 'Rome: 6603'],
                ],
              ],
              'sameAs' => [
                // OTA listings (helps Google connect the entity across sources)
                'https://www.booking.com/hotel/it/via-arriano-11.it.html',
                'https://www.agoda.com/via-arriano-11/hotel/rome-it.html',
                // Sometimes duplicated on Agoda under a slightly different name
                'https://www.agoda.com/casa-arriano-11-al-pigneto/hotel/rome-it.html',
                // Official registry entry
                'https://www.turismoroma.it/it/accoglienza/alloggio-uso-turistico-6603-0',
              ],
            ],
          ],
        ];

        // Optional: add verified reviews + aggregate rating (only if we have approved reviews).
        // IMPORTANT: any Review marked up here MUST also be visible in the HTML.
        $allReviews = load_reviews();
        $rStats = reviews_stats($allReviews);
        $rCount = (int)($rStats['count'] ?? 0);
        if ($rCount > 0):
          $rAvg = (float)($rStats['avg'] ?? 0);
          $approvedReviews = reviews_approved($allReviews);
          usort($approvedReviews, function($a, $b) {
            return (int)($b['created_at'] ?? 0) <=> (int)($a['created_at'] ?? 0);
          });

          // Aggregate rating across ALL approved reviews
          $graph['@graph'][2]['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => round($rAvg, 1),
            'bestRating' => 5,
            'worstRating' => 1,
            'ratingCount' => $rCount,
            'reviewCount' => $rCount,
          ];

          // Include a few latest reviews (keeps JSON-LD small)
          $reviewItems = [];
          foreach (array_slice($approvedReviews, 0, 5) as $r) {
            if (!is_array($r)) continue;
            $rating = (int)($r['rating'] ?? 0);
            if ($rating < 1 || $rating > 5) continue;
            $author = trim((string)($r['author'] ?? '')) ?: 'Guest';
            $datePublished = normalize_ymd((string)($r['date_published'] ?? '')) ?: date('Y-m-d');
            $body = trim((string)($r['body'] ?? ''));
            $stay = normalize_ymd((string)($r['stay_start'] ?? ''));

            $item = [
              '@type' => 'Review',
              'reviewRating' => [
                '@type' => 'Rating',
                'ratingValue' => $rating,
                'bestRating' => 5,
                'worstRating' => 1,
              ],
              'author' => ['@type' => 'Person', 'name' => $author],
              'datePublished' => $datePublished,
              'reviewBody' => $body,
            ];
            if ($stay) {
              // Date of stay (helps Google understand this is a real guest review)
              $item['contentReferenceTime'] = $stay;
            }
            $reviewItems[] = $item;
          }
          if ($reviewItems) {
            $graph['@graph'][2]['review'] = $reviewItems;
          }
        endif;
      else:
        // Minimal graph: declare the language WebPage and link it to the canonical entity @id.
        $graph = [
          '@context' => 'https://schema.org',
          '@graph' => [
            [
              '@type' => 'WebPage',
              '@id' => $pageUrl . '#webpage',
              'url' => $pageUrl,
              'name' => $title,
              'inLanguage' => html_lang($lang),
              'isPartOf' => ['@id' => $homeUrl . '#website'],
              'about' => ['@id' => $homeUrl . '#vacationrental'],
              'mainEntity' => ['@id' => $homeUrl . '#vacationrental'],
            ],
          ],
        ];
      endif;
?>
    <script type="application/ld+json"><?php echo json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
  <?php endif; ?>

  <script nonce="<?php echo e(csp_nonce()); ?>">
    window.__VIA_I18N = <?php echo json_encode($jsPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
  </script>
  <?php $i18n_js_v = @filemtime(__DIR__ . '/../assets/js/i18n.js') ?: 1; ?>
  <script src="/assets/js/i18n.js?v=<?php echo e((string)$i18n_js_v); ?>" defer></script>
</head>
<body class="<?php echo e($bodyClass); ?>">
  <header class="site-header">
    <div class="container header-inner">
      <a class="brand" href="<?php echo e(i18n_url('home')); ?>" aria-label="<?php echo e(t('nav.home')); ?>">
        <img src="/assets/brand/logo-44.png" srcset="/assets/brand/logo-44.png 1x, /assets/brand/logo-88.png 2x" alt="Logo <?php echo e($siteName); ?>" width="44" height="44" decoding="async">
        <div class="brand-text">
          <div class="brand-title"><?php echo e($siteName); ?></div>
          <div class="brand-sub"><?php echo e(t('nav.brand_sub')); ?></div>
        </div>
      </a>

      <nav class="nav" id="siteNav">
        <a class="<?php echo nav_active_route('home', $route); ?>" href="<?php echo e(i18n_url('home')); ?>"><?php echo e(t('nav.home')); ?></a>
        <a class="<?php echo nav_active_route('apartment', $route); ?>" href="<?php echo e(i18n_url('apartment')); ?>"><?php echo e(t('nav.apartment')); ?></a>
        <a class="<?php echo nav_active_route('neighborhood', $route); ?>" href="<?php echo e(i18n_url('neighborhood')); ?>"><?php echo e(t('nav.neighborhood')); ?></a>
        <a class="<?php echo nav_active_route('tips', $route); ?>" href="<?php echo e(i18n_url('tips')); ?>"><?php echo e(t('nav.tips')); ?></a>
        <a class="<?php echo nav_active_route('rules', $route); ?>" href="<?php echo e(i18n_url('rules')); ?>"><?php echo e(t('nav.rules')); ?></a>
        <a class="<?php echo nav_active_route('contact', $route); ?>" href="<?php echo e(i18n_url('contact')); ?>"><?php echo e(t('nav.contact')); ?></a>
      </nav>

      <div class="header-actions">
        <button id="navToggle" class="nav-toggle" type="button" aria-label="<?php echo e(t('ui.open_menu')); ?>">☰</button>

        <div class="lang-switch" data-lang-switch>
          <button class="lang-btn" type="button" data-lang-btn aria-haspopup="listbox" aria-expanded="false" aria-label="<?php echo e(t('ui.language')); ?>">
            <img class="lang-flag" src="/assets/flags/<?php echo e(flag_code($lang)); ?>.svg" alt="<?php echo e(lang_label($lang)); ?>" width="22" height="16">
            <span class="lang-caret" aria-hidden="true">▾</span>
          </button>
          <div class="lang-menu" data-lang-menu role="listbox" aria-label="<?php echo e(t('ui.language')); ?>">
            <?php foreach(i18n_supported_languages() as $code => $info): ?>
              <a class="lang-item <?php echo ($code === $lang) ? 'is-active' : ''; ?>" href="<?php echo e(i18n_url($route, $code)); ?>" data-lang="<?php echo e($code); ?>" role="option" aria-selected="<?php echo ($code === $lang) ? 'true' : 'false'; ?>">
                <img src="/assets/flags/<?php echo e($info['flag']); ?>.svg" alt="" width="22" height="16">
                <span><?php echo e($info['label']); ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>

        <a class="cta" href="<?php echo e(i18n_url('booking')); ?>"><?php echo e(t('nav.book')); ?></a>
      </div>
    </div>
  </header>
