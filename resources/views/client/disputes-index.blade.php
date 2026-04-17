@extends('layouts.role-dashboard')

@section('role-content')

@php
    // 1) Make sure $disputes always exists and is iterable (front-end only).
    $disputes = $disputes ?? collect();

    // 2) Optional demo mode: visit /client/disputes?demo=1 to see sample rows.
    if (blank($disputes) && request()->boolean('demo')) {
        $disputes = collect([
            (object)['id' => 101, 'title' => 'Incorrect amount charged on last booking', 'status' => 'open',      'created_at' => now()->subDays(2)],
            (object)['id' => 102, 'title' => 'Coach cancelled at the last minute',         'status' => 'in_review','created_at' => now()->subWeek()],
            (object)['id' => 103, 'title' => 'Resolved: session rescheduled',              'status' => 'resolved', 'created_at' => now()->subDays(10)],
            (object)['id' => 104, 'title' => 'Closed: duplicate ticket',                   'status' => 'closed',   'created_at' => now()->subDays(14)],
        ]);
    }

    // 3) Helper to print date safely (works with Carbon or string)
    $fmt = function($dt) {
        if ($dt instanceof \Carbon\CarbonInterface) return $dt->format('M d, Y');
        if (is_string($dt)) return \Carbon\Carbon::parse($dt)->format('M d, Y');
        return '';
    };
@endphp

<div class="zv-panel">
  <div class="d-flex align-items-center justify-content-between mb-2">
    <div>
      <h6 class="mb-1">{{ __('Disputes') }}</h6>
      <div class="zv-muted small">
        {{ __('All your disputes in one place.') }}
        @if($disputes instanceof \Illuminate\Contracts\Pagination\Paginator)
          — <strong>{{ $disputes->total() }}</strong> {{ __('total') }}
        @elseif($disputes && is_countable($disputes))
          — <strong>{{ count($disputes) }}</strong> {{ __('total') }}
        @endif
      </div>
    </div>

    <div class="d-flex gap-2">
      <form method="GET" action="{{ route('client.disputes.index') }}" class="d-none d-md-block">
        <input name="q" value="{{ request('q') }}" class="form-control form-control-sm"
               placeholder="{{ __('Search disputes…') }}">
      </form>

      {{-- “Add dispute” (stays here even in front-end only) --}}
      <a href="{{ route('client.disputes.index') }}" class="btn-3d btn-dark-elev">
        <i class="bi bi-plus-lg me-1"></i>{{ __('Add dispute') }}
      </a>
    </div>
  </div>

  <div class="table-responsive">
    <table>
      <thead>
        <tr>
          <th>{{ __('ID') }}</th>
          <th>{{ __('Title') }}</th>
          <th>{{ __('Status') }}</th>
          <th>{{ __('Created') }}</th>
          <th class="text-end">{{ __('Actions') }}</th>
        </tr>
      </thead>
      <tbody>
        @forelse($disputes as $d)
          @php $status = strtolower($d->status ?? ''); @endphp
          <tr>
            <td>#{{ $d->id ?? '' }}</td>
            <td class="text-truncate" style="max-width:360px">{{ $d->title ?? '' }}</td>
            <td>
              <span class="zv-badge
                {{ $status === 'open' ? 'zv-badge-open' : '' }}
                {{ $status === 'in_review' ? 'zv-badge-review' : '' }}
                {{ $status === 'resolved' ? 'zv-badge-resolved' : '' }}
                {{ $status === 'closed' ? 'zv-badge-closed' : '' }}">
                {{ __($d->status ?? '') }}
              </span>
            </td>
            <td>{{ $fmt($d->created_at ?? null) }}</td>
            <td class="text-end">
              <a href="{{ route('client.disputes.show', $d->id ?? 0) }}" class="btn-3d btn-plain btn-sm">
                <i class="bi bi-eye me-1"></i>{{ __('View') }}
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="zv-empty">
              {{ __('No Disputes Yet.') }}
              <a href="{{ route('client.disputes.create') }}" class="ms-1">{{ __('Create one?') }}</a>
              
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- Show pagination only if we actually have a paginator --}}
  @if(is_object($disputes) && method_exists($disputes, 'links'))
    <div class="mt-3">
      {{ $disputes->withQueryString()->links() }}
    </div>
  @endif
</div>
@endsection
