<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveParishProjectRequest;
use App\Http\Resources\ParishProjectResource;
use App\Models\Parish;
use App\Models\ParishProject;
use App\Services\AuditService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ParishProjectController extends Controller
{
    public function index(Request $request, Parish $parish): JsonResponse
    {
        Gate::authorize('view', $parish);
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'q' => ['sometimes', 'string', 'min:2', 'max:100'],
            'is_published' => ['sometimes', 'boolean'],
        ]);
        $records = $parish->projects()
            ->when(isset($validated['q']), function (Builder $query) use ($validated): void {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $validated['q']).'%';
                $query->where(fn (Builder $query) => $query->where('title', 'like', $term)->orWhere('subtitle', 'like', $term));
            })
            ->when(array_key_exists('is_published', $validated), fn ($query) => $query->where('is_published', $validated['is_published']))
            ->latest('updated_at')
            ->latest('id')
            ->paginate(perPage: $request->integer('per_page', 25), page: $request->integer('page', 1));

        return ApiResponse::page($records, ParishProjectResource::collection($records->getCollection())->resolve($request));
    }

    public function store(SaveParishProjectRequest $request, Parish $parish, AuditService $audit): JsonResponse
    {
        Gate::authorize('update', $parish);
        $record = $parish->projects()->create($request->validated());
        $audit->record($request->user(), 'parish_project.created', 'parish_projects', $record->id, new: $record->attributesToArray());

        return ApiResponse::success((new ParishProjectResource($record))->resolve($request), 201);
    }

    public function update(SaveParishProjectRequest $request, ParishProject $project, AuditService $audit): JsonResponse
    {
        Gate::authorize('update', $project->parish);
        $before = $project->attributesToArray();
        $project->update($request->validated());
        $audit->record($request->user(), 'parish_project.updated', 'parish_projects', $project->id, $before, $project->attributesToArray());

        return ApiResponse::success((new ParishProjectResource($project->fresh()))->resolve($request));
    }

    public function destroy(Request $request, ParishProject $project, AuditService $audit): JsonResponse
    {
        Gate::authorize('update', $project->parish);
        $before = $project->attributesToArray();
        $project->delete();
        $audit->record($request->user(), 'parish_project.deleted', 'parish_projects', $project->id, $before);

        return ApiResponse::success(['deleted' => true]);
    }
}
