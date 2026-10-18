(function(){
  var cfg = window.__VIA_I18N || {};
  var urls = cfg.urls || {};
  var supported = cfg.supported || Object.keys(urls);
  var fallback = cfg.fallback || 'en';
  var current = (cfg.lang || '').toLowerCase();

  function normalize(code){
    code = (code || '').toLowerCase().trim();
    if(!code) return '';
    code = code.replace('_', '-');
    var base = code.split('-')[0];
    // Common language family mapping
    if(base === 'zh') return 'zh';
    if(base === 'ja') return 'ja';
    if(base === 'ko') return 'ko';
    if(base === 'ru') return 'ru';
    if(base === 'es') return 'es';
    if(base === 'fr') return 'fr';
    if(base === 'en') return 'en';
    if(base === 'it') return 'it';
    if(base === 'de') return 'de';
    // Swedish browsers usually use "sv"; this project uses the /se prefix.
    if(base === 'sv') return 'se';
    if(base === 'se') return 'se';
    return base;
  }

  function isBot(){
    var ua = (navigator.userAgent || '').toLowerCase();
    return /bot|crawl|spider|slurp|bingpreview|facebookexternalhit|embedly|quora|pinterest|telegrambot|whatsapp|discordbot/.test(ua);
  }

  function getStored(){
    try{
      return (localStorage.getItem('via_lang') || '').toLowerCase();
    }catch(e){
      return '';
    }
  }

  function setStored(l){
    try{
      localStorage.setItem('via_lang', l);
    }catch(e){
      // ignore
    }
  }

  function detectBrowser(){
    var cand = '';
    try{
      var langs = (navigator.languages && navigator.languages.length) ? navigator.languages : [navigator.language];
      cand = (langs && langs.length) ? langs[0] : (navigator.language || '');
    }catch(e){
      cand = (navigator.language || '');
    }

    var base = normalize(cand);
    if(base && supported.indexOf(base) >= 0) return base;
    return fallback;
  }

  var stored = getStored();
  if(stored && supported.indexOf(stored) < 0) stored = '';

  // If a user lands directly on a language-prefixed URL, remember it.
  if(!stored && current && supported.indexOf(current) >= 0 && cfg.prefixed === true){
    setStored(current);
    stored = current;
  }

  var preferred = stored || detectBrowser();

  // Auto redirect:
  // - keep a user's language preference across visits on unprefixed URLs (/, /booking, ...)
  // - never override an explicit language-prefixed URL (/en/..., /de/..., /se/..., ...)
  // Note: we avoid redirects for crawlers / link previews.
  if(!isBot() && preferred && preferred !== current && urls[preferred]){
    var shouldRedirect = (cfg.prefixed === false);

    if(shouldRedirect){
      try{
        if(window.location && window.location.pathname && window.location.pathname !== urls[preferred]){
          window.location.replace(urls[preferred]);
          return;
        }
      }catch(e){
        window.location.replace(urls[preferred]);
        return;
      }
    }
  }

  // Persist language choice when user clicks a language link
  document.addEventListener('click', function(e){
    var target = e.target;
    if(!target || !target.closest) return;
    var a = target.closest('a[data-lang]');
    if(!a) return;
    var l = (a.getAttribute('data-lang') || '').toLowerCase();
    if(l && supported.indexOf(l) >= 0){
      setStored(l);
    }
  }, true);

  // Dropdown behavior
  var sw = document.querySelector('[data-lang-switch]');
  if(!sw) return;
  var btn = sw.querySelector('[data-lang-btn]');
  var menu = sw.querySelector('[data-lang-menu]');
  if(!btn || !menu) return;

  function open(){
    sw.classList.add('is-open');
    btn.setAttribute('aria-expanded', 'true');
  }

  function close(){
    sw.classList.remove('is-open');
    btn.setAttribute('aria-expanded', 'false');
  }

  btn.addEventListener('click', function(ev){
    ev.preventDefault();
    ev.stopPropagation();
    if(sw.classList.contains('is-open')) close(); else open();
  });

  document.addEventListener('click', function(ev){
    if(!sw.classList.contains('is-open')) return;
    if(sw.contains(ev.target)) return;
    close();
  });

  document.addEventListener('keydown', function(ev){
    if(!sw.classList.contains('is-open')) return;
    if(ev.key === 'Escape') close();
  });
})();
