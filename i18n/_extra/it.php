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
  // Price match promise (Oct 2026)
  'pricematch' => [
    'title' => 'Prezzo migliore garantito',
    'text' => 'Hai trovato questo appartamento a un prezzo più basso su Booking per le stesse date? Mandaci lo screenshot via WhatsApp o email: ti facciamo il 10% in meno su quel prezzo.',
    'cta_whatsapp' => 'Invia su WhatsApp',
    'cta_email' => 'Invia per email',
    'whatsapp_text' => 'Ciao! Ho trovato Via Arriano 11 a un prezzo più basso su Booking. Vi mando lo screenshot per avere il 10% in meno.',
    'email_subject' => 'Prezzo più basso trovato su Booking',
  ],

  // Neighbourhood guide (Oct 2026) — English is the fallback for languages without their own version
  'neighborhood_guide' => [
    'title' => 'Pigneto: guida completa al quartiere',
    'intro' => 'Il Pigneto è uno dei quartieri più autentici e vivaci di Roma: appena fuori Porta Maggiore, tra Via Prenestina e Via Casilina, a pochi minuti di metro dal Colosseo. Ecco cosa sapere prima di arrivare, raccontato da chi ci vive.',
    'sections' => [
      ['h' => 'Via del Pigneto: mercato di giorno, aperitivo la sera', 'html' => 'Il cuore del quartiere è l’isola pedonale di <strong>Via del Pigneto</strong>. La mattina (dal lunedì al sabato) c’è il mercato rionale con frutta, verdura e spesa quotidiana; la sera bar, enoteche e piccoli ristoranti riempiono la strada di tavolini. È il posto dove escono i romani, non i gruppi organizzati.'],
      ['h' => 'Cinema e storia: il Pigneto di Pasolini', 'html' => 'Il Pigneto è legato al cinema italiano. Pier Paolo Pasolini girò qui <em>Accattone</em> (1961) e lo storico <strong>Bar Necci</strong> (aperto dal 1924) è legato a quel film. Alcune scene di <em>Roma città aperta</em> di Rossellini furono girate nella vicina Via Montecuccoli. Passeggiando si vedono ancora le case basse e i cortili del vecchio quartiere popolare.'],
      ['h' => 'Street art e vita di quartiere', 'html' => 'Murales e street art coprono i muri del Pigneto e della vicina Torpignattara: una passeggiata nelle vie laterali è una galleria a cielo aperto, gratis. Librerie indipendenti, negozi vintage e botteghe artigiane completano un quartiere con un’identità tutta sua.'],
      ['h' => 'Cosa vedere nei dintorni', 'html' => 'A pochi minuti ci sono <strong>Porta Maggiore</strong>, con l’antico Sepolcro di Eurisace il fornaio (I secolo a.C.), e la Basilica di <strong>Santa Croce in Gerusalemme</strong>. <strong>San Giovanni in Laterano</strong> è a pochi minuti di metro e il quartiere universitario di <strong>San Lorenzo</strong> è vicinissimo.'],
      ['h' => 'Come muoversi: Metro C', 'html' => 'La fermata della Metro C è a <strong>3–5 minuti a piedi</strong> dall’appartamento. La linea C porta <strong>senza cambi a San Giovanni</strong> (qui si cambia per la Metro A verso Spagna e Vaticano) e alla nuova stazione <strong>Colosseo/Fori Imperiali</strong>: il Colosseo è a circa 15 minuti porta a porta. Il <strong>tram 14</strong> su Via Prenestina arriva direttamente alla stazione Termini.'],
      ['h' => 'Come arrivare da aeroporti e Termini', 'html' => '<strong>Da Fiumicino:</strong> treno Leonardo Express fino a Termini (circa 30 minuti), poi tram 14 o un breve tratto in taxi. Il taxi diretto impiega 45–60 minuti e si paga a tassametro (la tariffa fissa dall’aeroporto vale solo dentro le Mura Aureliane).<br><strong>Da Ciampino:</strong> bus navetta fino a Termini, poi tram 14, oppure taxi (circa 30 minuti).<br><strong>Da Termini:</strong> tram 14 direzione Togliatti, oppure Metro A fino a San Giovanni e poi Metro C.'],
    ],
  ],
];
