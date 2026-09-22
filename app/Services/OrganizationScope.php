<?php

namespace App\Services;

use App\Models\Deanery;
use App\Models\Diocese;
use App\Models\DirectoryEntity;
use App\Models\EcclesiasticalProvince;
use App\Models\Jumuiya;
use App\Models\Parish;
use App\Models\User;
use App\Models\Zone;
use App\Support\EntityRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class OrganizationScope
{
    /**
     * @var array<string, array{field: string, model: class-string<DirectoryEntity>}>
     */
    private const ROLE_SCOPES = [
        'province_admin' => ['field' => 'ecclesiastical_province_id', 'model' => EcclesiasticalProvince::class],
        'diocesan_admin' => ['field' => 'diocese_id', 'model' => Diocese::class],
        'deanery_admin' => ['field' => 'deanery_id', 'model' => Deanery::class],
        'parish_admin' => ['field' => 'parish_id', 'model' => Parish::class],
        'zone_leader' => ['field' => 'zone_id', 'model' => Zone::class],
        'jumuiya_leader' => ['field' => 'jumuiya_id', 'model' => Jumuiya::class],
    ];

    public function apply(Builder $query, User $user): Builder
    {
        if (! $user->hasPermission('directory.read')) {
            return $this->deny($query);
        }

        $role = $user->role?->name;
        if (in_array($role, ['super_admin', 'tec_admin'], true)) {
            return $query;
        }

        $scope = $this->scopeFor($user);
        $model = $query->getModel();
        if (! $scope || ! $model instanceof DirectoryEntity) {
            return $this->deny($query);
        }
        $scopeModel = $scope['model'];
        if ($model instanceof $scopeModel) {
            return $query->whereKey($scope['id']);
        }

        $paths = $this->pathsToScope(EntityRegistry::key($model), $scopeModel);
        if ($paths === []) {
            return $this->deny($query);
        }

        return $query->where(function (Builder $scoped) use ($paths, $scope): void {
            foreach ($paths as $path) {
                $scoped->orWhereHas($path, fn (Builder $parent) => $parent->whereKey($scope['id']));
            }
        });
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

        $scope = $this->scopeFor($user);
        if (! $scope) {
            return false;
        }
        $scopeModel = $scope['model'];
        if ($entity instanceof $scopeModel) {
            return false;
        }

        return $this->recordBelongsToScope($entity, $scopeModel, $scope['id']);
    }

    /**
     * @return array{field: string, model: class-string<DirectoryEntity>, id: int}|null
     */
    private function scopeFor(User $user): ?array
    {
        $definition = self::ROLE_SCOPES[$user->role?->name] ?? null;
        $id = $definition ? (int) $user->getAttribute($definition['field']) : 0;

        return $definition && $id > 0 ? $definition + ['id' => $id] : null;
    }

    /**
     * @param  class-string<DirectoryEntity>  $scopeModel
     * @param  array<string, true>  $visited
     * @return list<string>
     */
    private function pathsToScope(string $entity, string $scopeModel, array $visited = []): array
    {
        if (isset($visited[$entity])) {
            return [];
        }
        $visited[$entity] = true;
        $paths = [];

        foreach (EntityRegistry::definition($entity)['parents'] as $field => $parentDefinition) {
            $relation = $this->relationFor($field);
            $parentModel = $parentDefinition['model'];
            if ($parentModel === $scopeModel) {
                $paths[] = $relation;

                continue;
            }

            $parentEntity = EntityRegistry::key(new $parentModel);
            foreach ($this->pathsToScope($parentEntity, $scopeModel, $visited) as $parentPath) {
                $paths[] = $relation.'.'.$parentPath;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param  class-string<DirectoryEntity>  $scopeModel
     * @param  array<string, true>  $visited
     */
    private function recordBelongsToScope(DirectoryEntity $entity, string $scopeModel, int $scopeId, array $visited = []): bool
    {
        $entityKey = EntityRegistry::key($entity);
        if (isset($visited[$entityKey])) {
            return false;
        }
        $visited[$entityKey] = true;

        foreach (EntityRegistry::definition($entityKey)['parents'] as $field => $parentDefinition) {
            $parentId = (int) $entity->getAttribute($field);
            if ($parentId === 0) {
                continue;
            }

            $parentModel = $parentDefinition['model'];
            if ($parentModel === $scopeModel) {
                return $parentId === $scopeId;
            }

            $parent = $parentModel::query()->find($parentId);
            if ($parent && $this->recordBelongsToScope($parent, $scopeModel, $scopeId, $visited)) {
                return true;
            }
        }

        return false;
    }

    private function relationFor(string $foreignKey): string
    {
        return $foreignKey === 'ecclesiastical_province_id' ? 'province' : Str::beforeLast($foreignKey, '_id');
    }

    private function deny(Builder $query): Builder
    {
        return $query->whereRaw('1 = 0');
    }
}
