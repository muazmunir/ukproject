<div class="zv-tiles">

  {{-- =========================
       ROW 1: ACTIVITY OVERVIEW
       ========================= --}}
  <div class="zv-row">
    <div class="zv-row-header">
      <div class="zv-tile-label text-black">Activity Overview</div>
    </div>

    <div class="zv-tile">
      <div class="zv-tile-label">Booked Revenue</div>
      <div class="zv-tile-value">{{ $fmt((int) $bookedGmvMinor) }} USD</div>
      <div class="zv-tile-sub text-capitalize">Total value of sessions booked.</div>
    </div>

    <div class="zv-tile">
      <div class="zv-tile-label">New Bookings</div>
      <div class="zv-tile-value">{{ number_format((int) $bookingsCreatedCount) }}</div>
      <div class="zv-tile-sub text-capitalize">Number of new sessions booked.</div>
    </div>

    <div class="zv-tile">
      <div class="zv-tile-label">Delivered Revenue</div>
      <div class="zv-tile-value">{{ $fmt((int) $completedGmvMinor) }} USD</div>
      <div class="zv-tile-sub text-capitalize">Total value of sessions completed.</div>
    </div>

    <div class="zv-tile">
      <div class="zv-tile-label">Sessions Completed</div>
      <div class="zv-tile-value">{{ number_format((int) $completedCount) }} </div>
      <div class="zv-tile-sub text-capitalize">Number of sessions successfully delivered.</div>
    </div>
  </div>


  {{-- =========================
       ROW 2: EXCEPTIONS & OUTCOMES
       ========================= --}}
  <div class="zv-row">
    <div class="zv-row-header">
      <div class="zv-tile-label text-black">Exceptions &amp; Outcomes</div>
    </div>

    <div class="zv-tile zv-tile-warning">
      <div class="zv-tile-label">Refunded Sessions</div>
      <div class="zv-tile-value">
        {{ number_format((int) $completedRefundedCount) }}
        <span class="zv-muted text-black" style="display:block;font-size:.9em;">
          {{ $fmt((int) $completedRefundedGmvMinor) }} USD
        </span>
      </div>
      <div class="zv-tile-sub text-capitalize">
        Sessions delivered but later refunded or disputed.
      </div>
    </div>

    <div class="zv-tile zv-tile-warning">
      <div class="zv-tile-label">Cancelled Revenue</div>
      <div class="zv-tile-value">{{ $fmt((int) $cancelledGmvMinor) }} USD</div>
      <div class="zv-tile-sub text-capitalize">Total value of sessions cancelled.</div>
    </div>

    <div class="zv-tile zv-tile-warning">
      <div class="zv-tile-label">Cancelled Sessions</div>
      <div class="zv-tile-value">{{ number_format((int) $cancelledCount) }} </div>
      <div class="zv-tile-sub text-capitalize">Number of sessions cancelled.</div>
    </div>

    <div class="zv-tile zv-tile-danger">
      <div class="zv-tile-label"> No Show Sessions</div>
      <div class="zv-tile-value">{{ number_format((int) ($noShowCount ?? 0)) }}</div>
      <div class="zv-tile-sub text-capitalize">Sessions where the client did not attend.</div>
    </div>
  </div>


  {{-- =========================
       ROW 3: PERFORMANCE RATES (CENTER TWO BOXES)
       ========================= --}}
  <div class="zv-row">
    <div class="zv-row-header">
      <div class="text-black zv-tile-label">Performance Rates</div>
    </div>

    {{-- spacer to center (col 1) --}}
    <div class="zv-tile" style="visibility:hidden;"></div>

    <div class="zv-tile">
      <div class="zv-tile-label">Completion Rate</div>
      <div class="zv-tile-value">{{ number_format((float) $completionRate, 2) }}%</div>
      <div class="zv-tile-sub text-capitalize">Percentage of booked sessions completed.</div>
    </div>

    <div class="zv-tile">
      <div class="zv-tile-label">Cancellation Rate</div>
      <div class="zv-tile-value">{{ number_format((float) $cancellationRate, 2) }}%</div>
      <div class="zv-tile-sub text-capitalize">Percentage of booked sessions cancelled.</div>
    </div>

    {{-- spacer to center (col 4) --}}
    <div class="zv-tile" style="visibility:hidden;"></div>
  </div>


  {{-- =========================
       ROW 4: FINANCIAL SUMMARY
       ========================= --}}
  <div class="zv-row">
    <div class="zv-row-header">
      <div class="zv-tile-label text-black">Financial Summary</div>
    </div>

    <div class="zv-tile zv-tile-success">
      <div class="zv-tile-label">Net Earnings</div>
      <div class="zv-tile-value">{{ $fmt((int) $coachPaidNetMinor) }} USD</div>
      <div class="zv-tile-sub text-capitalize">Amount released to you after commission, refunds, and adjustments.</div>
    </div>

    <div class="zv-tile zv-tile-danger">
      <div class="zv-tile-label">Penalty Fees</div>
      <div class="zv-tile-value">{{ $fmt((int) $coachPenaltiesMinor) }} USD</div>
      <div class="zv-tile-sub text-capitalize">Fees applied due to late cancellations or no shows.</div>
    </div>

    <div class="zv-tile">
      <div class="zv-tile-label">Client Compensation</div>
      <div class="zv-tile-value">{{ $fmt((int) $coachCompMinor) }} USD</div>
      <div class="zv-tile-sub text-capitalize">Payments received from clients for late cancellations or no shows.</div>
    </div>

    <div class="zv-tile">
      <div class="zv-tile-label">Net Result</div>
      <div class="zv-tile-value">{{ $fmt((int) $coachFinalImpactMinor) }} USD</div>
      <div class="zv-tile-sub text-capitalize">Net earnings after penalties and compensation.</div>
    </div>
  </div>


  {{-- =========================
       ROW 5: REPORTING SNAPSHOT
       ========================= --}}
  <div class="zv-row">
    <div class="zv-row-header">
      <div class="zv-tile-label text-black">Reporting Snapshot</div>
    </div>

    <div class="zv-tile" style="visibility:hidden;"></div>

    <div class="zv-tile">
      <div class="zv-tile-label">Platform Commission Paid</div>
      <div class="zv-tile-value">{{ $fmt((int) ($coachCommissionMinor ?? 0)) }} USD</div>
      <div class="zv-tile-sub text-capitalize">Total commission deducted from paid sessions.</div>
    </div>

    <div class="zv-tile">
      <div class="zv-tile-label">Gross Revenue from Paid Sessions</div>
      <div class="zv-tile-value">{{ $fmt((int) ($coachGrossMinor ?? 0))}} USD </div>
      <div class="zv-tile-sub text-capitalize">Total client payments received before commission and deductions.</div>
    </div>

    <div class="zv-tile" style="visibility:hidden;"></div>
  </div>


  {{-- =========================
       ROW 6: FUNNEL ANALYTICS (KEEP)
       ========================= --}}
  <div class="zv-row">
    <div class="zv-row-header">
      <div class="zv-tile-label text-black">Funnel Analytics</div>
    </div>

    <div class="zv-tile">
      <div class="zv-tile-label">Profile Views</div>
      <div class="zv-tile-value">{{ number_format((int) $profileViews) }}</div>
      <div class="zv-tile-sub text-capitalize">How many times clients viewed your profile</div>
    </div>

    <div class="zv-tile">
      <div class="zv-tile-label">Booking Page Visits</div>
      <div class="zv-tile-value">{{ number_format((int) $bookingPageVisits) }}</div>
      <div class="zv-tile-sub text-capitalize">Visits to the reserve/checkout page</div>
    </div>

    <div class="zv-tile">
      <div class="zv-tile-label">Enquiries</div>
      <div class="zv-tile-value">{{ number_format((int) $enquiries) }}</div>
      <div class="zv-tile-sub text-capitalize">Client messages sent to you</div>
    </div>

    <div class="zv-tile">
      <div class="zv-tile-label">Bookings (Created)</div>
      <div class="zv-tile-value">{{ number_format((int) $bookingsCount) }}</div>
      <div class="zv-tile-sub text-capitalize">Bookings created in this period</div>
    </div>

    <div class="zv-tile">
      <div class="zv-tile-label">Conversion (Views → Bookings)</div>
      <div class="zv-tile-value">{{ number_format((float) $convViewsToBookings, 2) }}%</div>
      <div class="zv-tile-sub text-capitalize">Bookings created ÷ profile views</div>
    </div>

    <div class="zv-tile">
      <div class="zv-tile-label">Conversion (Enquiry → Booking)</div>
      <div class="zv-tile-value">{{ number_format((float) $convEnquiryToBooking, 2) }}%</div>
      <div class="zv-tile-sub text-capitalize">Bookings created ÷ enquiries</div>
    </div>
  </div>

</div>