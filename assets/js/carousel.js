(function(){
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

  function initCarousel(root){
    var track = root.querySelector('.carousel-track');
    if(!track) return;

    var slides = Array.prototype.slice.call(root.querySelectorAll('.carousel-slide'));
    if(!slides.length) return;

    var prev = root.querySelector('.carousel-btn.prev');
    var next = root.querySelector('.carousel-btn.next');
    var dotsWrap = root.querySelector('.carousel-dots');

    var index = 0;
    var timer = null;

    function setIndex(i){
      index = (i + slides.length) % slides.length;

      // translateX(%) is relative to the track width.
      // Since we size the track to slides.length * 100%, we must move by
      // (100 / slides.length)% per slide, otherwise it will jump all slides.
      var offset = -index * (100 / slides.length);
      track.style.transform = 'translateX(' + offset + '%)';

      if(dotsWrap){
        Array.prototype.slice.call(dotsWrap.children).forEach(function(dot, idx){
          if(idx === index) dot.classList.add('is-active');
          else dot.classList.remove('is-active');
        });
      }
    }

    function start(){
      stop();
      timer = setInterval(function(){ setIndex(index + 1); }, 5000);
    }

    function stop(){
      if(timer){ clearInterval(timer); timer = null; }
    }

    if(dotsWrap){
      dotsWrap.innerHTML = '';
      slides.forEach(function(_, idx){
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'carousel-dot';
        var label = ui('go_to_photo', {n: (idx+1)});
        if(!label) label = 'Go to photo ' + (idx+1);
        b.setAttribute('aria-label', label);
        b.addEventListener('click', function(){ setIndex(idx); start(); });
        dotsWrap.appendChild(b);
      });
    }

    if(prev){ prev.addEventListener('click', function(){ setIndex(index - 1); start(); }); }
    if(next){ next.addEventListener('click', function(){ setIndex(index + 1); start(); }); }

    // Pause autoplay when hovered (desktop)
    root.addEventListener('mouseenter', stop);
    root.addEventListener('mouseleave', start);

    // Mobile usability: swipe support
    var startX = 0;
    var startY = 0;
    var isDown = false;
    var moved = false;

    root.addEventListener('touchstart', function(e){
      if(!e.touches || e.touches.length !== 1) return;
      var t = e.touches[0];
      startX = t.clientX;
      startY = t.clientY;
      isDown = true;
      moved = false;
      stop();
    }, {passive:true});

    root.addEventListener('touchmove', function(e){
      if(!isDown || !e.touches || e.touches.length !== 1) return;
      var t = e.touches[0];
      var dx = t.clientX - startX;
      var dy = t.clientY - startY;

      // Only lock scroll if the gesture is mainly horizontal
      if(Math.abs(dx) > 14 && Math.abs(dx) > Math.abs(dy) * 1.2){
        moved = true;
        e.preventDefault();
      }
    }, {passive:false});

    root.addEventListener('touchend', function(e){
      if(!isDown){ return; }
      isDown = false;
      if(!moved){ start(); return; }

      // Use changedTouches for end position
      var t = (e.changedTouches && e.changedTouches[0]) ? e.changedTouches[0] : null;
      if(!t){ start(); return; }

      var dx = t.clientX - startX;
      var dy = t.clientY - startY;

      // Swipe threshold
      if(Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy) * 1.2){
        if(dx < 0) setIndex(index + 1);
        else setIndex(index - 1);
      }
      start();
    }, {passive:true});

    // Ensure the track is laid out as full-width slides
    track.style.width = (slides.length * 100) + '%';
    slides.forEach(function(s){ s.style.width = (100 / slides.length) + '%'; });

    setIndex(0);
    start();
  }

  document.querySelectorAll('[data-carousel]').forEach(function(root){
    initCarousel(root);
  });
})();
