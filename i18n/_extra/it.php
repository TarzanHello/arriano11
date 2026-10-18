<?php
// Extra strings for legal pages + cookie banner (Italian)
return [
  'meta' => [
    'privacy' => [
      'title' => 'Informativa privacy',
      'description' => 'Come trattiamo i tuoi dati quando navighi sul sito e quando effettui una prenotazione.'
    ],
    'cookies' => [
      'title' => 'Cookie policy',
      'description' => 'Informazioni sui cookie utilizzati dal sito e su come gestire le preferenze.'
    ],
  ],

  'footer' => [
    'privacy' => 'Privacy',
    'cookies' => 'Cookie',
    'manage_cookies' => 'Gestisci cookie',
  ],

  'cookies' => [
    'banner_title' => 'Cookie',
    'banner_text' => 'Usiamo cookie tecnici necessari per garantire il corretto funzionamento e la sicurezza del sito. Solo con il tuo consenso possiamo usare cookie analitici per misurare le visite e migliorare i contenuti. Puoi accettare, rifiutare i cookie non necessari o personalizzare le preferenze in qualsiasi momento.',
    'banner_accept' => 'Accetta tutti',
    'banner_reject' => 'Rifiuta non necessari',
    'banner_settings' => 'Personalizza',

    'modal_title' => 'Preferenze cookie',
    'modal_intro' => 'Scegli quali cookie facoltativi consentire. I cookie necessari sono sempre attivi perché indispensabili al funzionamento del sito. Puoi modificare o revocare il consenso in qualsiasi momento dal link nel footer.',

    'necessary_title' => 'Necessari',
    'necessary_desc' => 'Servono al funzionamento del sito (es. sicurezza, lingua, sessione e salvataggio delle preferenze) e non possono essere disattivati.',
    'analytics_title' => 'Analitici',
    'analytics_desc' => 'Ci aiutano a capire, in forma aggregata, come viene usato il sito e a migliorarne prestazioni e contenuti. Se disattivati, non verranno installati.',
    'always_on' => 'Sempre attivi',

    'save' => 'Salva preferenze',
    'cancel' => 'Annulla',

    'link_privacy' => 'Privacy',
    'link_cookies' => 'Cookie policy',
  ],

  'legal' => [
    'privacy_pill' => 'Privacy',
    'privacy_title' => 'Informativa privacy',
    'privacy_lead' => 'Trasparenza su come raccogliamo e utilizziamo i dati personali (contatti e prenotazioni).',

    'cookies_pill' => 'Cookie',
    'cookies_title' => 'Cookie policy',
    'cookies_lead' => 'Quali cookie usiamo e come gestire le preferenze.',

    'cta_contact' => 'Contattaci',
    'cta_cookies' => 'Cookie policy',
    'cta_privacy' => 'Privacy',
  ],

  // Recensioni (flusso recensioni verificate + sezione in home)
  'reviews' => [
    'section_title' => 'Recensioni verificate',
    'section_lead' => 'Le recensioni vengono pubblicate solo da ospiti che hanno realmente soggiornato in {site}.',
    'avg_label' => 'Valutazione media',
    'count_label' => 'recensioni',
    'pending_note' => 'Le recensioni vengono pubblicate dopo verifica del soggiorno.',
    'no_reviews_yet' => 'Non ci sono ancora recensioni pubblicate. Dopo il check-out riceverai un link personale per lasciare la tua recensione.',

    // Pagina recensione
    'page_title' => 'Lascia una recensione',
    'page_lead' => 'Questo link è personale ed è valido solo per gli ospiti dopo il check-out.',
    'form_name' => 'Nome visualizzato',
    'form_rating' => 'Valutazione',
    'form_body' => 'La tua recensione',
    'form_submit' => 'Invia recensione',
    'thanks_title' => 'Grazie!',
    'thanks_text' => 'Abbiamo ricevuto la tua recensione. Verrà pubblicata dopo verifica.',
    'invalid_token' => 'Link non valido o scaduto.',
    'too_early' => 'Potrai lasciare la recensione dopo la fine del soggiorno.',
    'already_submitted' => 'Hai già inviato una recensione per questo soggiorno. Grazie!',
  ],

  // Template email (solo chiavi aggiuntive rispetto al base)
  'emails' => [
    'review_invite_subject' => 'Com\'è andato il soggiorno a {site}? Lascia una recensione verificata',
    'review_invite_body' => "Ciao {name},\n\nGrazie per aver soggiornato a {site}.\n\nPuoi lasciare una recensione verificata usando questo link personale:\n{link}\n\nLa recensione verrà pubblicata dopo verifica del soggiorno.\n\nGrazie,\n{site}",
  ],
];
