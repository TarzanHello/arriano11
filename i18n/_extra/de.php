<?php
// Zusätzliche Texte für Rechtseiten + Cookie-Banner (Deutsch)
return [
  'meta' => [
    'privacy' => [
      'title' => 'Datenschutzerklärung',
      'description' => 'Wie wir Ihre Daten verarbeiten, wenn Sie die Website nutzen und eine Buchung vornehmen.'
    ],
    'cookies' => [
      'title' => 'Cookie-Richtlinie',
      'description' => 'Informationen über die auf dieser Website verwendeten Cookies und wie Sie Ihre Einstellungen verwalten.'
    ],
  ],

  'footer' => [
    'privacy' => 'Datenschutz',
    'cookies' => 'Cookies',
    'manage_cookies' => 'Cookie-Einstellungen',
  ],

  'cookies' => [
    'banner_title' => 'Cookies',
    'banner_text' => 'Wir verwenden notwendige Cookies, die für den Betrieb der Website erforderlich sind. Mit Ihrer Zustimmung können wir auch optionale Cookies (z. B. Analyse-Cookies) einsetzen, um die Nutzung zu verbessern. Sie können Ihre Auswahl jederzeit ändern.',
    'banner_accept' => 'Akzeptieren',
    'banner_reject' => 'Ablehnen',
    'banner_settings' => 'Einstellungen',

    'modal_title' => 'Cookie-Einstellungen',
    'modal_intro' => 'Wählen Sie aus, welche Cookies Sie zulassen. Notwendige Cookies sind immer aktiv, da sie für die Funktion der Website erforderlich sind.',

    'necessary_title' => 'Notwendig',
    'necessary_desc' => 'Erforderlich für den Betrieb der Website (z. B. Sicherheit, Sprache, Sitzung) und kann nicht deaktiviert werden.',
    'analytics_title' => 'Analyse',
    'analytics_desc' => 'Helfen uns zu verstehen, wie die Website genutzt wird, und sie zu verbessern. Optional.',
    'always_on' => 'Immer aktiv',

    'save' => 'Speichern',
    'cancel' => 'Abbrechen',

    'link_privacy' => 'Datenschutz',
    'link_cookies' => 'Cookie-Richtlinie',
  ],

  'legal' => [
    'privacy_pill' => 'Datenschutz',
    'privacy_title' => 'Datenschutzerklärung',
    'privacy_lead' => 'Transparenz darüber, wie wir personenbezogene Daten verarbeiten (Kontakt und Buchungen).',

    'cookies_pill' => 'Cookies',
    'cookies_title' => 'Cookie-Richtlinie',
    'cookies_lead' => 'Welche Cookies wir verwenden und wie Sie Ihre Einstellungen verwalten.',

    'cta_contact' => 'Kontakt',
    'cta_cookies' => 'Cookie-Richtlinie',
    'cta_privacy' => 'Datenschutz',
  ],
  // Price match promise (Oct 2026)
  'pricematch' => [
    'title' => 'Bestpreis-Garantie',
    'text' => 'Sie haben diese Wohnung für dieselben Daten günstiger auf Booking gefunden? Schicken Sie uns einen Screenshot per WhatsApp oder E-Mail – wir geben Ihnen 10 % Rabatt auf diesen Preis.',
    'cta_whatsapp' => 'Per WhatsApp senden',
    'cta_email' => 'Per E-Mail senden',
    'whatsapp_text' => 'Hallo! Ich habe Via Arriano 11 günstiger auf Booking gefunden. Hier ist der Screenshot für 10 % Rabatt.',
    'email_subject' => 'Günstigerer Preis auf Booking gefunden',
  ],

  // Neighbourhood guide (Oct 2026) — English is the fallback for languages without their own version
  'neighborhood_guide' => [
    'title' => 'Pigneto: der komplette Stadtteil-Guide',
    'intro' => 'Pigneto ist eines der authentischsten und lebendigsten Viertel Roms: direkt hinter der Porta Maggiore, zwischen Via Prenestina und Via Casilina, nur wenige Metrominuten vom Kolosseum entfernt. Das sollten Sie vor der Anreise wissen – erzählt von Menschen, die hier leben.',
    'sections' => [
      ['h' => 'Via del Pigneto: Markt am Morgen, Aperitivo am Abend', 'html' => 'Das Herz des Viertels ist die Fußgängerzone der <strong>Via del Pigneto</strong>. Morgens (Montag bis Samstag) findet hier ein Wochenmarkt mit Obst, Gemüse und Dingen des täglichen Bedarfs statt; abends füllen Bars, Weinbars und kleine Restaurants die Straße mit Tischen. Hier gehen Römer aus, keine Reisegruppen.'],
      ['h' => 'Kino und Geschichte: Pasolinis Pigneto', 'html' => 'Pigneto ist eng mit dem italienischen Kino verbunden. Pier Paolo Pasolini drehte hier <em>Accattone</em> (1961), und die historische <strong>Bar Necci</strong> (seit 1924) ist mit diesem Film verbunden. Szenen aus Rossellinis <em>Rom, offene Stadt</em> entstanden in der nahen Via Montecuccoli. Beim Spaziergang sieht man noch die niedrigen Häuser und Innenhöfe des alten Arbeiterviertels.'],
      ['h' => 'Street Art und Viertelleben', 'html' => 'Wandbilder und Street Art bedecken die Mauern von Pigneto und dem benachbarten Torpignattara: ein Spaziergang durch die Seitenstraßen ist eine kostenlose Open-Air-Galerie. Unabhängige Buchläden, Vintage-Shops und Handwerksbetriebe runden ein Viertel mit eigener Identität ab.'],
      ['h' => 'Sehenswertes in der Nähe', 'html' => 'Wenige Minuten entfernt liegen die <strong>Porta Maggiore</strong> mit dem antiken Grabmal des Bäckers Eurysaces (1. Jh. v. Chr.) und die Basilika <strong>Santa Croce in Gerusalemme</strong>. <strong>San Giovanni in Laterano</strong> ist wenige Metrominuten entfernt, das Universitätsviertel <strong>San Lorenzo</strong> liegt ganz in der Nähe.'],
      ['h' => 'Unterwegs: Metro C', 'html' => 'Die Metro-C-Station ist <strong>3–5 Gehminuten</strong> von der Wohnung entfernt. Die Linie C fährt <strong>ohne Umsteigen nach San Giovanni</strong> (Umstieg in die Metro A Richtung Spanische Treppe und Vatikan) und zur neuen Station <strong>Colosseo/Fori Imperiali</strong>: Das Kolosseum ist rund 15 Minuten von Tür zu Tür entfernt. Die <strong>Straßenbahn 14</strong> auf der Via Prenestina fährt direkt zum Bahnhof Termini.'],
      ['h' => 'Anreise von den Flughäfen und Termini', 'html' => '<strong>Ab Fiumicino:</strong> Leonardo Express bis Termini (ca. 30 Minuten), dann Straßenbahn 14 oder eine kurze Taxifahrt. Ein direktes Taxi braucht 45–60 Minuten und fährt nach Taxameter (der Flughafen-Festpreis gilt nur innerhalb der Aurelianischen Mauer).<br><strong>Ab Ciampino:</strong> Shuttlebus bis Termini, dann Straßenbahn 14, oder Taxi (ca. 30 Minuten).<br><strong>Ab Termini:</strong> Straßenbahn 14 Richtung Togliatti oder Metro A bis San Giovanni und dann Metro C.'],
    ],
  ],

  // Redesign v2 (Oct 2026)
  'v2' => [
    'title' => 'Von der Haustür zum Kolosseum, ohne Umsteigen',
    'start' => 'Ihr Zuhause in Rom',
    'walk' => '3–5 Minuten zu Fuß',
    'metro_a' => 'Umstieg in die Metro A',
    'colosseo' => 'Rund 15 Minuten von Tür zu Tür',
    'place' => 'Pigneto, Rom',
    'skip' => 'Zum Inhalt springen',
    'photos' => 'Fotos der Wohnung',
  ],
];
