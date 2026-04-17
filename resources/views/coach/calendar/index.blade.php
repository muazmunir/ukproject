@extends('layouts.role-dashboard')

@section('role-content')
<div class="container-narrow">
 <div class="d-flex flex-wrap align-items-start align-items-md-center justify-content-between gap-2 mb-3">
    <div>
      <h4 class="mb-0"><i class="bi bi-calendar2-week me-2"></i>{{ __('Availability Calendar') }}</h4>
      <small class="text-muted text-capitalize">
        {{ __('Set your weekly availability (forever), mark vacations or time off, and optionally add availability inside away days.') }}
      </small>
    </div>
    <div class="d-flex gap-2 align-items-center">
      {{-- 🔒 Read-only timezone badge (replaces <select id="tzSelect">) --}}
        <span id="tzBadge" class="badge bg-light text-dark border">
          <i class="bi bi-geo-alt me-1"></i>{{ $coachTz ?? config('app.timezone') }}
        </span>
        

     
    </div>
  </div>

  {{-- Quick usage tips (coach friendly) --}}
  <div class="alert alert-light border d-flex align-items-center gap-2 py-2 px-3 mb-3">
    <i class="bi bi-info-circle"></i>
    <div class="small text-capitalize">
      <strong>{{ __('Tip') }}:</strong>
      {{ __('Drag on the Month view to mark full-day unavailability (red).') }}
      {{ __('Switch to Week/Day to select specific hours (blue). Hold Shift while dragging to add availability overrides (green).') }}
    </div>
  </div>

  {{-- Weekly Schedule Editor --}}
  <div class="card shadow-sm border-0 rounded-4 mb-3">
    <div class="card-body">
      <div class="d-flex align-items-center justify-content-between">
        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>{{ __('Weekly Availability') }}</h5>
        <button id="btnSaveWeekly" type="button" class="btn btn-sm btn-dark">
          <i class="bi bi-save me-1"></i>{{ __('Save Schedule') }}
        </button>
      </div>
      <small class="text-muted text-capitalize">
        {{ __('Add one or more time ranges per day. These hours repeat every week until you change them.') }}
      </small>

      <div id="weeklyEditor" class="mt-3">
        @php $days=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday']; @endphp
        @foreach($days as $i=>$day)
          <div class="row g-2 align-items-center py-2 border-bottom">
            <div class="col-12 col-md-2 fw-medium">{{ __($day) }}</div>
            <div class="col">
              <div class="ranges" data-weekday="{{ $loop->index }}">
                {{-- JS populates range rows; at least one default row if empty --}}
              </div>
            </div>
            {{-- <div class="col-auto">
              <button type="button" class="btn btn-sm btn-outline-secondary btnAddRange" data-weekday="{{ $loop->index }}">
                <i class="bi bi-plus-lg"></i> {{ __('Add range') }}
              </button>
            </div> --}}
          </div>
        @endforeach
      </div>
      
    </div>
  </div>

  {{-- Legend --}}
  <div class="zv-legend mb-2 d-flex align-items-center justify-content-center  flex-wrap gap-5">
    <span class="d-inline-flex align-items-center gap-2">
      <span class="legend-swatch sw-available"></span> {{ __('Available Slots') }}
    </span>
    <span class="d-inline-flex align-items-center gap-2">
      <span class="legend-swatch sw-unavail-fullday"></span> {{ __('Holiday') }}
    </span>
    <span class="d-inline-flex align-items-center gap-2">
      <span class="legend-swatch sw-booked"></span> {{ __('Booked') }}
    </span>
    <span class="d-inline-flex align-items-center gap-2">
      <span class="legend-swatch sw-unavailable"></span> {{ __('Unavailable') }}
    </span>
    <span class="d-inline-flex align-items-center gap-2">
  <span class="legend-swatch sw-blocked"></span> {{ __('Locked') }}
</span>
  </div>


  {{-- Calendar (data-* URLs consumed by JS) --}}


  <div class="d-flex flex-wrap justify-content-start justify-content-md-end gap-2 mb-2" role="group" aria-label="Calendar modes">
    <button id="modeUnavail" type="button" class="btn btn-sm btn-outline-danger active rounded-3">
      <i class="bi bi-slash-circle me-1 fw-bold"> {{ __(' Unavailable') }}</i>
    </button>
    <button id="modeAvail" type="button" class="btn btn-sm btn-outline-primary rounded-3">
      <i class="bi bi-check2-circle me-1 fw-bold"> {{ __(' Availability') }}</i>
    </button>
    <button id="modeClear" type="button" class="btn btn-sm btn-outline-secondary rounded-3">
      <i class="bi bi-eraser me-1 fw-bold">{{ __(' Clear') }}</i>
    </button>
    
  </div>
  
<button id="btnJump" type="button" class="btn btn-sm btn-outline-dark mb-2 w-100 w-md-auto ms-md-auto d-block">
    <i class="bi bi-calendar3 me-1"></i>{{ __('Jump to…') }}
  </button>
  

  <div
    id="coachCalendar"
    class="card shadow-sm border-0 rounded-4 p-2"
    data-events-url="{{ route('coach.calendar.events') }}"
    data-lock-url="{{ route('lock.timezone') }}"
    data-store-url="{{ route('coach.calendar.unavailable.store') }}"
    data-clear-url="{{ route('coach.calendar.unavailable.clear') }}"
    data-schedule-get-url="{{ route('coach.calendar.schedule.get') }}"
    data-schedule-save-url="{{ route('coach.calendar.schedule.save') }}"
    
    data-avail-override-url="{{ route('coach.calendar.avail_override.store') }}"

    data-default-tz="{{ $coachTz ?? config('app.timezone') }}"
     data-coach-tz="{{ $coachTz ?? config('app.timezone') }}">
   

  </div>
</div>

{{-- Unavailable Modal --}}
<div class="modal fade" id="unavailModal" tabindex="-1" aria-labelledby="unavailModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4">
      <div class="modal-header border-0">
        <h5 id="unavailModalTitle" class="modal-title">
          <i class="bi bi-slash-circle me-2"></i>{{ __('Mark Unavailable') }}
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
      </div>
      <div class="modal-body">
        <form id="unavailForm" class="vstack gap-3">
          @csrf
          <div>
            <label class="form-label">{{ __('Selected range') }}</label>
            <input type="text" id="selRange" class="form-control" disabled>
          </div>
          <div>
            <label class="form-label">{{ __('Reason (optional)') }}</label>
            <input type="text" name="reason" class="form-control" placeholder="{{ __('e.g., Vacation, Personal time') }}">
          </div>
          <div class="row g-2 align-items-center">
            <div class="col-auto">
              <label class="form-label mb-0">{{ __('Repeat') }}</label>
            </div>
            <div class="col">
              <select class="form-select" name="repeat">
                <option value="none">{{ __('Do not repeat') }}</option>
                <option value="daily">{{ __('Daily (next 14 days)') }}</option>
                <option value="weekly">{{ __('Weekly (next 8 weeks)') }}</option>
              </select>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
        <button id="btnSaveUnavail" type="button" class="btn btn-dark">{{ __('Save') }}</button>
      </div>
    </div>
  </div>
</div>

{{-- Availability Override Modal --}}
{{-- Availability Override Modal --}}
<div class="modal fade" id="availModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4">
      <div class="modal-header border-0">
        <h5 class="modal-title">
          <i class="bi bi-check2-circle me-2"></i>{{ __('Add Availability (Override)') }}
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
      </div>
      <div class="modal-body">
        <form id="availForm" class="vstack gap-3">@csrf
          <div>
            <label class="form-label">{{ __('Will be saved as (UTC)') }}</label>
            <input type="text" id="selRangeAvail" class="form-control" disabled>
          </div>

          <div class="row g-2">
            <div class="col">
              <label class="form-label">
                {{ __('Start time (Coach local)') }}
              </label>
              <input type="time" name="start_time" class="form-control" value="14:00">
            </div>
            <div class="col">
              <label class="form-label">
                {{ __('End time (Coach local)') }}
              </label>
              <input type="time" name="end_time" class="form-control" value="17:00">
            </div>
          </div>

          <div>
            <label class="form-label">{{ __('Note (optional)') }}</label>
            <input type="text" name="reason" class="form-control" placeholder="{{ __('e.g., Taking a few clients') }}">
          </div>
          <small class="text-muted">
            {{ __('Tip: If you dragged on Week/Day view, those exact hours (with offset) are used. On Month view, we’ll convert the times you type from coach timezone to UTC.') }}
          </small>
        </form>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
        <button id="btnSaveAvail" class="btn btn-dark">{{ __('Save') }}</button>
      </div>
    </div>
  </div>
</div>

{{-- Confirm Clear Modal --}}
<div class="modal fade" id="confirmClearModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content rounded-4">
      <div class="modal-header border-0">
        <h6 class="modal-title">
          <i class="bi bi-eraser me-1"></i>{{ __('Remove selection?') }}
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
      </div>
      <div class="modal-body py-2">
        <p id="confirmClearMsg" class="mb-0 small text-muted">
          {{ __('Are you sure you want to clear unavailability/overrides for the selected range?') }}
        </p>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
        <button id="btnConfirmClear" type="button" class="btn btn-danger">
          <i class="bi bi-trash me-1"></i>{{ __('Clear') }}
        </button>
      </div>
    </div>
  </div>
</div>


{{-- Jump to Month/Year Modal --}}
{{-- Jump to Month/Year Modal --}}
<div class="modal fade" id="jumpModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content rounded-4">
      <div class="modal-header border-0">
        <h6 class="modal-title">
          <i class="bi bi-calendar3 me-1"></i>{{ __('Jump to month') }}
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
      </div>

      <div class="modal-body">
        <div class="vstack gap-2">
          <label class="form-label mb-1 small">{{ __('Pick month & year') }}</label>
          <div class="d-flex gap-2">
            <select id="jumpMonthSelect" class="form-select">
              <option value="0">Jan</option><option value="1">Feb</option><option value="2">Mar</option>
              <option value="3">Apr</option><option value="4">May</option><option value="5">Jun</option>
              <option value="6">Jul</option><option value="7">Aug</option><option value="8">Sep</option>
              <option value="9">Oct</option><option value="10">Nov</option><option value="11">Dec</option>
            </select>
            <select id="jumpYearSelect" class="form-select"></select>
          </div>
        </div>
      </div>

      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
        <button id="btnJumpGo" type="button" class="btn btn-dark">
          <i class="bi bi-arrow-right-circle me-1"></i>{{ __('Go') }}
        </button>
      </div>
    </div>
  </div>
</div>




@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/coach-availability.css') }}">
<style>
/* ===== Legend ===== */
.zv-legend .legend-swatch{
  width:14px; height:14px; display:inline-block; border-radius:4px;
  border:1px solid rgba(0,0,0,.14);
}
.sw-available{background:#16a34a; border-color:#166534; }      /* Blue */
.sw-unavail-fullday{ background:#ef4444; border-color:#b91c1c; }/* Red */
.sw-booked{ background:#2563eb; border-color:#1e40af; }         /* Green */
.sw-unavailable{background: rgb(65, 65, 66) ;border-color: rgb(44, 44, 44) !important;}
#coachCalendar .fc-bg-event.booked-slot{
  background: rgba(37, 99, 235, 1) !important;
  border-color: #1e40af !important;
}


/* ===== Background event base ===== */
#coachCalendar .fc-bg-event{
  z-index: 1;
  opacity: 1 !important;
  background-clip: padding-box;
}
#coachCalendar .fc-daygrid-day-frame{ position: relative; }

/* ===== NON-WORKING HOURS (GREY) =====
   This uses FullCalendar's "non-business" class.
   Make sure businessHours is set from JS (see Section 3). */
/* Grey outside working hours (driven by businessHours) */
#coachCalendar .fc-non-business{
  background: rgb(65, 65, 66,.8) !important;
}


/* ===== WORKING HOURS (BLUE) =====
   We paint weekly schedule (and overrides) as bg-events with these classes. */
#coachCalendar .fc-bg-event.available-weekly,
#coachCalendar .fc-bg-event.avail-override,
#coachCalendar .fc-event.available-weekly,
#coachCalendar .fc-event.avail-override{
  background:#16a34a; border-color:#166534;
}

/* Optional: also tint built-in business-hours band a touch of blue */
#coachCalendar .fc .fc-timegrid-col .fc-business-hours,
#coachCalendar .fc .fc-daygrid-day .fc-business-hours{
  background: rgba(37, 99, 235, .10) !important;
}

/* ===== UNAVAILABILITY (RED) ===== */
#coachCalendar .fc-bg-event.unavail-fullday,
#coachCalendar .fc-event.unavail-fullday,
#coachCalendar .fc-bg-event.unavail-partial,
#coachCalendar .fc-event.unavail-partial{
  background: #ef4444 !important;
  border-color: #b91c1c !important;
}

/* ===== BOOKINGS (GREEN, foreground events) ===== */
#coachCalendar .fc-event.booked-slot{
  background: #2563eb; /* Blue wash */
  border-color: #1e40af !important;
  color: #ffffff !important;
}

/* General readability for any event that does show text */
#coachCalendar .fc-event,
#coachCalendar .fc-bg-event{ color:#0f172a; }
</style>
@endpush






@endsection