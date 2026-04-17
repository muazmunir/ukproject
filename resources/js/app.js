import './bootstrap';
// import './date'; 
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';


import flatpickr from "flatpickr";

import 'flatpickr/dist/flatpickr.css';

// --- helpers ---
const csrfToken  = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
const getCookie  = (name) => document.cookie.split('; ').find(r => r.startsWith(name + '='))?.split('=')[1];
const xsrfToken  = () => decodeURIComponent(getCookie('XSRF-TOKEN') || '');

document.addEventListener('DOMContentLoaded', async () => {
  const el = document.getElementById('coachCalendar');
  if (!el) return;

  // URLs from Blade
  const eventsUrl        = el.dataset.eventsUrl;
  const storeUrl         = el.dataset.storeUrl;
  const clearUrl         = el.dataset.clearUrl;
  const scheduleGetUrl   = el.dataset.scheduleGetUrl;
  const scheduleSaveUrl  = el.dataset.scheduleSaveUrl;
  const availOverrideUrl = el.dataset.availOverrideUrl;
  

  const lockUrl          = el.dataset.lockUrl;
  const getCurrentDeviceTz = () => Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
  const getCurrentOffset  = () => new Date().getTimezoneOffset(); // minutes
  // UI bits
  const getTz = () => (el.dataset.defaultTz || 'UTC'); // coach tz only

// coach tz only

  const unavailModalEl  = document.getElementById('unavailModal');
  const availModalEl    = document.getElementById('availModal');
  const unavailModal    = window.bootstrap && unavailModalEl ? new window.bootstrap.Modal(unavailModalEl) : null;
  const availModal      = window.bootstrap && availModalEl ? new window.bootstrap.Modal(availModalEl) : null;
  const selRange        = document.getElementById('selRange');
  const selRangeAvail   = document.getElementById('selRangeAvail');

  // Weekly editor
  const weeklyEditor    = document.getElementById('weeklyEditor');
  const btnSaveWeekly   = document.getElementById('btnSaveWeekly');

  // Action buttons
  const btnMark         = document.getElementById('btnMarkUnavailable');
  const btnClear        = document.getElementById('btnClearUnavailable');
  const btnSave         = document.getElementById('btnSaveUnavail');
  const btnSaveAvail    = document.getElementById('btnSaveAvail');

  // Shift = availability override
  window.__zvShift = false;
  document.addEventListener('keydown', (e)=>{ if (e.key === 'Shift') window.__zvShift = true; });
  document.addEventListener('keyup',   (e)=>{ if (e.key === 'Shift') window.__zvShift = false; });

  let currentSelection = null;

  const pad = (n) => n.toString().padStart(2, '0');
   const fmt = (date) => calendar.formatDate(date, {
       timeZone: getTz(),
       hour12: false,
       year: 'numeric', month: '2-digit', day: '2-digit',
       hour: '2-digit', minute: '2-digit'
     }).replace(',', ''); // e.g. "2025-11-01 05:00"

  // --- Calendar ---
  const calendar = new Calendar(el, {
    plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    slotDuration: '00:30:00',
    height: 'auto',
    nowIndicator: true,
    selectable: true,
    selectMirror: true,
    expandRows: true,
    firstDay: 1,
    timeZone: getTz(), // ⬅ CHANGE: uses device tz when available
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
    },

    // Only allow whole-day select in month; hour/range in timeGrid
    selectAllow: (sel) => {
      const v = calendar.view.type;
      if (v === 'dayGridMonth') return sel.allDay;     // month view -> allDay drags only
      return true;
    },

    // Events feed (unavailability + overrides)
    eventSources: [{
      url: eventsUrl,
      method: 'GET',
      extraParams: () => ({ tz: getTz() }),
      extraFetchOptions: {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
      },
      failure(e) {
        console.error('Failed to load events', e);
        alert('Could not load events. Check server logs.');
      },
    }],

    // Selecting a range:
    // - Month (allDay selection) -> Unavailability by default (red). Hold Shift => Avail override (green).
    // - Week/Day (timed selection) -> same rule, but hours partial (blue) vs green.
    select(info) {
      currentSelection = info;

      const pretty = `${fmt(info.start)} → ${fmt(info.end)}`;
      if (selRange) selRange.value = pretty;
      if (selRangeAvail) selRangeAvail.value = pretty;

      if (window.__zvShift && availModal) {
        availModal.show();
      } else if (unavailModal) {
        unavailModal.show();
      }
    },

    // Apply class names based on extendedProps.type
    eventClassNames(arg) {
      const t = arg.event.extendedProps?.type;
      return t ? [t] : [];
    },
    eventDidMount(arg) {
      const t = arg.event.extendedProps?.type;
      if (t) arg.el.classList.add(t);
    },
  });

  calendar.render();
  async function applyTimezone(newTz) {
    // 1) Persist to server/session so backend + other pages are consistent
    if (lockUrl) {
      try {
        await fetch(lockUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
          },
          body: JSON.stringify({ timezone: newTz })
        }).then(r => r.ok ? r.json() : null);
      } catch (e) {
        console.warn('Failed to lock timezone on server:', e);
      }
    }
  
    // 2) Update badge + dataset
    const badge = document.getElementById('tzBadge');
    if (badge) {
      const icon = badge.querySelector('i')?.outerHTML || '';
      badge.innerHTML = icon + newTz;
    }
    el.dataset.defaultTz = newTz;
  
    // 3) Update FullCalendar runtime tz + refresh events
    calendar.setOption('timeZone', newTz);
    calendar.refetchEvents();
  }
  
  // ---- watch for tz/offset changes ----
 
  

  // Timezone change
  
   function stripOffset(iso) {
       // remove trailing Z, +HH:MM or +HHMM
      return (iso || '').replace(/(Z|[+\-]\d{2}:\d{2}|[+\-]\d{4})$/, '');
     }
  // ---------------- Weekly schedule editor ----------------
  function rangeRowHTML(start='08:00', end='17:00') {
    return `
      <div class="d-flex align-items-center gap-2 mb-2 range-row">
        <input type="time" class="form-control form-control-sm w-auto start" value="${start}">
        <span class="text-muted">–</span>
        <input type="time" class="form-control form-control-sm w-auto end" value="${end}">
        <button type="button" class="btn btn-sm btn-outline-danger btnDelRange"><i class="bi bi-x"></i></button>
      </div>`;
  }

  async function loadWeekly() {
    if (!weeklyEditor || !scheduleGetUrl) return;
    try {
      const res = await fetch(scheduleGetUrl, {
        credentials: 'same-origin',
        headers: { 'Accept':'application/json','X-Requested-With':'XMLHttpRequest' }
      });
      const j = await res.json();

      // Clear UI
      weeklyEditor.querySelectorAll('.ranges').forEach(c => c.innerHTML = '');

      // Populate rows from raw data
      (j.raw || []).forEach(row => {
        const cont = weeklyEditor.querySelector(`.ranges[data-weekday="${row.weekday}"]`);
        if (cont) cont.insertAdjacentHTML('beforeend', rangeRowHTML(row.start_time.slice(0,5), row.end_time.slice(0,5)));
      });

      // Ensure at least one row per day
      weeklyEditor.querySelectorAll('.ranges').forEach(cont => {
        if (!cont.children.length) cont.insertAdjacentHTML('beforeend', rangeRowHTML());
      });

      // Set businessHours on calendar (subtle green hint if you style it)
      calendar.setOption('businessHours', j.businessHours || []);
      calendar.render();
    } catch (e) {
      console.error('Failed to load weekly schedule', e);
    }
  }

  weeklyEditor?.addEventListener('click', (e) => {
    const addBtn = e.target.closest('.btnAddRange');
    if (addBtn) {
      const weekday = addBtn.dataset.weekday;
      const cont = weeklyEditor.querySelector(`.ranges[data-weekday="${weekday}"]`);
      cont?.insertAdjacentHTML('beforeend', rangeRowHTML());
      return;
    }
    const delBtn = e.target.closest('.btnDelRange');
    if (delBtn) {
      const row = delBtn.closest('.range-row');
      const cont = row?.parentElement;
      if (cont && cont.children.length > 1) {
        row.remove();
      } else if (row) {
        row.querySelector('.start').value = '08:00';
        row.querySelector('.end').value   = '17:00';
      }
    }
  });

  btnSaveWeekly?.addEventListener('click', async () => {
    if (!weeklyEditor || !scheduleSaveUrl) return;

    const hours = [];
    weeklyEditor.querySelectorAll('.ranges').forEach(cont => {
      const weekday = parseInt(cont.dataset.weekday,10);
      cont.querySelectorAll('.range-row').forEach(r => {
        const start = r.querySelector('.start').value;
        const end   = r.querySelector('.end').value;
        if (start && end) hours.push({ weekday, start_time: start, end_time: end });
      });
    });

    try {
      const res = await fetch(scheduleSaveUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken(),
          'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify({ hours })
      });

      const text = await res.text();
      if (!res.ok) {
        console.error('Schedule save failed', res.status, text);
        alert('Failed to save schedule.');
        return;
      }
      alert('Schedule saved.');
      await loadWeekly(); // refresh businessHours
    } catch (e) {
      console.error(e);
      alert('Network error while saving schedule.');
    }
  });

  // Initial weekly load
  await loadWeekly();

  // ---------------- Unavailability (red full-day / blue hours) ----------------
  btnMark?.addEventListener('click', () => {
    if (!currentSelection) {
      alert('Select a range on the calendar (drag) first.');
      return;
    }
    unavailModal?.show();
  });

  btnSave?.addEventListener('click', async () => {
    if (!currentSelection) { alert('Select a time range first.'); return; }

    const form = document.getElementById('unavailForm');
    const data = new FormData(form); // includes @csrf if present
    
     data.append('start', stripOffset(currentSelection.startStr)); // naive (coach-wall time)
data.append('end',   stripOffset(currentSelection.endStr));
  data.append('tz',    getTz());

    try {
      const res = await fetch(storeUrl, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken(),
          'X-XSRF-TOKEN': xsrfToken(),
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
        credentials: 'same-origin',
        body: data,
      });

      const text = await res.text();
      if (!res.ok) {
        console.error('Save failed', res.status, text);
        try { alert(JSON.parse(text).message || 'Failed to save.'); }
        catch { alert('Failed to save.'); }
        return;
      }

      window.bootstrap?.Modal.getInstance(unavailModalEl)?.hide();
      form.reset?.();
      calendar.unselect();
      currentSelection = null;
      calendar.refetchEvents();
    } catch (err) {
      console.error(err);
      alert('Network error while saving.');
    }
  });

  // Clear within selected range
  btnClear?.addEventListener('click', async () => {
    if (!currentSelection) { alert('Select a range on the calendar first.'); return; }
    if (!confirm('Clear all unavailability within the selected range?')) return;

    const payload = {
           start: stripOffset(currentSelection.startStr),
          end:   stripOffset(currentSelection.endStr),
          tz:    getTz()
         };

    try {
      const res = await fetch(clearUrl, {
        method: 'DELETE', // ⬅ prefer DELETE; matches typical route::delete
        headers: {
          'X-CSRF-TOKEN': csrfToken(),
          'X-XSRF-TOKEN': xsrfToken(),
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
      });

      const text = await res.text();
      if (!res.ok) {
        console.error('Clear failed', res.status, text);
        try { alert(JSON.parse(text).message || 'Failed to clear.'); }
        catch { alert('Failed to clear.'); }
        return;
      }

      calendar.unselect();
      currentSelection = null;
      calendar.refetchEvents();
    } catch (err) {
      console.error(err);
      alert('Network error while clearing.');
    }
  });

  // ---------------- Availability override (green) ----------------
  btnSaveAvail?.addEventListener('click', async () => {
    if (!currentSelection) { alert('Select a time/day first.'); return; }
  
    const form   = document.getElementById('availForm');
    const reason = form?.querySelector('[name="reason"]')?.value || '';
    const tz     = getTz();
  
   
let startISO = stripOffset(currentSelection.startStr);
 let endISO   = stripOffset(currentSelection.endStr);

// (Month allDay path already builds naive "YYYY-MM-DDTHH:mm:00", keep as is)

  
    // If Month view all-day selection, compose hours from inputs
    if (currentSelection.allDay) {
      const startTime = form?.querySelector('[name="start_time"]')?.value || '09:00';
      const endTime   = form?.querySelector('[name="end_time"]')?.value   || '17:00';
  
      // Disallow multi-day for a single override from Month view (simplest UX)
      const startDate = new Date(currentSelection.start);
      const endDate   = new Date(currentSelection.end);
      const days = Math.round((endDate - startDate) / 86400000);
      if (days !== 1) {
        alert('Select a single day for an availability override in Month view.');
        return;
      }
  
      const dateStr = currentSelection.startStr; // YYYY-MM-DD
      startISO = `${dateStr}T${startTime}:00`;
      endISO   = `${dateStr}T${endTime}:00`;
  
      if (endISO <= startISO) {
        alert('End time must be after start time.');
        return;
      }
    }
  
    try {
      const res = await fetch(availOverrideUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken(),
          'X-XSRF-TOKEN': xsrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify({ start: startISO, end: endISO, tz, reason }),
      });
  
      const text = await res.text();
      if (!res.ok) {
        console.error('Avail override failed', res.status, text);
        try { alert(JSON.parse(text).message || 'Failed to save.'); }
        catch { alert('Failed to save.'); }
        return;
      }
  
      window.bootstrap?.Modal.getInstance(availModalEl)?.hide();
      form?.reset?.();
      calendar.unselect();
      currentSelection = null;
      calendar.refetchEvents();
    } catch (e) {
      console.error(e);
      alert('Network error while saving.');
    }
  });
  

  // Accessibility: avoid aria-hidden/focus clash
  unavailModalEl?.addEventListener('hidden.bs.modal', () => { document.activeElement?.blur(); });
  availModalEl?.addEventListener('hidden.bs.modal',   () => { document.activeElement?.blur(); });
});






