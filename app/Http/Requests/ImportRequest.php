<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('imports.manage') && $this->user()->tokenCan('directory:write');
    }

    public function rules(): array
    {
        return ['entity_type' => ['required', \Illuminate\Validation\Rule::in(array_keys(array_filter(\App\Support\EntityRegistry::ENTITIES, fn (array $definition) => $definition['public'])))], 'source_id' => ['required', 'integer', 'exists:data_sources,id'], 'rows' => ['required', 'array', 'min:1', 'max:500'], 'rows.*' => ['required', 'array']];
    }
}
