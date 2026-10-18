// Admin helpers.
// FIX: the old inline onsubmit="return confirm(...)" handlers were blocked by the
// site's Content-Security-Policy, so "Elimina recensione" and "Disattiva 2FA"
// ran immediately without asking. Forms now use data-confirm="..." instead.
(function(){
  document.addEventListener('submit', function(e){
    var form = e.target;
    if(!form || !form.getAttribute) return;
    var msg = form.getAttribute('data-confirm');
    if(msg && !window.confirm(msg)){
      e.preventDefault();
    }
  }, true);
})();
