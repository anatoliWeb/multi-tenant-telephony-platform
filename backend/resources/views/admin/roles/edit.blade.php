@extends('layouts.app')

@section('title', 'Edit Role')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['label' => 'Roles', 'url' => route('admin.roles.index')],
        ['label' => 'Edit']
    ]" />
@endsection

@section('content')
    <header class="page-header">
        <div>
            <h1 class="page-title">Edit Role: {{ $role->name }}</h1>
            <p class="page-subtitle">Select permissions for this role.</p>
        </div>
    </header>

    <form method="POST" action="{{ route('admin.roles.update', $role->id) }}" class="c-form">
        @csrf
        @method('PUT')

        <div class="c-check-grid">
            @foreach($permissions as $permission)
                <label class="c-check">
                    <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" {{ $role->permissions->contains($permission->id) ? 'checked' : '' }}>
                    <span>{{ $permission->name }}</span>
                </label>
            @endforeach
        </div>

        <div class="c-form__actions">
            <button type="submit" class="c-btn c-btn--primary">Save Role</button>
        </div>
    </form>
@endsection
