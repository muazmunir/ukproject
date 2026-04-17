{{-- resources/views/superadmin/admins/index.blade.php --}}
@extends('superadmin.layout')

@section('title', 'Manage Admins')

@section('content')
<div class="container my-4">

    <h2 class="fw-bold mb-4">Manage Admin Users</h2>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <table class="table table-bordered table-striped align-middle">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Locked?</th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>
            @foreach($admins as $a)
            <tr>
                <td>{{ $a->id }}</td>
                <td>{{ $a->first_name }} {{ $a->last_name }}</td>
                <td>{{ $a->email }}</td>
                <td>
                    @if($a->is_locked)
                        <span class="badge bg-danger">Locked</span>
                    @else
                        <span class="badge bg-success">Active</span>
                    @endif
                </td>

                <td>
                    @if(!$a->is_locked)
                        <form method="POST" action="{{ route('superadmin.admins.lock', $a->id) }}">
                            @csrf
                            <button class="btn btn-sm btn-danger">Lock</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('superadmin.admins.unlock', $a->id) }}">
                            @csrf
                            <button class="btn btn-sm btn-success">Unlock</button>
                        </form>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{ $admins->links() }}

</div>
@endsection
