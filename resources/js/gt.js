(function () {
    const COOKIE_NAME = 'googtrans';
    const COOKIE_VALUE = (lang) => `/auto/${lang}`;
    const COOKIE_PATHS = ['/', window.location.pathname]; // set at root
    const RTL_CODES = ['ar','fa','ur','he'];
  
    // read cookie helper
    function readCookie(name) {
      const m = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'));
      return m ? decodeURIComponent(m[1]) : null;
    }
  
    function writeCookie(name, value, days) {
      const exp = new Date();
      exp.setTime(exp.getTime() + (days*24*60*60*1000));
      COOKIE_PATHS.forEach(p => {
        document.cookie = `${name}=${encodeURIComponent(value)}; expires=${exp.toUTCString()}; path=${p}; domain=${location.hostname}; SameSite=Lax`;
      });
    }
  
    function currentLang() {
      const c = readCookie(COOKIE_NAME); // "/auto/fr"
      if (c && /^\/[^/]+\/([^/]+)$/.test(c)) return c.split('/').pop();
      try { return localStorage.getItem('gt_lang') || 'en'; } catch (_) { return 'en'; }
    }
  
    function setLang(lang) {
      // store cookie so Google widget picks it
      writeCookie(COOKIE_NAME, COOKIE_VALUE(lang), 365);
      try { localStorage.setItem('gt_lang', lang); } catch (_) {}
  
      // Update label instantly
      const btnLabel = document.getElementById('gtCurrentLabel');
      const picked = document.querySelector(`.lang-pick[data-locale="${lang}"]`);
      if (btnLabel && picked) btnLabel.textContent = picked.dataset.label;
  
      // Checkmarks
      document.querySelectorAll('.lang-pick i.bi-check2').forEach(i => i.remove());
      if (picked) {
        const icon = document.createElement('i');
        icon.className = 'bi bi-check2';
        picked.appendChild(icon);
      }
  
      // Handle RTL/LTR on the fly
      const html = document.getElementById('htmlRoot') || document.documentElement;
      html.setAttribute('dir', RTL_CODES.includes(lang) ? 'rtl' : 'ltr');
  
      // A short delay helps ensure Google’s gadget sees the cookie before reload
      setTimeout(() => location.reload(), 150);
    }
  
    // Hook clicks
    document.addEventListener('click', function (e) {
      const btn = e.target.closest('.lang-pick');
      if (!btn) return;
      e.preventDefault();
      const code = btn.getAttribute('data-locale');
      if (!code) return;
      setLang(code);
    });
  
    // Simple client-side filter
    document.addEventListener('input', function (e) {
      if (e.target && e.target.id === 'langSearch') {
        const q = e.target.value.trim().toLowerCase();
        document.querySelectorAll('#gtLangList .lang-pick').forEach(b => {
          const label = (b.getAttribute('data-label') || '').toLowerCase();
          b.style.display = label.includes(q) ? '' : 'none';
        });
      }
    });
  
    // On first paint, align UI with cookie/localStorage
    document.addEventListener('DOMContentLoaded', function () {
      const lang = currentLang();
      const picked = document.querySelector(`.lang-pick[data-locale="${lang}"]`);
      if (picked) {
        const btnLabel = document.getElementById('gtCurrentLabel');
        if (btnLabel) btnLabel.textContent = picked.dataset.label;
        // mark check
        const icon = document.createElement('i');
        icon.className = 'bi bi-check2';
        picked.appendChild(icon);
        // set dir
        const html = document.getElementById('htmlRoot') || document.documentElement;
        html.setAttribute('dir', RTL_CODES.includes(lang) ? 'rtl' : 'ltr');
      }
    });
  })();
  