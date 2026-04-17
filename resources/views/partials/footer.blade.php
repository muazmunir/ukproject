<footer class="zv-footer mt-5 pt-5 bg-light-subtle">
    <div class="container">
  
      {{-- Top: Brand + Newsletter --}}
      <div class="row gy-4 align-items-stretch zv-footer-top">

        <div class="col-lg-4 zv-footer-about">
          <div class="d-flex align-items-center gap-2 mb-2">
            <img src="{{ asset('assets/logo.png') }}" alt="ZAIVIAS" height="28">
          </div>
          <p class="text-muted mb-4 text-capitalize">
            We connect clients with certified coaches for personalized training, goal-driven
            programs, and accessible wellness—online or in person.
          </p>
  
          {{-- Socials --}}
         
        </div>
  
        <div class="col-lg-8">
          <div class="zv-news card border-0 shadow-sm zv-news-card">
            <div class="card-body p-3 p-sm-4">
              <div class="row g-3 align-items-center">
                <div class="col-md-5">
                  <h5 class="mb-1 fw-bold">Get Inspired Weekly</h5>
                  <p class="text-muted mb-0 text-capitalize">Training tips, new coaches, and hand-picked programs straight to your inbox.</p>
                </div>
                <div class="col-md-7">
                  @if (session('newsletter_success'))
  <div class="alert alert-success py-2 mb-2 small">
    {{ session('newsletter_success') }}
  </div>
@endif

@if ($errors->has('email'))
  <div class="alert alert-danger py-2 mb-2 small">
    {{ $errors->first('email') }}
  </div>
@endif

                  <form class=" zv-news-form d-flex gap-2" method="POST" action="{{ route('newsletter.subscribe') }}">
                    @csrf
                    <input name="email" type="email" class="form-control form-control-lg"
                           placeholder="Write Your Email" required>
                    <button class="btn btn-lg btn-dark px-4">Subscribe</button>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
  
      {{-- Inspiration Tabs (desktop) / Accordions (mobile) --}}
      <div class="zv-inspire mt-5">
        <ul class="nav nav-underline small d-none d-md-flex">
          <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#insp-popular" type="button">Popular</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#insp-strength" type="button">Strength</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#insp-cardio" type="button">Cardio</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#insp-wellness" type="button">Wellness</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#insp-online" type="button">Online</button></li>
        </ul>
  
        <div class="tab-content d-none d-md-block pt-3">
          <div class="tab-pane fade show active" id="insp-popular">
            @include('partials.footer-inspire-row', ['items' => [
              ['title'=>'Virtual Personal Training','sub'=>'Goal-Based 1:1'],
              ['title'=>'Beginner Strength','sub'=>'Start Safe'],
              ['title'=>'Mobility & Stretch','sub'=>'Recover & Move'],
              ['title'=>'HIIT Express','sub'=>'30-Minute Burn'],
              ['title'=>'Pilates Core','sub'=>'Stability & Control'],
              ['title'=>'Outdoor Running','sub'=>'Technique + Plans'],
            ]])
          </div>
          <div class="tab-pane fade" id="insp-strength">
            @include('partials.footer-inspire-row', ['items' => [
              ['title'=>'Hypertrophy Blocks','sub'=>'Push/Pull/Legs'],
              ['title'=>'Powerlifting Basics','sub'=>'Form first'],
              ['title'=>'Kettlebell Skills','sub'=>'Flow & strength'],
              ['title'=>'Olympic Lifts Intro','sub'=>'Clean & snatch'],
              ['title'=>'Glute Focus','sub'=>'Posterior chain'],
              ['title'=>'Grip & Core','sub'=>'Farmers carry'],
            ]])
          </div>
          <div class="tab-pane fade" id="insp-cardio">
            @include('partials.footer-inspire-row', ['items' => [
              ['title'=>'Zone 2 Coaching','sub'=>'Endurance build'],
              ['title'=>'Rowing Technique','sub'=>'Efficient strokes'],
              ['title'=>'Spin Intervals','sub'=>'Power & cadence'],
              ['title'=>'Boxing Cardio','sub'=>'Padwork basics'],
              ['title'=>'Jump Rope','sub'=>'Rhythm & footwork'],
              ['title'=>'Stair Workouts','sub'=>'Leg engine'],
            ]])
          </div>
          <div class="tab-pane fade" id="insp-wellness">
            @include('partials.footer-inspire-row', ['items' => [
              ['title'=>'Yoga Nidra','sub'=>'Deep rest'],
              ['title'=>'Breathwork','sub'=>'CO₂ tolerance'],
              ['title'=>'Mindful Mobility','sub'=>'Move + breathe'],
              ['title'=>'Desk Reset','sub'=>'15-min routine'],
              ['title'=>'Prenatal Pilates','sub'=>'Safe movement'],
              ['title'=>'Low-Impact Cardio','sub'=>'Joint friendly'],
            ]])
          </div>
          <div class="tab-pane fade" id="insp-online">
            @include('partials.footer-inspire-row', ['items' => [
              ['title'=>'Remote Form Check','sub'=>'Video feedback'],
              ['title'=>'Program Audits','sub'=>'Coach review'],
              ['title'=>'Small Group Zoom','sub'=>'Train together'],
              ['title'=>'App-Based Plans','sub'=>'Daily tasks'],
              ['title'=>'Nutrition Coaching','sub'=>'Habits & macros'],
              ['title'=>'Accountability Calls','sub'=>'Stay on track'],
            ]])
          </div>
        </div>
  
        {{-- Mobile accordions --}}
        <div class="accordion d-md-none" id="inspireAcc">
          @foreach ([
            'Popular' => 'inspm1', 'Strength' => 'inspm2', 'Cardio' => 'inspm3',
            'Wellness' => 'inspm4', 'Online' => 'inspm5'
          ] as $label => $id)
            <div class="accordion-item border-0 border-top">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed py-3" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $id }}">
                  {{ $label }}
                </button>
              </h2>
              <div id="{{ $id }}" class="accordion-collapse collapse" data-bs-parent="#inspireAcc">
                <div class="accordion-body pt-0">
                  @include('partials.footer-inspire-row', ['items' => [
                    ['title'=>'Example item 1','sub'=>'Subtitle'],
                    ['title'=>'Example item 2','sub'=>'Subtitle'],
                    ['title'=>'Example item 3','sub'=>'Subtitle'],
                  ]])
                </div>
              </div>
            </div>
          @endforeach
        </div>
      </div>
  
      {{-- Link columns --}}
      <div class="row row-cols-2 row-cols-md-3 gy-4 mt-4 pt-3 border-top">
        <div class="col">
          <h6 class="fw-semibold mb-3">Support</h6>
          <ul class="list-unstyled small zv-links">
            <li><a href="{{ route('help.center') }}">Help Center</a></li>
            <li><a href="#">Disability Support</a></li>
            <li><a href="#">Safety & Reporting</a></li>
            <li><a href="#">Cancellation Policy</a></li>
            <li><a href="#">Anti-Discrimination</a></li>
          </ul>
        </div>
        <div class="col">
          <h6 class="fw-semibold mb-3">For Coaches</h6>
          <ul class="list-unstyled small zv-links">
            <li><a href="#">Become a Coach</a></li>
            <li><a href="#">Coach Resources</a></li>
            <li><a href="#">Pricing & Payouts</a></li>
            <li><a href="#">Community Forum</a></li>
            <li><a href="#">Coach Academy</a></li>
          </ul>
        </div>
        <div class="col">
          <h6 class="fw-semibold mb-3">Company</h6>
          <ul class="list-unstyled small zv-links">
            <li><a href="#">About ZAIVIAS</a></li>
            <li><a href="#">Careers</a></li>
            <li><a href="#">Press</a></li>
            <li><a href="#">Contact</a></li>
            <li><a href="#">Investors</a></li>
          </ul>
        </div>
      </div>
  
      {{-- Language / currency --}}
      {{-- <div class="d-flex flex-wrap align-items-center gap-3 mt-3">
        <button class="btn btn-sm btn-outline-dark rounded-pill px-3">
          <i class="bi bi-globe me-1"></i> English
        </button>
        <button class="btn btn-sm btn-outline-dark rounded-pill px-3">
          <i class="bi bi-currency-euro me-1"></i> EUR
        </button>
      </div> --}}
  
      {{-- Legal bar --}}
      <div class="d-flex flex-column align-items-center text-center gap-3 py-4 border-top mt-4 small text-muted">

  {{-- Social icons → middle bottom --}}
 <div class="d-flex justify-content-center gap-3 mb-2">
  <a class="zv-social" href="https://www.instagram.com/zaivias" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
    <i class="bi bi-instagram"></i>
  </a>
  
  <a class="zv-social" href="https://x.com/zaivias" target="_blank" rel="noopener noreferrer" aria-label="X">
    <i class="bi bi-twitter-x"></i>
  </a>
  
  <a class="zv-social" href="https://youtube.com/@zaivias?si=u81IQTi1Tv4Py1xd" target="_blank" rel="noopener noreferrer" aria-label="YouTube">
    <i class="bi bi-youtube"></i>
  </a>
  
  <a class="zv-social" href="https://www.linkedin.com/company/zaivias/" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn">
    <i class="bi bi-linkedin"></i>
  </a>
  
  <a class="zv-social" href="https://www.facebook.com/share/1E5gUWo7H7/" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
    <i class="bi bi-facebook"></i>
  </a>
  <a class="zv-social" href="https://www.tiktok.com/@zaivias?_r=1&_t=ZN-95SLcWijg03" target="_blank" rel="noopener noreferrer" aria-label="TikTok">
    <i class="bi bi-tiktok"></i>
  </a>
</div>

  {{-- Copyright --}}
  <div>© {{ date('Y') }} ZAIVIAS. All Rights Reserved.</div>

  {{-- Legal links --}}
  <div class="d-flex justify-content-center">
    <ul class="list-inline mb-0">
      <li class="list-inline-item"><a class="text-muted text-decoration-none" href="#">Privacy</a></li>
      <li class="list-inline-item"><a class="text-muted text-decoration-none" href="#">Terms</a></li>
      <li class="list-inline-item"><a class="text-muted text-decoration-none" href="#">Sitemap</a></li>
    </ul>
  </div>

</div>

    </div>
  </footer>

  

  