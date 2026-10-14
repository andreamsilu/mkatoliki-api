<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveParishAnnouncementRequest;
use App\Http\Resources\ParishAnnouncementResource;
use App\Models\Parish;
use App\Models\ParishAnnouncement;
use App\Services\AuditService;
use App\Services\ParishBannerStorage;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

class ParishAnnouncementController extends Controller
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
        $records = $parish->announcements()
            ->when(isset($validated['q']), function (Builder $query) use ($validated): void {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $validated['q']).'%';
                $query->where(fn (Builder $query) => $query->where('title', 'like', $term)->orWhere('summary', 'like', $term));
            })
            ->when(array_key_exists('is_published', $validated), fn ($query) => $query->where('is_published', $validated['is_published']))
            ->latest('published_at')
            ->latest('id')
            ->paginate(perPage: $request->integer('per_page', 25), page: $request->integer('page', 1));

        return ApiResponse::page($records, ParishAnnouncementResource::collection($records->getCollection())->resolve($request));
    }

    public function store(
        SaveParishAnnouncementRequest $request,
        Parish $parish,
        AuditService $audit,
        ParishBannerStorage $bannerStorage,
    ): JsonResponse {
        Gate::authorize('update', $parish);
        $attributes = Arr::except($request->validated(), ['banner', 'remove_banner']);
        $bannerPath = $request->hasFile('banner')
            ? $bannerStorage->store($request->file('banner'), $parish->id, 'announcements')
            : null;

        if ($bannerPath !== null) {
            $attributes['banner_path'] = $bannerPath;
        }

        try {
            $record = DB::transaction(function () use ($parish, $attributes, $audit, $request): ParishAnnouncement {
                $record = $parish->announcements()->create($attributes);
                $audit->record($request->user(), 'parish_announcement.created', 'parish_announcements', $record->id, new: $record->attributesToArray());

                return $record;
            });
        } catch (Throwable $exception) {
            $bannerStorage->delete($bannerPath);

            throw $exception;
        }

        return ApiResponse::success((new ParishAnnouncementResource($record))->resolve($request), 201);
    }

    public function update(
        SaveParishAnnouncementRequest $request,
        ParishAnnouncement $announcement,
        AuditService $audit,
        ParishBannerStorage $bannerStorage,
    ): JsonResponse {
        Gate::authorize('update', $announcement->parish);
        $before = $announcement->attributesToArray();
        $previousBannerPath = $announcement->banner_path;
        $attributes = Arr::except($request->validated(), ['banner', 'remove_banner']);
        $newBannerPath = $request->hasFile('banner')
            ? $bannerStorage->store($request->file('banner'), $announcement->parish_id, 'announcements')
            : null;

        if ($newBannerPath !== null) {
            $attributes['banner_path'] = $newBannerPath;
        } elseif ($request->boolean('remove_banner')) {
            $attributes['banner_path'] = null;
        }

        try {
            DB::transaction(function () use ($announcement, $attributes, $audit, $request, $before): void {
                $announcement->update($attributes);
                $audit->record($request->user(), 'parish_announcement.updated', 'parish_announcements', $announcement->id, $before, $announcement->attributesToArray());
            });
        } catch (Throwable $exception) {
            $bannerStorage->delete($newBannerPath);

            throw $exception;
        }

        if (($newBannerPath !== null || $request->boolean('remove_banner')) && $previousBannerPath !== $announcement->banner_path) {
            $bannerStorage->delete($previousBannerPath);
        }

        return ApiResponse::success((new ParishAnnouncementResource($announcement->fresh()))->resolve($request));
    }

    public function destroy(
        Request $request,
        ParishAnnouncement $announcement,
        AuditService $audit,
        ParishBannerStorage $bannerStorage,
    ): JsonResponse {
        Gate::authorize('update', $announcement->parish);
        $before = $announcement->attributesToArray();
        $bannerPath = $announcement->banner_path;
        DB::transaction(function () use ($announcement, $audit, $request, $before): void {
            $announcement->delete();
            $audit->record($request->user(), 'parish_announcement.deleted', 'parish_announcements', $announcement->id, $before);
        });
        $bannerStorage->delete($bannerPath);

        return ApiResponse::success(['deleted' => true]);
    }
}
