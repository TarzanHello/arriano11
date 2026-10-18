/*
  Cookie consent (minimal, dependency-free)
  - Stores preferences in localStorage and a lightweight cookie
  - Supports opening the preferences modal from footer (data-cookie-manage)
*/

(function(){
  'use strict';

  var STORAGE_KEY = 'via_cookie_consent';
  var COOKIE_KEY  = 'via_cookie_consent';

  var banner = document.querySelector('[data-cookie-banner]');
  var modal  = document.querySelector('[data-cookie-modal]');
  if (modal) { try { modal.setAttribute('aria-hidden', modal.hidden ? 'true' : 'false'); } catch(e){} }
  if (!banner) return;

  var version = banner.getAttribute('data-consent-version') || '1';

  // Share consent cookie across both apex and www (prevents the banner reopening on navigation)
  var COOKIE_DOMAIN = null;
  try {
    var h = location.hostname;
    if (h === 'viaarriano11.it' || h === 'www.viaarriano11.it' || (h && h.endsWith('.viaarriano11.it'))) {
      COOKIE_DOMAIN = 'viaarriano11.it';
    }
  } catch(e) { COOKIE_DOMAIN = null; }


  function safeJsonParse(str){
    try { return JSON.parse(str); } catch(e){ return null; }
  }

  function getCookie(name){
    var match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()\[\]\\\/\+^])/g, '\\$1') + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : '';
  }

  function setCookie(name, value, days){
    var maxAge = days ? (days*24*60*60) : (180*24*60*60);
    var cookie = name + '=' + encodeURIComponent(value) + '; Path=/; Max-Age=' + String(maxAge);
    if (COOKIE_DOMAIN) cookie += '; Domain=' + COOKIE_DOMAIN;
    cookie += '; SameSite=Lax';
    if (location.protocol === 'https:') cookie += '; Secure';
    document.cookie = cookie;
  }

  function readState(){
    // Prefer localStorage
    var raw = null;
    try { raw = window.localStorage ? localStorage.getItem(STORAGE_KEY) : null; } catch(e){ raw = null; }
    var st = raw ? safeJsonParse(raw) : null;
    if (st && st.v === version) return st;

    // Fallback: cookie
    var c = getCookie(COOKIE_KEY);
    var cs = c ? safeJsonParse(c) : null;
    if (cs && cs.v === version) return cs;
    return null;
  }

  function writeState(state){
    if (!state) return;
    try {
      if (window.localStorage) localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    } catch(e){}
    // Always write cookie as a fallback
    try { setCookie(COOKIE_KEY, JSON.stringify(state), 180); } catch(e){}
  }

  function applyState(state){
    // Expose a simple global + event for future optional scripts (analytics, etc.)
    window.__VIA_CONSENT = state;
    try {
      document.dispatchEvent(new CustomEvent('via:consent', { detail: state }));
    } catch(e){}
  }

  function show(el){
    if (!el) return;
    // Use both the property and the attribute for maximum browser/CSS compatibility.
    el.hidden = false;
    try { el.removeAttribute('hidden'); } catch(e){}
    // If previous code forced display:none, clear it.
    try { el.style.display = ''; } catch(e){}
  }

  function hide(el){
    if (!el) return;
    el.hidden = true;
    try { el.setAttribute('hidden', ''); } catch(e){}
    // Force-hide even if CSS accidentally overrides [hidden].
    try { el.style.display = 'none'; } catch(e){}
  }

  function openModal(){
    if (!modal) return;
    // Pre-fill values
    var st = readState() || { v: version, essential: true, analytics: false };
    var chk = modal.querySelector('[data-cookie-analytics]');
    if (chk) chk.checked = !!st.analytics;

    show(modal);
    try { modal.setAttribute('aria-hidden', 'false'); } catch(e){}
    // Basic focus management
    var first = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    if (first && first.focus) first.focus();
  }

  function closeModal(){
    hide(modal);
    try { modal.setAttribute('aria-hidden', 'true'); } catch(e){}
  }

  // Banner buttons
  var btnAccept   = banner.querySelector('[data-cookie-accept]');
  var btnReject   = banner.querySelector('[data-cookie-reject]');
  var btnSettings = banner.querySelector('[data-cookie-settings]');

  if (btnAccept) {
    btnAccept.addEventListener('click', function(e){
      if (e && e.preventDefault) e.preventDefault();
      if (e && e.stopPropagation) e.stopPropagation();

      var st = { v: version, essential: true, analytics: true, ts: Date.now() };
      try { writeState(st); } catch(err) {}
      applyState(st);
      hide(banner);
    });
  }

  if (btnReject) {
    btnReject.addEventListener('click', function(e){
      if (e && e.preventDefault) e.preventDefault();
      if (e && e.stopPropagation) e.stopPropagation();

      var st = { v: version, essential: true, analytics: false, ts: Date.now() };
      try { writeState(st); } catch(err) {}
      applyState(st);
      hide(banner);
    });
  }

  if (btnSettings) {
    btnSettings.addEventListener('click', function(e){
      if (e && e.preventDefault) e.preventDefault();
      if (e && e.stopPropagation) e.stopPropagation();
      openModal();
    });
  }

  // Modal buttons
  if (modal) {
    var btnSave   = modal.querySelector('[data-cookie-save]');
    var btnCancel = modal.querySelector('[data-cookie-cancel]');
    var closeEls  = modal.querySelectorAll('[data-cookie-close]');

    if (btnSave) {
      btnSave.addEventListener('click', function(e){
        if (e && e.preventDefault) e.preventDefault();
        if (e && e.stopPropagation) e.stopPropagation();

        var chk = modal.querySelector('[data-cookie-analytics]');
        var st = { v: version, essential: true, analytics: !!(chk && chk.checked), ts: Date.now() };

        try { writeState(st); } catch(err) {}
        applyState(st);

        // Close UI even if storage is blocked (e.g. strict privacy mode).
        hide(banner);
        closeModal();
      });
    }

    if (btnCancel) {
      btnCancel.addEventListener('click', function(e){
        if (e && e.preventDefault) e.preventDefault();
        if (e && e.stopPropagation) e.stopPropagation();
        closeModal();
      });
    }

    if (closeEls && closeEls.length) {
      closeEls.forEach(function(el){
        el.addEventListener('click', function(e){
          if (e && e.preventDefault) e.preventDefault();
          if (e && e.stopPropagation) e.stopPropagation();
          closeModal();
        });
      });
    }

    // ESC closes
    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape' && modal && !modal.hidden) {
        closeModal();
      }
    });
  }

  // Footer “manage cookies”
  var manageButtons = document.querySelectorAll('[data-cookie-manage]');
  if (manageButtons && manageButtons.length) {
    manageButtons.forEach(function(btn){
      btn.addEventListener('click', function(e){
        if (e && e.preventDefault) e.preventDefault();
        if (e && e.stopPropagation) e.stopPropagation();
        openModal();
      });
    });
  }

  // Init
  var existing = readState();
  if (existing) {
    hide(banner);
    applyState(existing);
  } else {
    // Default state (no optional cookies)
    applyState({ v: version, essential: true, analytics: false });
    show(banner);
  }
})();
