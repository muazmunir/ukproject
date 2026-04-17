{{-- resources/views/reserve/show.blade.php --}}
@extends('layouts.app')
@section('title', __('Confirm & Pay'))

@push('styles')
<style>
  .sticky-summary { position: sticky; top: 84px; }
  .step { border:1px solid #eee; border-radius:14px; padding:14px; }
  .step + .step { margin-top:10px; }
  .step h6 { margin:0 0 .5rem 0; }
  .env-pill { display:inline-block; padding:.45rem .75rem; border:1px solid #e5e7eb; border-radius:999px; cursor:pointer; }
  .env-pill.active { background:#0d6efd; color:#fff; border-color:#0d6efd; }
  .dates-list li { margin:.2rem 0; }
  .price-line { display:flex; justify-content:space-between; margin:.25rem 0; }
  .muted { color:#6b7280; }
</style>
@endpush

@section('content')
<div class="container my-4">
  <div class="row g-4">
    <div class="col-12 col-lg-7">
      {{-- Step 1: Who / When / Where --}}
      <div class="step">
        <h6>1. Review your selection</h6>
        <div class="mt-2">
          <div class="fw-semibold">{{ $service->title }}</div>
          <div class="muted small">
            {{ optional($service->coach)->full_name }} • {{ $service->city_name }}
          </div>
          <div class="mt-2">
            <div class="fw-semibold">{{ $package->name }}</div>
            <ul class="dates-list list-unstyled mt-2">
              @foreach($days as $d)
                <li>
                  <i class="bi bi-calendar-event me-1"></i>
                  <strong>{{ \Carbon\Carbon::parse($d['date'])->toFormattedDateString() }}</strong>
                  <span class="muted">• {{ $d['start'] }}–{{ $d['end'] }} ({{ $client_tz }})</span>
                </li>
              @endforeach
            </ul>
            <div class="muted small">Times shown in your timezone: {{ $client_tz }}</div>
          </div>
        </div>
      </div>

      {{-- Step 2: Choose environment --}}
      <div class="step">
        <h6>2. Choose the coaching environment</h6>
        @php $envs = (array)($service->environments ?? []); @endphp
        @if(empty($envs))
          <div class="muted">This coach hasn’t specified environments.</div>
        @else
          <div class="d-flex flex-wrap gap-2 mt-2" id="envSelect">
            @foreach($envs as $env)
              <span class="env-pill" data-value="{{ $env }}">{{ $env }}</span>
            @endforeach
          </div>
        @endif
        <div class="mt-3">
          <label class="form-label">Disability or medication info (optional)</label>
          <textarea form="reserveForm" name="notes" class="form-control" rows="3"
                    placeholder="Share anything that helps your coach prepare…"></textarea>
        </div>
      </div>

      {{-- Step 3: Payment (stub – you can integrate provider later) --}}
      {{-- Step 3: Payment --}}
<div class="step">
  <h6>3. Add a payment method</h6>
  <div id="payment-element"></div>
  <button id="payButton" type="button" class="btn btn-dark mt-3">
    Pay now
  </button>
  <div id="payment-message" class="mt-2 small text-danger" style="display:none;"></div>
</div>


      {{-- Step 4: Review & confirm --}}
      <div class="step">
        <h6>4. Review and confirm</h6>
        <form id="reserveForm" method="POST" action="{{ route('reserve.store') }}">
          @csrf
          <input type="hidden" name="service_id" value="{{ $service->id }}">
          <input type="hidden" name="package_id" value="{{ $package->id }}">
          <input type="hidden" name="client_tz" value="{{ $client_tz }}">
          {{-- serialize days[] --}}
          @foreach($days as $i => $d)
            <input type="hidden" name="days[{{ $i }}][date]"  value="{{ $d['date'] }}">
            <input type="hidden" name="days[{{ $i }}][start]" value="{{ $d['start'] }}">
            <input type="hidden" name="days[{{ $i }}][end]"   value="{{ $d['end'] }}">
          @endforeach
          <input type="hidden" name="env" id="envInput">

          <button id="reserveSubmit" type="submit" class="btn btn-primary">Confirm and continue</button>
        </form>
      </div>
    </div>

    {{-- Right column: Sticky summary & price breakdown --}}
    <div class="col-12 col-lg-5">
      <div class="card sticky-summary shadow-sm">
        <div class="card-body">
          <div class="d-flex align-items-center gap-3">
            <img src="{{ $service->thumbnail_url }}" class="rounded" width="72" height="72" alt="">
            <div>
              <div class="fw-semibold">{{ $service->title }}</div>
              <div class="muted small">{{ $dayCount }} {{ Str::plural('day',$dayCount) }} • {{ $package->name }}</div>
            </div>
          </div>

          <hr>

          <div class="price-line"><span>Subtotal</span><span>${{ number_format($subtotal,2) }}</span></div>
          @foreach($fees as $f)
            <div class="price-line muted"><span>{{ $f['label'] }}</span><span>${{ number_format($f['amount'],2) }}</span></div>
          @endforeach
          <hr>
          <div class="price-line fw-semibold"><span>Total (USD)</span><span>${{ number_format($total,2) }}</span></div>

          <div class="mt-3 muted small">
            Free cancellation policies etc. can be placed here.
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

@push('scripts')

<script>
 (() => {
  const form = document.getElementById('reserveForm');
  if (!form) return;

  form.addEventListener('submit', (e) => {
    e.preventDefault();

    const fd = new FormData(form);
    const payload = {};
    // structure days[]
    for (const [k,v] of fd.entries()) {
      const m = k.match(/^days\[(\d+)\]\[(date|start|end)\]$/);
      if (m) {
        const i = +m[1], f = m[2];
        (payload.days ||= [])[i] ||= {};
        payload.days[i][f] = v;
      } else {
        payload[k] = v;
      }
    }
    console.log('[RESERVE] about to submit', payload);

    // DEBUG: open submission in a new tab so logs stay
    form.setAttribute('target','_blank');   // ← remove after debugging

    // small delay to read the console
    setTimeout(() => form.submit(), 600);
  }, { once:true });


  })();
  </script>
<script>
  // simple pill toggle -> hidden input
  document.querySelectorAll('.env-pill').forEach(p => {
    p.addEventListener('click', () => {
      document.querySelectorAll('.env-pill').forEach(x => x.classList.remove('active'));
      p.classList.add('active');
      document.getElementById('envInput').value = p.dataset.value;
    });
  });
</script>
@endpush
@endsection
