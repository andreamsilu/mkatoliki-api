<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;

class UpdateAdministratorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('administrators.manage');
    }

    public function rules(): array
    {
        return [
            'password' => ['sometimes', 'required', 'string', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
