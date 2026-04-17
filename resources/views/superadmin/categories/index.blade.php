@extends('superadmin.layout')

@section('title','Categories')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin-categories.css') }}">
@endpush

@section('content')
 

  @php
    // current filters
    $q    = request('q', '');
    $per  = (int) request('per', $cats->perPage());
    $per  = in_array($per, [10,20,50,100], true) ? $per : 10;
  @endphp

  <section class="card">
    <div>
        <div class="card__title">Category List</div>
        <div class="muted text-capitalize text-center">Manage service categories (images, icons, status, order)</div>
      </div>
    <div class="card__head">
      

      <div class="actions">
        {{-- Per-page selector --}}
        <form method="get" class="per-form">
          <input type="hidden" name="q" value="{{ $q }}">
          <label class="per-label">Show
            <select name="per" onchange="this.form.submit()">
              @foreach([10,20,50,100] as $n)
                <option value="{{ $n }}" @selected($per==$n)>{{ $n }}</option>
              @endforeach
            </select>
            Entries
          </label>
        </form>

        {{-- Search --}}
     <form method="get" class="zv-search" role="search">
  <input type="hidden" name="per" value="{{ $per }}">

  <span class="zv-search__icon" aria-hidden="true">
    <i class="bi bi-search"></i>
  </span>

  <input class="zv-search__input"
         type="search"
         name="q"
         value="{{ $q }}"
         placeholder="Search Categories…">

  @if($q)
    <a class="zv-search__clear"
       href="{{ route('superadmin.categories.index', ['per'=>$per]) }}"

       title="Clear"
       aria-label="Clear">
      <i class="bi bi-x-lg"></i>
    </a>
  @endif
</form>


        <button class="btn btn-dark bg-black" data-open="#modalCreate">
          <i class="bi bi-plus-lg" aria-hidden="true"></i>

          Add Category
        </button>
      </div>
    </div>

    {{-- results summary --}}
   
    <div class="table-wrap">
      <table class="zv-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Description</th>
            <th>Cover</th>
            <th>Icon</th>
            <th>Sort</th>
            <th>Status</th>
            <th>Carousel</th>

            <th style="width:220px">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($cats as $c)
            <tr>
              <td>
                <div class="cell-title">
                  <strong>{{ $c->name }}</strong>
                  <div class="muted slug">/{{ $c->slug }}</div>
                </div>
              </td>

              <td class="truncate">{{ Str::limit((string)$c->description, 90) }}</td>

              <td>
                @if($c->cover_image)
                  <img class="thumb" src="{{ asset('storage/'.$c->cover_image) }}" alt="">
                @else
                  <span class="muted ico" title="No cover"><svg><use href="#i-img"/></svg></span>
                @endif
              </td>

              <td>
                @if($c->icon_path)
                  <img class="icon" src="{{ asset('storage/'.$c->icon_path) }}" alt="">
                @else
                  <span class="muted ico" title="No icon"><svg><use href="#i-img"/></svg></span>
                @endif
              </td>

              <td>{{ (int)$c->sort_order }}</td>

              <td>
                @if($c->is_active)
                  <span class="pill ok"><i class="bi bi-check2" aria-hidden="true"></i>Active</span>
                @else
                  <span class="pill"><i class="bi bi-dot" aria-hidden="true"></i>Inactive</span>
                @endif
              </td>

              <td>
  @if($c->show_in_scrollbar)
    <span class="pill ok"><i class="bi bi-check2" aria-hidden="true"></i>
Yes</span>
  @else
    <span class="pill"><i class="bi bi-dot" aria-hidden="true"></i>
No</span>
  @endif
</td>


              <td>
                <div class="row-actions compact">
                  {{-- Edit --}}
                 <button class="btn icon ghost" data-open="#modalEdit"
        data-id="{{ $c->id }}"
        data-name="{{ $c->name }}"
        data-desc="{{ e($c->description) }}"
        data-sort="{{ (int)$c->sort_order }}"
        data-active="{{ $c->is_active ? 1 : 0 }}"
        data-scrollbar="{{ $c->show_in_scrollbar ? 1 : 0 }}"

        data-cover="{{ $c->cover_image ? asset('storage/'.$c->cover_image) : '' }}"
        data-icon="{{ $c->icon_path ? asset('storage/'.$c->icon_path) : '' }}"

        title="Edit" aria-label="Edit">

                    <i class="bi bi-pencil-square" aria-hidden="true"></i>

                    <span class="sr-only">Edit</span>
                  </button>
              
                  {{-- Activate / Pause --}}
                  @if($c->is_active)
                    <form method="post" action="{{ route('superadmin.categories.deactivate', $c) }}" class="inline" title="Pause" aria-label="Pause">
                      @csrf
                      <button class="btn icon ghost" type="submit">
                        <i class="bi bi-pause-fill" aria-hidden="true"></i>

                        <span class="sr-only">Pause</span>
                      </button>
                    </form>
                  @else
                    <form method="post" action="{{ route('superadmin.categories.activate', $c) }}" class="inline" title="Activate" aria-label="Activate">
                      @csrf
                      <button class="btn icon ghost" type="submit">
                       <i class="bi bi-play-fill" aria-hidden="true"></i>

                        <span class="sr-only">Activate</span>
                      </button>
                    </form>
                  @endif
              
                  {{-- Delete --}}
                  <button class="btn icon danger" data-open="#modalDelete"
                          data-id="{{ $c->id }}" data-name="{{ $c->name }}"
                          title="Delete" aria-label="Delete">
                    <i class="bi bi-trash3" aria-hidden="true"></i>

                    <span class="sr-only">Delete</span>
                  </button>
                </div>
              </td>
              
            </tr>
          @empty
            <tr><td colspan="7" class="muted ta-center">No Categories Found.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    {{-- Laravel pagination (preserves q & per) --}}
    <div class="pager">
  {{ $cats->links() }}
</div>

  </section>

  {{-- Create Modal --}}
  <div id="modalCreate" class="modal" aria-hidden="true">
    <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="createTitle">
      <form method="post" action="{{ route('superadmin.categories.store') }}" enctype="multipart/form-data" class="modal__card">
        @csrf
        <div class="modal__head">
          @if ($errors->any())
  <div class="alert alert-danger" style="margin:0 1rem 1rem;">
    <ul class="mb-0">
      @foreach($errors->all() as $err)
        <li>{{ $err }}</li>
      @endforeach
    </ul>
  </div>
@endif

          <div class="title" id="createTitle">Add Category</div>
          <button type="button" class="x" data-close aria-label="Close">×</button>
        </div>

        <div class="modal__body grid2">
          <label>Name
           <input type="text" name="name" value="{{ old('name') }}" required>
          </label>
          <label>Sort Order
            <input type="number" name="sort_order" min="0" value="{{ old('sort_order', 0) }}">
          </label>
          <label class="col-span">Description
            <textarea name="description" rows="3" maxlength="1000">{{ old('description') }}</textarea>

          </label>
          <label>Cover Image (1350×350 Suggested)
            <input type="file" name="cover_image" accept="image/*">
          </label>
          <label>Icon (30×30+)
           <input type="file" name="icon_path" accept="image/*" data-icon-crop>

          </label>
          <div class="d-flex align-items-center justify-content-between">
 
            <label class="switch col-span">
              <input type="checkbox" name="is_active" {{ old('is_active', 'on') ? 'checked' : '' }}>
              <i></i> <span>Active</span>
            </label>
           
            <div>
            <label class="switch col-span">
              <input type="checkbox" name="show_in_scrollbar" {{ old('show_in_scrollbar', 'on') ? 'checked' : '' }}>
              <i></i> <span>Show In Carousel</span>
            </label>
             </div>
          </div>

        </div>


        <div class="modal__foot">
          <button type="button" class="btn ghost" data-close>Cancel</button>
          <button type="submit" class="btn bg-black">Create</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Edit Modal --}}
  <div id="modalEdit" class="modal" aria-hidden="true" data-update-url-template="{{ route('superadmin.categories.update', ['category' => '__ID__']) }}">
    <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="editTitle">
      <form method="post" id="editForm" enctype="multipart/form-data" class="modal__card">
        @csrf @method('PUT')
        <div class="modal__head">
          <div class="title text-center" id="editTitle">Edit Category</div>
          <button type="button" class="x" data-close aria-label="Close">×</button>
        </div>

        <div class="modal__body grid2">
          <label>Name
            <input type="text" name="name" id="e_name" required>
          </label>
          <label>Sort Order
            <input type="number" name="sort_order" id="e_sort" min="0">
          </label>
          <label class="col-span">Description
            <textarea name="description" id="e_desc" rows="3" maxlength="1000"></textarea>
          </label>
          {{-- Cover preview + remove --}}
<div class="media-field">
  <div class="media-head">
    <div class="media-title">Cover</div>

    <button type="button" class="btn icon ghost media-remove-btn" id="btnRemoveCover" title="Remove cover">
      <i class="bi bi-trash3" aria-hidden="true"></i>
      <span class="sr-only">Remove cover</span>
    </button>
  </div>

  <div class="media-preview" id="coverPreviewWrap">
    <img id="coverPreview" src="" alt="" class="media-img">
    <div class="media-empty" id="coverEmpty">
     <span class="muted ico" title="No cover"><i class="bi bi-image" aria-hidden="true"></i></span>

      <span class="muted">No cover uploaded</span>
    </div>
  </div>

  <input type="hidden" name="remove_cover" id="remove_cover" value="0">

  <label class="media-upload">
    Replace Cover
    <input type="file" name="cover_image" accept=".jpg,.jpeg,.png,.webp">
  </label>
</div>

{{-- Icon preview + remove --}}
<div class="media-field">
  <div class="media-head">
    <div class="media-title">Icon</div>

    <button type="button" class="btn icon ghost media-remove-btn" id="btnRemoveIcon" title="Remove icon">
      <i class="bi bi-trash3" aria-hidden="true"></i>

      <span class="sr-only">Remove icon</span>
    </button>
  </div>

  <div class="media-preview is-icon" id="iconPreviewWrap">
    <img id="iconPreview" src="" alt="" class="media-img is-icon">
    <div class="media-empty" id="iconEmpty">
      <span class="muted ico"><svg><use href="#i-img"/></svg></span>
      <span class="muted">No icon uploaded</span>
    </div>
  </div>

  <input type="hidden" name="remove_icon" id="remove_icon" value="0">

  <label class="media-upload">
    Replace Icon
    <input type="file" name="icon_path" accept=".jpg,.jpeg,.png,.webp,.svg,.svgz" data-icon-crop>

  </label>
</div>

          <div class="d-flex align-items-center justify-content-between">
            <div>
          <label class="switch col-span">
            <input type="checkbox" name="is_active" id="e_active">
            <i></i> <span>Active</span>
          </label>
            </div>
            <div>
          <label class="switch col-span">
  <input type="checkbox" name="show_in_scrollbar" id="e_scrollbar">
  <i></i> <span>Show In Carousel</span>
</label>
</div>
</div>

        </div>

        <div class="modal__foot">
          <button type="button" class="btn ghost" data-close>Cancel</button>
          <button type="submit" class="btn bg-black">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Delete Modal --}}
  <div id="modalDelete" class="modal" aria-hidden="true" data-destroy-url-base="{{ url('superadmin/categories') }}">
    <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="deleteTitle">
      <form method="post" id="delForm" class="modal__card">
        @csrf @method('DELETE')
        <div class="modal__head">
          <div class="title" id="deleteTitle">Delete Category</div>
          <button type="button" class="x" data-close aria-label="Close">×</button>
        </div>
        <div class="modal__body">
          <p class="text-capitalize">Are you sure you want to delete <strong id="delName">this category</strong>? This action cannot be undone.</p>
        </div>
        <div class="modal__foot">
          <button type="button" class="btn ghost" data-close>Cancel</button>
          <button type="submit" class="btn danger">Delete</button>
        </div>
      </form>
    </div>
  </div>



 <div id="iconCropModal" class="modal" aria-hidden="true">
  <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="iconCropTitle">
    <div class="modal__card">
      <div class="modal__head">
        <div class="title" id="iconCropTitle">Adjust your icon</div>
        <button type="button" class="x" data-close aria-label="Close">×</button>
      </div>

      <div class="modal__body">
        <div class="zv-cropper-wrap" style="max-height:60vh; overflow:hidden; border-radius:12px;">
          <img id="icon-cropper-image" src="" alt="Icon crop preview" style="max-width:100%; display:block;">
        </div>

        <small class="text-muted d-block mt-2">
          Drag to reposition, scroll to zoom in/out.
        </small>
      </div>

      <div class="modal__foot" style="display:flex; justify-content:space-between;">
        <button type="button" class="btn ghost" data-close>Cancel</button>
        <button type="button" class="btn bg-black" id="icon-crop-save">Use this icon</button>
      </div>
    </div>
  </div>
</div>


@endsection



@push('scripts')
<script>
  // ---------- Modal logic + focus trap ----------
  const body = document.body;

  function openModal(el){ if(!el) return;
    el.classList.add('open'); el.setAttribute('aria-hidden','false'); body.classList.add('modal-open');
    const f = el.querySelector('button,[href],input,select,textarea,[tabindex]:not([tabindex="-1"])'); f && f.focus();
    const trap = (e)=>{
      const focusables = [...el.querySelectorAll('button,[href],input,select,textarea,[tabindex]:not([tabindex="-1"])')].filter(x=>!x.disabled && x.offsetParent !== null);
      if(!focusables.length) return;
      const first=focusables[0], last=focusables[focusables.length-1];
      if(e.key==='Tab'){
        if(e.shiftKey && document.activeElement===first){ last.focus(); e.preventDefault(); }
        else if(!e.shiftKey && document.activeElement===last){ first.focus(); e.preventDefault(); }
      }
      if(e.key==='Escape') closeModal(el);
    };
    el._trap = trap; document.addEventListener('keydown', trap);
  }
  function closeModal(el){ if(!el) return;
    el.classList.remove('open'); el.setAttribute('aria-hidden','true'); body.classList.remove('modal-open');
    document.removeEventListener('keydown', el._trap || (()=>{}));
  }

  // open handlers + payload populate
  document.querySelectorAll('[data-open]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const modal = document.querySelector(btn.getAttribute('data-open'));
      openModal(modal);

   if (modal?.id === 'modalEdit') {
  const id   = btn.dataset.id;
  const urlT = modal.getAttribute('data-update-url-template');
  document.querySelector('#editForm').action = urlT.replace('__ID__', id);

  // inputs
  const coverInput = modal.querySelector('input[name="cover_image"]');
  const iconInput  = modal.querySelector('input[name="icon_path"]');

  // clear file inputs every open (so "No file chosen")
  if (coverInput) coverInput.value = '';
  if (iconInput) iconInput.value = '';

  // reset remove flags each time modal opens
  const removeCover = modal.querySelector('#remove_cover');
  const removeIcon  = modal.querySelector('#remove_icon');
  if (removeCover) removeCover.value = '0';
  if (removeIcon) removeIcon.value = '0';

  // text fields
  modal.querySelector('#e_name').value = btn.dataset.name || '';
  modal.querySelector('#e_desc').value = btn.dataset.desc || '';
  modal.querySelector('#e_sort').value = btn.dataset.sort || 0;
  modal.querySelector('#e_active').checked = btn.dataset.active === '1';
  modal.querySelector('#e_scrollbar').checked = btn.dataset.scrollbar === '1';

  // previews
  const coverUrl = btn.dataset.cover || '';
  const iconUrl  = btn.dataset.icon || '';

  const coverImg   = modal.querySelector('#coverPreview');
  const coverEmpty = modal.querySelector('#coverEmpty');
  const iconImg    = modal.querySelector('#iconPreview');
  const iconEmpty  = modal.querySelector('#iconEmpty');

  // cover show/hide
  if (coverUrl) {
    coverImg.src = coverUrl;
    coverImg.style.display = 'block';
    coverEmpty.style.display = 'none';
  } else {
    coverImg.src = '';
    coverImg.style.display = 'none';
    coverEmpty.style.display = 'flex';
  }

  // icon show/hide
  if (iconUrl) {
    iconImg.src = iconUrl;
    iconImg.style.display = 'block';
    iconEmpty.style.display = 'none';
  } else {
    iconImg.src = '';
    iconImg.style.display = 'none';
    iconEmpty.style.display = 'flex';
  }

  // remove buttons
  const btnRemoveCover = modal.querySelector('#btnRemoveCover');
  const btnRemoveIcon  = modal.querySelector('#btnRemoveIcon');

  if (btnRemoveCover) {
    btnRemoveCover.onclick = () => {
      if (removeCover) removeCover.value = '1';
      if (coverInput) coverInput.value = '';   // ✅ resets "No file chosen"

      coverImg.src = '';
      coverImg.style.display = 'none';
      coverEmpty.style.display = 'flex';
    };
  }

  if (btnRemoveIcon) {
    btnRemoveIcon.onclick = () => {
      if (removeIcon) removeIcon.value = '1';
      if (iconInput) iconInput.value = '';     // ✅ resets "No file chosen"

      iconImg.src = '';
      iconImg.style.display = 'none';
      iconEmpty.style.display = 'flex';
    };
  }
}



      if (modal?.id === 'modalDelete') {
        const id = btn.dataset.id;
        const base = modal.getAttribute('data-destroy-url-base');
        document.querySelector('#delForm').action = `${base}/${id}`;
        document.querySelector('#delName').textContent = btn.dataset.name || 'this category';
      }
    });
  });

  // close on X, Cancel, backdrop
  document.querySelectorAll('[data-close]').forEach(x=> x.addEventListener('click', ()=> closeModal(x.closest('.modal'))));
  document.querySelectorAll('.modal').forEach(m=> m.addEventListener('click',(e)=>{ if(e.target===m) closeModal(m); }));
  document.addEventListener('keydown',(e)=>{ if(e.key==='Escape'){ const open=document.querySelector('.modal.open'); open && closeModal(open);} });
</script>

<script>
  // ---------- Live preview on file select (Edit Modal) ----------

  function previewImage(input, imgEl, emptyEl, removeFlagEl) {
    if (!input || !input.files || !input.files[0]) return;

    const file = input.files[0];

    // cancel removal if user picks new file
    if (removeFlagEl) removeFlagEl.value = '0';

    // SVG preview
    if (file.type === 'image/svg+xml') {
      const reader = new FileReader();
      reader.onload = e => {
        imgEl.src = e.target.result;
        imgEl.style.display = 'block';
        emptyEl.style.display = 'none';
      };
      reader.readAsDataURL(file);
      return;
    }

    // Raster preview
    const url = URL.createObjectURL(file);
    imgEl.src = url;
    imgEl.style.display = 'block';
    emptyEl.style.display = 'none';

    // cleanup blob URL after load
    imgEl.onload = () => URL.revokeObjectURL(url);
  }

  // Cover preview
  document.addEventListener('change', function (e) {
    if (e.target.matches('input[name="cover_image"]')) {
      previewImage(
        e.target,
        document.querySelector('#coverPreview'),
        document.querySelector('#coverEmpty'),
        document.querySelector('#remove_cover')
      );
    }

    // Icon preview
    if (e.target.matches('input[name="icon_path"]')) {
      previewImage(
        e.target,
        document.querySelector('#iconPreview'),
        document.querySelector('#iconEmpty'),
        document.querySelector('#remove_icon')
      );
    }
  });
</script>



<script>
(() => {
  const modalEl = document.getElementById('iconCropModal');
  const imgEl   = document.getElementById('icon-cropper-image');
  const saveBtn = document.getElementById('icon-crop-save');

  if (!modalEl || !imgEl || !saveBtn) return;

  let cropper = null;
  let activeInput = null;
  let objectUrl = null;

  const destroyCropper = () => {
    if (cropper) { cropper.destroy(); cropper = null; }
    if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
    imgEl.removeAttribute('src');
    activeInput = null;
  };

  // when your modal closes (via [data-close] / backdrop / esc), cleanup
  const closeAndCleanup = () => { destroyCropper(); closeModal(modalEl); };

  // hook close buttons inside crop modal
  modalEl.querySelectorAll('[data-close]').forEach(btn => {
    btn.addEventListener('click', (e) => { e.preventDefault(); closeAndCleanup(); });
  });

  // open cropper when user selects file
  document.addEventListener('change', (e) => {
    const input = e.target;
    if (!input.matches('input[type="file"][data-icon-crop]')) return;

    // ✅ skip re-opening when we programmatically trigger change after crop
    if (input.dataset.skipCrop === '1') { input.dataset.skipCrop = '0'; return; }

    const file = input.files?.[0];
    if (!file) return;

    // skip SVG crop
    if (file.type === 'image/svg+xml') return;

    activeInput = input;

    // open modal (your custom modal)
    openModal(modalEl);

    // load image
    objectUrl = URL.createObjectURL(file);
    imgEl.src = objectUrl;

    imgEl.onload = () => {
      if (cropper) cropper.destroy();

      cropper = new Cropper(imgEl, {
        aspectRatio: 1,
        viewMode: 1,
        dragMode: 'move',
        autoCropArea: 1,
        responsive: true,
        background: false,
        zoomOnWheel: true,
        movable: true,
        scalable: false,
        rotatable: false,
        guides: true,
        center: true,
      });
    };
  });

  saveBtn.addEventListener('click', () => {
    if (!cropper || !activeInput) return;

    const canvas = cropper.getCroppedCanvas({
      width: 256,
      height: 256,
      imageSmoothingEnabled: true,
      imageSmoothingQuality: 'high'
    });

    canvas.toBlob((blob) => {
      if (!blob) return;

      const outFile = new File([blob], `icon-${Date.now()}.png`, { type: 'image/png' });
      const dt = new DataTransfer();
      dt.items.add(outFile);

      // replace file input with cropped file
      activeInput.files = dt.files;

      // ✅ update preview without reopening crop modal
      activeInput.dataset.skipCrop = '1';
      activeInput.dispatchEvent(new Event('change', { bubbles: true }));

      // close and cleanup
      closeAndCleanup();
    }, 'image/png', 0.92);
  });
})();
</script>


@endpush
