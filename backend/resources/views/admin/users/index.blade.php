@extends('layouts.app')

@section('title', 'Users')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['label' => 'Users']
    ]" />
@endsection

@section('content')
    <x-page-header
        title="Users"
        subtitle="Manage user accounts, roles, and access details."
    >
        <x-slot:actions>
            @can('users.create')
                {{-- WHY:
                     UI elements are permission-controlled to prevent unauthorized actions.
                     Blade uses @can to reflect backend RBAC rules in UI. --}}
                <a href="{{ route('admin.users.create') }}" class="c-btn c-btn--primary">Create User</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <table class="c-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Roles</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($users as $user)
                    <tr>
                        <td>{{ $user->id }}</td>
                        <td>{{ $user->name }}</td>
                        <td class="c-table__muted">{{ $user->email }}</td>
                        <td>
                            @foreach($user->roles as $role)
                                <span class="c-badge">{{ $role }}</span>
                            @endforeach
                        </td>
                        <td class="c-table__actions">
                            <x-actions>
                                @can('users.edit')
                                    {{-- WHY:
                                         UI elements are permission-controlled to prevent unauthorized actions.
                                         Blade uses @can to reflect backend RBAC rules in UI. --}}
                                    <a href="{{ route('admin.users.edit', $user->id) }}" class="c-btn c-btn--ghost">Edit</a>
                                @endcan
                                @can('users.delete')
                                    {{-- WHY:
                                         UI elements are permission-controlled to prevent unauthorized actions.
                                         Blade uses @can to reflect backend RBAC rules in UI. --}}
                                    <form method="POST" action="{{ route('admin.users.destroy', $user->id) }}" onsubmit="return confirm('Are you sure?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="c-btn c-btn--danger">Delete</button>
                                    </form>
                                @endcan
                            </x-actions>
                        </td>
                    </tr>
            @empty
                <tr>
                    <td colspan="5">
                        <div class="c-empty">No data available</div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </x-card>
@endsection
