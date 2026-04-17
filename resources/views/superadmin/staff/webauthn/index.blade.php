@extends('superadmin.layout')

@section('title','Passkey Management')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/superadmin-staff-webauthn.css') }}">
@endpush

@section('content')
<section class="card">

    <div class="card__head">
        <div>
            <div class="card__title">Passkey Management</div>
            <div class="muted">
                {{ $user->full_name ?: $user->email }} — {{ $user->email }}
            </div>
        </div>

        <a href="{{ route('superadmin.staff.index') }}" class="btn ghost">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    @if(session('ok'))
        <div class="zv-alert zv-alert--ok" style="margin: 1rem 0;">
            {{ session('ok') }}
        </div>
    @endif

    <div style="margin: 1rem 0;">
        @if($credentials->count())
            <span class="pill ok">
                <i class="bi bi-shield-check"></i>
                {{ $credentials->count() }} Registered
            </span>
        @else
            <span class="pill danger">
                <i class="bi bi-shield-x"></i>
                No Passkeys
            </span>
        @endif
    </div>

    @if($credentials->count())
        <div class="table-wrapp">
            <table class="zv-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Alias</th>
                        <th>Created</th>
                        <th>Last Used</th>
                        <th style="width:120px">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($credentials as $credential)
                        <tr>
                            <td>#{{ $credential->id }}</td>
                            <td>{{ $credential->alias ?? '—' }}</td>
                            <td>{{ optional($credential->created_at)->format('Y-m-d H:i') ?? '—' }}</td>
                            <td>{{ optional($credential->last_used_at)->format('Y-m-d H:i') ?? '—' }}</td>
                            <td>
                                <form method="POST"
                                      action="{{ route('superadmin.staff.webauthn.destroy', [$user->id, $credential->id]) }}"
                                      class="inline"
                                      onsubmit="return confirm('Remove this passkey?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn icon danger" title="Remove Passkey">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <form method="POST"
              action="{{ route('superadmin.staff.webauthn.reset', $user->id) }}"
              onsubmit="return confirm('Reset all passkeys for this account?')"
              style="margin-top:1rem;">
            @csrf
            <button type="submit" class="btn danger">
                <i class="bi bi-exclamation-triangle"></i>
                Reset All Passkeys
            </button>
        </form>
    @endif

</section>
@endsection