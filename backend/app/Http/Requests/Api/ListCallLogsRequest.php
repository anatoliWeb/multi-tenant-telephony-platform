<?php

namespace App\Http\Requests\Api;

use App\Enums\CallLogs\CallDisposition;
use App\Enums\Telephony\TelephonyCallDirection;
use App\Enums\Telephony\TelephonyCallStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCallLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:128'],
            'direction' => ['nullable', Rule::in(array_column(TelephonyCallDirection::cases(), 'value'))],
            'status' => ['nullable', Rule::in(array_column(TelephonyCallStatus::cases(), 'value'))],
            'disposition' => ['nullable', Rule::in(array_column(CallDisposition::cases(), 'value'))],
            'user' => ['nullable', 'integer'],
            'extension' => ['nullable', 'integer'],
            'did' => ['nullable', 'integer'],
            'contact' => ['nullable', 'integer'],
            'from' => ['nullable', 'string', 'max:64'],
            'to' => ['nullable', 'string', 'max:64'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'answered' => ['nullable', Rule::in(['true', 'false', '1', '0'])],
            'provider' => ['nullable', 'string', 'max:32'],
            'sort' => ['nullable', Rule::in(['started_at', 'answered_at', 'ended_at', 'status', 'direction', 'talk_seconds', 'total_seconds'])],
            'direction_sort' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
