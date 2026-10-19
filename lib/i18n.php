<?php
// Simple i18n layer + SEO-friendly routing helpers.
// Supported languages: it (default), en (UK), fr, es, ru, ro, pt, pl, ko, zh, ja, de, se (Swedish), dk (Danish)

if (!function_exists('base_url')) {
  require_once __DIR__ . '/lib.php';
}
require_once __DIR__ . '/articles.php';
require_once __DIR__ . '/itineraries.php';

$GLOBALS['_VIA_LANG'] = $GLOBALS['_VIA_LANG'] ?? 'it';
$GLOBALS['_VIA_I18N'] = $GLOBALS['_VIA_I18N'] ?? [];

function i18n_supported_languages() {
  // NOTE: labels are UTF-8.
  // Path prefixes are the array keys (e.g. /en, /fr, /se, ...).
  // hreflang follows standards (e.g. Swedish uses sv-SE even if path is /se).
  return [
    'it' => ['label' => 'Italiano',      'hreflang' => 'it-IT', 'locale' => 'it-IT', 'flag' => 'it'],
    'en' => ['label' => 'English (UK)',  'hreflang' => 'en-GB', 'locale' => 'en-GB', 'flag' => 'gb'],
    'fr' => ['label' => 'Français',      'hreflang' => 'fr-FR', 'locale' => 'fr-FR', 'flag' => 'fr'],
    'es' => ['label' => 'Español',       'hreflang' => 'es-ES', 'locale' => 'es-ES', 'flag' => 'es'],
    'ru' => ['label' => 'Русский',       'hreflang' => 'ru-RU', 'locale' => 'ru-RU', 'flag' => 'ru'],
    'ro' => ['label' => 'Română',        'hreflang' => 'ro-RO', 'locale' => 'ro-RO', 'flag' => 'ro'],
    'pt' => ['label' => 'Português',     'hreflang' => 'pt-PT', 'locale' => 'pt-PT', 'flag' => 'pt'],
    'pl' => ['label' => 'Polski',        'hreflang' => 'pl-PL', 'locale' => 'pl-PL', 'flag' => 'pl'],
    'ko' => ['label' => '한국어',        'hreflang' => 'ko-KR', 'locale' => 'ko-KR', 'flag' => 'kr'],
    'zh' => ['label' => '中文（简体）', 'hreflang' => 'zh-CN', 'locale' => 'zh-CN', 'flag' => 'cn'],
    'ja' => ['label' => '日本語',        'hreflang' => 'ja-JP', 'locale' => 'ja-JP', 'flag' => 'jp'],

    'de' => ['label' => 'Deutsch',       'hreflang' => 'de-DE', 'locale' => 'de-DE', 'flag' => 'de'],
    'se' => ['label' => 'Svenska',       'hreflang' => 'sv-SE', 'locale' => 'sv-SE', 'flag' => 'se'],
    'dk' => ['label' => 'Dansk',         'hreflang' => 'da-DK', 'locale' => 'da-DK', 'flag' => 'dk'],
  ];
}


function i18n_request_path() {
  $uri = $_SERVER['REQUEST_URI'] ?? '/';
  return (string)(parse_url($uri, PHP_URL_PATH) ?? '/');
}

function i18n_lang_from_path() {
  $path = trim(i18n_request_path(), '/');
  if ($path === '') return null;
  $first = explode('/', $path, 2)[0];
  $first = strtolower($first);
  $langs = i18n_supported_languages();
  if (isset($langs[$first])) return $first;
  return null;
}

function is_lang_prefixed_request() {
  $p = i18n_lang_from_path();
  return ($p !== null && $p !== 'it');
}

function i18n_normalize_lang($lang) {
  $lang = strtolower(trim((string)$lang));
  if ($lang === '') return '';
  // Accept legacy codes if any
  if ($lang === 'cn') return 'zh';
  if ($lang === 'jp') return 'ja';
  if ($lang === 'kr') return 'ko';
  // Swedish is usually "sv"; this project uses the /se prefix.
  if ($lang === 'sv') return 'se';
  return $lang;
}

function i18n_init($forcedLang = null) {
  $langs = i18n_supported_languages();

  $lang = '';
  if ($forcedLang !== null) {
    $lang = i18n_normalize_lang($forcedLang);
  }
  if ($lang === '' && isset($_GET['lang'])) {
    $lang = i18n_normalize_lang($_GET['lang']);
  }
  if ($lang === '') {
    $fromPath = i18n_lang_from_path();
    if ($fromPath) $lang = $fromPath;
  }
  if ($lang === '' || !isset($langs[$lang])) {
    $lang = 'it';
  }

  $file = __DIR__ . '/../i18n/' . $lang . '.php';
  if (!file_exists($file)) {
    $file = __DIR__ . '/../i18n/en.php';
    $lang = 'en';
  }

  $dict = require $file;
  if (!is_array($dict)) $dict = [];

  // Optional extra translations (kept separate to reduce risk when adding new pages/features).
  // Location: /i18n/_extra/{lang}.php
  // Behavior:
  // - Always load the English extra file as a fallback base (if present)
  // - Then override with the language-specific extra file (if present)
  // This prevents missing-key leaks when a language has an _extra file but it doesn't include
  // the newest keys.
  $extra = [];
  $fallbackExtra = __DIR__ . '/../i18n/_extra/en.php';
  if (file_exists($fallbackExtra)) {
    $baseExtra = require $fallbackExtra;
    if (is_array($baseExtra)) $extra = $baseExtra;
  }

  $extraFile = __DIR__ . '/../i18n/_extra/' . $lang . '.php';
  if ($lang !== 'en' && file_exists($extraFile)) {
    $langExtra = require $extraFile;
    if (is_array($langExtra) && !empty($langExtra)) {
      $extra = i18n_deep_merge($extra, $langExtra);
    }
  }

  if (is_array($extra) && !empty($extra)) {
    $dict = i18n_deep_merge($dict, $extra);
  }

  $GLOBALS['_VIA_LANG'] = $lang;
  $GLOBALS['_VIA_I18N'] = $dict;
}

// Deep merge (distinct) for translation arrays.
// - Nested arrays are merged recursively
// - Scalar values from $overrides replace $base
function i18n_deep_merge($base, $overrides) {
  if (!is_array($base)) $base = [];
  if (!is_array($overrides)) return $base;

  foreach ($overrides as $k => $v) {
    if (is_array($v) && isset($base[$k]) && is_array($base[$k])) {
      $base[$k] = i18n_deep_merge($base[$k], $v);
    } else {
      $base[$k] = $v;
    }
  }
  return $base;
}

function current_lang() {
  return (string)($GLOBALS['_VIA_LANG'] ?? 'it');
}

function current_locale() {
  $lang = current_lang();
  $langs = i18n_supported_languages();
  return (string)($langs[$lang]['locale'] ?? 'en-GB');
}

function html_lang($lang) {
  $lang = i18n_normalize_lang($lang);
  $langs = i18n_supported_languages();
  return (string)($langs[$lang]['hreflang'] ?? 'en');
}

function flag_code($lang) {
  $lang = i18n_normalize_lang($lang);
  $langs = i18n_supported_languages();
  return (string)($langs[$lang]['flag'] ?? 'gb');
}

function lang_label($lang) {
  $lang = i18n_normalize_lang($lang);
  $langs = i18n_supported_languages();
  return (string)($langs[$lang]['label'] ?? $lang);
}

function i18n_get($path = '') {
  $dict = $GLOBALS['_VIA_I18N'] ?? [];
  if ($path === '' || $path === null) return $dict;
  $parts = explode('.', (string)$path);
  $cur = $dict;
  foreach ($parts as $p) {
    if (!is_array($cur) || !array_key_exists($p, $cur)) return null;
    $cur = $cur[$p];
  }
  return $cur;
}

function t($key, $vars = []) {
  $val = i18n_get($key);
  if ($val === null) return $key;
  if (!is_string($val)) return $key;

  if (is_array($vars) && !empty($vars)) {
    foreach ($vars as $k => $v) {
      $val = str_replace('{' . $k . '}', (string)$v, $val);
    }
  }
  return $val;
}

function i18n_meta_key_for_route($route) {
  $route = (string)$route;
  if ($route === '') return 'home';
  if ($route === 'home') return 'home';
  if ($route === 'booking_success') return 'booking_success';
  if ($route === 'booking_cancel') return 'booking_cancel';
  return $route;
}

function i18n_route_from_request() {
  $path = trim(i18n_request_path(), '/');
  if ($path === '') return 'home';

  // Strip language prefix
  $parts = explode('/', $path);
  $langs = i18n_supported_languages();
  if (isset($langs[strtolower($parts[0])])) {
    array_shift($parts);
  }

  $slug = implode('/', $parts);
  $slug = trim($slug, '/');
  $slug = preg_replace('/\.php$/', '', $slug);

  if ($slug === '' || $slug === 'index') return 'home';

  if ($slug === 'booking-success') return 'booking_success';
  if ($slug === 'booking-cancel') return 'booking_cancel';
  if ($slug === 'policlinico-sapienza' || $slug === 'policlinico') return 'policlinico';
  if ($slug === 'guide') return 'guides';
  $itinRoute = itin_route_for_slug($slug);
  if ($itinRoute !== '') return $itinRoute;
  if (strpos($slug, 'guide/') === 0) {
    $art = article_route_for_slug(substr($slug, 6));
    if ($art !== '') return $art;
    return 'guides';
  }

  $known = ['booking','apartment','neighborhood','tips','rules','contact','privacy','cookies','review'];
  if (in_array($slug, $known, true)) return $slug;

  // Default to home for unknown slugs
  return 'home';
}

function i18n_current_route() {
  return i18n_route_from_request();
}

function i18n_slug_for_route($route, $lang = null) {
  $route = (string)$route;
  if ($route === '' || $route === 'home') return '';
  if ($route === 'booking_success') return 'booking-success';
  if ($route === 'booking_cancel') return 'booking-cancel';
  if ($route === 'policlinico') return 'policlinico-sapienza';
  if ($route === 'guides') return 'guide';
  if (is_itin_route($route)) return itin_slug($route, $lang === null ? current_lang() : $lang);
  if (is_article_route($route)) {
    return 'guide/' . article_slug($route, $lang === null ? current_lang() : $lang);
  }
  return $route;
}

// Languages in which a route exists. Most pages exist in every language;
// some local-audience pages only in Italian + English (others are sent to English).
function i18n_route_languages($route) {
  $all = array_keys(i18n_supported_languages());
  if ((string)$route === 'policlinico') return ['it', 'en'];
  if (is_guides_route($route)) return VIA_ARTICLE_LANGS;
  if (is_itin_route($route)) return itin_languages();
  return $all;
}

// Language actually used for $route when the visitor asks for $lang.
function i18n_route_lang($route, $lang) {
  $langs = i18n_route_languages($route);
  return in_array($lang, $langs, true) ? $lang : (in_array('en', $langs, true) ? 'en' : $langs[0]);
}

function i18n_url($route, $lang = null) {
  $lang = ($lang === null) ? current_lang() : i18n_normalize_lang($lang);
  $langs = i18n_supported_languages();
  if (!isset($langs[$lang])) $lang = 'it';

  $slug = i18n_slug_for_route($route, $lang);
  if ($lang === 'it') {
    return ($slug === '') ? '/' : '/' . $slug;
  }
  return ($slug === '') ? '/' . $lang . '/' : '/' . $lang . '/' . $slug;
}

function abs_url_for_route($route, $lang = null) {
  return rtrim(base_url(), '/') . i18n_url($route, $lang);
}

function urls_for_route($route) {
  $out = [];
  foreach (i18n_supported_languages() as $code => $info) {
    $out[$code] = i18n_url($route, i18n_route_lang($route, $code));
  }
  return $out;
}

function hreflang_links_for_route($route) {
  $links = [];
  $routeLangs = i18n_route_languages($route);
  foreach (i18n_supported_languages() as $code => $info) {
    if (!in_array($code, $routeLangs, true)) continue;
    $links[] = [
      'hreflang' => $info['hreflang'],
      'href' => abs_url_for_route($route, $code),
    ];
  }
  // x-default -> site default language
  $links[] = [
    'hreflang' => 'x-default',
    'href' => abs_url_for_route($route, 'it'),
  ];
  return $links;
}

// ------------------------------------------------------------
// Page copy v2 (Oct 2026): richer texts written per language in /i18n/copy/{lang}.php.
// Returns null when the current language has no specific text, so templates can fall
// back to the previous translation instead of mixing languages.
// ------------------------------------------------------------
function copy_get($key, $vars = []) {
  static $cache = [];
  $lang = current_lang();
  if (!array_key_exists($lang, $cache)) {
    $f = __DIR__ . '/../i18n/copy/' . preg_replace('/[^a-z]/', '', $lang) . '.php';
    $data = file_exists($f) ? (require $f) : [];
    $cache[$lang] = is_array($data) ? $data : [];
  }
  $cur = $cache[$lang];
  foreach (explode('.', (string)$key) as $part) {
    if (!is_array($cur) || !array_key_exists($part, $cur)) return null;
    $cur = $cur[$part];
  }
  if ($vars) $cur = copy_fill($cur, $vars);
  return $cur;
}

function copy_fill($value, $vars) {
  if (is_array($value)) {
    foreach ($value as $k => $v) $value[$k] = copy_fill($v, $vars);
    return $value;
  }
  if (!is_string($value)) return $value;
  foreach ($vars as $k => $v) $value = str_replace('{' . $k . '}', (string)$v, $value);
  return $value;
}

// Real values from the settings, used inside FAQ answers.
function copy_vars($store) {
  $s = is_array($store) ? ($store['settings'] ?? []) : [];
  $tax = (float)($s['city_tax_per_person_per_night'] ?? 6);
  return [
    'min_nights' => (int)($s['min_nights'] ?? 3),
    'checkin' => (string)($s['check_in_time'] ?? '15:00'),
    'checkout' => (string)($s['check_out_time'] ?? '11:00'),
    'tax' => (fmod($tax, 1.0) == 0.0) ? (string)(int)$tax : number_format($tax, 2, ',', ''),
    'tax_max' => (int)($s['tourist_tax_max_nights'] ?? 10),
  ];
}

