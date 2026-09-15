<?php

namespace App\Services;

use App\Models\DirectoryEntity;
use App\Models\User;
use App\Support\EntityRegistry;
use Illuminate\Validation\ValidationException;

final class HierarchyValidator
{
    public function validate(DirectoryEntity $entity): void
    {
        $expected = [];
        $visited = [];
        $pending = [[$entity, 'hierarchy']];
        while ($pending !== []) {
            [$record, $origin] = array_shift($pending);
            foreach (EntityRegistry::definition(EntityRegistry::key($record))['parents'] as $field => $parent) {
                $id = $record->{$field};
                if ($id === null) {
                    continue;
                }
                if (isset($expected[$field]) && $expected[$field] !== (int) $id) {
                    throw ValidationException::withMessages([$origin === 'hierarchy' ? $field : $origin => 'The selected records must have consistent ancestry within the same parish.']);
                }
                $expected[$field] = (int) $id;
                $key = $parent['model'].':'.$id;
                if (isset($visited[$key])) {
                    continue;
                }
                $ancestor = $parent['model']::query()->lockForUpdate()->find($id);
                if (! $ancestor) {
                    throw ValidationException::withMessages([$field => 'The selected parent does not exist.']);
                }
                $visited[$key] = true;
                $pending[] = [$ancestor, $origin === 'hierarchy' ? $field : $origin];
            }
        }
    }

    public function preventUnsafeReparenting(DirectoryEntity $entity): void
    {
        $key = EntityRegistry::key($entity);
        foreach (EntityRegistry::definition($key)['parents'] as $field => $parent) {
            if (! $entity->isDirty($field)) {
                continue;
            }
            if ($key === 'parishes' && $field === 'deanery_id') {
                throw ValidationException::withMessages([$field => 'Use the parish transfer endpoint to preserve history.']);
            }
            foreach (EntityRegistry::ENTITIES as $childDefinition) {
                foreach ($childDefinition['parents'] as $childField => $childParent) {
                    if ($childParent['model'] === $entity::class && $childDefinition['model']::where($childField, $entity->id)->exists()) {
                        throw ValidationException::withMessages([$field => 'An organization with dependent records cannot be reparented.']);
                    }
                }
            }
            $scopeColumn = match ($key) {
                'dioceses' => 'diocese_id', 'deaneries' => 'deanery_id', default => null,
            };
            if ($scopeColumn && User::where($scopeColumn, $entity->id)->exists()) {
                throw ValidationException::withMessages([$field => 'An organization with assigned administrators cannot be reparented.']);
            }
        }
    }
}
