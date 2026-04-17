{{-- ✅ Global helper (AJAX + normal pages) --}}
<script>
  window.zToast = function (msg, type = 'ok') {
    if (typeof Toastify === 'undefined') {
      console.warn('Toastify not loaded');
      return;
    }

    Toastify({
      text: msg,
      duration: 3000,
      gravity: "top",
      position: "right",
      close: true,
      stopOnFocus: true,
      backgroundColor: (type === 'ok') ? "#16a34a" : "#dc2626",
    }).showToast();
  };
</script>

{{-- ✅ Flash session toasts (use same helper + wait for DOM) --}}
@if(session('ok'))
<script>
  document.addEventListener("DOMContentLoaded", function () {
    window.zToast(@json(session('ok')), 'ok');
  });
</script>
@endif

@if(session('error'))
<script>
  document.addEventListener("DOMContentLoaded", function () {
    window.zToast(@json(session('error')), 'error');
  });
</script>
@endif
