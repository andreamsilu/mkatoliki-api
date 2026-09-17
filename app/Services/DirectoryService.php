<?php

namespace App\Services;

use App\Models\Deanery;
use App\Models\DirectoryEntity;
use App\Models\Parish;
use App\Models\ParishHistory;
use App\Models\User;
use App\Support\EntityRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DirectoryService
{
    public function __construct(private OrganizationScope $scope, private HierarchyValidator $hierarchy, private AuditService $audit) {}

    public function query(string $entity, array $filters = [], ?User $user = null): Builder
    {
        $model = EntityRegistry::model($entity);
        $query = $model->newQuery();
        if ($user) {
            $this->scope->apply($query, $user);
        } else {
            $this->publiclyVisible($query);
        }
        foreach ($filters as $field => $value) {
            if ($field === 'status' || array_key_exists($field, EntityRegistry::definition($entity)['parents'])) {
                $query->where($field, $value);
            } elseif (str_ends_with($field, '_id')) {
                $path = $this->parentPath($entity, $field);
                if ($path) {
                    $query->whereHas($path, fn (Builder $parent) => $parent->whereKey($value));
                }
            }
        }
        if (! empty($filters['q'])) {
            $columns = match ($entity) {
                'families' => ['family_name', 'family_code'],
                'members' => ['first_name', 'middle_name', 'last_name', 'member_code'],
                default => array_intersect(['name', 'name_en', 'code'], $model->getFillable()),
            };
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $query->where(function (Builder $search) use ($columns, $term): void {
                foreach ($columns as $column) {
                    $search->orWhereRaw("LOWER($column) LIKE LOWER(?) ESCAPE '!'", [$term]);
                }
            });
        }

        return $query->orderBy($entity === 'members' ? 'last_name' : ($entity === 'families' ? 'family_name' : 'name'))->orderBy('id');
    }

    public function publiclyVisible(Builder $query): void
    {
        $query->where('status', 'active');
        $entity = EntityRegistry::key($query->getModel());
        $parent = match ($entity) {
            'provinces' => null, 'dioceses' => 'province', 'deaneries' => 'diocese',
            'parishes' => 'deanery', 'jumuiyas' => 'zone', default => 'parish',
        };
        if ($parent) {
            $query->whereHas($parent, function (Builder $ancestor): void {
                $this->publiclyVisible($ancestor);
            });
        }
    }

    private function parentPath(string $entity, string $field): ?string
    {
        foreach (EntityRegistry::definition($entity)['parents'] as $parentField => $definition) {
            $relation = $parentField === 'ecclesiastical_province_id' ? 'province' : Str::beforeLast($parentField, '_id');
            if ($parentField === $field) {
                return $relation;
            }
            $path = $this->parentPath(EntityRegistry::key(new $definition['model']), $field);
            if ($path) {
                return $relation.'.'.$path;
            }
        }

        return null;
    }

    public function save(string $entity, array $attributes, User $actor, ?int $id = null): DirectoryEntity
    {
        return DB::transaction(function () use ($entity, $attributes, $actor, $id): DirectoryEntity {
            $model = EntityRegistry::model($entity);
            if ($id) {
                $model = $model->newQuery()->lockForUpdate()->findOrFail($id);
                Gate::forUser($actor)->authorize('update', $model);
            }
            $before = $model->attributesToArray();
            $model->fill($attributes);
            $this->hierarchy->validate($model);
            if ($id) {
                $this->hierarchy->preventUnsafeReparenting($model);
            } else {
                Gate::forUser($actor)->authorize('create', $model);
            }
            // Recheck the destination scope when a leaf record changes parish.
            if ($id && $model->isDirty(array_keys(EntityRegistry::definition($entity)['parents']))) {
                Gate::forUser($actor)->authorize('create', $model);
            }
            if (! $id && EntityRegistry::definition($entity)['public']) {
                $model->verification_status = 'verified';
                $model->verified_at = now();
            }
            $model->save();
            $this->audit->record($actor, $id ? 'updated' : 'created', $entity, $model->id, $before, $model->attributesToArray());
            DB::afterCommit(fn () => $this->invalidateCache());

            return $model->refresh();
        }, 3);
    }

    public function transfer(int $id, array $attributes, User $actor): Parish
    {
        return DB::transaction(function () use ($id, $attributes, $actor): Parish {
            $parish = Parish::query()->lockForUpdate()->findOrFail($id);
            Gate::forUser($actor)->authorize('transfer', $parish);
            $destination = Deanery::query()->lockForUpdate()->findOrFail($attributes['new_deanery_id']);
            abort_unless($this->scope->contains($actor, $destination), 403);
            if ((int) $parish->deanery_id === (int) $destination->id) {
                throw ValidationException::withMessages(['new_deanery_id' => 'The parish already belongs to this deanery.']);
            }
            $lastDate = ParishHistory::where('parish_id', $id)->max('effective_date');
            if ($lastDate && $attributes['effective_date'] < $lastDate) {
                throw ValidationException::withMessages(['effective_date' => 'The effective date must not precede the latest transfer.']);
            }
            $before = $parish->attributesToArray();
            ParishHistory::create($attributes + [
                'parish_id' => $id, 'old_deanery_id' => $parish->deanery_id,
                'old_diocese_id' => $parish->deanery?->diocese_id, 'new_diocese_id' => $destination->diocese_id,
            ]);
            $parish->update(['deanery_id' => $destination->id, 'source_id' => $attributes['source_id']]);
            $this->audit->record($actor, 'transferred', 'parishes', $id, $before, $parish->attributesToArray());
            DB::afterCommit(fn () => $this->invalidateCache());

            return $parish->refresh();
        }, 3);
    }

    public function invalidateCache(): void
    {
        Cache::forever('directory:revision', (string) Str::uuid());
    }
}
