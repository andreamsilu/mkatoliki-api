<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewContributionPaymentRequest extends FormRequest
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
            'status' => ['required', Rule::in(['confirmed', 'rejected'])],
            'gateway_reference' => ['nullable', 'string', 'max:128'],
            'receipt_number' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('contribution_payments', 'receipt_number')->ignore($this->route('payment')),
            ],
        ];
    }
}
