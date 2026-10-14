<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyMemberOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'challenge_id' => ['required', 'uuid'],
            'code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }
}
