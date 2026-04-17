@extends('superadmin.layout')
@section('title','Teams')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/teams.css') }}">
@endpush

@section('content')
<div class="zv-page">
  <div>
      <h1 class="zv-title text-center">Teams</h1>
      <p class="zv-sub text-capitalize text-center">
        Create teams and assign Agents under Managers.
        Reassignments keep chat history intact.
      </p>
    </div>
  <div class="zv-head">
    

    <a class="btn btn-dark bg-black zv-btn " href="{{ route('superadmin.teams.create') }}">
      <i class="bi bi-people"></i> Create Team
    </a>
  </div>


  <div class="zv-filters zv-card mb-3 p-3">
  <form method="get" class="row g-2 align-items-end">

    <div class="col-md-4">
      <label class="form-label">Search</label>
      <input type="text"
             name="q"
             class="form-control"
             value="{{ request('q') }}"
             placeholder="Search Team Name...">
    </div>

    <div class="col-md-4">
      <label class="form-label">Manager</label>
      <select name="manager_id" class="form-select">
        <option value="">All Managers</option>
        @foreach($managers as $m)
          <option value="{{ $m->id }}" @selected((string)request('manager_id') === (string)$m->id)>
            {{ $m->username ?? $m->name }}
          </option>
        @endforeach
      </select>
    </div>

    <div class="col-md-2">
      <label class="form-label">Status</label>
      <select name="status" class="form-select">
        <option value="">All</option>
        <option value="active" @selected(request('status') === 'active')>Active</option>
        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
      </select>
    </div>

    <div class="col-md-2 d-flex gap-2">
      <button class="btn btn-dark bg-black w-100">Filter</button>
      <a href="{{ route('superadmin.teams.index') }}" class="btn btn-outline-dark w-100">Reset</a>
    </div>

  </form>
</div>


  <div class="zv-card">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="zv-thead">
          <tr>
            <th>Team</th>
            <th>Manager</th>
            <th>Agents</th>
            <th>Status</th>
            <th class="">Action</th>
          </tr>
        </thead>

        <tbody>
        @forelse($teams as $t)
          @php
            $agents = $t->activeMembers->pluck('agent');
            $max = 6;
          @endphp

          <tr>
            {{-- TEAM --}}
            <td>
              <div class="fw-semibold">{{ $t->name }}</div>
              <div class="text-muted small text-capitalize">
                Created {{ optional($t->created_at)->diffForHumans() }}
              </div>
            </td>

            {{-- MANAGER --}}
            <td>
              <div class="fw-semibold">
                {{ $t->manager->username ?? $t->manager->name ?? '—' }}
              </div>
              <div class="text-muted small">
                {{ $t->manager->role_label ?? 'Manager' }}
              </div>
            </td>

            {{-- AGENTS --}}
            <td>
              @if($agents->isEmpty())
                <span class="text-muted small">No agents</span>
              @else
                <div class="zv-agent-stack">
                  @foreach($agents->take($max) as $a)
                    @php
                      $name = $a->username ?? $a->name ?? 'A';
                    @endphp
                    <span class="zv-agent"
                          title="{{ $name }}">
                      {{ strtoupper(mb_substr($name,0,1)) }}
                    </span>
                  @endforeach

                  @if($agents->count() > $max)
                    <span class="zv-agent zv-agent-more">
                      +{{ $agents->count() - $max }}
                    </span>
                  @endif
                </div>

                <div class="text-muted small mt-1">
                  {{ $agents->count() }} active
                </div>
              @endif
            </td>

            {{-- STATUS --}}
            <td>
              @if($t->is_active)
                <span class="badge text-bg-success">Active</span>
              @else
                <span class="badge text-bg-secondary">Inactive</span>
              @endif
            </td>

            {{-- ACTION --}}
            <td class="">
              <a class="btn btn-outline-dark btn-sm"
                 href="{{ route('superadmin.teams.edit', $t) }}">
                Manage
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="text-center text-muted py-5 text-capitalize">
              No teams yet. Create your first team.
            </td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>

    <div class="p-3">
      {{ $teams->links() }}
    </div>
  </div>
</div>
@endsection
