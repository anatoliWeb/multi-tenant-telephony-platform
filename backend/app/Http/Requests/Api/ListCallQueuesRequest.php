<?php

namespace App\Http\Requests\Api;

use App\Enums\CallQueues\CallQueueStatus;
use App\Enums\CallQueues\CallQueueStrategy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCallQueuesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(CallQueueStatus::values())],
            'strategy' => ['nullable', Rule::in(CallQueueStrategy::values())],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
