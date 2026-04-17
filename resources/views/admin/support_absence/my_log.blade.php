@extends('layouts.admin')
@section('title','My Absence Log')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/support_absence_log.css') }}">
@endpush

@section('content')
<div class="abs-wrap">

  <div class="abs-head">
    <div>
      <h1 class="abs-title">My Absence Log</h1>
      <div class="abs-sub">Who changed my absence status & when</div>
    </div>

    <a class="btn btn-outline-dark" href="{{ route('admin.support.absence.my') }}">
      <i class="bi bi-arrow-left"></i> Back
    </a>
  </div>

  <div class="abs-card">
    <div class="abs-card-title">History</div>

    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th style="width:170px;">Time</th>
            <th style="width:160px;">Action</th>
            <th style="width:170px;">Set By</th>
            <th>Details</th>
            <th style="width:180px;">Attachment</th>
          </tr>
        </thead>
        <tbody>
          @forelse($audits as $a)
            @php
              $meta = (array)($a->meta ?? []);

              // ✅ Support both old + new keys safely
              $type  = $meta['type'] ?? null;

              $start = $meta['window_start'] ?? ($meta['start_at'] ?? null);
              $end   = $meta['window_end']   ?? ($meta['end_at'] ?? null);

              $note  = $meta['note'] ?? ($meta['decision_note'] ?? null);
              $reason = $meta['reason'] ?? null;
              $state = $meta['state'] ?? null;
            @endphp

            <tr>
              <td class="small text-muted">{{ optional($a->created_at)->format('d M Y, H:i') }}</td>

              <td>
                @php
                  $badge = 'secondary';
                  if (in_array($a->action, ['approved','direct_set'], true)) $badge = 'success';
                  elseif (in_array($a->action, ['requested'], true)) $badge = 'warning';
                  elseif (in_array($a->action, ['cancelled','removed'], true)) $badge = 'dark';
                @endphp

                <span class="badge bg-{{ $badge }} text-uppercase">
                  {{ str_replace('_',' ', $a->action) }}
                </span>
              </td>

              <td class="fw-semibold">{{ $a->actor->username ?? 'System' }}</td>

              <td class="small">
                {{-- Type might be null until manager decides --}}
                @if(!empty($type))
                  <div><strong>Type:</strong> <span class="text-capitalize">{{ $type }}</span></div>
                @endif

                @if(!empty($start) && !empty($end))
                  <div><strong>Window:</strong> {{ $start }} → {{ $end }}</div>
                @endif

                @if(!empty($state))
                  <div><strong>State:</strong> <span class="text-uppercase">{{ $state }}</span></div>
                @endif

                @if(!empty($reason))
                  <div><strong>Reason:</strong> {{ $reason }}</div>
                @endif

                @if(!empty($note))
                  <div><strong>Note:</strong> {{ $note }}</div>
                @endif
              </td>

              <td>
                @if($a->file_path)
                  {{-- If you have a fileUrl() accessor, keep this --}}
                  <a class="btn btn-sm btn-outline-dark" target="_blank"
                     href="{{ method_exists($a,'fileUrl') ? $a->fileUrl() : \Storage::disk($a->file_disk ?: 'public')->url($a->file_path) }}">
                    <i class="bi bi-paperclip"></i> View
                  </a>

                  <div class="small text-muted mt-1 text-truncate" style="max-width:160px;">
                    {{ $a->file_name ?? 'Attachment' }}
                  </div>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-muted">No logs yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="p-3">{{ $audits->links() }}</div>
  </div>
</div>
@endsection
