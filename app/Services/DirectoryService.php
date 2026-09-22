<?php

namespace App\Services;

use App\Models\Deanery;
use App\Models\DirectoryEntity;
use App\Models\Family;
use App\Models\Jumuiya;
use App\Models\Member;
use App\Models\Parish;
use App\Models\ParishHistory;
use App\Models\User;
use App\Models\Zone;
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
            $this->validateOperationalAssignments($model);
            if ($id) {
                $this->hierarchy->preventUnsafeReparenting($model);
            } else {
                Gate::forUser($actor)->authorize('create', $model);
            }
            // Recheck the destination scope when a leaf record changes parish.
            $parentFields = array_keys(EntityRegistry::definition($entity)['parents']);
            if ($id && $parentFields !== [] && $model->isDirty($parentFields)) {
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

    public function delete(string $entity, int $id, User $actor): void
    {
        DB::transaction(function () use ($entity, $id, $actor): void {
            $model = EntityRegistry::model($entity)->newQuery()->lockForUpdate()->findOrFail($id);
            Gate::forUser($actor)->authorize('delete', $model);
            $this->ensureNoDependents($entity, $model);
            $before = $model->attributesToArray();
            $model->delete();
            $this->audit->record($actor, 'deleted', $entity, $id, $before);
            DB::afterCommit(fn () => $this->invalidateCache());
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

    private function validateOperationalAssignments(DirectoryEntity $entity): void
    {
        if ($entity instanceof Member) {
            $this->validateMemberLeadership($entity);

            return;
        }

        $assignments = match (true) {
            $entity instanceof Zone => ['leader_member_id' => 'zone_id'],
            $entity instanceof Jumuiya => ['leader_member_id' => 'jumuiya_id', 'secretary_member_id' => 'jumuiya_id'],
            $entity instanceof Family => ['head_member_id' => 'family_id'],
            default => [],
        };

        foreach ($assignments as $field => $membershipField) {
            $memberId = $entity->{$field};
            if ($memberId === null) {
                continue;
            }

            $member = Member::query()->lockForUpdate()->find($memberId);
            if (! $member || ! $entity->exists || (int) $member->{$membershipField} !== (int) $entity->id) {
                throw ValidationException::withMessages([
                    $field => 'The selected leader must be a member of this organization.',
                ]);
            }
        }
    }

    private function validateMemberLeadership(Member $member): void
    {
        if (! $member->exists) {
            return;
        }

        $assignments = [
            ['model' => Zone::class, 'fields' => ['leader_member_id'], 'membership' => 'zone_id'],
            ['model' => Jumuiya::class, 'fields' => ['leader_member_id', 'secretary_member_id'], 'membership' => 'jumuiya_id'],
            ['model' => Family::class, 'fields' => ['head_member_id'], 'membership' => 'family_id'],
        ];

        foreach ($assignments as $assignment) {
            $query = $assignment['model']::query()->lockForUpdate();
            $query->where(function (Builder $leaders) use ($assignment, $member): void {
                foreach ($assignment['fields'] as $field) {
                    $leaders->orWhere($field, $member->id);
                }
            });
            $organizationId = $query->value('id');
            if ($organizationId && (int) $member->{$assignment['membership']} !== (int) $organizationId) {
                throw ValidationException::withMessages([
                    $assignment['membership'] => 'A designated leader cannot be moved out of the organization they lead.',
                ]);
            }
        }
    }

    private function ensureNoDependents(string $entity, DirectoryEntity $model): void
    {
        foreach (EntityRegistry::ENTITIES as $childDefinition) {
            foreach ($childDefinition['parents'] as $field => $parentDefinition) {
                if ($parentDefinition['model'] === $model::class && $childDefinition['model']::query()->where($field, $model->id)->exists()) {
                    throw ValidationException::withMessages([
                        $entity => 'This record cannot be deleted while dependent records exist.',
                    ]);
                }
            }
        }

        $scopeColumn = match ($entity) {
            'provinces' => 'ecclesiastical_province_id',
            'dioceses' => 'diocese_id',
            'deaneries' => 'deanery_id',
            'parishes' => 'parish_id',
            'zones' => 'zone_id',
            'jumuiyas' => 'jumuiya_id',
            default => null,
        };
        if ($scopeColumn && User::query()->where($scopeColumn, $model->id)->exists()) {
            throw ValidationException::withMessages([
                $entity => 'This record cannot be deleted while administrator accounts are assigned to it.',
            ]);
        }
    }
}
