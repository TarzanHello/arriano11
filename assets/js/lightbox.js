(function(){
  function qsa(sel, root){ return Array.prototype.slice.call((root||document).querySelectorAll(sel)); }

  // Support two markup styles:
  // 1) <a data-lightbox="group" href="...">...</a>
  // 2) <div data-lightbox> <a href="...">...</a> ... </div>
  // We always intercept clicks to avoid opening the raw file URL.

  var I = window.__VIA_I18N || {};
  var UI = (I.strings && I.strings.ui) ? I.strings.ui : {};

  function tmpl(str, vars){
    str = String(str || '');
    if(!vars) return str;
    Object.keys(vars).forEach(function(k){
      str = str.split('{' + k + '}').join(String(vars[k]));
    });
    return str;
  }

  function ui(key, vars){
    var s = UI[key] || '';
    return tmpl(s, vars);
  }

  var containers = qsa('[data-lightbox]');
  if(!containers.length) return;

  // Build groups
  var groups = {};
  containers.forEach(function(c){
    // If the container is an anchor itself, treat it as a single-item group.
    if(c.tagName && c.tagName.toLowerCase() === 'a'){
      var g = c.getAttribute('data-lightbox') || 'default';
      groups[g] = groups[g] || [];
      groups[g].push(c);
      return;
    }
    var g2 = c.getAttribute('data-lightbox') || 'default';
    var items = qsa('a[href]', c);
    if(!items.length) return;
    groups[g2] = groups[g2] || [];
    items.forEach(function(a){ groups[g2].push(a); });
  });

  // Flatten clickable links
  var links = Object.keys(groups).reduce(function(arr, k){
    return arr.concat(groups[k]);
  }, []);
  if(!links.length) return;

  var overlay = document.createElement('div');
  overlay.className = 'lightbox';
  overlay.innerHTML =
    '<div class="lightbox-inner" role="dialog" aria-modal="true">' +
      '<button class="lightbox-close" type="button" aria-label="' + ui('close') + '">×</button>' +
      '<button class="lightbox-nav lightbox-prev" type="button" aria-label="' + ui('previous_photo') + '">‹</button>' +
      '<img class="lightbox-img" alt="" />' +
      '<button class="lightbox-nav lightbox-next" type="button" aria-label="' + ui('next_photo') + '">›</button>' +
    '</div>';
  document.body.appendChild(overlay);

  var img = overlay.querySelector('.lightbox-img');
  var closeBtn = overlay.querySelector('.lightbox-close');
  var prevBtn = overlay.querySelector('.lightbox-prev');
  var nextBtn = overlay.querySelector('.lightbox-next');

  var items = [];
  var index = 0;
  var isOpen = false;

  function setImage(){
    if(!items.length) return;
    var href = items[index].href;
    img.src = href;
    img.alt = items[index].getAttribute('data-alt') || items[index].getAttribute('aria-label') || '';
  }

  function updateNav(){
    var show = items.length > 1;
    prevBtn.style.display = show ? '' : 'none';
    nextBtn.style.display = show ? '' : 'none';
  }

  function openAt(groupItems, idx){
    items = groupItems;
    index = idx;
    isOpen = true;
    updateNav();
    setImage();
    overlay.classList.add('is-open');
    document.body.classList.add('no-scroll');
  }

  function close(){
    if(!isOpen) return;
    isOpen = false;
    overlay.classList.remove('is-open');
    document.body.classList.remove('no-scroll');
    img.src = '';
  }

  function prev(){
    if(items.length <= 1) return;
    index = (index - 1 + items.length) % items.length;
    setImage();
  }

  function next(){
    if(items.length <= 1) return;
    index = (index + 1) % items.length;
    setImage();
  }

  // Open on click (always prevent default navigation to the image file)
  links.forEach(function(link){
    link.addEventListener('click', function(e){
      // Only intercept normal left-click / tap
      if(e && (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey)) return;
      e.preventDefault();

      // Determine group
      var group = 'default';
      var explicit = link.getAttribute('data-lightbox');
      if(explicit) group = explicit;
      else {
        var parent = link.closest ? link.closest('[data-lightbox]') : null;
        if(parent){ group = parent.getAttribute('data-lightbox') || 'default'; }
      }

      var groupItems = groups[group] || [link];
      var idx = groupItems.indexOf(link);
      if(idx < 0) idx = 0;
      openAt(groupItems, idx);
    }, {passive:false});
  });

  // Close interactions
  closeBtn.addEventListener('click', close);
  overlay.addEventListener('click', function(e){
    if(e.target === overlay) close();
  });

  // Navigation
  prevBtn.addEventListener('click', function(e){ e.stopPropagation(); prev(); });
  nextBtn.addEventListener('click', function(e){ e.stopPropagation(); next(); });

  // Keyboard
  document.addEventListener('keydown', function(e){
    if(!isOpen) return;
    if(e.key === 'Escape') close();
    if(e.key === 'ArrowLeft') prev();
    if(e.key === 'ArrowRight') next();
  });

  // Touch swipe (basic)
  var touchStartX = null;
  overlay.addEventListener('touchstart', function(e){
    if(!isOpen) return;
    if(!e.touches || !e.touches.length) return;
    touchStartX = e.touches[0].clientX;
  }, {passive:true});

  overlay.addEventListener('touchend', function(e){
    if(!isOpen) return;
    if(touchStartX === null) return;
    var x = (e.changedTouches && e.changedTouches[0]) ? e.changedTouches[0].clientX : null;
    if(x === null) { touchStartX = null; return; }
    var dx = x - touchStartX;
    touchStartX = null;
    if(Math.abs(dx) < 50) return;
    if(dx > 0) prev(); else next();
  }, {passive:true});
})();
