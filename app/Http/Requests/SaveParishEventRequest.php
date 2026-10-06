<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveParishEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:255'],
            'starts_at' => [$required, 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'venue' => [$required, 'string', 'max:255'],
            'description' => [$required, 'string', 'max:5000'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
