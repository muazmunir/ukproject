<!DOCTYPE html>
@php
  $locales = config('locales') ?? [];
  $curr    = app()->getLocale();
  // $locales is assumed like: 'en' => ['English', false]
  $rtl     = isset($locales[$curr]) && isset($locales[$curr][1]) ? (bool)$locales[$curr][1] : false;
@endphp
<html lang="{{ $curr }}" id="htmlRoot" dir="{{ $rtl ? 'rtl' : 'ltr' }}">


<head>
    <meta charset="utf-8">  {{-- fixed (was "utaf-8") --}}
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.esm-importmap')
    <title>@yield('title', 'ZAIVIAS')</title>
    

  
    {{-- CSS libs --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
   <link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jquery-nice-select/css/nice-select.css">
  
    
    {{-- Your CSS --}}
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    {{-- <link rel="stylesheet" href="{{ asset('assets/css/hero.css') }}"> --}}
    <link rel="stylesheet" href="{{ asset('assets/css/client.css') }}">

    {{-- chat header --}}

      
    {{-- <link rel="stylesheet" href="{{ asset('assets/css/chat-widget.css') }}"> --}}
    

    
    {{-- if "../app/app.css" is accidental, remove it or point to a real file --}}
    {{-- <link rel="stylesheet" href="../app/app.css"> --}}
  
    <style>
        /* nuke the banner in all cases */
        iframe.goog-te-banner-frame,
        .goog-te-banner-frame,
        .goog-te-balloon-frame,
        #goog-gt-tt,
        .goog-tooltip,
        .goog-te-spinner-pos,
        .goog-te-gadget,
        .goog-te-gadget-icon {
          display: none !important;
          visibility: hidden !important;
          height: 0 !important;
          width: 0 !important;
          opacity: 0 !important;
        }
      
        /* Google sometimes pushes the page down by setting top on html/body */
        html, body { top: 0 !important; }
        .skiptranslate { display: none !important; } /* container Google adds */




        

    


      </style>
      
  <!-- Toastify CSS -->
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">

<!-- Toastify JS -->
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>



    {{-- Load libs that need <head> --}}
    {{-- <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script> --}}

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery-nice-select/js/jquery.nice-select.min.js" defer></script>
    {{-- IMPORTANT: remove these anti-translate flags so Google can translate --}}
    {{-- <meta name="google" content="notranslate"> --}}
    {{-- <html ... translate="no">  <-- already removed above --}}
    
    
    @stack('styles')
    
  </head>
  
<body class="bg-light">

  @php
      $user     = auth()->user();
      // Adjust this based on how you store roles (role / type / is_coach, etc.)
      $role     = $user->role ?? null;
      $canChatSupport = $user && in_array($role, ['coach', 'client']);
    @endphp
   
    {{-- NAVBAR --}}
    @include('partials.navbar')
    



    <main class="">
        {{-- <div class="container-fluid"> --}}
            @yield('content')
        </div>
    </main>

     @include('partials.footer')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
   

   

   
       

      <!-- Hidden Google Translate mount point -->
<!-- Hidden mount for Google widget -->
<!-- Hidden Google Translate mount -->
<div id="google_translate_element" style="display:none;"></div>

<script>
  // Build a safe LOCALES map only with rtl flags:
  // expected config('locales'): code => [label, isRtl]
  const LOCALES = @json(collect(config('locales') ?? [])->mapWithKeys(
    fn($v,$k)=>[$k=>['rtl'=> ((is_array($v)&&isset($v[1])) ? (bool)$v[1] : false)]]
  ));
</script>

<script>
  // ---------------- Cookie helpers (Google reads "googtrans") ---------------
  function setGoogTransCookie(target) {
    const val = `googtrans=${encodeURIComponent('/auto/' + target)}; path=/`;
    // Set at path=/ for current host
    document.cookie = val;

    // Also set with dot-domain on real domains (avoid on localhost / IPs)
    const h = location.hostname;
    if (/\./.test(h) && !/^\d{1,3}(\.\d{1,3}){3}$/.test(h)) {
      document.cookie = val + `; domain=.${h}`;
    }
  }
  function getGoogTransCookie() {
    const m = document.cookie.match(/(?:^|;\s*)googtrans=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : '';
  }
  function clearGoogTransCookie() {
    const expires = 'expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
    document.cookie = `googtrans=; ${expires}`;
    const h = location.hostname;
    if (/\./.test(h) && !/^\d{1,3}(\.\d{1,3}){3}$/.test(h)) {
      document.cookie = `googtrans=; ${expires}; domain=.${h}`;
    }
  }

  // ---------------- Apply dir/lang to <html> --------------------------------
  function setHtmlLangDir(code) {
    const html = document.getElementById('htmlRoot') || document.documentElement;
    html.setAttribute('lang', code || 'en');
    const rtl = !!(LOCALES[code] && LOCALES[code].rtl);
    html.setAttribute('dir', rtl ? 'rtl' : 'ltr');
  }

  // ---------------- Hide Google banner repeatedly ---------------------------
  function hideGTBanner() {
    const frame = document.querySelector('iframe.goog-te-banner-frame');
    if (frame) frame.style.display = 'none';
    document.documentElement.style.top = '0px';
    document.body.style.top = '0px';
  }
  (function keepHidingBanner(){
    let tries = 0;
    const t = setInterval(() => { hideGTBanner(); if (++tries > 30) clearInterval(t); }, 300);
  })();

  // ---------------- Google widget init (kept hidden) ------------------------
  window.googleTranslateElementInit = function () {
    new google.translate.TranslateElement({
      pageLanguage: '{{ $curr }}', // your base language from Laravel
      autoDisplay: false
    }, 'google_translate_element');
  };
</script>
<script src="https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit" defer></script>

<script>
  // ---------------- First load: default to English if nothing set -----------
  (function initDefaultLanguage(){
    const cookie = getGoogTransCookie(); // like "/auto/fr"
    if (!cookie) {
      setGoogTransCookie('en');
      setHtmlLangDir('en');
      if (!sessionStorage.getItem('gt-initialized')) {
        sessionStorage.setItem('gt-initialized', '1');
        location.reload(); // ensure Google sees cookie
      }
    } else {
      const code = cookie.split('/').pop();
      if (code) setHtmlLangDir(code);
    }
  })();

  // ---------------- Hook your dropdown (.lang-pick) -------------------------
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.lang-pick');
    if (!btn) return;

    const target = btn.dataset.locale; // "en","fr","ar"
    if (!target) return;

    setGoogTransCookie(target);
    setHtmlLangDir(target);

    // If widget already loaded, rebuild quickly; else reload (most reliable)
    if (window.google && window.google.translate && window.google.translate.TranslateElement) {
      const mount = document.getElementById('google_translate_element');
      if (mount) mount.innerHTML = '';
      new google.translate.TranslateElement({ pageLanguage: '{{ $curr }}', autoDisplay: false }, 'google_translate_element');
      hideGTBanner();
      // optional: refresh the page to fully re-run translations
      setTimeout(()=>location.reload(), 120);
    } else {
      location.reload();
    }
  });
</script>

<script>
    (function removeGoogleBanner() {
      const removeBanner = () => {
        const iframe = document.querySelector('iframe.goog-te-banner-frame');
        if (iframe) iframe.remove(); // remove from DOM completely
        document.body.style.top = '0px';
        document.documentElement.style.top = '0px';
      };
    
      // Run immediately
      removeBanner();
    
      // Keep removing if Google re-adds it
      const observer = new MutationObserver(removeBanner);
      observer.observe(document.body, { childList: true, subtree: true });
    
      // Safety interval for older browsers
      let tries = 0;
      const interval = setInterval(() => {
        removeBanner();
        if (++tries > 50) clearInterval(interval);
      }, 300);
    })();
    </script>
    

        
      
  



    






    
    
        
    {{-- ... all your content ... --}}

    {{-- Floating support chat widget --}}
   




    
      
    <script type="module" src="{{ asset('js/coach_calendar.js') }}"></script>
    
@auth
<script>
(function () {
  if (!('Intl' in window) || !Intl.DateTimeFormat) return;

  const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
  if (!tz) return;

  // Avoid spamming: keep last sent value in sessionStorage (optional)
  const KEY = 'lastSentTimezone';
  if (sessionStorage.getItem(KEY) === tz) return;

  fetch('{{ route('me.timezone.update') }}', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': '{{ csrf_token() }}',
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json'
    },
    body: JSON.stringify({ timezone: tz })
  })
  .then(r => r.json())
  .then(data => {
    console.log('Timezone update:', data);
    sessionStorage.setItem(KEY, tz);
  })
  .catch(err => console.error('Timezone update failed', err));
})();
</script>
@endauth

<script>
setInterval(() => {
    fetch('/api/visitor/ping', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({})
    });
}, 15000); // every 15 seconds
</script>


<script>
  window.zToast = function (msg, type = 'ok') {
    if (typeof Toastify === 'undefined') return;

    const bg = (type === 'ok') ? '#16a34a' : '#dc2626';

    Toastify({
      text: msg,
      duration: 3000,
      gravity: "top",
      position: "right",
      close: true,
      stopOnFocus: true,

      // ✅ override gradient background completely
      style: {
        background: bg,
      },
    }).showToast();
  };
</script>

@if(session('ok'))
<script>
  zToast(@json(session('ok')), 'ok');
</script>
@endif

@if(session('error'))
<script>
  zToast(@json(session('error')), 'error');
</script>
@endif


@if(session('error'))
<script>
  document.addEventListener("DOMContentLoaded", function () {
    zToast(@json(session('error')), 'error');
  });
</script>
@endif


     {{-- @include('partials.footer') --}}
    @stack('scripts')
    
</body>
</html>







