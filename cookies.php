<?php
require_once __DIR__ . '/lib/lib.php';
include __DIR__ . '/partials/header.php';

$store = load_store();
$siteName = (string)($store['settings']['site_name'] ?? 'Via Arriano 11');
$email = trim((string)($store['settings']['email'] ?? ''));
if ($email === '') {
  $email = trim((string)($store['settings']['notify_email'] ?? ''));
}
if ($email === '') $email = 'viaarriano11@gmail.com';

$lang = current_lang();
$isIt = ($lang === 'it');

$pageHero = [
  'kicker' => t('legal.cookies_pill'),
  'title' => t('legal.cookies_title'),
  'lead'  => t('legal.cookies_lead'),
  'image' => '/assets/images/gallery/outdoor_wide.jpg',
  'actions' => [
    ['href' => i18n_url('privacy'), 'label' => t('legal.cta_privacy'), 'class' => 'btn primary'],
    ['href' => i18n_url('contact'), 'label' => t('legal.cta_contact'), 'class' => 'btn ghost'],
  ],
];
include __DIR__ . '/partials/page-hero.php';

$updated = $isIt ? date('d/m/Y') : date('Y-m-d');
?>

<main class="site-main">
  <section class="section-pad section-muted">
    <div class="container">
      <div class="card legal" style="padding:18px; max-width:920px; margin:0 auto;">
        <p class="legal-meta"><strong><?php echo e($isIt ? 'Ultimo aggiornamento' : 'Last updated'); ?>:</strong> <?php echo e($updated); ?></p>

        <?php if ($isIt): ?>
          <p>
            Questa Cookie Policy descrive l’uso di cookie e tecnologie simili su <strong><?php echo e($siteName); ?></strong> e spiega come puoi gestire le tue preferenze.
          </p>

          <h2>1. Cosa sono cookie e tecnologie simili</h2>
          <p>
            I cookie sono piccoli file di testo che un sito può salvare sul tuo dispositivo durante la navigazione. Oltre ai cookie,
            possiamo utilizzare tecnologie analoghe (ad esempio <em>localStorage</em>) per memorizzare preferenze tecniche.
          </p>

          <h2>2. Cookie strettamente necessari (sempre attivi)</h2>
          <p>
            Utilizziamo cookie tecnici indispensabili per il funzionamento e la sicurezza del sito. Questi cookie non richiedono il tuo consenso
            perché sono necessari a fornire il servizio richiesto.
          </p>
          <ul>
            <li><strong>PHPSESSID</strong> (cookie di sessione) — <strong>Durata</strong>: sessione — <strong>Finalità</strong>: gestione della sessione, sicurezza e corretto funzionamento delle funzionalità (es. prenotazione, area admin).</li>
            <li><strong>via_cookie_consent</strong> — <strong>Durata</strong>: fino a 180 giorni — <strong>Finalità</strong>: memorizzare le tue preferenze sul consenso cookie.</li>
          </ul>
          <p>
            Alcune preferenze (come lingua e consenso) possono essere memorizzate anche tramite <em>localStorage</em> del browser. Non utilizziamo cookie di profilazione.
          </p>

          <h2>3. Cookie facoltativi (solo con consenso)</h2>
          <p>
            Eventuali cookie analitici (se presenti/attivati) vengono installati <strong>solo previo tuo consenso</strong> tramite il banner.
            Se non acconsenti, il sito resta pienamente utilizzabile.
          </p>
          <p>
            Se acconsenti ai cookie analitici, usiamo <strong>Google Analytics 4</strong> (Google Ireland Ltd.) per contare le visite in forma aggregata
            e capire quali pagine sono utili. Senza consenso Google Analytics non viene caricato.
          </p>
          <ul>
            <li><strong>_ga</strong> — <strong>Durata</strong>: fino a 2 anni — <strong>Finalità</strong>: distinguere i visitatori in forma statistica.</li>
            <li><strong>_ga_&lt;ID&gt;</strong> — <strong>Durata</strong>: fino a 2 anni — <strong>Finalità</strong>: mantenere lo stato della sessione di navigazione.</li>
          </ul>
          <p>
            Non usiamo Google Analytics per pubblicità o profilazione (segnali pubblicitari disattivati). Informativa di Google:
            <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">policies.google.com/privacy</a>.
          </p>

          <h2>4. Terze parti (pagamenti)</h2>
          <p>
            Per i pagamenti, il sito può reindirizzare a Stripe (fornitore di pagamenti). Durante la procedura di pagamento, Stripe può impostare cookie propri
            sul dominio Stripe per finalità di sicurezza e prevenzione frodi. Questi cookie sono gestiti direttamente da Stripe secondo le loro informative.
          </p>

          <h2>5. Come gestire o revocare il consenso</h2>
          <p>
            Puoi modificare o revocare il consenso in qualsiasi momento usando il link “<?php echo e(t('footer.manage_cookies')); ?>” nel footer.
            Puoi inoltre eliminare o bloccare i cookie dalle impostazioni del tuo browser.
          </p>

          <h2>6. Contatti</h2>
          <p>
            Per domande su cookie e privacy, puoi scriverci a <a href="mailto:<?php echo e($email); ?>"><?php echo e($email); ?></a>.
          </p>

        <?php else: ?>
          <p>
            This Cookie Policy explains the use of cookies and similar technologies on the <strong><?php echo e($siteName); ?></strong> website and how you can manage your preferences.
          </p>

          <h2>1. Cookies and similar technologies</h2>
          <p>
            Cookies are small text files that a website may store on your device while you browse. We may also use similar technologies
            (such as <em>localStorage</em>) to remember technical preferences.
          </p>

          <h2>2. Strictly necessary cookies (always on)</h2>
          <p>
            We use essential technical cookies that are necessary for the website to function and stay secure. These cookies do not require your consent
            because they are required to provide the service you request.
          </p>
          <ul>
            <li><strong>PHPSESSID</strong> (session cookie) — <strong>Duration</strong>: session — <strong>Purpose</strong>: session management, security and core functionality (e.g. booking flow, admin area).</li>
            <li><strong>via_cookie_consent</strong> — <strong>Duration</strong>: up to 180 days — <strong>Purpose</strong>: store your cookie consent preferences.</li>
          </ul>
          <p>
            Some preferences (such as language and consent) may also be stored using browser <em>localStorage</em>. We do not use profiling cookies.
          </p>

          <h2>3. Optional cookies (only with consent)</h2>
          <p>
            Any optional analytics cookies (if enabled) are set <strong>only with your consent</strong> via the banner.
            If you do not consent, the website remains fully usable.
          </p>
          <p>
            If you accept analytics cookies, we use <strong>Google Analytics 4</strong> (Google Ireland Ltd.) to count visits in aggregate form
            and understand which pages are useful. Without your consent Google Analytics is not loaded.
          </p>
          <ul>
            <li><strong>_ga</strong> — <strong>Duration</strong>: up to 2 years — <strong>Purpose</strong>: distinguish visitors for statistics.</li>
            <li><strong>_ga_&lt;ID&gt;</strong> — <strong>Duration</strong>: up to 2 years — <strong>Purpose</strong>: keep the browsing session state.</li>
          </ul>
          <p>
            We do not use Google Analytics for advertising or profiling (advertising signals disabled). Google's policy:
            <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">policies.google.com/privacy</a>.
          </p>

          <h2>4. Third parties (payments)</h2>
          <p>
            For payments, the website may redirect to Stripe (payment provider). During the checkout process, Stripe may set its own cookies
            on the Stripe domain for security and fraud prevention. These cookies are controlled by Stripe under their policies.
          </p>

          <h2>5. How to manage or withdraw consent</h2>
          <p>
            You can change or withdraw consent at any time using the “<?php echo e(t('footer.manage_cookies')); ?>” link in the footer.
            You can also delete or block cookies from your browser settings.
          </p>

          <h2>6. Contacts</h2>
          <p>
            For questions about cookies and privacy, contact us at <a href="mailto:<?php echo e($email); ?>"><?php echo e($email); ?></a>.
          </p>
<?php endif; ?>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
