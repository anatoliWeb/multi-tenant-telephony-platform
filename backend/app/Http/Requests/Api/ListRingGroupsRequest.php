<?php

namespace App\Http\Requests\Api;

use App\Enums\RingGroups\RingGroupStatus;
use App\Enums\RingGroups\RingGroupStrategy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRingGroupsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(RingGroupStatus::values())],
            'strategy' => ['nullable', Rule::in(RingGroupStrategy::values())],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
