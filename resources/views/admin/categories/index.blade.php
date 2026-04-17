@extends('layouts.admin')

@section('title','Categories')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin-categories.css') }}">
@endpush

@section('content')
  {{-- SVG sprite: crisp line icons --}}
  {{-- <svg xmlns="http://www.w3.org/2000/svg" style="display:none">
    <symbol id="i-search" viewBox="0 0 24 24"><path d="M21 21l-4.2-4.2M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
    <symbol id="i-plus"   viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
    <symbol id="i-edit"   viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.8 9.94l-3.75-3.75L3 17.25zM20.7 7.04l-2.34-2.34" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="i-play"   viewBox="0 0 24 24"><path d="M8 5v14l11-7-11-7z" fill="currentColor"/></symbol>
    <symbol id="i-pause"  viewBox="0 0 24 24"><path d="M6 5h4v14H6zm8 0h4v14h-4z" fill="currentColor"/></symbol>
    <symbol id="i-trash"  viewBox="0 0 24 24"><path d="M9 3h6l1 2h4v2H4V5h4l1-2zM9 9v10m6-10v10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
    <symbol id="i-ok"     viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="i-dot"    viewBox="0 0 24 24"><circle cx="12" cy="12" r="5" fill="currentColor"/></symbol>
    <symbol id="i-img"    viewBox="0 0 24 24"><path d="M21 19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" fill="none" stroke="currentColor" stroke-width="2"/><path d="m4 16 5-5 6 6 3-3 2 2" fill="none" stroke="currentColor" stroke-width="2"/></symbol>
  </svg> --}}

  @php
    // current filters
    $q    = request('q', '');
    $per  = (int) request('per', $cats->perPage());
    $per  = in_array($per, [10,20,50,100], true) ? $per : 10;
  @endphp

  <section class="card">
    <div class="card__head">
      <div>
        <div class="card__title">Category List</div>
        <div class="muted text-capitalize">Manage service categories (images, icons, status, order)</div>
      </div>

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
       href="{{ route('admin.categories.index', ['per'=>$per]) }}"
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
                    <form method="post" action="{{ route('admin.categories.deactivate', $c) }}" class="inline" title="Pause" aria-label="Pause">
                      @csrf
                      <button class="btn icon ghost" type="submit">
                        <i class="bi bi-pause-fill" aria-hidden="true"></i>

                        <span class="sr-only">Pause</span>
                      </button>
                    </form>
                  @else
                    <form method="post" action="{{ route('admin.categories.activate', $c) }}" class="inline" title="Activate" aria-label="Activate">
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
      <form method="post" action="{{ route('admin.categories.store') }}" enctype="multipart/form-data" class="modal__card">
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
            <input type="file" name="icon_path" accept="image/*">
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
  <div id="modalEdit" class="modal" aria-hidden="true" data-update-url-template="{{ route('admin.categories.update', ['category' => '__ID__']) }}">
    <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="editTitle">
      <form method="post" id="editForm" enctype="multipart/form-data" class="modal__card">
        @csrf @method('PUT')
        <div class="modal__head">
          <div class="title" id="editTitle">Edit Category</div>
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
    <input type="file" name="icon_path" accept=".jpg,.jpeg,.png,.webp,.svg,.svgz">
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
  <div id="modalDelete" class="modal" aria-hidden="true" data-destroy-url-base="{{ url('admin/categories') }}">
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

@endpush
