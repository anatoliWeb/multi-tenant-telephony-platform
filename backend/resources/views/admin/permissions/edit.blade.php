@extends('layouts.app')

@section('title', 'Edit Permission')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['label' => 'Permissions', 'url' => route('admin.permissions.index')],
        ['label' => 'Edit']
    ]" />
@endsection

@section('content')
    <header class="page-header">
        <div>
            <h1 class="page-title">Edit Permission</h1>
            <p class="page-subtitle">Update capability name and keep naming consistent.</p>
        </div>
    </header>

    <form method="POST" action="{{ route('admin.permissions.update', $permission->id) }}" class="c-form">
        @csrf
        @method('PUT')

        <div class="c-form__group">
            <label for="name" class="c-form__label">Permission Name</label>
            <input id="name" type="text" name="name" value="{{ $permission->name }}" class="c-form__input">
        </div>

        <div class="c-form__actions">
            <button type="submit" class="c-btn c-btn--primary">Save Permission</button>
        </div>
    </form>
@endsection
