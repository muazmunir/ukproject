<div class="row row-cols-2 row-cols-sm-3 row-cols-lg-6 gy-3 zv-inspire-row">
    @foreach($items as $it)
      <div class="col">
        <a href="#" class="d-block text-decoration-none">
          <div class="fw-semibold text-dark">{{ $it['title'] }}</div>
          <div class="text-muted small">{{ $it['sub'] }}</div>
        </a>
      </div>
    @endforeach
  </div>
  