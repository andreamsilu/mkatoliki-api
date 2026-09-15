<?php

namespace App\Http\Requests;

use App\Support\EntityRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DirectoryListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'q' => [$this->routeIs('api.v1.search') ? 'required' : 'sometimes', 'string', 'min:2', 'max:100'],
            'status' => ['sometimes', Rule::in(EntityRegistry::STATUSES)],
        ];
        foreach (['ecclesiastical_province_id', 'diocese_id', 'deanery_id', 'parish_id', 'outstation_id', 'zone_id', 'jumuiya_id', 'family_id'] as $field) {
            $rules[$field] = ['sometimes', 'integer', 'min:1'];
        }

        return $rules;
    }
}
