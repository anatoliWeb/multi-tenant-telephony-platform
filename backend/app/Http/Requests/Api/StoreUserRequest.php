<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for creating a new user.
 *
 * WHY:
 * This request centralizes validation logic for user creation,
 * ensuring that controllers remain clean and focused only on orchestration.
 *
 * Also acts as a contract between frontend and backend,
 * defining exactly what data is allowed and required.
 */
class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the request is authorized.
     *
     * WHY:
     * Authorization is handled via middleware (RBAC / permissions),
     * so this method always returns true to avoid duplication of logic.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for incoming request.
     *
     * WHY:
     * These rules ensure data integrity and prevent invalid or unsafe input
     * from reaching the service layer or database.
     */
    public function rules(): array
    {
        return [

            // WHY:
            // User must have a name for identification in UI and system logs
            'name' => ['required', 'string', 'max:255'],

            // WHY:
            // Email must be unique to prevent account duplication
            // and ensure proper authentication behavior
            'email' => ['required', 'email', 'unique:users,email'],

            // WHY:
            // Password is required on creation and must meet minimum length
            // to ensure basic security standards
            'password' => ['required', 'string', 'min:6'],

            // WHY:
            // Roles are optional but must be valid existing role IDs
            // This ensures RBAC integrity
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['exists:roles,id'],

            // WHY:
            // Direct permissions allow fine-grained access control
            // beyond roles (advanced RBAC usage)
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['exists:permissions,name'],

            // WHY:
            // Denied permissions explicitly block capabilities even if they
            // come from roles or direct permissions.
            'denied_permissions' => ['sometimes', 'array'],
            'denied_permissions.*' => ['exists:permissions,name'],
        ];
    }
}
