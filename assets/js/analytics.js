// Google Analytics 4 — loaded ONLY after the visitor accepts analytics cookies.
// Uses Google Consent Mode v2 (everything denied by default) and the site's
// cookie banner event "via:consent" (see cookie-consent.js).
(function(){
  'use strict';
  var id = window.__VIA_GA_ID;
  if(!id) return;

  window.dataLayer = window.dataLayer || [];
  function gtag(){ window.dataLayer.push(arguments); }
  window.gtag = gtag;
  gtag('consent', 'default', {
    ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied',
    analytics_storage: 'denied'
  });

  var loaded = false;
  var queue = [];

  function load(){
    if(loaded) return;
    loaded = true;
    gtag('consent', 'update', { analytics_storage: 'granted' });
    gtag('js', new Date());
    gtag('config', id);
    var s = document.createElement('script');
    s.async = true;
    s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
    document.head.appendChild(s);
    while(queue.length){ var q = queue.shift(); gtag('event', q[0], q[1]); }
  }

  function onConsent(st){
    if(st && st.analytics){ load(); }
    else if(loaded){ gtag('consent', 'update', { analytics_storage: 'denied' }); }
  }

  document.addEventListener('via:consent', function(e){ onConsent(e && e.detail); });
  if(window.__VIA_CONSENT) onConsent(window.__VIA_CONSENT);

  // Public helper used by the booking calendar (request sent, payment started…)
  window.viaTrack = function(name, params){
    if(loaded){ gtag('event', name, params || {}); }
    else if(queue.length < 20){ queue.push([name, params || {}]); }
  };

  // Contact clicks: WhatsApp, phone, email, best-price buttons
  document.addEventListener('click', function(e){
    var a = e.target && e.target.closest ? e.target.closest('a') : null;
    if(!a) return;
    var custom = a.getAttribute('data-track');
    if(custom){ window.viaTrack(custom); return; }
    var h = a.getAttribute('href') || '';
    if(h.indexOf('wa.me/') !== -1) window.viaTrack('contact_whatsapp');
    else if(h.indexOf('tel:') === 0) window.viaTrack('contact_phone');
    else if(h.indexOf('mailto:') === 0) window.viaTrack('contact_email');
  }, true);
})();
