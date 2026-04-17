{{-- resources/views/reserve/create.blade.php --}}
@extends('layouts.app')
@section('title', __('Confirm & pay'))

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/reserve.css') }}">
<style>
  /* ---------- Quick polish (you can move to reserve.css) ---------- */
  .zv-container{ max-width: 1100px; }
  .zv-pill{
    display:inline-flex;align-items:center;gap:.4rem;
    padding:.35rem .6rem;border-radius:999px;
    border:1px solid #e5e7eb;background:#fff;font-size:.85rem;
  }
  .zv-muted{ color:#64748b; }
  .zv-divider{ height:1px;background:#e5e7eb;margin:14px 0; }

  /* Steps */
  .zv-step{
    border:1px solid #e5e7eb;border-radius:16px;
    background:#fff;overflow:hidden;margin-bottom:14px;
    box-shadow:0 1px 2px rgba(0,0,0,.04);
  }
  .zv-step-toggle{
    width:100%;display:flex;align-items:center;gap:10px;
    padding:14px 14px;background:transparent;border:0;text-align:left;
  }
  .zv-step-num{
    width:28px;height:28px;border-radius:999px;
    display:inline-flex;align-items:center;justify-content:center;
    background:#0f172a;color:#fff;font-weight:700;font-size:.9rem;
    flex:0 0 auto;
  }
  .zv-step-title{ font-weight:700; }
  .zv-step-body{ padding:14px; display:none; }
  .zv-step[aria-expanded="true"] .zv-step-body{ display:block; }
  .zv-btn-disabled{ opacity:.6; pointer-events:none; }

  /* Payment tabs */
  .zv-pay-tabs{ display:grid; gap:10px; }
  .zv-pay-tab{
    border:1px solid #e5e7eb;border-radius:14px;padding:12px;
    display:flex;align-items:center;justify-content:space-between;gap:12px;
    cursor:pointer;background:#fff;
  }
  .zv-pay-tab input{ display:none; }
  .zv-pay-main{ display:flex;align-items:flex-start;gap:10px; }
  .zv-pay-title{ font-weight:700; display:block; }
  .zv-pay-sub{ display:block;font-size:.85rem;color:#64748b; }
  

  /* Card summary (right) */
  .zv-sticky{ position:sticky; top:18px; }
  .zv-card{
    border:1px solid #e5e7eb;border-radius:18px;background:#fff;
    box-shadow:0 8px 24px rgba(15,23,42,.08);
  }
  .zv-card-body{ padding:16px; }
  .zv-media{ display:flex;gap:12px;align-items:flex-start; }
  .zv-media .thumb{ width:92px;height:72px;border-radius:14px;object-fit:cover; }
  .zv-media .title{ font-weight:800; }
  .zv-media .meta{ color:#64748b;font-size:.9rem; margin-top:2px; }
  .zv-badge{
    display:inline-flex;align-items:center;gap:6px;
    padding:.25rem .5rem;border-radius:999px;
    border:1px solid #e5e7eb;background:#fff;font-size:.82rem;
  }
  .zv-fee-line{ display:flex;justify-content:space-between;align-items:center;margin:8px 0; }
  .zv-total-line{
    display:flex;justify-content:space-between;align-items:center;
    margin-top:12px;padding-top:12px;border-top:1px solid #e5e7eb;
    font-weight:800;
  }

  /* Schedule block */
  .zv-summary-block{ padding:.25rem 0; }
  .zv-summary-slots{ display:grid; gap:.5rem; }
  .zv-summary-slot{
    display:flex;justify-content:space-between;gap:12px;
    padding:.55rem .6rem;border:1px solid #e5e7eb;border-radius:12px;background:#fff;
  }
  .zv-summary-date{  font-size:.9rem; }
  .zv-summary-time{ font-size:.9rem; color:#475569; }

  /* Card inputs wrappers (your existing classes keep working) */
  .zv-input-wrap{
    display:flex;align-items:center;gap:10px;
    padding:10px 12px;border:1px solid #e5e7eb;border-radius:14px;background:#fff;
  }
  .zv-input{ width:100%; }
  .zv-card-divider{ height:1px;background:#e5e7eb;margin:12px 0; }
  .zv-input-row{ display:grid; grid-template-columns:1fr 1fr; gap:10px; }
  .zv-field-label{ font-size:.85rem; font-weight:700; color:#0f172a; margin-bottom:6px; }
  .zv-ctl{ border-radius:14px; border:1px solid #e5e7eb; padding:10px 12px; }
  @media (max-width: 992px){
    .zv-sticky{ position:static; }
  }
  @media (max-width: 520px){
    .zv-input-row{ grid-template-columns:1fr; }
  }
</style>
@endpush

@section('content')
<div class="container my-4 zv-container">

  {{-- Global errors --}}
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  {{-- Validation errors --}}
  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $err)
          <li>{{ $err }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  
  <h4 class="mb-3 fw-bold">{{ __('Confirm Your Reservation') }}</h4>

  <div class="row g-4 align-items-start">

    {{-- LEFT: STEPS --}}
    <div class="col-12 col-lg-7">

      {{-- ===== STEP 1: Where ===== --}}
      <section class="zv-step" id="step-1" aria-expanded="true">
        <button class="zv-step-toggle" type="button" data-step="1">
          <span class="zv-step-num">1</span>
          <span class="zv-step-title text-capitalize">{{ __('Where do you want to be coached?') }}</span>
          <i class="bi bi-chevron-down ms-auto"></i>
        </button>

        <div class="zv-step-body">
          @if(count($environments))
            <div class="zv-env mb-2">
              @foreach($environments as $i => $env)
                <label class="form-check">
                  <input class="form-check-input" type="radio" name="env_fake"
                         value="{{ $env }}" {{ $i === 0 ? 'checked' : '' }}>
                  <i class="bi bi-geo-alt"></i>
                  <span>{{ $env }}</span>
                </label>
              @endforeach
            </div>
          @else
            <div class="zv-muted">
              {{ __('No environments provided by the coach. You can explain your preference in the note below.') }}
            </div>
          @endif

          <div class="zv-divider"></div>

          <label class="form-label text-capitalize">
            {{ __('Disability / medication or anything the coach should know') }}
          </label>
          <textarea id="note" class="form-control zv-ctl" rows="4" maxlength="2000"
                    placeholder="{{ __('Optional') }}"></textarea>

          <div class="d-flex justify-content-between align-items-center mt-2">
            <small class="zv-muted">
              <span id="note-count">0</span>/2000
            </small>

            <button type="button" class="btn btn-dark btn-sm" data-next="2">
              {{ __('Next') }} <i class="bi bi-arrow-right ms-1"></i>
            </button>
          </div>
        </div>
      </section>

      {{-- ===== STEP 2: Payment ===== --}}
      <section class="zv-step" id="step-2" aria-expanded="false">
        <button class="zv-step-toggle" type="button" data-step="2">
          <span class="zv-step-num">2</span>
          <span class="zv-step-title text-capitalize">{{ __('Add a payment method') }}</span>
          <i class="bi bi-chevron-down ms-auto"></i>
        </button>

        <div class="zv-step-body">

          {{-- Payment selector --}}
          <div class="mb-3">

          @php
  $availPlatformMinor = (int)(auth()->user()->platform_credit_minor ?? 0);
@endphp

<div class="border rounded-3 p-3 mb-3">
  <div class="d-flex justify-content-between align-items-center">
    <div class="fw-semibold">{{ __('Your Balance') }}</div>
    <div class="small text-muted">
      {{ __('Available') }}:
      <strong>${{ number_format($availPlatformMinor/100, 2) }}</strong>
    </div>
  </div>

  <label class="form-check mt-2">
    <input class="form-check-input" type="checkbox" id="use_platform_credit" name="use_platform_credit" value="1">
    <span class="form-check-label">
      {{ __('Use non-withdrawable balance (platform credit)') }}
    </span>
  </label>

  <div class="small text-muted mt-2">
    {{ __('We’ll apply it first and charge the remainder with your selected method.') }}
  </div>

  <div class="mt-2 small">
    <div class="d-flex justify-content-between">
      <span class="text-muted">{{ __('Applied from balance') }}</span>
      <span id="ui-credit-applied">$0.00</span>
    </div>
    <div class="d-flex justify-content-between">
      <span class="text-muted">{{ __('Remaining to pay') }}</span>
      <span id="ui-remaining">$0.00</span>
    </div>
  </div>
</div>

          <div class="zv-pay-tabs">

  {{-- Card --}}
  <label class="zv-pay-tab">
    <div class="zv-pay-main">
      <i class="bi bi-credit-card-2-front"></i>
      <div>
        <span class="zv-pay-title">{{ __('Credit Or Debit Card') }}</span>
        <span class="zv-pay-sub">Visa · MasterCard · AmEx · JCB</span>
      </div>
    </div>
    <input type="radio" name="paytab" value="card" checked>
  </label>

  {{-- PayPal --}}
  <label class="zv-pay-tab">
    <div class="zv-pay-main">
      <i class="bi bi-paypal"></i>
      <div>
        <span class="zv-pay-title">PayPal</span>
        <span class="zv-pay-sub">{{ __('Pay With Your PayPal Account') }}</span>
      </div>
    </div>
    <input type="radio" name="paytab" value="paypal">
  </label>

  {{-- Wallet (hidden until available on device/browser) --}}
  <label class="zv-pay-tab" id="wallet-tab" style="display:none;">
    <div class="zv-pay-main">
      <i class="bi bi-wallet2"></i>
      <div>
        <span class="zv-pay-title" id="wallet-label">{{ __('Apple Pay / Google Pay') }}</span>
        <span class="zv-pay-sub">{{ __('Fast checkout with your saved wallet') }}</span>
      </div>
    </div>
    <input type="radio" name="paytab" value="wallet">
  </label>

</div>
          </div>

          {{-- Panels --}}
          <div class="mt-3">

            {{-- CARD --}}
           <div class="zv-pay-panel" data-panel="card">

  {{-- Card number --}}
  <div class="zv-field">
    <label class="zv-label">{{ __('Card Number') }}</label>
    <div class="zv-control zv-control--stripe">
      <span class="zv-icon"><i class="bi bi-credit-card-2-front"></i></span>
      <div id="card-number" class="StripeElement zv-stripe"></div>
      <span class="zv-suffix"><i class="bi bi-lock-fill"></i></span>
    </div>
    <div class="zv-help text-capitalize">{{ __('Secure payment powered by Stripe') }}</div>
  </div>

  {{-- Expiry + CVC --}}
  <div class="zv-grid-2">
    <div class="zv-field">
      <label class="zv-label">{{ __('Expiration') }}</label>
      <div class="zv-control zv-control--stripe">
        <div id="card-expiry" class="StripeElement zv-stripe"></div>
      </div>
    </div>

    <div class="zv-field">
      <label class="zv-label">{{ __('CVC') }}</label>
      <div class="zv-control zv-control--stripe">
        <div id="card-cvc" class="StripeElement zv-stripe"></div>
      </div>
    </div>
  </div>

  <div class="zv-sep"></div>

  {{-- Billing address --}}
  <div class="zv-section-title">{{ __('Billing Address') }}</div>

  <div class="zv-field">
    <label class="zv-label">{{ __('Street Address') }}</label>
    <div class="zv-control">
      <input class="zv-input" id="bill-line1" placeholder="{{ __('Street Address') }}">
    </div>
  </div>

  <div class="zv-field">
    <label class="zv-label">{{ __('Apt Or Suite Number') }}</label>
    <div class="zv-control">
      <input class="zv-input" id="bill-line2" placeholder="{{ __('Apt Or Suite Number') }}">
    </div>
  </div>

  <div class="zv-grid-2">

    <div class="zv-field">
      <label class="zv-label">{{ __('Country/Region') }}</label>
      <div class="zv-control">
        <select
          class="zv-input js-bill-country"
          id="bill-country"
          name="bill_country"
          data-selected="{{ old('bill_country', $clientCountryName ?? 'Pakistan') }}"

        >
          <option value="">{{ __('Select') }}</option>
        </select>
      </div>
    </div>
   <div class="zv-field">
  <label class="zv-label">{{ __('City') }}</label>
  <div class="zv-control">
    <select class="zv-input js-bill-city" id="bill-city" name="bill_city" data-selected="{{ old('bill_city') }}">
      <option value="">{{ __('Select') }}</option>
    </select>
  </div>
</div>


    
  </div>

  <div class="zv-grid-2">
    <div class="zv-field">
      <label class="zv-label">{{ __('ZIP Code') }}</label>
      <div class="zv-control">
        <input class="zv-input" id="bill-zip" placeholder="{{ __('ZIP Code') }}">
      </div>
    </div>

    <div class="zv-field">
      <label class="zv-label">{{ __('State') }}</label>
      <div class="zv-control">
        <input class="zv-input" id="bill-state" placeholder="{{ __('State') }}">
      </div>
    </div>

    
  </div>

</div>


            {{-- PAYPAL --}}
           {{-- PAYPAL --}}
<div class="zv-pay-panel d-none" data-panel="paypal">
  <div class="p-3 border rounded-3">
    <div class="zv-muted text-capitalize">
      {{ __('After confirming, you’ll be redirected to PayPal to complete the payment.') }}
    </div>
  </div>
</div>

{{-- WALLET --}}
<div class="zv-pay-panel d-none" data-panel="wallet">
  <div class="p-3 border rounded-3">
    <div class="mb-2 fw-semibold" id="wallet-panel-title">{{ __('Apple Pay / Google Pay') }}</div>
    <div id="wallet-button"></div>
    <div class="zv-muted mt-2 small">
      {{ __('The wallet button only appears on supported browsers/devices with Apple Pay or Google Pay already configured.') }}
    </div>
  </div>
</div>

            {{-- KLARNA --}}
          
          </div>

          <div class="d-flex justify-content-between mt-3">
            <button type="button" class="btn btn-outline-dark btn-sm" data-prev="1">
              <i class="bi bi-arrow-left me-1"></i> {{ __('Back') }}
            </button>

            <button type="button" class="btn btn-dark btn-sm" data-next="3">
              {{ __('Next') }} <i class="bi bi-arrow-right ms-1"></i>
            </button>
          </div>
        </div>
      </section>

      {{-- ===== STEP 3: Review & Confirm ===== --}}
      <section class="zv-step" id="step-3" aria-expanded="false">
        <button class="zv-step-toggle" type="button" data-step="3">
          <span class="zv-step-num">3</span>
          <span class="zv-step-title text-capitalize">{{ __('Review & confirm') }}</span>
          <i class="bi bi-chevron-down ms-auto"></i>
        </button>

        <div class="zv-step-body">

          <div class="zv-muted small mb-3 text-capitalize">
            {{ __('By confirming, you agree to the booking terms and you’ll be charged the total shown on the right.') }}
          </div>

          <form method="POST" action="{{ route('reserve.store') }}" id="reserveForm">
          
            @csrf
            <input type="hidden" name="use_platform_credit" id="use_platform_credit_hidden" value="0">

            <input type="hidden" name="platform_credit_apply_minor" id="platform_credit_apply_minor" value="0">
<input type="hidden" name="payable_minor" id="payable_minor" value="0">

<input type="hidden" name="payment_method" id="payment_method" value="card">
<input type="hidden" name="checkout_method" id="checkout_method" value="card">
<input type="hidden" name="wallet_type" id="wallet_type" value="">

            <input type="hidden" name="service_id" value="{{ $service->id }}">
            <input type="hidden" name="package_id" value="{{ $package->id }}">
            <input type="hidden" name="client_tz"  value="{{ $clientTz }}">
            <input type="hidden" name="environment" id="envHidden" value="{{ $environments[0] ?? '' }}">
            <input type="hidden" name="note" id="noteHidden" value="">
            <input type="hidden" name="pricing_snapshot"
       value="{{ base64_encode(json_encode($pricingSnapshot)) }}">




            @foreach($rawDays as $i => $d)
              <input type="hidden" name="days[{{ $i }}][date]"  value="{{ $d['date'] }}">
              <input type="hidden" name="days[{{ $i }}][start]" value="{{ $d['start'] }}">
              <input type="hidden" name="days[{{ $i }}][end]"   value="{{ $d['end'] }}">
            @endforeach

            <input type="hidden" name="payment_method" id="payment_method" value="card">



            {{-- OVERALL SUMMARY (inside Step 3) --}}
<div class="border rounded-3 p-3 mb-3">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <div class="fw-semibold">{{ __('Overall Summary') }}</div>
    <span class="small text-muted">{{ $clientTz }}</span>
  </div>

  {{-- Schedule --}}
 <div class="mb-2">
  <div class="small fw-semibold mb-1">{{ __('Schedule') }}</div>

  @foreach($slots as $s)
    <div class="d-flex justify-content-between small">
      <span>
        {{ \Carbon\Carbon::parse($s['date'])->format('m/d/Y') }}
      </span>
      <span>
        {{ $s['start']->setTimezone($clientTz)->format('H:i') }}
        – {{ $s['end']->setTimezone($clientTz)->format('H:i') }}
      </span>
    </div>
  @endforeach

  <div class="small text-muted mt-1">
    {{ __('Total Hours') }}:
    <strong>{{ number_format($totalHours, 2) }}</strong>
  </div>
</div>


  <hr class="my-2">

  {{-- Where to coach --}}
  <div class="d-flex justify-content-between small mb-1">
    <span class="text-muted">{{ __('Where To Coach') }}</span>
    <span id="summary-env-inline">{{ $environments[0] ?? __('Not Specified') }}</span>
  </div>

  {{-- Package --}}
  <div class="d-flex justify-content-between small mb-1">
    <span class="text-muted">{{ __('Package') }}</span>
    <span>{{ $package->name }}</span>
  </div>

  <hr class="my-2">

  {{-- Price details --}}
  <div class="d-flex justify-content-between small mb-1">
    <span class="text-muted">{{ __('Subtotal') }}</span>
    <span>${{ number_format($subtotal,2) }}</span>
  </div>

 @php
  $platformValue = $clientPlatformFee ?? 0;
@endphp
<div class="d-flex justify-content-between small mb-1">
  <span class="text-muted">{{ __('Service Fee') }}</span>
  <span>${{ number_format($platformValue,2) }}</span>
</div>


 <div class="d-flex justify-content-between fw-semibold mt-2">
  <span>{{ __('Total') }}</span>
  <span id="ui-total-step3">${{ number_format($total,2) }}</span>
</div>

</div>


           <div class="d-flex justify-content-start">
  <button type="button" class="btn btn-outline-dark btn-sm" data-prev="2">
    <i class="bi bi-arrow-left me-1"></i> {{ __('Back') }}
  </button>
</div>
          </form>
        </div>
      </section>

    </div>

    {{-- RIGHT: SUMMARY (Airbnb style, includes Schedule) --}}
    <div class="col-12 col-lg-5">
      <aside class="zv-card zv-sticky">
        <div class="zv-card-body">

          <div class="zv-media">
            <img class="thumb" src="{{ $service->thumbnail_url }}" alt="{{ $service->title }}">
            <div>
              <div class="title">{{ $service->title }}</div>
              <div class="meta">
                {{ optional($service->coach)->full_name }}
                @if($service->city_name) • {{ $service->city_name }} @endif
              </div>
              <div class="d-flex align-items-center gap-2 mt-2">
                <span class="zv-badge">
                  <i class="bi bi-star-fill text-warning"></i>
                  {{ number_format($service->rating ?? 5, 1) }}
                </span>
                <span class="zv-badge">
                  <i class="bi bi-globe2"></i> {{ $clientTz }}
                </span>
              </div>
            </div>
          </div>

          {{-- Schedule moved here --}}
          <div class="zv-divider"></div>
          <div class="zv-summary-block">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div class="fw-semibold">{{ __('Schedule') }}</div>
              <span class="zv-muted small">{{ $clientTz }}</span>
            </div>

            <div class="zv-summary-slots">
              @foreach($slots as $s)
                <div class="zv-summary-slot">
                 <div class="zv-summary-date">
  {{ \Carbon\Carbon::parse($s['date'])->format('m/d/Y') }}
</div>

                  <div class="zv-summary-time">
                    {{ $s['start']->setTimezone($clientTz)->format('H:i') }}
                    – {{ $s['end']->setTimezone($clientTz)->format('H:i') }}
                  </div>
                </div>
              @endforeach
            </div>

            <div class="zv-muted small mt-2">
              {{ __('Total Hours') }}: <strong>{{ number_format($totalHours, 2) }}</strong>
            </div>
          </div>

          <div class="zv-divider"></div>

          <div class="zv-fee-line">
            <span class="zv-muted">{{ __('Package') }}</span>
            <span class="fw-semibold">{{ $package->name }}</span>
          </div>

          <div class="zv-fee-line">
            <span>{{ __('Subtotal') }}</span>
            <span>${{ number_format($subtotal, 2) }}</span>
          </div>

          @if(!empty($feeLines))
            @php
              $clientFeeLine = collect($feeLines)->first(function ($f) {
                  return ($f['party'] ?? null) === 'client'
                      || ($f['slug'] ?? null) === 'client_commission';
              });
              $platformValue = $clientFeeLine['value'] ?? ($feeLines[0]['value'] ?? 0);
            @endphp
            <div class="zv-fee-line">
              <span>{{ __('Service Fee') }}</span>
              <span>${{ number_format($platformValue, 2) }}</span>
            </div>
          @endif

          <div class="zv-total-line">
  <span>{{ __('Total') }}</span>
  <span id="ui-total">${{ number_format($total, 2) }}</span>
</div>


          <div class="zv-divider"></div>

        

<button class="btn btn-dark w-100 mt-2" id="btnConfirmPay" type="button">
  {{ __('Confirm & Pay') }}
</button>





        </div>
      </aside>
    </div>

  </div>
</div>


{{-- Coach Refund Policy Modal --}}

@endsection

@push('scripts')
<script src="https://js.stripe.com/v3/"></script>

{{-- STEP SCRIPT --}}
<script>
(function(){
  const steps = [1,2,3];
  const stepVisited = { 1: true, 2: false };

  const btnConfirm = document.getElementById('btnConfirmPay');

  function updateConfirmState(){
    if (!btnConfirm) return;

    // Must have visited step 1 & 2
    const ready = stepVisited[1] && stepVisited[2];

    btnConfirm.disabled = !ready;
    btnConfirm.classList.toggle('zv-btn-disabled', !ready);
  }

  function openStep(n){
    steps.forEach(i => {
      const sec  = document.getElementById('step-'+i);
      const body = sec?.querySelector('.zv-step-body');
      if (!sec || !body) return;

      const expanded = (i === n);
      sec.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      body.style.display = expanded ? 'block' : 'none';
    });

    if (n === 1 || n === 2) {
      stepVisited[n] = true;
      updateConfirmState();
    }

    document.getElementById('step-'+n)?.scrollIntoView({behavior:'smooth',block:'start'});
  }

  // init
  openStep(1);
  updateConfirmState();

  document.querySelectorAll('.zv-step-toggle').forEach(b =>
    b.addEventListener('click', () => openStep(Number(b.dataset.step)))
  );

  // NEXT buttons
  document.querySelectorAll('[data-next]').forEach(b => {
    b.addEventListener('click', () => {
      const next = Number(b.dataset.next);

      // BLOCK: going to step 3 unless payment section valid (only for card)
      if (next === 3) {
        const method = document.getElementById('payment_method')?.value || 'card';

        if (method === 'card' && typeof window.zvValidateCardStep === 'function') {
          if (!window.zvValidateCardStep()) {
            // jump user to step 2 (ensure visible)
            openStep(2);
            return;
          }
        }
      }

      openStep(next);
    });
  });

  // PREV buttons
  document.querySelectorAll('[data-prev]').forEach(b =>
    b.addEventListener('click', () => openStep(Number(b.dataset.prev)))
  );

  // env -> hidden + summary inline (optional)
  const envHidden = document.getElementById('envHidden');
  const envInline = document.getElementById('summary-env-inline');
  document.querySelectorAll('input[name="env_fake"]').forEach(r => {
    r.addEventListener('change', () => {
      if (envHidden) envHidden.value = r.value;
      if (envInline) envInline.textContent = r.value;
    });
  });

  // note counter + sync
  const note       = document.getElementById('note');
  const cnt        = document.getElementById('note-count');
  const noteHidden = document.getElementById('noteHidden');

  if (note && cnt) {
    note.addEventListener('input', () => { cnt.textContent = String(note.value.length); });
  }

  document.getElementById('reserveForm')?.addEventListener('submit', () => {
    if (noteHidden && note) noteHidden.value = note.value.trim();
  });
})();
</script>


{{-- PAYMENT PANEL SWITCHER --}}
<script>
(function(){
  const methodHidden = document.getElementById('payment_method');
  const checkoutMethodHidden = document.getElementById('checkout_method');
  const walletTypeHidden = document.getElementById('wallet_type');
  const panels = document.querySelectorAll('.zv-pay-panel');
  const tabs   = document.querySelectorAll('.zv-pay-tab');

  function showPanel(name){
    panels.forEach(p => p.classList.toggle('d-none', p.dataset.panel !== name));

    if (methodHidden) methodHidden.value = name;

    if (checkoutMethodHidden) {
      if (name === 'wallet') checkoutMethodHidden.value = 'wallet';
      else if (name === 'paypal') checkoutMethodHidden.value = 'paypal';
      else checkoutMethodHidden.value = 'card';
    }

    if (name !== 'wallet' && walletTypeHidden) {
      walletTypeHidden.value = '';
    }

    tabs.forEach(tab => {
      const radio = tab.querySelector('input[name="paytab"]');
      tab.classList.toggle('is-active', radio?.value === name);
    });
  }

  document.querySelectorAll('input[name="paytab"]').forEach(r => {
    r.addEventListener('change', () => showPanel(r.value));
  });

  showPanel(methodHidden?.value || document.querySelector('input[name="paytab"]:checked')?.value || 'card');

  window.zvShowPaymentPanel = showPanel;
})();
</script>



{{-- STRIPE + PAYPAL SWITCH (your logic kept, minor edits only) --}}
<script>
(function(){
  const stripe = Stripe("{{ config('services.stripe.key') }}");
  const elements = stripe.elements();

  const btn = document.getElementById('btnConfirmPay');
  const form = document.getElementById('reserveForm');

  const methodHidden = document.getElementById('payment_method');
  const checkoutMethodHidden = document.getElementById('checkout_method');
  const walletTypeHidden = document.getElementById('wallet_type');

  const walletTab = document.getElementById('wallet-tab');
  const walletLabel = document.getElementById('wallet-label');
  const walletPanelTitle = document.getElementById('wallet-panel-title');
  const walletButton = document.getElementById('wallet-button');

  const cardHost   = document.getElementById('card-number');
  const expiryHost = document.getElementById('card-expiry');
  const cvcHost    = document.getElementById('card-cvc');

  const elementStyle = {
    base: {
      color: '#0f172a',
      fontSize: '16px',
      fontFamily: 'Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif',
      '::placeholder': { color: '#9ca3af' }
    },
    invalid: { color: '#ef4444' }
  };

  let elCardNumber, elCardExpiry, elCardCvc;
  let currentPiId = null;
  let paymentRequest = null;
  let prButton = null;
  let walletHandlerBound = false;

  const stripeState = { number:false, expiry:false, cvc:false };

  function mountCardElements(){
    if (!cardHost || elCardNumber) return;

    elCardNumber = elements.create('cardNumber', { style: elementStyle, showIcon: true });
    elCardExpiry = elements.create('cardExpiry', { style: elementStyle });
    elCardCvc    = elements.create('cardCvc', { style: elementStyle });

    elCardNumber.mount(cardHost);
    elCardExpiry.mount(expiryHost);
    elCardCvc.mount(cvcHost);

    elCardNumber.on('change', (e) => { stripeState.number = !!e.complete; });
    elCardExpiry.on('change', (e) => { stripeState.expiry = !!e.complete; });
    elCardCvc.on('change', (e) => { stripeState.cvc = !!e.complete; });
  }
  mountCardElements();

  function markInvalid(id, isBad){
    const el = document.getElementById(id);
    if (!el) return;
    const wrap = el.closest('.zv-control') || el;
    if (isBad) wrap.classList.add('zv-invalid','is-invalid');
    else wrap.classList.remove('zv-invalid','is-invalid');
  }

  function validateCardStep(){
    const method = methodHidden?.value || 'card';
    const payableMinor = parseInt(document.getElementById('payable_minor')?.value || '0', 10) || 0;

    if (payableMinor === 0) return true;
    if (method !== 'card') return true;

    const okStripe = stripeState.number && stripeState.expiry && stripeState.cvc;
    if (!okStripe) {
      (window.zToast ? window.zToast('Please complete card number, expiration, and CVC.', 'error') : console.warn('toast missing'));
      return false;
    }

    const required = ['bill-line1','bill-city','bill-state','bill-zip','bill-country'];
    let ok = true;
    let firstBad = null;

    required.forEach(id => {
      const el = document.getElementById(id);
      const bad = !el || !String(el.value || '').trim();
      markInvalid(id, bad);
      if (bad) {
        ok = false;
        if (!firstBad) firstBad = el;
      }
    });

    if (!ok) {
      (window.zToast ? window.zToast('Please fill all billing address fields.', 'error') : console.warn('toast missing'));
      firstBad?.focus?.();
      return false;
    }

    return true;
  }

  window.zvValidateCardStep = validateCardStep;

  function getPayableMinor(){
    return parseInt(document.getElementById('payable_minor')?.value || '0', 10) || 0;
  }

  function buildPayload(){
    return {
      service_id: {{ $service->id }},
      package_id: {{ $package->id }},
      client_tz: "{{ $clientTz }}",
      pricing_snapshot: document.querySelector('input[name="pricing_snapshot"]')?.value || null,
      note: document.getElementById('noteHidden')?.value || null,
      environment: document.getElementById('envHidden')?.value || null,
      days: [
        @foreach($rawDays as $d)
          { date: "{{ $d['date'] }}", start: "{{ $d['start'] }}", end: "{{ $d['end'] }}" },
        @endforeach
      ],
      use_platform_credit: document.getElementById('use_platform_credit_hidden')?.value || '0',
      platform_credit_apply_minor: document.getElementById('platform_credit_apply_minor')?.value || '0',
      payable_minor: document.getElementById('payable_minor')?.value || '0',
      payment_intent_id: currentPiId,
      checkout_method: checkoutMethodHidden?.value || 'card',
      wallet_type: walletTypeHidden?.value || ''
    };
  }

  async function createOrUpdatePI(payload){
    const res = await fetch("{{ route('payments.intent') }}", {
      method:'POST',
      credentials:'same-origin',
      headers:{
        'Content-Type':'application/json',
        'Accept':'application/json',
        'X-CSRF-TOKEN':'{{ csrf_token() }}'
      },
      body: JSON.stringify(payload)
    });

    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Failed to create payment intent');

    currentPiId = data.payment_intent_id || null;
    return data;
  }

  async function setupWalletButton(){
    if (!walletTab || !walletButton) return;

    const payableMinor = getPayableMinor();

    if (prButton) {
      try { prButton.unmount(); } catch(e) {}
      prButton = null;
    }

    paymentRequest = null;
    walletHandlerBound = false;
    walletButton.innerHTML = '';

    if (payableMinor <= 0) {
      walletTab.style.display = 'none';
      return;
    }

    paymentRequest = stripe.paymentRequest({
      country: 'US',
      currency: 'usd',
      total: {
        label: 'Total',
        amount: payableMinor,
      },
      requestPayerName: true,
      requestPayerEmail: true,
    });

    const result = await paymentRequest.canMakePayment();

    console.log('Wallet result:', result);
    if (!result) {
      walletTab.style.display = 'none';
      return;
    }

    walletTypeHidden.value = '';

    if (result.applePay) {
      walletLabel.textContent = 'Apple Pay';
      if (walletPanelTitle) walletPanelTitle.textContent = 'Apple Pay';
      walletTypeHidden.value = 'apple_pay';
    } else if (result.googlePay) {
      walletLabel.textContent = 'Google Pay';
      if (walletPanelTitle) walletPanelTitle.textContent = 'Google Pay';
      walletTypeHidden.value = 'google_pay';
    } else {
      walletLabel.textContent = 'Apple Pay / Google Pay';
      if (walletPanelTitle) walletPanelTitle.textContent = 'Apple Pay / Google Pay';
    }

    walletTab.style.display = '';

    prButton = elements.create('paymentRequestButton', {
      paymentRequest: paymentRequest,
    });

    prButton.mount('#wallet-button');

    if (!walletHandlerBound) {
      walletHandlerBound = true;

      paymentRequest.on('paymentmethod', async (ev) => {
        try {
          checkoutMethodHidden.value = 'wallet';

          const payload = buildPayload();
          const data = await createOrUpdatePI(payload);
          const clientSecret = data.client_secret;

          const firstConfirm = await stripe.confirmCardPayment(
            clientSecret,
            {
              payment_method: ev.paymentMethod.id,
            },
            { handleActions: false }
          );

          if (firstConfirm.error) {
            ev.complete('fail');
            throw new Error(firstConfirm.error.message || 'Wallet payment failed');
          }

          ev.complete('success');

          if (firstConfirm.paymentIntent && firstConfirm.paymentIntent.status === 'requires_action') {
            const secondConfirm = await stripe.confirmCardPayment(clientSecret);
            if (secondConfirm.error) {
              throw new Error(secondConfirm.error.message || 'Wallet authentication failed');
            }
          }

          window.location.href = "{{ route('payments.success') }}?pi=" + encodeURIComponent(currentPiId);
        } catch (err) {
          console.error(err);
          (window.zToast ? window.zToast(err.message || 'Wallet payment failed', 'error') : console.error(err));
          btn.disabled = false;
        }
      });
    }
  }

  btn?.addEventListener('click', async (e) => {
    e.preventDefault();

    const method = methodHidden?.value || 'card';
    const payableMinor = getPayableMinor();

    if (payableMinor === 0) {
      form.action = "{{ route('reserve.store') }}";
      form.method = "POST";
      form.submit();
      return;
    }

    if (method === 'card' && !validateCardStep()) {
      return;
    }

    if (method === 'paypal') {
      form.action = "{{ route('paypal.create') }}";
      form.method = "POST";
      form.submit();
      return;
    }

    if (method === 'wallet') {
      btn.disabled = false;

      if (!paymentRequest) {
        (window.zToast ? window.zToast('Apple Pay / Google Pay is not available on this device.', 'error') : console.warn('wallet unavailable'));
        return;
      }

      try {
        paymentRequest.show();
      } catch (err) {
        console.error(err);
      }
      return;
    }

    btn.disabled = true;

    try {
      checkoutMethodHidden.value = 'card';
      walletTypeHidden.value = '';

      const payload = buildPayload();
      const data = await createOrUpdatePI(payload);
      const clientSecret = data.client_secret;

      const billingDetails = {
        address: {
          line1: document.getElementById('bill-line1')?.value || undefined,
          line2: document.getElementById('bill-line2')?.value || undefined,
          city: document.getElementById('bill-city')?.value || undefined,
          state: document.getElementById('bill-state')?.value || undefined,
          postal_code: document.getElementById('bill-zip')?.value || undefined,
          country: document.getElementById('bill-country')?.selectedOptions?.[0]?.dataset?.code || undefined,
        }
      };

      const { error } = await stripe.confirmCardPayment(clientSecret, {
        payment_method: {
          card: elCardNumber,
          billing_details: billingDetails
        },
        return_url: "{{ route('payments.success') }}" + "?pi=" + encodeURIComponent(currentPiId),
      });

      if (error) throw new Error(error.message || 'Payment failed');

      window.location.href = "{{ route('payments.success') }}?pi=" + encodeURIComponent(currentPiId);
    } catch (err) {
      console.error(err);
      (window.zToast ? window.zToast(err.message || 'Something went wrong', 'error') : console.error(err));
      btn.disabled = false;
    }
  });

  document.addEventListener('DOMContentLoaded', () => {
    setupWalletButton();
  });

  window.setupWalletButton = setupWalletButton;
})();
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  if (window._ccBillingInit) return;
  window._ccBillingInit = true;

  const API_COUNTRIES = '{{ route("cc.countries") }}';
  const API_CITIES    = '{{ route("cc.cities") }}';

  const countrySel = document.getElementById('bill-country');
  const citySel    = document.getElementById('bill-city');
  if (!countrySel || !citySel) return;

  function setDisabled(el, on){ el.disabled = !!on; }

  function setOptions(el, items, placeholder='Select', preselect='') {
    const frag = document.createDocumentFragment();

    const opt0 = document.createElement('option');
    opt0.value = '';
    opt0.textContent = placeholder;
    frag.appendChild(opt0);

   items.forEach(item => {
  const opt = document.createElement('option');

  // Cities list comes like ["Islamabad", "Lahore", ...]
  if (typeof item === 'string') {
    opt.value = item;
    opt.textContent = item;
  }

  // Countries list comes like [{code:"PK", name:"Pakistan"}, ...]
  else if (item && typeof item === 'object') {
    opt.value = item.name;                 // submit NAME (Pakistan) to your backend
    opt.textContent = item.name;
    opt.dataset.code = item.code || '';    // keep CODE (PK) for Stripe
  }

  frag.appendChild(opt);
});


    el.innerHTML = '';
    el.appendChild(frag);

    if (preselect) {
      el.value = preselect;
      if (el.value !== preselect) el.value = '';
    }
  }

  async function fetchJSON(url, params={}) {
    const qs = new URLSearchParams(params).toString();
    const full = qs ? `${url}?${qs}` : url;

    const res = await fetch(full, {
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
      credentials: 'same-origin',
    });

    const text = await res.text();
    let json = null; try { json = JSON.parse(text); } catch {}

    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    if (!json || json.success !== true) throw new Error((json && json.message) || 'API error');
    return json.data || [];
  }

  async function loadCountries(){
    setDisabled(countrySel, true);
    setOptions(countrySel, [], 'Loading...');

    const countries = await fetchJSON(API_COUNTRIES).catch(() => []);
    setOptions(countrySel, countries, 'Select', countrySel.dataset.selected || '');
    setDisabled(countrySel, false);
  }

  async function loadCities(countryName, preselect=''){
    if (!countryName) {
      setOptions(citySel, [], 'Select a country first');
      setDisabled(citySel, true);
      return;
    }

    setDisabled(citySel, true);
    setOptions(citySel, [], 'Loading...');

    const cities = await fetchJSON(API_CITIES, { country: countryName }).catch(() => []);
    setOptions(citySel, cities, 'Select', preselect);
    setDisabled(citySel, false);
  }

  (async () => {
    await loadCountries();

    // preload cities if a country already selected (old() / request())
    const selectedCountry = countrySel.value || countrySel.dataset.selected || '';
    if (selectedCountry) {
      await loadCities(selectedCountry, citySel.dataset.selected || '');
    } else {
      setOptions(citySel, [], 'Select a country first');
      setDisabled(citySel, true);
    }
  })();

  countrySel.addEventListener('change', async () => {
    await loadCities(countrySel.value, '');
  });
});
</script>


<script>
(function(){
  const totalMinor = Math.round({{ $total }} * 100);
  const availMinor = {{ (int)(auth()->user()->platform_credit_minor ?? 0) }};

  const chk = document.getElementById('use_platform_credit');
  const appliedEl = document.getElementById('ui-credit-applied');
  const remainEl  = document.getElementById('ui-remaining');

  const totalRight = document.getElementById('ui-total');
  const totalStep3 = document.getElementById('ui-total-step3');

  const applyMinorInput   = document.getElementById('platform_credit_apply_minor');
  const payableMinorInput = document.getElementById('payable_minor');
  const useHidden         = document.getElementById('use_platform_credit_hidden');

  function money(minor){ return '$' + (minor / 100).toFixed(2); }

  function setTotals(){
    const use = !!(chk && chk.checked);
    const appliedMinor = use ? Math.min(availMinor, totalMinor) : 0;
    const remaining = Math.max(0, totalMinor - appliedMinor);

    if (useHidden) useHidden.value = use ? '1' : '0';
    if (applyMinorInput) applyMinorInput.value = String(appliedMinor);
    if (payableMinorInput) payableMinorInput.value = String(remaining);

    if (appliedEl) appliedEl.textContent = money(appliedMinor);
    if (remainEl)  remainEl.textContent  = money(remaining);

    if (totalRight) totalRight.textContent = money(remaining);
    if (totalStep3) totalStep3.textContent = money(remaining);
  }

  async function refreshTotalsAndWallet(){
    setTotals();

    if (typeof window.setupWalletButton === 'function') {
      await window.setupWalletButton();
    }
  }

  setTotals();

  chk?.addEventListener('change', refreshTotalsAndWallet);

  const oldValidate = window.zvValidateCardStep;
  window.zvValidateCardStep = function(){
    const payable = parseInt(payableMinorInput?.value || '0', 10) || 0;
    if (payable === 0) return true;
    return (typeof oldValidate === 'function') ? oldValidate() : true;
  };
})();
</script>



@endpush
