<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

use App\Http\Middleware\CorsMiddleware;
use App\Http\Middleware\PermissionMiddleware;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\LogRequestMiddleware;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\SetRequestLocale;
use App\Http\Middleware\ExternalChatScopeMiddleware;

use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Application bootstrap configuration.
 *
 * This file is responsible for:
 * - routing setup (web, api, console, custom routes)
 * - global middleware registration
 * - exception handling configuration
 *
 * Acts as the central entry point for application configuration in modern Laravel.
 */
return Application::configure(basePath: dirname(__DIR__))

    /**
     * ------------------------------------------------------------
     * Routing Configuration
     * ------------------------------------------------------------
     *
     * Registers all route groups used in the application:
     * - web routes (session, CSRF, views)
     * - API routes (stateless, JSON)
     * - console commands
     * - health check endpoint
     */
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',

        /**
         * Register additional route groups AFTER Laravel
         * finishes its internal routing setup.
         *
         * Used for admin panel with custom middleware stack.
         */
        then: function (): void {
            Route::middleware(['web'])
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )

    /**
     * ------------------------------------------------------------
     * Middleware Configuration
     * ------------------------------------------------------------
     *
     * Registers global and aliased middleware.
     */
    ->withMiddleware(function (Middleware $middleware): void {
        /**
         * Sanctum stateful API mode for embedded admin SPA.
         *
         * WHY:
         * Vue admin is served from the same Laravel application and should
         * authenticate API requests via existing session cookies when no
         * Bearer token is provided.
         *
         * This enables hybrid auth:
         * - session/cookie auth for web-embedded admin SPA
         * - Bearer token auth for token-based API clients
         */
        $middleware->statefulApi();
        $middleware->api(prepend: [
            SetRequestLocale::class,
            EnsureFrontendRequestsAreStateful::class,
        ]);

        /**
         * WHY:
         * Feature tests post to web auth/profile routes without CSRF tokens.
         * Laravel Breeze-style tests assume CSRF is not enforced in testing.
         */
        $argv = implode(' ', $_SERVER['argv'] ?? []);
        $isRunningTests = str_contains($argv, 'artisan') && str_contains($argv, 'test');

        if ($isRunningTests) {
            $middleware->web(remove: [
                PreventRequestForgery::class,
            ]);
        }

        /**
         * Global middleware configuration.
         *
         * WHY:
         * This is the central place to define middleware execution order
         * and aliases for the entire application.
         *
         * Order matters here — middleware are executed sequentially.
         */

        /**
         * CORS Middleware (must be FIRST).
         *
         * WHY:
         * - Handles preflight (OPTIONS) requests before hitting application logic
         * - Ensures CORS headers are always attached to responses
         * - Prevents frontend blocking due to missing headers
         *
         * NOTE:
         * This replaces any nginx-level CORS handling,
         * keeping behavior consistent across environments.
         */
        $middleware->prepend(CorsMiddleware::class);
        $middleware->append(SecurityHeadersMiddleware::class);

        /**
         * Request logging middleware.
         *
         * WHY:
         * - Logs every incoming request for debugging and monitoring
         * - Captures method, URL, user, response status and execution time
         * - Helps identify performance bottlenecks and failing endpoints
         *
         * NOTE:
         * Placed AFTER CORS to ensure even preflight requests are handled properly.
         */
        $middleware->append(LogRequestMiddleware::class);

        /**
         * Middleware aliases.
         *
         * WHY:
         * Provides readable and maintainable route definitions:
         *
         * Example:
         * ->middleware('permission:users.edit')
         * ->middleware('role:admin')
         *
         * Instead of using full class names everywhere.
         */
        $middleware->alias([
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'external.chat.scope' => ExternalChatScopeMiddleware::class,
        ]);
    })

    /**
     * ------------------------------------------------------------
     * Exception Handling
     * ------------------------------------------------------------
     *
     * Centralized API exception rendering.
     *
     * WHY:
     * All API responses must follow the same JSON structure
     * regardless of where the exception originates.
     *
     * This guarantees:
     * - predictable frontend behavior
     * - shared response contract
     * - easier Angular/Vue integration
     * - cleaner API debugging
     *
     * IMPORTANT:
     * API routes must NEVER return default Laravel HTML pages.
     */
    ->withExceptions(function (Exceptions $exceptions): void {

        /**
         * Validation Exception
         *
         * WHY:
         * Frontend expects structured validation errors
         * for forms and API requests.
         */
        $exceptions->render(function (
            ValidationException $e,
                                $request
        ) {

            if ($request->is('api/*')) {

                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        /**
         * Authentication Exception
         *
         * WHY:
         * Returned when user is not authenticated.
         */
        $exceptions->render(function (
            AuthenticationException $e,
                                    $request
        ) {

            if ($request->is('api/*')) {

                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                    'errors' => [],
                ], 401);
            }
        });

        /**
         * Authorization Exception
         *
         * WHY:
         * Returned when authenticated user
         * does not have required permissions.
         */
        $exceptions->render(function (
            AuthorizationException $e,
                                   $request
        ) {

            if ($request->is('api/*')) {

                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden',
                    'errors' => [],
                ], 403);
            }
        });

        /**
         * Model Not Found Exception
         *
         * WHY:
         * Prevents Laravel default HTML 404 responses
         * for missing models.
         */
        $exceptions->render(function (
            ModelNotFoundException $e,
                                   $request
        ) {

            if ($request->is('api/*')) {

                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found',
                    'errors' => [],
                ], 404);
            }
        });

        /**
         * Route Not Found Exception
         *
         * WHY:
         * API endpoints should always return JSON,
         * even for invalid routes.
         */
        $exceptions->render(function (
            NotFoundHttpException $e,
                                  $request
        ) {

            if ($request->is('api/*')) {

                return response()->json([
                    'success' => false,
                    'message' => 'Endpoint not found',
                    'errors' => [],
                ], 404);
            }
        });

        /**
         * HTTP Exception (abort(401/403/...))
         *
         * WHY:
         * Permission middleware and guards use abort() which throws HttpException.
         * We must preserve original status code for API consumers and tests.
         */
        $exceptions->render(function (
            HttpExceptionInterface $e,
            $request
        ) {
            if ($request->is('api/*')) {
                $status = $e->getStatusCode();
                $message = $e->getMessage();

                if ($message === '') {
                    $message = match ($status) {
                        401 => 'Unauthenticated',
                        403 => 'Forbidden',
                        404 => 'Resource not found',
                        default => 'Request failed',
                    };
                }

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'errors' => [],
                ], $status);
            }
        });

        /**
         * Fallback Exception Handler
         *
         * WHY:
         * Catches unexpected server errors
         * and prevents leaking sensitive information.
         *
         * SECURITY:
         * Production environment should NEVER expose
         * internal exception details or stack traces.
         */
        $exceptions->render(function (
            \Throwable $e,
                       $request
        ) {

            if ($request->is('api/*')) {

                $message = app()->environment('production')
                    ? 'Server error'
                    : $e->getMessage();

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'errors' => [],
                ], 500);
            }
        });

    })

    /**
     * Create and return application instance
     */
    ->create();
