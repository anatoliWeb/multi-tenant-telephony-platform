<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request validation for updating an existing user.
 *
 * WHY:
 * Separates validation logic from controller and defines
 * a clear contract for update operations.
 *
 * Unlike creation, this request must handle partial updates
 * and existing data constraints (e.g. unique email).
 */
class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the request is authorized.
     *
     * WHY:
     * Authorization is enforced via middleware (RBAC),
     * so we avoid duplicating permission logic here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for update request.
     *
     * WHY:
     * Ensures safe updates while allowing flexibility
     * (e.g. optional password change, preserving existing email).
     */
    public function rules(): array
    {
        // WHY:
        // We extract user ID from route to correctly handle
        // unique validation (ignore current user record)
        $userId = (int) $this->route('user');

        return [

            // WHY:
            // Name is required to maintain consistent user identity
            'name' => ['required', 'string', 'max:255'],

            // WHY:
            // Email must remain unique across users,
            // but we ignore the current user's email to allow updating other fields
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($userId),
            ],

            // WHY:
            // Password is optional during update:
            // - if provided → must meet security requirements
            // - if not → existing password remains unchanged
            'password' => ['nullable', 'string', 'min:6'],

            // WHY:
            // Roles can be reassigned during update
            // Must be valid role IDs to preserve RBAC integrity
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['exists:roles,id'],

            // WHY:
            // Direct permissions allow fine-grained access control
            // independent of roles (advanced RBAC scenario)
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['exists:permissions,name'],

            // WHY:
            // Denied permissions override inherited/direct grants and provide
            // explicit access restrictions.
            'denied_permissions' => ['sometimes', 'array'],
            'denied_permissions.*' => ['exists:permissions,name'],
        ];
    }
}
