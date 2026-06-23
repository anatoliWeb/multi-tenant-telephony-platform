<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Login request validation and authentication handler.
 *
 * WHY:
 * Combines validation, authentication logic, and rate limiting
 * into a single request object to keep controllers clean
 * and ensure consistent login behavior across the application.
 */
class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * WHY:
     * Login is a public action, so no prior authorization is required.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for login request.
     *
     * WHY:
     * Ensures required credentials are present and properly formatted
     * before attempting authentication.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // WHY:
            // Email is required for identifying the user account
            'email' => ['required', 'string', 'email'],

            // WHY:
            // Password is required for authentication attempt
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * WHY:
     * Centralizes authentication logic and integrates rate limiting
     * to prevent brute-force attacks.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        // WHY:
        // Prevent excessive login attempts before even checking credentials
        $this->ensureIsNotRateLimited();

        // WHY:
        // Attempt authentication using provided credentials
        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {

            // WHY:
            // Record failed attempt for rate limiting
            RateLimiter::hit($this->throttleKey());

            // WHY:
            // Do not reveal whether email or password is incorrect
            // to avoid leaking sensitive information
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        // WHY:
        // Clear rate limiter after successful login
        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * WHY:
     * Protects the system from brute-force attacks by limiting
     * the number of login attempts per user/IP combination.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        // WHY:
        // Allow up to 5 attempts before triggering lockout
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        // WHY:
        // Dispatch lockout event for logging/monitoring
        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        // WHY:
        // Inform user about retry delay without exposing internal logic
        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Generate a unique rate limiting key.
     *
     * WHY:
     * Combines normalized email and IP address to:
     * - prevent distributed brute-force attempts
     * - isolate limits per user and client
     */
    public function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower($this->string('email')) . '|' . $this->ip()
        );
    }
}
