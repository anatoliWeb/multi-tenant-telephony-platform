<?php

namespace App\Http\Requests\Api;

class UpdateSystemSettingRequest extends StoreSystemSettingRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['key'][0] = 'sometimes';
        $rules['label'][0] = 'sometimes';
        $rules['group'][0] = 'sometimes';
        $rules['type'][0] = 'sometimes';

        return $rules;
    }
}

