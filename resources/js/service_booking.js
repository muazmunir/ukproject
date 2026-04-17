// resources/js/service_booking.js

import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import luxon2Plugin from '@fullcalendar/luxon2';

(function () {

  window.zToast = window.zToast || function (msg, type = 'ok') {
  if (typeof Toastify === 'undefined') {
    console.warn('Toastify not loaded:', msg);
    return;
  }

  Toastify({
    text: msg,
    duration: 3000,
    gravity: "top",
    position: "right",
    close: true,
    backgroundColor: type === 'ok' ? "#16a34a" : "#dc2626",
  }).showToast();
};
  const root   = document.getElementById('serviceShowRoot');
  const calWrap = document.getElementById('bookingCalendar');
  if (!root || !calWrap) {
    console.warn('[ServiceBooking] Root or Calendar not found.');
    return;
  }

  // ---------- Context from Blade ----------
  const availabilityUrl     = root.dataset.availabilityUrl;
  const availabilityDayUrl  = root.dataset.availabilityDayUrl;  // NEW (server /day endpoint)
  const coachTz             = root.dataset.coachTz || 'UTC';
  const defaultTz           = root.dataset.defaultTz || coachTz;
  const serviceId           = root.dataset.serviceId;


  const calCountEl = document.getElementById('zvCalCount');


  // ---------- UI refs ----------
  // ---------- UI refs ----------
const exploreDaysEl     = document.getElementById('exploreDays');
const reservedDaysEl    = document.getElementById('reservedDays');

const dayTimesEl        = document.getElementById('dayTimes');        // left slots list

  const tzBadge         = document.getElementById('clientTzBadge');
  const tzLabel         = document.getElementById('clientTzLabel'); // ⭐ we'll update ONLY this
  const tzSelect        = document.getElementById('clientTzSelect');
  const tzHintEl        = document.getElementById('clientTzHint');  // optional small text

  // ---------- TZ prefs ----------
  const deviceTz = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
  const LS_KEY   = 'zv_client_tz_pref';

  function loadTzPref() {
    const v = localStorage.getItem(LS_KEY);
    if (!v) return 'auto';
    try { return JSON.parse(v); } catch { return 'auto'; }
  }
  function saveTzPref(v) { localStorage.setItem(LS_KEY, JSON.stringify(v)); }
  function resolveClientTz(pref) { return (!pref || pref === 'auto') ? deviceTz : pref; }

  let tzPref   = loadTzPref();            // 'auto' or an IANA TZ
  let clientTz = resolveClientTz(tzPref); // actual TZ used for render + fetch
  let currentTz = clientTz;               // source of truth for calendar

  function paintTzUi() {
    // ⭐ Do NOT overwrite the badge's HTML (icon). Only set the inner label.
    if (tzLabel) tzLabel.textContent = `${clientTz}${tzPref === 'auto' ? ' (auto)' : ''}`;
    if (tzSelect) {
      const wanted = tzPref || 'auto';
      if ([...tzSelect.options].some(o => o.value === wanted)) tzSelect.value = wanted;
      else tzSelect.value = 'auto';
    }
  }



  function updateCalCounter() {
  if (!calCountEl) return;
  const max = MAX_CAL_DAYS || 7;
  calCountEl.textContent = `${state.chosen.size} / ${max} selected`;
}

 function formatMDY(ymd) {
  if (!ymd || typeof ymd !== 'string') return '';
  const parts = ymd.split('-');
  if (parts.length !== 3) return ymd;

  const [y, m, d] = parts.map(Number);
  const date = new Date(y, (m || 1) - 1, d || 1);

  return date.toLocaleDateString('en-US', {
    month: '2-digit',
    day: '2-digit',
    year: 'numeric'
  });
}



  // ---------- Package state ----------
  const pkgSelect = document.getElementById('pkgSelect');
  const state = {
    pkgDays: 0,           // allowed days you can pick (package total_days)
    hoursPerDay: 0,       // ⭐ package hours_per_day (drives slot length)
    chosen: new Set(),    // yyyy-mm-dd strings
    cache: new Map(),     // date -> { slots: [{start,end}], windows:[...] }
  };
    const MAX_CAL_DAYS = 7;    // user can explore up to 7 dates in the calendar

    // Booking card UI refs
  const packageIdHidden = document.getElementById('packageId');
  const priceHeader     = document.getElementById('priceHeader'); // big price at top
  const priceTotal      = document.getElementById('priceTotal');  // "Total" line
  const pkgMeta         = document.getElementById('pkgMeta');     // small text under select

  // Helper to format money nicely
  function formatMoney(v) {
    const n = Number(v || 0);
    return new Intl.NumberFormat('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(n);
  }

  // Update booking card prices from the selected <option> data-*
  function updatePackagePricingUI(opt) {
    if (!opt) return;

    const totalPrice = parseFloat(opt.dataset.totalPrice || '0');
    const hourlyRate = parseFloat(opt.dataset.hourlyRate || '0');
    const totalHours = parseFloat(opt.dataset.totalHours || '0');
    const totalDays  = parseInt(opt.dataset.totalDays || '0', 10);

    let displayPrice = 0;
    let metaText = '';

    if (totalPrice) {
      // If package has an explicit total_price
      displayPrice = totalPrice;
      metaText = 'Package Total';
    } else if (hourlyRate && totalHours) {
      displayPrice = hourlyRate * totalHours;
      metaText = `${totalHours}h × $${formatMoney(hourlyRate)} /h`;
    } else if (hourlyRate && totalDays) {
      displayPrice = hourlyRate * totalDays;
      metaText = `${totalDays} day(s) × $${formatMoney(hourlyRate)} /day`;
    } else if (hourlyRate) {
      displayPrice = hourlyRate;
      metaText = 'Per hour';
    }

    // Big header price
    if (displayPrice && priceHeader) {
      priceHeader.innerHTML =
        `$${formatMoney(displayPrice)} ` +
        `<span class="subtle small">/ session</span>`;
      // change "/ session" to "/ package" if you prefer
    }

    // "Total" line
    if (displayPrice && priceTotal) {
      priceTotal.textContent = `$${formatMoney(displayPrice)}`;
    }

    // Small description under select
    if (pkgMeta && metaText) {
      pkgMeta.textContent = metaText;
    }
  }

  

  // Keep the user's chosen slot for each selected day
const selected = new Map();  // ymd -> { start, end }

// Return the array shape the form expects
function computeSelectedDays() {
  return Array.from(selected, ([date, v]) => ({ date, start: v.start, end: v.end }))
    .sort((a, b) => a.date.localeCompare(b.date));
}


// Public mirror the form serializer reads
window.__selectedDays = [];



    function setPkgFromSelect() {
    const opt = pkgSelect?.selectedOptions?.[0];

    state.pkgDays     = Number(opt?.dataset?.totalDays || 0);
    state.hoursPerDay = Number(opt?.dataset?.hoursPerDay || 0);

    // 🟦 Update price header + total when package changes
    updatePackagePricingUI(opt);

    // If selection exceeds new pkg days, reset dates & slots
   // If we already booked more session days than the package allows,
// trim the extra booked days (but keep calendar selections for exploring).
if (state.pkgDays > 0 && selected.size > state.pkgDays) {
  const toRemove = selected.size - state.pkgDays;
  const keys = Array.from(selected.keys());
  keys.slice(-toRemove).forEach(d => selected.delete(d));
  window.__selectedDays = computeSelectedDays();
  renderReservedSessions();
  renderTimesForSelected();
}

renderReservedSessions();


  }


    // Initialise hidden package_id from current select (if any)
  if (packageIdHidden && pkgSelect?.value) {
    packageIdHidden.value = pkgSelect.value;
  }

  // Single change handler: sync hidden field, pricing, slots, etc.
  pkgSelect?.addEventListener('change', async () => {
  if (packageIdHidden) {
    packageIdHidden.value = pkgSelect.value || '';
  }

  setPkgFromSelect();         // updates pkgDays/hoursPerDay + price UI
  renderExploreDays();
  renderReservedSessions();
  paintSelectionBorders();

  // If user already picked days, refresh their slots for this package
  await Promise.all([...state.chosen].map(ensureDayLoaded));
  renderTimesForSelected();
});

  // Initial state (if something pre-selected on load)
  setPkgFromSelect();
  renderExploreDays();
  updateCalCounter();

renderReservedSessions();

    // Clicking "Choose" on a package card should sync the select in the booking card
  document.querySelectorAll('.select-package').forEach(btn => {
    btn.addEventListener('click', () => {
      const raw = btn.getAttribute('data-pkg');
      if (!raw || !pkgSelect) return;

      let data;
      try {
        data = JSON.parse(raw);
      } catch (e) {
        console.warn('[ServiceBooking] invalid data-pkg JSON', e, raw);
        return;
      }

      // 1) Set the select value to this package id
      pkgSelect.value = String(data.id);

      // 2) Trigger the normal change handler
      pkgSelect.dispatchEvent(new Event('change', { bubbles: true }));

      // 3) (Optional) visually show which "Choose" is active
      document.querySelectorAll('.select-package').forEach(b => {
        b.classList.remove('btn-dark');
        b.classList.add('btn-outline-dark');
      });
      btn.classList.remove('btn-outline-dark');
      btn.classList.add('btn-dark');

      // 4) (Optional) scroll to the booking card so user sees it changed
      document.getElementById('bookingForm')?.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
      });
    });
  });


  // ---------- Calendar helpers ----------
  function paintSelectionBorders() {
  calWrap.querySelectorAll('.fc-daygrid-day[data-date]').forEach(el => {
    const ymd = el.getAttribute('data-date');

    // explore = red
    if (state.chosen.has(ymd)) el.classList.add('zv-explore-day');
    else el.classList.remove('zv-explore-day');

    // reserved = green
    if (selected.has(ymd)) el.classList.add('zv-reserved-day');
    else el.classList.remove('zv-reserved-day');
  });
}


  // LEFT: candidate days (up to MAX_CAL_DAYS)
function renderExploreDays() {
  if (!exploreDaysEl) return;

  const arr = Array.from(state.chosen).sort();

  if (!arr.length) {
    exploreDaysEl.innerHTML = `
      <div class="small text-muted">
        Select Up To ${MAX_CAL_DAYS} Days In The Calendar To View Available Time Slots.
      </div>
    `;
    return;
  }

  const chipsHtml = arr.map(d => `
    <span class="zv-chip-day zv-chip-red">

     ${formatMDY(d)}

      <button type="button"
              class="zv-chip-remove"
              data-day="${d}"
              aria-label="Remove ${d}">
        &times;
      </button>
    </span>
  `).join('');

  exploreDaysEl.innerHTML = `
    <div class="d-flex justify-content-between align-items-center mb-1">
      <div class="small fw-semibold">Days Selected To View Timings</div>
      <div class="small text-muted">${arr.length} / ${MAX_CAL_DAYS}</div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      ${chipsHtml}
    </div>
  `;

  // Cross on candidate days
  exploreDaysEl.querySelectorAll('.zv-chip-remove').forEach(btn => {
    const day = btn.dataset.day;
    btn.addEventListener('click', () => {
      // remove from candidate list
      state.chosen.delete(day);
      updateCalCounter();


      // if that day had a reserved session, remove that too
      if (selected.has(day)) {
        selected.delete(day);
        window.__selectedDays = computeSelectedDays();
        renderReservedSessions();
      }

      renderExploreDays();

      renderTimesForSelected();
      paintSelectionBorders();
    });
  });
}

// RIGHT: reserved sessions for this package
function renderReservedSessions() {
  if (!reservedDaysEl) return;

  const sessions = computeSelectedDays();
  const count = sessions.length;
  const pkgDays = state.pkgDays || 0;

  if (!count) {
    reservedDaysEl.innerHTML = `
      <div class="small text-muted">
        No Session Days Selected Yet. Choose a Time Slot From The Left To Add It Here.
      </div>
      ${pkgDays ? `<div class="small text-muted mt-1">
        This Package Includes ${pkgDays} Day(s).
      </div>` : ''}
    `;
    return;
  }

  const itemsHtml = sessions.map(d => `
    <div class="d-flex justify-content-between align-items-center mb-1">
      <div>
        <div class="fw-semibold small">${formatMDY(d.date)}</div>
        <div class="small text-muted">${fmtTime(d.start)} – ${fmtTime(d.end)}</div>
      </div>
      <button type="button"
              class="btn btn-link btn-sm p-0 zv-chip-remove"
              data-day="${d.date}">
        &times;
      </button>
    </div>
  `).join('');

  const label = pkgDays
    ? `Selected ${count} Of ${pkgDays} Session Day(s)`
    : `Selected ${count} Session Day(s)`;

  reservedDaysEl.innerHTML = `
    <div class="small fw-semibold mb-1">${label}</div>
    ${itemsHtml}
  `;

  // Cross to remove reserved session
  reservedDaysEl.querySelectorAll('[data-day]').forEach(btn => {
    const day = btn.dataset.day;
    btn.addEventListener('click', () => {
      selected.delete(day);
      window.__selectedDays = computeSelectedDays();
      renderReservedSessions();
      renderTimesForSelected(); // un-highlight slot for that day
    });
  });
}




  // ⭐ Fetch day windows/slots with hours_per_day & step=30
  async function ensureDayLoaded(ymd) {
    if (!ymd) return;
    if (state.cache.has(ymd)) return;

    const hpd = state.hoursPerDay > 0 ? state.hoursPerDay : 0;
    const url = new URL(availabilityDayUrl, window.location.origin);
    url.searchParams.set('date', ymd);
    url.searchParams.set('tz', currentTz);
    if (hpd) url.searchParams.set('hpd', String(hpd)); // server builds rolling windows of length hpd hours
    url.searchParams.set('step', '30');

    const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json(); // { slots:[{start,end}], windows:[...] }
    state.cache.set(ymd, data || { slots: [] });
  }

  function fmtTime(isoUtc) {
    // show in the viewer’s (client) tz
    const d = new Date(isoUtc);
    return d.toLocaleTimeString([], { timeZone: currentTz, hour: '2-digit', minute: '2-digit' });
  }

  function renderTimesForSelected() {
  if (!dayTimesEl) return;

  const arr = Array.from(state.chosen).sort();
  if (!arr.length) { dayTimesEl.innerHTML = ''; return; }

    dayTimesEl.innerHTML = arr.map(ymd => {
      const data  = state.cache.get(ymd) || { slots: [] };
      const slots = data.slots || [];
      const buttons = slots.length
        ? slots.map(s => `
            <button type="button" class="btn btn-sm btn-outline-dark me-2 mb-2"
                    data-day="${ymd}" data-start="${s.start}" data-end="${s.end}">
              ${fmtTime(s.start)} – ${fmtTime(s.end)}
            </button>`).join('')
        : `<span class="text-muted">No times available</span>`;
      return `
        <div class="mb-3">
          <div class="fw-medium mb-1">${formatMDY(ymd)}</div>
          <div>${buttons}</div>
        </div>`;
    }).join('');

    // choose exactly one slot per selected day (toggle)
   // choose exactly one slot per selected day (toggle)
dayTimesEl.querySelectorAll('button[data-day]').forEach(btn => {
  const day = btn.dataset.day;
  const s   = btn.dataset.start;
  const e   = btn.dataset.end;

  // Pre-highlight if this slot is already chosen for that day
  if (selected.has(day) && selected.get(day).start === s && selected.get(day).end === e) {
    btn.classList.remove('btn-outline-dark');
    btn.classList.add('btn-dark');
  }

 btn.addEventListener('click', () => {
  const pkgDays = state.pkgDays || 0;

  // Enforce package session limit
  if (pkgDays && !selected.has(day) && selected.size >= pkgDays) {
   window.zToast(
  `This Package Includes ${pkgDays} Day(s). You Have Already Chosen ${pkgDays} Session Day(s).`,
  'error'
);
return;

  }

  // Save reserved session
  selected.set(day, { start: s, end: e });
  window.__selectedDays = computeSelectedDays();
  renderReservedSessions(); // update right box
  // remove day from explore list once reserved
state.chosen.delete(day);
updateCalCounter();


renderExploreDays();
paintSelectionBorders();


  // Toggle visuals for that day only
  dayTimesEl.querySelectorAll(`button[data-day="${day}"]`).forEach(b => {
    b.classList.toggle('btn-dark', b === btn);
    b.classList.toggle('btn-outline-dark', b !== btn);
  });
});


});

  }

  function asInTz(iso, tz) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleString([], { timeZone: tz, hour12: false, hour: '2-digit', minute: '2-digit' });
  }

  function paintDailyHintFor(date) {
    if (!tzHintEl) return;
    const events = calendar.getEvents().filter(e => e.display === 'background' && e.extendedProps?.type === 'weekly');
    const match = events.find(e => {
      const s = e.start; const eend = e.end;
      return s && eend && date >= s && date < eend;
    });
    if (match) {
      const s = match.start.toISOString();
      const e = match.end.toISOString();
      const clientStart = asInTz(s, currentTz);
      const clientEnd   = asInTz(e, currentTz);
      tzHintEl.textContent = `Shown in your time: ${clientStart}–${clientEnd} (${currentTz})`;
    } else {
      tzHintEl.textContent = `Times Shown In Your Timezone (${currentTz}).`;
    }
  }

  console.log('[ServiceBooking] init', { availabilityUrl, availabilityDayUrl, coachTz, defaultTz, deviceTz, tzPref, clientTz, currentTz });

  // ---------- Calendar ----------
  const calendar = new Calendar(calWrap, {
    plugins: [dayGridPlugin, interactionPlugin, luxon2Plugin],
    timeZone: currentTz,
    initialView: 'dayGridMonth',
    headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
    buttonText: {
    today: 'Today'
  },

   dateClick: async (info) => {
  // 🚫 1) Block past days completely
  if (info.dayEl.classList.contains('fc-day-past')) {
    return;
  }

  // ⭐ 2) Require a package first (we need hours_per_day)
  
  if (!state.hoursPerDay || !pkgSelect?.value) {
  info.dayEl.classList.add('shake');
  setTimeout(() => info.dayEl.classList.remove('shake'), 400);

  window.zToast('Please Select a Package Before Choosing Dates.', 'error');
  return;
}


  const ymd = info.dateStr;

  // Toggle off if already selected as candidate
  if (state.chosen.has(ymd)) {
    state.chosen.delete(ymd);
    updateCalCounter();


    // also drop any reserved session for that day
    if (selected.has(ymd)) {
      selected.delete(ymd);
      window.__selectedDays = computeSelectedDays();
      renderReservedSessions();
    }

    renderExploreDays();
    renderTimesForSelected();
    paintSelectionBorders();
    return;
  }

  // ✅ New: allow up to 7 calendar days as candidates
  if (state.chosen.size >= MAX_CAL_DAYS) {
  info.dayEl.classList.add('shake');
  setTimeout(() => info.dayEl.classList.remove('shake'), 400);
  window.zToast(`You Can Select Up To ${MAX_CAL_DAYS} Day(s).`, 'error');
  return;
}

  // Add new candidate day
  state.chosen.add(ymd);
  updateCalCounter();

  renderExploreDays();
  await ensureDayLoaded(ymd);
  renderTimesForSelected();
  paintSelectionBorders();
},


    // Background paints: weekly (coach-TZ ISO with offset), unavail & overrides (UTC Z)
    events: (info, success, failure) => {
      const url = new URL(availabilityUrl, window.location.origin);
      url.searchParams.set('start', info.startStr); // FullCalendar emits TZ-aware strings
      url.searchParams.set('end',   info.endStr);
      url.searchParams.set('tz',    currentTz);

      fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(async (r) => {
          const data = await r.json();
          const events = Array.isArray(data) ? data : (data.events || []);
          success(events);
          const mid = new Date(info.start.getTime() + (info.end.getTime() - info.start.getTime()) / 2);
          paintDailyHintFor(mid);
        })
        .catch(err => { console.error('[ServiceBooking] availability fetch failed', err); failure(err); });
    },

    eventClassNames(arg) { return arg.event.extendedProps?.classNames || []; },
    eventDidMount(info) {
      if (info.event.display === 'background') info.el.classList.add('fc-bg-event');
    },
    datesSet(arg) {
      const mid = new Date(arg.start.getTime() + (arg.end.getTime() - arg.start.getTime()) / 2);
      paintDailyHintFor(mid);
    }
  });

  calendar.render();
  paintTzUi();

  // ---------- TZ Selector ----------
  tzSelect?.addEventListener('change', () => {
    tzPref   = tzSelect.value;                 // 'auto' or IANA
    clientTz = resolveClientTz(tzPref);
    currentTz = clientTz;
    saveTzPref(tzPref);
    paintTzUi();
    calendar.setOption('timeZone', currentTz);
    calendar.refetchEvents();

    // Repaint visible day slot labels in new TZ
    renderTimesForSelected();
  });

  // ---------- Public helper ----------
  window.__zvFetchAvailability = async function ({ tz } = {}) {
    if (tz && tz !== clientTz) {
      tzPref   = tz;
      clientTz = resolveClientTz(tz);
      currentTz = clientTz;
      saveTzPref(tzPref);
      paintTzUi();
      calendar.setOption('timeZone', currentTz);
    }
    await calendar.refetchEvents();
    renderTimesForSelected();
  };

  // Optional submit guard
// ---- Form serialization + validation (used by services.show form onsubmit) ----
(function(){
  const form     = document.getElementById('bookingForm');
  const tzSel    = document.getElementById('clientTzSelect');
  const tzHidden = document.getElementById('clientTzHidden');

  function serializeDaysIntoForm() {
    if (!form) return;
    // clear previous hidden inputs
    [...form.querySelectorAll('input[name^="days["]')].forEach(el => el.remove());

    // ensure mirror is current
    window.__selectedDays = computeSelectedDays();

    window.__selectedDays.forEach((d, i) => {
      ['date','start','end'].forEach(key => {
        const input = document.createElement('input');
        input.type  = 'hidden';
        input.name  = `days[${i}][${key}]`;
        input.value = d[key];
        form.appendChild(input);
      });
    });
  }

  window.__validateBooking = function () {
    console.log('DEBUG submitting', {
      pkg: document.getElementById('pkgSelect')?.value,
      selectedDays: window.__selectedDays
    });
    const pkg = document.getElementById('pkgSelect')?.value;
   if (!pkg) {
  window.zToast('Please Select a Package First.', 'error');
  return false;
}


    const days = Array.isArray(window.__selectedDays) ? window.__selectedDays : [];
   if (!days.length || days.some(d => !d.date || !d.start || !d.end)) {
  window.zToast('Please Select a Time Slot For Each Chosen Day.', 'error');
  return false;
}

    // Enforce that the number of booked session days matches the package days
const pkgSelectEl = document.getElementById('pkgSelect');
const pkgOpt = pkgSelectEl?.selectedOptions?.[0];
const requiredDays = parseInt(pkgOpt?.dataset.totalDays || '0', 10);

if (requiredDays && days.length !== requiredDays) {
  window.zToast(
    `This package requires ${requiredDays} day(s). You have selected ${days.length}.`,
    'error'
  );
  return false;
}



    // forward current client timezone preference
    if (tzSel && tzHidden) {
      tzHidden.value = tzSel.value === 'auto'
        ? (Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC')
        : tzSel.value;
    }

    serializeDaysIntoForm();
    return true; // auth middleware will redirect guests to login then back
  };
})();

})();
