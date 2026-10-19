/* Rome itineraries — map, day tabs, levels, selectable stops, save with a code (patch 10, Oct 2026).
   Patch 12: the visitor's own stops (private), "Avvia percorso" (GPS + Google/Apple Maps), game (points, badges).
   Data: <script type="application/json" id="itinData"> printed by itineraries.php / itinerary-saved.php.
   The schedule rules mirror itin_schedule() in lib/itineraries.php and the arrival radius mirrors
   itin_game_radius() in lib/itin_game.php: keep them in sync. */
(function () {
  'use strict';
  var root = document.getElementById('itinTool');
  var dataEl = document.getElementById('itinData');
  if (!root || !dataEl) return;

  var D;
  try { D = JSON.parse(dataEl.textContent || '{}'); } catch (e) { return; }
  var T = D.texts || {}, U = T.ui || {}, BADGES = T.badges || {};
  var MODE = root.getAttribute('data-mode') || 'build';
  var LEVELS = ['easy', 'medium', 'hard'];
  var LOCALE = (window.__VIA_I18N && window.__VIA_I18N.locale) || 'it-IT';
  var STORE_KEY = 'via_itin_v1_' + D.n;
  var CUSTOM_MAX = 12, CUSTOM_DURS = [15, 30, 45, 60, 90, 120];
  if (!T.guides || typeof T.guides !== 'object' || Array.isArray(T.guides)) T.guides = {};
  if (!T.days) T.days = {};
  if (!D.badgeIcons) D.badgeIcons = {};

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
  function isObj(o) { return !!o && typeof o === 'object' && !Array.isArray(o); }
  function rad(x) { return x * Math.PI / 180; }
  // Straight-line distance in metres
  function distM(a, b) {
    var dLat = rad(b[0] - a[0]), dLng = rad(b[1] - a[1]);
    var h = Math.pow(Math.sin(dLat / 2), 2) + Math.cos(rad(a[0])) * Math.cos(rad(b[0])) * Math.pow(Math.sin(dLng / 2), 2);
    return 2 * 6371000 * Math.asin(Math.min(1, Math.sqrt(h)));
  }
  // Walking distance: straight line × 1.3 (same as PHP)
  function km(a, b) { return distM(a, b) / 1000 * 1.3; }
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
  function distText(m) { return m < 950 ? (Math.max(10, Math.round(m / 10) * 10) + ' m') : kmText(m / 1000); }
  function durText(m) {
    if (m < 60) return fmt(U.minutes, { min: m });
    return fmt(U.hours, { h: Math.floor(m / 60), min: m % 60 }).replace(/ 0 min$/, '');
  }
  function inRome(lat, lng) { return lat >= 41.65 && lat <= 42.10 && lng >= 12.20 && lng <= 12.90; }
  function placePos(id) { var p = D.places[id]; return [p.lat, p.lng]; }
  function stationPos(id) { return D.stations[id]; }
  function cfg(d, lv) { return D.days[d].levels[lv]; }
  function lsGet(k) { try { return JSON.parse(window.localStorage.getItem(k) || 'null'); } catch (e) { return null; } }
  function lsSet(k, v) { try { window.localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* private mode */ } }
  function post(url, body) {
    return fetch(url, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify(body)
    }).then(function (r) {
      return r.json().catch(function () { return { ok: false }; }).then(function (j) { j.status = r.status; return j; });
    }).catch(function () { return null; });
  }
  function track(name, params) { if (typeof window.gtag === 'function') window.gtag('event', name, params || {}); }

  // ---------- the visitor's own stops ----------
  function addCustomPlace(id, c) {
    D.places[id] = {
      lat: +c.lat, lng: +c.lng, name: String(c.name || ''), tip: String(c.tip || ''),
      dur: CUSTOM_DURS.indexOf(+c.dur) >= 0 ? +c.dur : 30, day: +c.day,
      guide: null, book: false, meal: false, inner: 0, custom: true
    };
  }
  function customIds() { return Object.keys(D.places).filter(function (id) { return D.places[id].custom; }); }
  function customExport() {
    var o = {};
    customIds().forEach(function (id) {
      var P = D.places[id];
      o[id] = { day: P.day, name: P.name, tip: P.tip, lat: P.lat, lng: P.lng, dur: P.dur };
    });
    return o;
  }
  function nextCustomId() { for (var k = 1; k < 100; k++) if (!D.places['u' + k]) return 'u' + k; return ''; }

  // Stops a visitor can pick on a day (medium first: most "average" durations), then their own stops
  var poolCache = {};
  function resetPool() { poolCache = {}; }
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
    customIds().forEach(function (id) {
      var P = D.places[id];
      if (P.day !== d) return;
      out[id] = { dur: P.dur, leg: 0, level: 'medium', custom: true };
      order.push(id);
    });
    out._order = order;
    return (poolCache[d] = out);
  }
  function params(d, lv, id) {
    var P = D.places[id];
    if (P && P.custom) return { dur: P.dur, leg: 0, level: lv };
    var st = cfg(d, lv).stops;
    for (var i = 0; i < st.length; i++) if (st[i][0] === id) return { dur: st[i][1], leg: st[i][2] || 0, level: lv };
    return pool(d)[id] || { dur: 30, leg: 0, level: lv };
  }
  function levelDefault(d, lv) { return cfg(d, lv).stops.map(function (s) { return s[0]; }); }
  function note(d, lv, id) {
    var P = D.places[id];
    if (P && P.custom) return P.tip || '';
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
    if (D.plan && D.plan.days) src = D.plan;
    else if (MODE === 'build') { var ls = lsGet(STORE_KEY); if (ls && Array.isArray(ls.days)) src = ls; }
    if (src && isObj(src.custom)) {
      Object.keys(src.custom).forEach(function (id) {
        var c = src.custom[id];
        if (/^u[1-9][0-9]?$/.test(id) && c && inRome(+c.lat, +c.lng) && c.name && +c.day >= 1 && +c.day <= D.n) addCustomPlace(id, c);
      });
    }
    for (var d = 1; d <= D.n; d++) {
      var v = src ? validDay(d, src.days[d - 1]) : null;
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

  // ---------- game state (saved page) ----------
  var G = null;
  function setGame(s) {
    G = s || {};
    if (!isObj(G.visits)) G.visits = {};
    if (!Array.isArray(G.days_done)) G.days_done = [];
    if (!Array.isArray(G.badges)) G.badges = [];
    G.points = +G.points || 0;
  }
  if (MODE === 'view' && D.code) setGame(D.game);
  var NAV_KEY = 'via_nav_' + (D.code || '');
  var navLocal = (MODE === 'view' && D.code && lsGet(NAV_KEY)) || {};
  if (!isObj(navLocal.done)) navLocal.done = {};
  if (!Array.isArray(navLocal.pending)) navLocal.pending = [];
  function saveNavLocal() { if (D.code) lsSet(NAV_KEY, navLocal); }
  function isDone(id) { return !!((G && G.visits[id]) || navLocal.done[id]); }

  // ---------- map ----------
  var map = null, layer = null, navLayer = null;
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
    navLayer = L.layerGroup().addTo(map);
    map.setView([41.895, 12.49], 13);
    map.on('click', onMapClick);
    map.on('dragstart', function () { if (nav) nav.userMoved = Date.now(); });
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
    L.polyline([home, from], { color: '#4F5A68', weight: 3, dashArray: '6 8', opacity: .8, interactive: false }).addTo(layer);
    L.polyline([to, home], { color: '#4F5A68', weight: 3, dashArray: '6 8', opacity: .8, interactive: false }).addTo(layer);
    // Walking path, broken where a leg is by bus/tram/taxi
    var seg = [from];
    sc.items.forEach(function (it) {
      var p = placePos(it.id);
      if (it.transit) {
        if (seg.length > 1) L.polyline(seg, { color: '#18905C', weight: 5, opacity: .9, interactive: false }).addTo(layer);
        L.polyline([seg[seg.length - 1], p], { color: '#4F5A68', weight: 3, dashArray: '6 8', opacity: .8, interactive: false }).addTo(layer);
        seg = [p];
      } else seg.push(p);
      bounds.push(p);
    });
    seg.push(to);
    L.polyline(seg, { color: '#18905C', weight: 5, opacity: .9, interactive: false }).addTo(layer);

    L.marker(home, { icon: pin('itin-pin-home', '<span aria-hidden="true">⌂</span>', 30), title: U.from_home, keyboard: false })
      .bindPopup('<strong>Via Arriano 11</strong>').addTo(layer);
    [[C.from, from], [C.to, to]].forEach(function (s) {
      L.marker(s[1], { icon: pin('itin-pin-station', '<span aria-hidden="true">M</span>', 22), title: T.stations[s[0]] || '', keyboard: false })
        .bindPopup(esc(T.stations[s[0]] || '')).addTo(layer);
    });
    sc.items.forEach(function (it, i) {
      var P = D.places[it.id], done = MODE === 'view' && isDone(it.id);
      var cls = (P.meal ? 'itin-pin-meal' : '') + (P.custom ? ' itin-pin-own' : '') + (done ? ' itin-pin-done' : '');
      var m = L.marker(placePos(it.id), { icon: pin(cls, '<span>' + (done ? '✓' : (i + 1)) + '</span>', 30), title: P.name, riseOnHover: true })
        .bindPopup('<strong>' + esc(P.name) + '</strong><br>' + hhmm(it.arrive) + ' · ' + esc(durText(it.dur)))
        .addTo(layer);
      m.on('click', function () { highlight(it.id); });
    });
    if (!nav) map.fitBounds(bounds, { padding: [28, 28], maxZoom: 16 });
  }
  function highlight(id) {
    var li = root.querySelector('[data-day-panel="' + state.active + '"] [data-stop-id="' + id + '"]') || root.querySelector('[data-stop-id="' + id + '"]');
    if (!li || nav) return;
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
    if (MODE === 'build') lsSet(STORE_KEY, { days: planDays(), custom: customExport() });
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
      h += '<li class="itin-stop' + (P.meal ? ' is-meal' : '') + (P.custom ? ' is-own' : '') + '" data-stop-id="' + esc(it.id) + '">';
      h += legHtml(it);
      h += '<div class="itin-stop-card">';
      h += '<div class="itin-stop-head"><span class="itin-num" aria-hidden="true">' + (i + 1) + '</span>';
      h += '<span class="itin-time">' + hhmm(it.arrive) + '</span>';
      h += '<span class="itin-stop-name">' + esc(P.name) + '</span></div>';
      h += '<div class="itin-stop-meta"><span>' + esc(durText(it.dur)) + '</span>';
      if (P.book) h += '<span class="itin-badge">' + esc(U.book_badge) + '</span>';
      if (P.meal) h += '<span class="itin-badge itin-badge-meal">' + esc(U.meal_badge) + '</span>';
      if (P.custom) h += '<span class="itin-badge itin-badge-own">' + esc(U.custom_badge) + '</span>';
      h += '</div>';
      if (n) h += '<p class="itin-note">' + esc(n) + '</p>';
      h += '<div class="itin-stop-actions">';
      if (P.guide && T.guides[P.guide]) h += '<button type="button" class="itin-act itin-act-guide" data-guide="' + esc(P.guide) + '">' + esc(U.guide_open) + '</button>';
      if (P.custom) h += '<button type="button" class="itin-act itin-act-guide" data-custom-edit="' + esc(it.id) + '">' + esc(U.custom_edit) + '</button>';
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
          (P.guide && T.guides[P.guide] ? ' <button type="button" class="linklike small" data-guide="' + esc(P.guide) + '">' + esc(U.guide_open) + '</button>' : '') +
          (P.custom ? ' <button type="button" class="linklike small" data-custom-del="' + esc(id) + '">' + esc(U.custom_delete) + '</button>' : '') + '</li>';
      });
      h += '</ul>';
    }
    // Own stops
    h += '<div class="itin-own"><button type="button" class="btn itin-own-add" data-custom-add><span aria-hidden="true">★</span> ' + esc(U.custom_add) + '</button>';
    h += '<p class="small muted">' + esc(U.custom_private) + '</p><p class="itin-error" data-custom-msg role="alert" hidden></p></div>';
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
    if (dlg.open) return;
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

  // ---------- own stops: pick a point, then name it ----------
  var picking = false, pickBar = null, pickMarker = null, editing = null, pendingPos = null, cdlg = null;
  function customMsg(txt) {
    var m = root.querySelector('[data-day-panel="' + state.active + '"] [data-custom-msg]');
    if (!m) return;
    m.textContent = txt || '';
    m.hidden = !txt;
  }
  function startPick() {
    if (customIds().length >= CUSTOM_MAX) { customMsg(U.custom_limit); return; }
    customMsg('');
    picking = true;
    var mapEl = document.getElementById('itinMap');
    if (!pickBar) {
      pickBar = el('div', 'itin-pick');
      pickBar.setAttribute('role', 'status');
      pickBar.innerHTML = '<span class="itin-pick-text">' + esc(U.custom_pick) + '</span>' +
        '<span class="itin-pick-btns"><button type="button" class="btn" data-pick-here>' + esc(U.custom_here) + '</button>' +
        '<button type="button" class="btn" data-pick-cancel>' + esc(U.custom_cancel) + '</button></span>' +
        '<span class="itin-pick-err" data-pick-err></span>';
      mapEl.parentNode.insertBefore(pickBar, mapEl);
    }
    pickBar.hidden = false;
    pickBar.querySelector('[data-pick-err]').textContent = '';
    mapEl.classList.add('is-picking');
    var r = mapEl.getBoundingClientRect();
    if (r.top < 0 || r.bottom > window.innerHeight) pickBar.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
  function stopPick() {
    picking = false;
    if (pickBar) pickBar.hidden = true;
    var mapEl = document.getElementById('itinMap');
    if (mapEl) mapEl.classList.remove('is-picking');
  }
  function pickErr(txt) { if (pickBar) pickBar.querySelector('[data-pick-err]').textContent = txt; }
  function onMapClick(e) {
    if (!picking) return;
    var ll = [e.latlng.lat, e.latlng.lng];
    if (!inRome(ll[0], ll[1])) { pickErr(U.custom_outside); return; }
    stopPick();
    openCustom(null, ll);
  }
  function pickHere() {
    if (!navigator.geolocation) { pickErr(U.custom_noloc); return; }
    navigator.geolocation.getCurrentPosition(function (p) {
      var ll = [p.coords.latitude, p.coords.longitude];
      if (!inRome(ll[0], ll[1])) { pickErr(U.custom_outside); return; }
      stopPick();
      openCustom(null, ll);
    }, function () { pickErr(U.custom_noloc); }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 30000 });
  }
  function buildCustomDialog() {
    cdlg = el('dialog', 'itin-guide itin-custom-dlg');
    cdlg.id = 'itinCustomDlg';
    cdlg.setAttribute('aria-labelledby', 'itinCustomTitle');
    var opts = CUSTOM_DURS.map(function (m) { return '<option value="' + m + '">' + esc(durText(m)) + '</option>'; }).join('');
    cdlg.innerHTML =
      '<form class="itin-guide-inner itin-custom-form" method="dialog" novalidate>' +
      '<button class="itin-guide-close" type="button" data-cdlg-close aria-label="' + esc(U.guide_close) + '">×</button>' +
      '<h2 id="itinCustomTitle">' + esc(U.custom_title) + '</h2>' +
      '<label class="itin-field"><span>' + esc(U.custom_name) + '</span><input type="text" name="name" maxlength="60" autocomplete="off" required></label>' +
      '<label class="itin-field"><span>' + esc(U.custom_tip) + '</span><textarea name="tip" maxlength="280" rows="3"></textarea></label>' +
      '<label class="itin-field"><span>' + esc(U.custom_dur) + '</span><select name="dur">' + opts + '</select></label>' +
      '<p class="small muted">' + esc(U.custom_private) + '</p>' +
      '<p class="itin-error" data-cdlg-err role="alert" hidden></p>' +
      '<div class="actions"><button class="btn primary" type="submit" data-cdlg-ok></button>' +
      '<button class="btn" type="button" data-cdlg-close>' + esc(U.custom_cancel) + '</button></div></form>';
    document.body.appendChild(cdlg);
    cdlg.addEventListener('click', function (e) {
      if (e.target === cdlg || e.target.closest('[data-cdlg-close]')) closeCustom();
    });
    cdlg.addEventListener('close', function () { if (pickMarker && map) { map.removeLayer(pickMarker); pickMarker = null; } });
    cdlg.querySelector('form').addEventListener('submit', function (e) {
      e.preventDefault();
      saveCustom();
    });
  }
  function openCustom(id, ll) {
    if (!cdlg) buildCustomDialog();
    editing = id;
    pendingPos = ll;
    var f = cdlg.querySelector('form').elements, P = id ? D.places[id] : null;
    f['name'].value = P ? P.name : '';
    f['tip'].value = P ? P.tip : '';
    f['dur'].value = String(P ? P.dur : 30);
    cdlg.querySelector('[data-cdlg-ok]').textContent = id ? U.custom_update : U.custom_save;
    cdlg.querySelector('[data-cdlg-err]').hidden = true;
    if (ll && map) {
      pickMarker = window.L.marker(ll, { icon: pin('itin-pin-own', '<span>★</span>', 30), keyboard: false }).addTo(map);
      map.panTo(ll);
    }
    if (typeof cdlg.showModal === 'function') cdlg.showModal(); else cdlg.setAttribute('open', '');
    setTimeout(function () { f['name'].focus(); }, 30);
  }
  function closeCustom() {
    if (!cdlg) return;
    if (typeof cdlg.close === 'function') cdlg.close(); else { cdlg.removeAttribute('open'); if (pickMarker && map) { map.removeLayer(pickMarker); pickMarker = null; } }
  }
  function saveCustom() {
    var f = cdlg.querySelector('form').elements, err = cdlg.querySelector('[data-cdlg-err]');
    var name = f['name'].value.replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim().slice(0, 60);
    if (!name) { err.textContent = U.custom_name_missing; err.hidden = false; f['name'].focus(); return; }
    var tip = f['tip'].value.replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim().slice(0, 280), dur = parseInt(f['dur'].value, 10) || 30;
    var d = state.active, id = editing;
    if (id && D.places[id]) {
      D.places[id].name = name; D.places[id].tip = tip; D.places[id].dur = dur;
    } else {
      id = nextCustomId();
      if (!id || customIds().length >= CUSTOM_MAX) { err.textContent = U.custom_limit; err.hidden = false; return; }
      addCustomPlace(id, { lat: pendingPos[0], lng: pendingPos[1], name: name, tip: tip, dur: dur, day: d });
      resetPool();
      insertCheapest(d, id);
      track('itinerary_own_stop', { day: d });
    }
    closeCustom();
    render();
    highlight(id);
  }
  function deleteCustom(id) {
    if (!D.places[id] || !D.places[id].custom) return;
    delete D.places[id];
    for (var d = 1; d <= D.n; d++) state.days[d].order = state.days[d].order.filter(function (x) { return x !== id; });
    resetPool();
    render();
  }

  // ---------- events ----------
  root.addEventListener('click', function (e) {
    var t = e.target.closest('button');
    if (!t || !root.contains(t)) return;
    var d = state.active, S = state.days[d];
    if (t.hasAttribute('data-day')) {
      if (nav) return;
      state.active = parseInt(t.getAttribute('data-day'), 10);
      render();
      if (map) setTimeout(function () { map.invalidateSize(); drawMap(state.active); }, 0);
      return;
    }
    if (t.hasAttribute('data-nav-start')) { startNav(parseInt(t.getAttribute('data-nav-start'), 10)); return; }
    if (t.hasAttribute('data-pick-here')) { pickHere(); return; }
    if (t.hasAttribute('data-pick-cancel')) { stopPick(); return; }
    if (t.hasAttribute('data-guide')) { openGuide(t.getAttribute('data-guide')); return; }
    if (MODE !== 'build') return;
    if (t.hasAttribute('data-level')) {
      var lv = t.getAttribute('data-level');
      // keep the visitor's own stops of this day when changing level
      var own = S.order.filter(function (id) { return D.places[id] && D.places[id].custom; });
      state.days[d] = { level: lv, order: levelDefault(d, lv) };
      own.forEach(function (id) { insertCheapest(d, id); });
      render(); return;
    }
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
    if (t.hasAttribute('data-custom-add')) { startPick(); return; }
    if (t.hasAttribute('data-custom-edit')) { openCustom(t.getAttribute('data-custom-edit'), null); return; }
    if (t.hasAttribute('data-custom-del')) { deleteCustom(t.getAttribute('data-custom-del')); return; }
    if (t.hasAttribute('data-reset')) {
      var mine = S.order.filter(function (x) { return D.places[x] && D.places[x].custom; });
      state.days[d] = { level: S.level, order: levelDefault(d, S.level) };
      mine.forEach(function (x) { insertCheapest(d, x); });
      render(); return;
    }
  });

  // Keyboard: arrows move between day tabs
  root.addEventListener('keydown', function (e) {
    var t = e.target;
    if (nav || !t.hasAttribute || !t.hasAttribute('data-day') || (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft')) return;
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
    var btnNew = null;
    if (D.code) {
      // Editing a saved itinerary: update it (same code, game points stay) or save a copy
      btn.textContent = fmt(U.save_update, { code: D.codeDisplay || D.code });
      btnNew = el('button', 'btn', esc(U.save_new));
      btnNew.type = 'button';
      btn.parentNode.insertBefore(btnNew, btn.nextSibling);
      btn.parentNode.insertBefore(el('p', 'small muted itin-save-help', esc(U.save_update_help)), btnNew.nextSibling);
    }
    var doSave = function (update) {
      btn.disabled = true; if (btnNew) btnNew.disabled = true;
      out.innerHTML = '<p class="small muted">' + esc(U.saving) + '</p>';
      var body = { n: D.n, days: planDays(), custom: customExport(), lang: D.lang };
      if (update && D.code) body.code = D.code;
      post(D.saveUrl, body).then(function (j) {
        btn.disabled = false; if (btnNew) btnNew.disabled = false;
        if (!j || !j.ok) { out.innerHTML = '<p class="itin-error" role="alert">' + esc(j && j.status === 429 ? U.save_limit : U.save_error) + '</p>'; return; }
        var url = location.origin + j.url;
        out.innerHTML =
          '<p class="itin-saved-ok">' + esc(U.saved_ok) + '</p>' +
          '<p class="itin-code">' + esc(j.code) + '</p>' +
          '<div class="actions">' +
          '<a class="btn primary" href="' + esc(j.url + '#avvia-' + state.active) + '">▶ ' + esc(U.nav_start) + '</a>' +
          '<a class="btn" href="' + esc(j.url) + '">' + esc(U.saved_open) + '</a>' +
          '<button type="button" class="btn" data-copy="' + esc(url) + '">' + esc(U.copy) + '</button>' +
          '<a class="btn btn-wa" target="_blank" rel="noopener" href="https://wa.me/?text=' + encodeURIComponent(fmt(U.share_text, { url: url })) + '">' + esc(U.share_wa) + '</a>' +
          '</div>';
        try { window.localStorage.setItem('via_itin_last_code', j.code); } catch (e) { /* ignore */ }
        track('itinerary_saved', { days: D.n, update: update ? 1 : 0 });
      });
    };
    btn.addEventListener('click', function () { doSave(!!D.code); });
    if (btnNew) btnNew.addEventListener('click', function () { doSave(false); });
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

  // ---------- toast ----------
  var toastEl = null, toastTimer = 0;
  function toast(html) {
    if (!toastEl) {
      toastEl = el('div', 'itin-toast');
      toastEl.setAttribute('role', 'status');
      toastEl.setAttribute('aria-live', 'polite');
      document.body.appendChild(toastEl);
    }
    toastEl.innerHTML = html;
    toastEl.classList.add('is-on');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toastEl.classList.remove('is-on'); }, 4500);
  }

  // ---------- game box (saved page) ----------
  var gameBox = document.getElementById('itinGame');
  function updateGame() {
    if (!G) return;
    if (gameBox) {
      var pts = gameBox.querySelector('[data-game-points]');
      if (pts) pts.textContent = String(G.points);
      gameBox.querySelectorAll('[data-badge]').forEach(function (b) {
        b.classList.toggle('is-on', G.badges.indexOf(b.getAttribute('data-badge')) >= 0);
      });
      var rk = gameBox.querySelector('[data-game-rank]');
      if (rk) rk.textContent = G.rank ? fmt(U.game_rank, { n: G.rank }) : U.game_rank_none;
    }
    root.querySelectorAll('[data-stop-id]').forEach(function (li) {
      var done = isDone(li.getAttribute('data-stop-id'));
      li.classList.toggle('is-done', done);
      var b = li.querySelector('[data-done-badge]');
      if (b) b.hidden = !done;
    });
  }
  if (gameBox && G) {
    var nickForm = gameBox.querySelector('[data-game-nick]');
    if (nickForm) nickForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var msg = nickForm.querySelector('[data-nick-msg]'), b = nickForm.querySelector('button');
      b.disabled = true;
      post(D.gameUrl, { action: 'nick', code: D.code, nick: nickForm.elements['nick'].value }).then(function (j) {
        b.disabled = false;
        if (j && j.ok) { setGame(j.state); updateGame(); msg.textContent = U.game_nick_ok; msg.className = 'small itin-ok'; }
        else { msg.textContent = j && j.reason === 'nick' ? U.game_nick_bad : U.game_error; msg.className = 'small itin-error'; }
      });
    });
  }

  // ---------- "Avvia percorso": GPS on our map, walking directions in Google/Apple Maps ----------
  var nav = null, navEl = null;
  var IOS = /iP(hone|ad|od)/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

  // Same as itin_game_radius() in PHP
  function radiusFor(P) {
    if (P.custom) return 80;
    if (P.meal) return 250;
    if ((P.inner || 0) >= 1.5) return 220;
    if ((P.inner || 0) >= 0.5) return 130;
    return 80;
  }
  function navTargets(d) {
    var out = schedule(d).items.map(function (it) {
      var P = D.places[it.id];
      return { id: it.id, name: P.name, pos: placePos(it.id), transit: it.transit > 0, r: radiusFor(P), guide: P.guide && T.guides[P.guide] ? P.guide : '' };
    });
    out.push({ id: '_home', name: U.nav_home, pos: D.home, transit: true, r: 120, guide: '' });
    return out;
  }
  function cur() { return nav ? nav.targets[nav.i] : null; }
  function mapsUrl(app, tg, transit) {
    var ll = tg.pos[0].toFixed(6) + ',' + tg.pos[1].toFixed(6);
    if (app === 'apple') return 'https://maps.apple.com/?daddr=' + ll + '&dirflg=' + (transit ? 'r' : 'w');
    return 'https://www.google.com/maps/dir/?api=1&destination=' + ll + '&travelmode=' + (transit ? 'transit' : 'walking') + '&dir_action=navigate';
  }

  function startNav(d) {
    if (nav) endNav(true);
    stopPick();
    state.active = d;
    render();
    var targets = navTargets(d), i = 0;
    while (i < targets.length - 1 && isDone(targets[i].id)) i++;
    nav = { day: d, targets: targets, i: i, pos: null, arrived: false, busy: false, lastTry: 0, watch: null, wake: null, userMoved: 0, lastFit: 0, msg: '', msgKind: '', autoGuide: {} };
    navLocal.active = true; navLocal.day = d; saveNavLocal();
    document.body.classList.add('itin-navigating');
    buildNavPanel();
    // Map at the top of the screen, under the site header; the panel covers the bottom
    var mc = document.getElementById('itinMap'), hd = document.querySelector('.site-header');
    if (mc) window.scrollTo({ top: mc.getBoundingClientRect().top + window.scrollY - (hd ? hd.getBoundingClientRect().height : 0) - 8, behavior: 'smooth' });
    if (map) setTimeout(function () { map.invalidateSize(); fitNav(true); }, 120);
    if (!navigator.geolocation) setMsg(U.nav_denied, 'warn');
    else {
      setMsg(U.nav_locating, 'info');
      nav.watch = navigator.geolocation.watchPosition(onPos, onPosErr, { enableHighAccuracy: true, maximumAge: 5000, timeout: 30000 });
    }
    requestWake();
    track('itinerary_nav_start', { day: d });
    updateNav();
  }
  function endNav(silent) {
    if (!nav) return;
    if (nav.watch != null && navigator.geolocation) navigator.geolocation.clearWatch(nav.watch);
    if (nav.wake && nav.wake.release) nav.wake.release().catch(function () {});
    nav = null;
    if (navLayer) navLayer.clearLayers();
    if (navEl) { navEl.remove(); navEl = null; }
    document.body.classList.remove('itin-navigating');
    navLocal.active = false; saveNavLocal();
    if (!silent) { drawMap(state.active); updateStartButtons(); }
  }
  function requestWake() {
    if (!nav || !('wakeLock' in navigator)) return;
    navigator.wakeLock.request('screen').then(function (w) { if (nav) nav.wake = w; else w.release(); }).catch(function () {});
  }
  document.addEventListener('visibilitychange', function () {
    if (nav && document.visibilityState === 'visible') requestWake();
  });

  function onPos(p) {
    if (!nav) return;
    nav.pos = { lat: p.coords.latitude, lng: p.coords.longitude, acc: typeof p.coords.accuracy === 'number' ? Math.max(1, p.coords.accuracy) : 999, t: Date.now() };
    if (nav.msgKind === 'info' || nav.msgKind === 'warn') setMsg('', '');
    drawUser();
    flushPending();
    maybeArrive();
    updateNav();
  }
  function onPosErr(e) {
    if (!nav) return;
    if (e && e.code === 1) setMsg(U.nav_denied, 'warn');
    else if (!nav.pos) setMsg(U.nav_locating, 'info');
  }
  function setMsg(txt, kind) {
    if (!nav) return;
    nav.msg = txt || ''; nav.msgKind = kind || '';
    if (navEl) {
      var m = navEl.querySelector('[data-nav-msg]');
      m.textContent = nav.msg;
      m.className = 'itin-nav-msg' + (nav.msgKind ? ' is-' + nav.msgKind : '');
      m.hidden = !nav.msg;
    }
  }

  // Map: blue dot with accuracy, target circle (arrival zone)
  var userMarker = null, userCircle = null, targetCircle = null;
  function drawUser() {
    if (!map || !nav) return;
    var L = window.L, ll = [nav.pos.lat, nav.pos.lng];
    if (!userMarker || !navLayer.hasLayer(userMarker)) {
      userCircle = L.circle(ll, { radius: nav.pos.acc, color: '#2B7BE4', weight: 1, fillOpacity: .12, interactive: false }).addTo(navLayer);
      userMarker = L.circleMarker(ll, { radius: 8, color: '#fff', weight: 3, fillColor: '#2B7BE4', fillOpacity: 1 }).bindTooltip(esc(U.nav_you)).addTo(navLayer);
    } else {
      userMarker.setLatLng(ll); userCircle.setLatLng(ll); userCircle.setRadius(Math.min(nav.pos.acc, 500));
    }
    fitNav(false);
  }
  function drawTarget() {
    if (!map || !nav) return;
    var tg = cur();
    if (targetCircle) navLayer.removeLayer(targetCircle);
    targetCircle = window.L.circle(tg.pos, { radius: tg.r, color: '#C8215A', weight: 2, dashArray: '4 6', fillOpacity: .08, interactive: false }).addTo(navLayer);
  }
  function fitNav(force) {
    if (!map || !nav) return;
    var now = Date.now();
    if (!force && (now - nav.userMoved < 20000 || now - nav.lastFit < 8000)) return;
    nav.lastFit = now;
    var tg = cur(), pts = [tg.pos];
    if (nav.pos && distM([nav.pos.lat, nav.pos.lng], tg.pos) < 15000) pts.push([nav.pos.lat, nav.pos.lng]);
    if (pts.length === 1) map.setView(tg.pos, 16);
    else map.fitBounds(pts, { padding: [40, 40], maxZoom: 17 });
  }

  function maybeArrive() {
    var tg = cur();
    if (!tg || nav.arrived || nav.busy || !nav.pos) return;
    var dm = distM([nav.pos.lat, nav.pos.lng], tg.pos);
    if (dm > tg.r + Math.min(nav.pos.acc, 50)) return;
    if (nav.pos.acc > 150) { setMsg(U.nav_inaccurate, 'warn'); return; }
    if (Date.now() - nav.lastTry < 15000) return;
    arrive(false);
  }
  function markLocal(id) { navLocal.done[id] = Date.now(); saveNavLocal(); }
  function arrive(manual) {
    var tg = cur();
    if (!tg || nav.busy) return;
    if (tg.id === '_home') { nav.arrived = true; setMsg(U.nav_finished, 'ok'); updateNav(); return; }
    if (G && G.visits[tg.id]) { showArrived(''); return; }
    var fresh = nav.pos && Date.now() - nav.pos.t < 60000;
    if (!fresh) { markLocal(tg.id); showArrived(U.nav_manual); return; }
    nav.busy = true; nav.lastTry = Date.now();
    var body = { action: 'checkin', code: D.code, day: nav.day, place: tg.id, lat: nav.pos.lat, lng: nav.pos.lng, acc: Math.round(nav.pos.acc) };
    post(D.gameUrl, body).then(function (j) {
      if (!nav) return;
      nav.busy = false;
      if (j && j.ok) { checkinDone(j, tg); return; }
      var reason = j && j.reason;
      if (reason === 'far') {
        var txt = fmt(U.nav_too_far, { dist: distText(j.dist || 0) });
        if (manual) { markLocal(tg.id); showArrived(txt + ' ' + U.nav_manual); } else setMsg(txt, 'warn');
      } else if (reason === 'inaccurate') {
        if (manual) { markLocal(tg.id); showArrived(U.nav_manual); } else setMsg(U.nav_inaccurate, 'warn');
      } else if (reason === 'fast') {
        markLocal(tg.id); showArrived(U.game_rejected);
      } else if (!j || j.status >= 500 || j.status === 429 || !j.status) {
        // offline: keep it and retry later
        navLocal.pending.push(body); saveNavLocal();
        markLocal(tg.id); showArrived(U.game_error);
      } else {
        markLocal(tg.id); showArrived(U.nav_manual);
      }
    });
  }
  function checkinDone(j, tg) {
    var before = G ? G.days_done.length : 0;
    setGame(j.state);
    markLocal(tg.id);
    var parts = [];
    if (j.gained) parts.push('<strong>' + esc(fmt(U.game_plus, { n: j.gained })) + '</strong>');
    (j.new_badges || []).forEach(function (b) {
      parts.push((D.badgeIcons[b] || '') + ' ' + esc(fmt(U.game_new_badge, { badge: (BADGES[b] && BADGES[b].name) || b })));
    });
    if (G.days_done.length > before) parts.push(esc(U.nav_day_done));
    if (parts.length) toast(parts.join('<br>'));
    track('itinerary_checkin', { place: tg.id, points: j.gained || 0 });
    updateGame();
    if (nav && cur() === tg) showArrived('');
  }
  function flushPending() {
    if (!navLocal.pending.length || (navigator.onLine === false)) return;
    var list = navLocal.pending.splice(0, navLocal.pending.length);
    saveNavLocal();
    list.forEach(function (body) {
      post(D.gameUrl, body).then(function (j) {
        if (j && j.ok) { setGame(j.state); updateGame(); if (j.gained) toast('<strong>' + esc(fmt(U.game_plus, { n: j.gained })) + '</strong>'); }
        else if (!j || !j.status || j.status >= 500) { navLocal.pending.push(body); saveNavLocal(); }
      });
    });
  }
  function showArrived(extra) {
    var tg = cur();
    nav.arrived = true;
    // Confirmed by GPS: open the monument guide once. Marked by hand: just say why there are no points.
    if (extra) setMsg(extra, 'warn');
    else {
      setMsg(fmt(U.nav_here, { place: tg.name }), 'ok');
      if (tg.guide && !nav.autoGuide[tg.id]) { nav.autoGuide[tg.id] = 1; openGuide(tg.guide); }
    }
    updateNav();
    drawMap(state.active);
  }
  function nextTarget() {
    if (!nav) return;
    if (nav.i >= nav.targets.length - 1) { endNav(false); return; }
    nav.i++;
    while (nav.i < nav.targets.length - 1 && isDone(nav.targets[nav.i].id)) nav.i++;
    nav.arrived = false; nav.lastTry = 0;
    setMsg('', '');
    drawTarget();
    fitNav(true);
    updateNav();
    maybeArrive();
  }

  function buildNavPanel() {
    navEl = el('section', 'itin-nav');
    navEl.setAttribute('aria-label', U.nav_title);
    navEl.innerHTML =
      '<div class="itin-nav-top"><span class="itin-nav-step" data-nav-step></span>' +
      '<span class="itin-nav-pts" data-nav-pts></span>' +
      '<button type="button" class="itin-nav-close" data-nav-end aria-label="' + esc(U.nav_end) + '">×</button></div>' +
      '<div class="itin-nav-name" data-nav-name></div>' +
      '<div class="itin-nav-dist" data-nav-dist></div>' +
      '<p class="itin-nav-msg" data-nav-msg role="status" aria-live="polite" hidden></p>' +
      '<div class="itin-nav-actions" data-nav-actions></div>' +
      '<div class="itin-nav-dots" data-nav-dots aria-hidden="true"></div>';
    document.body.appendChild(navEl);
    navEl.addEventListener('click', function (e) {
      var b = e.target.closest('[data-nav-end],[data-nav-arrived],[data-nav-next],[data-nav-skip],[data-nav-guide]');
      if (!b || !nav) return;
      if (b.hasAttribute('data-nav-end')) endNav(false);
      else if (b.hasAttribute('data-nav-arrived')) arrive(true);
      else if (b.hasAttribute('data-nav-next') || b.hasAttribute('data-nav-skip')) nextTarget();
      else if (b.hasAttribute('data-nav-guide')) openGuide(b.getAttribute('data-nav-guide'));
    });
    drawTarget();
  }
  function updateNav() {
    if (!nav || !navEl) return;
    var tg = cur(), last = nav.i === nav.targets.length - 1, stops = nav.targets.length - 1;
    navEl.querySelector('[data-nav-step]').textContent = last ? U.nav_home : fmt(U.nav_step, { i: nav.i + 1, n: stops });
    navEl.querySelector('[data-nav-pts]').textContent = G ? (G.points + ' ' + U.game_points) : '';
    navEl.querySelector('[data-nav-name]').textContent = last ? 'Via Arriano 11' : tg.name;
    var far = tg.transit, dist = '';
    if (nav.pos) {
      var dm = distM([nav.pos.lat, nav.pos.lng], tg.pos);
      if (dm > 2000) far = true;
      dist = far ? fmt(U.nav_far, { dist: distText(dm) }) : fmt(U.nav_walk, { dist: distText(dm), min: Math.max(1, Math.round(dm * 1.3 / 1000 / 4.5 * 60)) });
    }
    navEl.querySelector('[data-nav-dist]').textContent = dist;
    var main = IOS ? 'apple' : 'google', other = IOS ? 'google' : 'apple';
    var h = '';
    if (nav.arrived) {
      if (tg.guide) h += '<button type="button" class="btn" data-nav-guide="' + esc(tg.guide) + '">' + esc(U.guide_open) + '</button>';
      h += last ? '<button type="button" class="btn primary" data-nav-end>' + esc(U.nav_end) + '</button>'
                : '<button type="button" class="btn primary" data-nav-next>' + esc(U.nav_next) + ' →</button>';
    } else {
      h += '<a class="btn primary" target="_blank" rel="noopener" href="' + esc(mapsUrl(main, tg, far)) + '">➤ ' + esc(U.nav_go) + '</a>';
      h += '<button type="button" class="btn" data-nav-arrived>' + esc(U.nav_arrived) + '</button>';
      if (tg.guide) h += '<button type="button" class="btn" data-nav-guide="' + esc(tg.guide) + '">' + esc(U.guide_open) + '</button>';
      if (!last) h += '<button type="button" class="linklike small" data-nav-skip>' + esc(U.nav_skip) + '</button>';
      h += '<a class="small itin-nav-alt" target="_blank" rel="noopener" href="' + esc(mapsUrl(other, tg, far)) + '">' + esc(fmt(U.nav_open_in, { app: other === 'apple' ? 'Apple Maps' : 'Google Maps' })) + '</a>';
    }
    navEl.querySelector('[data-nav-actions]').innerHTML = h;
    var dots = '';
    nav.targets.forEach(function (t, k) {
      dots += '<span class="' + (k === nav.i ? 'is-cur' : (t.id !== '_home' && isDone(t.id) ? 'is-done' : '')) + '"></span>';
    });
    navEl.querySelector('[data-nav-dots]').innerHTML = dots;
  }

  function updateStartButtons() {
    root.querySelectorAll('[data-nav-start]').forEach(function (b) {
      var d = parseInt(b.getAttribute('data-nav-start'), 10);
      var resume = navLocal.day === d && navTargets(d).some(function (t) { return t.id !== '_home' && isDone(t.id); });
      b.textContent = '▶ ' + (resume ? U.nav_resume : U.nav_start);
    });
  }

  // ---------- go ----------
  initMap();
  render();
  if (MODE === 'view') {
    updateGame();
    updateStartButtons();
    // Coming from "Salva": #avvia-2 opens day 2 and points at its button (GPS needs a tap to start)
    var m = /^#avvia-(\d)$/.exec(location.hash || '');
    if (m && +m[1] >= 1 && +m[1] <= D.n) {
      state.active = +m[1];
      render();
      var sb = root.querySelector('[data-nav-start="' + m[1] + '"]');
      if (sb) { sb.classList.add('is-hl'); sb.scrollIntoView({ block: 'center' }); setTimeout(function () { sb.focus(); }, 300); }
    }
  }
})();
