<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMemberSacramentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'member_id' => [$required, 'integer', 'exists:members,id'],
            'name' => [$required, 'string', 'max:100'],
            'received_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'place' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['pending', 'verified', 'corrected', 'rejected'])],
        ];
    }
}
