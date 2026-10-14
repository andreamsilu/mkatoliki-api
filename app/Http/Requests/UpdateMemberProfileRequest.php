<?php

namespace App\Http\Requests;

use App\Support\MemberPhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:255'],
            'middle_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:32', function (string $attribute, mixed $value, Closure $fail): void {
                $phone = is_string($value) ? MemberPhoneNumber::normalize($value) : null;
                if (! $phone || $phone !== MemberPhoneNumber::normalize($this->user()->member->phone)) {
                    $fail('Contact your parish office to change your registered login phone number.');
                }
            }],
            'email' => ['sometimes', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
        ];
    }
}
