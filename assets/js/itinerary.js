/* Rome itineraries — map, day tabs, levels, selectable stops, save with a code (patch 10, Oct 2026).
   Data: <script type="application/json" id="itinData"> printed by itineraries.php / itinerary-saved.php.
   The schedule rules mirror itin_schedule() in lib/itineraries.php: keep them in sync. */
(function () {
  'use strict';
  var root = document.getElementById('itinTool');
  var dataEl = document.getElementById('itinData');
  if (!root || !dataEl) return;

  var D;
  try { D = JSON.parse(dataEl.textContent || '{}'); } catch (e) { return; }
  var T = D.texts || {}, U = T.ui || {};
  var MODE = root.getAttribute('data-mode') || 'build';
  var LEVELS = ['easy', 'medium', 'hard'];
  var LOCALE = (window.__VIA_I18N && window.__VIA_I18N.locale) || 'it-IT';
  var STORE_KEY = 'via_itin_v1_' + D.n;

  // ---------- helpers ----------
  function fmt(s, vars) {
    s = String(s == null ? '' : s);
    for (var k in vars) if (Object.prototype.hasOwnProperty.call(vars, k)) s = s.split('{' + k + '}').join(String(vars[k]));
    return s;
  }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function el(tag, cls, html) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (html != null) n.innerHTML = html;
    return n;
  }
  function rad(x) { return x * Math.PI / 180; }
  // Walking distance: straight line × 1.3 (same as PHP)
  function km(a, b) {
    var dLat = rad(b[0] - a[0]), dLng = rad(b[1] - a[1]);
    var h = Math.pow(Math.sin(dLat / 2), 2) + Math.cos(rad(a[0])) * Math.cos(rad(b[0])) * Math.pow(Math.sin(dLng / 2), 2);
    return 2 * 6371 * Math.asin(Math.min(1, Math.sqrt(h))) * 1.3;
  }
  function walkMin(k) { return k < 0.05 ? 0 : Math.ceil((k / 4.5 * 60) / 5) * 5; }
  function hhmm(m) {
    m = Math.round(m);
    var h = Math.floor(m / 60) % 24, mm = m % 60;
    return (h < 10 ? '0' : '') + h + ':' + (mm < 10 ? '0' : '') + mm;
  }
  function kmText(k) {
    try { return k.toLocaleString(LOCALE, { maximumFractionDigits: 1, minimumFractionDigits: 1 }) + ' km'; }
    catch (e) { return k.toFixed(1) + ' km'; }
  }
  function durText(m) {
    if (m < 60) return fmt(U.minutes, { min: m });
    return fmt(U.hours, { h: Math.floor(m / 60), min: m % 60 }).replace(/ 0 min$/, '');
  }
  function placePos(id) { var p = D.places[id]; return [p.lat, p.lng]; }
  function stationPos(id) { return D.stations[id]; }
  function cfg(d, lv) { return D.days[d].levels[lv]; }
  function lsGet() { try { return JSON.parse(window.localStorage.getItem(STORE_KEY) || 'null'); } catch (e) { return null; } }
  function lsSet(v) { try { window.localStorage.setItem(STORE_KEY, JSON.stringify(v)); } catch (e) { /* private mode */ } }

  // Stops a visitor can pick on a day (medium first: most "average" durations)
  var poolCache = {};
  function pool(d) {
    if (poolCache[d]) return poolCache[d];
    var out = {}, order = [];
    ['medium', 'easy', 'hard'].forEach(function (lv) {
      cfg(d, lv).stops.forEach(function (s) {
        if (out[s[0]]) return;
        out[s[0]] = { dur: s[1], leg: s[2] || 0, level: lv };
        order.push(s[0]);
      });
    });
    out._order = order;
    return (poolCache[d] = out);
  }
  function params(d, lv, id) {
    var st = cfg(d, lv).stops;
    for (var i = 0; i < st.length; i++) if (st[i][0] === id) return { dur: st[i][1], leg: st[i][2] || 0, level: lv };
    return pool(d)[id] || { dur: 30, leg: 0, level: lv };
  }
  function levelDefault(d, lv) { return cfg(d, lv).stops.map(function (s) { return s[0]; }); }
  function note(d, lv, id) {
    var L = (T.days[d] && T.days[d].levels) || {};
    var n = (L[lv] && L[lv].notes && L[lv].notes[id]) || '';
    if (!n) { var pl = params(d, lv, id).level; n = (L[pl] && L[pl].notes && L[pl].notes[id]) || ''; }
    return n;
  }

  // ---------- state ----------
  var state = { active: 1, days: {} };
  function validDay(d, v) {
    if (!v || LEVELS.indexOf(v.level) < 0 || !Array.isArray(v.stops)) return null;
    var p = pool(d), seen = {};
    var stops = v.stops.filter(function (id) { if (!p[id] || seen[id]) return false; seen[id] = 1; return true; });
    return { level: v.level, order: stops };
  }
  (function init() {
    var src = null;
    if (D.plan && D.plan.days) src = D.plan.days;
    else if (MODE === 'build') { var ls = lsGet(); if (ls && Array.isArray(ls.days)) src = ls.days; }
    for (var d = 1; d <= D.n; d++) {
      var v = src ? validDay(d, src[d - 1]) : null;
      state.days[d] = v || { level: 'medium', order: levelDefault(d, 'medium') };
    }
  })();

  // Same rules as itin_schedule() in PHP
  function schedule(d) {
    var S = state.days[d], C = cfg(d, S.level);
    var parts = C.start.split(':');
    var start = parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
    var t = start + C.go, pos = stationPos(C.from), total = 0, items = [];
    S.order.forEach(function (id) {
      var P = params(d, S.level, id), here = placePos(id), wk = 0, wm = 0, tr = 0;
      if (P.leg > 0 && items.length) { tr = P.leg; t += tr; }
      else { wk = km(pos, here); wm = walkMin(wk); total += wk; t += wm; }
      total += D.places[id].inner || 0;
      items.push({ id: id, arrive: t, dur: P.dur, walkKm: wk, walkMin: wm, transit: tr });
      t += P.dur;
      pos = here;
    });
    var endPos = stationPos(C.to), lw = km(pos, endPos);
    total += lw; t += walkMin(lw);
    return { start: start, home: t + C.back, km: total, items: items, lastWalk: walkMin(lw), lastKm: lw };
  }

  // Add a stop where it lengthens the walk the least
  function insertCheapest(d, id) {
    var S = state.days[d], C = cfg(d, S.level);
    var pts = [stationPos(C.from)].concat(S.order.map(placePos), [stationPos(C.to)]);
    var p = placePos(id), best = 0, bestCost = Infinity;
    for (var i = 0; i < pts.length - 1; i++) {
      var c = km(pts[i], p) + km(p, pts[i + 1]) - km(pts[i], pts[i + 1]);
      if (c < bestCost) { bestCost = c; best = i; }
    }
    S.order.splice(best, 0, id);
  }

  // ---------- map ----------
  var map = null, layer = null;
  function initMap() {
    if (map || typeof window.L === 'undefined') return;
    var L = window.L;
    map = L.map('itinMap', { scrollWheelZoom: false, zoomControl: true, tap: true });
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>'
    }).addTo(map);
    map.attributionControl.setPrefix('<a href="https://leafletjs.com" target="_blank" rel="noopener">Leaflet</a>');
    layer = L.layerGroup().addTo(map);
    map.setView([41.895, 12.49], 13);
  }
  function pin(cls, html, size) {
    return window.L.divIcon({ className: 'itin-pin ' + cls, html: html, iconSize: [size, size], iconAnchor: [size / 2, size / 2] });
  }
  function drawMap(d) {
    if (!map) return;
    var L = window.L, S = state.days[d], C = cfg(d, S.level), sc = schedule(d);
    layer.clearLayers();
    var from = stationPos(C.from), to = stationPos(C.to), home = D.home;
    var bounds = [from, to];
    // Transit from home and back (dashed)
    L.polyline([home, from], { color: '#4F5A68', weight: 3, dashArray: '6 8', opacity: .8 }).addTo(layer);
    L.polyline([to, home], { color: '#4F5A68', weight: 3, dashArray: '6 8', opacity: .8 }).addTo(layer);
    // Walking path, broken where a leg is by bus/tram/taxi
    var seg = [from];
    sc.items.forEach(function (it) {
      var p = placePos(it.id);
      if (it.transit) {
        if (seg.length > 1) L.polyline(seg, { color: '#18905C', weight: 5, opacity: .9 }).addTo(layer);
        L.polyline([seg[seg.length - 1], p], { color: '#4F5A68', weight: 3, dashArray: '6 8', opacity: .8 }).addTo(layer);
        seg = [p];
      } else seg.push(p);
      bounds.push(p);
    });
    seg.push(to);
    L.polyline(seg, { color: '#18905C', weight: 5, opacity: .9 }).addTo(layer);

    L.marker(home, { icon: pin('itin-pin-home', '<span aria-hidden="true">⌂</span>', 30), title: U.from_home, keyboard: false })
      .bindPopup('<strong>Via Arriano 11</strong>').addTo(layer);
    [[C.from, from], [C.to, to]].forEach(function (s) {
      L.marker(s[1], { icon: pin('itin-pin-station', '<span aria-hidden="true">M</span>', 22), title: T.stations[s[0]] || '', keyboard: false })
        .bindPopup(esc(T.stations[s[0]] || '')).addTo(layer);
    });
    sc.items.forEach(function (it, i) {
      var P = D.places[it.id];
      var m = L.marker(placePos(it.id), { icon: pin(P.meal ? 'itin-pin-meal' : '', '<span>' + (i + 1) + '</span>', 30), title: P.name, riseOnHover: true })
        .bindPopup('<strong>' + esc(P.name) + '</strong><br>' + hhmm(it.arrive) + ' · ' + esc(durText(it.dur)))
        .addTo(layer);
      m.on('click', function () { highlight(it.id); });
    });
    map.fitBounds(bounds, { padding: [28, 28], maxZoom: 16 });
  }
  function highlight(id) {
    var li = root.querySelector('[data-stop-id="' + id + '"]');
    if (!li) return;
    li.classList.add('is-hl');
    setTimeout(function () { li.classList.remove('is-hl'); }, 1600);
    if (window.innerWidth < 900) li.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  // ---------- render ----------
  function render() {
    var d = state.active;
    root.querySelectorAll('[data-day]').forEach(function (b) {
      b.setAttribute('aria-selected', String(parseInt(b.getAttribute('data-day'), 10) === d));
    });
    root.querySelectorAll('[data-day-panel]').forEach(function (p) {
      p.hidden = parseInt(p.getAttribute('data-day-panel'), 10) !== d;
    });
    if (MODE === 'build') renderPanel(d);
    renderSummary(d);
    drawMap(d);
    if (MODE === 'build') lsSet({ days: planDays() });
  }

  function renderSummary(d) {
    var box = document.getElementById('itinSummary');
    if (!box) return;
    var sc = schedule(d);
    box.innerHTML =
      '<span><strong>' + hhmm(sc.start) + '</strong> ' + esc(U.summary_start) + '</span>' +
      '<span><strong>' + sc.items.length + '</strong> ' + esc(U.summary_stops) + '</span>' +
      '<span><strong>' + esc(kmText(sc.km)) + '</strong> ' + esc(U.summary_km) + '</span>' +
      '<span>' + esc(U.summary_home) + ' <strong>' + hhmm(sc.home) + '</strong></span>';
  }

  function legHtml(it) {
    if (it.transit) return '<div class="itin-leg itin-leg-transit">' + esc(fmt(U.transit, { min: it.transit })) + '</div>';
    if (!it.walkMin) return '';
    return '<div class="itin-leg">' + esc(fmt(U.walk, { min: it.walkMin })) + ' · ' + esc(kmText(it.walkKm)) + '</div>';
  }

  function renderPanel(d) {
    var panel = root.querySelector('[data-day-panel="' + d + '"]');
    if (!panel) return;
    var S = state.days[d], L = T.days[d].levels[S.level], sc = schedule(d);

    panel.querySelectorAll('.itin-level').forEach(function (b) {
      b.setAttribute('aria-checked', String(b.getAttribute('data-level') === S.level));
    });

    var box = panel.querySelector('[data-stops]');
    var h = '';
    h += '<p class="itin-go"><span class="itin-time">' + hhmm(sc.start) + '</span> <strong>' + esc(U.from_home) + '.</strong> ' + esc(L.go) + '</p>';
    h += '<p class="itin-help small muted">' + esc(U.stops_help) + '</p>';
    if (!sc.items.length) h += '<p class="itin-empty">' + esc(U.empty) + '</p>';
    h += '<ol class="itin-list">';
    sc.items.forEach(function (it, i) {
      var P = D.places[it.id], n = note(d, S.level, it.id);
      h += '<li class="itin-stop' + (P.meal ? ' is-meal' : '') + '" data-stop-id="' + esc(it.id) + '">';
      h += legHtml(it);
      h += '<div class="itin-stop-card">';
      h += '<div class="itin-stop-head"><span class="itin-num" aria-hidden="true">' + (i + 1) + '</span>';
      h += '<span class="itin-time">' + hhmm(it.arrive) + '</span>';
      h += '<span class="itin-stop-name">' + esc(P.name) + '</span></div>';
      h += '<div class="itin-stop-meta"><span>' + esc(durText(it.dur)) + '</span>';
      if (P.book) h += '<span class="itin-badge">' + esc(U.book_badge) + '</span>';
      if (P.meal) h += '<span class="itin-badge itin-badge-meal">' + esc(U.meal_badge) + '</span>';
      h += '</div>';
      if (n) h += '<p class="itin-note">' + esc(n) + '</p>';
      h += '<div class="itin-stop-actions">';
      if (P.guide && T.guides[P.guide]) h += '<button type="button" class="itin-act itin-act-guide" data-guide="' + esc(P.guide) + '">' + esc(U.guide_open) + '</button>';
      h += '<button type="button" class="itin-act" data-move="-1" data-id="' + esc(it.id) + '"' + (i === 0 ? ' disabled' : '') + ' aria-label="' + esc(U.move_up + ': ' + P.name) + '">↑</button>';
      h += '<button type="button" class="itin-act" data-move="1" data-id="' + esc(it.id) + '"' + (i === sc.items.length - 1 ? ' disabled' : '') + ' aria-label="' + esc(U.move_down + ': ' + P.name) + '">↓</button>';
      h += '<button type="button" class="itin-act itin-act-remove" data-remove="' + esc(it.id) + '" aria-label="' + esc(U.remove + ': ' + P.name) + '">' + esc(U.remove) + '</button>';
      h += '</div></div></li>';
    });
    h += '</ol>';
    if (sc.lastWalk) h += '<div class="itin-leg">' + esc(fmt(U.walk, { min: sc.lastWalk })) + ' · ' + esc(kmText(sc.lastKm)) + '</div>';
    h += '<p class="itin-back"><span class="itin-time">' + hhmm(sc.home) + '</span> <strong>' + esc(U.back_home) + '.</strong> ' + esc(L.back) + '</p>';
    h += '<p class="itin-evening"><strong>' + esc(U.evening) + ':</strong> ' + esc(L.evening) + '</p>';

    // Stops not selected
    var p = pool(d), extra = p._order.filter(function (id) { return S.order.indexOf(id) < 0; });
    h += '<div class="itin-extra"><h3 class="itin-extra-title">' + esc(U.extra_title) + '</h3>';
    if (!extra.length) h += '<p class="small muted">' + esc(U.extra_none) + '</p>';
    else {
      h += '<ul class="itin-extra-list">';
      extra.forEach(function (id) {
        var P = D.places[id];
        h += '<li><button type="button" class="itin-add" data-add="' + esc(id) + '"><span aria-hidden="true">+</span> ' + esc(P.name) +
          ' <span class="muted">· ' + esc(durText(p[id].dur)) + '</span></button>' +
          (P.guide && T.guides[P.guide] ? ' <button type="button" class="linklike small" data-guide="' + esc(P.guide) + '">' + esc(U.guide_open) + '</button>' : '') + '</li>';
      });
      h += '</ul>';
    }
    h += '<button type="button" class="linklike small itin-reset" data-reset>' + esc(U.reset) + '</button></div>';

    // Tickets to book (all days)
    var book = bookList();
    if (book.length) {
      h += '<div class="itin-book"><strong>' + esc(U.book_list) + ':</strong> ' + book.map(function (id) { return esc(D.places[id].name); }).join(', ') + '.</div>';
    }
    box.innerHTML = h;
  }

  function bookList() {
    var seen = {}, out = [];
    for (var d = 1; d <= D.n; d++) state.days[d].order.forEach(function (id) {
      var P = D.places[id];
      if (!P.book) return;
      var key = P.guide || id;
      if (seen[key]) return;
      seen[key] = 1; out.push(id);
    });
    return out;
  }

  function planDays() {
    var out = [];
    for (var d = 1; d <= D.n; d++) out.push({ level: state.days[d].level, stops: state.days[d].order.slice() });
    return out;
  }

  // ---------- guide dialog ----------
  var dlg = document.getElementById('itinGuide');
  function openGuide(key) {
    var g = T.guides[key];
    if (!g || !dlg) return;
    var body = dlg.querySelector('[data-guide-body]');
    var h = '<h2 id="itinGuideTitle">' + esc(g.name) + '</h2>';
    h += '<p>' + esc(g.brief) + '</p>';
    if (g.see && g.see.length) h += '<h3>' + esc(U.guide_see) + '</h3><ul>' + g.see.map(function (s) { return '<li>' + esc(s) + '</li>'; }).join('') + '</ul>';
    h += '<p><strong>' + esc(U.guide_ticket) + '.</strong> ' + esc(g.ticket) + '</p>';
    h += '<p><strong>' + esc(U.guide_time) + '.</strong> ' + esc(g.time) + '</p>';
    if (g.tip) h += '<p class="itin-guide-tip"><strong>' + esc(U.guide_tip) + '.</strong> ' + esc(g.tip) + '</p>';
    body.innerHTML = h;
    if (typeof dlg.showModal === 'function') dlg.showModal(); else dlg.setAttribute('open', '');
  }
  function closeGuide() {
    if (!dlg) return;
    if (typeof dlg.close === 'function') dlg.close(); else dlg.removeAttribute('open');
  }
  if (dlg) {
    dlg.addEventListener('click', function (e) {
      if (e.target === dlg || e.target.closest('[data-guide-close]')) closeGuide();
    });
  }

  // ---------- events ----------
  root.addEventListener('click', function (e) {
    var t = e.target.closest('button');
    if (!t || !root.contains(t)) return;
    var d = state.active, S = state.days[d];
    if (t.hasAttribute('data-day')) {
      state.active = parseInt(t.getAttribute('data-day'), 10);
      render();
      if (map) setTimeout(function () { map.invalidateSize(); drawMap(state.active); }, 0);
      return;
    }
    if (MODE !== 'build') return;
    if (t.hasAttribute('data-level')) {
      var lv = t.getAttribute('data-level');
      state.days[d] = { level: lv, order: levelDefault(d, lv) };
      render(); return;
    }
    if (t.hasAttribute('data-guide')) { openGuide(t.getAttribute('data-guide')); return; }
    if (t.hasAttribute('data-remove')) {
      S.order = S.order.filter(function (id) { return id !== t.getAttribute('data-remove'); });
      render(); return;
    }
    if (t.hasAttribute('data-add')) { insertCheapest(d, t.getAttribute('data-add')); render(); highlight(t.getAttribute('data-add')); return; }
    if (t.hasAttribute('data-move')) {
      var id = t.getAttribute('data-id'), i = S.order.indexOf(id), j = i + parseInt(t.getAttribute('data-move'), 10);
      if (i < 0 || j < 0 || j >= S.order.length) return;
      S.order.splice(i, 1); S.order.splice(j, 0, id);
      render();
      var again = root.querySelector('[data-stop-id="' + id + '"] [data-move="' + t.getAttribute('data-move') + '"]');
      if (again && !again.disabled) again.focus();
      return;
    }
    if (t.hasAttribute('data-reset')) { state.days[d] = { level: S.level, order: levelDefault(d, S.level) }; render(); return; }
  });

  // Keyboard: arrows move between day tabs
  root.addEventListener('keydown', function (e) {
    var t = e.target;
    if (!t.hasAttribute || !t.hasAttribute('data-day') || (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft')) return;
    var n = state.active + (e.key === 'ArrowRight' ? 1 : -1);
    if (n < 1 || n > D.n) return;
    state.active = n; render();
    var b = root.querySelector('[data-day="' + n + '"]'); if (b) b.focus();
  });

  // ---------- save ----------
  var saveBox = document.getElementById('itinSave');
  if (saveBox && MODE === 'build') {
    saveBox.hidden = false;
    var btn = saveBox.querySelector('[data-save]'), out = saveBox.querySelector('[data-save-result]');
    btn.addEventListener('click', function () {
      btn.disabled = true;
      out.innerHTML = '<p class="small muted">' + esc(U.saving) + '</p>';
      fetch(D.saveUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ n: D.n, days: planDays(), lang: D.lang })
      }).then(function (r) { return r.json().catch(function () { return { ok: false }; }).then(function (j) { j.status = r.status; return j; }); })
        .then(function (j) {
          btn.disabled = false;
          if (!j || !j.ok) { out.innerHTML = '<p class="itin-error" role="alert">' + esc(j && j.status === 429 ? U.save_limit : U.save_error) + '</p>'; return; }
          var url = location.origin + j.url;
          out.innerHTML =
            '<p class="itin-saved-ok">' + esc(U.saved_ok) + '</p>' +
            '<p class="itin-code">' + esc(j.code) + '</p>' +
            '<div class="actions">' +
            '<a class="btn primary" href="' + esc(j.url) + '">' + esc(U.saved_open) + '</a>' +
            '<button type="button" class="btn" data-copy="' + esc(url) + '">' + esc(U.copy) + '</button>' +
            '<a class="btn btn-wa" target="_blank" rel="noopener" href="https://wa.me/?text=' + encodeURIComponent(fmt(U.share_text, { url: url })) + '">' + esc(U.share_wa) + '</a>' +
            '</div>';
          try { window.localStorage.setItem('via_itin_last_code', j.code); } catch (e) { /* ignore */ }
          if (typeof window.gtag === 'function') window.gtag('event', 'itinerary_saved', { days: D.n });
        })
        .catch(function () { btn.disabled = false; out.innerHTML = '<p class="itin-error" role="alert">' + esc(U.save_error) + '</p>'; });
    });
  }

  // Copy link buttons (builder result and saved page)
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-copy]');
    if (!b) return;
    var txt = b.getAttribute('data-copy'), label = b.textContent;
    function done() { b.textContent = U.copied; setTimeout(function () { b.textContent = label; }, 1800); }
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(txt).then(done, function () { window.prompt('', txt); });
    else window.prompt('', txt);
  });
  // Print button (saved page)
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-print]');
    if (b) window.print();
  });

  // ---------- go ----------
  initMap();
  render();
})();
