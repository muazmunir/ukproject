@extends('superadmin.layout')
@section('title','Coaches Settings')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin-websettings.css') }}">
@endpush

@section('content')
<section class="card settings-card">
  <div class="card__head">
    <div>
      <div class="card__title">Coaches Settings</div>
      <div class="muted text-capitalize">
        Set The Service Fee Percentage Charged To The Coach And Amend Coaches Default Options.
      </div>
    </div>
  </div>

  @include('superadmin.website._settings_nav')

  <form method="post"
        action="{{ route('superadmin.settings.trainer.update') }}"
        enctype="multipart/form-data"
        class="settings-form">
    @csrf

    {{-- ✅ COMMISSION RATE --}}
    <div class="field">
      <label class="label">
        Commission Rate
        <span class="hint text-capitalize">(Commission rate will be calculated in %)</span>
      </label>

      <div class="inline-input">
       <input
  id="coach_commission"
  type="text"
  name="commission"
  value="{{ old('commission', number_format($coachFee->amount, 1, '.', '')) }}"
  inputmode="decimal"
  autocomplete="off"
  placeholder="0.0"
  maxlength="5"
  oninput="fixCommission(this)"
  onblur="clampCommission(this)"
>

        <span class="suffix">%</span>
      </div>

      @error('commission')
        <div class="error">{{ $message }}</div>
      @enderror
    </div>

    {{-- ✅ SHOW SOCIAL --}}
    <div class="field checkbox-row">
      <label class="checkbox">
        <input type="checkbox"
               name="show_social"
               value="1"
               @checked(old('show_social', $showSocial ? 1 : 0))>
        <span>Show Social Media Profiles</span>
      </label>
    </div>

    {{-- ✅ DEFAULT COVER IMAGE UPLOADER --}}
    <div class="field">
      @include('superadmin.website._image_uploader', [
        'name'        => 'default_cover',
        'id'          => 'default_cover',
        'labelText'   => 'Default Cover Photo',
        'currentPath' => $defaultCover,
        'deleteRoute' => route('superadmin.settings.trainer.default_cover.delete'),
        'removeField' => 'remove_default_cover',
      ])

      @error('default_cover')
        <div class="error">{{ $message }}</div>
      @enderror
    </div>

    {{-- ✅ SUBMIT --}}
    <div class="form-foot">
      <button class="btn bg-black" type="submit">Update</button>
    </div>

  </form>
</section>
@endsection


@push('scripts')
<script>
  function fixCommission(el){
    const start = el.selectionStart;
    const beforeLen = el.value.length;

    // keep only digits + one dot
    let v = el.value.replace(/[^\d.]/g, '');
    v = v.replace(/(\..*)\./g, '$1');

    // limit to 1 decimal
    if (v.includes('.')) {
      const [a,b] = v.split('.');
      v = a + '.' + (b ?? '').slice(0,1);
    }

    el.value = v;

    // restore caret position
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

    el.value = n.toFixed(1);
  }
</script>
@endpush
