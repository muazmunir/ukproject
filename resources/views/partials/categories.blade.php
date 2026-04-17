<section class="categories-wrap py-4">
  <div class="container-fluid position-relative">

    <!-- Left arrow -->
    <button class="cat-nav cat-prev" type="button" aria-label="Scroll left">
      <i class="bi bi-chevron-left"></i>
    </button>

    <!-- Right arrow -->
    <button class="cat-nav cat-next" type="button" aria-label="Scroll right">
      <i class="bi bi-chevron-right"></i>
    </button>

    <div class="cat-scroller" id="catScroller" tabindex="0" aria-label="Browse categories">
      @foreach($categories as $cat)
        <a href="{{ route('services.index', ['category' => $cat->slug]) }}" class="cat-item">
          <span class="cat-ico">
            @php
              // Prefer icon_path, fall back to a default image if null
              $icon = $cat->icon_path
                ? asset('storage/' . $cat->icon_path)
                : asset('assets/cat/default.png');
            @endphp
            <img style="width:100%"; src="{{ $icon }}" alt="{{ $cat->name }}">
          </span>
          <span class="cat-txt">{{ $cat->name }}</span>
        </a>
      @endforeach
    </div>
  </div>
</section>

{{-- keep your existing JS for scroller (unchanged) --}}


  {{-- for smooth scroll   --}}

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const scroller = document.getElementById('catScroller');
      if (!scroller) return;
    
      const prevBtn = document.querySelector('.cat-prev');
      const nextBtn = document.querySelector('.cat-next');
    
      const cardWidth = () => {
        // scroll by ~ 3 cards each click
        const first = scroller.querySelector('.cat-item');
        return first ? first.getBoundingClientRect().width + 18 : 220;
      };
    
      function updateButtons(){
        const max = scroller.scrollWidth - scroller.clientWidth - 1;
        prevBtn.disabled = scroller.scrollLeft <= 0;
        nextBtn.disabled = scroller.scrollLeft >= max;
      }
    
      function smoothScroll(delta){
        scroller.scrollBy({ left: delta, behavior: 'smooth' });
        setTimeout(updateButtons, 300);
      }
    
      prevBtn.addEventListener('click', () => smoothScroll(-cardWidth()*3));
      nextBtn.addEventListener('click', () => smoothScroll(cardWidth()*3));
    
      // Mouse wheel horizontal
      scroller.addEventListener('wheel', (e) => {
        if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
          scroller.scrollLeft += e.deltaY;
          e.preventDefault();
          updateButtons();
        }
      }, { passive: false });
    
      // Drag to scroll (desktop)
      let isDown=false, startX=0, startLeft=0;
      scroller.addEventListener('mousedown', (e)=>{ isDown=true; startX=e.pageX; startLeft=scroller.scrollLeft; scroller.classList.add('dragging'); });
      window.addEventListener('mouseup', ()=>{ isDown=false; scroller.classList.remove('dragging'); });
      window.addEventListener('mousemove', (e)=>{ if(!isDown) return; scroller.scrollLeft = startLeft - (e.pageX - startX); });
    
      // Keyboard
      scroller.addEventListener('keydown', (e)=>{
        if(e.key === 'ArrowRight') { smoothScroll(cardWidth()); }
        if(e.key === 'ArrowLeft')  { smoothScroll(-cardWidth()); }
      });
    
      updateButtons();
    });
    </script>
    
  