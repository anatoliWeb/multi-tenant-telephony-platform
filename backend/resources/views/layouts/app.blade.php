<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin Panel')</title>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body>
<div class="admin-layout" data-admin-layout>
    @include('layouts.partials.navigation')

    <main class="admin-main">
        <div class="admin-content">
            @hasSection('breadcrumbs')
                @yield('breadcrumbs')
            @endif

            @if(session('success'))
                <x-alert type="success">{{ session('success') }}</x-alert>
            @endif

            @if(session('error'))
                <x-alert type="error">{{ session('error') }}</x-alert>
            @endif

            @if(session('warning'))
                <x-alert type="warning">{{ session('warning') }}</x-alert>
            @endif

            @yield('content')
        </div>
    </main>
</div>

@stack('scripts')
</body>
</html>
