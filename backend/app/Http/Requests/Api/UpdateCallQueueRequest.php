<?php

namespace App\Http\Requests\Api;

use App\Enums\CallQueues\CallQueueStatus;
use App\Enums\CallQueues\CallQueueStrategy;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCallQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->tenantId();
        $queueId = $this->route('callQueue')?->getKey();

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:128',
                Rule::unique('call_queues', 'slug')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($queueId),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'strategy' => ['sometimes', Rule::in(CallQueueStrategy::values())],
            'status' => ['sometimes', Rule::in(CallQueueStatus::values())],
            'max_wait_time_seconds' => ['sometimes', 'integer', 'min:5', 'max:7200'],
            'ring_timeout_seconds' => ['sometimes', 'integer', 'min:1', 'max:600'],
            'retry_delay_seconds' => ['sometimes', 'integer', 'min:0', 'max:600'],
            'max_attempts' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'music_on_hold' => ['sometimes', 'nullable', 'string', 'max:128'],
            'announce_position' => ['sometimes', 'boolean'],
            'announce_estimated_wait' => ['sometimes', 'boolean'],
            'overflow_destination_type' => ['sometimes', 'nullable', Rule::in(['extension', 'user', 'ring_group', 'queue'])],
            'overflow_destination_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'settings' => ['sometimes', 'nullable', 'array'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
