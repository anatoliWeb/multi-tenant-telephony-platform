@extends('layouts.app')

@section('title', 'Create Role')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['label' => 'Roles', 'url' => route('admin.roles.index')],
        ['label' => 'Create']
    ]" />
@endsection

@section('content')
    <x-page-header
        title="Create Role"
        subtitle="Create a role and define its permission set."
    />

    @if ($errors->any())
        <x-alert type="error">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    <x-card>
        <form method="POST" action="{{ route('admin.roles.store') }}" class="c-form">
            @csrf

            <section class="c-form__section">
                <div class="c-form__group">
                    <label for="name" class="c-form__label">Role Name</label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" class="c-form__input" placeholder="manager">
                </div>
            </section>

            <section class="c-form__section">
                <h2 class="c-form__title">Permissions</h2>
                <div class="c-check-grid">
                    @foreach($permissions as $permission)
                        <label class="c-check">
                            <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" {{ in_array($permission->id, old('permissions', [])) ? 'checked' : '' }}>
                            <span>{{ $permission->name }}</span>
                        </label>
                    @endforeach
                </div>
            </section>

            <div class="c-form__actions">
                <button type="submit" class="c-btn c-btn--primary">Create Role</button>
            </div>
        </form>
    </x-card>
@endsection
