<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContentPostResource;
use App\Models\ContentBookmark;
use App\Models\ContentCategory;
use App\Models\ContentMedia;
use App\Models\ContentPost;
use App\Models\ContentReport;
use App\Models\DailyReflection;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CatholicContentController extends Controller
{
    private function filters(Request $request): array
    {
        return $request->validate([
            'locale' => ['sometimes', Rule::in(['sw', 'en'])],
            'category' => ['sometimes', 'string', 'max:100'],
            'format' => ['sometimes', Rule::in(['article', 'audio', 'short'])],
            'season' => ['sometimes', Rule::in(['advent', 'christmas', 'lent', 'easter', 'ordinary'])],
            'q' => ['sometimes', 'string', 'max:100'],
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'sort' => ['sometimes', Rule::in(['recent', 'editorial'])],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['sometimes', 'integer', 'between:1,30'],
        ]);
    }

    private function query(Request $request, bool $filterDate = true, bool $includeBody = false): Builder
    {
        $query = ContentPost::query()->visible()->with(['translations' => function ($translation) use ($request, $includeBody): void {
            $columns = ['id', 'content_post_id', 'locale', 'title', 'summary', 'scripture_reference'];
            if ($includeBody) { $columns = [...$columns, 'body', 'practical_action', 'closing_prayer']; }
            $translation->where('locale', $request->query('locale', 'sw'))->select($columns);
        }, 'author', 'categories', 'tags', 'cover', 'audio', 'reflections'])
            ->whereHas('translations', fn ($translation) => $translation->where('locale', $request->query('locale', 'sw')));
        foreach (['format', 'season'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->query($field));
            }
        }
        if ($request->filled('category')) {
            $query->whereHas('categories', fn ($category) => $category->where('slug', $request->query('category'))->where('is_active', true));
        }
        if ($filterDate && $request->filled('date')) {
            $query->whereDate('published_at', $request->query('date'));
        }
        if ($request->filled('q')) {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $request->query('q')).'%';
            $query->where(function (Builder $search) use ($term, $request): void {
                $search->whereHas('translations', function ($translation) use ($term, $request): void {
                    $translation->where('locale', $request->query('locale', 'sw'))->where(function ($text) use ($term): void {
                        foreach (['title', 'summary', 'body'] as $field) {
                            $text->orWhereRaw("$field LIKE ? ESCAPE '!'", [$term]);
                        }
                    });
                })->orWhereHas('categories', fn ($category) => $category->whereRaw("name LIKE ? ESCAPE '!'", [$term]))
                    ->orWhereHas('tags', fn ($tag) => $tag->whereRaw("name LIKE ? ESCAPE '!'", [$term]));
            });
        }
        return $query;
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        $key = 'content:feed:'.hash('sha256', json_encode($filters).Cache::get('content:revision', 'initial').$request->path());
        $result = Cache::remember($key, 60, function () use ($request): array {
            $query = $this->query($request);
            if ($request->is('api/v1/content/featured')) {
                $query->where('featured', true);
            }
            if ($request->query('sort', 'editorial') === 'editorial') {
                $query->orderByDesc('featured');
            }
            $page = $query->orderByDesc('published_at')->orderByDesc('id')->paginate($request->integer('per_page', 12));
            return ['data' => ContentPostResource::collection($page->getCollection())->resolve($request),
                'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()]];
        });
        return ApiResponse::success($result['data'], meta: $result['meta']);
    }

    public function categories(): JsonResponse
    {
        $key = 'content:categories:'.Cache::get('content:revision', 'initial');
        return ApiResponse::success(Cache::remember($key, 300, fn () => ContentCategory::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()->toArray()));
    }

    public function category(Request $request, string $slug): JsonResponse
    {
        ContentCategory::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $request->query->set('category', $slug);
        return $this->index($request);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $this->filters($request);
        $post = $this->query($request, includeBody: true)->where('slug', $slug)->firstOrFail();
        $request->attributes->set('content_detail', true);
        return ApiResponse::success((new ContentPostResource($post))->resolve($request));
    }

    public function daily(Request $request): JsonResponse
    {
        $this->filters($request);
        $id = DailyReflection::where('locale', $request->query('locale', 'sw'))
            ->whereDate('reflection_date', $request->query('date', now('Africa/Dar_es_Salaam')->toDateString()))->value('content_post_id');
        $post = $id ? $this->query($request, false, true)->whereKey($id)->where('content_type', 'reflection')->first() : null;
        $request->attributes->set('content_detail', true);
        return ApiResponse::success($post ? (new ContentPostResource($post))->resolve($request) : null);
    }

    public function related(Request $request, string $slug): JsonResponse
    {
        $this->filters($request);
        $post = $this->query($request)->where('slug', $slug)->firstOrFail();
        $query = $this->query($request)->whereKeyNot($post->id)->where(function ($related) use ($post): void {
            $related->whereHas('categories', fn ($category) => $category->whereIn('content_categories.id', $post->categories->pluck('id')))
                ->orWhereHas('tags', fn ($tag) => $tag->whereIn('content_tags.id', $post->tags->pluck('id')));
        });
        return ApiResponse::success(ContentPostResource::collection($query->latest('published_at')->latest('id')->limit(6)->get())->resolve($request));
    }

    public function media(ContentMedia $media): BinaryFileResponse
    {
        abort_unless($media->rights_status !== 'unknown' && ContentPost::visible()->where(fn ($query) =>
            $query->where('cover_media_id', $media->id)->orWhere('audio_media_id', $media->id))->exists(), 404);
        abort_unless(Storage::disk('local')->exists($media->storage_path), 404);
        return response()->file(Storage::disk('local')->path($media->storage_path), [
            'Content-Type' => $media->mime_type, 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function bookmarks(Request $request): JsonResponse
    {
        $this->filters($request);
        $ids = ContentBookmark::where('user_id', $request->user()->id)->select('content_post_id');
        $page = $this->query($request)->whereIn('id', $ids)->latest('published_at')->latest('id')->paginate($request->integer('per_page', 12));
        return ApiResponse::page($page, ContentPostResource::collection($page->getCollection())->resolve($request));
    }

    public function bookmark(Request $request): JsonResponse
    {
        $data = $request->validate(['post_id' => ['required', 'integer']]);
        $post = ContentPost::visible()->findOrFail($data['post_id']);
        ContentBookmark::firstOrCreate(['user_id' => $request->user()->id, 'content_post_id' => $post->id]);
        return ApiResponse::success(['bookmarked' => true]);
    }

    public function unbookmark(Request $request, int $postId): JsonResponse
    {
        ContentBookmark::where('user_id', $request->user()->id)->where('content_post_id', $postId)->delete();
        return ApiResponse::success(['bookmarked' => false]);
    }

    public function bookmarkStatus(Request $request, int $postId): JsonResponse
    {
        return ApiResponse::success(['bookmarked' => ContentBookmark::where('user_id', $request->user()->id)->where('content_post_id', $postId)->exists()]);
    }

    public function report(Request $request, string $slug): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']]);
        $post = ContentPost::visible()->where('slug', $slug)->firstOrFail();
        ContentReport::create(['content_post_id' => $post->id, 'reason' => $data['reason']]);
        return ApiResponse::success(['received' => true], 201);
    }
}
