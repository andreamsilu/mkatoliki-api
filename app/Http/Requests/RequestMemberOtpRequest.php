<?php

namespace App\Http\Requests;

use App\Support\MemberPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class RequestMemberOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('phone'))) {
            $phone = MemberPhoneNumber::normalize($this->input('phone'));
            if ($phone) {
                $this->merge(['phone' => $phone]);
            }
        }
    }

    public function rules(): array
    {
        return ['phone' => ['required', 'string', 'max:16', 'regex:/^\+[1-9][0-9]{7,14}$/']];
    }
}
