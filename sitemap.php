<?php
// Dynamic sitemap (keeps in sync with enabled languages + public routes)
require_once __DIR__ . '/lib/lib.php';
require_once __DIR__ . '/lib/i18n.php';

// No HTML: explicit content type
header('Content-Type: application/xml; charset=UTF-8');

$routes = [
  'home',
  'apartment',
  'neighborhood',
  'tips',
  'rules',
  'contact',
  'booking',
  'privacy',
  'cookies',
];

$langs = i18n_supported_languages();
$base = rtrim(base_url(), '/');
$today = date('Y-m-d');

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

foreach ($routes as $route) {
  // Use the default language as the primary <loc>
  $loc = $base . i18n_url($route, 'it');
  echo "  <url>\n";
  echo '    <loc>' . htmlspecialchars($loc, ENT_QUOTES, 'UTF-8') . "</loc>\n";

  foreach ($langs as $code => $info) {
    $href = $base . i18n_url($route, $code);
    $hreflang = (string)($info['hreflang'] ?? $code);
    echo '    <xhtml:link rel="alternate" hreflang="' . htmlspecialchars($hreflang, ENT_QUOTES, 'UTF-8') . '" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" />' . "\n";
  }

  // x-default points to the default language (Italian)
  $xDefault = $base . i18n_url($route, 'it');
  echo '    <xhtml:link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($xDefault, ENT_QUOTES, 'UTF-8') . '" />' . "\n";

  echo '    <lastmod>' . $today . "</lastmod>\n";
  echo "  </url>\n";
}

echo "</urlset>\n";
