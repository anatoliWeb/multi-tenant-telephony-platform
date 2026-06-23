@extends('layouts.app')

@section('title', 'Create Permission')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['label' => 'Permissions', 'url' => route('admin.permissions.index')],
        ['label' => 'Create']
    ]" />
@endsection

@section('content')
    <header class="page-header">
        <div>
            <h1 class="page-title">Create Permission</h1>
            <p class="page-subtitle">Add a new granular capability key.</p>
        </div>
    </header>

    <form method="POST" action="{{ route('admin.permissions.store') }}" class="c-form">
        @csrf

        <div class="c-form__group">
            <label for="name" class="c-form__label">Permission Name</label>
            <input id="name" type="text" name="name" placeholder="permission_name" class="c-form__input">
        </div>

        <div class="c-form__actions">
            <button type="submit" class="c-btn c-btn--primary">Save Permission</button>
        </div>
    </form>
@endsection
