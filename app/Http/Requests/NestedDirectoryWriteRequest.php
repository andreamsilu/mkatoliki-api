<?php

namespace App\Http\Requests;

use App\Support\EntityRegistry;

class NestedDirectoryWriteRequest extends DirectoryWriteRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $entity = $this->route('entity');
        $parentEntity = $this->route('parent');
        $parentId = (int) $this->route('id');
        $parentModel = EntityRegistry::model($parentEntity);
        $parent = $parentModel->newQuery()->find($parentId);
        $attributes = [];

        foreach (EntityRegistry::definition($entity)['parents'] as $field => $definition) {
            if ($parentModel instanceof $definition['model']) {
                $attributes[$field] = $parentId;
            } elseif ($parent && array_key_exists($field, $parent->getAttributes())) {
                $attributes[$field] = $parent->getAttribute($field);
            }
        }

        $this->merge($attributes);
    }
}
