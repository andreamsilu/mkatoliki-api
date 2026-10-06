<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberNotificationRequest extends FormRequest
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
        return [
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'type' => ['sometimes', Rule::in(['announcement', 'event', 'contribution', 'sacrament', 'general'])],
            'audience' => ['required', Rule::in(['all', 'selected'])],
            'member_ids' => ['required_if:audience,selected', 'array', 'max:1000'],
            'member_ids.*' => ['integer', 'distinct', 'exists:members,id'],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
