@extends('layouts.app')

@section('title', 'Permissions')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['label' => 'Permissions']
    ]" />
@endsection

@section('content')
    <x-page-header
        title="Permissions"
        subtitle="Access control capabilities used by roles and users."
    >
        <x-slot:actions>
            @can('permissions.create')
                <a href="{{ route('admin.permissions.create') }}" class="c-btn c-btn--primary">Add Permission</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <table class="c-table">
            <thead>
            <tr>
                <th>Name</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse($permissions as $permission)
                    <tr>
                        <td>{{ $permission->name }}</td>
                        <td class="c-table__actions">
                            <x-actions>
                                @can('permissions.edit')
                                    <a href="{{ route('admin.permissions.edit', $permission->id) }}" class="c-btn c-btn--ghost">Edit</a>
                                @endcan
                                @can('permissions.delete')
                                    <form method="POST" action="{{ route('admin.permissions.destroy', $permission->id) }}" onsubmit="return confirm('Delete permission?')">
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
                    <td colspan="2">
                        <div class="c-empty">No data available</div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </x-card>
@endsection
