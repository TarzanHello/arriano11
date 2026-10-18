<?php
// Usage:
// $pageHero = [
//   'title' => 'Titolo pagina',
//   'lead' => 'Testo breve',
//   'image' => '/assets/images/gallery/living_wide.jpg',
//   'kicker' => 'Via Arriano 11',
//   'actions' => [ ['href'=>'/booking.php','label'=>'Prenota','class'=>'btn primary'] ]
// ];

$store = load_store();
$siteName = $store['settings']['site_name'] ?? 'Via Arriano 11';

$title = (string)($pageHero['title'] ?? '');
$lead = (string)($pageHero['lead'] ?? '');
$image = (string)($pageHero['image'] ?? '/assets/images/gallery/living_wide.jpg');
$kicker = (string)($pageHero['kicker'] ?? $siteName);
$actions = $pageHero['actions'] ?? [];
if (!is_array($actions)) $actions = [];
?>

<section class="hero-cover hero-page">
  <div class="hero-media" aria-hidden="true">
    <img src="<?php echo e($image); ?>" alt="">
  </div>
  <div class="hero-overlay">
    <div class="container">
      <div class="hero-content">
        <div class="hero-kicker"><?php echo e($kicker); ?></div>
        <h1 class="hero-title"><?php echo e($title); ?></h1>
        <?php if ($lead): ?>
          <p class="hero-lead"><?php echo e($lead); ?></p>
        <?php endif; ?>

        <?php if (!empty($actions)): ?>
          <div class="hero-actions">
            <?php foreach ($actions as $a):
              if (!is_array($a)) continue;
              $href = (string)($a['href'] ?? '#');
              $label = (string)($a['label'] ?? '');
              $class = (string)($a['class'] ?? 'btn');
              if ($label === '') continue;
            ?>
              <a class="<?php echo e($class); ?>" href="<?php echo e($href); ?>"><?php echo e($label); ?></a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
