(function(){
  var btn = document.getElementById('navToggle');
  var nav = document.getElementById('siteNav');
  if(btn && nav){
    btn.addEventListener('click', function(){
      document.body.classList.toggle('nav-open');
    });

    // Close on link click (mobile)
    nav.addEventListener('click', function(ev){
      var t = ev.target;
      if(t && t.tagName === 'A'){
        document.body.classList.remove('nav-open');
      }
    });
  }
})();

(function(){
  // Remove broken thumbnails in the apartment gallery (prevents "broken image" tiles).
  function closest(el, sel){
    while(el && el.nodeType === 1){
      if(el.matches && el.matches(sel)) return el;
      el = el.parentElement;
    }
    return null;
  }

  // Capture phase so we also catch errors from lazy-loaded images.
  document.addEventListener('error', function(e){
    var img = e && e.target;
    if(!img || !img.tagName || img.tagName.toUpperCase() !== 'IMG') return;

    // Only act inside the gallery grid.
    var gallery = closest(img, '.gallery');
    if(!gallery) return;

    // Remove the entire tile (anchor) to avoid empty boxes.
    var a = closest(img, 'a');
    if(a && a.parentNode){
      a.parentNode.removeChild(a);
      return;
    }
    if(img.parentNode){
      img.parentNode.removeChild(img);
    }
  }, true);
})();
