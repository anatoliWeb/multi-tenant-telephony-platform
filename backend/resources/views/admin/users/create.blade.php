@extends('layouts.app')

@section('title', 'Create User')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['label' => 'Users', 'url' => route('admin.users.index')],
        ['label' => 'Create']
    ]" />
@endsection

@section('content')
    <header class="page-header">
        <div>
            <h1 class="page-title">Create User</h1>
            <p class="page-subtitle">Create a new user and assign initial access settings.</p>
        </div>
    </header>

    @if ($errors->any())
        <div class="c-alert c-alert--error">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.users.store') }}" class="c-form">
        @csrf

        <section class="c-form__section">
            <h2 class="c-form__title">Basic Info</h2>

            <div class="c-form__grid">
                <div class="c-form__group">
                    <label for="name" class="c-form__label">Name</label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" class="c-form__input">
                </div>

                <div class="c-form__group">
                    <label for="email" class="c-form__label">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" class="c-form__input">
                </div>

                <div class="c-form__group">
                    <label for="password" class="c-form__label">Password</label>
                    <input id="password" type="password" name="password" class="c-form__input" placeholder="New password">
                </div>
            </div>
        </section>

        <section class="c-form__section">
            <h2 class="c-form__title">Roles</h2>
            <div class="c-check-grid">
                @foreach($roles as $role)
                    <label class="c-check">
                        <input type="checkbox" name="roles[]" value="{{ $role->id }}" {{ in_array($role->id, old('roles', [])) ? 'checked' : '' }}>
                        <span>{{ $role->name }}</span>
                    </label>
                @endforeach
            </div>
        </section>

        <section class="c-form__section">
            <h2 class="c-form__title">Direct Permissions</h2>
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
            <button type="submit" class="c-btn c-btn--primary">Create User</button>
        </div>
    </form>
@endsection
