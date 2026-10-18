(function(){
  function pad(n){ return n<10?('0'+n):(''+n); }
  function parseYmd(s){
    var m = /^([0-9]{4})-([0-9]{2})-([0-9]{2})$/.exec(s);
    if(!m) return null;
    return new Date(Number(m[1]), Number(m[2])-1, Number(m[3]));
  }
  function ymd(d){ return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate()); }
  function addDays(date, days){ var d = new Date(date.getTime()); d.setDate(d.getDate()+days); return d; }

  var table = document.getElementById('adminCalTable');
  if(!table) return;

  // Markup IDs must match the admin template.
  // Keep backward-compatibility with older IDs to avoid silent breakages.
  var selInput = document.getElementById('adminDatesCsv') || document.getElementById('selectedDatesInput');
  var selInputClear = document.getElementById('adminDatesCsvClear') || document.getElementById('selectedDatesInputClear');
  var info = document.getElementById('adminSelInfo') || document.getElementById('selectedInfo');
  var clearBtn = document.getElementById('adminClearSel');

  var bulkForm = document.getElementById('adminBulkUpdate');
  var clearForm = document.getElementById('adminBulkClear');

  var selected = new Set();
  var last = null;

  function setSelected(dateStr, on){
    // Keep selections even if the day is not currently visible in the monthly view.
    // (Useful for bulk updates across months via the range picker.)
    if(on){
      selected.add(dateStr);
    } else {
      selected.delete(dateStr);
    }

    var btn = table.querySelector('.day[data-date="'+dateStr+'"]');
    if(!btn) return;
    if(on){
      btn.classList.add('is-selected');
    } else {
      btn.classList.remove('is-selected');
    }
  }

  function updateInputs(){
    var arr = Array.from(selected);
    arr.sort();
    var csv = arr.join(',');
    if(selInput) selInput.value = csv;
    if(selInputClear) selInputClear.value = csv;

    if(info){
      if(arr.length===0){
        info.textContent = 'Nessun giorno selezionato.';
      } else if(arr.length===1){
        info.textContent = 'Selezionato: ' + arr[0];
      } else {
        info.textContent = 'Selezionati ' + arr.length + ' giorni (' + arr[0] + ' → ' + arr[arr.length-1] + ')';
      }
    }
  }

  function clearSelection(){
    selected.forEach(function(d){
      var btn = table.querySelector('.day[data-date="'+d+'"]');
      if(btn) btn.classList.remove('is-selected');
    });
    selected.clear();
    last = null;
    updateInputs();
  }

  table.addEventListener('click', function(e){
    var btn = e.target.closest('.day[data-date]');
    if(!btn) return;
    var dateStr = btn.getAttribute('data-date');
    if(!dateStr) return;

    // Shift+click => range select
    if(e.shiftKey && last){
      var a = parseYmd(last);
      var b = parseYmd(dateStr);
      if(a && b){
        var step = a <= b ? 1 : -1;
        var d = a;
        while(true){
          var ds = ymd(d);
          setSelected(ds, true);
          if(ds === ymd(b)) break;
          d = addDays(d, step);
        }
      }
    } else {
      // Toggle
      var isOn = selected.has(dateStr);
      setSelected(dateStr, !isOn);
    }

    last = dateStr;
    updateInputs();
  });

  if(clearBtn){
    clearBtn.addEventListener('click', clearSelection);
  }

  // ------------------------------------------------------------
  // Quick selection tools (range + weekends + weekdays)
  // ------------------------------------------------------------
  var rangeStartEl = document.getElementById('adminRangeStart');
  var rangeEndEl = document.getElementById('adminRangeEnd');
  var selectRangeBtn = document.getElementById('adminSelectRangeBtn');
  var selectWeekendBtn = document.getElementById('adminSelectWeekendBtn');
  var deselectWeekendBtn = document.getElementById('adminDeselectWeekendBtn');
  var addDowBtn = document.getElementById('adminAddDowBtn');
  var removeDowBtn = document.getElementById('adminRemoveDowBtn');

  function getRange(){
    var s = rangeStartEl ? (rangeStartEl.value || '').trim() : '';
    var e = rangeEndEl ? (rangeEndEl.value || '').trim() : '';
    var ds = parseYmd(s);
    var de = parseYmd(e);
    if(!ds || !de) return null;
    // Normalize order
    if(ds > de){
      var tmp = ds; ds = de; de = tmp;
    }
    return { start: ds, end: de };
  }

  function iterRange(range, cb){
    if(!range) return;
    var d = range.start;
    var end = range.end;
    while(d <= end){
      cb(d);
      d = addDays(d, 1);
    }
  }

  function requireRange(){
    var r = getRange();
    if(r) return r;
    if(info){
      info.textContent = 'Imposta un intervallo (Da/A) per usare la selezione rapida.';
    } else {
      alert('Imposta un intervallo (Da/A).');
    }
    return null;
  }

  if(selectRangeBtn){
    selectRangeBtn.addEventListener('click', function(){
      var r = requireRange();
      if(!r) return;
      iterRange(r, function(d){
        setSelected(ymd(d), true);
      });
      last = ymd(r.end);
      updateInputs();
    });
  }

  function isWeekend(dateObj){
    var dow = dateObj.getDay(); // 0=Sun ... 6=Sat
    return dow === 0 || dow === 6;
  }

  if(selectWeekendBtn){
    selectWeekendBtn.addEventListener('click', function(){
      var r = requireRange();
      if(!r) return;
      iterRange(r, function(d){
        if(isWeekend(d)) setSelected(ymd(d), true);
      });
      updateInputs();
    });
  }

  if(deselectWeekendBtn){
    deselectWeekendBtn.addEventListener('click', function(){
      var r = requireRange();
      if(!r) return;
      iterRange(r, function(d){
        if(isWeekend(d)) setSelected(ymd(d), false);
      });
      updateInputs();
    });
  }

  function selectedDows(){
    var boxes = document.querySelectorAll('.adminDow');
    var dows = [];
    Array.prototype.forEach.call(boxes, function(b){
      if(b && b.checked){
        var v = parseInt(b.value, 10);
        if(!isNaN(v)) dows.push(v);
      }
    });
    return dows;
  }

  function applyDowSelection(mode){
    var r = requireRange();
    if(!r) return;
    var dows = selectedDows();
    if(!dows.length){
      if(info) info.textContent = 'Seleziona almeno un giorno della settimana (Lun/Dom).';
      return;
    }
    iterRange(r, function(d){
      // Convert JS Sunday=0 .. Saturday=6 into our checkbox values (same)
      var dow = d.getDay();
      if(dows.indexOf(dow) !== -1){
        setSelected(ymd(d), mode === 'add');
      }
    });
    updateInputs();
  }

  if(addDowBtn){
    addDowBtn.addEventListener('click', function(){ applyDowSelection('add'); });
  }
  if(removeDowBtn){
    removeDowBtn.addEventListener('click', function(){ applyDowSelection('remove'); });
  }

  function ensureSelection(e){
    if(selected.size > 0) return true;
    if(e && e.preventDefault) e.preventDefault();
    if(info){
      info.textContent = 'Seleziona almeno un giorno nel calendario.';
    } else {
      alert('Seleziona almeno un giorno nel calendario.');
    }
    return false;
  }

  if(bulkForm){
    bulkForm.addEventListener('submit', ensureSelection);
  }
  if(clearForm){
    clearForm.addEventListener('submit', ensureSelection);
  }

  // Initialize from any pre-filled selection
  updateInputs();
})();
