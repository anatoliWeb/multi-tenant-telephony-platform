@extends('layouts.app')

@section('title', 'Roles')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['label' => 'Roles']
    ]" />
@endsection

@section('content')
    <x-page-header
        title="Roles"
        subtitle="Role definitions and attached permission counts."
    >
        <x-slot:actions>
            @can('roles.create')
                {{-- WHY:
                     UI elements are permission-controlled to prevent unauthorized actions.
                     Blade uses @can to reflect backend RBAC rules in UI. --}}
                <a href="{{ route('admin.roles.create') }}" class="c-btn c-btn--primary">Create Role</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <table class="c-table">
            <thead>
            <tr>
                <th>Name</th>
                <th>Permissions</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse($roles as $role)
                    <tr>
                        <td>{{ $role->name }}</td>
                        <td class="c-table__muted">{{ $role->permissions_count }}</td>
                        <td class="c-table__actions">
                            <x-actions>
                                @can('roles.edit')
                                    {{-- WHY:
                                         UI elements are permission-controlled to prevent unauthorized actions.
                                         Blade uses @can to reflect backend RBAC rules in UI. --}}
                                    <a href="{{ route('admin.roles.edit', $role->id) }}" class="c-btn c-btn--ghost">Edit</a>
                                @endcan
                                @can('roles.delete')
                                    @if($role->name !== 'admin')
                                        {{-- WHY:
                                             UI elements are permission-controlled to prevent unauthorized actions.
                                             Blade uses @can to reflect backend RBAC rules in UI. --}}
                                        <form method="POST" action="{{ route('admin.roles.destroy', $role->id) }}" onsubmit="return confirm('Delete role?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="c-btn c-btn--danger">Delete</button>
                                        </form>
                                    @endif
                                @endcan
                            </x-actions>
                        </td>
                    </tr>
            @empty
                <tr>
                    <td colspan="3">
                        <div class="c-empty">No data available</div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </x-card>
@endsection
