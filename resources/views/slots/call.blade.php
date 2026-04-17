@extends('layouts.app')

@section('content')
<div class="container py-3">
  <h5 class="mb-2">{{ __('Video Call') }}</h5>
  <div id="jaas-container" style="height:80vh;width:100%;border-radius:14px;overflow:hidden;"></div>
</div>

{{-- JaaS external API must be loaded from 8x8.vc/<APP_ID>/external_api.js --}}
<script src="https://8x8.vc/{{ $appId }}/external_api.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const api = new JitsiMeetExternalAPI("8x8.vc", {
    roomName: @json($roomName),
    parentNode: document.querySelector('#jaas-container'),
    jwt: @json($jwt),
  });
});
</script>
@endsection
