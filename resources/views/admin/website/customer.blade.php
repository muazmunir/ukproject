@extends('layouts.admin')
@section('title','Client Settings')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin-websettings.css') }}">
@endpush

@section('content')
<section class="card settings-card">
  <div class="card__head">
    <div>
      <div class="card__title">Client Settings</div>
      <div class="muted text-capitalize">Set The Service Fee Percentage Charged To The <b>Clients</b>.</div>
    </div>
  </div>

  @include('admin.website._settings_nav')
  <form method="post" action="{{ route('admin.settings.customer.update') }}" class="settings-form">
    @csrf

    <div class="field">
      <label class="label">
        Commission Rate
        <span class="hint text-capitalize">(Commission rate will be calculated in %)</span>
      </label>
      <div class="inline-input">
       <input
  id="commission"
  type="text"
  name="commission"
  value="{{ old('commission', number_format($clientFee->amount, 1, '.', '')) }}"
  inputmode="decimal"
  autocomplete="off"
  placeholder="0.0"
  maxlength="5"
  oninput="fixCommission(this)"
  onblur="clampCommission(this)"
>

        <span class="suffix">%</span>
      </div>
      @error('commission')<div class="error">{{ $message }}</div>@enderror
    </div>

    <div class="form-foot">
      <button class="btn primary" type="submit">Update</button>
    </div>
  </form>
</section>
@endsection




@push('scripts')
<script>
  function fixCommission(el){
    // save caret position
    const start = el.selectionStart;
    const beforeLen = el.value.length;

    // allow only digits and one dot
    let v = el.value.replace(/[^\d.]/g, '');
    v = v.replace(/(\..*)\./g, '$1'); // keep only first dot

    // limit to 1 decimal place
    if (v.includes('.')) {
      const [a,b] = v.split('.');
      v = a + '.' + (b ?? '').slice(0,1);
    }

    el.value = v;

    // restore caret position (adjust for length change)
    const afterLen = el.value.length;
    const diff = afterLen - beforeLen;
    const newPos = Math.max(0, (start ?? afterLen) + diff);
    el.setSelectionRange(newPos, newPos);
  }

  function clampCommission(el){
    if (!el.value) return;

    let n = parseFloat(el.value);
    if (isNaN(n)) { el.value = ''; return; }

    if (n < 0) n = 0;
    if (n > 100) n = 100;

    // keep 1 decimal always
    el.value = n.toFixed(1);
  }
</script>
@endpush
