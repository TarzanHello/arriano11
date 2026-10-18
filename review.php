<?php
require_once __DIR__ . '/lib/lib.php';
require_once __DIR__ . '/lib/i18n.php';

// Reviews are guest-only and verified via a personal token.
// This page is intentionally NOINDEX (see partials/header.php) and not in the sitemap.

// Init i18n based on URL (/review or /en/review, ...)
i18n_init();

$store = load_store();
$siteName = (string)($store['settings']['site_name'] ?? 'Via Arriano 11');

$route = i18n_current_route(); // should be 'review'

$token = isset($_REQUEST['token']) ? normalize_text((string)$_REQUEST['token'], 120, false) : '';

// Basic token format check: 32 hex chars (16 bytes). We keep it permissive to allow future changes.
if ($token !== '' && !preg_match('/^[a-f0-9]{16,128}$/i', $token)) {
  $token = '';
}

// Helper: find booking by review token
function find_booking_by_review_token($store, $token) {
  if ($token === '' || !is_array($store)) return [-1, null];
  foreach (($store['bookings'] ?? []) as $i => $bk) {
    if (!is_array($bk)) continue;
    $bt = (string)($bk['review_token'] ?? '');
    if ($bt !== '' && hash_equals($bt, $token)) {
      return [(int)$i, $bk];
    }
  }
  return [-1, null];
}

// Helper: check if a review already exists for a booking id
function review_exists_for_booking($reviews, $bookingId) {
  foreach (($reviews ?? []) as $r) {
    if (!is_array($r)) continue;
    if ((string)($r['booking_id'] ?? '') === (string)$bookingId) return true;
  }
  return false;
}

$errKey = '';
$okKey = '';
$bookingIdx = -1;
$booking = null;

if ($token !== '') {
  [$bookingIdx, $booking] = find_booking_by_review_token($store, $token);
  if ($bookingIdx < 0 || !$booking) {
    $errKey = 'reviews.invalid_token';
  }
}

// POST submission
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $csrf = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';
  if (!csrf_verify($csrf)) {
    $errKey = 'reviews.invalid_token';
  } else {
    // Rate limit submissions (anti-spam)
    [$rlOk, $rlRetry] = rate_limit_hit('review_submit', client_ip(), 5, 30 * 60);
    if (!$rlOk) {
      $errKey = 'reviews.invalid_token';
    } else {
      // Re-load store to avoid race conditions
      $store = load_store();
      [$bookingIdx, $booking] = find_booking_by_review_token($store, $token);

      if ($bookingIdx < 0 || !$booking) {
        $errKey = 'reviews.invalid_token';
      } elseif (!booking_is_eligible_for_review($booking)) {
        $errKey = 'reviews.too_early';
      } elseif (!empty($booking['review_submitted_at'])) {
        $okKey = 'reviews.already_submitted';
      } else {
        $reviews = load_reviews();
        $bookingId = (string)($booking['id'] ?? '');
        if ($bookingId !== '' && review_exists_for_booking($reviews, $bookingId)) {
          $okKey = 'reviews.already_submitted';
        } else {
          $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
          if ($rating < 1 || $rating > 5) {
            $errKey = 'reviews.invalid_token';
          } else {
            $displayName = isset($_POST['display_name']) ? normalize_text((string)$_POST['display_name'], 80, false) : '';
            if ($displayName === '') {
              $displayName = review_default_display_name((string)($booking['name'] ?? ''));
            }
            $displayName = trim($displayName);
            if ($displayName === '') {
              $displayName = 'Guest';
            }

            $body = isset($_POST['review_body']) ? normalize_text((string)$_POST['review_body'], 800, true) : '';
            $body = trim($body);
            // Minimum length to reduce low-quality / accidental submissions
            if (function_exists('mb_strlen')) {
              if (mb_strlen($body, 'UTF-8') < 20) {
                $errKey = 'reviews.invalid_token';
              }
            } else {
              if (strlen($body) < 20) {
                $errKey = 'reviews.invalid_token';
              }
            }

            if (!$errKey) {
              $review = [
                'id' => new_id('rev_'),
                'booking_id' => $bookingId,
                'status' => 'pending',
                'verified' => true,
                'rating' => $rating,
                'author' => $displayName,
                'body' => $body,
                'lang' => booking_guest_lang($booking),
                'stay_start' => normalize_ymd((string)($booking['start'] ?? '')),
                'stay_end' => normalize_ymd((string)($booking['end'] ?? '')),
                'date_published' => date('Y-m-d'),
                'created_at' => time(),
              ];

              $reviews[] = $review;
              if (!save_reviews($reviews)) {
                $errKey = 'reviews.invalid_token';
              } else {
                // Mark booking as reviewed (one review per stay)
                $store['bookings'][$bookingIdx]['review_submitted_at'] = time();
                $store['bookings'][$bookingIdx]['review_id'] = $review['id'];
                save_store($store);

                $okKey = 'reviews.thanks_title';
              }
            }
          }
        }
      }
    }
  }
}

require __DIR__ . '/partials/header.php';

$csrf = csrf_token();

// Pre-fill display name suggestion from booking (privacy-friendly)
$prefillName = '';
if (is_array($booking)) {
  $prefillName = review_default_display_name((string)($booking['name'] ?? ''));
}

?>

<main class="site-main">
  <section class="section-pad section-muted">
    <div class="container">
      <div class="card" style="padding:18px; max-width:760px; margin:0 auto;">
        <h1 style="margin:0 0 8px; font-size:24px;"><?php echo e(t('reviews.page_title')); ?></h1>
        <p class="muted" style="margin:0 0 16px; max-width:80ch;"><?php echo e(t('reviews.page_lead')); ?></p>

        <?php if ($token === ''): ?>
          <div class="notice warn"><?php echo e(t('reviews.invalid_token')); ?></div>
        <?php elseif ($errKey): ?>
          <div class="notice danger"><?php echo e(t($errKey)); ?></div>
        <?php elseif ($okKey && $okKey === 'reviews.already_submitted'): ?>
          <div class="notice"><?php echo e(t('reviews.already_submitted')); ?></div>
          <div style="margin-top:12px;">
            <a class="btn" href="<?php echo e(i18n_url('home')); ?>"><?php echo e(t('booking_success.actions_home') ?: 'Home'); ?></a>
          </div>
        <?php elseif ($okKey && $okKey === 'reviews.thanks_title'): ?>
          <div class="notice" style="margin-bottom:12px;"><strong><?php echo e(t('reviews.thanks_title')); ?></strong><br><?php echo e(t('reviews.thanks_text')); ?></div>
          <div>
            <a class="btn primary" href="<?php echo e(i18n_url('home')); ?>"><?php echo e(t('booking_success.actions_home') ?: 'Home'); ?></a>
          </div>
        <?php else: ?>
          <?php
            // Eligibility checks for GET view
            if (is_array($booking) && !booking_is_eligible_for_review($booking)) {
              echo '<div class="notice warn">' . e(t('reviews.too_early')) . '</div>';
            } elseif (is_array($booking) && !empty($booking['review_submitted_at'])) {
              echo '<div class="notice">' . e(t('reviews.already_submitted')) . '</div>';
            } else {
          ?>
          <form method="post" action="<?php echo e(i18n_url('review')); ?>" style="margin-top:10px;">
            <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
            <input type="hidden" name="token" value="<?php echo e($token); ?>">

            <div class="form-row" style="grid-template-columns: 1fr 200px;">
              <div>
                <label for="display_name"><?php echo e(t('reviews.form_name')); ?></label>
                <input id="display_name" name="display_name" type="text" maxlength="80" value="<?php echo e($prefillName); ?>" autocomplete="name">
              </div>
              <div>
                <label for="rating"><?php echo e(t('reviews.form_rating')); ?></label>
                <select id="rating" name="rating" required>
                  <option value="5">5 / 5</option>
                  <option value="4">4 / 5</option>
                  <option value="3">3 / 5</option>
                  <option value="2">2 / 5</option>
                  <option value="1">1 / 5</option>
                </select>
              </div>
            </div>

            <div style="margin-top:10px;">
              <label for="review_body"><?php echo e(t('reviews.form_body')); ?></label>
              <textarea id="review_body" name="review_body" maxlength="800" required placeholder="(min 20 caratteri)"></textarea>
            </div>

            <div class="actions" style="margin-top:14px;">
              <button class="btn primary" type="submit"><?php echo e(t('reviews.form_submit')); ?></button>
              <a class="btn" href="<?php echo e(i18n_url('home')); ?>"><?php echo e(t('booking_success.actions_home') ?: 'Home'); ?></a>
            </div>
            <p class="small muted" style="margin:10px 0 0;"><?php echo e(t('reviews.pending_note')); ?></p>
          </form>
          <?php } ?>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
