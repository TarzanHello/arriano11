<?php
// Chaînes supplémentaires pour les pages légales + bandeau cookies (Français)
return [
  'meta' => [
    'privacy' => [
      'title' => 'Politique de confidentialité',
      'description' => 'Comment nous traitons vos données lorsque vous naviguez sur le site et lorsque vous effectuez une réservation.'
    ],
    'cookies' => [
      'title' => 'Politique relative aux cookies',
      'description' => 'Informations sur les cookies utilisés sur ce site et sur la gestion de vos préférences.'
    ],
  ],

  'footer' => [
    'privacy' => 'Confidentialité',
    'cookies' => 'Cookies',
    'manage_cookies' => 'Gérer les cookies',
  ],

  'cookies' => [
    'banner_title' => 'Cookies',
    'banner_text' => 'Nous utilisons des cookies essentiels nécessaires au fonctionnement du site. Avec votre consentement, nous pouvons aussi utiliser des cookies facultatifs (ex. statistiques) pour améliorer votre expérience. Vous pouvez modifier votre choix à tout moment.',
    'banner_accept' => 'Accepter',
    'banner_reject' => 'Refuser',
    'banner_settings' => 'Préférences',

    'modal_title' => 'Préférences cookies',
    'modal_intro' => 'Choisissez les cookies à autoriser. Les cookies essentiels sont toujours activés car indispensables au fonctionnement du site.',

    'necessary_title' => 'Essentiels',
    'necessary_desc' => 'Nécessaires au fonctionnement du site (ex. sécurité, langue, session) et ne peuvent pas être désactivés.',
    'analytics_title' => 'Statistiques',
    'analytics_desc' => 'Nous aident à comprendre l’utilisation du site et à l’améliorer. Facultatifs.',
    'always_on' => 'Toujours actifs',

    'save' => 'Enregistrer',
    'cancel' => 'Annuler',

    'link_privacy' => 'Confidentialité',
    'link_cookies' => 'Cookies',
  ],

  'legal' => [
    'privacy_pill' => 'Confidentialité',
    'privacy_title' => 'Politique de confidentialité',
    'privacy_lead' => 'Explication claire de la collecte et de l’utilisation des données personnelles (contacts et réservations).',

    'cookies_pill' => 'Cookies',
    'cookies_title' => 'Politique relative aux cookies',
    'cookies_lead' => 'Quels cookies nous utilisons et comment gérer vos préférences.',

    'cta_contact' => 'Nous contacter',
    'cta_cookies' => 'Cookies',
    'cta_privacy' => 'Confidentialité',
  ],
  // Price match promise (Oct 2026)
  'pricematch' => [
    'title' => 'Meilleur prix garanti',
    'text' => 'Vous avez trouvé cet appartement moins cher sur Booking pour les mêmes dates ? Envoyez-nous une capture d’écran par WhatsApp ou e-mail : nous vous faisons 10 % de moins sur ce prix.',
    'cta_whatsapp' => 'Envoyer sur WhatsApp',
    'cta_email' => 'Envoyer par e-mail',
    'whatsapp_text' => 'Bonjour ! J’ai trouvé Via Arriano 11 moins cher sur Booking. Voici la capture d’écran pour obtenir 10 % de réduction.',
    'email_subject' => 'Prix plus bas trouvé sur Booking',
  ],

  // Neighbourhood guide (Oct 2026) — English is the fallback for languages without their own version
  'neighborhood_guide' => [
    'title' => 'Pigneto : guide complet du quartier',
    'intro' => 'Le Pigneto est l’un des quartiers les plus authentiques et animés de Rome : juste après la Porta Maggiore, entre la Via Prenestina et la Via Casilina, à quelques minutes de métro du Colisée. Voici ce qu’il faut savoir avant d’arriver, raconté par ceux qui y vivent.',
    'sections' => [
      ['h' => 'Via del Pigneto : marché le matin, apéritif le soir', 'html' => 'Le cœur du quartier est la partie piétonne de la <strong>Via del Pigneto</strong>. Le matin (du lundi au samedi) s’y tient un marché de quartier avec fruits, légumes et courses du quotidien ; le soir, bars, bars à vin et petits restaurants remplissent la rue de tables. C’est là que sortent les Romains, pas les groupes de touristes.'],
      ['h' => 'Cinéma et histoire : le Pigneto de Pasolini', 'html' => 'Le Pigneto est lié au cinéma italien. Pier Paolo Pasolini y a tourné <em>Accattone</em> (1961) et l’historique <strong>Bar Necci</strong> (ouvert depuis 1924) est associé à ce film. Des scènes de <em>Rome, ville ouverte</em> de Rossellini ont été tournées dans la Via Montecuccoli toute proche. En vous promenant, vous verrez encore les maisons basses et les cours de l’ancien quartier populaire.'],
      ['h' => 'Street art et vie de quartier', 'html' => 'Fresques et street art couvrent les murs du Pigneto et du quartier voisin de Torpignattara : une balade dans les rues adjacentes est une galerie à ciel ouvert, gratuite. Librairies indépendantes, boutiques vintage et ateliers d’artisans complètent un quartier à l’identité bien à lui.'],
      ['h' => 'À voir aux alentours', 'html' => 'À quelques minutes se trouvent la <strong>Porta Maggiore</strong>, avec l’antique tombeau d’Eurysaces le boulanger (Ier siècle av. J.-C.), et la basilique <strong>Sainte-Croix-de-Jérusalem</strong>. <strong>Saint-Jean-de-Latran</strong> est à quelques minutes de métro et le quartier universitaire de <strong>San Lorenzo</strong> est tout proche.'],
      ['h' => 'Se déplacer : le métro C', 'html' => 'La station du métro C est à <strong>3–5 minutes à pied</strong> de l’appartement. La ligne C mène <strong>sans changement à San Giovanni</strong> (correspondance avec le métro A vers Spagna et le Vatican) et à la nouvelle station <strong>Colosseo/Fori Imperiali</strong> : le Colisée est à environ 15 minutes de porte à porte. Le <strong>tram 14</strong>, sur la Via Prenestina, va directement à la gare Termini.'],
      ['h' => 'Venir depuis les aéroports et Termini', 'html' => '<strong>Depuis Fiumicino :</strong> train Leonardo Express jusqu’à Termini (environ 30 minutes), puis tram 14 ou un court trajet en taxi. Le taxi direct met 45 à 60 minutes, au compteur (le forfait aéroport ne s’applique qu’à l’intérieur des murs d’Aurélien).<br><strong>Depuis Ciampino :</strong> navette jusqu’à Termini, puis tram 14, ou taxi (environ 30 minutes).<br><strong>Depuis Termini :</strong> tram 14 direction Togliatti, ou métro A jusqu’à San Giovanni puis métro C.'],
    ],
  ],

  // Redesign v2 (Oct 2026)
  'v2' => [
    'title' => 'De votre porte au Colisée, sans changement',
    'start' => 'Votre maison à Rome',
    'walk' => '3 à 5 minutes à pied',
    'metro_a' => 'Correspondance métro A',
    'colosseo' => 'Environ 15 minutes de porte à porte',
    'place' => 'Pigneto, Rome',
    'skip' => 'Aller au contenu',
    'photos' => 'Les photos de l’appartement',
  ],
];
