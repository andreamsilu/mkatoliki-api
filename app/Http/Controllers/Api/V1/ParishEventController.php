<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveParishEventRequest;
use App\Http\Resources\ParishEventResource;
use App\Models\Parish;
use App\Models\ParishEvent;
use App\Services\AuditService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ParishEventController extends Controller
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
        $records = $parish->events()
            ->when(isset($validated['q']), function (Builder $query) use ($validated): void {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $validated['q']).'%';
                $query->where(fn (Builder $query) => $query->where('title', 'like', $term)->orWhere('venue', 'like', $term));
            })
            ->when(array_key_exists('is_published', $validated), fn ($query) => $query->where('is_published', $validated['is_published']))
            ->orderBy('starts_at')
            ->orderBy('id')
            ->paginate(perPage: $request->integer('per_page', 25), page: $request->integer('page', 1));

        return ApiResponse::page($records, ParishEventResource::collection($records->getCollection())->resolve($request));
    }

    public function store(SaveParishEventRequest $request, Parish $parish, AuditService $audit): JsonResponse
    {
        Gate::authorize('update', $parish);
        $record = $parish->events()->create($request->validated());
        $audit->record($request->user(), 'parish_event.created', 'parish_events', $record->id, new: $record->attributesToArray());

        return ApiResponse::success((new ParishEventResource($record))->resolve($request), 201);
    }

    public function update(SaveParishEventRequest $request, ParishEvent $event, AuditService $audit): JsonResponse
    {
        Gate::authorize('update', $event->parish);
        $before = $event->attributesToArray();
        $event->update($request->validated());
        $audit->record($request->user(), 'parish_event.updated', 'parish_events', $event->id, $before, $event->attributesToArray());

        return ApiResponse::success((new ParishEventResource($event->fresh()))->resolve($request));
    }

    public function destroy(Request $request, ParishEvent $event, AuditService $audit): JsonResponse
    {
        Gate::authorize('update', $event->parish);
        $before = $event->attributesToArray();
        $event->delete();
        $audit->record($request->user(), 'parish_event.deleted', 'parish_events', $event->id, $before);

        return ApiResponse::success(['deleted' => true]);
    }
}
