<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Services\ActivityService;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Admin dashboard controller.
 *
 * Responsible for providing aggregated data for the admin dashboard view.
 *
 * WHY:
 * Instead of performing multiple queries directly in Blade,
 * all metrics are prepared here to keep the view layer clean
 * and maintain a clear separation of concerns.
 */
class DashboardController extends Controller
{
    /**
     * Activity service instance.
     *
     * WHY:
     * Activity data is encapsulated in a dedicated service to allow
     * reuse across API and admin panel without duplicating logic.
     */
    protected ActivityService $activityService;

    /**
     * Inject dependencies.
     *
     * WHY:
     * Using dependency injection keeps the controller testable
     * and avoids tight coupling with concrete implementations.
     */
    public function __construct(ActivityService $activityService)
    {
        $this->activityService = $activityService;
    }

    /**
     * Display dashboard data.
     *
     * WHY:
     * This method aggregates all required statistics in one place
     * to minimize database calls from the view and ensure consistent data structure.
     */
    public function index()
    {
        // WHY:
        // Preload roles with user counts to avoid N+1 queries in charts
        $roles = Role::withCount('users')->get();

        return view('admin.dashboard', [

            // WHY:
            // Core system metrics for quick overview
            'usersCount' => User::count(),

            // WHY:
            // Role-based segmentation helps admins understand system distribution
            'adminsCount' => User::whereHas('roles', fn($q) => $q->where('name', 'admin'))->count(),
            'managersCount' => User::whereHas('roles', fn($q) => $q->where('name', 'manager'))->count(),

            // WHY:
            // Token count reflects API usage and system activity level
            'tokensCount' => PersonalAccessToken::count(),

            // WHY:
            // Direct permissions indicate advanced RBAC usage beyond roles
            'usersWithDirectPermissions' => User::whereHas('permissions')->count(),

            // WHY:
            // Chart data is prepared here to keep Blade simple
            'usersByRoleLabels' => $roles->pluck('name')->toArray(),
            'usersByRoleValues' => $roles->pluck('users_count')->toArray(),

            // WHY:
            // Basic token chart (can be extended later to time-based analytics)
            'tokensChartLabels' => ['Total Tokens'],
            'tokensChartValues' => [PersonalAccessToken::count()],

            // WHY:
            // Recent activity provides audit visibility for admin actions
            'recent_activity' => $this->activityService->getRecent(),
        ]);
    }
}
