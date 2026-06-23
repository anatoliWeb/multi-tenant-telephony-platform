<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Auth')</title>

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="guest-layout">

<main class="guest-container">
    @yield('content')
</main>

</body>
</html>
