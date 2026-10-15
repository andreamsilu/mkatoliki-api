<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveContentRequest;
use App\Models\ContentCategory;
use App\Models\ContentMedia;
use App\Models\ContentPost;
use App\Models\ContentReport;
use App\Models\ContentTag;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ContentPublishing;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ContentEditorialController extends Controller
{
    public function __construct(private ContentPublishing $publishing) {}

    public function index(Request $request): JsonResponse
    {
        $this->publishing->authorize($request->user(), 'content.manage', write: false);
        $data = $request->validate(['q' => ['sometimes', 'string', 'max:100'], 'status' => ['sometimes', Rule::in(['draft', 'in_review', 'approved', 'published', 'archived'])],
            'category' => ['sometimes', 'integer'], 'author_id' => ['sometimes', 'integer'], 'date' => ['sometimes', 'date_format:Y-m-d'],
            'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'between:1,50']]);
        $query = ContentPost::with(['translations', 'categories', 'tags', 'author'])->latest('id');
        if (! $this->publishing->allowed($request->user(), 'content.review', false)) {
            $query->where('author_id', $request->user()->id);
        }
        foreach (['status', 'author_id'] as $field) {
            if (isset($data[$field])) { $query->where($field, $data[$field]); }
        }
        if (isset($data['date'])) { $query->whereDate('published_at', $data['date']); }
        if (isset($data['category'])) { $query->whereHas('categories', fn ($category) => $category->whereKey($data['category'])); }
        if (isset($data['q'])) {
            $query->whereHas('translations', fn ($translation) => $translation->where('title', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $data['q']).'%'));
        }
        return ApiResponse::page($query->paginate($request->integer('per_page', 20)));
    }

    public function show(Request $request, ContentPost $post): JsonResponse
    {
        $this->publishing->authorize($request->user(), 'content.manage', $post, false);
        return ApiResponse::success($post->load(['translations', 'categories', 'tags', 'reviews', 'reflections', 'cover', 'audio']));
    }

    public function store(SaveContentRequest $request): JsonResponse
    {
        return ApiResponse::success($this->publishing->save($request->user(), $request->validated()), 201);
    }

    public function update(SaveContentRequest $request, ContentPost $post): JsonResponse
    {
        return ApiResponse::success($this->publishing->save($request->user(), $request->validated(), $post));
    }

    public function transition(Request $request, ContentPost $post): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['submit', 'approve', 'reject', 'publish', 'schedule', 'unpublish', 'archive'])],
            'notes' => ['nullable', 'required_if:action,reject', 'string', 'max:5000'],
            'scheduled_at' => ['nullable', 'required_if:action,schedule', 'date', 'after:now'],
        ]);
        return ApiResponse::success($this->publishing->transition($request->user(), $post, $data['action'], $data['notes'] ?? null, $data['scheduled_at'] ?? null));
    }

    public function destroy(Request $request, ContentPost $post, AuditService $audit): JsonResponse
    {
        DB::transaction(function () use ($request, $post, $audit): void {
            $record = ContentPost::query()->lockForUpdate()->findOrFail($post->id);
            $this->publishing->authorize($request->user(), 'content.manage', $record);
            abort_unless($record->status === 'draft', 409, 'Only drafts can be deleted. Archive published content.');
            $record->reflections()->delete();
            $record->delete();
            $audit->record($request->user(), 'content.deleted', 'content_posts', $record->id);
        });
        $this->publishing->invalidate();
        return ApiResponse::success(['deleted' => true]);
    }

    public function taxonomy(Request $request): JsonResponse
    {
        $this->publishing->authorize($request->user(), 'content.manage', write: false);
        $authors = User::query()->whereIn('id', ContentPost::select('author_id'));
        if (! $this->publishing->allowed($request->user(), 'content.review', false)) { $authors->whereKey($request->user()->id); }
        return ApiResponse::success(['authors' => $authors->orderBy('name')->limit(500)->get(['id', 'name']), 'categories' => ContentCategory::orderBy('sort_order')->get(), 'tags' => ContentTag::orderBy('name')->limit(1000)->get(),
            'can_review' => $this->publishing->allowed($request->user(), 'content.review'), 'can_publish' => $this->publishing->allowed($request->user(), 'content.publish')]);
    }

    public function saveTaxonomy(Request $request, AuditService $audit, string $kind, ?int $id = null): JsonResponse
    {
        $this->publishing->authorize($request->user(), 'content.publish');
        abort_unless(in_array($kind, ['categories', 'tags'], true), 404);
        $rules = ['name' => ['required', 'string', 'max:100'], 'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/', Rule::unique('content_'.$kind)->ignore($id)]];
        if ($kind === 'categories') {
            $rules += ['description' => ['nullable', 'string', 'max:2000'], 'icon' => ['required', Rule::in(['book-open', 'church', 'heart-handshake', 'music', 'landmark', 'circle-help', 'users', 'newspaper'])], 'sort_order' => ['required', 'integer', 'between:0,1000'], 'is_active' => ['required', 'boolean']];
        }
        $model = $kind === 'categories' ? new ContentCategory : new ContentTag;
        $record = $id ? $model->findOrFail($id) : $model;
        $attributes = $request->validate($rules);
        DB::transaction(function () use ($record, $attributes, $audit, $request, $kind): void {
            $record->fill($attributes)->save();
            $audit->record($request->user(), 'content.taxonomy_saved', 'content_'.$kind, $record->id);
        });
        $this->publishing->invalidate();
        return ApiResponse::success($record);
    }

    public function mediaIndex(Request $request): JsonResponse
    {
        $this->publishing->authorize($request->user(), 'content.manage', write: false);
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        $query = ContentMedia::latest('id');
        if (! $this->publishing->allowed($request->user(), 'content.review', false)) { $query->where('uploaded_by', $request->user()->id); }
        return ApiResponse::page($query->paginate(30));
    }

    public function previewMedia(Request $request, ContentMedia $media): BinaryFileResponse
    {
        $this->publishing->authorize($request->user(), 'content.manage', write: false);
        abort_unless((int) $media->uploaded_by === (int) $request->user()->id || $this->publishing->allowed($request->user(), 'content.review', false), 403);
        abort_unless(Storage::disk('local')->exists($media->storage_path), 404);
        return response()->file(Storage::disk('local')->path($media->storage_path), ['Content-Type' => $media->mime_type, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function upload(Request $request, AuditService $audit): JsonResponse
    {
        $this->publishing->authorize($request->user(), 'content.manage');
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,mp3,m4a,ogg,wav', 'max:20480'],
            'alt_text' => ['nullable', 'string', 'max:255'], 'source_name' => ['required', 'string', 'max:255'],
            'source_url' => ['nullable', 'url:https', 'max:2000'],
            'rights_status' => ['required', Rule::in(['original', 'public_domain', 'licensed', 'permission', 'unknown'])],
            'license_details' => ['nullable', 'required_if:rights_status,licensed,permission', 'string', 'max:5000'],
            'attribution' => ['required', 'string', 'max:2000'], 'duration_seconds' => ['nullable', 'integer', 'between:1,86400'],
        ]);
        $file = $request->file('file');
        if (str_starts_with($file->getMimeType(), 'image/') && empty($data['alt_text'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['alt_text' => 'Describe the image for screen-reader users.']);
        }
        if (str_starts_with($file->getMimeType(), 'image/')) {
            $request->validate(['file' => ['image', 'max:5120', 'dimensions:max_width=6000,max_height=6000']]);
        }
        $path = $file->store('catholic-content', 'local');
        abort_unless(is_string($path), 503);
        try {
            unset($data['file']);
            $record = DB::transaction(function () use ($data, $path, $file, $request, $audit): ContentMedia {
                $media = ContentMedia::create($data + ['storage_path' => $path, 'media_type' => str_starts_with($file->getMimeType(), 'image/') ? 'image' : 'audio',
                    'mime_type' => $file->getMimeType(), 'file_size' => $file->getSize(), 'uploaded_by' => $request->user()->id]);
                $audit->record($request->user(), 'content.media_uploaded', 'content_media', $media->id);
                return $media;
            });
        } catch (Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }
        return ApiResponse::success($record, 201);
    }

    public function reports(Request $request): JsonResponse
    {
        $this->publishing->authorize($request->user(), 'content.review', write: false);
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        return ApiResponse::page(ContentReport::with(['post.translations' => fn ($translation) => $translation->where('locale', 'sw')->select(['id', 'content_post_id', 'locale', 'title'])])->whereNull('resolved_at')->latest('id')->paginate(30));
    }

    public function resolve(Request $request, ContentReport $report, AuditService $audit): JsonResponse
    {
        $this->publishing->authorize($request->user(), 'content.review');
        $report->update(['resolved_at' => now()]);
        $audit->record($request->user(), 'content.report_resolved', 'content_reports', $report->id);
        return ApiResponse::success(['resolved' => true]);
    }
}
