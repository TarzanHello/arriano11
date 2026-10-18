<?php
// Cadenas extra para páginas legales + banner de cookies (Español)
return [
  'meta' => [
    'privacy' => [
      'title' => 'Política de privacidad',
      'description' => 'Cómo tratamos tus datos cuando navegas por el sitio y cuando realizas una reserva.'
    ],
    'cookies' => [
      'title' => 'Política de cookies',
      'description' => 'Información sobre los cookies usados en este sitio y cómo gestionar tus preferencias.'
    ],
  ],

  'footer' => [
    'privacy' => 'Privacidad',
    'cookies' => 'Cookies',
    'manage_cookies' => 'Gestionar cookies',
  ],

  'cookies' => [
    'banner_title' => 'Cookies',
    'banner_text' => 'Utilizamos cookies esenciales necesarias para el funcionamiento del sitio. Con tu consentimiento, también podemos usar cookies opcionales (por ejemplo, analíticas) para mejorar tu experiencia. Puedes cambiar tu elección en cualquier momento.',
    'banner_accept' => 'Aceptar',
    'banner_reject' => 'Rechazar',
    'banner_settings' => 'Preferencias',

    'modal_title' => 'Preferencias de cookies',
    'modal_intro' => 'Elige qué cookies permitir. Las cookies esenciales siempre están activas porque son necesarias para que el sitio funcione.',

    'necessary_title' => 'Esenciales',
    'necessary_desc' => 'Necesarias para que el sitio funcione (p. ej. seguridad, idioma, sesión) y no se pueden desactivar.',
    'analytics_title' => 'Analíticas',
    'analytics_desc' => 'Nos ayudan a entender cómo se usa el sitio y a mejorarlo. Opcionales.',
    'always_on' => 'Siempre activas',

    'save' => 'Guardar',
    'cancel' => 'Cancelar',

    'link_privacy' => 'Privacidad',
    'link_cookies' => 'Política de cookies',
  ],

  'legal' => [
    'privacy_pill' => 'Privacidad',
    'privacy_title' => 'Política de privacidad',
    'privacy_lead' => 'Información clara sobre cómo recopilamos y usamos datos personales (contactos y reservas).',

    'cookies_pill' => 'Cookies',
    'cookies_title' => 'Política de cookies',
    'cookies_lead' => 'Qué cookies usamos y cómo gestionar tus preferencias.',

    'cta_contact' => 'Contáctanos',
    'cta_cookies' => 'Política de cookies',
    'cta_privacy' => 'Privacidad',
  ],
  // Price match promise (Oct 2026)
  'pricematch' => [
    'title' => 'Mejor precio garantizado',
    'text' => '¿Has encontrado este apartamento más barato en Booking para las mismas fechas? Envíanos una captura por WhatsApp o email y te hacemos un 10 % menos sobre ese precio.',
    'cta_whatsapp' => 'Enviar por WhatsApp',
    'cta_email' => 'Enviar por email',
    'whatsapp_text' => '¡Hola! He encontrado Via Arriano 11 más barato en Booking. Os envío la captura para obtener un 10 % menos.',
    'email_subject' => 'Precio más bajo encontrado en Booking',
  ],

  // Neighbourhood guide (Oct 2026) — English is the fallback for languages without their own version
  'neighborhood_guide' => [
    'title' => 'Pigneto: guía completa del barrio',
    'intro' => 'Pigneto es uno de los barrios más auténticos y animados de Roma: justo fuera de Porta Maggiore, entre Via Prenestina y Via Casilina, a pocos minutos en metro del Coliseo. Esto es lo que conviene saber antes de llegar, contado por quien vive aquí.',
    'sections' => [
      ['h' => 'Via del Pigneto: mercado por la mañana, aperitivo por la noche', 'html' => 'El corazón del barrio es la zona peatonal de <strong>Via del Pigneto</strong>. Por la mañana (de lunes a sábado) hay un mercado de barrio con fruta, verdura y compra diaria; por la noche bares, vinotecas y pequeños restaurantes llenan la calle de mesas. Aquí salen los romanos, no los grupos de turistas.'],
      ['h' => 'Cine e historia: el Pigneto de Pasolini', 'html' => 'Pigneto está ligado al cine italiano. Pier Paolo Pasolini rodó aquí <em>Accattone</em> (1961) y el histórico <strong>Bar Necci</strong> (abierto desde 1924) está vinculado a esa película. Algunas escenas de <em>Roma, ciudad abierta</em> de Rossellini se rodaron en la cercana Via Montecuccoli. Paseando todavía se ven las casas bajas y los patios del antiguo barrio obrero.'],
      ['h' => 'Arte urbano y vida de barrio', 'html' => 'Murales y arte urbano cubren las paredes de Pigneto y del vecino Torpignattara: un paseo por las calles laterales es una galería al aire libre y gratuita. Librerías independientes, tiendas vintage y talleres artesanos completan un barrio con identidad propia.'],
      ['h' => 'Qué ver cerca', 'html' => 'A pocos minutos están <strong>Porta Maggiore</strong>, con el antiguo sepulcro de Eurísaces el panadero (siglo I a. C.), y la basílica de <strong>Santa Croce in Gerusalemme</strong>. <strong>San Juan de Letrán</strong> está a pocos minutos en metro y el barrio universitario de <strong>San Lorenzo</strong> está muy cerca.'],
      ['h' => 'Cómo moverse: metro C', 'html' => 'La estación del metro C está a <strong>3–5 minutos a pie</strong> del apartamento. La línea C lleva <strong>sin transbordos a San Giovanni</strong> (aquí se cambia al metro A hacia Plaza de España y el Vaticano) y a la nueva estación <strong>Colosseo/Fori Imperiali</strong>: el Coliseo queda a unos 15 minutos puerta a puerta. El <strong>tranvía 14</strong>, por Via Prenestina, va directo a la estación Termini.'],
      ['h' => 'Cómo llegar desde los aeropuertos y Termini', 'html' => '<strong>Desde Fiumicino:</strong> tren Leonardo Express hasta Termini (unos 30 minutos) y luego tranvía 14 o un trayecto corto en taxi. El taxi directo tarda 45–60 minutos y se paga con taxímetro (la tarifa fija del aeropuerto solo vale dentro de las Murallas Aurelianas).<br><strong>Desde Ciampino:</strong> autobús lanzadera hasta Termini y luego tranvía 14, o taxi (unos 30 minutos).<br><strong>Desde Termini:</strong> tranvía 14 dirección Togliatti, o metro A hasta San Giovanni y luego metro C.'],
    ],
  ],

  // Redesign v2 (Oct 2026)
  'v2' => [
    'title' => 'De tu puerta al Coliseo, sin transbordos',
    'start' => 'Tu casa en Roma',
    'walk' => '3–5 minutos a pie',
    'metro_a' => 'Transbordo al metro A',
    'colosseo' => 'Unos 15 minutos puerta a puerta',
    'place' => 'Pigneto, Roma',
    'skip' => 'Ir al contenido',
    'photos' => 'Fotos del apartamento',
  ],
];
