@extends('superadmin.layout')
@section('title','Create Team')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/team-create.css') }}">
@endpush

@section('content')
<div class="zv-page">
  <a class="btn btn-outline-dark zv-btn" href="{{ route('superadmin.teams.index') }}">
      <i class="bi bi-arrow-left"></i> Back
    </a>
  <div class="zv-head">
    <div>
         <h1 class="zv-title text-center">Create Team</h1>
         <p class="zv-sub text-capitalize text-center">
           Pick a Manager, then assign Agents.
           <br>Agents already in a team are clearly marked.
         </p>
       </div>
   
    
    
  </div>

  <form method="post" action="{{ route('superadmin.teams.store') }}" class="zv-card p-3 p-md-4">
    @csrf

    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Team name</label>
        <input
          type="text"
          name="name"
          value="{{ old('name') }}"
          class="form-control"
          placeholder="e.g. Ops Team A"
          required
        >
        @error('name')
          <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
      </div>

      <div class="col-md-6">
        <label class="form-label">Manager (optional)</label>
        <select name="manager_id" class="form-select">
          <option value="">— No Manager —</option>

          @foreach($managers as $m)
            @php
              $t = $managerTeamMap[$m->id] ?? null;
              $currentManagerId = old('manager_id');
              $isSelected = (string)$currentManagerId === (string)$m->id;

              // manager can only belong to one active team
              $disable = $t && !$isSelected;
            @endphp

            <option value="{{ $m->id }}" @selected($isSelected) @disabled($disable)>
              {{ $m->username ?? $m->name }} — {{ $m->role_label ?? 'Manager' }}
              @if($t)
                (Already has team: {{ $t['team_name'] }})
              @endif
            </option>
          @endforeach
        </select>

        @error('manager_id')
          <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
      </div>

      <div class="col-12">
        <div class="zv-split">
          <div>
            <label class="form-label mb-1">Assign Agents</label>
            <div class="text-muted small text-capitalize">
              Agents already assigned to a team show their current team & manager.
            </div>
          </div>
          <input id="agentSearch" class="form-control zv-search" placeholder="Search Agents...">
        </div>

        <div class="zv-members">
          @foreach($agents as $a)
            @php
              $display = $a->username ?? $a->name ?? ('User #'.$a->id);
              $info = $agentTeamMap[$a->id] ?? null;
            @endphp

            <label class="zv-member {{ $info ? 'zv-member-assigned' : '' }}"
                   data-name="{{ strtolower($display) }}">

              <input
                type="checkbox"
                name="agent_ids[]"
                value="{{ $a->id }}"
                @checked(is_array(old('agent_ids')) && in_array($a->id, old('agent_ids')))
              >

              <span class="zv-avatar">{{ strtoupper(mb_substr($display,0,1)) }}</span>

              <span class="zv-minfo">
                <span class="zv-mname">{{ $display }}</span>
                <span class="zv-mrole">{{ $a->role_label ?? 'Agent' }}</span>

                @if($info)
                  <span class="zv-team-pill">
                    Assigned · {{ $info['team_name'] }}
                    <span class="zv-team-manager">({{ $info['manager'] }})</span>
                  </span>
                @else
                  <span class="zv-team-pill zv-team-pill-ok">Unassigned</span>
                @endif
              </span>
            </label>
          @endforeach
        </div>

        @error('agent_ids')
          <div class="text-danger small mt-2">{{ $message }}</div>
        @enderror
      </div>

      <div class="col-12 d-flex gap-2 justify-content-end pt-2">
        <a href="{{ route('superadmin.teams.index') }}" class="btn btn-outline-dark">Cancel</a>
        <button class="btn btn-dark bg-black">Create Team</button>
      </div>
    </div>
  </form>
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