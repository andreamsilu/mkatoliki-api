<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DirectoryListRequest;
use App\Http\Requests\DirectoryWriteRequest;
use App\Http\Resources\DirectoryResource;
use App\Models\User;
use App\Services\DirectoryService;
use App\Support\ApiResponse;
use App\Support\EntityRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DirectoryController extends Controller
{
    public function __construct(private DirectoryService $directory) {}

    public function index(DirectoryListRequest $request, string $entity): JsonResponse
    {
        return $this->cached($request, function () use ($request, $entity): JsonResponse {
            $page = $this->directory->query($entity, $request->validated(), $this->actor($request))->paginate($request->integer('per_page', 25));

            return ApiResponse::page($page, DirectoryResource::collection($page->getCollection())->resolve($request));
        });
    }

    public function show(Request $request, string $id, string $entity): JsonResponse
    {
        return $this->cached($request, function () use ($request, $entity, $id): JsonResponse {
            $model = $this->directory->query($entity, user: $this->actor($request))->findOrFail($id);
            if ($this->actor($request)) {
                Gate::authorize('view', $model);
            }

            return ApiResponse::success((new DirectoryResource($model))->resolve($request));
        });
    }

    public function store(DirectoryWriteRequest $request, string $entity): JsonResponse
    {
        $model = $this->directory->save($entity, $request->validated(), $request->user());

        return ApiResponse::success((new DirectoryResource($model))->resolve($request), 201);
    }

    public function update(DirectoryWriteRequest $request, string $id, string $entity): JsonResponse
    {
        $model = $this->directory->save($entity, $request->validated(), $request->user(), (int) $id);

        return ApiResponse::success((new DirectoryResource($model))->resolve($request));
    }

    public function children(DirectoryListRequest $request, string $id, string $entity, string $child): JsonResponse
    {
        return $this->cached($request, function () use ($request, $entity, $id, $child): JsonResponse {
            $parent = $this->directory->query($entity)->findOrFail($id);
            $foreignKey = collect(EntityRegistry::definition($child)['parents'])->search(fn (array $definition) => $parent instanceof $definition['model']);
            abort_unless($foreignKey, 404);
            $query = $this->directory->query($child, $request->validated())->where($foreignKey, $parent->id);
            $page = $query->paginate($request->integer('per_page', 25));

            return ApiResponse::page($page, DirectoryResource::collection($page->getCollection())->resolve($request));
        });
    }

    public function context(Request $request, string $id): JsonResponse
    {
        return $this->cached($request, function () use ($request, $id): JsonResponse {
            $parish = $this->directory->query('parishes')->with('deanery.diocese.province')->findOrFail($id);
            $records = ['parish' => $parish, 'deanery' => $parish->deanery, 'diocese' => $parish->deanery?->diocese, 'ecclesiastical_province' => $parish->deanery?->diocese?->province];

            return ApiResponse::success(collect($records)->map(fn ($model) => (new DirectoryResource($model))->resolve($request)));
        });
    }

    public function structure(Request $request, string $id): JsonResponse
    {
        return $this->cached($request, function () use ($request, $id): JsonResponse {
            $actor = $this->actor($request);
            $parish = $this->directory->query('parishes', user: $actor)->findOrFail($id);
            if ($actor) {
                Gate::authorize('view', $parish);
            }
            $data = ['parish' => (new DirectoryResource($parish))->resolve($request)];
            $meta = [];
            $entities = ['outstations', 'zones', 'jumuiyas', 'associations', 'choirs', 'ministries'];
            if ($actor) {
                $entities = array_merge($entities, ['families', 'members']);
            }
            $routePrefix = $actor ? 'api.v1.admin.' : 'api.v1.';
            foreach ($entities as $entity) {
                $query = $this->directory->query($entity, ['parish_id' => $id], $actor);
                $total = (clone $query)->count();
                $data[$entity] = DirectoryResource::collection($query->limit(100)->get())->resolve($request);
                $meta[$entity] = ['total' => $total, 'truncated' => $total > 100, 'url' => route($routePrefix.$entity.'.index', ['parish_id' => $id])];
            }

            return ApiResponse::success($data, meta: $meta);
        });
    }

    public function search(DirectoryListRequest $request): JsonResponse
    {
        return $this->cached($request, function () use ($request): JsonResponse {
            $union = null;
            foreach (EntityRegistry::publicKeys() as $entity) {
                $query = $this->directory->query($entity, $request->validated())->reorder()
                    ->select(['id', 'code', 'name', 'name_en'])->selectRaw('? as entity_type', [$entity])->toBase();
                $union = $union ? $union->unionAll($query) : $query;
            }
            $page = DB::query()->fromSub($union, 'directory_search')->orderBy('name')->orderBy('entity_type')->orderBy('id')->paginate($request->integer('per_page', 25));

            return ApiResponse::page($page);
        });
    }

    private function actor(Request $request): ?User
    {
        return $request->attributes->get('directory_private', false) ? $request->user() : null;
    }

    private function cached(Request $request, \Closure $callback): JsonResponse
    {
        if ($this->actor($request)) {
            return $callback();
        }
        $query = $request->query();
        ksort($query);
        $key = 'directory:'.Cache::get('directory:revision', 'initial').':'.hash('sha256', $request->path().json_encode($query));
        $payload = Cache::remember($key, now()->addSeconds(config('core.cache_ttl')), fn () => $callback()->getData(true));

        return response()->json($payload);
    }
}
