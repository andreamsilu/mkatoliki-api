<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DirectoryListRequest;
use App\Http\Requests\ImportRequest;
use App\Http\Requests\TransferParishRequest;
use App\Http\Resources\DirectoryResource;
use App\Models\AuditLog;
use App\Models\DataSource;
use App\Models\ImportBatch;
use App\Models\ParishHistory;
use App\Services\AuditService;
use App\Services\DirectoryService;
use App\Services\ImportService;
use App\Support\ApiResponse;
use App\Support\EntityRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class GovernanceController extends Controller
{
    public function transfer(TransferParishRequest $request, string $id, DirectoryService $directory): JsonResponse
    {
        return ApiResponse::success((new DirectoryResource($directory->transfer((int) $id, $request->validated(), $request->user())))->resolve($request));
    }

    public function history(DirectoryListRequest $request, string $id): JsonResponse
    {
        Gate::authorize('view', EntityRegistry::model('parishes')->newQuery()->findOrFail($id));

        return ApiResponse::page(ParishHistory::where('parish_id', $id)->latest('id')->paginate($request->integer('per_page', 25)));
    }

    public function sources(DirectoryListRequest $request): JsonResponse
    {
        Gate::authorize('sources.manage');

        return ApiResponse::page(DataSource::orderBy('name')->orderBy('id')->paginate($request->integer('per_page', 25)));
    }

    public function storeSource(Request $request, AuditService $audit): JsonResponse
    {
        Gate::authorize('sources.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('data_sources')->where('version', $request->input('version'))],
            'type' => ['required', 'string', 'max:60'], 'publisher' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:5000'], 'version' => ['required', 'string', 'max:40'],
            'publication_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'], 'description' => ['nullable', 'string', 'max:5000'],
        ]);
        $source = DB::transaction(function () use ($data, $request, $audit): DataSource {
            $source = DataSource::create($data);
            $audit->record($request->user(), 'created', 'data_sources', $source->id, new: $source->attributesToArray());

            return $source;
        });

        return ApiResponse::success($source, 201);
    }

    public function audits(DirectoryListRequest $request): JsonResponse
    {
        Gate::authorize('audit.read');

        return ApiResponse::page(AuditLog::latest('id')->paginate($request->integer('per_page', 25)));
    }

    public function imports(DirectoryListRequest $request): JsonResponse
    {
        Gate::authorize('imports.manage');

        return ApiResponse::page(ImportBatch::select(['id', 'source_id', 'entity_type', 'status', 'created_by', 'reviewed_by', 'reviewed_at', 'created_at'])->latest('id')->paginate($request->integer('per_page', 25)));
    }

    public function stageImport(ImportRequest $request, ImportService $imports): JsonResponse
    {
        $data = $request->validated();

        return ApiResponse::success($imports->stage($data['entity_type'], $data['source_id'], $data['rows'], $request->user()), 201);
    }

    public function showImport(Request $request, string $id): JsonResponse
    {
        Gate::authorize('imports.manage');

        return ApiResponse::success(ImportBatch::findOrFail($id));
    }

    public function commitImport(Request $request, string $id, ImportService $imports): JsonResponse
    {
        Gate::authorize('imports.manage');
        $request->validate(['reviewed' => ['required', 'accepted']]);

        return ApiResponse::success($imports->commit((int) $id, $request->user()));
    }
}
