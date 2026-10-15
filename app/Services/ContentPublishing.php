<?php

namespace App\Services;

use App\Models\ContentMedia;
use App\Models\ContentPost;
use App\Models\User;
use App\Policies\ContentPostPolicy;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ContentPublishing
{
    public function __construct(private AuditService $audit, private ContentPostPolicy $policy) {}

    public function allowed(User $user, string $permission, bool $write = true): bool
    {
        return $this->policy->permission($user, $permission, $write);
    }

    public function authorize(User $user, string $permission, ?ContentPost $post = null, bool $write = true): void
    {
        $ability = match ($permission) {
            'content.review' => $write ? 'review' : 'viewReviews',
            'content.publish' => 'publish',
            default => $write ? ($post ? 'update' : 'create') : ($post ? 'view' : 'viewAny'),
        };
        Gate::forUser($user)->authorize($ability, $post ?? ContentPost::class);
    }

    public function invalidate(): void
    {
        Cache::forever('content:revision', (string) Str::uuid());
    }

    public function save(User $actor, array $data, ?ContentPost $post = null): ContentPost
    {
        $this->authorize($actor, 'content.manage', $post);
        $record = DB::transaction(function () use ($actor, $data, $post): ContentPost {
            $record = $post ? ContentPost::query()->lockForUpdate()->findOrFail($post->id) : new ContentPost;
            $this->authorize($actor, 'content.manage', $record->exists ? $record : null);
            $record->fill(Arr::except($data, ['translations', 'category_ids', 'tag_ids', 'reflections']));
            $record->author_id ??= $actor->id;
            $record->status = 'draft';
            $record->reviewer_id = null;
            $record->published_at = null;
            $record->scheduled_at = null;
            foreach (['cover_media_id' => 'image', 'audio_media_id' => 'audio'] as $key => $type) {
                if ($record->$key) {
                    $media = ContentMedia::findOrFail($record->$key);
                    abort_unless($media->media_type === $type, 422);
                    abort_unless((int) $media->uploaded_by === (int) $actor->id || $this->allowed($actor, 'content.review'), 403);
                }
            }
            $record->save();
            $record->categories()->sync($data['category_ids']);
            $record->tags()->sync($data['tag_ids'] ?? []);
            $record->translations()->delete();
            foreach ($data['translations'] as $translation) {
                $record->translations()->create($translation);
            }
            if (isset($data['reflections'])) {
                if ($record->content_type !== 'reflection' && $data['reflections'] !== []) {
                    throw ValidationException::withMessages(['reflections' => 'Only reflections can reserve daily dates.']);
                }
                $record->reflections()->delete();
                foreach ($data['reflections'] as $reflection) {
                    if (! in_array($reflection['locale'], array_column($data['translations'], 'locale'), true)) {
                        throw ValidationException::withMessages(['reflections' => 'A matching translation is required.']);
                    }
                    $record->reflections()->create($reflection);
                }
            }
            if ($record->content_type !== 'reflection') {
                $record->reflections()->delete();
            }
            $record->reviews()->create(['reviewer_id' => $actor->id, 'status' => 'draft', 'reviewed_at' => now()]);
            $this->audit->record($actor, 'content.saved', 'content_posts', $record->id);

            return $record;
        });
        $this->invalidate();

        return $record;
    }

    public function transition(User $actor, ContentPost $post, string $action, ?string $notes, ?string $date): ContentPost
    {
        $record = DB::transaction(function () use ($actor, $post, $action, $notes, $date): ContentPost {
            $record = ContentPost::query()->lockForUpdate()->findOrFail($post->id);
            $permission = match ($action) {
                'approve', 'reject' => 'content.review',
                'publish', 'schedule', 'archive', 'unpublish' => 'content.publish',
                default => 'content.manage',
            };
            $this->authorize($actor, $permission, $record);
            $valid = match ($action) {
                'submit' => $record->status === 'draft',
                'approve', 'reject' => $record->status === 'in_review' && (int) $record->author_id !== (int) $actor->id,
                'publish', 'schedule' => $record->status === 'approved' && $record->reviewer_id !== null,
                'archive', 'unpublish' => in_array($record->status, ['approved', 'published'], true),
                default => false,
            };
            abort_unless($valid, 409, 'This transition is not allowed. Authors cannot review their own content.');
            if (in_array($action, ['approve', 'publish', 'schedule'], true)) {
                $this->assertPublishable($record);
            }
            $record->status = match ($action) {
                'submit' => 'in_review', 'approve', 'schedule' => 'approved', 'publish' => 'published',
                'archive' => 'archived', default => 'draft',
            };
            if ($action === 'approve') {
                $record->reviewer_id = $actor->id;
            }
            if (in_array($action, ['reject', 'unpublish'], true)) {
                $record->reviewer_id = null;
            }
            $record->published_at = $action === 'publish' ? now() : null;
            $record->scheduled_at = $action === 'schedule' ? $date : null;
            $record->save();
            $record->reviews()->create(['reviewer_id' => $actor->id, 'status' => $action, 'review_notes' => $notes, 'reviewed_at' => now()]);
            $this->audit->record($actor, 'content.'.$action, 'content_posts', $record->id);

            return $record;
        });
        $this->invalidate();

        return $record;
    }

    public function assertPublishable(ContentPost $post): void
    {
        if ($post->rights_status === 'unknown' || ! $post->translations()->where('locale', 'sw')->exists()
            || ! $post->categories()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['content' => 'A Kiswahili translation, active category and confirmed text rights are required.']);
        }
        foreach (['cover' => 'image', 'audio' => 'audio'] as $relation => $type) {
            $asset = $post->$relation;
            if ($asset && ($asset->rights_status === 'unknown' || $asset->media_type !== $type || ! Storage::disk('local')->exists($asset->storage_path)
                || (in_array($asset->rights_status, ['licensed', 'permission'], true) && ! $asset->license_details))) {
                throw ValidationException::withMessages(['media' => 'Confirm the source and usage rights of every attached asset.']);
            }
        }
    }

    public function publishDue(): void
    {
        ContentPost::where('status', 'approved')->where('scheduled_at', '<=', now())->select('id')->chunkById(100, function ($posts): void {
            foreach ($posts as $post) {
                try {
                    DB::transaction(function () use ($post): void {
                        $record = ContentPost::query()->lockForUpdate()->find($post->id);
                        if (! $record || $record->status !== 'approved' || ! $record->reviewer_id
                            || (int) $record->reviewer_id === (int) $record->author_id || ! $record->scheduled_at || $record->scheduled_at->isFuture()) {
                            return;
                        }
                        $this->assertPublishable($record);
                        $record->status = 'published';
                        $record->published_at = $record->scheduled_at;
                        $record->scheduled_at = null;
                        $record->save();
                        $record->reviews()->create(['status' => 'scheduled_publish', 'reviewed_at' => now()]);
                        $this->audit->record(null, 'content.scheduled_publish', 'content_posts', $record->id);
                    });
                    $this->invalidate();
                } catch (ValidationException) {
                    // Keep unpublishable scheduled content private for editorial correction.
                }
            }
        });
    }
}
