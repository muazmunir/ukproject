
// Served as static ES module (see resources/views/partials/esm-importmap.blade.php). No Vite/Node.
import { Calendar } from '@fullcalendar/core';
 import dayGridPlugin from '@fullcalendar/daygrid';
 import timeGridPlugin from '@fullcalendar/timegrid';
 import interactionPlugin from '@fullcalendar/interaction';
 import luxon2Plugin from '@fullcalendar/luxon2';
 
 (function () {
   const root = document.getElementById('coachCalendar');
   if (!root) return;

   // ---- Endpoints from Blade ----
   const EVENTS_URL   = root.dataset.eventsUrl;
   const LOCK_URL     = root.dataset.lockUrl;
   const STORE_URL    = root.dataset.storeUrl;
   const CLEAR_URL    = root.dataset.clearUrl;
   const SCHED_GET    = root.dataset.scheduleGetUrl;
   const SCHED_SAVE   = root.dataset.scheduleSaveUrl;
   const OVERRIDE_URL = root.dataset.availOverrideUrl;
   const DEFAULT_TZ   = root.dataset.defaultTz || 'UTC';
   const PREVIEW_URL  = root.dataset.availOverridePreviewUrl;
  // *** Coach timezone from Blade (authoritative) ***
  const coachTz = root.dataset.coachTz || DEFAULT_TZ;


  // ---- Detect OS timezone once (for lock only) ----
  const osTz = Intl.DateTimeFormat().resolvedOptions().timeZone || DEFAULT_TZ;

   // Lock to server (non-blocking)
   try {
     fetch(LOCK_URL, {
       method: 'POST',
       headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
       body: JSON.stringify({ tz: osTz })
     });
   } catch(e) { /* ignore */ }

   // ---- Modals / forms ----
   const unavailModal = new bootstrap.Modal(document.getElementById('unavailModal'));
   const availModal   = new bootstrap.Modal(document.getElementById('availModal'));


   const selRangeEl   = document.getElementById('selRange');
   const selRangeAvail= document.getElementById('selRangeAvail');
   const btnSaveUn    = document.getElementById('btnSaveUnavail');
   const btnSaveOv    = document.getElementById('btnSaveAvail');

   const unavailForm  = document.getElementById('unavailForm');
   const availForm    = document.getElementById('availForm');

   let pending = { start: null, end: null, isOverride: false };

   


   let mode = 'unavail'; // 'unavail' | 'avail' | 'clear'

const modeUnavailBtn = document.getElementById('modeUnavail');
const modeAvailBtn   = document.getElementById('modeAvail');
const modeClearBtn   = document.getElementById('modeClear');

// Confirm Clear modal wiring
const confirmClearModal = new bootstrap.Modal(document.getElementById('confirmClearModal'));
const confirmClearMsg   = document.getElementById('confirmClearMsg');
const btnConfirmClear   = document.getElementById('btnConfirmClear');

// Reusable confirm dialog that resolves true/false
function confirmClear(message){
  return new Promise(resolve => {
    const el = document.getElementById('confirmClearModal');
    if (confirmClearMsg) confirmClearMsg.textContent = message || 'Clear selected range?';

    let decided = false;

    const onApprove = () => {
      if (decided) return;
      decided = true;
      // stop listening further
      el.removeEventListener('hidden.bs.modal', onHide);
      btnConfirmClear.removeEventListener('click', onApprove);
      // close the modal NOW
      confirmClearModal.hide();
      // resolve "approved"
      resolve(true);
    };

    const onHide = () => {
      if (decided) return; // already approved and hidden programmatically
      decided = true;
      btnConfirmClear.removeEventListener('click', onApprove);
      resolve(false);
    };

    btnConfirmClear.addEventListener('click', onApprove);
    el.addEventListener('hidden.bs.modal', onHide);

    confirmClearModal.show();
  });
}


function setMode(next){
  mode = next;
  [modeUnavailBtn, modeAvailBtn, modeClearBtn].forEach(b=>b?.classList.remove('active'));
  if (next==='unavail') modeUnavailBtn?.classList.add('active');
  if (next==='avail')   modeAvailBtn?.classList.add('active');
  if (next==='clear')   modeClearBtn?.classList.add('active');
}

modeUnavailBtn?.addEventListener('click', ()=>setMode('unavail'));
modeAvailBtn?.addEventListener('click',   ()=>setMode('avail'));
modeClearBtn?.addEventListener('click',   ()=>setMode('clear'));


   function hhmmFromIso(iso) {
    // arg.startStr/arg.endStr come in the COACH tz (calendar timeZone = coachTz)
    // If month/all-day, there may be no time part → return null
    const m = String(iso).match(/T(\d{2}):(\d{2})/);
    return m ? `${m[1]}:${m[2]}` : null;
  }

   // --- OVERRIDE modal live preview ---
 function updateOverridePreview() {
   const stInput = availForm.querySelector('input[name="start_time"]');
   const etInput = availForm.querySelector('input[name="end_time"]');
   if (!stInput || !etInput) return;

   // use the selected day from pending (always coach tz)
   const dateStr = (pending.start || '').slice(0, 10); // "YYYY-MM-DD"
   if (!dateStr) return;

   const st = (stInput.value || '09:00') + ':00';
   const et = (etInput.value || '17:00') + ':00';

   // compose coach-local ISO (no Z). Backend parses in coach tz.
   const startISO = `${dateStr}T${st}`;
  const endISO   = `${dateStr}T${et}`;

   // show it above and keep pending in sync so Save uses these
   selRangeAvail.value = `${startISO} → ${endISO}`;
   pending.start = startISO;
   pending.end   = endISO;
 }

   // ---- Calendar ----
   const calendar = new Calendar(root, {
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin, luxon2Plugin],

    timeZone: coachTz,            // << run calendar in COACH tz
     initialView: 'timeGridWeek',
     selectable: true,
     selectMirror: true,
     height: 'auto',
     handleWindowResize: true,
expandRows: true,
stickyHeaderDates: true,
nowIndicator: true,
     headerToolbar: {
       left: 'prev,next today',
       center: 'title',
       right: 'dayGridMonth,timeGridWeek,timeGridDay'
     },
     
   selectAllow: (selectInfo) => {
  if (selectionHitsBusy(selectInfo.start, selectInfo.end)) {
    if (window.zToast) window.zToast("This Time Is Already Booked Or Blocked.", "error");
    return false;
  }
  return true;
},


     events: (info, success, failure) => {
       const url = new URL(EVENTS_URL, window.location.origin);
       url.searchParams.set('start', info.startStr);
       url.searchParams.set('end',   info.endStr);

      url.searchParams.set('tz',    coachTz); // clarity
       fetch(url, { headers: {'X-Requested-With':'XMLHttpRequest'} })
         .then(r => r.json()).then(success).catch(failure);
     },

      // helper: pull HH:mm from an ISO like 2025-11-08T13:30:0004:00
 
      select: (arg) => {
        // selection context (already in coach tz)
        pending.start  = arg.startStr;
        pending.end    = arg.endStr;
        pending.allDay = !!arg.allDay;
      
        // Desktop shortcut: Shift = availability override
        const shift = !!(arg.jsEvent && arg.jsEvent.shiftKey);
        const effectiveMode = shift ? 'avail' : mode;
      
        // Label for modal preview
        const label = `${arg.startStr} → ${arg.endStr}`;
      
        if (effectiveMode === 'unavail') {
          // Unavailability flow (use your existing modal)
          selRangeEl.value = label;
          unavailModal.show();
          return;
        }
      
        if (effectiveMode === 'avail') {
          // Availability override flow (use your existing modal, prefill HH:mm)
          const st = hhmmFromIso(pending.start);
          const et = hhmmFromIso(pending.end);
          const stInput = availForm.querySelector('input[name="start_time"]');
          const etInput = availForm.querySelector('input[name="end_time"]');
      
          if (stInput && etInput) {
            if (!pending.allDay && st && et) { stInput.value = st; etInput.value = et; }
            else { stInput.value ||= '09:00'; etInput.value ||= '17:00'; }
          }
          updateOverridePreview();
          availModal.show();
          return;
        }
      
        if (effectiveMode === 'clear') {
          const isMonth = calendar.view.type === 'dayGridMonth';
          const payload = (pending.allDay || isMonth)
            ? { start: pending.start.slice(0,10), end: pending.end.slice(0,10), allDay: true }
            : { start: pending.start, end: pending.end };
        
          const label = (payload.allDay)
            ? `${payload.start} → ${payload.end}`
            : `${pending.start} → ${pending.end}`;
        
          // Ask first
          confirmClear(`Are you sure you want to remove unavailability/overrides within: ${label} ?`)
            .then(async ok => {
              if (!ok) return;
        
              // optional: optimistic hint stripe
              const temp = calendar.addEvent({
                start: pending.start, end: pending.end,
                display: 'background', classNames: ['nonwork-grey','fc-bg-event']
              });
        
              const res = await fetch(CLEAR_URL, {
                method: 'DELETE',
                headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
                body: JSON.stringify(payload)
              });
        
              temp.remove();
              if (res.ok) calendar.refetchEvents();
            });
        
          return; // stop here
        }
        
      },
      

    // Pretty background
  eventDidMount(info){
  if (info.event.classNames.includes('res-blocked-slot')) {
    // harness is parent element
    info.el.closest('.fc-timegrid-event-harness')?.classList.add('blocked-harness');
  }
  if (info.event.display === 'background') {
    info.el.classList.add('fc-bg-event');
  }
}
  });


 function isBusyEvent(ev){
  const cn = ev.classNames || [];
  return cn.includes('res-booked-slot') || cn.includes('res-blocked-slot');
}

// checks if selection intersects any busy event (booked OR blocked)
function selectionHitsBusy(start, end){
  const startMs = start.getTime();
  const endMs   = end.getTime();

  return calendar.getEvents().some(ev => {
    if (!isBusyEvent(ev)) return false;

    const a = ev.start?.getTime() ?? 0;
    const b = ev.end?.getTime() ?? a;

    // overlap test: [start,end) intersects [a,b)
    return startMs < b && endMs > a;
  });
}


function applyResponsiveCalendar() {
  const width = window.innerWidth;
  const isMobile = width <= 576;
  const isTiny   = width <= 390;

  calendar.setOption('headerToolbar', isMobile
    ? { left: 'prev,next', center: 'title', right: 'today' }
    : { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' }
  );

  // default to Day on mobile
  if (isMobile && calendar.view.type === 'timeGridWeek') {
    calendar.changeView('timeGridDay');
  }

  calendar.setOption('allDaySlot', !isMobile);

  calendar.setOption('dayHeaderFormat', isTiny
    ? { weekday: 'short' }
    : isMobile
      ? { weekday: 'short', day: 'numeric' }
      : { weekday: 'short', month: 'numeric', day: 'numeric' }
  );

  calendar.setOption('slotMinTime', isMobile ? '06:00:00' : '00:00:00');
  calendar.setOption('slotMaxTime', isMobile ? '22:00:00' : '24:00:00');
}

// Run on view/date changes + window resize
calendar.on('datesSet', applyResponsiveCalendar);
window.addEventListener('resize', applyResponsiveCalendar);

// ✅ Render ONCE
calendar.render();

// ✅ Apply once after render
applyResponsiveCalendar();

  // ---- Jump to month/year ----
const jumpBtn        = document.getElementById('btnJump');
const jumpModalEl    = document.getElementById('jumpModal');
const jumpModal      = new bootstrap.Modal(jumpModalEl);
const jumpMonthInput = document.getElementById('jumpMonth');        // type="month"
const jumpMonthSel   = document.getElementById('jumpMonthSelect');  // fallback month select
const jumpYearSel    = document.getElementById('jumpYearSelect');
const jumpGoBtn      = document.getElementById('btnJumpGo');

// Populate years (currentYear-5 … currentYear+7, tweak as you like)
(function populateYears(){
  const now = new Date();
  const yr = now.getFullYear();
  const start = yr - 5, end = yr + 7;
  let html = '';
  for (let y = start; y <= end; y++) html += `<option value="${y}">${y}</option>`;
  jumpYearSel.innerHTML = html;
})();

jumpBtn?.addEventListener('click', () => {
  // preset controls to currently visible date
  const d = calendar.getDate(); // current calendar date
  const y = d.getFullYear();
  const m = String(d.getMonth()+1).padStart(2,'0');
  if (jumpMonthInput) jumpMonthInput.value = `${y}-${m}`;
  if (jumpMonthSel) jumpMonthSel.value = String(d.getMonth());
  if (jumpYearSel)  jumpYearSel.value  = String(y);
  jumpModal.show();
});

function gotoYMD(year, monthIndex){ // monthIndex: 0..11
  const y = Number(year);
  const m = Number(monthIndex) + 1;
  const ymd = `${String(y)}-${String(m).padStart(2,'0')}-01`;
  calendar.gotoDate(ymd);
  // calendar will fetch events automatically; if your source doesn't, uncomment:
  // calendar.refetchEvents();
}

jumpGoBtn?.addEventListener('click', () => {
  // Prefer the month input if filled, else use selects
  const mVal = jumpMonthInput?.value; // "YYYY-MM"
  if (mVal && /^\d{4}-\d{2}$/.test(mVal)) {
    const [yy, mm] = mVal.split('-');
    gotoYMD(yy, Number(mm)-1);
    jumpModal.hide();
    return;
  }
  // fallback: selects
  gotoYMD(jumpYearSel.value, Number(jumpMonthSel.value));
  jumpModal.hide();
});

// Bonus: keyboard shortcut (Ctrl/Cmd+G opens Jump)
document.addEventListener('keydown', (e) => {
  const isMod = e.ctrlKey || e.metaKey;
  if (isMod && (e.key === 'g' || e.key === 'G')) {
    e.preventDefault();
    jumpBtn?.click();
  }
});


  // ----- Long-press to toggle full-day unavailability on Month view -----
let pressTimer = null;
root.addEventListener('touchstart', (ev) => {
  if (calendar.view.type !== 'dayGridMonth') return;
  const target = ev.target.closest('.fc-daygrid-day');
  if (!target) return;

  const dateStr = target.getAttribute('data-date'); // YYYY-MM-DD
  if (!dateStr) return;

  pressTimer = setTimeout(async () => {
    // Decide action: if the day already has red unavail, clear it; else add it.
    const hasRed = !![...target.querySelectorAll('.unavail-fullday')].length;

    if (!hasRed) {
      // Add full-day unavailability
      const payload = { allDay:true, start: dateStr, end: dateStr };
      // optimistic paint
      const temp = calendar.addEvent({
        start: dateStr, end: dateStr, allDay:true,
        display:'background', classNames:['unavail-fullday','fc-bg-event']
      });
      const res = await fetch(STORE_URL, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
        body: JSON.stringify(payload)
      });
      temp.remove();
      if (res.ok) calendar.refetchEvents();
    } else {
      // Clear that day
      const res = await fetch(CLEAR_URL, {
        method: 'DELETE',
        headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
        body: JSON.stringify({ allDay:true, start: dateStr, end: dateStr })
      });
      if (res.ok) calendar.refetchEvents();
    }
  }, 700); // ~0.7s press
}, { passive:true });

['touchend','touchcancel'].forEach(evt=>{
  root.addEventListener(evt, ()=>{ if (pressTimer){ clearTimeout(pressTimer); pressTimer=null; } }, { passive:true });
});

   // after calendar.render(), once you have availForm
 const stInput = availForm.querySelector('input[name="start_time"]');
 const etInput = availForm.querySelector('input[name="end_time"]');
 stInput?.addEventListener('input',  updateOverridePreview);
 etInput?.addEventListener('input',  updateOverridePreview);
 stInput?.addEventListener('change', updateOverridePreview);
 etInput?.addEventListener('change', updateOverridePreview);


// Build FullCalendar businessHours from rows [{weekday, start_time, end_time}, ...]
// 0=Sun..6=Sat; times are HH:mm in COACH tz (calendar.timeZone = coachTz)
function businessFromRows(rows){
  // (optional) sanitize + sort by weekday/start
  const clean = (rows||[])
    .filter(r => r && r.start_time && r.end_time && r.start_time !== r.end_time)
    .map(r => ({
      weekday: Number(r.weekday),
      start: String(r.start_time).slice(0,5),
      end:   String(r.end_time).slice(0,5)
    }))
    .sort((a,b)=> a.weekday - b.weekday || a.start.localeCompare(b.start));

  // FullCalendar accepts multiple entries per day (for split shifts).
  // Map each row to a businessHours segment.
  return clean.map(r => ({
    daysOfWeek: [r.weekday],       // 0..6
    startTime:  r.start,           // 'HH:mm'
    endTime:    r.end
  }));
}


  // ---- Weekly editor ----
  const weeklyEditor = document.getElementById('weeklyEditor');
  const btnSaveWeekly = document.getElementById('btnSaveWeekly');

  function rangeRow(weekday, start='08:00', end='17:00') {
    const wrap = document.createElement('div');
    wrap.className = 'range-row d-flex text-capitalize align-items-center gap-2 mb-2 flex-wrap';
    wrap.innerHTML = `
      <input type="time" class="form-control text-capitalize form-control-sm start" value="${start}">
      <span>–</span>
      <input type="time" class="form-control text-capitalize form-control-sm end" value="${end}">
      <button type="button" class="btn btn-sm text-capitalize btn-outline-danger ms-1 btnDel"><i class="bi bi-x"></i></button>
    `;
    wrap.querySelector('.btnDel').addEventListener('click', () => wrap.remove());
    wrap.dataset.weekday = String(weekday);
    return wrap;
  }

  function hydrateWeekly(rows){
    // rows: [{weekday, start_time, end_time}, ...]
    weeklyEditor.querySelectorAll('.ranges').forEach(r => r.innerHTML = '');
    rows.forEach(r => {
      const box = weeklyEditor.querySelector(`.ranges[data-weekday="${r.weekday}"]`);
      if (box) box.appendChild(rangeRow(r.weekday, r.start_time.substring(0,5), r.end_time.substring(0,5)));
    });
    // ensure at least one empty row per day
    weeklyEditor.querySelectorAll('.ranges').forEach(box => {
      if (!box.children.length) box.appendChild(rangeRow(box.dataset.weekday));
    });
  
    // ⬇️ Set businessHours from THIS coach's rows (this drives the grey)
    calendar.setOption('businessHours', businessFromRows(rows));
  }
  

  // Add range buttons
  weeklyEditor.querySelectorAll('.btnAddRange').forEach(btn => {
    btn.addEventListener('click', () => {
      const weekday = btn.dataset.weekday;
      const box = weeklyEditor.querySelector(`.ranges[data-weekday="${weekday}"]`);
      box.appendChild(rangeRow(weekday));
    });
  });

  // Load existing schedule
  fetch(SCHED_GET, { headers: {'X-Requested-With':'XMLHttpRequest'} })
    .then(r => r.json())
    .then(hydrateWeekly)
    .catch(() => hydrateWeekly([]));

  // Save weekly
  // Save weekly
btnSaveWeekly.addEventListener('click', async () => {
  const items = [];

  weeklyEditor.querySelectorAll('.ranges').forEach(box => {
    [...box.children].forEach(row => {
      const startInput = row.querySelector('.start');
      const endInput   = row.querySelector('.end');
      if (!startInput || !endInput) return;

      const start = startInput.value;
      const end   = endInput.value;

      if (start && end && start !== end) {
        items.push({
          weekday: Number(box.dataset.weekday),
          start_time: start,
          end_time: end
        });
      }
    });
  });

  // If nothing to save, show error toast
  if (!items.length) {
    if (window.zToast) {
      window.zToast("Please add at least one time range before saving.", 'error');
    }
    return;
  }

  // Instant visual update (business hours shading)
  calendar.setOption('businessHours', businessFromRows(items));

  try {
    const res = await fetch(SCHED_SAVE, {
      method: 'POST',
      headers: {
        'Content-Type':'application/json',
        'X-Requested-With':'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify({ items })
    });

    if (res.ok) {
      if (window.zToast) {
        window.zToast("Schedule Saved successfully.", 'ok');
      }
      calendar.refetchEvents();
    } else {
      if (window.zToast) {
        window.zToast("Something went wrong while saving. Please try again.", 'error');
      }
    }
  } catch (e) {
    console.error(e);
    if (window.zToast) {
      window.zToast("Server error. Please try again.", 'error');
    }
  }
});

  

  // ---- Save Unavailability ----
  btnSaveUn.addEventListener('click', async () => {
    const payload = Object.fromEntries(new FormData(unavailForm));
  
    // default: use the coach-tz RFC3339 strings
    let start = pending.start;
    let end   = pending.end;
  
    // For all-day/month selections, send pure dates
    if (pending.allDay || calendar.view.type === 'dayGridMonth') {
      const startDate = pending.start.slice(0, 10); // YYYY-MM-DD
      const endDate   = pending.end.slice(0, 10);   // YYYY-MM-DD (exclusive)
      payload.allDay = true;
      payload.start  = startDate;
      payload.end    = endDate;
    } else {
      payload.start = start;
      payload.end   = end;
    }

     // optimistic paint in coach tz (exactly what the user picked)
 const temp = calendar.addEvent({
   start: pending.start,
   end:   pending.end,
   display: 'background',
   classNames: ['unavail-red','fc-bg-event'],
 });
  
    const res = await fetch(STORE_URL, {
      method: 'POST',
      headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
      body: JSON.stringify(payload)
    });
    if (res.ok) { unavailModal.hide(); await calendar.refetchEvents();
    }
    temp.remove();
  });

  // ---- Clear Unavailability ----
  const btnClear = document.getElementById('btnClearUnavailable');
  btnClear?.addEventListener('click', async () => {
    const view = calendar.view;

    // ok to send UTC for a window
    const payload = { start: view.currentStart.toISOString(), end: view.currentEnd.toISOString() };
    const res = await fetch(CLEAR_URL, {
      method: 'DELETE',
      headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
      body: JSON.stringify(payload)
    });
    if (res.ok) calendar.refetchEvents();
  });

  // ---- Save Availability Override ----
  btnSaveOv.addEventListener('click', async () => {
    const fd = new FormData(availForm);
    const startTime = fd.get('start_time'); // H:i
    const endTime   = fd.get('end_time');

    let startISO = pending.start;
      let endISO   = pending.end;
    // If selection was dayGrid (all-day), compose coach-local ISO strings
    if (calendar.view.type === 'dayGridMonth') {

      // Take YYYY-MM-DD from pending.start (already in coach tz),
      // then build naive local ISO (no Z). Backend will parse in coach tz.
      const dateStr = pending.start.slice(0, 10);           // "YYYY-MM-DD"
      const st = (startTime || '09:00') + ':00';
      const et = (endTime   || '17:00') + ':00';
      startISO = `${dateStr}T${st}`; // e.g., "2025-11-11T09:00:00"
      endISO   = `${dateStr}T${et}`; //        "2025-11-11T11:00:00"
    }

    const payload = {
      start: startISO,
      end:   endISO,
      reason: fd.get('reason') || null,
    };

     // optimistic paint (green)
 const temp = calendar.addEvent({
   start: startISO,
   end:   endISO,
   display: 'background',
   classNames: ['avail-override','fc-bg-event'],
 });


    const res = await fetch(OVERRIDE_URL, {
      method: 'POST',
      headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
      body: JSON.stringify(payload)
    });
    if (res.ok) {
      availModal.hide();
          await calendar.refetchEvents();
    }
   temp.remove();
  });
})();