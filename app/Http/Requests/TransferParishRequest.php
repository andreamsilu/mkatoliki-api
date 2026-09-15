<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferParishRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('directory.transfer') && $this->user()->tokenCan('directory:write');
    }

    public function rules(): array
    {
        return ['new_deanery_id' => ['required', 'integer', 'exists:deaneries,id'], 'effective_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'reason' => ['required', 'string', 'max:5000'], 'source_id' => ['required', 'integer', 'exists:data_sources,id']];
    }
}
