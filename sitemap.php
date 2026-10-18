<?php
// Dynamic sitemap (keeps in sync with enabled languages + public routes)
// Oct 2026: real <lastmod> (file modification date instead of "today" on every request,
// which Google learns to ignore) + image entries so the photos can rank in Google Images.
require_once __DIR__ . '/lib/lib.php';
require_once __DIR__ . '/lib/i18n.php';

// No HTML: explicit content type
header('Content-Type: application/xml; charset=UTF-8');

// route => page file
$routes = [
  'home' => 'index.php',
  'apartment' => 'apartment.php',
  'neighborhood' => 'neighborhood.php',
  'tips' => 'tips.php',
  'rules' => 'rules.php',
  'contact' => 'contact.php',
  'booking' => 'booking.php',
  'privacy' => 'privacy.php',
  'cookies' => 'cookies.php',
  'policlinico' => 'policlinico.php',
];

$langs = i18n_supported_languages();
$base = rtrim(base_url(), '/');

// Last change of the translations (texts live there)
$i18nMtime = 0;
foreach (glob(__DIR__ . '/i18n/*.php') ?: [] as $f) {
  $i18nMtime = max($i18nMtime, (int)@filemtime($f));
}
$headerMtime = (int)@filemtime(__DIR__ . '/partials/header.php');

// Photos: all large gallery images (not the resized variants / thumbs)
$images = [];
foreach (glob(__DIR__ . '/assets/images/gallery/*.jpg') ?: [] as $f) {
  $name = basename($f);
  if (preg_match('/_(480|800|1200)\.jpg$/', $name)) continue;
  $images[] = '/assets/images/gallery/' . $name;
}
sort($images);
$homeImages = [
  '/assets/images/gallery/camera_da_letto_wide.jpg',
  '/assets/images/gallery/living_wide.jpg',
  '/assets/images/gallery/giardino_1.jpg',
  '/assets/images/gallery/giardino_2.jpg',
  '/assets/images/gallery/living_1.jpg',
];

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

foreach ($routes as $route => $file) {
  $mtime = max((int)@filemtime(__DIR__ . '/' . $file), $i18nMtime, $headerMtime);
  $lastmod = $mtime > 0 ? date('Y-m-d', $mtime) : date('Y-m-d');

  $imgs = [];
  if ($route === 'home') $imgs = $homeImages;
  if ($route === 'apartment') $imgs = $images;

  // One <url> per language version, each listing all alternates (Google's recommended format)
  $routeLangs = i18n_route_languages($route);
  foreach ($langs as $code => $info) {
    if (!in_array($code, $routeLangs, true)) continue;
    $loc = $base . i18n_url($route, $code);
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($loc, ENT_QUOTES, 'UTF-8') . "</loc>\n";
    foreach ($langs as $code2 => $info2) {
      if (!in_array($code2, $routeLangs, true)) continue;
      $href = $base . i18n_url($route, $code2);
      $hreflang = (string)($info2['hreflang'] ?? $code2);
      echo '    <xhtml:link rel="alternate" hreflang="' . htmlspecialchars($hreflang, ENT_QUOTES, 'UTF-8') . '" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" />' . "\n";
    }
    $xDefault = $base . i18n_url($route, 'it');
    echo '    <xhtml:link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($xDefault, ENT_QUOTES, 'UTF-8') . '" />' . "\n";
    echo '    <lastmod>' . $lastmod . "</lastmod>\n";
    // Images only on the Italian (canonical entity) version to avoid 14 duplicates
    if ($code === 'it') {
      foreach ($imgs as $img) {
        echo "    <image:image><image:loc>" . htmlspecialchars($base . $img, ENT_QUOTES, 'UTF-8') . "</image:loc></image:image>\n";
      }
    }
    echo "  </url>\n";
  }
}

echo "</urlset>\n";
