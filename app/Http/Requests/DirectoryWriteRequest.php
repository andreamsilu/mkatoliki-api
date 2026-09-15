<?php

namespace App\Http\Requests;

use App\Support\EntityRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DirectoryWriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('directory.write') && $this->user()->tokenCan('directory:write');
    }

    public function rules(): array
    {
        return self::rulesFor($this->route('entity'), $this->route('id'));
    }

    public static function rulesFor(string $entity, ?string $id = null): array
    {
        $model = EntityRegistry::model($entity);
        $definition = EntityRegistry::definition($entity);
        $rules = [];
        $required = $id ? 'sometimes' : 'required';
        foreach ($model->getFillable() as $field) {
            $rules[$field] = ['sometimes', 'nullable', 'string', 'max:255'];
        }
        foreach ($definition['parents'] as $field => $parent) {
            $rules[$field] = [$parent['nullable'] ? 'sometimes' : $required, $parent['nullable'] ? 'nullable' : 'required', 'integer', Rule::exists((new $parent['model'])->getTable(), 'id')];
        }
        $codeField = match ($entity) { 'families' => 'family_code', 'members' => 'member_code', default => 'code' };
        $rules[$codeField] = [$required, 'required', 'string', 'max:64', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/', Rule::unique($model->getTable(), $codeField)->ignore($id)];
        foreach (match ($entity) { 'families' => ['family_name'], 'members' => ['first_name', 'last_name'], default => ['name'] } as $field) {
            $rules[$field] = [$required, 'required', 'string', 'max:255'];
        }
        $rules['status'] = ['sometimes', 'required', Rule::in(EntityRegistry::STATUSES)];
        foreach (['address', 'description'] as $field) {
            if (isset($rules[$field])) {
                $rules[$field] = ['sometimes', 'nullable', 'string', 'max:5000'];
            }
        }
        foreach (['latitude' => [-90, 90], 'longitude' => [-180, 180]] as $field => [$min, $max]) {
            if (isset($rules[$field])) {
                $rules[$field] = ['sometimes', 'nullable', 'numeric', "between:$min,$max"];
            }
        }
        foreach (['date_of_birth', 'established_at'] as $field) {
            if (isset($rules[$field])) {
                $rules[$field] = ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'];
            }
        }
        if (isset($rules['email'])) {
            $rules['email'] = ['sometimes', 'nullable', 'email:rfc', 'max:255'];
        }
        if (isset($rules['phone'])) {
            $rules['phone'] = ['sometimes', 'nullable', 'string', 'max:32'];
        }
        if (isset($rules['gender'])) {
            $rules['gender'] = [$required, 'required', Rule::in(['male', 'female', 'unspecified'])];
        }
        if (isset($rules['type'])) {
            $rules['type'] = [$required, 'required', Rule::in(['archdiocese', 'diocese'])];
        }
        if (isset($rules['source_id'])) {
            $rules['source_id'] = ['sometimes', 'nullable', 'integer', 'exists:data_sources,id'];
        }
        $rules['verification_status'] = ['prohibited'];
        $rules['verified_at'] = ['prohibited'];

        return $rules;
    }
}
