<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('directory.verify') && $this->user()->tokenCan('directory:write');
    }

    public function rules(): array
    {
        return ['source_id' => ['required', 'integer', 'exists:data_sources,id'], 'status' => ['required', \Illuminate\Validation\Rule::in(['pending', 'verified', 'needs_review', 'rejected'])], 'notes' => ['nullable', 'string', 'max:5000']];
    }
}
