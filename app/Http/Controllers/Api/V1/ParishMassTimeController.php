<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveParishMassTimeRequest;
use App\Http\Resources\ParishMassTimeResource;
use App\Models\Parish;
use App\Models\ParishMassTime;
use App\Services\AuditService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ParishMassTimeController extends Controller
{
    public function index(Request $request, Parish $parish): JsonResponse
    {
        Gate::authorize('view', $parish);
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'is_published' => ['sometimes', 'boolean'],
        ]);
        $records = $parish->massTimes()
            ->when(array_key_exists('is_published', $validated), fn ($query) => $query->where('is_published', $validated['is_published']))
            ->orderBy('display_order')
            ->orderBy('id')
            ->paginate(perPage: $request->integer('per_page', 25), page: $request->integer('page', 1));

        return ApiResponse::page($records, ParishMassTimeResource::collection($records->getCollection())->resolve($request));
    }

    public function store(SaveParishMassTimeRequest $request, Parish $parish, AuditService $audit): JsonResponse
    {
        Gate::authorize('update', $parish);
        $record = $parish->massTimes()->create($request->validated());
        $audit->record($request->user(), 'parish_mass_time.created', 'parish_mass_times', $record->id, new: $record->attributesToArray());

        return ApiResponse::success((new ParishMassTimeResource($record))->resolve($request), 201);
    }

    public function update(SaveParishMassTimeRequest $request, ParishMassTime $massTime, AuditService $audit): JsonResponse
    {
        Gate::authorize('update', $massTime->parish);
        $before = $massTime->attributesToArray();
        $massTime->update($request->validated());
        $audit->record($request->user(), 'parish_mass_time.updated', 'parish_mass_times', $massTime->id, $before, $massTime->attributesToArray());

        return ApiResponse::success((new ParishMassTimeResource($massTime->fresh()))->resolve($request));
    }

    public function destroy(Request $request, ParishMassTime $massTime, AuditService $audit): JsonResponse
    {
        Gate::authorize('update', $massTime->parish);
        $before = $massTime->attributesToArray();
        $massTime->delete();
        $audit->record($request->user(), 'parish_mass_time.deleted', 'parish_mass_times', $massTime->id, $before);

        return ApiResponse::success(['deleted' => true]);
    }
}
