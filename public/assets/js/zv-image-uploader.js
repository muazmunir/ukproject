(() => {
  const fmtSize = (bytes) => {
    if (!bytes && bytes !== 0) return '';
    const units = ['B','KB','MB','GB'];
    let i = 0, n = bytes;
    while (n >= 1024 && i < units.length-1) { n /= 1024; i++; }
    return `${n.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
  };

  document.querySelectorAll('[data-uploader]').forEach((root) => {
    const input = root.querySelector('[data-file]');
    const drop = root.querySelector('[data-drop]');
    const filename = root.querySelector('[data-filename]');
    const filesize = root.querySelector('[data-filesize]');
    const previewWrap = root.querySelector('[data-preview-wrap]');
    const previewImg = root.querySelector('[data-preview]');
    const zoomBtn = root.querySelector('[data-zoom]');
    const clearBtn = root.querySelector('[data-clear-selected]');
    const removeFlag = root.querySelector('[data-remove-flag]');

    const lightbox = root.querySelector('[data-lightbox]');
    const lbImg = root.querySelector('[data-lightbox-img]');

    const deleteModal = root.querySelector('[data-delete-modal]');
    const openDelete = root.querySelector('[data-open-delete]');

    const hasCurrent = root.getAttribute('data-has-current') === '1';
    const currentUrl = root.getAttribute('data-current-url');

    const setPreviewUrl = (url) => {
      if (!url) {
        previewWrap?.setAttribute('hidden','hidden');
        return;
      }
      previewImg.src = url;
      previewWrap?.removeAttribute('hidden');
    };

    const resetSelected = () => {
      input.value = '';
      clearBtn?.setAttribute('hidden','hidden');
      filesize.textContent = '';
      filename.textContent = hasCurrent ? 'Current image loaded' : 'Nothing selected';

      // if we removed current via update form
      if (removeFlag) removeFlag.value = '0';

      // restore current preview if exists
      if (hasCurrent && currentUrl) setPreviewUrl(currentUrl);
      else setPreviewUrl('');
    };

    const onPick = (file) => {
      if (!file) return;
      filename.textContent = file.name;
      filesize.textContent = fmtSize(file.size);

      const url = URL.createObjectURL(file);
      setPreviewUrl(url);

      clearBtn?.removeAttribute('hidden');

      // if user selects new file, ensure remove flag is off
      if (removeFlag) removeFlag.value = '0';
    };

    input?.addEventListener('change', (e) => onPick(e.target.files?.[0]));

    // drag & drop
    ['dragenter','dragover'].forEach(evt =>
      drop?.addEventListener(evt, (e) => { e.preventDefault(); drop.classList.add('is-drag'); })
    );
    ['dragleave','drop'].forEach(evt =>
      drop?.addEventListener(evt, (e) => { e.preventDefault(); drop.classList.remove('is-drag'); })
    );
    drop?.addEventListener('drop', (e) => {
      const f = e.dataTransfer?.files?.[0];
      if (!f) return;
      input.files = e.dataTransfer.files;
      onPick(f);
    });

    // clear selected (not saved yet)
    clearBtn?.addEventListener('click', () => resetSelected());

    // zoom/lightbox
    const openLightbox = () => {
      const src = previewImg?.src;
      if (!src) return;
      lbImg.src = src;
      lightbox.removeAttribute('hidden');
    };
    zoomBtn?.addEventListener('click', openLightbox);
    previewImg?.addEventListener('click', openLightbox);

    lightbox?.addEventListener('click', (e) => {
      if (e.target.matches('[data-lightbox-close], [data-lightbox], [data-lightbox__backdrop]')) {
        lightbox.setAttribute('hidden','hidden');
      }
    });
    root.querySelector('[data-lightbox-close]')?.addEventListener('click', () => lightbox.setAttribute('hidden','hidden'));

    // delete modal
    const closeModal = () => deleteModal?.setAttribute('hidden','hidden');
    openDelete?.addEventListener('click', () => deleteModal?.removeAttribute('hidden'));
    deleteModal?.querySelectorAll('[data-close]')?.forEach(btn => btn.addEventListener('click', closeModal));

    // Optional: if you want "Remove current (no route)" just set removeFlag=1 and clear preview:
    // (You can add another button if you prefer this style.)
  });
})();
