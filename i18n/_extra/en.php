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
];
