<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveParishAnnouncementRequest extends FormRequest
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
            'summary' => [$required, 'string', 'max:5000'],
            'category' => ['sometimes', 'string', 'max:80'],
            'banner' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_banner' => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
