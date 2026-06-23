<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request validation for updating authenticated user's profile.
 *
 * WHY:
 * This request defines a clear contract for profile updates
 * and ensures that only valid and safe data is persisted.
 *
 * It also prevents duplication of validation logic in controllers.
 */
class ProfileUpdateRequest extends FormRequest
{
    /**
     * Validation rules for profile update.
     *
     * WHY:
     * Ensures that user data remains consistent and unique
     * while allowing safe updates to existing records.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [

            // WHY:
            // Name is required for user identification in UI and system records
            'name' => ['required', 'string', 'max:255'],

            // WHY:
            // Email must:
            // - be valid format
            // - be stored in lowercase for consistency
            // - remain unique across users
            // - allow current user to keep their existing email
            'email' => [
                'required',
                'string',

                // WHY:
                // Normalize email to lowercase to avoid duplicates like:
                // Test@mail.com vs test@mail.com
                'lowercase',

                'email',
                'max:255',

                // WHY:
                // Ignore current user's ID to allow updating profile
                // without triggering unique constraint violation
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }
}
