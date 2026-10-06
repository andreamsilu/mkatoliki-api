<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMemberNotificationRequest;
use App\Http\Resources\MemberNotificationResource;
use App\Models\MemberNotification;
use App\Models\Parish;
use App\Services\AuditService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ParishNotificationController extends Controller
{
    public function index(Request $request, Parish $parish): JsonResponse
    {
        Gate::authorize('view', $parish);
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'type' => ['sometimes', Rule::in(['announcement', 'event', 'contribution', 'sacrament', 'general'])],
            'q' => ['sometimes', 'string', 'min:2', 'max:100'],
        ]);
        $notifications = MemberNotification::query()
            ->whereHas('member', fn (Builder $query) => $query->where('parish_id', $parish->id))
            ->with('member:id,member_code,first_name,middle_name,last_name,parish_id')
            ->when(isset($validated['type']), fn (Builder $query) => $query->where('type', $validated['type']))
            ->when(isset($validated['q']), function (Builder $query) use ($validated): void {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $validated['q']).'%';
                $query->where(fn (Builder $query) => $query->where('title', 'like', $term)->orWhere('message', 'like', $term));
            })
            ->latest('published_at')
            ->latest('id')
            ->paginate(perPage: $request->integer('per_page', 25), page: $request->integer('page', 1));

        return ApiResponse::page($notifications, MemberNotificationResource::collection($notifications->getCollection())->resolve($request));
    }

    public function store(StoreMemberNotificationRequest $request, Parish $parish, AuditService $audit): JsonResponse
    {
        Gate::authorize('update', $parish);
        $attributes = $request->validated();
        $members = $parish->members()
            ->when($attributes['audience'] === 'selected', fn ($query) => $query->whereKey($attributes['member_ids']))
            ->get(['id']);
        if ($attributes['audience'] === 'selected' && $members->count() !== count($attributes['member_ids'])) {
            throw ValidationException::withMessages([
                'member_ids' => 'Every selected member must belong to this parish.',
            ]);
        }
        if ($members->isEmpty()) {
            throw ValidationException::withMessages([
                'audience' => 'No parish members are available for this notification.',
            ]);
        }

        $notifications = DB::transaction(function () use ($attributes, $members): array {
            $records = [];
            foreach ($members as $member) {
                $records[] = MemberNotification::create([
                    'member_id' => $member->id,
                    'title' => $attributes['title'],
                    'message' => $attributes['message'],
                    'type' => $attributes['type'] ?? 'general',
                    'published_at' => $attributes['published_at'] ?? now(),
                ]);
            }

            return $records;
        });
        $firstNotification = $notifications[0];
        $audit->record($request->user(), 'member_notification.sent', 'member_notifications', $firstNotification->id, new: [
            'title' => $attributes['title'],
            'audience' => $attributes['audience'],
            'sent_count' => count($notifications),
        ]);

        return ApiResponse::success([
            'sent_count' => count($notifications),
            'notification' => (new MemberNotificationResource($firstNotification->load('member')))->resolve($request),
        ], 201);
    }

    public function destroy(Request $request, MemberNotification $notification, AuditService $audit): JsonResponse
    {
        $notification->loadMissing('member.parish');
        Gate::authorize('update', $notification->member->parish);
        $before = $notification->attributesToArray();
        $notification->delete();
        $audit->record($request->user(), 'member_notification.deleted', 'member_notifications', $notification->id, $before);

        return ApiResponse::success(['deleted' => true]);
    }
}
