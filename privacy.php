<?php
require_once __DIR__ . '/lib/lib.php';
include __DIR__ . '/partials/header.php';

$store = load_store();
$siteName = (string)($store['settings']['site_name'] ?? 'Via Arriano 11');
$address = (string)($store['settings']['address'] ?? '');
$phone = (string)($store['settings']['phone'] ?? '');
$email = trim((string)($store['settings']['email'] ?? ''));
if ($email === '') {
  $email = trim((string)($store['settings']['notify_email'] ?? ''));
}
if ($email === '') $email = 'viaarriano11@gmail.com';

$lang = current_lang();
$isIt = ($lang === 'it');

$pageHero = [
  'kicker' => t('legal.privacy_pill'),
  'title' => t('legal.privacy_title'),
  'lead'  => t('legal.privacy_lead'),
  'image' => '/assets/images/gallery/living_wide.jpg',
  'actions' => [
    ['href' => i18n_url('contact'), 'label' => t('legal.cta_contact'), 'class' => 'btn primary'],
    ['href' => i18n_url('cookies'), 'label' => t('legal.cta_cookies'), 'class' => 'btn ghost'],
  ],
];
include __DIR__ . '/partials/page-hero.php';

$updated = $isIt ? '18/10/2026' : '2026-10-18'; // update when the text changes
$ownerLine = t('footer.owner');
?>

<main class="site-main">
  <section class="section-pad section-muted">
    <div class="container">
      <div class="card legal" style="padding:18px; max-width:920px; margin:0 auto;">
        <p class="legal-meta"><strong><?php echo e($isIt ? 'Ultimo aggiornamento' : 'Last updated'); ?>:</strong> <?php echo e($updated); ?></p>

        <?php if ($isIt): ?>
          <p>
            La presente informativa descrive come vengono trattati i dati personali degli utenti che visitano il sito
            <strong><?php echo e($siteName); ?></strong> e/o che inviano richieste o effettuano prenotazioni.
          </p>

          <h2>1. Titolare del trattamento</h2>
          <p>
            Il Titolare del trattamento è: <strong><?php echo e($ownerLine); ?></strong>.<br>
            <?php if ($address): ?>Indirizzo: <?php echo e($address); ?><br><?php endif; ?>
            <?php if ($phone): ?>Telefono: <?php echo e($phone); ?><br><?php endif; ?>
            Email: <a href="mailto:<?php echo e($email); ?>"><?php echo e($email); ?></a>
          </p>

          <h2>2. Dati trattati</h2>
          <ul>
            <li><strong>Dati di contatto</strong>: nome, email, eventuale numero di telefono, contenuto del messaggio.</li>
            <li><strong>Dati di prenotazione</strong>: date di check-in/check-out, numero di ospiti, eventuali richieste.</li>
            <li><strong>Dati tecnici</strong>: indirizzo IP, log di sicurezza e diagnostica, informazioni tecniche del browser (se necessarie per la sicurezza/erogazione del servizio).</li>
            <li><strong>Dati di pagamento</strong>: i pagamenti online sono gestiti da fornitori terzi (es. Stripe). Il sito non memorizza i dati completi della carta di pagamento.</li>
          </ul>

          <h2>3. Finalità e basi giuridiche</h2>
          <ul>
            <li><strong>Gestione richieste e comunicazioni</strong> (base giuridica: esecuzione di misure precontrattuali e/o legittimo interesse).</li>
            <li><strong>Gestione della prenotazione e del soggiorno</strong> (base giuridica: esecuzione di un contratto).</li>
            <li><strong>Adempimenti legali e fiscali</strong> (base giuridica: obbligo di legge).</li>
            <li><strong>Sicurezza del sito</strong> e prevenzione abusi (base giuridica: legittimo interesse).</li>
            <li><strong>Cookie facoltativi</strong> (se presenti/attivati): base giuridica: consenso (vedi <a href="<?php echo e(i18n_url('cookies')); ?>">Cookie policy</a>).</li>
          </ul>

          <h2>4. Modalità del trattamento</h2>
          <p>
            Il trattamento avviene con strumenti elettronici e misure di sicurezza adeguate a proteggere i dati.
            L’accesso ai dati è limitato al personale autorizzato e ai fornitori che agiscono per conto del Titolare.
          </p>

          <h2>5. Destinatari dei dati</h2>
          <p>
            I dati possono essere comunicati a fornitori che supportano l’erogazione del servizio, ad esempio:
          </p>
          <ul>
            <li>provider di hosting e manutenzione tecnica;</li>
            <li>fornitori di pagamento (es. Stripe) per l’elaborazione delle transazioni;</li>
            <li>fornitori di servizi email per l’invio delle comunicazioni;</li>
            <li>autorità competenti, ove richiesto dalla legge.</li>
          </ul>

          <h2>6. Trasferimento verso Paesi extra UE</h2>
          <p>
            Alcuni fornitori (ad es. servizi di pagamento) possono trattare dati anche al di fuori dello Spazio Economico Europeo.
            In tali casi, il trasferimento avviene secondo le garanzie previste dal GDPR (es. Clausole Contrattuali Standard).
          </p>

          <h2>7. Periodo di conservazione</h2>
          <ul>
            <li>Dati di prenotazione: per il tempo necessario alla gestione del soggiorno e, se richiesto, per gli adempimenti contabili/fiscali.</li>
            <li>Dati di contatto: per il tempo necessario a rispondere e gestire eventuali richieste successive.</li>
            <li>Log tecnici/sicurezza: per un periodo limitato e proporzionato alle finalità di sicurezza e diagnosi.</li>
          </ul>

          <h2>8. Diritti dell’interessato</h2>
          <p>
            In qualsiasi momento puoi esercitare i diritti previsti dagli artt. 15-22 GDPR (accesso, rettifica, cancellazione,
            limitazione, portabilità, opposizione) e revocare eventuali consensi prestati. Puoi inoltre proporre reclamo al
            Garante per la protezione dei dati personali.
          </p>

          <h2>9. Cookie e strumenti simili</h2>
          <p>
            Per informazioni dettagliate sui cookie e su come gestire le preferenze, consulta la
            <a href="<?php echo e(i18n_url('cookies')); ?>">Cookie policy</a>.
          </p>

          <h2>10. Modifiche</h2>
          <p>
            Potremmo aggiornare questa informativa. In caso di modifiche rilevanti, provvederemo ad aggiornare la data di “Ultimo aggiornamento”.
          </p>
        <?php else: ?>
          <p>
            This privacy policy explains how personal data is processed when you visit the
            <strong><?php echo e($siteName); ?></strong> website and/or when you submit enquiries or make a booking.
          </p>

          <h2>1. Data controller</h2>
          <p>
            Data controller: <strong><?php echo e($ownerLine); ?></strong>.<br>
            <?php if ($address): ?>Address: <?php echo e($address); ?><br><?php endif; ?>
            <?php if ($phone): ?>Phone: <?php echo e($phone); ?><br><?php endif; ?>
            Email: <a href="mailto:<?php echo e($email); ?>"><?php echo e($email); ?></a>
          </p>

          <h2>2. Data we process</h2>
          <ul>
            <li><strong>Contact data</strong>: name, email, optional phone number, message content.</li>
            <li><strong>Booking data</strong>: check-in/check-out dates, number of guests, special requests.</li>
            <li><strong>Technical data</strong>: IP address, security/diagnostic logs, limited browser technical information (as needed for security and service delivery).</li>
            <li><strong>Payment data</strong>: online payments are processed by third-party providers (e.g. Stripe). The site does not store full card details.</li>
          </ul>

          <h2>3. Purposes and legal bases</h2>
          <ul>
            <li><strong>Handling enquiries and communications</strong> (legal basis: pre-contractual measures and/or legitimate interest).</li>
            <li><strong>Managing bookings and the stay</strong> (legal basis: performance of a contract).</li>
            <li><strong>Legal and tax compliance</strong> (legal basis: legal obligation).</li>
            <li><strong>Website security</strong> and abuse prevention (legal basis: legitimate interest).</li>
            <li><strong>Optional cookies</strong> (if enabled): legal basis: consent (see the <a href="<?php echo e(i18n_url('cookies')); ?>">Cookie policy</a>).</li>
          </ul>

          <h2>4. How we process data</h2>
          <p>
            We process data using electronic tools and appropriate security measures. Access is limited to authorized personnel
            and service providers acting on behalf of the data controller.
          </p>

          <h2>5. Data recipients</h2>
          <p>
            Data may be shared with service providers supporting service delivery, for example:
          </p>
          <ul>
            <li>hosting and technical maintenance providers;</li>
            <li>payment providers (e.g. Stripe) to process transactions;</li>
            <li>email providers to send communications;</li>
            <li>competent authorities when required by law.</li>
          </ul>

          <h2>6. International transfers</h2>
          <p>
            Some providers (e.g. payment services) may process data outside the European Economic Area.
            Where applicable, transfers are carried out using safeguards provided by GDPR (e.g. Standard Contractual Clauses).
          </p>

          <h2>7. Retention</h2>
          <ul>
            <li>Booking data: for the time needed to manage the stay and, when required, for accounting/tax compliance.</li>
            <li>Contact data: for the time needed to respond and manage follow-up requests.</li>
            <li>Technical/security logs: for a limited period proportionate to security and diagnostic purposes.</li>
          </ul>

          <h2>8. Your rights</h2>
          <p>
            You can exercise your rights under GDPR (access, rectification, erasure, restriction, portability, objection) and withdraw any consent at any time.
            You also have the right to lodge a complaint with the competent data protection authority.
          </p>

          <h2>9. Cookies</h2>
          <p>
            For details about cookies and how to manage your preferences, please see the
            <a href="<?php echo e(i18n_url('cookies')); ?>">Cookie policy</a>.
          </p>

          <h2>10. Changes</h2>
          <p>
            We may update this policy. If significant changes are made, we will update the “Last updated” date.
          </p>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
