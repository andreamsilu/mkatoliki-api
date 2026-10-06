<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveParishMassTimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'day_label' => [$required, 'string', 'max:80'],
            'time_label' => [$required, 'string', 'max:160'],
            'display_order' => ['sometimes', 'integer', 'between:0,65535'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
