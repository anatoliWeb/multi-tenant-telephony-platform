@auth
<aside class="admin-sidebar" data-sidebar>
    <div class="admin-sidebar__header">
        <a href="{{ route('admin.dashboard') }}" class="admin-brand" aria-label="Go to dashboard">
            <span class="admin-brand__dot" aria-hidden="true"></span>
            <span class="admin-brand__name">Platform Admin</span>
        </a>

        <button type="button" class="admin-sidebar__toggle" data-sidebar-toggle aria-label="Toggle sidebar">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/></svg>
        </button>
    </div>

    <nav class="admin-sidebar__nav" aria-label="Sidebar navigation">
        <section class="admin-sidebar__section">
            <h2 class="admin-sidebar__heading">Overview</h2>
            @can('users.view')
                <a href="{{ route('admin.dashboard') }}" class="admin-sidebar__link {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 13h8V3H3z"/><path d="M13 21h8v-6h-8z"/><path d="M13 3v8h8V3z"/><path d="M3 21h8v-6H3z"/></svg>
                    <span class="admin-sidebar__label">Dashboard</span>
                </a>
            @endcan
        </section>

        <section class="admin-sidebar__section">
            <h2 class="admin-sidebar__heading">Management</h2>
            @can('users.view')
                <a href="{{ route('admin.users.index') }}" class="admin-sidebar__link {{ request()->routeIs('admin.users.*') ? 'is-active' : '' }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M20 8v6M23 11h-6"/></svg>
                    <span class="admin-sidebar__label">Users</span>
                </a>
            @endcan

            @can('roles.view')
                <a href="{{ route('admin.roles.index') }}" class="admin-sidebar__link {{ request()->routeIs('admin.roles.*') ? 'is-active' : '' }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/></svg>
                    <span class="admin-sidebar__label">Roles</span>
                </a>
            @endcan

            @can('permissions.view')
                <a href="{{ route('admin.permissions.index') }}" class="admin-sidebar__link {{ request()->routeIs('admin.permissions.*') ? 'is-active' : '' }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4 7v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V7z"/><path d="m9 12 2 2 4-4"/></svg>
                    <span class="admin-sidebar__label">Permissions</span>
                </a>
            @endcan
        </section>

        @can('tokens.view')
            <section class="admin-sidebar__section">
                <h2 class="admin-sidebar__heading">API</h2>
                <a href="{{ route('admin.tokens.index') }}" class="admin-sidebar__link {{ request()->routeIs('admin.tokens.*') ? 'is-active' : '' }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 14a4 4 0 0 1 0-6l3-3a4 4 0 1 1 6 6l-1 1"/><path d="M17 10a4 4 0 0 1 0 6l-3 3a4 4 0 1 1-6-6l1-1"/></svg>
                    <span class="admin-sidebar__label">Tokens</span>
                </a>
            </section>
        @endcan
    </nav>

    <div class="admin-sidebar__footer">
        <span class="admin-userbar__name">{{ auth()->user()->name }}</span>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="c-btn c-btn--ghost admin-sidebar__logout">Logout</button>
        </form>
    </div>
</aside>
@endauth
