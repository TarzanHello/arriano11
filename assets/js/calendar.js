(function(){
  function pad(n){ return n < 10 ? '0'+n : ''+n; }
  function ymd(d){ return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate()); }
  function parseYmd(s){
    var m = /^([0-9]{4})-([0-9]{2})-([0-9]{2})$/.exec(s || '');
    if(!m) return null;
    return new Date(Number(m[1]), Number(m[2])-1, Number(m[3]));
  }
  function addDays(date, days){
    var d = new Date(date.getTime());
    d.setDate(d.getDate()+days);
    return d;
  }
  function monthKey(date){ return date.getFullYear()+'-'+pad(date.getMonth()+1); }
  function monthKeyFromDateStr(dateStr){ return (typeof dateStr === 'string' && dateStr.length >= 7) ? dateStr.slice(0,7) : '';
  }

  // Simple i18n helper (strings are injected by PHP in window.__VIA_I18N)
  var I = window.__VIA_I18N || {};
  var STR = I.strings || {};
  function get(obj, path){
    var parts = path.split('.');
    var cur = obj;
    for(var i=0;i<parts.length;i++){
      if(!cur || typeof cur !== 'object') return '';
      cur = cur[parts[i]];
    }
    return (typeof cur === 'string') ? cur : '';
  }
  function tmpl(str, vars){
    str = String(str || '');
    if(!vars) return str;
    Object.keys(vars).forEach(function(k){
      str = str.split('{' + k + '}').join(String(vars[k]));
    });
    return str;
  }
  function t(path, vars){
    var s = get(STR, path);
    if(!s) return '';
    return tmpl(s, vars);
  }

  var root = document.getElementById('bookingCalendar');
  if(!root) return;

  var apiBase = root.getAttribute('data-api-base') || '/api';
  var csrf = root.getAttribute('data-csrf') || '';

  var titleEl = document.getElementById('calTitle');
  var tableEl = document.getElementById('calTable');
  var msgEl = document.getElementById('calMsg');
  var summaryEl = document.getElementById('bookingSummary');
  var quoteEl = document.getElementById('bookingQuote');
  var formEl = document.getElementById('bookingForm');
  var startInput = document.getElementById('startDate');
  var endInput = document.getElementById('endDate');
  var guestsInput = document.getElementById('guests');

  var locale = I.locale || (I.lang || 'it');

  function safeIntl(){
    try{ return typeof Intl !== 'undefined' && !!Intl.DateTimeFormat; }catch(e){ return false; }
  }

  function monthName(date){
    if(safeIntl()){
      try{
        var fmt = new Intl.DateTimeFormat(locale, { month: 'long' });
        var name = fmt.format(date);
        // Capitalize first letter for nicer UI in some locales.
        return name.charAt(0).toUpperCase() + name.slice(1);
      }catch(e){ /* ignore */ }
    }
    // Fallback
    var m = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    return m[date.getMonth()];
  }

  function dowLabels(){
    if(safeIntl()){
      try{
        var fmt = new Intl.DateTimeFormat(locale, { weekday: 'short' });
        // Monday Jan 4 2021
        var base = new Date(Date.UTC(2021,0,4));
        var out = [];
        for(var i=0;i<7;i++){
          out.push(fmt.format(new Date(base.getTime() + i*86400000)));
        }
        return out;
      }catch(e){ /* ignore */ }
    }
    return ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
  }

  var DOW = dowLabels();

  var todayStr = ymd(new Date());

  var state = {
    month: new Date(new Date().getFullYear(), new Date().getMonth(), 1),
    availabilityByMonth: {},
    currency: 'EUR',
    globalMinNights: 1,
    minNights: 1,
    start: null,
    end: null,
    guests: 2
  };

  // Prefill from query string (optional)
  var prefill = { start:'', end:'', guests:'' };
  try{
    var qs = new URLSearchParams(window.location.search || '');
    prefill.start = (qs.get('start') || '').trim();
    prefill.end = (qs.get('end') || '').trim();
    prefill.guests = (qs.get('guests') || '').trim();
  }catch(e){
    // ignore
  }

  var preStartDate = prefill.start ? parseYmd(prefill.start) : null;
  if(preStartDate){
    state.month = new Date(preStartDate.getFullYear(), preStartDate.getMonth(), 1);
  }

  function fmtMoney(v){
    if(typeof v !== 'number') return '';
    try{
      return new Intl.NumberFormat(locale, { style: 'currency', currency: state.currency }).format(v);
    }catch(e){
      var sym = (state.currency === 'EUR') ? '€' : (state.currency + ' ');
      return sym + Math.round(v);
    }
  }

  // Compact, single-line price for tight calendar cells (mobile portrait).
  // We intentionally remove cents and whitespace to ensure readability.
  function fmtMoneyCompact(v){
    if(typeof v !== 'number') return '';
    var rounded = Math.round(v);
    // Most installations use EUR.
    if(state.currency === 'EUR') return '€' + rounded;
    // Fallback: currency after number
    return String(rounded) + String(state.currency || '');
  }

  function setMsg(text, type){
    if(!msgEl) return;
    msgEl.textContent = text || '';
    msgEl.className = text ? ('notice ' + (type||'')) : 'notice';
    msgEl.style.display = text ? 'block' : 'none';
  }

  function dayInfo(dateStr){
    var key = monthKeyFromDateStr(dateStr);
    if(!key) return null;
    var bucket = state.availabilityByMonth[key];
    if(!bucket) return null;
    return bucket[dateStr] || null;
  }

  function canCheckin(dateStr){
    if(!dateStr || dateStr < todayStr) return false;
    var d = dayInfo(dateStr);
    return !!(d && d.status !== 'closed' && d.can_checkin);
  }

  function canCheckout(dateStr){
    if(!dateStr || dateStr < todayStr) return false;
    var d = dayInfo(dateStr);
    return !!(d && d.status !== 'closed' && d.can_checkout);
  }

  function isFullAvailable(dateStr){
    var d = dayInfo(dateStr);
    return !!(d && d.status === 'available' && d.can_checkin && d.can_checkout);
  }

  function priceFor(dateStr){
    var d = dayInfo(dateStr);
    return d && typeof d.price === 'number' ? d.price : null;
  }

  function minNightsFor(dateStr){
    var d = dayInfo(dateStr);
    if(d && typeof d.min_nights === 'number' && d.min_nights >= 1) return d.min_nights;
    return state.globalMinNights || 1;
  }

  function updateEndMin(){
    if(!endInput) return;
    if(!state.start){
      endInput.min = todayStr;
      return;
    }
    var s = parseYmd(state.start);
    if(!s){
      endInput.min = todayStr;
      return;
    }
    var minCheckout = ymd(addDays(s, state.minNights || 1));
    endInput.min = minCheckout;
  }

  function clearSelection(){
    state.start = null;
    state.end = null;
    state.minNights = state.globalMinNights || 1;

    if(startInput) startInput.value = '';
    if(endInput) endInput.value = '';
    updateEndMin();

    if(summaryEl) summaryEl.textContent = t('booking.summary.select_dates') || '';
    if(quoteEl) quoteEl.textContent = '';
  }

  function validateRange(startStr, endStr){
    var s = parseYmd(startStr);
    var e = parseYmd(endStr);
    if(!s || !e) return {ok:false, code:'dates_invalid'};
    if(endStr <= startStr || e <= s) return {ok:false, code:'dates_invalid'};

    if(startStr < todayStr){
      return {ok:false, code:'past_not_allowed'};
    }

    if(!canCheckin(startStr)){
      return {ok:false, code:'unavailable'};
    }

    if(!canCheckout(endStr)){
      return {ok:false, code:'unavailable'};
    }

    var nights = Math.round((e - s) / 86400000);
    if(nights < (state.minNights || 1)){
      return {ok:false, code:'min_nights', vars:{n: (state.minNights || 1)}, nights:nights};
    }

    // Internal nights must be fully available (guest occupies whole day)
    for(var i=1; i<nights; i++){
      var dayStr = ymd(addDays(s, i));
      if(dayStr >= endStr) break;
      if(!isFullAvailable(dayStr)){
        return {ok:false, code:'unavailable'};
      }
    }

    return {ok:true, nights:nights};
  }

  function renderQuote(data){
    if(!quoteEl) return;
    if(!data || !data.ok){
      quoteEl.textContent = '';
      return;
    }

    var lines = '';
    lines += '<div class="quote-line"><span>' + (t('booking.quote.stay') || '') + ' (' + data.nights + ')</span><strong>' + fmtMoney(data.accommodation_total) + '</strong></div>';
    lines += '<div class="quote-line"><span>' + (t('booking.quote.cleaning') || '') + '</span><strong>' + fmtMoney(data.cleaning_fee) + '</strong></div>';
    lines += '<div class="quote-line"><span>' + (t('booking.quote.city_tax') || '') + '</span><strong>' + fmtMoney(data.city_tax_total) + '</strong></div>';
    lines += '<div class="quote-line total"><span>' + (t('booking.quote.total') || '') + '</span><strong>' + fmtMoney(data.total) + '</strong></div>';

    var guestsLabel = get(STR, 'booking.labels.guests') || '';

    quoteEl.innerHTML =
      '<div class="notice">' +
        lines +
        '<div class="small muted" style="margin-top:8px;">' + guestsLabel + ': ' + data.guests + '</div>' +
      '</div>';
  }

  function refreshQuote(){
    if(!state.start || !state.end){
      if(quoteEl) quoteEl.textContent = '';
      return;
    }

    var guests = state.guests || 2;
    if(quoteEl) quoteEl.textContent = t('booking.messages.loading_quote') || '';

    var url = apiBase + '/quote.php?start=' + encodeURIComponent(state.start) + '&end=' + encodeURIComponent(state.end) + '&guests=' + encodeURIComponent(String(guests));
    return fetch(url, { credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if(!data || !data.ok){
          var code = (data && data.error_code) ? data.error_code : 'request_failed';
          var msg = t('booking.messages.' + code, (data && data.error_vars) ? data.error_vars : null) || (data && data.error) || '';
          throw new Error(msg || 'Error');
        }
        // Keep currency in sync
        if(data.currency) state.currency = data.currency;
        renderQuote(data);
      })
      .catch(function(err){
        if(quoteEl) quoteEl.textContent = (t('booking.messages.request_failed') || '') + ' ' + (err.message || '');
      });
  }

  function fetchMonth(mkey, setAsCurrent){
    return fetch(apiBase + '/availability.php?month=' + encodeURIComponent(mkey), { credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if(!data || !data.ok){
          var code = (data && data.error_code) ? data.error_code : 'request_failed';
          var msg = t('booking.messages.' + code, (data && data.error_vars) ? data.error_vars : null) || (data && data.error) || '';
          throw new Error(msg || 'Error');
        }

        state.availabilityByMonth[mkey] = data.days || {};
        state.currency = data.currency || state.currency || 'EUR';
        state.globalMinNights = data.min_nights || state.globalMinNights || 1;
        if(!state.start){
          state.minNights = state.globalMinNights;
        }

        if(setAsCurrent){
          render();
        }
      });
  }

  function monthsBetween(startStr, endStr){
    var s = parseYmd(startStr);
    var e = parseYmd(endStr);
    if(!s || !e) return [];
    var keys = [];
    var d = new Date(s.getFullYear(), s.getMonth(), 1);
    var last = new Date(e.getFullYear(), e.getMonth(), 1);
    while(d <= last){
      keys.push(monthKey(d));
      d = new Date(d.getFullYear(), d.getMonth() + 1, 1);
    }
    return keys;
  }

  function ensureMonthsLoaded(startStr, endStr){
    var keys = monthsBetween(startStr, endStr);
    var missing = keys.filter(function(k){ return !state.availabilityByMonth[k]; });
    if(!missing.length) return Promise.resolve();

    return missing.reduce(function(p, k){
      return p.then(function(){ return fetchMonth(k, false); });
    }, Promise.resolve());
  }

  var prevBtn = document.getElementById('calPrev');
  var nextBtn = document.getElementById('calNext');
  var clearBtn = document.getElementById('calClear');

  function fetchAvailability(){
    setMsg('', '');

    var mkey = monthKey(state.month);
    if(titleEl){
      titleEl.textContent = monthName(state.month) + ' ' + state.month.getFullYear();
    }

    if(prevBtn){
      var now = new Date();
      var curStart = new Date(now.getFullYear(), now.getMonth(), 1);
      prevBtn.disabled = (state.month.getFullYear() === curStart.getFullYear() && state.month.getMonth() === curStart.getMonth());
    }

    if(state.availabilityByMonth[mkey]){
      render();
    }

    return fetchMonth(mkey, true)
      .then(function(){
        if(state.start && !state.end){
          var lastDay = new Date(state.month.getFullYear(), state.month.getMonth() + 1, 0);
          var lastStr = ymd(lastDay);
          return ensureMonthsLoaded(state.start, lastStr).then(function(){ render(); });
        }
      })
      .catch(function(err){
        setMsg((t('booking.messages.request_failed') || '') + ' ' + (err.message || ''), 'danger');
        render();
      });
  }

  function setStart(dateStr){
    if(!dateStr){
      clearSelection();
      render();
      return;
    }

    if(dateStr < todayStr){
      setMsg(t('booking.messages.past_not_allowed') || '', 'warn');
      return;
    }

    if(!canCheckin(dateStr)){
      setMsg(t('booking.messages.unavailable') || '', 'warn');
      return;
    }

    state.start = dateStr;
    state.end = null;
    state.minNights = minNightsFor(dateStr);

    if(startInput) startInput.value = dateStr;
    if(endInput) endInput.value = '';

    updateEndMin();

    var minHint = '';
    if(state.minNights > 1){
      minHint = t('booking.min_nights_hint', {n: state.minNights}) || '';
    }

    if(summaryEl){
      summaryEl.textContent = t('booking.summary.checkin_selected', {start: dateStr, minNights: minHint}) || '';
    }

    if(quoteEl) quoteEl.textContent = '';
    setMsg('', '');
    render();
  }

  function setEnd(dateStr){
    if(!state.start){
      setMsg(t('booking.messages.dates_invalid') || '', 'warn');
      return;
    }

    if(!dateStr){
      state.end = null;
      if(endInput) endInput.value = '';
      if(quoteEl) quoteEl.textContent = '';
      render();
      return;
    }

    if(dateStr <= state.start){
      setStart(dateStr);
      return;
    }

    setMsg(t('booking.messages.loading_calendar') || '', '');
    ensureMonthsLoaded(state.start, dateStr)
      .then(function(){
        var check = validateRange(state.start, dateStr);
        if(!check.ok){
          var msg = t('booking.messages.' + check.code, check.vars) || '';
          setMsg(msg || t('booking.messages.unavailable') || '', 'warn');
          return;
        }

        state.end = dateStr;
        if(endInput) endInput.value = dateStr;
        if(summaryEl){
          summaryEl.textContent = t('booking.summary.selection', {start: state.start, end: state.end, nights: check.nights}) || '';
        }
        setMsg('', '');
        render();
        refreshQuote();
      })
      .catch(function(err){
        setMsg((t('booking.messages.request_failed') || '') + ' ' + (err.message || ''), 'danger');
      });
  }

  function jumpToMonthOf(dateStr){
    var d = parseYmd(dateStr);
    if(!d) return Promise.resolve();
    var target = new Date(d.getFullYear(), d.getMonth(), 1);
    if(state.month.getFullYear() === target.getFullYear() && state.month.getMonth() === target.getMonth()){
      return fetchAvailability();
    }
    state.month = target;
    return fetchAvailability();
  }

  function render(){
    if(!tableEl) return;

    var html = '';
    html += '<thead><tr>';
    DOW.forEach(function(d){ html += '<th>' + d + '</th>'; });
    html += '</tr></thead>';
    html += '<tbody>';

    var first = new Date(state.month.getFullYear(), state.month.getMonth(), 1);
    var last = new Date(state.month.getFullYear(), state.month.getMonth()+1, 0);

    // Monday-based index: 0=Mon ... 6=Sun
    var jsDow = first.getDay(); // 0=Sun
    var offset = (jsDow === 0) ? 6 : (jsDow - 1);

    var totalCells = offset + last.getDate();
    var rows = Math.ceil(totalCells / 7);

    var dayNum = 1;
    for(var r=0; r<rows; r++){
      html += '<tr>';
      for(var c=0; c<7; c++){
        var cellIndex = r*7 + c;
        if(cellIndex < offset || dayNum > last.getDate()){
          html += '<td><div class="day is-empty"></div></td>';
        } else {
          var dateStr = state.month.getFullYear() + '-' + pad(state.month.getMonth()+1) + '-' + pad(dayNum);
          var info = dayInfo(dateStr);

          var ciFree = canCheckin(dateStr);
          var coFree = canCheckout(dateStr);

          // FIX: colour by NIGHT. A booking from the 23rd to the 25th occupies the nights
          // of the 23rd and 24th: before, the 23rd was painted green (only the 24th red)
          // and a 1-night stay was not visible at all.
          //  - night and previous night busy → red
          //  - only tonight busy (someone arrives) → half: you can still CHECK OUT here
          //  - only last night busy (someone leaves) → half: you can CHECK IN here
          var nightBusy = info ? ((typeof info.night_busy === 'boolean') ? info.night_busy : !info.can_checkin) : true;
          var prevBusy = info ? ((typeof info.prev_night_busy === 'boolean') ? info.prev_night_busy : !info.can_checkout) : true;
          var isPast = (dateStr < todayStr);

          var cls = 'day';
          if(isPast || !info || (!ciFree && !coFree) || (nightBusy && prevBusy)){
            cls += ' is-unavailable';
          } else if(nightBusy){
            cls += ' is-open is-half-in';
          } else if(prevBusy && dateStr !== todayStr){
            cls += ' is-open is-half-out';
          } else {
            cls += ' is-open';
          }

          // Selection should not change day colors; show selection with a ring only.
          // Highlight both endpoints (check-in/check-out) and the whole range between them.
          if(state.start && state.end){
            if(dateStr === state.start || dateStr === state.end){
              cls += ' is-selected';
            } else if(dateStr > state.start && dateStr < state.end){
              cls += ' is-range';
            }
          } else if(state.start && dateStr === state.start){
            cls += ' is-selected';
          }

          var price = priceFor(dateStr);
          // Price = price of the night starting this day → only when that night is free
          var showPrice = ((price !== null) && ciFree);
          var priceHtml = '';
          if(showPrice){
            var fullP = fmtMoney(price);
            var compactP = fmtMoneyCompact(price);
            // Put the full price in a title attribute for hover, and show a compact version on narrow screens.
            // NOTE: escape quotes for safety.
            var titleAttr = String(fullP || '').replace(/"/g, '&quot;');
            priceHtml = '<span class="price" title="' + titleAttr + '">' +
              '<span class="price-full">' + fullP + '</span>' +
              '<span class="price-compact">' + compactP + '</span>' +
              '</span>';
          }

          html += '<td><div class="' + cls + '" data-date="' + dateStr + '"><span class="num">' + dayNum + '</span>' + priceHtml + '</div></td>';
          dayNum++;
        }
      }
      html += '</tr>';
    }

    html += '</tbody>';
    tableEl.innerHTML = html;

    Array.prototype.slice.call(tableEl.querySelectorAll('.day[data-date]')).forEach(function(el){
      el.addEventListener('click', function(){
        var ds = el.getAttribute('data-date');
        if(!ds) return;

        // First click => start
        if(!state.start || (state.start && state.end)){
          setStart(ds);
          return;
        }

        // Second click => end
        if(state.start && !state.end){
          setEnd(ds);
        }
      });
    });
  }

  // Controls
  if(prevBtn){
    prevBtn.addEventListener('click', function(){
      var now = new Date();
      var curStart = new Date(now.getFullYear(), now.getMonth(), 1);
      if(state.month.getFullYear() === curStart.getFullYear() && state.month.getMonth() === curStart.getMonth()){
        return;
      }
      state.month = new Date(state.month.getFullYear(), state.month.getMonth()-1, 1);
      fetchAvailability();
    });
  }

  if(nextBtn){
    nextBtn.addEventListener('click', function(){
      state.month = new Date(state.month.getFullYear(), state.month.getMonth()+1, 1);
      fetchAvailability();
    });
  }

  if(clearBtn){
    clearBtn.addEventListener('click', function(){
      clearSelection();
      setMsg('', '');
      render();
    });
  }

  if(startInput){
    startInput.min = todayStr;
    startInput.addEventListener('change', function(){
      var ds = (startInput.value || '').trim();
      if(!ds){
        clearSelection();
        setMsg('', '');
        render();
        return;
      }
      jumpToMonthOf(ds).then(function(){
        setStart(ds);
        var e = (endInput && endInput.value) ? (endInput.value || '').trim() : '';
        if(e){
          setEnd(e);
        }
      });
    });
  }

  if(endInput){
    endInput.min = todayStr;
    endInput.addEventListener('change', function(){
      var ds = (endInput.value || '').trim();
      if(!ds){
        setEnd('');
        setMsg('', '');
        return;
      }
      jumpToMonthOf(ds).then(function(){
        setEnd(ds);
      });
    });
  }

  if(guestsInput){
    guestsInput.addEventListener('change', function(){
      var g = parseInt(guestsInput.value, 10);
      if(!isNaN(g)) state.guests = g;
      if(state.start && state.end){
        refreshQuote();
      }
    });
  }

  if(formEl){
    formEl.addEventListener('submit', function(e){
      e.preventDefault();
      if(!state.start || !state.end){
        setMsg(t('booking.messages.dates_invalid') || '', 'warn');
        return;
      }

      ensureMonthsLoaded(state.start, state.end)
        .then(function(){
          var check = validateRange(state.start, state.end);
          if(!check.ok){
            setMsg(t('booking.messages.' + check.code, check.vars) || (t('booking.messages.unavailable') || ''), 'warn');
            return;
          }

          var fd = new FormData(formEl);
          fd.set('start', state.start);
          fd.set('end', state.end);
          fd.set('guests', String(state.guests || 2));
          fd.set('csrf', csrf);

          setMsg(t('booking.messages.sending') || '', '');

          return fetch(apiBase + '/create_checkout.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
          })
          .then(function(r){ return r.json(); })
          .then(function(data){
            if(!data || !data.ok){
              var code = (data && data.error_code) ? data.error_code : 'payment_failed';
              var msg = t('booking.messages.' + code, (data && data.error_vars) ? data.error_vars : null) || (data && data.error) || '';
              throw new Error(msg || 'Error');
            }

            if(data.mode === 'checkout' && data.checkout_url){
              setMsg(t('booking.messages.redirecting_to_payment') || '', '');
              window.location.href = data.checkout_url;
              return;
            }

            setMsg(t('booking.messages.request_sent') || '', '');
            clearSelection();
            if(formEl) formEl.reset();
            return fetchAvailability();
          });
        })
        .catch(function(err){
          var base = t('booking.messages.request_failed') || '';
          setMsg(base + (err && err.message ? (' ' + err.message) : ''), 'danger');
        });
    });
  }

  function applyPrefillIfAny(){
    // Guests
    if(prefill.guests && guestsInput){
      var g = parseInt(prefill.guests, 10);
      if(!isNaN(g) && (g === 1 || g === 2 || g === 3)){
        guestsInput.value = String(g);
        state.guests = g;
      }
    }

    if(!prefill.start) return;
    if(prefill.start < todayStr){
      setMsg(t('booking.messages.past_not_allowed') || '', 'warn');
      return;
    }

    jumpToMonthOf(prefill.start).then(function(){
      if(!canCheckin(prefill.start)){
        setMsg(t('booking.messages.unavailable') || '', 'warn');
        return;
      }

      state.start = prefill.start;
      state.end = null;
      state.minNights = minNightsFor(prefill.start);
      if(startInput) startInput.value = prefill.start;
      if(endInput) endInput.value = '';
      updateEndMin();

      var minHint = '';
      if(state.minNights > 1){
        minHint = t('booking.min_nights_hint', {n: state.minNights}) || '';
      }
      if(summaryEl){
        summaryEl.textContent = t('booking.summary.checkin_selected', {start: prefill.start, minNights: minHint}) || '';
      }
      render();

      if(prefill.end){
        if(prefill.end <= prefill.start){
          return;
        }
        setMsg(t('booking.messages.loading_calendar') || '', '');
        ensureMonthsLoaded(prefill.start, prefill.end)
          .then(function(){
            var check = validateRange(prefill.start, prefill.end);
            if(!check.ok){
              setMsg(t('booking.messages.' + check.code, check.vars) || (t('booking.messages.unavailable') || ''), 'warn');
              return;
            }
            state.end = prefill.end;
            if(endInput) endInput.value = prefill.end;
            if(summaryEl){
              summaryEl.textContent = t('booking.summary.selection', {start: state.start, end: state.end, nights: check.nights}) || '';
            }
            setMsg('', '');
            render();
            refreshQuote();
          })
          .catch(function(err){
            setMsg((t('booking.messages.request_failed') || '') + ' ' + (err.message || ''), 'danger');
          });
      }
    });
  }

  // Initial state
  clearSelection();

  // Default guests from UI
  if(guestsInput){
    var g0 = parseInt(guestsInput.value, 10);
    if(!isNaN(g0)) state.guests = g0;
  }

  fetchAvailability().then(applyPrefillIfAny);
})();
