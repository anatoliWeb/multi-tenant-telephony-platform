<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UploadChatAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxSizeKb = (int) config('chat.attachments.max_size_kb', 10240);
        $allowedMimes = (array) config('chat.attachments.allowed_mimes', []);

        return [
            'file' => array_merge(
                ['required', 'file', "max:{$maxSizeKb}"],
                ! empty($allowedMimes) ? ['mimetypes:'.implode(',', $allowedMimes)] : []
            ),
        ];
    }
}

