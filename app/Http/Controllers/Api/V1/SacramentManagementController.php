<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewMemberServiceRequest;
use App\Http\Requests\SaveMemberSacramentRequest;
use App\Http\Resources\MemberSacramentResource;
use App\Http\Resources\MemberServiceRequestResource;
use App\Models\MemberSacrament;
use App\Models\MemberServiceRequest;
use App\Models\Parish;
use App\Services\AuditService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SacramentManagementController extends Controller
{
    public function sacraments(Request $request, Parish $parish): JsonResponse
    {
        Gate::authorize('view', $parish);
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'q' => ['sometimes', 'string', 'min:2', 'max:100'],
            'status' => ['sometimes', Rule::in(['pending', 'verified', 'corrected', 'rejected'])],
        ]);
        $sacraments = MemberSacrament::query()
            ->whereHas('member', fn (Builder $query) => $query->where('parish_id', $parish->id))
            ->with('member:id,member_code,first_name,middle_name,last_name,parish_id')
            ->when(isset($validated['status']), fn (Builder $query) => $query->where('status', $validated['status']))
            ->when(isset($validated['q']), function (Builder $query) use ($validated): void {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $validated['q']).'%';
                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', $term)
                    ->orWhere('place', 'like', $term)
                    ->orWhereHas('member', fn (Builder $member) => $member
                        ->where('member_code', 'like', $term)
                        ->orWhere('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)));
            })
            ->latest('received_on')
            ->latest('id')
            ->paginate(perPage: $request->integer('per_page', 25), page: $request->integer('page', 1));

        return ApiResponse::page($sacraments, MemberSacramentResource::collection($sacraments->getCollection())->resolve($request));
    }

    public function storeSacrament(SaveMemberSacramentRequest $request, Parish $parish, AuditService $audit): JsonResponse
    {
        Gate::authorize('update', $parish);
        $attributes = $request->validated();
        $member = $parish->members()->findOrFail($attributes['member_id']);
        $sacrament = $member->sacraments()->create(collect($attributes)->except('member_id')->all());
        $audit->record($request->user(), 'member_sacrament.created', 'member_sacraments', $sacrament->id, new: $sacrament->attributesToArray());

        return ApiResponse::success((new MemberSacramentResource($sacrament->load('member')))->resolve($request), 201);
    }

    public function updateSacrament(SaveMemberSacramentRequest $request, MemberSacrament $sacrament, AuditService $audit): JsonResponse
    {
        $sacrament->loadMissing('member.parish');
        $parish = $sacrament->member->parish;
        Gate::authorize('update', $parish);
        $attributes = $request->validated();
        if (isset($attributes['member_id'])) {
            $parish->members()->findOrFail($attributes['member_id']);
        }
        $before = $sacrament->attributesToArray();
        $sacrament->update($attributes);
        $audit->record($request->user(), 'member_sacrament.updated', 'member_sacraments', $sacrament->id, $before, $sacrament->attributesToArray());

        return ApiResponse::success((new MemberSacramentResource($sacrament->fresh()->load('member')))->resolve($request));
    }

    public function destroySacrament(Request $request, MemberSacrament $sacrament, AuditService $audit): JsonResponse
    {
        $sacrament->loadMissing('member.parish');
        Gate::authorize('update', $sacrament->member->parish);
        if ($sacrament->serviceRequests()->exists()) {
            throw ValidationException::withMessages([
                'sacrament' => 'A sacrament with service requests cannot be deleted.',
            ]);
        }
        $before = $sacrament->attributesToArray();
        $sacrament->delete();
        $audit->record($request->user(), 'member_sacrament.deleted', 'member_sacraments', $sacrament->id, $before);

        return ApiResponse::success(['deleted' => true]);
    }

    public function serviceRequests(Request $request, Parish $parish): JsonResponse
    {
        Gate::authorize('view', $parish);
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'status' => ['sometimes', Rule::in(['pending', 'approved', 'rejected', 'completed'])],
            'type' => ['sometimes', Rule::in(['certificate', 'correction'])],
        ]);
        $records = MemberServiceRequest::query()
            ->whereHas('member', fn (Builder $query) => $query->where('parish_id', $parish->id))
            ->with(['member:id,member_code,first_name,middle_name,last_name,parish_id', 'sacrament:id,name'])
            ->when(isset($validated['status']), fn (Builder $query) => $query->where('status', $validated['status']))
            ->when(isset($validated['type']), fn (Builder $query) => $query->where('type', $validated['type']))
            ->latest('requested_at')
            ->latest('id')
            ->paginate(perPage: $request->integer('per_page', 25), page: $request->integer('page', 1));

        return ApiResponse::page($records, MemberServiceRequestResource::collection($records->getCollection())->resolve($request));
    }

    public function reviewServiceRequest(ReviewMemberServiceRequest $request, MemberServiceRequest $serviceRequest, AuditService $audit): JsonResponse
    {
        $serviceRequest->loadMissing('member.parish');
        Gate::authorize('update', $serviceRequest->member->parish);
        $before = $serviceRequest->attributesToArray();
        $serviceRequest->update([
            'status' => $request->validated('status'),
            'reviewed_at' => now(),
        ]);
        $audit->record($request->user(), 'member_service_request.reviewed', 'member_service_requests', $serviceRequest->id, $before, $serviceRequest->attributesToArray());

        return ApiResponse::success((new MemberServiceRequestResource($serviceRequest->fresh()->load(['member', 'sacrament'])))->resolve($request));
    }
}
