<?php

namespace App\Services;

use App\Http\Requests\DirectoryWriteRequest;
use App\Models\ImportBatch;
use App\Models\User;
use App\Support\EntityRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ImportService
{
    public function __construct(private DirectoryService $directory, private HierarchyValidator $hierarchy, private AuditService $audit) {}

    public function stage(string $entity, int $sourceId, array $rows, User $actor): ImportBatch
    {
        Gate::forUser($actor)->authorize('imports.manage');
        abort_unless(EntityRegistry::definition($entity)['public'], 422);
        $normalized = array_map(function (array $row): array {
            $row = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $row);
            if (isset($row['code']) && is_string($row['code'])) {
                $row['code'] = strtoupper($row['code']);
            }
            ksort($row);

            return $row;
        }, $rows);
        $checksum = hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
        $existing = ImportBatch::where(['source_id' => $sourceId, 'entity_type' => $entity, 'checksum' => $checksum])->first();
        if ($existing) {
            return $existing;
        }
        $report = $this->inspect($entity, $sourceId, $normalized);

        return DB::transaction(function () use ($actor, $entity, $sourceId, $normalized, $checksum, $report): ImportBatch {
            $batch = ImportBatch::create([
                'source_id' => $sourceId, 'entity_type' => $entity, 'created_by' => $actor->id,
                'checksum' => $checksum, 'rows' => $normalized, 'report' => $report,
                'status' => $report['invalid'] > 0 ? 'invalid' : 'staged',
            ]);
            $this->audit->record($actor, 'import.staged', 'import_batches', $batch->id, new: ['entity_type' => $entity, 'report' => $report]);
            if ($report['invalid'] > 0) {
                Log::notice('directory.import_invalid', ['batch_id' => $batch->id, 'invalid_rows' => $report['invalid']]);
            }

            return $batch;
        });
    }

    public function commit(int $id, User $reviewer): ImportBatch
    {
        Gate::forUser($reviewer)->authorize('imports.manage');

        return DB::transaction(function () use ($id, $reviewer): ImportBatch {
            $batch = ImportBatch::query()->lockForUpdate()->findOrFail($id);
            if ($batch->status === 'committed') {
                return $batch;
            }
            abort_unless($batch->status === 'staged', 409);
            $report = $this->inspect($batch->entity_type, $batch->source_id, $batch->rows);
            if ($report['invalid'] > 0) {
                throw ValidationException::withMessages(['rows' => 'The staged records are no longer valid. Stage a corrected batch before committing.']);
            }
            foreach ($batch->rows as $row) {
                $model = EntityRegistry::model($batch->entity_type);
                $existing = $model->newQuery()->where('code', $row['code'])->lockForUpdate()->first();
                $data = Validator::make($row, $this->rules($batch->entity_type, $existing?->id))->validated();
                $data['source_id'] = $batch->source_id;
                $this->directory->save($batch->entity_type, $data, $reviewer, $existing?->id);
            }
            $batch->update(['status' => 'committed', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'report' => $report]);
            $this->audit->record($reviewer, 'import.committed', 'import_batches', $batch->id, new: ['entity_type' => $batch->entity_type, 'report' => $report]);

            return $batch->refresh();
        }, 3);
    }

    private function inspect(string $entity, int $sourceId, array $rows): array
    {
        $seen = [];
        $results = [];
        foreach ($rows as $index => $row) {
            try {
                $code = $row['code'] ?? null;
                $existing = is_string($code) ? EntityRegistry::model($entity)->newQuery()->where('code', $code)->first() : null;
                $validated = Validator::make($row, $this->rules($entity, $existing?->id))->validate();
                if (isset($seen[$code])) {
                    throw ValidationException::withMessages(['code' => 'Duplicate code within this batch.']);
                }
                $seen[$code] = true;
                $model = $existing ?? EntityRegistry::model($entity);
                $model->fill($validated + ['source_id' => $sourceId]);
                DB::transaction(function () use ($model, $existing): void {
                    $this->hierarchy->validate($model);
                    if ($existing) {
                        $this->hierarchy->preventUnsafeReparenting($model);
                    }
                });
                $results[] = ['row' => $index + 1, 'valid' => true, 'operation' => $existing ? 'update' : 'create'];
            } catch (ValidationException $exception) {
                $results[] = ['row' => $index + 1, 'valid' => false, 'errors' => $exception->errors()];
            }
        }

        return ['total' => count($rows), 'valid' => count(array_filter($results, fn ($result) => $result['valid'])), 'invalid' => count(array_filter($results, fn ($result) => ! $result['valid'])), 'rows' => $results];
    }

    private function rules(string $entity, ?int $id): array
    {
        $rules = DirectoryWriteRequest::rulesFor($entity);
        $rules['code'] = ['required', 'string', 'max:64', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/', Rule::unique(EntityRegistry::model($entity)->getTable(), 'code')->ignore($id)];
        $rules['source_id'] = ['prohibited'];
        $rules['status'] = ['prohibited'];

        return $rules;
    }
}
