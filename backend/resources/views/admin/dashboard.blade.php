@extends('layouts.app')

@section('title', 'Dashboard')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Dashboard']
    ]" />
@endsection

@section('content')
    <header class="page-header">
        <div>
            <h1 class="page-title">Dashboard</h1>
            <p class="page-subtitle">Overview of platform activity and access metrics.</p>
        </div>
    </header>

    <section class="dashboard-grid">
        <article class="c-card c-card--stat">
            <span class="c-card__label">Total Users</span>
            <div class="c-card__value">{{ $usersCount }}</div>
        </article>

        <article class="c-card c-card--stat">
            <span class="c-card__label">Admins</span>
            <div class="c-card__value">{{ $adminsCount }}</div>
        </article>

        <article class="c-card c-card--stat">
            <span class="c-card__label">Managers</span>
            <div class="c-card__value">{{ $managersCount }}</div>
        </article>

        <article class="c-card c-card--stat">
            <span class="c-card__label">API Tokens</span>
            <div class="c-card__value">{{ $tokensCount }}</div>
        </article>

        <article class="c-card c-card--stat">
            <span class="c-card__label">Users with Direct Permissions</span>
            <div class="c-card__value">{{ $usersWithDirectPermissions }}</div>
        </article>
    </section>

    <section class="dashboard-rail">
        <div class="dashboard-placeholder">
            <h2 class="dashboard-placeholder__title">Traffic & Usage</h2>
            <script>
                window.dashboardData = {
                    usersByRole: {
                        labels: @json($usersByRoleLabels),
                        values: @json($usersByRoleValues),
                    },
                    tokens: {
                        labels: @json($tokensChartLabels),
                        values: @json($tokensChartValues),
                    }
                };
            </script>

            <div class="dashboard-charts">
                <div class="dashboard-chart-card">
                    <h2>Users by Role</h2>
                    <canvas id="usersByRoleChart"></canvas>
                </div>

                <div class="dashboard-chart-card">
                    <h2>API Tokens</h2>
                    <canvas id="tokensChart"></canvas>
                </div>
            </div>
        </div>

        <div class="dashboard-placeholder">
            <h2 class="dashboard-placeholder__title">Recent Activity</h2>
            <div class="activity-list">

                @forelse($recent_activity as $activity)

                    <div class="activity-item">

                        <div class="activity-icon info"></div>

                        <div class="activity-content">

                            <div class="activity-title">
                                @if($activity->user)
                                    <strong>{{ $activity->user->email }}</strong>
                                @endif

                                {{ $activity->description ?? $activity->action }}
                            </div>

                            <div class="activity-time">
                                {{ $activity->created_at->diffForHumans() }}
                            </div>

                        </div>

                    </div>

                @empty
                    <div class="activity-item">
                        <div class="activity-content">
                            <div class="activity-title">No activity yet</div>
                        </div>
                    </div>
                @endforelse

            </div>
        </div>
    </section>
@endsection
