<section class="promo-banner my-4 my-sm-5">
    <div class="container">
      <a style="text-decoration:none;" href="{{ route('services.index') }}" class="promo-card d-block position-relative overflow-hidden">
        {{-- Background image --}}
        <img
          src="{{ asset('assets/promo/banner.jpg') }}"
          alt="Reach your fitness goals with our coaches"
          class="promo-bg"
          loading="lazy">
  
        {{-- Gradient overlay --}}
        <div class="promo-overlay"></div>
  
        {{-- Text + CTA --}}
        <div class="promo-content">
          <h3  class="promo-title ">Redefining Your Goals</h3>
          <p class="promo-sub text-capitalize">
            Unleash your peak fitness potential! Set a SMART (Specific, Measurable) goal.
            Start small, enjoy the journey, dominate!
          </p>
  
          <span class="btn promo-cta">
            Visit all services
            <i class="bi bi-arrow-right-short ms-1"></i>
          </span>
        </div>
      </a>
    </div>
  </section>
  