@extends('superadmin.layout')
@section('title','Manage Team')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/team-manage.css') }}">
@endpush

@section('content')
<div class="zv-page">
   <a class="btn btn-outline-dark zv-btn" href="{{ route('superadmin.teams.index') }}">
      <i class="bi bi-arrow-left"></i> Back
    </a>
   <div>
      <h1 class="zv-title text-center">Manage Team</h1>
      <p class="zv-sub text-capitalize text-center">
        Agents in this team are marked <strong>Current</strong>.
        Agents in other teams are clearly labeled.
      </p>
    </div>
  <div class="zv-head">
   
   
  </div>

  <form method="post" action="{{ route('superadmin.teams.update', $team) }}" class="zv-card p-3 p-md-4">
    @csrf
    @method('PUT')

    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Team name</label>
        <input type="text" name="name"
               value="{{ old('name', $team->name) }}"
               class="form-control" required>
        @error('name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
      </div>

      <div class="col-md-6">
        <label class="form-label">Manager</label>
       <select name="manager_id" class="form-select">
  
  {{-- ✅ Allow removing manager --}}
  <option value="">— No Manager —</option>

  @foreach($managers as $m)
    @php
      $currentManagerId = old('manager_id', $team->manager_id);
      $t = $managerTeamMap[$m->id] ?? null;

      // block if manager already has another team
      $hasOtherTeam = $t && (int)$t['team_id'] !== (int)$team->id;

      $isSelected = (string)$currentManagerId === (string)$m->id;
      $disable = $hasOtherTeam && !$isSelected;
    @endphp

    <option value="{{ $m->id }}" @selected($isSelected) @disabled($disable)>
      {{ $m->username ?? $m->name }} — {{ $m->role_label ?? 'Manager' }}

      @if($t)
        @if((int)$t['team_id'] === (int)$team->id)
          (Current team: {{ $t['team_name'] }})
        @else
          (Already has team: {{ $t['team_name'] }})
        @endif
      @endif
    </option>
  @endforeach
</select>

      </div>

      <div class="col-12">
        <div class="zv-split">
          {{-- <div>
            <label class="form-label mb-1">Agents</label>
            <div class="text-muted small text-capitalize">
              Check to add · Uncheck to remove
            </div>
          </div> --}}
          <input id="agentSearch" class="form-control zv-search" placeholder="Search Agents...">
        </div>

        @php
          $selectedIds = old('agent_ids', $selected ?? []);
          if (!is_array($selectedIds)) $selectedIds = [];
        @endphp

        <div class="zv-members">
          @foreach($agents as $a)
            @php
              $display = $a->username ?? $a->name ?? ('User #'.$a->id);
              $info = $agentTeamMap[$a->id] ?? null;
              $inThisTeam = $info && (int)$info['team_id'] === (int)$team->id;
            @endphp

            <label class="zv-member {{ ($info && !$inThisTeam) ? 'zv-member-assigned' : '' }}"
                   data-name="{{ strtolower($display) }}">

              <input type="checkbox" name="agent_ids[]" value="{{ $a->id }}"
                     @checked(in_array($a->id, $selectedIds))>

              <span class="zv-avatar">{{ strtoupper(mb_substr($display,0,1)) }}</span>

              <span class="zv-minfo">
                <span class="zv-mname">{{ $display }}</span>
                <span class="zv-mrole">{{ $a->role_label ?? 'Agent' }}</span>

                @if($info)
                  @if($inThisTeam)
                    <span class="zv-team-pill zv-team-pill-ok">
                      Current · {{ $info['team_name'] }}
                    </span>
                  @else
                    <span class="zv-team-pill">
                      Assigned · {{ $info['team_name'] }}
                      <span class="zv-team-manager">({{ $info['manager'] }})</span>
                    </span>
                  @endif
                @else
                  <span class="zv-team-pill zv-team-pill-ok">Unassigned</span>
                @endif
              </span>
            </label>
          @endforeach
        </div>
      </div>

    <div class="col-12 d-flex gap-2 justify-content-end pt-2">
  <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteTeamModal">
    Delete Team
  </button>

  <button type="submit" class="btn btn-dark bg-black">Save Changes</button>
</div>



      
    </div>
  </form>


  <!-- Delete Team Modal -->
<div class="modal fade" id="deleteTeamModal" tabindex="-1" aria-labelledby="deleteTeamModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold" id="deleteTeamModalLabel">Delete Team</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <p class="mb-2 text-capitalize">
          Are you sure you want to delete <strong>{{ $team->name }}</strong>?
        </p>
        <div class="text-muted small text-capitalize">
          This will <strong>soft delete</strong> the team, unassign active agents, and deactivate related DM threads.
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Cancel</button>

        <form method="post" action="{{ route('superadmin.teams.destroy', $team) }}">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn btn-danger bg-danger">
            Yes, Delete
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

</div>

@push('scripts')
<script>
(function(){
  const input = document.getElementById('agentSearch');
  const items = document.querySelectorAll('.zv-member');
  input?.addEventListener('input', () => {
    const q = (input.value || '').trim().toLowerCase();
    items.forEach(el => {
      const name = el.getAttribute('data-name') || '';
      el.style.display = (!q || name.includes(q)) ? '' : 'none';
    });
  });
})();
</script>
@endpush
@endsection
