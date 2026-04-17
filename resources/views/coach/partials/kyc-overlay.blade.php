@php
  $u = auth()->user();
  $activeRole = strtolower((string) session('active_role', 'client'));
  $isCoachMode = $activeRole === 'coach';

  $show = false;
  $state = null;

  if ($u && $isCoachMode && ($u->is_coach ?? false)) {
    $st = (string) ($u->coach_verification_status ?? 'draft');
    if (in_array($st, ['pending','rejected'], true)) {
      $show = true;
      $state = $st;
    }
  }
@endphp

@if($show)
  <div class="position-fixed top-0 start-0 w-100 h-100" style="z-index: 9999;">
    <div class="w-100 h-100 bg-dark" style="opacity:.55;"></div>

    <div class="position-absolute top-50 start-50 translate-middle w-100 px-3" style="max-width: 560px;">
      <div class="card border-0 shadow-lg">
        <div class="card-body p-4">
          <div class="d-flex align-items-start gap-3">
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light"
                 style="width:56px;height:56px;">
              @if($state === 'pending')
                <span class="fs-4">⏳</span>
              @else
                <span class="fs-4">⚠️</span>
              @endif
            </div>

            <div class="flex-grow-1">
              @if($state === 'pending')
                <h5 class="mb-1">{{ __('Verification Under Review') }}</h5>
                <p class="text-muted mb-3">
                  {{ __("You’ve submitted your documents. Coach features will unlock after approval.") }}
                </p>
              @else
                <h5 class="mb-1">{{ __('Verification Rejected') }}</h5>
                <p class="text-muted mb-3">
                  {{ __("Please resubmit clearer documents to get approved.") }}
                </p>
              @endif

              <div class="d-flex flex-wrap gap-2">
                @if($state === 'rejected')
                  <a href="{{ route('coach.kyc.show') }}" class="btn btn-dark">
                    {{ __('Resubmit documents') }}
                  </a>
                @endif

                <a href="{{ route('support.conversation.index') }}" class="btn btn-outline-secondary">
                  {{ __('Contact support') }}
                </a>

                <form method="POST" action="{{ route('role.switch') }}" class="ms-auto">
                  @csrf
                  <input type="hidden" name="role" value="client">
                  <button class="btn btn-link text-decoration-none px-0">
                    {{ __('Switch to client') }}
                  </button>
                </form>
              </div>

              <div class="mt-3 small text-muted">
                {{ __('You can browse, but some actions are disabled until verification is complete.') }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
@endif
