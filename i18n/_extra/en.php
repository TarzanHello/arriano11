<?php
// Extra strings for legal pages + cookie banner (English)
return [
  'meta' => [
    'privacy' => [
      'title' => 'Privacy policy',
      'description' => 'How we process your data when you browse the site and when you make a booking.'
    ],
    'cookies' => [
      'title' => 'Cookie policy',
      'description' => 'Information about the cookies used on this site and how to manage your preferences.'
    ],
  ],

  'footer' => [
    'privacy' => 'Privacy',
    'cookies' => 'Cookies',
    'manage_cookies' => 'Manage cookies',
  ],

  'cookies' => [
    'banner_title' => 'Cookies',
    'banner_text' => 'We use essential cookies required for the website to work and stay secure. Only with your consent we may use optional analytics cookies to measure visits and improve content. You can accept, reject non-essential cookies or customise your preferences at any time.',
    'banner_accept' => 'Accept all',
    'banner_reject' => 'Reject non-essential',
    'banner_settings' => 'Customise',

    'modal_title' => 'Cookie preferences',
    'modal_intro' => 'Choose which optional cookies to allow. Essential cookies are always enabled because they are required for the site to function. You can change or withdraw consent at any time from the footer link.',

    'necessary_title' => 'Essential',
    'necessary_desc' => 'Required for the site to work (e.g. security, language, session and saving your preferences) and cannot be disabled.',
    'analytics_title' => 'Analytics',
    'analytics_desc' => 'Help us understand, in aggregated form, how the site is used and improve performance and content. Optional. If disabled, they will not be set.',
    'always_on' => 'Always on',

    'save' => 'Save preferences',
    'cancel' => 'Cancel',

    'link_privacy' => 'Privacy',
    'link_cookies' => 'Cookie policy',
  ],

  'legal' => [
    'privacy_pill' => 'Privacy',
    'privacy_title' => 'Privacy policy',
    'privacy_lead' => 'A clear explanation of how we collect and use personal data (contacts and bookings).',

    'cookies_pill' => 'Cookies',
    'cookies_title' => 'Cookie policy',
    'cookies_lead' => 'Which cookies we use and how to manage your preferences.',

    'cta_contact' => 'Contact us',
    'cta_cookies' => 'Cookie policy',
    'cta_privacy' => 'Privacy',
  ],

  // Reviews (used for verified-guest review flow + homepage section)
  'reviews' => [
    'section_title' => 'Verified reviews',
    'section_lead' => 'Reviews are published only from guests who actually stayed at {site}.',
    'avg_label' => 'Average rating',
    'count_label' => 'reviews',
    'pending_note' => 'Reviews are published after verification.',
    'no_reviews_yet' => 'No reviews have been published yet. After checkout you will receive a personal link to leave your review.',

    // Review page
    'page_title' => 'Leave a review',
    'page_lead' => 'This link is personal and valid only for guests after checkout.',
    'form_name' => 'Display name',
    'form_rating' => 'Rating',
    'form_body' => 'Your review',
    'form_submit' => 'Submit review',
    'thanks_title' => 'Thank you!',
    'thanks_text' => 'We received your review. It will be published after verification.',
    'invalid_token' => 'Invalid or expired link.',
    'too_early' => 'You can leave your review after the end of your stay.',
    'already_submitted' => 'You have already submitted a review for this stay. Thank you!',
  ],

  // Email templates (only the keys missing from the base translations)
  'emails' => [
    'review_invite_subject' => 'How was your stay at {site}? Leave a verified review',
    'review_invite_body' => "Hi {name},\n\nThank you for staying at {site}.\n\nYou can leave a verified review using this personal link:\n{link}\n\nYour review will be published after verification.\n\nThanks,\n{site}",
  ],
  // Price match promise (Oct 2026)
  'pricematch' => [
    'title' => 'Best price guaranteed',
    'text' => 'Found this apartment cheaper on Booking for the same dates? Send us a screenshot by WhatsApp or email and we will give you 10% off that price.',
    'cta_whatsapp' => 'Send on WhatsApp',
    'cta_email' => 'Send by email',
    'whatsapp_text' => 'Hi! I found Via Arriano 11 at a lower price on Booking. Here is the screenshot to get 10% off.',
    'email_subject' => 'Lower price found on Booking',
  ],

  // Neighbourhood guide (Oct 2026) — English is the fallback for languages without their own version
  'neighborhood_guide' => [
    'title' => 'Pigneto: a complete neighbourhood guide',
    'intro' => 'Pigneto is one of Rome’s most authentic and lively neighbourhoods: just outside Porta Maggiore, between Via Prenestina and Via Casilina, a short metro ride from the Colosseum. Here is what to know before you arrive, from the people who live here.',
    'sections' => [
      ['h' => 'Via del Pigneto: market by day, aperitivo by night', 'html' => 'The heart of the neighbourhood is the pedestrian stretch of <strong>Via del Pigneto</strong>. In the morning (Monday to Saturday) it hosts a local market with fruit, vegetables and everyday shopping; in the evening bars, wine bars and small restaurants fill the street with tables. It is where Romans – not tour groups – go out.'],
      ['h' => 'Cinema and history: Pasolini’s Pigneto', 'html' => 'Pigneto is tied to Italian cinema. Pier Paolo Pasolini shot <em>Accattone</em> (1961) in these streets, and the historic <strong>Bar Necci</strong> (open since 1924) is linked to that film. Scenes of Rossellini’s <em>Rome, Open City</em> were filmed in nearby Via Montecuccoli. Walking around you still see the low houses and courtyards of the old working-class district.'],
      ['h' => 'Street art and local life', 'html' => 'Murals and street art cover walls all around Pigneto and neighbouring Torpignattara: a walk through the side streets is a free open-air gallery. Independent bookshops, vintage shops and artisan workshops complete the picture of a neighbourhood with its own identity.'],
      ['h' => 'What to see nearby', 'html' => '<strong>Porta Maggiore</strong>, with the ancient Tomb of Eurysaces the Baker (1st century BC), is just a few minutes away, as is the Basilica of <strong>Santa Croce in Gerusalemme</strong>. <strong>San Giovanni in Laterano</strong> is a few minutes away by metro, and the university district of <strong>San Lorenzo</strong> is close by.'],
      ['h' => 'Getting around: Metro C', 'html' => 'The Metro C station is a <strong>3–5 minute walk</strong> from the apartment. Line C runs <strong>without changes to San Giovanni</strong> (change here for Metro A towards Spagna and the Vatican) and to the new <strong>Colosseo/Fori Imperiali</strong> station: the Colosseum is about 15 minutes door to door. <strong>Tram 14</strong> along Via Prenestina goes straight to Termini station.'],
      ['h' => 'How to get here from the airports and Termini', 'html' => '<strong>From Fiumicino:</strong> Leonardo Express train to Termini (about 30 minutes), then tram 14 or a short taxi ride. A direct taxi takes 45–60 minutes and is charged by the meter (the airport fixed fare only applies inside the Aurelian Walls).<br><strong>From Ciampino:</strong> shuttle bus to Termini, then tram 14, or a taxi (about 30 minutes).<br><strong>From Termini:</strong> tram 14 towards Togliatti, or Metro A to San Giovanni and then Metro C.'],
    ],
  ],
];
