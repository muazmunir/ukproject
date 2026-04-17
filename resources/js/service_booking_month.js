import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';

(function () {
  const root = document.getElementById('serviceShowRoot');
  const calWrap = document.getElementById('bookingCalendar');
  if (!root || !calWrap) return;

  // ---------- Context from Blade ----------
  const availabilityUrl     = root.dataset.availabilityUrl;        
  const availabilityDayUrl  = root.dataset.availabilityDayUrl; {id}/availability/day
  const coachTz   = root.dataset.coachTz || 'UTC';
  const defaultTz = root.dataset.defaultTz || coachTz;
  const serviceId = root.dataset.serviceId;

  // package select (already in your UI)
  const pkgSelect  = document.getElementById('pkgSelect'); // must hold pkg days in dataset
  const chooseBtns = document.querySelectorAll('.select-package');
  const selectedDaysEl = document.getElementById('selectedDays');
  const dayTimesEl     = document.getElementById('dayTimes');

  // ---------- State ----------
  const state = {
    tz: Intl.DateTimeFormat().resolvedOptions().timeZone || defaultTz,  // client tz for display
    pkgDays: 0,                 // required number of days (5 / 7 / etc.)
    chosen: new Set(),          // yyyy-mm-dd strings
    cache: new Map(),  
    hoursPerDay: 0,
   stepMinutes: 30,         // date -> { windows:[{start,end}], slots:[{start,end}] }
  };

  chooseBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      // parse the JSON baked into the button
      const pkg = JSON.parse(btn.dataset.pkg || '{}');
  
      // keep the <select> as the single source of truth
      if (pkg?.id && pkgSelect) {
        const opt = Array.from(pkgSelect.options).find(o => o.value == String(pkg.id));
        if (opt) {
          pkgSelect.value = String(pkg.id);
          // update state from option dataset (so both paths behave the same)
          setPkgDaysFromSelect();
          renderSelectedDays();
          paintSelectionBorders();
  
          // re-load any already-chosen days with the new hours_per_day
          const dates = Array.from(state.chosen);
          state.cache.clear();
          Promise.all(dates.map(d => ensureDayLoaded(d))).then(renderTimesForSelected);
        }
      }
    });
  });

  function setPkgDaysFromSelect() {
    const opt = pkgSelect?.selectedOptions?.[0];
    state.pkgDays     = Number(opt?.dataset?.totalDays     || 0); // data-total-days
    state.hoursPerDay = Number(opt?.dataset?.hoursPerDay   || 0); // data-hours-per-day
    state.cache.clear();
  }
  

  // ---------- Calendar ----------
  const calendar = new Calendar(calWrap, {
    plugins: [dayGridPlugin],
    timeZone: state.tz,          // month-only view; painting is background only
    initialView: 'dayGridMonth',
    selectable: false,
    dayMaxEvents: true,
    height: 'auto',
    headerToolbar: { left: 'prev,next today', center: 'title', right: '' },

    dateClick: async (info) => {
       if (!state.hoursPerDay) {
           // gentle hint – or show a toast
           dayTimesEl.innerHTML = `<span class="text-muted">Select a package to see times.</span>`;
           return;
         }
      const ymd = info.dateStr; // YYYY-MM-DD
      if (state.chosen.has(ymd)) {
        state.chosen.delete(ymd);                    // toggle off
        renderSelectedDays();
        renderTimesForSelected();                    // rebuild list
        paintSelectionBorders();
        return;
      }
      // enforce package day count
      if (state.pkgDays && state.chosen.size >= state.pkgDays) {
        // flash or toast
        info.dayEl.classList.add('shake');
        setTimeout(()=>info.dayEl.classList.remove('shake'), 400);
        return;
      }
      state.chosen.add(ymd);                         // toggle on
      renderSelectedDays();
      await ensureDayLoaded(ymd);
      renderTimesForSelected();
      paintSelectionBorders();
    },
    // We still want to show weekly/unavail/override backgrounds; reuse your /events if you like:
    events: (info, success, failure) => {
      // optional: keep your coach calendar events feed to paint blue/red/green as bg
      const url = new URL(availabilityUrl, window.location.origin); // supply this in Blade
  url.searchParams.set('start', info.startStr);
  url.searchParams.set('end',   info.endStr);
  url.searchParams.set('tz',    state.tz);   // tell server we’re a client in this tz
  fetch(url, { headers: {'X-Requested-With':'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
      // if you left the 'debug' wrapper on the controller, use data.events
      success(data.events ?? data);
    })
    .catch(failure); // keep empty if you don’t need painting on client
    },
  });
  calendar.render();

  // ---------- Painting: outline selected cells ----------
  function paintSelectionBorders() {
    const dayEls = calWrap.querySelectorAll('.fc-daygrid-day[data-date]');
    dayEls.forEach(el => {
      const ymd = el.getAttribute('data-date');
      el.classList.toggle('zv-selected', state.chosen.has(ymd));
    });
  }

  // ---------- Selected chips ----------
  function renderSelectedDays() {
    const arr = Array.from(state.chosen).sort();
    selectedDaysEl.innerHTML = arr.length
      ? `<div class="d-flex flex-wrap gap-2">
          ${arr.map(d=>`<span class="badge bg-light text-dark border">${d}</span>`).join('')}
         </div>
         <small class="text-muted d-block mt-1">
           ${state.pkgDays ? `${arr.length}/${state.pkgDays} day(s) selected` : `${arr.length} day(s) selected`}
         </small>`
      : `<small class="text-muted">Select ${state.pkgDays || ''} day(s) on the calendar.</small>`;
  }

  // ---------- Fetch per-day availability (windows/slots) ----------
  async function ensureDayLoaded(ymd) {
    if (state.cache.has(ymd)) return;
    const url = new URL(availabilityDayUrl, window.location.origin);
    url.searchParams.set('date', ymd);           // YYYY-MM-DD (coach day basis in server)
    url.searchParams.set('tz',   state.tz);  
    
    if (state.hoursPerDay > 0) {
           url.searchParams.set('hours_per_day', String(state.hoursPerDay));
           url.searchParams.set('step_minutes',  String(state.stepMinutes)); // 30
         }// client tz for slot display
    const res = await fetch(url, { headers: {'X-Requested-With':'XMLHttpRequest'} });
    const data = await res.json();               // { windows:[], slots:[] }
    state.cache.set(ymd, data || { windows: [], slots: [] });
  }

  // ---------- Render times for all selected days ----------
  function renderTimesForSelected() {
    const arr = Array.from(state.chosen).sort();
    if (!arr.length) { dayTimesEl.innerHTML = ''; return; }

    dayTimesEl.innerHTML = arr.map(ymd => {
      const data = state.cache.get(ymd);
      const slots = data?.slots || [];
      const buttons = slots.length
        ? slots.map(s => `
            <button type="button" class="btn btn-sm btn-outline-dark me-2 mb-2"
                    data-day="${ymd}" data-start="${s.start}" data-end="${s.end}">
              ${fmtTime(s.start)} – ${fmtTime(s.end)}
            </button>`).join('')
        : `<span class="text-muted">No times available</span>`;

      return `
        <div class="mb-3">
          <div class="fw-medium mb-1">${ymd}</div>
          <div>${buttons}</div>
        </div>`;
    }).join('');

    // attach handlers for choosing a slot (one per day)
    dayTimesEl.querySelectorAll('button[data-day]').forEach(btn => {
      btn.addEventListener('click', () => {
        const day = btn.dataset.day;
        // toggle active, one per day
        dayTimesEl
          .querySelectorAll(`button[data-day="${day}"]`)
          .forEach(b => b.classList.toggle('btn-dark', b === btn));
        dayTimesEl
          .querySelectorAll(`button[data-day="${day}"]`)
          .forEach(b => b.classList.toggle('btn-outline-dark', b !== btn));
        // TODO: write hidden inputs for form submission per day
        // e.g., <input name="days[YYYY-MM-DD][start]" value="ISOZ">
      });
    });
  }

  function fmtTime(iso) {
    // show in client local time HH:mm (or 12h if you want)
    const d = new Date(iso);
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }

  // ---------- Package select wiring ----------
  pkgSelect?.addEventListener('change', () => {
    setPkgDaysFromSelect();
    renderSelectedDays();
    paintSelectionBorders();
  });
  setPkgDaysFromSelect();
})();
