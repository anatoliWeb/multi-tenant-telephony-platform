<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Multi-Tenant Telephony Platform</title>

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body>

<div class="landing">

    <div class="landing-content">

        <h1 class="landing-title">
            Multi-Tenant Telephony Platform
        </h1>

        <p class="landing-subtitle">
            Modern backend architecture with API, authentication, and admin panel.
        </p>

        <div class="landing-actions">
            <a href="/login" class="btn-primary">Login</a>
            <a href="/admin" class="btn-secondary">Admin Panel</a>
            <a href="#docs" class="btn-link">API Docs</a>
        </div>

    </div>

    <div id="docs" class="landing-docs">

        <h2>API Overview</h2>

        <p>
            This platform provides a RESTful API with authentication, user management,
            and scalable architecture using Laravel and modern frontend technologies.
        </p>

        <ul>
            <li><strong>GET /api/users</strong> — List users</li>
            <li><strong>GET /api/stats</strong> — Application statistics</li>
            <li><strong>POST /api/token</strong> — Generate API token</li>
        </ul>

    </div>

</div>

</body>
</html>
