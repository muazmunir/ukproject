@extends('client.layout')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/buttons.css') }}">
  <style>
    .zv-page {
      padding: 12px;
    }
    .zv-card {
      background: #fff;
      border: 1px solid rgba(0,0,0,.08);
      border-radius: 16px;
      box-shadow: 0 10px 24px rgba(0,0,0,.06);
      overflow: hidden;
      margin-bottom: 14px;
    }
    .zv-card__head {
      padding: 14px 16px;
      border-bottom: 1px solid rgba(0,0,0,.06);
      display:flex; align-items:center; justify-content:space-between; gap:12px;
    }
    .zv-title {
      font-weight: 700;
      margin:0;
      font-size: 16px;
    }
    .zv-sub {
      margin:0;
      color:#6b7280;
      font-size: 13px;
    }
    .zv-pill {
      display:inline-flex; align-items:center; gap:6px;
      border-radius: 999px;
      padding: 6px 10px;
      font-size: 12px;
      font-weight: 700;
      border: 1px solid rgba(0,0,0,.08);
      background: #f8fafc;
    }
    .zv-pill--open { background:#fff7ed; border-color:#fed7aa; color:#9a3412; }
    .zv-pill--in_review { background:#eff6ff; border-color:#bfdbfe; color:#1d4ed8; }
    .zv-pill--resolved { background:#ecfdf5; border-color:#a7f3d0; color:#065f46; }
    .zv-pill--rejected { background:#fef2f2; border-color:#fecaca; color:#991b1b; }

    .zv-card__body { padding: 14px 16px; }
    .zv-grid {
      display:grid;
      grid-template-columns: 1fr;
      gap: 12px;
    }
    @media (min-width: 992px){
      .zv-grid-2 { grid-template-columns: 1fr 1fr; }
      .zv-grid-3 { grid-template-columns: 1fr 1fr 1fr; }
    }
    .zv-kv {
      border: 1px solid rgba(0,0,0,.06);
      border-radius: 14px;
      padding: 12px;
      background: #fcfcfd;
    }
    .zv-kv .k { color:#6b7280; font-size: 12px; margin-bottom: 4px; }
    .zv-kv .v { font-weight: 700; font-size: 14px; color:#111827; }
    .zv-muted { color:#6b7280; }
    .zv-divider { height:1px; background: rgba(0,0,0,.06); margin: 12px 0; }

    .zv-attachments {
      display:grid;
      grid-template-columns: repeat(1, minmax(0, 1fr));
      gap: 10px;
    }
    @media (min-width: 768px){
      .zv-attachments { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    .zv-attachment {
      border:1px solid rgba(0,0,0,.06);
      border-radius: 14px;
      overflow:hidden;
      background:#fff;
    }
    .zv-attachment__preview {
      width:100%;
      aspect-ratio: 16/10;
      background:#0b1220;
      display:flex; align-items:center; justify-content:center;
    }
    .zv-attachment__preview img { width:100%; height:100%; object-fit:cover; }
    .zv-attachment__body { padding: 10px; }
    .zv-attachment__meta { font-size: 12px; color:#6b7280; }
    .zv-attachment__actions { margin-top: 8px; display:flex; gap:8px; flex-wrap:wrap; }
    .zv-btn {
      display:inline-flex; align-items:center; gap:8px;
      padding: 8px 12px;
      border-radius: 999px;
      border:1px solid rgba(0,0,0,.10);
      background:#fff;
      color:#111827;
      text-decoration:none;
      font-weight:700;
      font-size: 13px;
    }
    .zv-btn:hover { background:#f9fafb; }
    .zv-btn-primary {
      background:#111827; color:#fff; border-color:#111827;
    }
    .zv-btn-primary:hover { background:#0b1220; }
    .zv-table th { font-size: 12px; color:#6b7280; font-weight: 700; }
    .zv-table td { vertical-align: middle; }
    .badge-soft { border-radius: 999px; padding: 6px 10px; font-size: 12px; font-weight: 700; }
    .badge-soft-success { background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; }
    .badge-soft-warning { background:#fff7ed; color:#9a3412; border:1px solid #fed7aa; }
    .badge-soft-danger { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }
    .badge-soft-info { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
  </style>
@endpush

@section('client-content')
@php
  /** @var \App\Models\Dispute $dispute */
  /** @var \App\Models\Reservation $reservation */

  $res = $reservation ?? $dispute->reservation;

  $res->loadMissing(['service.coach','client','package','slots','payment']);
  $tz = auth()->user()->timezone ?? config('app.timezone','UTC');

  $currency = $res->currency ?? 'USD';
  $subtotal = (int)($res->subtotal_minor ?? 0) / 100;
  $fees     = (int)($res->fees_minor ?? 0) / 100;
  $total    = (int)($res->total_minor ?? 0) / 100;

  $status = strtolower((string)($dispute->status ?? 'open'));
  $pillClass = match($status) {
    'open' => 'zv-pill--open',
    'in_review' => 'zv-pill--in_review',
    'resolved' => 'zv-pill--resolved',
    'rejected' => 'zv-pill--rejected',
    default => 'zv-pill--open',
  };

  $slots = $res->slots->sortBy('start_utc');
@endphp

<div class="zv-page">

  {{-- Header --}}
  <div class="zv-card">
    <div class="zv-card__head">
      <div>
        <p class="zv-sub mb-1">{{ __('Dispute for Booking') }} #{{ $res->id }}</p>
        <h2 class="zv-title mb-0">{{ __('Dispute Details') }}</h2>
      </div>

      <div class="text-end">
        <span class="zv-pill {{ $pillClass }}">
          <i class="bi bi-flag"></i>
          {{ ucwords(str_replace('_',' ', $status)) }}
        </span>
        <div class="zv-sub mt-1">
          {{ __('Created') }}:
          {{ optional($dispute->created_at)->timezone($tz)->format('d M Y, H:i') }}
        </div>
      </div>
    </div>

    <div class="zv-card__body">
      <div class="zv-grid zv-grid-2">
        <div class="zv-kv">
          <div class="k">{{ __('Issue / Category') }}</div>
          <div class="v">{{ $dispute->category ?? '-' }}</div>
        </div>
        <div class="zv-kv">
          <div class="k">{{ __('Raised By') }}</div>
          <div class="v">
            {{ ucfirst($dispute->raised_by_role ?? '-') }}
            <span class="zv-muted fw-normal">
              — {{ optional($dispute->raisedBy)->name ?? '' }}
            </span>
          </div>
        </div>
      </div>

      <div class="zv-divider"></div>

      <div class="zv-kv">
        <div class="k">{{ __('Description') }}</div>
        <div class="v" style="font-weight:600;">
          {!! nl2br(e($dispute->description ?? '-')) !!}
        </div>
      </div>
    </div>
  </div>

  {{-- Booking Snapshot --}}
  <div class="zv-card">
    <div class="zv-card__head">
      <div>
        <h3 class="zv-title mb-0">{{ __('Booking Snapshot') }}</h3>
        <p class="zv-sub">{{ __('Key booking data at the time of dispute') }}</p>
      </div>
      <a href="{{ route(auth()->user()->role === 'coach' ? 'coach.bookings.show' : 'client.bookings.show', $res) }}"
         class="zv-btn zv-btn-primary">
        <i class="bi bi-box-arrow-up-right"></i> {{ __('Open Booking') }}
      </a>
    </div>

    <div class="zv-card__body">
      <div class="zv-grid zv-grid-3">
        <div class="zv-kv">
          <div class="k">{{ __('Service') }}</div>
          <div class="v">{{ $res->package?->title ?? $res->service?->title ?? '-' }}</div>
        </div>

        <div class="zv-kv">
          <div class="k">{{ __('Client') }}</div>
          <div class="v">{{ $res->client?->name ?? $res->client?->full_name ?? '-' }}</div>
        </div>

        <div class="zv-kv">
          <div class="k">{{ __('Coach') }}</div>
          <div class="v">{{ $res->service?->coach?->name ?? $res->service?->coach?->full_name ?? '-' }}</div>
        </div>
      </div>

      <div class="zv-divider"></div>

      <div class="zv-grid zv-grid-3">
        <div class="zv-kv">
          <div class="k">{{ __('Reservation Status') }}</div>
          <div class="v">{{ ucwords(str_replace('_',' ', (string)($res->status ?? '-'))) }}</div>
        </div>

        <div class="zv-kv">
          <div class="k">{{ __('Settlement Status') }}</div>
          <div class="v">{{ ucwords(str_replace('_',' ', (string)($res->settlement_status ?? '-'))) }}</div>
        </div>

        <div class="zv-kv">
          <div class="k">{{ __('Payment Status') }}</div>
          <div class="v">{{ ucwords(str_replace('_',' ', (string)($res->payment_status ?? '-'))) }}</div>
        </div>
      </div>

      <div class="zv-divider"></div>

      <div class="zv-grid zv-grid-3">
        <div class="zv-kv">
          <div class="k">{{ __('Subtotal') }}</div>
          <div class="v">{{ number_format($subtotal, 2) }} {{ $currency }}</div>
        </div>

        <div class="zv-kv">
          <div class="k">{{ __('Fees') }}</div>
          <div class="v">{{ number_format($fees, 2) }} {{ $currency }}</div>
        </div>

        <div class="zv-kv">
          <div class="k">{{ __('Total Paid') }}</div>
          <div class="v">{{ number_format($total, 2) }} {{ $currency }}</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Slot Details --}}
  <div class="zv-card">
    <div class="zv-card__head">
      <div>
        <h3 class="zv-title mb-0">{{ __('Session Slots') }}</h3>
        <p class="zv-sub">{{ __('Check-in activity and slot outcomes') }}</p>
      </div>
    </div>

    <div class="zv-card__body">
      <div class="table-responsive">
        <table class="table zv-table align-middle">
          <thead>
            <tr>
              <th>#</th>
              <th>{{ __('Date') }}</th>
              <th>{{ __('Time') }}</th>
              <th>{{ __('Status') }}</th>
              <th>{{ __('Client Check-in') }}</th>
              <th>{{ __('Coach Check-in') }}</th>
              <th>{{ __('Finalized') }}</th>
            </tr>
          </thead>
          <tbody>
          @forelse($slots as $i => $slot)
            @php
              $st = strtolower(trim((string)($slot->session_status ?? '')));
              $badge = 'badge-soft-info';
              if (in_array($st, ['completed','no_show_client'], true)) $badge = 'badge-soft-success';
              if (in_array($st, ['no_show_coach','no_show_both'], true)) $badge = 'badge-soft-danger';
              if (in_array($st, ['live','in_progress'], true)) $badge = 'badge-soft-warning';

              $start = $slot->start_utc ? \Carbon\Carbon::parse($slot->start_utc)->utc()->timezone($tz) : null;
              $end   = $slot->end_utc ? \Carbon\Carbon::parse($slot->end_utc)->utc()->timezone($tz) : null;
            @endphp
            <tr>
              <td class="fw-bold">{{ $i+1 }}</td>
              <td>{{ $start?->format('d M Y') ?? '-' }}</td>
              <td>
                {{ $start?->format('H:i') ?? '-' }}
                —
                {{ $end?->format('H:i') ?? '-' }}
              </td>
              <td>
                <span class="badge-soft {{ $badge }}">
                  {{ $st ? ucwords(str_replace('_',' ', $st)) : '-' }}
                </span>
              </td>
              <td class="small">
                {{ $slot->client_checked_in_at ? \Carbon\Carbon::parse($slot->client_checked_in_at)->timezone($tz)->format('d M Y, H:i') : '-' }}
              </td>
              <td class="small">
                {{ $slot->coach_checked_in_at ? \Carbon\Carbon::parse($slot->coach_checked_in_at)->timezone($tz)->format('d M Y, H:i') : '-' }}
              </td>
              <td class="small">
                {{ $slot->finalized_at ? \Carbon\Carbon::parse($slot->finalized_at)->timezone($tz)->format('d M Y, H:i') : '-' }}
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted py-4">{{ __('No slots found.') }}</td>
            </tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- Attachments --}}
  <div class="zv-card">
    <div class="zv-card__head">
      <div>
        <h3 class="zv-title mb-0">{{ __('Attachments') }}</h3>
        <p class="zv-sub">{{ __('Images and videos submitted with the dispute') }}</p>
      </div>
    </div>

    <div class="zv-card__body">
      @php
        $attachments = $dispute->attachments ?? collect();
      @endphp

      @if($attachments->isEmpty())
        <div class="text-muted">{{ __('No attachments uploaded.') }}</div>
      @else
        <div class="zv-attachments">
          @foreach($attachments as $att)
            @php
              $url = $att->file_path ? asset('storage/'.$att->file_path) : null;
              $isVideo = ($att->file_type === 'video') || (is_string($att->mime) && str_contains($att->mime, 'video'));
            @endphp

            <div class="zv-attachment">
              <div class="zv-attachment__preview">
                @if($isVideo)
                  @if($url)
                    <video controls style="width:100%; height:100%; object-fit:cover;">
                      <source src="{{ $url }}" type="{{ $att->mime ?? 'video/mp4' }}">
                    </video>
                  @else
                    <span class="text-white">{{ __('Video') }}</span>
                  @endif
                @else
                  @if($url)
                    <img src="{{ $url }}" alt="attachment">
                  @else
                    <span class="text-white">{{ __('Image') }}</span>
                  @endif
                @endif
              </div>

              <div class="zv-attachment__body">
                <div class="zv-attachment__meta">
                  {{ strtoupper($att->file_type ?? 'file') }}
                  @if(!empty($att->size))
                    · {{ number_format($att->size/1024, 1) }} KB
                  @endif
                </div>

                <div class="zv-attachment__actions">
                  @if($url)
                    <a class="zv-btn" href="{{ $url }}" target="_blank" rel="noopener">
                      <i class="bi bi-box-arrow-up-right"></i> {{ __('Open') }}
                    </a>

                    <a class="zv-btn" href="{{ $url }}" download>
                      <i class="bi bi-download"></i> {{ __('Download') }}
                    </a>
                  @endif
                </div>
              </div>
            </div>
          @endforeach
        </div>
      @endif
    </div>
  </div>

  {{-- Back --}}
  <div class="d-flex justify-content-end">
    <a href="{{ route('client.disputes.index') }}" class="zv-btn">
      <i class="bi bi-arrow-left"></i> {{ __('Back to Disputes') }}
    </a>
  </div>

</div>
@endsection
