@if($rows->count())

<div class="zv-card">

  <div class="zv-card-head">
    <div class="zv-card-title">Earnings & Activity</div>
    
  </div>

  <div class="zv-tablewrap">
    <table class="zv-table zv-table--responsive">

    <thead>
  <tr>
    <th>ID</th>
    <th>Date</th>
    <th>Status</th>

    <th class="">Booking Value</th>
    <th class="">Adjustment</th>
    <th class="">Service Fee</th>
    <th class="">Net Pay</th>
  </tr>
</thead>

      <tbody>


        @php
  $label = fn($s) => $s ? ucwords(str_replace('_',' ', strtolower(trim((string)$s)))) : '—';

  // Optional: if you want custom names for some statuses
  $statusLabel = function($s) use ($label) {
    $s = strtolower(trim((string)$s));
    return match($s) {
      'no_show' => 'No Show',
      default => $label($s),
    };
  };

  $settleLabel = function($s) use ($label) {
    $s = strtolower(trim((string)$s));
    return match($s) {
      'refund_pending' => 'Refund Pending',
      'refunded_partial' => 'Partially Refunded',
      'in_dispute' => 'In Dispute',
      'pending' => 'Pending Payout',
      default => $label($s),
    };
  };
@endphp
      @foreach($rows as $r)

        @php
       
  $rowDate = $r->completed_at ?? $r->cancelled_at ?? $r->created_at;

  $status = strtolower((string) $r->status);
  $settle = strtolower((string) $r->settlement_status);

  $booked = (int) ($r->subtotal_minor ?? 0);

  // "paid net" from reservation snapshot (only reliable when settle=paid)
  $paidNet = (int) ($r->coach_earned_minor ?? $r->coach_net_minor ?? 0);

  $penalty = (int) ($r->coach_penalty_minor ?? 0);
  $comp    = (int) ($r->coach_comp_minor ?? 0);

  // Adjustment shown as single number
  $adjustmentMinor = (int) ($comp - $penalty);

  // Service Fee = booked - paidNet (only when paid)
  $serviceFeeMinor = ($settle === 'paid')
      ? max(0, $booked - $paidNet)
      : null;

  // Net Pay = what coach effectively earned for this reservation
  // If paid: paidNet +/- adjustments
  // If not paid: show adjustments if any, but otherwise 0/—
  $netPayMinor = (int) ($paidNet + $comp - $penalty);

  $badgeClass = 'zv-badge';
  if ($settle === 'paid') $badgeClass .= ' zv-badge-success';
  elseif ($settle === 'pending') $badgeClass .= ' zv-badge-warning';
  elseif (in_array($settle, ['refund_pending','refunded','refunded_partial'])) $badgeClass .= ' zv-badge-danger';
  elseif ($settle === 'in_dispute') $badgeClass .= ' zv-badge-info';
@endphp

        <tr>

          <td>#{{ $r->id }}</td>

          <td>
            {{ optional($rowDate)->format('Y-m-d') ?? '-' }}

            @if($r->completed_at)
              <div class="zv-muted">Completed</div>
            @elseif($r->cancelled_at)
              <div class="zv-muted">Cancelled</div>
            @else
              <div class="zv-muted">Created</div>
            @endif
          </td>

          <td>
  <div class="d-flex flex-column gap-1 align-items-center">
    <div class="text-capitalize">{{ $statusLabel($status) }}</div>
    <span class="{{ $badgeClass }}">{{ $settleLabel($settle) }}</span>
  </div>
</td>

          {{-- Booked --}}
          <td class="">
            {{ $fmt($booked) }}
          </td>

          {{-- Paid Net --}}
          <td class="">
  @if($adjustmentMinor > 0)
    <span class="text-success">+{{ $fmt($adjustmentMinor) }}</span>
    <div class="zv-muted">Compensation</div>
  @elseif($adjustmentMinor < 0)
    <span class="text-danger">{{ $fmt($adjustmentMinor) }}</span>
    <div class="zv-muted">Penalty</div>
  @else
    —
  @endif
</td>

          {{-- Penalty --}}
          <td class="">
  @if($settle === 'paid')
    {{ $fmt((int)$serviceFeeMinor) }}
    {{-- <div class="zv-muted">Booking − Paid net</div>   --}}
  @else
    <span class="zv-muted">—</span>
  @endif
</td>

          {{-- Compensation --}}
          <td class="">
  @if($settle === 'paid')
    <strong>{{ $fmt($netPayMinor) }}</strong>
    @if($adjustmentMinor !== 0)
      <div class="zv-muted">Net ± adjustment</div>
    @else
      <div class="zv-muted">Paid Net</div>
    @endif
  @else
    @if(($comp - $penalty) !== 0)
      {{-- show adjustments even if not paid --}}
      <span class="{{ $netPayMinor < 0 ? 'text-danger' : 'text-success' }}">
        {{ $fmt($netPayMinor) }}
      </span>
      <div class="zv-muted">Adjustment Only</div>
    @else
      <span class="zv-muted">—</span>
    @endif
  @endif
</td>
          {{-- Final Impact --}}
         

        </tr>

      @endforeach

      </tbody>

    </table>
  </div>

  <div class="zv-pager">
    {{ $rows->withQueryString()->links() }}
  </div>

</div>

@else

<div class="zv-empty-hero zv-empty-hero-compact">
  <div class="zv-empty-emoji">📊</div>
  <div class="zv-empty-title text-capitalize">No data for this period</div>
  <div class="zv-empty-sub text-capitalize">Try a different range or remove date filters.</div>
</div>

@endif