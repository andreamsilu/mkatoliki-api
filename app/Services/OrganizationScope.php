<?php

namespace App\Services;

use App\Models\DirectoryEntity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class OrganizationScope
{
    public function apply(Builder $query, User $user): Builder
    {
        if (! $user->hasPermission('directory.read')) {
            return $query->whereRaw('1 = 0');
        }
        $role = $user->role->name;
        if (in_array($role, ['super_admin', 'tec_admin'], true)) {
            return $query;
        }
        [$column, $id, $table] = match ($role) {
            'diocesan_admin' => ['diocese_id', $user->diocese_id, 'dioceses'],
            'deanery_admin' => ['deanery_id', $user->deanery_id, 'deaneries'],
            'parish_admin' => ['parish_id', $user->parish_id, 'parishes'],
            default => [null, null, null],
        };
        if (! $id) {
            return $query->whereRaw('1 = 0');
        }
        $modelTable = $query->getModel()->getTable();
        if ($modelTable === $table) {
            return $query->whereKey($id);
        }
        $paths = [
            'ecclesiastical_provinces' => [], 'dioceses' => [],
            'deaneries' => ['diocese_id' => 'diocese'],
            'parishes' => ['diocese_id' => 'deanery.diocese', 'deanery_id' => 'deanery'],
        ];
        $path = $paths[$modelTable][$column] ?? null;
        if (! array_key_exists($modelTable, $paths)) {
            $path = match ($column) {
                'diocese_id' => 'parish.deanery.diocese',
                'deanery_id' => 'parish.deanery',
                'parish_id' => 'parish',
            };
        }

        return $path ? $query->whereHas($path, fn (Builder $parent) => $parent->whereKey($id)) : $query->whereRaw('1 = 0');
    }

    public function contains(User $user, DirectoryEntity $entity): bool
    {
        return $this->apply($entity->newQuery(), $user)->whereKey($entity->getKey())->exists();
    }

    public function canCreate(User $user, DirectoryEntity $entity): bool
    {
        if (in_array($user->role?->name, ['super_admin', 'tec_admin'], true)) {
            return true;
        }
        $parent = match ($entity->getTable()) {
            'ecclesiastical_provinces', 'dioceses' => null,
            'deaneries' => $entity->diocese,
            'parishes' => $entity->deanery,
            default => $entity->parish,
        };

        return $parent && $this->contains($user, $parent);
    }
}
